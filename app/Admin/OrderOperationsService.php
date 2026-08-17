<?php

namespace App\Admin;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderOperationsService
{
    public function cancelPendingOrder(User $actor, Order $order, string $reason, ?string $correlationId = null): Order
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('A documented reason is required.');
        }

        $correlationId = $this->correlationId($correlationId);

        return DB::transaction(function () use ($actor, $order, $reason, $correlationId): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $replayed = $locked->events()
                ->where('event_type', 'admin_order_cancelled')
                ->where('correlation_id', $correlationId)
                ->exists();

            if ($replayed) {
                if ($locked->order_state === 'cancelled') {
                    return $locked->fresh();
                }

                throw new DomainException('Correlation context was already consumed by another order transition.');
            }

            if ($locked->order_state !== 'pending_payment') {
                throw new DomainException('Only a pending-payment order can be cancelled by this bounded workflow.');
            }

            if ($locked->reservation_state !== 'not_reserved' || $locked->fulfillment_state !== 'not_started') {
                throw new DomainException('Cancellation is blocked because reservation or fulfillment truth already advanced.');
            }

            $before = $this->snapshot($locked);
            $locked->forceFill(['order_state' => 'cancelled'])->save();
            $after = $this->snapshot($locked->fresh());
            $now = now();

            $locked->events()->create([
                'event_type' => 'admin_order_cancelled',
                'actor_type' => 'catalog_admin',
                'entity_type' => 'order',
                'order_reference' => $locked->order_number,
                'resulting_order_state' => $locked->order_state,
                'resulting_payment_state' => $locked->payment_state,
                'resulting_reservation_state' => $locked->reservation_state,
                'resulting_fulfillment_state' => $locked->fulfillment_state,
                'reason_code' => 'admin_cancelled_pending_order',
                'correlation_id' => $correlationId,
                'occurred_at' => $now,
            ]);

            AdminAuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'actor_identifier' => $actor->email,
                'action' => 'orders.pending.cancelled',
                'entity_type' => 'order',
                'entity_id' => $locked->id,
                'before_values' => $before,
                'after_values' => $after,
                'reason' => $reason,
                'correlation_id' => $correlationId,
                'created_at' => $now,
            ]);

            return $locked->fresh(['events']);
        }, 3);
    }

    private function snapshot(Order $order): array
    {
        return [
            'order_state' => $order->order_state,
            'payment_state' => $order->payment_state,
            'reservation_state' => $order->reservation_state,
            'fulfillment_state' => $order->fulfillment_state,
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
