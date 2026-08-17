<?php

namespace App\Http\Controllers\Admin;

use App\Admin\InventoryAdjustmentService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function adjust(Request $request, Product $product, Variant $variant, InventoryAdjustmentService $service): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $validated = $request->validate([
            'quantity_delta' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $service->adjust(
            $actor,
            $variant,
            (int) $validated['quantity_delta'],
            $validated['reason'],
            $this->correlation($request),
        );

        return back()->with('status', 'تم تسجيل حركة المخزون وتحديث الرصيد الحالي.');
    }

    private function correlation(Request $request): ?string
    {
        $value = $request->header('X-Correlation-ID');
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
