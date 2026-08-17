<?php

namespace App\Admin;

use App\Commerce\Returns\ReturnCaseService;
use App\Models\AdminAuditLog;
use App\Models\ReturnCase;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnOperationsService
{
    public function __construct(private readonly ReturnCaseService $returns)
    {
    }

    public function authorize(User $actor, ReturnCase $case, string $reason, ?string $correlationId = null): ReturnCase
    {
        return $this->mutate($actor, $case, 'returns.case.authorized', $reason, $correlationId,
            fn (ReturnCase $locked, string $cid) => $this->returns->authorize($locked, 'catalog_admin', $cid));
    }

    public function receive(User $actor, ReturnCase $case, string $reason, ?string $correlationId = null): ReturnCase
    {
        return $this->mutate($actor, $case, 'returns.case.received', $reason, $correlationId,
            fn (ReturnCase $locked, string $cid) => $this->returns->recordReceipt($locked, 'catalog_admin', $cid));
    }

    public function inspect(User $actor, ReturnCase $case, string $outcome, string $reason, ?string $correlationId = null): ReturnCase
    {
        $outcome = trim($outcome);
        if ($outcome === '') {
            throw new DomainException('Inspection outcome is required.');
        }

        return $this->mutate($actor, $case, 'returns.case.inspected', $reason, $correlationId,
            fn (ReturnCase $locked, string $cid) => $this->returns->recordInspection($locked, $outcome, 'catalog_admin', $cid));
    }

    public function decideDisposition(User $actor, ReturnCase $case, string $disposition, string $reason, ?string $correlationId = null): ReturnCase
    {
        return $this->mutate($actor, $case, 'returns.case.disposition_decided', $reason, $correlationId,
            fn (ReturnCase $locked, string $cid) => $this->returns->decideDisposition($locked, $disposition, 'catalog_admin', $cid));
    }

    private function mutate(
        User $actor,
        ReturnCase $case,
        string $action,
        string $reason,
        ?string $correlationId,
        callable $mutation,
    ): ReturnCase {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('A documented reason is required.');
        }

        $correlationId = $this->correlationId($correlationId);

        return DB::transaction(function () use ($actor, $case, $action, $reason, $correlationId, $mutation): ReturnCase {
            $locked = ReturnCase::query()->lockForUpdate()->findOrFail($case->id);
            $before = $this->snapshot($locked);

            $mutation($locked, $correlationId);

            $afterCase = ReturnCase::query()->findOrFail($locked->id);
            $after = $this->snapshot($afterCase);

            AdminAuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'actor_identifier' => $actor->email,
                'action' => $action,
                'entity_type' => 'return_case',
                'entity_id' => $locked->id,
                'before_values' => $before,
                'after_values' => $after,
                'reason' => $reason,
                'correlation_id' => $correlationId,
                'created_at' => now(),
            ]);

            return $afterCase->fresh(['events', 'inspection', 'inventoryDisposition', 'refundRecords', 'storeCreditEntries']);
        }, 3);
    }

    private function snapshot(ReturnCase $case): array
    {
        return [
            'return_state' => $case->return_state,
            'authorized_at' => $case->authorized_at?->toISOString(),
            'received_at' => $case->received_at?->toISOString(),
            'inspected_at' => $case->inspected_at?->toISOString(),
            'disposition_decided_at' => $case->disposition_decided_at?->toISOString(),
        ];
    }

    private function correlationId(?string $candidate): string
    {
        if ($candidate === null || trim($candidate) === '') {
            return (string) Str::uuid();
        }

        if (! Str::isUuid($candidate)) {
            throw new DomainException('Invalid correlation context.');
        }

        return $candidate;
    }
}
