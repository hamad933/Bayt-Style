<?php
namespace App\Commerce\Shipping;
interface ShippingMethodProvider
{
    public function methods(string $countryCode): array;
    public function find(string $code, string $countryCode): ?array;
}
