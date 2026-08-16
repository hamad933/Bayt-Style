<?php
namespace App\Commerce;
use App\Models\Order;
use App\Models\Variant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class CheckoutSubmissionService
{
    public function submit(array $validated, array $cart, array $totals): Order
    {
        $token = (string) $validated['checkout_token'];
        try {
            return DB::transaction(function () use ($validated, $cart, $totals, $token): Order {
                DB::selectOne('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$token]);
                if ($existing = Order::query()->where('idempotency_key', $token)->first()) {
                    return $existing;
                }
                $order = Order::query()->create([
                    'order_number' => $this->orderNumber(),
                    'idempotency_key' => $token,
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
                ]);
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
                    $variant = Variant::query()->with('attributeOptions.attribute')->findOrFail($item['variant_id']);
                    foreach ($variant->attributeOptions as $option) {
                        if (! $option->attribute) continue;
                        $line->options()->create([
                            'attribute_code' => $option->attribute->code,
                            'attribute_name' => $option->attribute->name_ar,
                            'option_value' => $option->value_ar,
                        ]);
                    }
                }
                $order->events()->create([
                    'event_type' => 'order_created',
                    'actor_type' => 'guest_customer',
                    'entity_type' => 'order',
                    'order_reference' => $order->order_number,
                    'resulting_order_state' => $order->order_state,
                    'resulting_payment_state' => $order->payment_state,
                    'resulting_reservation_state' => $order->reservation_state,
                    'resulting_fulfillment_state' => $order->fulfillment_state,
                    'reason_code' => 'checkout_submitted',
                    'correlation_id' => $token,
                    'occurred_at' => now(),
                ]);
                return $order;
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                $existing = Order::query()->where('idempotency_key', $token)->first();
                if ($existing) return $existing;
            }
            throw $exception;
        }
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
