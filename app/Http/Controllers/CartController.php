<?php

namespace App\Http\Controllers;

use App\Models\Variant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $variant = Variant::query()->whereKey($validated['variant_id'])->where('is_active', true)->firstOrFail();
        $cart = $request->session()->get('cart', []);
        $current = (int) ($cart[$variant->id] ?? 0);
        $next = $current + (int) $validated['quantity'];

        if ($next > 10) {
            throw ValidationException::withMessages(['quantity' => 'الحد الأقصى للكمية من القطعة الواحدة هو 10.']);
        }

        $cart[$variant->id] = $next;
        $request->session()->put('cart', $cart);

        return response()->json($this->snapshot($request), 201);
    }

    public function update(Request $request, Variant $variant): JsonResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10']]);
        $cart = $request->session()->get('cart', []);
        abort_unless(array_key_exists($variant->id, $cart), 404);
        $cart[$variant->id] = (int) $validated['quantity'];
        $request->session()->put('cart', $cart);

        return response()->json($this->snapshot($request));
    }

    public function destroy(Request $request, Variant $variant): JsonResponse
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$variant->id]);
        $request->session()->put('cart', $cart);

        return response()->json($this->snapshot($request));
    }

    private function snapshot(Request $request): array
    {
        $cart = $request->session()->get('cart', []);
        $variants = Variant::query()
            ->with(['product.primaryMedia'])
            ->whereIn('id', array_keys($cart))
            ->get()
            ->keyBy('id');

        $items = [];
        $count = 0;
        $total = 0.0;

        foreach ($cart as $variantId => $quantity) {
            $variant = $variants->get((int) $variantId);
            if (! $variant) {
                continue;
            }
            $quantity = (int) $quantity;
            $lineTotal = (float) $variant->price * $quantity;
            $count += $quantity;
            $total += $lineTotal;
            $items[] = [
                'variant_id' => $variant->id,
                'product' => $variant->product->name_ar,
                'variant' => $variant->name_ar,
                'quantity' => $quantity,
                'price' => number_format((float) $variant->price, 0),
                'line_total' => number_format($lineTotal, 0),
                'image' => $variant->product->primaryMedia ? asset($variant->product->primaryMedia->path) : null,
            ];
        }

        return ['count' => $count, 'total' => number_format($total, 0), 'items' => $items];
    }
}
