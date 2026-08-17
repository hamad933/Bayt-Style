<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Admin\VariantController as AdminVariantController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReturnController;
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

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/cart/summary', [CartController::class, 'summary'])->name('cart.summary');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{variant}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/confirmation/{order:order_number}', [CheckoutController::class, 'confirmation'])
    ->name('checkout.confirmation');

Route::get('/orders/{order:order_number}', OrderStatusController::class)->name('orders.show');
Route::get('/orders/{order:order_number}/returns', [ReturnController::class, 'index'])
    ->name('orders.returns.index');
Route::post('/orders/{order:order_number}/returns', [ReturnController::class, 'store'])
    ->name('orders.returns.store');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');

    Route::middleware('catalog-admin')->group(function (): void {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/catalog', [AdminCatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalog/{product}/edit', [AdminProductController::class, 'edit'])->name('catalog.edit');
        Route::patch('/catalog/{product}', [AdminProductController::class, 'update'])->name('catalog.update');
        Route::patch('/catalog/{product}/variants/{variant}', [AdminVariantController::class, 'update'])
            ->name('variants.update');
        Route::post('/catalog/{product}/variants/{variant}/inventory-adjustments', [AdminInventoryController::class, 'adjust'])
            ->name('inventory.adjust');

        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order:order_number}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order:order_number}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order:order_number}/returns/{returnCase:return_number}/authorize', [AdminReturnController::class, 'authorizeCase'])
            ->name('returns.authorize');
        Route::post('/orders/{order:order_number}/returns/{returnCase:return_number}/receive', [AdminReturnController::class, 'receive'])
            ->name('returns.receive');
        Route::post('/orders/{order:order_number}/returns/{returnCase:return_number}/inspect', [AdminReturnController::class, 'inspect'])
            ->name('returns.inspect');
        Route::post('/orders/{order:order_number}/returns/{returnCase:return_number}/disposition', [AdminReturnController::class, 'decideDisposition'])
            ->name('returns.disposition');
    });
});
