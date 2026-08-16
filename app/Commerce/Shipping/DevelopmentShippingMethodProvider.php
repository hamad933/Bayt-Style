<?php
namespace App\Commerce\Shipping;
class DevelopmentShippingMethodProvider implements ShippingMethodProvider
{
    public function methods(string $countryCode): array
    {
        if ($countryCode !== config('commerce.checkout.country_code')) return [];
        return collect(config('commerce.development_shipping_methods', []))
            ->map(fn (array $method, string $code): array => [
                'code' => $code,
                'name_ar' => $method['name_ar'],
                'amount_minor' => (int) $method['amount_minor'],
                'fixture' => true,
            ])->values()->all();
    }
    public function find(string $code, string $countryCode): ?array
    {
        return collect($this->methods($countryCode))->firstWhere('code', $code);
    }
}
