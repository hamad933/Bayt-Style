<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __invoke(Product $product): View
    {
        abort_unless($product->published_at && $product->published_at->lte(now()), 404);

        $product->load(['category', 'defaultVariant', 'variants.attributeOptions.attribute', 'media']);
        abort_unless($product->defaultVariant, 404);

        $variantConfig = $this->variantConfig($product);

        return view('products.show', compact('product', 'variantConfig'));
    }

    private function variantConfig(Product $product): array
    {
        $variants = $product->variants
            ->sortBy(fn (Variant $variant) => $variant->is_default ? 0 : 1)
            ->values();

        $attributes = $variants
            ->flatMap(fn (Variant $variant) => $variant->attributeOptions)
            ->map(fn ($option) => $option->attribute)
            ->filter()
            ->unique('id')
            ->sortBy('sort_order')
            ->values();

        $dimensions = $attributes->map(function ($attribute) use ($variants): array {
            $values = $variants
                ->flatMap(fn (Variant $variant) => $variant->attributeOptions
                    ->where('variant_attribute_id', $attribute->id))
                ->unique('id')
                ->sortBy('sort_order')
                ->pluck('value_ar')
                ->values()
                ->all();

            return [
                'key' => $attribute->code,
                'label' => $attribute->name_ar,
                'values' => $values,
            ];
        })->all();

        return [
            'productId' => $product->id,
            'defaultVariantId' => $product->defaultVariant->id,
            'dimensions' => $dimensions,
            'variants' => $variants->map(fn (Variant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name_ar,
                'price' => (float) $variant->price,
                'priceFormatted' => number_format((float) $variant->price, 0),
                'inventory' => $variant->inventory_quantity,
                'active' => $variant->is_active,
                'available' => $variant->isSellable(),
                'options' => $variant->optionSelection(),
            ])->all(),
        ];
    }
}
