<?php

namespace Tests\Feature;

use App\Commerce\Returns\ReturnCaseService;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\RefundRecord;
use App\Models\ReturnCase;
use App\Models\ReturnEligibility;
use App\Models\StoreCreditEntry;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class S10AdminOrdersPaymentsReturnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_guest_customer_and_admin_access_boundaries_are_server_enforced(): void
    {
        $this->get(route('admin.orders.index'))->assertRedirect(route('admin.login'));

        $customer = $this->user(User::ROLE_CUSTOMER, 's10-customer@example.test');
        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();

        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 's10-admin@example.test');
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk()->assertSeeText('الطلبات والمدفوعات والمرتجعات');
    }

    public function test_customer_cannot_bypass_server_authorization_for_sensitive_mutations(): void
    {
        $order = $this->placeOrder();
        $customer = $this->user(User::ROLE_CUSTOMER, 's10-blocked@example.test');

        $this->actingAs($customer)->post(route('admin.orders.cancel', $order), ['reason' => 'محاولة غير مصرح بها لإلغاء الطلب'])->assertForbidden();
        $this->assertSame('pending_payment', $order->fresh()->order_state);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_admin_order_cancellation_changes_only_order_truth_and_appends_event_and_audit(): void
    {
        $order = $this->placeOrder();
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 's10-cancel@example.test');
        $correlationId = (string) Str::uuid();

        $beforePayment = $order->payment_state;
        $beforeReservation = $order->reservation_state;
        $beforeFulfillment = $order->fulfillment_state;

        $this->actingAs($admin)->post(route('admin.orders.cancel', $order), [
            'reason' => 'إلغاء طلب معلّق بناءً على قرار تشغيلي موثق',
            'correlation_id' => $correlationId,
            'payment_state' => 'paid',
            'browser_return' => 'success',
            'fulfillment_state' => 'shipped',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('cancelled', $order->order_state);
        $this->assertSame($beforePayment, $order->payment_state);
        $this->assertSame($beforeReservation, $order->reservation_state);
        $this->assertSame($beforeFulfillment, $order->fulfillment_state);

        $event = $order->events()->where('event_type', 'admin_order_cancelled')->firstOrFail();
        $this->assertSame($correlationId, $event->correlation_id);
        $this->assertSame($beforePayment, $event->resulting_payment_state);

        $audit = AdminAuditLog::query()->where('action', 'orders.pending.cancelled')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame($correlationId, $audit->correlation_id);
        $this->assertSame('pending_payment', $audit->before_values['order_state']);
        $this->assertSame('cancelled', $audit->after_values['order_state']);
        $this->assertSame($beforePayment, $audit->after_values['payment_state']);
    }

    public function test_duplicate_sensitive_order_mutation_is_idempotent_for_same_correlation_context(): void
    {
        $order = $this->placeOrder();
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 's10-replay@example.test');
        $correlationId = (string) Str::uuid();
        $payload = ['reason' => 'إلغاء موثق قابل لإعادة الطلب بأمان', 'correlation_id' => $correlationId];

        $this->actingAs($admin)->post(route('admin.orders.cancel', $order), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.orders.cancel', $order), $payload)->assertRedirect();

        $this->assertSame(1, $order->events()->where('event_type', 'admin_order_cancelled')->where('correlation_id', $correlationId)->count());
        $this->assertSame(1, AdminAuditLog::query()->where('action', 'orders.pending.cancelled')->where('correlation_id', $correlationId)->count());
    }

    public function test_invalid_order_transition_fails_atomically(): void
    {
        $order = $this->placeOrder();
        $order->forceFill(['order_state' => 'confirmed'])->save();
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 's10-atomic-order@example.test');
        $eventCount = $order->events()->count();
        $auditCount = AdminAuditLog::query()->count();

        $this->actingAs($admin)->post(route('admin.orders.cancel', $order), ['reason' => 'محاولة انتقال غير صالح يجب رفضها'])->assertSessionHasErrors('order_state');

        $this->assertSame('confirmed', $order->fresh()->order_state);
        $this->assertSame($eventCount, $order->events()->count());
        $this->assertSame($auditCount, AdminAuditLog::query()->count());
    }

    public function test_current_catalog_edits_do_not_rewrite_or_reconstruct_historical_order_snapshots(): void
    {
        $order = $this->placeOrder();
        $line = $order->lines()->firstOrFail();
        $snapshot = $line->only(['product_name', 'variant_name', 'variant_sku', 'unit_price', 'line_total', 'currency']);
        $product = Product::query()->findOrFail($line->product_id);
        $variant = Variant::query()->findOrFail($line->variant_id);

        $product->forceFill(['name_ar' => 'اسم كتالوج حديث لا يجب أن يغيّر التاريخ'])->save();
        $variant->forceFill(['name_ar' => 'خيار حديث', 'sku' => 'S10-NEW-SKU', 'price' => '9999.00'])->save();

        $this->assertSame($snapshot, $line->fresh()->only(array_keys($snapshot)));

        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 's10-snapshot@example.test');
        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSeeText($snapshot['product_name'])
            ->assertSeeText($snapshot['variant_sku']);
    }

    public function test_return_lifecycle_requires_inspection_and_receipt_does_not_refund_restock_or_credit(): void
    {
        [$order, $case, $variant] = $this->returnCase();
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 's10-return@example.test');
        $stock = $variant->inventory_quantity;

        $this->actingAs($admin)->post(route('admin.returns.authorize', [$order, $case]), ['reason' => 'اعتماد حالة الإرجاع للاختبار'])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.returns.receive', [$order, $case]), ['reason' => 'تسجيل الاستلام الفعلي للاختبار'])->assertRedirect();

        $case->refresh();
        $this->assertSame('received', $case->return_state);
        $this->assertSame($stock, $variant->fresh()->inventory_quantity);
        $this->assertSame(0, RefundRecord::query()->where('return_case_id', $case->id)->count());
        $this->assertSame(0, StoreCreditEntry::query()->where('return_case_id', $case->id)->count());

        $this->actingAs($admin)->post(route('admin.returns.disposition', [$order, $case]), [
            'disposition' => 'sellable',
            'reason' => 'يجب رفض القرار قبل وجود فحص',
        ])->assertSessionHasErrors('return_state');
        $this->assertDatabaseMissing('inventory_dispositions', ['return_case_id' => $case->id]);

        $this->actingAs($admin)->post(route('admin.returns.inspect', [$order, $case]), [
            'inspection_outcome' => 'sealed_and_complete',
            'reason' => 'تسجيل نتيجة الفحص قبل قرار المخزون',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.returns.disposition', [$order, $case]), [
            'disposition' => 'sellable',
            'reason' => 'قرار التصرف بعد وجود فحص موثق',
        ])->assertRedirect();

        $case->refresh();
        $this->assertSame('disposition_decided', $case->return_state);
        $this->assertSame($stock, $variant->fresh()->inventory_quantity, 'Disposition truth must not silently restock inventory.');
        $this->assertSame(0, RefundRecord::query()->where('return_case_id', $case->id)->count());
        $this->assertSame(0, StoreCreditEntry::query()->where('return_case_id', $case->id)->count());
        $this->assertSame(4, AdminAuditLog::query()->where('entity_type', 'return_case')->where('entity_id', $case->id)->count());
    }

    public function test_refund_request_is_visible_but_never_promoted_to_success_by_admin_surface(): void
    {
        [$order, $case] = $this->returnCase();
        RefundRecord::query()->create([
            'order_id' => $order->id,
            'return_case_id' => $case->id,
            'refund_reference' => 'RF-S10-REQUEST',
            'refund_state' => 'requested',
            'amount' => '100.00',
            'currency' => $order->currency,
            'actor_type' => 'test_fixture',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);

        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 's10-refund@example.test');
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSeeText('requested')->assertSeeText('RF-S10-REQUEST');
        $this->assertSame('requested', RefundRecord::query()->where('refund_reference', 'RF-S10-REQUEST')->value('refund_state'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.refunds.complete'));
    }

    public function test_store_credit_remains_ledger_only_and_s10_introduces_no_issue_or_balance_authority(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.store-credit.issue'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.store-credit.balance.update'));
        $this->assertTrue(class_exists(\App\Commerce\Returns\StoreCreditLedgerService::class));
    }

    public function test_s01_to_s09_critical_surfaces_remain_available(): void
    {
        $product = Product::query()->published()->whereHas('defaultVariant')->firstOrFail();
        $this->get(route('home'))->assertOk();
        $this->get(route('catalog'))->assertOk();
        $this->get(route('products.show', $product))->assertOk();
        $this->get(route('cart.index'))->assertOk();

        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 's10-regression@example.test');
        $this->actingAs($admin)->get(route('admin.catalog.index'))->assertOk();
    }

    private function user(string $role, string $email): User
    {
        return User::query()->create(['name' => 'هوية اختبار S10', 'email' => $email, 'password' => bin2hex(random_bytes(24)), 'role' => $role]);
    }

    private function placeOrder(): Order
    {
        $variant = Variant::query()->where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $this->get('/checkout')->assertOk();
        $token = (string) session('checkout_token');
        $this->post('/checkout', [
            'checkout_token' => $token,
            'full_name' => 'عميل S10',
            'email' => 's10-order@example.test',
            'phone' => '+966500000010',
            'country_code' => 'SA',
            'region' => 'الرياض',
            'city' => 'الرياض',
            'district' => 'حي تجريبي',
            'address_line' => 'شارع S10 التجريبي',
            'building_unit' => '10',
            'postal_code' => '00000',
            'delivery_notes' => 'اختبار فقط',
            'shipping_method' => 'demo_standard',
            'payment_method' => 'manual_pending_demo',
            'terms' => '1',
        ])->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function returnCase(): array
    {
        $order = $this->placeOrder();
        $line = $order->lines()->firstOrFail();
        ReturnEligibility::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'eligible_quantity' => 1,
            'state' => 'active',
            'source_type' => 'test_fixture',
            'source_reference' => 'S10-ELIGIBLE',
            'correlation_id' => (string) Str::uuid(),
            'recorded_at' => now(),
        ]);

        $case = app(ReturnCaseService::class)->request($order, $line, 1, 'test_reason', hash('sha256', 's10-authority'), (string) Str::uuid());
        $variant = Variant::query()->findOrFail($line->variant_id);

        return [$order, $case, $variant];
    }
}
