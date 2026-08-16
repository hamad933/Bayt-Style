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

        $product->load(['category', 'defaultVariant', 'variants', 'media']);
        abort_unless($product->defaultVariant, 404);

        $variantConfig = $this->variantConfig($product);

        return view('products.show', compact('product', 'variantConfig'));
    }

    private function variantConfig(Product $product): array
    {
        $labels = collect([
            'color' => 'اللون',
            'finish' => 'تشطيب القاعدة',
            'material' => 'الخامة',
            'size' => 'المقاس',
        ]);

        $variants = $product->variants
            ->sortBy(fn (Variant $variant) => $variant->is_default ? 0 : 1)
            ->values();

        $keys = $variants
            ->flatMap(fn (Variant $variant) => array_keys($variant->options ?? []))
            ->unique()
            ->sortBy(fn (string $key) => ($labels->keys()->search($key) === false ? 99 : $labels->keys()->search($key)))
            ->values();

        $dimensions = $keys->map(function (string $key) use ($variants, $labels): array {
            return [
                'key' => $key,
                'label' => $labels->get($key, $key),
                'values' => $variants
                    ->map(fn (Variant $variant) => $variant->options[$key] ?? null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
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
                'options' => $variant->options ?? [],
            ])->all(),
        ];
    }
}
