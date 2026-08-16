<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Variant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'name_ar', 'price', 'currency',
        'inventory_quantity', 'is_default', 'is_active', 'options',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'inventory_quantity' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isSellable(): bool
    {
        return $this->is_active && $this->inventory_quantity > 0;
    }
}
