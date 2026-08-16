<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featuredProducts = Product::query()
            ->published()
            ->where('is_featured', true)
            ->with(['defaultVariant', 'primaryMedia', 'variants'])
            ->orderBy('id')
            ->limit(4)
            ->get();

        return view('home', compact('featuredProducts'));
    }
}
