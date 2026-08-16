<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name_ar', 'slug', 'short_description_ar', 'description_ar',
        'details_ar', 'material_ar', 'room_ar', 'is_featured', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(Variant::class)->where('is_default', true)->where('is_active', true);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    public function primaryMedia(): HasOne
    {
        return $this->hasOne(ProductMedia::class)->ofMany('sort_order', 'min');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function resolveVariant(array $options): ?Variant
    {
        $normalized = $this->normalizeOptions($options);
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        return $variants->first(function (Variant $variant) use ($normalized): bool {
            return $this->normalizeOptions($variant->options ?? []) === $normalized;
        });
    }

    private function normalizeOptions(array $options): array
    {
        $normalized = collect($options)
            ->filter(fn ($value, $key) => is_string($key) && is_scalar($value) && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->all();

        ksort($normalized);

        return $normalized;
    }
}
