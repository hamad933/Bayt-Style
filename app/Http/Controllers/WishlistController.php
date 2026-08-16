<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function toggle(Request $request, Product $product): JsonResponse
    {
        $wishlist = array_values(array_unique(array_map('intval', $request->session()->get('wishlist', []))));
        $saved = in_array($product->id, $wishlist, true);

        if ($saved) {
            $wishlist = array_values(array_filter($wishlist, fn (int $id) => $id !== $product->id));
        } else {
            $wishlist[] = $product->id;
        }

        $request->session()->put('wishlist', $wishlist);

        return response()->json(['saved' => ! $saved, 'count' => count($wishlist)]);
    }
}
