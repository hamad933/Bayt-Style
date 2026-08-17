<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class S10AdminOrdersSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::query()->where('order_number', 'BAS-S10-EVIDENCE')->exists()) {
            return;
        }

        $variant = Variant::query()->with(['product', 'attributeOptions.attribute'])->where('is_active', true)->orderBy('id')->first();
        if (! $variant || ! $variant->product) {
            return;
        }

        $now = now();
        $correlationId = (string) Str::uuid();
        $order = Order::query()->create([
            'order_number' => 'BAS-S10-EVIDENCE',
            'idempotency_key' => (string) Str::uuid(),
            'currency' => $variant->currency,
            'customer_full_name' => 'عميل تحقق S10',
            'customer_email' => 's10-browser@example.test',
            'customer_phone' => '+966500000010',
            'delivery_country_code' => 'SA',
            'delivery_region' => 'الرياض',
            'delivery_city' => 'الرياض',
            'delivery_district' => 'حي تجريبي',
            'delivery_address_line' => 'عنوان تحقق غير حقيقي',
            'delivery_building_unit' => null,
            'delivery_postal_code' => null,
            'delivery_notes' => 'Fixture متعمد للمتصفح فقط',
            'shipping_method_code' => 'demo_standard',
            'shipping_method_name' => 'توصيل تجريبي قياسي',
            'shipping_amount' => '35.00',
            'tax_policy_code' => 'demo_unconfigured_zero',
            'tax_amount' => '0.00',
            'subtotal' => (string) $variant->price,
            'total' => number_format((float) $variant->price + 35, 2, '.', ''),
            'payment_method_code' => 'manual_pending_demo',
            'payment_state' => 'pending',
            'order_state' => 'pending_payment',
            'reservation_state' => 'not_reserved',
            'reservation_policy_code' => 'policy_not_activated',
            'fulfillment_state' => 'not_started',
            'consent_version' => 'rp01-s10-browser-fixture',
            'consented_at' => $now,
        ]);

        $line = $order->lines()->create([
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'product_name' => $variant->product->name_ar,
            'variant_name' => $variant->name_ar,
            'variant_sku' => $variant->sku,
            'unit_price' => $variant->price,
            'quantity' => 1,
            'line_total' => $variant->price,
            'currency' => $variant->currency,
        ]);

        foreach ($variant->attributeOptions as $option) {
            if (! $option->attribute) {
                continue;
            }
            $line->options()->create([
                'attribute_code' => $option->attribute->code,
                'attribute_name' => $option->attribute->name_ar,
                'option_value' => $option->value_ar,
            ]);
        }

        $order->events()->create([
            'event_type' => 'order_created',
            'actor_type' => 'system_fixture',
            'entity_type' => 'order',
            'order_reference' => $order->order_number,
            'resulting_order_state' => $order->order_state,
            'resulting_payment_state' => $order->payment_state,
            'resulting_reservation_state' => $order->reservation_state,
            'resulting_fulfillment_state' => $order->fulfillment_state,
            'reason_code' => 's10_browser_fixture',
            'correlation_id' => $correlationId,
            'occurred_at' => $now,
        ]);
    }
}
