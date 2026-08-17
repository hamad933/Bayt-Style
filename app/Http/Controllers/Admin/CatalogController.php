<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'published', 'draft'], true)) {
            $status = 'all';
        }

        $products = Product::query()
            ->with(['category', 'defaultVariant'])
            ->withCount('variants')
            ->withSum('variants', 'inventory_quantity')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name_ar', 'ilike', '%'.$search.'%')
                        ->orWhere('slug', 'ilike', '%'.$search.'%')
                        ->orWhereHas('variants', fn ($variants) => $variants
                            ->where('sku', 'ilike', '%'.$search.'%')
                            ->orWhere('name_ar', 'ilike', '%'.$search.'%'));
                });
            })
            ->when($status === 'published', fn ($query) => $query->published())
            ->when($status === 'draft', fn ($query) => $query->where(function ($nested): void {
                $nested->whereNull('published_at')->orWhere('published_at', '>', now());
            }))
            ->orderByDesc('updated_at')
            ->orderBy('name_ar')
            ->paginate(20)
            ->withQueryString();

        return view('admin.catalog.index', compact('products', 'search', 'status'));
    }
}
