<?php

namespace App\Commerce\Returns;

use App\Models\InventoryDisposition;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\ReturnCase;
use App\Models\ReturnEligibility;
use App\Models\ReturnInspection;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnCaseService
{
    private const DISPOSITIONS = [
        'sellable',
        'damaged',
        'repair',
        'return_to_supplier',
        'disposal',
        'hold',
    ];

    public function request(
        Order $order,
        OrderLine $line,
        int $quantity,
        string $reasonCode,
        string $authorityFingerprint,
        ?string $correlationId = null,
    ): ReturnCase {
        if ((int) $line->order_id !== (int) $order->id) {
            throw new DomainException('The order line does not belong to this order.');
        }

        if ($quantity < 1) {
            throw new DomainException('Return quantity must be positive.');
        }

        return DB::transaction(function () use (
            $order,
            $line,
            $quantity,
            $reasonCode,
            $authorityFingerprint,
            $correlationId,
        ): ReturnCase {
            $eligibility = ReturnEligibility::query()
                ->where('order_id', $order->id)
                ->where('order_line_id', $line->id)
                ->where('state', 'active')
                ->lockForUpdate()
                ->first();

            if (! $eligibility) {
                throw new DomainException('No durable return eligibility exists for this order line.');
            }

            $alreadyRequested = (int) ReturnCase::query()
                ->where('order_id', $order->id)
                ->where('order_line_id', $line->id)
                ->sum('requested_quantity');

            $eligibleQuantity = min((int) $eligibility->eligible_quantity, (int) $line->quantity);

            if ($quantity > ($eligibleQuantity - $alreadyRequested)) {
                throw new DomainException('Requested quantity exceeds the durable eligible quantity.');
            }

            $correlationId ??= (string) Str::uuid();
            $now = now();

            $case = ReturnCase::query()->create([
                'return_number' => $this->returnNumber(),
                'order_id' => $order->id,
                'order_line_id' => $line->id,
                'requested_quantity' => $quantity,
                'reason_code' => $reasonCode,
                'return_state' => 'requested',
                'authority_type' => 'checkout_session',
                'authority_fingerprint' => $authorityFingerprint,
                'correlation_id' => $correlationId,
                'requested_at' => $now,
            ]);

            $this->appendEvent($case, 'return_requested', null, 'requested', 'guest_customer', $correlationId, $now);

            return $case;
        }, 3);
    }

    public function authorize(ReturnCase $case, string $actorType = 'operations', ?string $correlationId = null): ReturnCase
    {
        return $this->transition($case, 'requested', 'authorized', 'return_authorized', 'authorized_at', $actorType, $correlationId);
    }

    public function recordReceipt(ReturnCase $case, string $actorType = 'operations', ?string $correlationId = null): ReturnCase
    {
        return $this->transition($case, 'authorized', 'received', 'return_received', 'received_at', $actorType, $correlationId);
    }

    public function recordInspection(
        ReturnCase $case,
        string $inspectionOutcome,
        string $actorType = 'operations',
        ?string $correlationId = null,
    ): ReturnInspection {
        return DB::transaction(function () use ($case, $inspectionOutcome, $actorType, $correlationId): ReturnInspection {
            $locked = ReturnCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($locked->return_state !== 'received') {
                throw new DomainException('A return must be received before it can be inspected.');
            }

            if ($locked->inspection()->exists()) {
                throw new DomainException('This return already has an inspection record.');
            }

            $correlationId ??= (string) Str::uuid();
            $now = now();

            $inspection = $locked->inspection()->create([
                'inspection_outcome' => $inspectionOutcome,
                'actor_type' => $actorType,
                'correlation_id' => $correlationId,
                'inspected_at' => $now,
            ]);

            $locked->forceFill([
                'return_state' => 'inspected',
                'inspected_at' => $now,
            ])->save();

            $this->appendEvent($locked, 'return_inspected', 'received', 'inspected', $actorType, $correlationId, $now);

            return $inspection;
        }, 3);
    }

    public function decideDisposition(
        ReturnCase $case,
        string $disposition,
        string $actorType = 'operations',
        ?string $correlationId = null,
    ): InventoryDisposition {
        if (! in_array($disposition, self::DISPOSITIONS, true)) {
            throw new DomainException('Unsupported inventory disposition.');
        }

        return DB::transaction(function () use ($case, $disposition, $actorType, $correlationId): InventoryDisposition {
            $locked = ReturnCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($locked->return_state !== 'inspected') {
                throw new DomainException('Inventory disposition requires completed inspection truth.');
            }

            $inspection = $locked->inspection()->first();

            if (! $inspection) {
                throw new DomainException('Inventory disposition requires an inspection record.');
            }

            if ($locked->inventoryDisposition()->exists()) {
                throw new DomainException('This return already has an inventory disposition.');
            }

            $correlationId ??= (string) Str::uuid();
            $now = now();

            $record = $locked->inventoryDisposition()->create([
                'return_inspection_id' => $inspection->id,
                'disposition' => $disposition,
                'actor_type' => $actorType,
                'correlation_id' => $correlationId,
                'decided_at' => $now,
            ]);

            $locked->forceFill([
                'return_state' => 'disposition_decided',
                'disposition_decided_at' => $now,
            ])->save();

            $this->appendEvent(
                $locked,
                'disposition_decided',
                'inspected',
                'disposition_decided',
                $actorType,
                $correlationId,
                $now,
            );

            return $record;
        }, 3);
    }

    private function transition(
        ReturnCase $case,
        string $expectedState,
        string $nextState,
        string $eventType,
        string $timestampColumn,
        string $actorType,
        ?string $correlationId,
    ): ReturnCase {
        return DB::transaction(function () use (
            $case,
            $expectedState,
            $nextState,
            $eventType,
            $timestampColumn,
            $actorType,
            $correlationId,
        ): ReturnCase {
            $locked = ReturnCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($locked->return_state !== $expectedState) {
                throw new DomainException("Return transition requires {$expectedState} state.");
            }

            $correlationId ??= (string) Str::uuid();
            $now = now();

            $locked->forceFill([
                'return_state' => $nextState,
                $timestampColumn => $now,
            ])->save();

            $this->appendEvent($locked, $eventType, $expectedState, $nextState, $actorType, $correlationId, $now);

            return $locked->fresh();
        }, 3);
    }

    private function appendEvent(
        ReturnCase $case,
        string $eventType,
        ?string $fromState,
        string $toState,
        string $actorType,
        string $correlationId,
        $occurredAt,
    ): void {
        $case->events()->create([
            'event_type' => $eventType,
            'from_state' => $fromState,
            'to_state' => $toState,
            'actor_type' => $actorType,
            'correlation_id' => $correlationId,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function returnNumber(): string
    {
        do {
            $number = 'BAS-R-'.now()->format('ymd').'-'.strtoupper(Str::random(8));
        } while (ReturnCase::query()->where('return_number', $number)->exists());

        return $number;
    }
}
