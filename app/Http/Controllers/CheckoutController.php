<?php
namespace App\Http\Controllers;
use App\Commerce\CartService;
use App\Commerce\CheckoutTotals;
use App\Commerce\Shipping\ShippingMethodProvider;
use App\Models\Order;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ShippingMethodProvider $shipping,
        private readonly CheckoutTotals $totals,
    ) {}
    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->snapshot($request, true);
        if ($cart['count'] === 0) return redirect()->route('cart.index')->with('warning','السلة فارغة.');
        if ($cart['issues']) {
            return redirect()->route('cart.index')->with('warning','حدّثنا معلومات السلة. راجع التغييرات قبل متابعة إتمام الطلب.');
        }
        $country = config('commerce.checkout.country_code');
        $methods = $this->shipping->methods($country);
        $shippingCode = $methods[0]['code'] ?? null;
        abort_unless($shippingCode, 503);
        $totals = $this->totals->calculate($cart['subtotal_minor'], $shippingCode, $country, $cart['currency']);
        $token = $request->session()->get('checkout_token') ?: (string) Str::uuid();
        $request->session()->put('checkout_token', $token);
        return view('checkout.show', compact('cart','methods','shippingCode','totals','token'));
    }
    public function store(Request $request): RedirectResponse
    {
        $country = (string) config('commerce.checkout.country_code');
        $methodCodes = collect($this->shipping->methods($country))->pluck('code')->all();
        $validated = $request->validate([
            'checkout_token' => ['required','uuid'],
            'full_name' => ['required','string','min:2','max:160'],
            'email' => ['required','email:rfc','max:254'],
            'phone' => ['required','string','min:7','max:32','regex:/^\+?[0-9 ()-]+$/'],
            'country_code' => ['required', Rule::in([$country])],
            'region' => ['required','string','max:120'],
            'city' => ['required','string','max:120'],
            'district' => ['nullable','string','max:160'],
            'address_line' => ['required','string','max:255'],
            'building_unit' => ['nullable','string','max:120'],
            'postal_code' => ['nullable','string','max:24'],
            'delivery_notes' => ['nullable','string','max:1000'],
            'shipping_method' => ['required', Rule::in($methodCodes)],
            'payment_method' => ['required', Rule::in([config('commerce.checkout.payment_method_code')])],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'يجب الموافقة صراحةً على شروط الإرسال التجريبية قبل تأكيد الطلب.',
        ]);
        $existing = Order::query()->where('idempotency_key', $validated['checkout_token'])->first();
        if ($existing) return redirect()->route('checkout.confirmation', $existing);
        $cart = $this->cart->snapshot($request, true);
        if ($cart['count'] === 0 || $cart['issues']) {
            return redirect()->route('cart.index')->with('warning','تغيّرت معلومات السلة أو التوفر. راجع السلة ثم أعد المتابعة.');
        }
        $totals = $this->totals->calculate(
            $cart['subtotal_minor'], $validated['shipping_method'], $country, $cart['currency']
        );
        $order = DB::transaction(function () use ($validated, $cart, $totals): Order {
            $order = Order::query()->firstOrCreate(
                ['idempotency_key' => $validated['checkout_token']],
                [
                    'order_number' => $this->orderNumber(),
                    'currency' => $totals['currency'],
                    'customer_full_name' => trim($validated['full_name']),
                    'customer_email' => Str::lower(trim($validated['email'])),
                    'customer_phone' => preg_replace('/[\s()-]+/', '', trim($validated['phone'])),
                    'delivery_country_code' => $validated['country_code'],
                    'delivery_region' => trim($validated['region']),
                    'delivery_city' => trim($validated['city']),
                    'delivery_district' => $this->nullableTrim($validated['district'] ?? null),
                    'delivery_address_line' => trim($validated['address_line']),
                    'delivery_building_unit' => $this->nullableTrim($validated['building_unit'] ?? null),
                    'delivery_postal_code' => $this->nullableTrim($validated['postal_code'] ?? null),
                    'delivery_notes' => $this->nullableTrim($validated['delivery_notes'] ?? null),
                    'shipping_method_code' => $totals['shipping_method']['code'],
                    'shipping_method_name' => $totals['shipping_method']['name_ar'],
                    'shipping_amount' => $this->money($totals['shipping_minor']),
                    'tax_policy_code' => $totals['tax_policy_code'],
                    'tax_amount' => $this->money($totals['tax_minor']),
                    'subtotal' => $this->money($totals['subtotal_minor']),
                    'total' => $this->money($totals['total_minor']),
                    'payment_method_code' => config('commerce.checkout.payment_method_code'),
                    'payment_state' => 'pending',
                    'order_state' => 'pending_payment',
                    'reservation_state' => 'not_reserved',
                    'reservation_policy_code' => 'policy_not_activated',
                    'fulfillment_state' => 'not_started',
                    'consent_version' => config('commerce.checkout.consent_version'),
                    'consented_at' => now(),
                ]
            );
            if (! $order->wasRecentlyCreated) return $order;
            foreach ($cart['items'] as $item) {
                $line = $order->lines()->create([
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'product_name' => $item['product'],
                    'variant_name' => $item['variant'],
                    'variant_sku' => $item['sku'],
                    'unit_price' => $this->money($item['price_minor']),
                    'quantity' => $item['quantity'],
                    'line_total' => $this->money($item['line_total_minor']),
                    'currency' => $item['currency'],
                ]);
                $variant = Variant::query()
                    ->with('attributeOptions.attribute')
                    ->findOrFail($item['variant_id']);
                foreach ($variant->attributeOptions as $option) {
                    if (! $option->attribute) continue;
                    $line->options()->create([
                        'attribute_code' => $option->attribute->code,
                        'attribute_name' => $option->attribute->name_ar,
                        'option_value' => $option->value_ar,
                    ]);
                }
            }
            return $order;
        });
        $this->cart->clear($request);
        $request->session()->put('checkout.completed.'.$validated['checkout_token'], $order->id);
        return redirect()->route('checkout.confirmation', $order);
    }
    public function confirmation(Request $request, Order $order): View
    {
        $completed = $request->session()->get('checkout.completed', []);
        abort_unless(in_array($order->id, array_map('intval', $completed), true), 403);
        $order->load('lines.options');
        return view('checkout.confirmation', compact('order'));
    }
    private function orderNumber(): string
    {
        do {
            $number = 'BAS-'.now()->format('ymd').'-'.strtoupper(Str::random(8));
        } while (Order::query()->where('order_number', $number)->exists());
        return $number;
    }
    private function money(int $minor): string { return number_format($minor / 100, 2, '.', ''); }
    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
