<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReturnInspection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'return_case_id', 'inspection_outcome', 'actor_type', 'correlation_id', 'inspected_at',
    ];

    protected function casts(): array
    {
        return ['inspected_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('ReturnInspection records are append-only.'));
        static::deleting(fn () => throw new LogicException('ReturnInspection records are append-only.'));
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }

}
