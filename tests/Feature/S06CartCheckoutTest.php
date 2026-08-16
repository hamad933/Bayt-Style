<?php

namespace Tests\Feature;

use App\Commerce\CheckoutTotals;
use App\Commerce\Shipping\ShippingMethodProvider;
use App\Commerce\Tax\TaxCalculator;
use App\Models\Order;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class S06CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_cart_page_has_empty_and_populated_exact_variant_states(): void
    {
        $this->get('/cart')->assertOk()->assertSee('السلة فارغة');
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])->assertCreated();
        $this->get('/cart')->assertOk()->assertSee('كرسي استرخاء مخملي')->assertSee('BAS-CHAIR-SAND-01')
            ->assertSee('مخمل رملي')->assertSee('4,100')->assertSessionHas('cart.'.$variant->id, 2);
    }

    public function test_quantity_update_and_remove_preserve_session_semantics_without_inventory_mutation(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $inventory = $variant->inventory_quantity;
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $this->patch('/cart/items/'.$variant->id, ['quantity' => 3])->assertRedirect('/cart');
        $this->assertSame(3, session('cart')[$variant->id]);
        $this->assertSame($inventory, $variant->fresh()->inventory_quantity);
        $this->assertFalse(Schema::hasTable('inventory_reservations'));
        $this->delete('/cart/items/'.$variant->id)->assertRedirect('/cart');
        $this->assertArrayNotHasKey($variant->id, session('cart', []));
        $this->assertSame($inventory, $variant->fresh()->inventory_quantity);
    }

    public function test_checkout_is_blocked_for_unavailable_variant_and_cart_never_reserves_inventory(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $inventory = $variant->inventory_quantity;
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $variant->update(['is_active' => false]);
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHas('warning');
        $this->get('/cart')->assertOk()->assertSee('غير متاحة')->assertSee('المتابعة غير متاحة قبل المراجعة');
        $this->assertSame($inventory, $variant->fresh()->inventory_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_meaningful_price_change_is_refreshed_and_requires_customer_review(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $variant->update(['price' => 2150]);
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHas('warning');
        $this->get('/cart')->assertOk()->assertSee('2,150')->assertDontSee('4,100');
        $this->assertSame('2150.00', session('cart_prices')[$variant->id]);
    }

    public function test_guest_checkout_validates_contact_address_shipping_and_terms(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $this->get('/checkout')->assertOk()->assertSee('إتمام الطلب كزائر');
        $token = session('checkout_token');
        $this->post('/checkout', [
            'checkout_token' => $token, 'full_name' => '', 'email' => 'not-an-email', 'phone' => 'x',
            'country_code' => 'AE', 'region' => '', 'city' => '', 'address_line' => '',
            'shipping_method' => 'fake', 'payment_method' => 'manual_pending_demo',
        ])->assertSessionHasErrors(['full_name','email','phone','country_code','region','city','address_line','shipping_method','terms']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_development_shipping_and_typed_zero_tax_boundary_are_deterministic(): void
    {
        $shipping = app(ShippingMethodProvider::class);
        $tax = app(TaxCalculator::class);
        $totals = app(CheckoutTotals::class);
        $method = $shipping->find('demo_standard', 'SA');
        $this->assertSame(3500, $method['amount_minor']);
        $this->assertTrue($method['fixture']);
        $taxResult = $tax->calculate(208500, 'SA', 'SAR');
        $this->assertSame('demo_unconfigured_zero', $taxResult['policy_code']);
        $this->assertSame(0, $taxResult['amount_minor']);
        $this->assertFalse($taxResult['configured_for_production']);
        $calculated = $totals->calculate(205000, 'demo_standard', 'SA', 'SAR');
        $this->assertSame(208500, $calculated['total_minor']);
        $this->assertSame('SAR', $calculated['currency']);
    }

    public function test_confirmed_checkout_creates_one_durable_snapshot_and_duplicate_submit_is_idempotent(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $inventory = $variant->inventory_quantity;
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])->assertCreated();
        $this->get('/checkout')->assertOk();
        $token = session('checkout_token');
        $payload = $this->validCheckoutPayload($token);
        $first = $this->post('/checkout', $payload);
        $order = Order::query()->with('lines.options')->sole();
        $first->assertRedirect(route('checkout.confirmation', $order));
        $this->assertSame('pending_payment', $order->order_state);
        $this->assertSame('pending', $order->payment_state);
        $this->assertSame('not_reserved', $order->reservation_state);
        $this->assertSame('policy_not_activated', $order->reservation_policy_code);
        $this->assertSame('not_started', $order->fulfillment_state);
        $this->assertSame('SAR', $order->currency);
        $this->assertSame('4100.00', $order->subtotal);
        $this->assertSame('35.00', $order->shipping_amount);
        $this->assertSame('0.00', $order->tax_amount);
        $this->assertSame('4135.00', $order->total);
        $this->assertSame('demo_unconfigured_zero', $order->tax_policy_code);
        $this->assertSame('manual_pending_demo', $order->payment_method_code);
        $this->assertSame('عميل تجريبي', $order->customer_full_name);
        $this->assertSame('customer@example.test', $order->customer_email);
        $this->assertSame('+966500000001', $order->customer_phone);
        $this->assertSame('SA', $order->delivery_country_code);
        $this->assertSame('الرياض', $order->delivery_region);
        $this->assertSame('الرياض', $order->delivery_city);
        $this->assertSame('شارع تطويري 10', $order->delivery_address_line);
        $this->assertNotNull($order->consented_at);
        $this->assertSame($inventory, $variant->fresh()->inventory_quantity);
        $line = $order->lines->sole();
        $this->assertSame($variant->id, $line->variant_id);
        $this->assertSame('BAS-CHAIR-SAND-01', $line->variant_sku);
        $this->assertSame('2050.00', $line->unit_price);
        $this->assertSame(2, $line->quantity);
        $this->assertSame('4100.00', $line->line_total);
        $this->assertCount(2, $line->options);
        $this->post('/checkout', $payload)->assertRedirect(route('checkout.confirmation', $order));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_lines', 1);
    }

    public function test_server_revalidates_cart_again_at_final_submission(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $this->get('/checkout')->assertOk();
        $token = session('checkout_token');
        $variant->update(['price' => 2225]);
        $this->post('/checkout', $this->validCheckoutPayload($token))->assertRedirect('/cart')->assertSessionHas('warning');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('2225.00', session('cart_prices')[$variant->id]);
    }

    public function test_historical_order_snapshots_do_not_change_when_product_or_variant_changes(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $product = $variant->product;
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $this->get('/checkout')->assertOk();
        $token = session('checkout_token');
        $this->post('/checkout', $this->validCheckoutPayload($token));
        $order = Order::with('lines')->sole();
        $originalLine = $order->lines->sole();
        $product->update(['name_ar' => 'اسم حالي جديد']);
        $variant->update(['name_ar' => 'Variant حالي جديد', 'price' => 9999, 'sku' => 'CHANGED-SKU']);
        $snapshot = $originalLine->fresh();
        $this->assertSame('كرسي استرخاء مخملي', $snapshot->product_name);
        $this->assertSame('مخمل رملي · جوزي داكن', $snapshot->variant_name);
        $this->assertSame('BAS-CHAIR-SAND-01', $snapshot->variant_sku);
        $this->assertSame('2050.00', $snapshot->unit_price);
        $this->assertSame('2085.00', $order->fresh()->total);
    }

    public function test_customer_and_address_snapshots_are_typed_relational_columns_not_generic_json(): void
    {
        foreach (['customer_full_name','customer_email','customer_phone','delivery_country_code','delivery_region','delivery_city','delivery_district','delivery_address_line','shipping_method_code','tax_policy_code','payment_state','order_state','reservation_state','fulfillment_state','consent_version'] as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column), $column);
        }
        $this->assertFalse(Schema::hasColumn('orders', 'address'));
        $this->assertFalse(Schema::hasColumn('orders', 'payload'));
        $this->assertTrue(Schema::hasTable('order_lines'));
        $this->assertTrue(Schema::hasTable('order_line_options'));
    }

    public function test_s06_contains_no_raw_card_storage_and_no_reservation_duration_policy(): void
    {
        foreach (['card_number','pan','cvv','cvc','card_data','reservation_expires_at','reservation_duration_minutes'] as $column) {
            $this->assertFalse(Schema::hasColumn('orders', $column), $column);
        }
        $this->assertFalse(Schema::hasTable('inventory_reservations'));
        $this->assertSame('rp01-s06-development-consent-v1', config('commerce.checkout.consent_version'));
        $this->assertSame('manual_pending_demo', config('commerce.checkout.payment_method_code'));
    }

    public function test_confirmation_is_session_bound_and_truthfully_reports_pending_unreserved_states(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $this->get('/checkout')->assertOk();
        $token = session('checkout_token');
        $this->post('/checkout', $this->validCheckoutPayload($token));
        $order = Order::sole();
        $this->get(route('checkout.confirmation', $order))->assertOk()->assertSee($order->order_number)
            ->assertSee('معلّق / غير مسدد')->assertSee('غير محجوز — السياسة غير مفعّلة')
            ->assertDontSee('تم الدفع بنجاح')->assertDontSee('تم حجز المخزون');
        $this->flushSession();
        $this->get(route('checkout.confirmation', $order))->assertForbidden();
    }

    private function validCheckoutPayload(string $token): array
    {
        return [
            'checkout_token' => $token,
            'full_name' => 'عميل تجريبي',
            'email' => 'customer@example.test',
            'phone' => '+966500000001',
            'country_code' => 'SA',
            'region' => 'الرياض',
            'city' => 'الرياض',
            'district' => 'حي تجريبي',
            'address_line' => 'شارع تطويري 10',
            'building_unit' => 'مبنى 2',
            'postal_code' => '00000',
            'delivery_notes' => 'بيانات اختبار فقط',
            'shipping_method' => 'demo_standard',
            'payment_method' => 'manual_pending_demo',
            'terms' => '1',
        ];
    }
}
