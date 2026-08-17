<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

class Variant extends Model
{
    private bool $inventoryProjectionMutationAuthorized = false;

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

    protected static function booted(): void
    {
        static::created(function (Variant $variant): void {
            if (Schema::hasTable('inventory_movements') && ! $variant->inventoryMovements()->exists()) {
                InventoryMovement::query()->create([
                    'variant_id' => $variant->id,
                    'movement_type' => 'opening_balance',
                    'quantity_delta' => (int) $variant->inventory_quantity,
                    'quantity_before' => 0,
                    'quantity_after' => (int) $variant->inventory_quantity,
                    'reason' => 'Initial inventory balance captured when the variant was created.',
                    'actor_user_id' => null,
                    'actor_identifier' => 'system:variant-create',
                    'correlation_id' => (string) Str::uuid(),
                    'occurred_at' => now(),
                ]);
            }
        });

        static::updating(function (Variant $variant): void {
            if ($variant->isDirty('inventory_quantity') && ! $variant->inventoryProjectionMutationAuthorized) {
                throw new LogicException('Inventory projection must be changed through the inventory movement boundary.');
            }
        });

        static::deleting(function (Variant $variant): void {
            if ($variant->orderLines()->exists()) {
                throw new LogicException('Ordered variants must be deactivated instead of deleted.');
            }
        });
    }

    public function applyInventoryProjection(int $quantity): void
    {
        if ($quantity < 0) {
            throw new LogicException('Inventory projection cannot be negative.');
        }

        $this->inventoryProjectionMutationAuthorized = true;

        try {
            $this->inventory_quantity = $quantity;
            $this->save();
        } finally {
            $this->inventoryProjectionMutationAuthorized = false;
        }
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

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->orderByDesc('occurred_at')->orderByDesc('id');
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
