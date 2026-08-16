<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class S07OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_authorized_checkout_session_can_open_its_order_status_and_a_new_session_cannot(): void
    {
        $order = $this->placeOrder();

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSeeText('تم استلام طلبك');

        $this->flushSession();

        $this->get(route('orders.show', $order))->assertForbidden();
    }

    public function test_status_uses_durable_order_snapshots_for_items_quantities_totals_and_private_destination_summary(): void
    {
        $order = $this->placeOrder(2);

        $response = $this->get(route('orders.show', $order))->assertOk();

        $response->assertSeeText('كرسي استرخاء مخملي')
            ->assertSeeText('مخمل رملي · جوزي داكن')
            ->assertSeeText('الكمية: 2')
            ->assertSeeText('4,100')
            ->assertSeeText('4,135')
            ->assertSeeText('الرياض')
            ->assertSeeText('حي تجريبي')
            ->assertSeeText('المنتهي بـ 0001')
            ->assertDontSeeText('شارع تطويري 10')
            ->assertDontSeeText('+966500000001')
            ->assertDontSeeText('customer@example.test');
    }

    public function test_pending_payment_unreserved_inventory_and_not_started_fulfillment_render_truthfully(): void
    {
        $order = $this->placeOrder();

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSeeText('الدفع لم يكتمل بعد')
            ->assertSeeText('المخزون غير محجوز حتى الآن')
            ->assertSeeText('تجهيز الطلب لم يبدأ بعد')
            ->assertSeeText('لا توجد شحنة حتى الآن')
            ->assertSeeText('لا يوجد ناقل أو رقم تتبع أو موعد تسليم مؤكد لهذا الطلب حتى الآن.')
            ->assertDontSeeText('تم الدفع بنجاح')
            ->assertDontSeeText('تم حجز المخزون')
            ->assertDontSeeText('تم شحن الطلب')
            ->assertDontSee('data-testid="tracking-number"', false)
            ->assertDontSee('data-testid="carrier-name"', false)
            ->assertDontSee('data-testid="delivery-eta"', false);
    }

    public function test_event_chronology_is_ordered_and_only_events_for_the_order_are_loaded(): void
    {
        $order = $this->placeOrder();

        $firstTime = now()->addHour()->startOfMinute();
        $secondTime = now()->addHours(2)->startOfMinute();

        $order->events()->create([
            'event_type' => 'customer_safe_update',
            'actor_type' => 'system',
            'entity_type' => 'order',
            'order_reference' => $order->order_number,
            'resulting_order_state' => $order->order_state,
            'resulting_payment_state' => $order->payment_state,
            'resulting_reservation_state' => $order->reservation_state,
            'resulting_fulfillment_state' => $order->fulfillment_state,
            'reason_code' => 'status_refresh',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => $firstTime,
        ]);
        $order->events()->create([
            'event_type' => 'customer_safe_update',
            'actor_type' => 'system',
            'entity_type' => 'order',
            'order_reference' => $order->order_number,
            'resulting_order_state' => $order->order_state,
            'resulting_payment_state' => $order->payment_state,
            'resulting_reservation_state' => $order->reservation_state,
            'resulting_fulfillment_state' => $order->fulfillment_state,
            'reason_code' => 'status_refresh',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => $secondTime,
        ]);

        $other = $this->placeOrder();
        $this->assertNotSame($order->id, $other->id);

        $response = $this->withSession([
            'checkout.completed' => ['authorized' => $order->id],
        ])->get(route('orders.show', $order))->assertOk();

        $response->assertSeeInOrder([
            $firstTime->translatedFormat('j M Y، H:i'),
            $secondTime->translatedFormat('j M Y، H:i'),
        ]);
        $response->assertSee($order->order_number)
            ->assertDontSee($other->order_number);
    }

    public function test_customer_status_does_not_expose_internal_states_policy_codes_or_engineering_terms(): void
    {
        $order = $this->placeOrder();

        $response = $this->get(route('orders.show', $order))->assertOk();

        foreach ([
            'S06', 'S07', 'workstream', 'Checkout', 'SLA',
            'pending_payment', 'not_reserved', 'not_started',
            'policy_not_activated', 'demo_unconfigured_zero', 'manual_pending_demo',
            'rp01-s06-development-consent-v1', 'customer_safe_update', 'status_refresh',
        ] as $term) {
            $response->assertDontSeeText($term);
        }
    }

    public function test_confirmation_links_to_the_session_bound_order_status_without_weakening_access(): void
    {
        $order = $this->placeOrder();

        $this->get(route('checkout.confirmation', $order))
            ->assertOk()
            ->assertSee(route('orders.show', $order), false)
            ->assertSeeText('عرض حالة الطلب');

        $this->flushSession();
        $this->get(route('orders.show', $order))->assertForbidden();
    }

    private function placeOrder(int $quantity = 1): Order
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();

        $this->postJson('/cart/items', [
            'variant_id' => $variant->id,
            'quantity' => $quantity,
        ])->assertCreated();

        $this->get('/checkout')->assertOk();
        $token = (string) session('checkout_token');

        $this->post('/checkout', [
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
        ])->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }
}
