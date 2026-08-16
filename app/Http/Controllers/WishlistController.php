<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
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

        return view('wishlist.index', compact('products'));
    }

    public function toggle(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->published_at && $product->published_at->lte(now()), 404);

        $wishlist = $this->ids($request);
        $saved = in_array($product->id, $wishlist, true);

        if ($saved) {
            $wishlist = array_values(array_filter($wishlist, fn (int $id) => $id !== $product->id));
        } else {
            $wishlist[] = $product->id;
        }

        $request->session()->put('wishlist', $wishlist);

        return response()->json(['saved' => ! $saved, 'count' => count($wishlist)]);
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $wishlist = array_values(array_filter($this->ids($request), fn (int $id) => $id !== $product->id));
        $request->session()->put('wishlist', $wishlist);

        return redirect()->route('wishlist.index')->with('status', 'تمت إزالة القطعة من المفضلة.');
    }

    private function ids(Request $request): array
    {
        return array_values(array_unique(array_map('intval', $request->session()->get('wishlist', []))));
    }
}
