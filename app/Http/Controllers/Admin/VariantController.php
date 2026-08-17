<?php

namespace App\Http\Controllers\Admin;

use App\Admin\CatalogAdminService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VariantController extends Controller
{
    public function update(Request $request, Product $product, Variant $variant, CatalogAdminService $service): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:80', Rule::unique('variants', 'sku')->ignore($variant->id)],
            'name_ar' => ['required', 'string', 'max:160'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        /** @var User $actor */
        $actor = $request->user();
        $service->updateVariant($actor, $variant, $validated, $validated['reason'], $this->correlation($request));

        return back()->with('status', 'تم حفظ بيانات الخيار مع تسجيل أثر التدقيق.');
    }

    private function correlation(Request $request): ?string
    {
        $value = $request->header('X-Correlation-ID');
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
