<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnCase extends Model
{
    protected $fillable = [
        'return_number', 'order_id', 'order_line_id', 'requested_quantity', 'reason_code',
        'return_state', 'authority_type', 'authority_fingerprint', 'correlation_id',
        'requested_at', 'authorized_at', 'received_at', 'inspected_at',
        'disposition_decided_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'requested_at' => 'datetime',
            'authorized_at' => 'datetime',
            'received_at' => 'datetime',
            'inspected_at' => 'datetime',
            'disposition_decided_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function events(): HasMany
    {
        return $this->hasMany(ReturnCaseEvent::class);
    }

    public function inspection(): HasOne
    {
        return $this->hasOne(ReturnInspection::class);
    }

    public function inventoryDisposition(): HasOne
    {
        return $this->hasOne(InventoryDisposition::class);
    }

    public function refundRecords(): HasMany
    {
        return $this->hasMany(RefundRecord::class);
    }

    public function storeCreditEntries(): HasMany
    {
        return $this->hasMany(StoreCreditEntry::class);
    }

    public function getRouteKeyName(): string
    {
        return 'return_number';
    }
}
