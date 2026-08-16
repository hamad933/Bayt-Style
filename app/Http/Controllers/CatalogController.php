<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'room' => ['nullable', 'string', 'max:80'],
            'material' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', 'in:under-500,500-1000,over-1000'],
            'sort' => ['nullable', 'in:recommended,newest,price-asc,price-desc'],
        ]);

        $query = Product::query()
            ->published()
            ->join('variants as dv', function ($join): void {
                $join->on('dv.product_id', '=', 'products.id')
                    ->where('dv.is_default', true)
                    ->where('dv.is_active', true);
            })
            ->select('products.*', 'dv.price as current_price')
            ->with(['category', 'defaultVariant', 'primaryMedia']);

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $term = '%'.$search.'%';
                $builder->where('products.name_ar', 'ILIKE', $term)
                    ->orWhere('products.short_description_ar', 'ILIKE', $term)
                    ->orWhere('products.material_ar', 'ILIKE', $term)
                    ->orWhere('products.room_ar', 'ILIKE', $term);
            });
        }

        if ($category = $filters['category'] ?? null) {
            $query->whereHas('category', fn (Builder $builder) => $builder->where('slug', $category));
        }

        if ($room = $filters['room'] ?? null) {
            $query->where('products.room_ar', $room);
        }

        if ($material = $filters['material'] ?? null) {
            $query->where('products.material_ar', $material);
        }

        match ($filters['price'] ?? null) {
            'under-500' => $query->where('dv.price', '<', 500),
            '500-1000' => $query->whereBetween('dv.price', [500, 1000]),
            'over-1000' => $query->where('dv.price', '>', 1000),
            default => null,
        };

        match ($filters['sort'] ?? 'recommended') {
            'newest' => $query->orderByDesc('products.published_at'),
            'price-asc' => $query->orderBy('dv.price'),
            'price-desc' => $query->orderByDesc('dv.price'),
            default => $query->orderByDesc('products.is_featured')->orderBy('products.id'),
        };

        $products = $query->paginate(6)->withQueryString();
        $categories = Category::query()->orderBy('sort_order')->get();
        $rooms = Product::query()->published()->whereNotNull('room_ar')->distinct()->orderBy('room_ar')->pluck('room_ar');
        $materials = Product::query()->published()->whereNotNull('material_ar')->distinct()->orderBy('material_ar')->pluck('material_ar');

        return view('catalog', compact('products', 'categories', 'rooms', 'materials', 'filters'));
    }
}
