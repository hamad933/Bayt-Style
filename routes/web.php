<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/catalog', CatalogController::class)->name('catalog');
Route::get('/products/{product:slug}', ProductController::class)->name('products.show');

Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
Route::post('/wishlist/{product}/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

Route::get('/comparison', [ComparisonController::class, 'index'])->name('comparison.index');
Route::post('/comparison/{product}', [ComparisonController::class, 'store'])->name('comparison.store');
Route::delete('/comparison/{product}', [ComparisonController::class, 'destroy'])->name('comparison.destroy');
Route::delete('/comparison', [ComparisonController::class, 'clear'])->name('comparison.clear');

Route::get('/cart/summary', [CartController::class, 'summary'])->name('cart.summary');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{variant}', [CartController::class, 'destroy'])->name('cart.destroy');
