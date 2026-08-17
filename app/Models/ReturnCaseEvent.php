<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReturnCaseEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'return_case_id', 'event_type', 'from_state', 'to_state', 'actor_type',
        'correlation_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('ReturnCaseEvent records are append-only.'));
        static::deleting(fn () => throw new LogicException('ReturnCaseEvent records are append-only.'));
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }

}
