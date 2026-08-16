<?php

namespace Tests\Feature;

use App\Commerce\Returns\ReturnCaseService;
use App\Models\Order;
use App\Models\RefundRecord;
use App\Models\ReturnCase;
use App\Models\ReturnCaseEvent;
use App\Models\ReturnEligibility;
use App\Models\StoreCreditEntry;
use App\Models\Variant;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class S08ReturnsRefundsCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_current_pending_order_is_truthfully_not_return_eligible_and_has_no_automatic_financial_truth(): void
    {
        $order = $this->placeOrder();

        $this->assertDatabaseCount('return_eligibilities', 0);
        $this->assertDatabaseCount('return_cases', 0);
        $this->assertDatabaseCount('refund_records', 0);
        $this->assertDatabaseCount('store_credit_entries', 0);

        $this->get(route('orders.returns.index', $order))
            ->assertOk()
            ->assertSeeText('طلب المرتجع غير متاح حاليًا')
            ->assertSeeText('هذا الطلب لم يصل إلى حالة موثقة تسمح ببدء مرتجع')
            ->assertSeeText('لا توجد عملية استرداد مسجّلة لهذا الطلب حتى الآن.')
            ->assertSeeText('لم يصدر رصيد متجر لهذا الطلب حتى الآن.')
            ->assertSeeText('0.00')
            ->assertDontSee('data-testid="start-return"', false)
            ->assertDontSeeText('اكتمل الاسترداد')
            ->assertDontSeeText('رسوم إعادة تخزين')
            ->assertDontSeeText('أيام عمل')
            ->assertDontSeeText('انتهاء الرصيد');
    }

    public function test_returns_surface_preserves_the_existing_session_bound_order_access(): void
    {
        $order = $this->placeOrder();

        $this->get(route('orders.returns.index', $order))->assertOk();

        $this->flushSession();

        $this->get(route('orders.returns.index', $order))->assertForbidden();
        $this->post(route('orders.returns.store', $order), [
            'line_ref' => $order->lines()->firstOrFail()->variant_sku,
            'quantity' => 1,
            'reason' => 'other',
        ])->assertForbidden();
    }

    public function test_explicit_eligibility_can_create_a_return_without_refund_credit_restock_or_order_snapshot_mutation(): void
    {
        $order = $this->placeOrder();
        $line = $order->lines()->firstOrFail();
        $variant = Variant::query()->findOrFail($line->variant_id);
        $orderSnapshot = $order->only([
            'order_number', 'currency', 'subtotal', 'shipping_amount', 'tax_amount', 'total',
            'payment_state', 'order_state', 'reservation_state', 'fulfillment_state',
        ]);
        $lineSnapshot = $line->only([
            'product_name', 'variant_name', 'variant_sku', 'unit_price', 'quantity', 'line_total', 'currency',
        ]);
        $inventoryBefore = $variant->inventory_quantity;

        $this->grantEligibility($order);

        $this->post(route('orders.returns.store', $order), [
            'line_ref' => $line->variant_sku,
            'quantity' => 1,
            'reason' => 'changed_mind',
        ])->assertRedirect(route('orders.returns.index', $order));

        $case = ReturnCase::query()->firstOrFail();

        $this->assertSame('requested', $case->return_state);
        $this->assertSame($order->id, $case->order_id);
        $this->assertSame($line->id, $case->order_line_id);
        $this->assertSame(1, $case->requested_quantity);
        $this->assertSame(1, $case->events()->count());

        $this->assertDatabaseCount('refund_records', 0);
        $this->assertDatabaseCount('store_credit_entries', 0);
        $this->assertDatabaseCount('inventory_dispositions', 0);
        $this->assertSame($inventoryBefore, Variant::query()->findOrFail($variant->id)->inventory_quantity);
        $this->assertSame($orderSnapshot, $order->fresh()->only(array_keys($orderSnapshot)));
        $this->assertSame($lineSnapshot, $line->fresh()->only(array_keys($lineSnapshot)));
    }

    public function test_receipt_and_inspection_keep_refund_and_inventory_truth_separate_and_sellable_requires_inspection(): void
    {
        $order = $this->placeOrder();
        $line = $order->lines()->firstOrFail();
        $variant = Variant::query()->findOrFail($line->variant_id);
        $inventoryBefore = $variant->inventory_quantity;
        $this->grantEligibility($order);

        $service = app(ReturnCaseService::class);
        $case = $service->request(
            $order,
            $line,
            1,
            'damaged_or_defective',
            hash('sha256', 'test-authority'),
        );

        try {
            $service->decideDisposition($case, 'sellable');
            $this->fail('Sellable disposition was accepted before inspection.');
        } catch (DomainException) {
            $this->assertDatabaseCount('inventory_dispositions', 0);
        }

        $case = $service->authorize($case);
        $case = $service->recordReceipt($case);

        $this->assertSame('received', $case->return_state);
        $this->assertDatabaseCount('refund_records', 0);
        $this->assertDatabaseCount('store_credit_entries', 0);
        $this->assertDatabaseCount('inventory_dispositions', 0);
        $this->assertSame($inventoryBefore, Variant::query()->findOrFail($variant->id)->inventory_quantity);

        $inspection = $service->recordInspection($case, 'accepted_for_disposition');
        $disposition = $service->decideDisposition($case->fresh(), 'sellable');

        $this->assertSame($inspection->id, $disposition->return_inspection_id);
        $this->assertSame('sellable', $disposition->disposition);
        $this->assertSame('disposition_decided', $case->fresh()->return_state);
        $this->assertSame($inventoryBefore, Variant::query()->findOrFail($variant->id)->inventory_quantity);
        $this->assertDatabaseCount('refund_records', 0);
        $this->assertDatabaseCount('store_credit_entries', 0);
    }

    public function test_refund_records_are_append_only_and_do_not_change_physical_return_state(): void
    {
        $order = $this->placeOrder();
        $line = $order->lines()->firstOrFail();
        $this->grantEligibility($order);
        $case = app(ReturnCaseService::class)->request(
            $order,
            $line,
            1,
            'other',
            hash('sha256', 'refund-test-authority'),
        );

        $reference = 'RF-TEST-001';

        RefundRecord::query()->create([
            'order_id' => $order->id,
            'return_case_id' => $case->id,
            'refund_reference' => $reference,
            'refund_state' => 'requested',
            'amount' => '250.00',
            'currency' => 'SAR',
            'actor_type' => 'financial_record',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now()->subMinute(),
        ]);

        $latest = RefundRecord::query()->create([
            'order_id' => $order->id,
            'return_case_id' => $case->id,
            'refund_reference' => $reference,
            'refund_state' => 'approved',
            'amount' => '250.00',
            'currency' => 'SAR',
            'actor_type' => 'financial_record',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);

        $this->assertSame('requested', $case->fresh()->return_state);
        $this->assertDatabaseCount('store_credit_entries', 0);
        $this->assertDatabaseCount('inventory_dispositions', 0);

        $this->get(route('orders.returns.index', $order))
            ->assertOk()
            ->assertSeeText('تمت الموافقة على الاسترداد')
            ->assertSeeText('لا نعرض الاسترداد كمكتمل')
            ->assertDontSeeText('اكتمل الاسترداد');

        try {
            $latest->refund_state = 'completed';
            $latest->save();
            $this->fail('Refund record mutation was allowed.');
        } catch (LogicException) {
            $this->assertSame('approved', $latest->fresh()->refund_state);
        }
    }

    public function test_store_credit_balance_is_projected_from_append_only_ledger_entries_only(): void
    {
        $order = $this->placeOrder();

        $credit = StoreCreditEntry::query()->create([
            'order_id' => $order->id,
            'return_case_id' => null,
            'entry_type' => 'credit',
            'amount' => '120.00',
            'currency' => 'SAR',
            'source_type' => 'authoritative_credit_record',
            'source_reference' => 'SC-TEST-CREDIT',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now()->subMinute(),
        ]);

        StoreCreditEntry::query()->create([
            'order_id' => $order->id,
            'return_case_id' => null,
            'entry_type' => 'debit',
            'amount' => '20.00',
            'currency' => 'SAR',
            'source_type' => 'authoritative_debit_record',
            'source_reference' => 'SC-TEST-DEBIT',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);

        $this->assertFalse(Schema::hasColumn('orders', 'store_credit_balance'));
        $this->assertFalse(Schema::hasColumn('store_credit_entries', 'balance'));

        $this->get(route('orders.returns.index', $order))
            ->assertOk()
            ->assertSeeText('100.00')
            ->assertSeeText('إضافة إلى رصيد المتجر')
            ->assertSeeText('استخدام من رصيد المتجر');

        try {
            $credit->amount = '999.00';
            $credit->save();
            $this->fail('Store-credit ledger mutation was allowed.');
        } catch (LogicException) {
            $this->assertSame('120.00', $credit->fresh()->amount);
        }
    }

    public function test_return_audit_is_append_only_and_customer_copy_hides_internal_states_and_policy_identifiers(): void
    {
        $order = $this->placeOrder();
        $line = $order->lines()->firstOrFail();
        $this->grantEligibility($order);

        $this->post(route('orders.returns.store', $order), [
            'line_ref' => $line->variant_sku,
            'quantity' => 1,
            'reason' => 'other',
        ])->assertRedirect();

        $event = ReturnCaseEvent::query()->firstOrFail();

        try {
            $event->to_state = 'received';
            $event->save();
            $this->fail('Return audit mutation was allowed.');
        } catch (LogicException) {
            $this->assertSame('requested', $event->fresh()->to_state);
        }

        $response = $this->get(route('orders.returns.index', $order))->assertOk();

        foreach ([
            'S08', 'workstream', 'pending_payment', 'not_reserved', 'not_started',
            'policy_not_activated', 'requested', 'authorized', 'received', 'inspected',
            'disposition_decided', 'sellable', 'checkout_session', 'changed_mind',
            'authoritative_return_state',
        ] as $term) {
            $response->assertDontSeeText($term);
        }

        $response
            ->assertDontSeeText('خلال 14 يومًا')
            ->assertDontSeeText('خلال 30 يومًا')
            ->assertDontSeeText('رسوم إعادة تخزين')
            ->assertDontSeeText('يستغرق الاسترداد')
            ->assertDontSeeText('تنتهي صلاحية رصيد المتجر');
    }

    private function grantEligibility(Order $order): ReturnEligibility
    {
        $line = $order->lines()->firstOrFail();

        return ReturnEligibility::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'eligible_quantity' => $line->quantity,
            'state' => 'active',
            'source_type' => 'authoritative_return_state',
            'source_reference' => 'TEST-ELIGIBILITY-'.$order->order_number,
            'correlation_id' => (string) Str::uuid(),
            'recorded_at' => now(),
        ]);
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
