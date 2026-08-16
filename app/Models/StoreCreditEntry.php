<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StoreCreditEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'return_case_id', 'entry_type', 'amount', 'currency', 'source_type',
        'source_reference', 'reversal_of_entry_id', 'correlation_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('StoreCreditEntry records are append-only.'));
        static::deleting(fn () => throw new LogicException('StoreCreditEntry records are append-only.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_entry_id');
    }

}
