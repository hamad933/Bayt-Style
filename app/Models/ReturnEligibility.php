<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnEligibility extends Model
{
    protected $fillable = [
        'order_id', 'order_line_id', 'eligible_quantity', 'state',
        'source_type', 'source_reference', 'correlation_id', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'eligible_quantity' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }
}
