<?php

namespace App\Http\Controllers\Admin;

use App\Admin\CatalogAdminService;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function edit(Product $product): View
    {
        $product->load([
            'category',
            'media',
            'variants.attributeOptions.attribute',
        ]);

        $categories = Category::query()->orderBy('sort_order')->orderBy('name_ar')->get();
        $variantIds = $product->variants->pluck('id');
        $movements = InventoryMovement::query()
            ->whereIn('variant_id', $variantIds)
            ->with('variant')
            ->latest('occurred_at')
            ->latest('id')
            ->limit(20)
            ->get();
        $auditLogs = AdminAuditLog::query()
            ->where(function ($query) use ($product, $variantIds): void {
                $query->where(function ($productQuery) use ($product): void {
                    $productQuery->where('entity_type', 'product')->where('entity_id', $product->id);
                })->orWhere(function ($variantQuery) use ($variantIds): void {
                    $variantQuery->where('entity_type', 'variant')->whereIn('entity_id', $variantIds);
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.catalog.edit', compact('product', 'categories', 'movements', 'auditLogs'));
    }

    public function update(Request $request, Product $product, CatalogAdminService $service): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name_ar' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('products', 'slug')->ignore($product->id)],
            'short_description_ar' => ['required', 'string', 'max:500'],
            'description_ar' => ['required', 'string', 'max:4000'],
            'details_ar' => ['nullable', 'string', 'max:4000'],
            'material_ar' => ['nullable', 'string', 'max:160'],
            'room_ar' => ['nullable', 'string', 'max:160'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['published', 'draft'])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $validated['is_featured'] = $request->boolean('is_featured');

        /** @var User $actor */
        $actor = $request->user();
        $service->updateProduct($actor, $product, $validated, $validated['reason'], $this->correlation($request));

        return back()->with('status', 'تم حفظ بيانات المنتج مع تسجيل أثر التدقيق.');
    }

    private function correlation(Request $request): ?string
    {
        $value = $request->header('X-Correlation-ID');
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
