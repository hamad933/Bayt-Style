<?php

namespace App\Admin;

use App\Models\AdminAuditLog;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogAdminService
{
    public function updateProduct(User $actor, Product $product, array $data, string $reason, ?string $correlationId = null): Product
    {
        return DB::transaction(function () use ($actor, $product, $data, $reason, $correlationId): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $before = $this->productSnapshot($locked);

            $locked->fill(Arr::only($data, [
                'category_id', 'name_ar', 'slug', 'short_description_ar', 'description_ar',
                'details_ar', 'material_ar', 'room_ar', 'is_featured',
            ]));

            if (($data['status'] ?? 'draft') === 'published') {
                if (! $locked->published_at || $locked->published_at->isFuture()) {
                    $locked->published_at = now();
                }
            } else {
                $locked->published_at = null;
            }

            $locked->save();
            $after = $this->productSnapshot($locked->fresh());
            $this->recordAudit($actor, 'catalog.product.updated', 'product', $locked->id, $before, $after, $reason, $correlationId);

            return $locked->fresh(['category', 'variants']);
        }, 3);
    }

    public function updateVariant(User $actor, Variant $variant, array $data, string $reason, ?string $correlationId = null): Variant
    {
        return DB::transaction(function () use ($actor, $variant, $data, $reason, $correlationId): Variant {
            $locked = Variant::query()->lockForUpdate()->findOrFail($variant->id);
            $before = $this->variantSnapshot($locked);

            $locked->fill(Arr::only($data, ['sku', 'name_ar', 'price', 'is_active']));
            $locked->save();

            $after = $this->variantSnapshot($locked->fresh());
            $this->recordAudit($actor, 'catalog.variant.updated', 'variant', $locked->id, $before, $after, $reason, $correlationId);

            return $locked->fresh(['product', 'attributeOptions.attribute']);
        }, 3);
    }

    private function productSnapshot(Product $product): array
    {
        return [
            'category_id' => $product->category_id,
            'name_ar' => $product->name_ar,
            'slug' => $product->slug,
            'short_description_ar' => $product->short_description_ar,
            'description_ar' => $product->description_ar,
            'details_ar' => $product->details_ar,
            'material_ar' => $product->material_ar,
            'room_ar' => $product->room_ar,
            'is_featured' => (bool) $product->is_featured,
            'status' => $product->published_at && $product->published_at->lte(now()) ? 'published' : 'draft',
        ];
    }

    private function variantSnapshot(Variant $variant): array
    {
        return [
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            'name_ar' => $variant->name_ar,
            'price' => number_format((float) $variant->price, 2, '.', ''),
            'currency' => $variant->currency,
            'is_default' => (bool) $variant->is_default,
            'is_active' => (bool) $variant->is_active,
        ];
    }

    private function recordAudit(
        User $actor,
        string $action,
        string $entityType,
        int $entityId,
        array $before,
        array $after,
        string $reason,
        ?string $correlationId,
    ): void {
        if ($before === $after) {
            return;
        }

        AdminAuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_identifier' => $actor->email,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_values' => $before,
            'after_values' => $after,
            'reason' => trim($reason),
            'correlation_id' => $this->correlationId($correlationId),
            'created_at' => now(),
        ]);
    }

    private function correlationId(?string $candidate): string
    {
        return $candidate && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
    }
}
