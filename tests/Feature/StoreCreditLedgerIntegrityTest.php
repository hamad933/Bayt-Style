<?php

namespace Tests\Feature;

use App\Commerce\Returns\StoreCreditLedgerService;
use App\Models\Order;
use App\Models\StoreCreditEntry;
use App\Models\Variant;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreCreditLedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_normal_credit_and_debit_projection_remains_correct_without_mutable_balance_authority(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);

        $ledger->record(
            order: $order,
            entryType: 'credit',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_credit_record',
            sourceReference: 'SC-NORMAL-CREDIT',
            occurredAt: now()->subMinutes(2),
        );
        $ledger->record(
            order: $order,
            entryType: 'debit',
            amount: '20.00',
            currency: 'SAR',
            sourceType: 'authoritative_debit_record',
            sourceReference: 'SC-NORMAL-DEBIT',
            occurredAt: now()->subMinute(),
        );

        $this->assertFalse(Schema::hasColumn('orders', 'store_credit_balance'));
        $this->assertFalse(Schema::hasColumn('store_credit_entries', 'balance'));

        $this->get(route('orders.returns.index', $order))
            ->assertOk()
            ->assertSeeText('100.00');
    }

    public function test_valid_reversal_produces_exact_balance_even_when_occurred_at_sorts_before_target(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);
        $credit = $ledger->record(
            order: $order,
            entryType: 'credit',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_credit_record',
            sourceReference: 'SC-REV-CREDIT',
            occurredAt: now(),
        );
        $reversal = $ledger->record(
            order: $order,
            entryType: 'reversal',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_reversal_record',
            sourceReference: 'SC-REV-REVERSAL',
            reversalOfEntryId: $credit->id,
            occurredAt: now()->subMinute(),
        );

        $ledger->assertProjectionIntegrity($order, collect([$reversal, $credit]));

        $this->get(route('orders.returns.index', $order))
            ->assertOk()
            ->assertSeeText('0.00')
            ->assertSeeText('عكس قيد سابق');
    }

    public function test_duplicate_reversal_of_same_entry_is_rejected_atomically(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);
        $credit = $this->credit($ledger, $order, 'SC-DUP-CREDIT', now()->subMinutes(3));

        $ledger->record(
            order: $order,
            entryType: 'reversal',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_reversal_record',
            sourceReference: 'SC-DUP-FIRST',
            reversalOfEntryId: $credit->id,
            occurredAt: now()->subMinutes(2),
        );

        $this->assertDomainRejected(fn () => $ledger->record(
            order: $order,
            entryType: 'reversal',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_reversal_record',
            sourceReference: 'SC-DUP-SECOND',
            reversalOfEntryId: $credit->id,
            occurredAt: now()->subMinute(),
        ));

        $this->assertDatabaseCount('store_credit_entries', 2);
        $this->assertSame(1, StoreCreditEntry::query()->where('reversal_of_entry_id', $credit->id)->count());
    }

    public function test_cross_currency_reversal_is_rejected(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);
        $credit = $this->credit($ledger, $order, 'SC-CURRENCY-CREDIT', now()->subMinutes(2));

        $this->assertDomainRejected(fn () => $ledger->record(
            order: $order,
            entryType: 'reversal',
            amount: '120.00',
            currency: 'USD',
            sourceType: 'authoritative_reversal_record',
            sourceReference: 'SC-CURRENCY-REVERSAL',
            reversalOfEntryId: $credit->id,
            occurredAt: now()->subMinute(),
        ));

        $this->assertDatabaseCount('store_credit_entries', 1);
    }

    public function test_cross_order_reversal_is_rejected(): void
    {
        $firstOrder = $this->placeOrder();
        $secondOrder = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);
        $credit = $this->credit($ledger, $firstOrder, 'SC-ORDER-CREDIT', now()->subMinutes(2));

        $this->assertDomainRejected(fn () => $ledger->record(
            order: $secondOrder,
            entryType: 'reversal',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_reversal_record',
            sourceReference: 'SC-ORDER-REVERSAL',
            reversalOfEntryId: $credit->id,
            occurredAt: now()->subMinute(),
        ));

        $this->assertDatabaseCount('store_credit_entries', 1);
    }

    public function test_nonexistent_reversal_target_is_rejected(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);

        $this->assertDomainRejected(fn () => $ledger->record(
            order: $order,
            entryType: 'reversal',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_reversal_record',
            sourceReference: 'SC-MISSING-REVERSAL',
            reversalOfEntryId: 999999,
            occurredAt: now(),
        ));

        $this->assertDatabaseCount('store_credit_entries', 0);
    }

    public function test_reversal_cannot_precede_its_target_in_ledger_identity(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);

        DB::table('store_credit_entries')->insert([
            'id' => 200,
            'order_id' => $order->id,
            'return_case_id' => null,
            'entry_type' => 'credit',
            'amount' => '120.00',
            'currency' => 'SAR',
            'source_type' => 'causal_probe',
            'source_reference' => 'SC-CAUSAL-TARGET',
            'reversal_of_entry_id' => null,
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);
        DB::table('store_credit_entries')->insert([
            'id' => 100,
            'order_id' => $order->id,
            'return_case_id' => null,
            'entry_type' => 'reversal',
            'amount' => '120.00',
            'currency' => 'SAR',
            'source_type' => 'causal_probe',
            'source_reference' => 'SC-CAUSAL-REVERSAL',
            'reversal_of_entry_id' => 200,
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);

        $entries = StoreCreditEntry::query()->where('order_id', $order->id)->get();

        $this->assertDomainRejected(fn () => $ledger->assertProjectionIntegrity($order, $entries));
    }

    public function test_failed_reversal_leaves_ledger_unchanged(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);
        $credit = $this->credit($ledger, $order, 'SC-ATOMIC-CREDIT', now()->subMinutes(2));
        $before = StoreCreditEntry::query()->orderBy('id')->get()->map->only([
            'id', 'order_id', 'entry_type', 'amount', 'currency', 'reversal_of_entry_id',
        ])->all();

        $this->assertDomainRejected(fn () => $ledger->record(
            order: $order,
            entryType: 'reversal',
            amount: '119.00',
            currency: 'SAR',
            sourceType: 'authoritative_reversal_record',
            sourceReference: 'SC-ATOMIC-REVERSAL',
            reversalOfEntryId: $credit->id,
            occurredAt: now()->subMinute(),
        ));

        $after = StoreCreditEntry::query()->orderBy('id')->get()->map->only([
            'id', 'order_id', 'entry_type', 'amount', 'currency', 'reversal_of_entry_id',
        ])->all();

        $this->assertSame($before, $after);
    }

    public function test_database_unique_constraint_blocks_second_reversal_even_if_service_is_bypassed(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);
        $credit = $this->credit($ledger, $order, 'SC-DB-CREDIT', now()->subMinutes(3));

        $ledger->record(
            order: $order,
            entryType: 'reversal',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_reversal_record',
            sourceReference: 'SC-DB-FIRST',
            reversalOfEntryId: $credit->id,
            occurredAt: now()->subMinutes(2),
        );

        try {
            DB::transaction(function () use ($order, $credit): void {
                StoreCreditEntry::query()->create([
                    'order_id' => $order->id,
                    'return_case_id' => null,
                    'entry_type' => 'reversal',
                    'amount' => '120.00',
                    'currency' => 'SAR',
                    'source_type' => 'constraint_bypass_probe',
                    'source_reference' => 'SC-DB-SECOND',
                    'reversal_of_entry_id' => $credit->id,
                    'correlation_id' => (string) Str::uuid(),
                    'occurred_at' => now()->subMinute(),
                ]);
            });
            $this->fail('Database accepted a second reversal for the same target.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, StoreCreditEntry::query()->where('reversal_of_entry_id', $credit->id)->count());
    }

    public function test_internally_inconsistent_persisted_ledger_fails_safe_instead_of_projecting_balance(): void
    {
        $order = $this->placeOrder();
        $target = StoreCreditEntry::query()->create([
            'order_id' => $order->id,
            'return_case_id' => null,
            'entry_type' => 'credit',
            'amount' => '120.00',
            'currency' => 'SAR',
            'source_type' => 'corruption_probe',
            'source_reference' => 'SC-CORRUPT-TARGET',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);
        $firstReversal = StoreCreditEntry::query()->create([
            'order_id' => $order->id,
            'return_case_id' => null,
            'entry_type' => 'reversal',
            'amount' => '120.00',
            'currency' => 'SAR',
            'source_type' => 'corruption_probe',
            'source_reference' => 'SC-CORRUPT-REVERSAL-ONE',
            'reversal_of_entry_id' => $target->id,
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);
        StoreCreditEntry::query()->create([
            'order_id' => $order->id,
            'return_case_id' => null,
            'entry_type' => 'reversal',
            'amount' => '120.00',
            'currency' => 'SAR',
            'source_type' => 'corruption_probe',
            'source_reference' => 'SC-CORRUPT-REVERSAL-TWO',
            'reversal_of_entry_id' => $firstReversal->id,
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);

        $this->get(route('orders.returns.index', $order))->assertServerError();
    }

    public function test_write_boundary_rejects_unsupported_non_positive_invalid_currency_and_direct_target_states(): void
    {
        $order = $this->placeOrder();
        $ledger = app(StoreCreditLedgerService::class);

        $this->assertDomainRejected(fn () => $ledger->record(
            order: $order,
            entryType: 'adjustment',
            amount: '1.00',
            currency: 'SAR',
            sourceType: 'validation_probe',
            sourceReference: 'SC-BAD-TYPE',
        ));
        $this->assertDomainRejected(fn () => $ledger->record(
            order: $order,
            entryType: 'credit',
            amount: '0.00',
            currency: 'SAR',
            sourceType: 'validation_probe',
            sourceReference: 'SC-BAD-AMOUNT',
        ));
        $this->assertDomainRejected(fn () => $ledger->record(
            order: $order,
            entryType: 'credit',
            amount: '1.00',
            currency: 'SA',
            sourceType: 'validation_probe',
            sourceReference: 'SC-BAD-CURRENCY',
        ));

        $credit = $this->credit($ledger, $order, 'SC-DIRECT-TARGET', now()->subMinute());

        $this->assertDomainRejected(fn () => $ledger->record(
            order: $order,
            entryType: 'debit',
            amount: '1.00',
            currency: 'SAR',
            sourceType: 'validation_probe',
            sourceReference: 'SC-DIRECT-BAD-TARGET',
            reversalOfEntryId: $credit->id,
        ));

        $this->assertDatabaseCount('store_credit_entries', 1);
    }

    private function credit(
        StoreCreditLedgerService $ledger,
        Order $order,
        string $reference,
        $occurredAt,
    ): StoreCreditEntry {
        return $ledger->record(
            order: $order,
            entryType: 'credit',
            amount: '120.00',
            currency: 'SAR',
            sourceType: 'authoritative_credit_record',
            sourceReference: $reference,
            occurredAt: $occurredAt,
        );
    }

    private function assertDomainRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected store-credit ledger operation to be rejected.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }
    }

    private function placeOrder(): Order
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();

        $this->postJson('/cart/items', [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->get('/checkout')->assertOk();
        $token = (string) session('checkout_token');

        $this->post('/checkout', [
            'checkout_token' => $token,
            'full_name' => 'عميل تجريبي',
            'email' => 'ledger@example.test',
            'phone' => '+966500000003',
            'country_code' => 'SA',
            'region' => 'الرياض',
            'city' => 'الرياض',
            'district' => 'حي تجريبي',
            'address_line' => 'شارع تطويري 30',
            'building_unit' => 'مبنى 3',
            'postal_code' => '00000',
            'delivery_notes' => 'بيانات اختبار فقط',
            'shipping_method' => 'demo_standard',
            'payment_method' => 'manual_pending_demo',
            'terms' => '1',
        ])->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }
}
