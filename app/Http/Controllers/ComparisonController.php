<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComparisonController extends Controller
{
    public const MAX_ITEMS = 3;

    public function index(Request $request): View
    {
        $ids = $this->ids($request);
        $order = array_flip($ids);

        $products = Product::query()
            ->published()
            ->whereIn('id', $ids)
            ->with(['category', 'defaultVariant', 'primaryMedia'])
            ->get()
            ->sortBy(fn (Product $product) => $order[$product->id] ?? PHP_INT_MAX)
            ->values();

        return view('comparison.index', [
            'products' => $products,
            'comparisonLimit' => self::MAX_ITEMS,
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->published_at && $product->published_at->lte(now()), 404);

        $ids = $this->ids($request);
        if (in_array($product->id, $ids, true)) {
            return $this->result($request, $ids, true, 200);
        }

        if (count($ids) >= self::MAX_ITEMS) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'comparison' => 'يمكن مقارنة ثلاثة منتجات كحد أقصى في الوقت نفسه.',
                ]);
            }

            return redirect()->route('comparison.index')->withErrors([
                'comparison' => 'يمكن مقارنة ثلاثة منتجات كحد أقصى في الوقت نفسه.',
            ]);
        }

        $ids[] = $product->id;
        $request->session()->put('comparison', $ids);

        return $this->result($request, $ids, false, 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $ids = array_values(array_filter($this->ids($request), fn (int $id) => $id !== $product->id));
        $request->session()->put('comparison', $ids);

        if ($request->expectsJson()) {
            return response()->json($this->snapshot($ids));
        }

        return redirect()->route('comparison.index')->with('status', 'تمت إزالة المنتج من المقارنة.');
    }

    public function clear(Request $request): JsonResponse|RedirectResponse
    {
        $request->session()->forget('comparison');

        if ($request->expectsJson()) {
            return response()->json($this->snapshot([]));
        }

        return redirect()->route('comparison.index')->with('status', 'تم مسح المقارنة.');
    }

    private function result(Request $request, array $ids, bool $alreadyPresent, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                ...$this->snapshot($ids),
                'already_present' => $alreadyPresent,
            ], $status);
        }

        return redirect()->route('comparison.index');
    }

    private function snapshot(array $ids): array
    {
        return [
            'count' => count($ids),
            'ids' => array_values($ids),
            'limit' => self::MAX_ITEMS,
        ];
    }

    private function ids(Request $request): array
    {
        return array_values(array_unique(array_map('intval', $request->session()->get('comparison', []))));
    }
}
