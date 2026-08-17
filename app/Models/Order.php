<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'idempotency_key', 'currency', 'customer_full_name', 'customer_email', 'customer_phone',
        'delivery_country_code', 'delivery_region', 'delivery_city', 'delivery_district', 'delivery_address_line',
        'delivery_building_unit', 'delivery_postal_code', 'delivery_notes', 'shipping_method_code', 'shipping_method_name',
        'shipping_amount', 'tax_policy_code', 'tax_amount', 'subtotal', 'total', 'payment_method_code', 'payment_state',
        'order_state', 'reservation_state', 'reservation_policy_code', 'fulfillment_state', 'consent_version', 'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'consented_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function returnEligibilities(): HasMany
    {
        return $this->hasMany(ReturnEligibility::class);
    }

    public function returnCases(): HasMany
    {
        return $this->hasMany(ReturnCase::class);
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
        return 'order_number';
    }
}
