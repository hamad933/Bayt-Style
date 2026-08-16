<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RefundRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'return_case_id', 'refund_reference', 'refund_state', 'amount',
        'currency', 'actor_type', 'correlation_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('RefundRecord records are append-only.'));
        static::deleting(fn () => throw new LogicException('RefundRecord records are append-only.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }

}
