<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __invoke(Product $product): View
    {
        abort_unless($product->published_at && $product->published_at->lte(now()), 404);

        $product->load(['category', 'defaultVariant', 'media']);
        abort_unless($product->defaultVariant, 404);

        return view('products.show', compact('product'));
    }
}
