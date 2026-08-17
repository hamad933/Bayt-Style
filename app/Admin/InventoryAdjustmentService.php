<?php

namespace App\Admin;

use App\Models\AdminAuditLog;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function adjust(User $actor, Variant $variant, int $delta, string $reason, ?string $correlationId = null): InventoryMovement
    {
        return DB::transaction(function () use ($actor, $variant, $delta, $reason, $correlationId): InventoryMovement {
            if ($delta === 0) {
                throw ValidationException::withMessages(['quantity_delta' => 'يجب أن يكون التعديل أكبر أو أقل من صفر.']);
            }

            $locked = Variant::query()->lockForUpdate()->findOrFail($variant->id);
            $before = (int) $locked->inventory_quantity;
            $after = $before + $delta;

            if ($after < 0) {
                throw ValidationException::withMessages(['quantity_delta' => 'لا يمكن أن ينتج عن التعديل رصيد مخزون سالب.']);
            }

            $correlation = $correlationId && Str::isUuid($correlationId)
                ? $correlationId
                : (string) Str::uuid();

            $movement = InventoryMovement::query()->create([
                'variant_id' => $locked->id,
                'movement_type' => 'admin_adjustment',
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => trim($reason),
                'actor_user_id' => $actor->id,
                'actor_identifier' => $actor->email,
                'correlation_id' => $correlation,
                'occurred_at' => now(),
            ]);

            $locked->applyInventoryProjection($after);

            AdminAuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'actor_identifier' => $actor->email,
                'action' => 'inventory.variant.adjusted',
                'entity_type' => 'variant',
                'entity_id' => $locked->id,
                'before_values' => ['inventory_quantity' => $before],
                'after_values' => ['inventory_quantity' => $after, 'quantity_delta' => $delta],
                'reason' => trim($reason),
                'correlation_id' => $correlation,
                'created_at' => now(),
            ]);

            return $movement;
        }, 3);
    }
}
