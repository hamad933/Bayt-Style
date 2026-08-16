<?php

namespace App\Commerce\Returns;

use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\StoreCreditEntry;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreCreditLedgerService
{
    private const ENTRY_TYPES = ['credit', 'debit', 'reversal'];
    private const DIRECT_ENTRY_TYPES = ['credit', 'debit'];

    public function record(
        Order $order,
        string $entryType,
        string|int|float $amount,
        string $currency,
        string $sourceType,
        string $sourceReference,
        ?ReturnCase $returnCase = null,
        ?int $reversalOfEntryId = null,
        ?CarbonInterface $occurredAt = null,
        ?string $correlationId = null,
    ): StoreCreditEntry {
        $entryType = trim(strtolower($entryType));
        $currency = strtoupper(trim($currency));
        $amountMinor = $this->moneyMinor($amount);
        $occurredAt ??= now();
        $sourceType = trim($sourceType);
        $sourceReference = trim($sourceReference);

        if (! in_array($entryType, self::ENTRY_TYPES, true)) {
            throw new DomainException('Unsupported store-credit entry type.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Store-credit currency must be an explicit three-letter currency code.');
        }

        if ($sourceType === '' || $sourceReference === '') {
            throw new DomainException('Store-credit source context is required.');
        }

        if ($returnCase !== null && (int) $returnCase->order_id !== (int) $order->id) {
            throw new DomainException('Store-credit return context must belong to the same order.');
        }

        if (in_array($entryType, self::DIRECT_ENTRY_TYPES, true) && $reversalOfEntryId !== null) {
            throw new DomainException('Credit and debit entries cannot reference a reversal target.');
        }

        if ($entryType === 'reversal' && $reversalOfEntryId === null) {
            throw new DomainException('A reversal must reference an existing store-credit entry.');
        }

        return DB::transaction(function () use (
            $order,
            $entryType,
            $amountMinor,
            $currency,
            $sourceType,
            $sourceReference,
            $returnCase,
            $reversalOfEntryId,
            $occurredAt,
            $correlationId,
        ): StoreCreditEntry {
            $target = null;

            if ($entryType === 'reversal') {
                $target = StoreCreditEntry::query()
                    ->whereKey($reversalOfEntryId)
                    ->lockForUpdate()
                    ->first();

                if (! $target) {
                    throw new DomainException('The reversal target does not exist.');
                }

                $this->assertReversalTarget($order, $target, $amountMinor, $currency);

                if (StoreCreditEntry::query()
                    ->where('reversal_of_entry_id', $target->id)
                    ->exists()) {
                    throw new DomainException('This store-credit entry has already been reversed.');
                }
            }

            $entry = StoreCreditEntry::query()->create([
                'order_id' => $order->id,
                'return_case_id' => $returnCase?->id,
                'entry_type' => $entryType,
                'amount' => $this->moneyString($amountMinor),
                'currency' => $currency,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'reversal_of_entry_id' => $target?->id,
                'correlation_id' => $correlationId ?? (string) Str::uuid(),
                'occurred_at' => $occurredAt,
            ]);

            if ($target !== null && (int) $target->id >= (int) $entry->id) {
                throw new DomainException('The reversal target must be causally prior in ledger identity.');
            }

            return $entry;
        }, 3);
    }

    public function assertProjectionIntegrity(Order $order, Collection $entries): void
    {
        $entriesById = $entries->keyBy(fn (StoreCreditEntry $entry): int => (int) $entry->id);
        $reversedTargets = [];

        foreach ($entries as $entry) {
            if ((int) $entry->order_id !== (int) $order->id) {
                throw new DomainException('Store-credit ledger contains an entry from another order.');
            }

            if (! in_array($entry->entry_type, self::ENTRY_TYPES, true)) {
                throw new DomainException('Store-credit ledger contains an unsupported entry type.');
            }

            $this->moneyMinor((string) $entry->amount);

            if (! preg_match('/^[A-Z]{3}$/', (string) $entry->currency)) {
                throw new DomainException('Store-credit ledger contains an invalid currency.');
            }

            if (in_array($entry->entry_type, self::DIRECT_ENTRY_TYPES, true)) {
                if ($entry->reversal_of_entry_id !== null) {
                    throw new DomainException('Direct store-credit entry has an invalid reversal target.');
                }

                continue;
            }

            if ($entry->reversal_of_entry_id === null) {
                throw new DomainException('Store-credit reversal is missing its target.');
            }

            $targetId = (int) $entry->reversal_of_entry_id;
            $target = $entriesById->get($targetId);

            if (! $target) {
                throw new DomainException('Store-credit reversal target cannot be resolved in this order ledger.');
            }

            if (isset($reversedTargets[$targetId])) {
                throw new DomainException('Store-credit ledger contains more than one reversal for a target.');
            }

            $this->assertReversalTarget(
                $order,
                $target,
                $this->moneyMinor((string) $entry->amount),
                (string) $entry->currency,
            );

            if ((int) $target->id >= (int) $entry->id) {
                throw new DomainException('Store-credit reversal target is not causally prior in ledger identity.');
            }

            $reversedTargets[$targetId] = true;
        }
    }

    private function assertReversalTarget(
        Order $order,
        StoreCreditEntry $target,
        int $reversalAmountMinor,
        string $currency,
    ): void {
        if (! in_array($target->entry_type, self::DIRECT_ENTRY_TYPES, true)) {
            throw new DomainException('A reversal can target only an original credit or debit entry.');
        }

        if ((int) $target->order_id !== (int) $order->id) {
            throw new DomainException('The reversal target belongs to another order.');
        }

        if ((string) $target->currency !== $currency) {
            throw new DomainException('The reversal currency must match its target.');
        }

        if ($this->moneyMinor((string) $target->amount) !== $reversalAmountMinor) {
            throw new DomainException('The reversal amount must exactly match its target.');
        }
    }

    private function moneyMinor(string|int|float $amount): int
    {
        $raw = trim((string) $amount);

        if (! preg_match('/^(0|[1-9]\d{0,9})(?:\.(\d{1,2}))?$/', $raw, $matches)) {
            throw new DomainException('Store-credit amount must be a positive monetary amount with at most two decimals.');
        }

        $whole = (int) explode('.', $raw, 2)[0];
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        $minor = ($whole * 100) + (int) $fraction;

        if ($minor <= 0) {
            throw new DomainException('Store-credit amount must be strictly positive.');
        }

        return $minor;
    }

    private function moneyString(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
