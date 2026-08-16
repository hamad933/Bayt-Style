<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Variant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'name_ar', 'price', 'currency',
        'inventory_quantity', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'inventory_quantity' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            VariantAttributeOption::class,
            'variant_attribute_option_variant',
            'variant_id',
            'variant_attribute_option_id'
        );
    }

    public function optionSelection(): array
    {
        $options = $this->relationLoaded('attributeOptions')
            ? $this->attributeOptions
            : $this->attributeOptions()->with('attribute')->get();

        $options->loadMissing('attribute');

        return $options
            ->filter(fn (VariantAttributeOption $option) => $option->attribute !== null)
            ->sortBy(fn (VariantAttributeOption $option) => [$option->attribute->sort_order, $option->sort_order])
            ->mapWithKeys(fn (VariantAttributeOption $option): array => [
                $option->attribute->code => $option->value_ar,
            ])
            ->all();
    }

    public function isSellable(): bool
    {
        return $this->is_active && $this->inventory_quantity > 0;
    }
}
