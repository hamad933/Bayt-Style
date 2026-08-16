<?php
namespace App\Commerce;
use App\Models\Variant;
use Illuminate\Http\Request;
class CartService
{
    public function snapshot(Request $request, bool $refreshPriceReference = false): array
    {
        $cart = collect($request->session()->get('cart', []))
            ->mapWithKeys(fn ($quantity, $variantId): array => [(int) $variantId => (int) $quantity])
            ->filter(fn (int $quantity): bool => $quantity > 0)->all();
        $knownPrices = $request->session()->get('cart_prices', []);
        $variants = Variant::query()->with(['product.primaryMedia', 'attributeOptions.attribute'])
            ->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $items = []; $issues = []; $count = 0; $subtotalMinor = 0;
        foreach ($cart as $variantId => $quantity) {
            $variant = $variants->get($variantId);
            if (! $variant) {
                $issues[] = ['type' => 'unavailable', 'variant_id' => $variantId,
                    'message' => 'إحدى القطع في السلة لم تعد متاحة. راجع السلة قبل المتابعة.'];
                continue;
            }
            $currentPrice = number_format((float) $variant->price, 2, '.', '');
            $previousPrice = isset($knownPrices[$variantId])
                ? number_format((float) $knownPrices[$variantId], 2, '.', '') : null;
            $priceChanged = $previousPrice !== null && $previousPrice !== $currentPrice;
            $sellable = $variant->isSellable() && $quantity <= $variant->inventory_quantity;
            if (! $sellable) {
                $issues[] = ['type' => 'unavailable', 'variant_id' => $variantId,
                    'message' => "القطعة {$variant->product->name_ar} بالكمية المطلوبة غير متاحة حاليًا."];
            }
            if ($priceChanged) {
                $issues[] = ['type' => 'price_changed', 'variant_id' => $variantId,
                    'message' => "تغيّر سعر {$variant->product->name_ar}. تم تحديث السلة إلى السعر الحالي؛ راجع الإجمالي قبل المتابعة."];
            }
            if ($previousPrice === null || ($priceChanged && $refreshPriceReference)) {
                $knownPrices[$variantId] = $currentPrice;
            }
            $unitMinor = $this->toMinor($currentPrice);
            $lineMinor = $unitMinor * $quantity;
            $count += $quantity; $subtotalMinor += $lineMinor;
            $selection = $variant->optionSelection();
            $items[] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'product' => $variant->product->name_ar,
                'variant' => $variant->name_ar,
                'option_summary' => $selection ? collect($selection)->values()->implode(' · ') : $variant->name_ar,
                'options' => $selection,
                'sku' => $variant->sku,
                'quantity' => $quantity,
                'price' => number_format($unitMinor / 100, 0),
                'price_minor' => $unitMinor,
                'line_total' => number_format($lineMinor / 100, 0),
                'line_total_minor' => $lineMinor,
                'currency' => $variant->currency,
                'available' => $sellable,
                'price_changed' => $priceChanged,
                'image' => $variant->product->primaryMedia ? asset($variant->product->primaryMedia->path) : null,
            ];
        }
        $request->session()->put('cart_prices', $knownPrices);
        return [
            'count' => $count,
            'subtotal_minor' => $subtotalMinor,
            'subtotal' => number_format($subtotalMinor / 100, 0),
            'total' => number_format($subtotalMinor / 100, 0),
            'currency' => $items[0]['currency'] ?? config('commerce.checkout.currency', 'SAR'),
            'items' => $items,
            'issues' => $issues,
            'checkout_blocked' => count($issues) > 0 || count($items) === 0,
        ];
    }
    public function rememberPrice(Request $request, Variant $variant): void
    {
        $prices = $request->session()->get('cart_prices', []);
        $prices[$variant->id] = number_format((float) $variant->price, 2, '.', '');
        $request->session()->put('cart_prices', $prices);
        $this->invalidateCheckout($request);
    }
    public function forget(Request $request, int $variantId): void
    {
        $prices = $request->session()->get('cart_prices', []);
        unset($prices[$variantId]);
        $request->session()->put('cart_prices', $prices);
        $this->invalidateCheckout($request);
    }
    public function invalidateCheckout(Request $request): void
    {
        $request->session()->forget('checkout_token');
    }
    public function clear(Request $request): void
    {
        $request->session()->forget(['cart', 'cart_prices']);
    }
    private function toMinor(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
