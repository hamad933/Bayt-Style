<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryDisposition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'return_case_id', 'return_inspection_id', 'disposition', 'actor_type',
        'correlation_id', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('InventoryDisposition records are append-only.'));
        static::deleting(fn () => throw new LogicException('InventoryDisposition records are append-only.'));
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ReturnInspection::class, 'return_inspection_id');
    }

}
