<?php
namespace App\Providers;
use App\Commerce\Shipping\DevelopmentShippingMethodProvider;
use App\Commerce\Shipping\ShippingMethodProvider;
use App\Commerce\Tax\DevelopmentTaxCalculator;
use App\Commerce\Tax\TaxCalculator;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShippingMethodProvider::class, DevelopmentShippingMethodProvider::class);
        $this->app->bind(TaxCalculator::class, DevelopmentTaxCalculator::class);
    }
    public function boot(): void {}
}
