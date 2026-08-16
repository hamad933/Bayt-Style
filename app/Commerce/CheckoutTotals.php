<?php
namespace App\Commerce;
use App\Commerce\Shipping\ShippingMethodProvider;
use App\Commerce\Tax\TaxCalculator;
use InvalidArgumentException;
class CheckoutTotals
{
    public function __construct(
        private readonly ShippingMethodProvider $shipping,
        private readonly TaxCalculator $tax,
    ) {}
    public function calculate(int $subtotalMinor, string $shippingCode, string $countryCode, string $currency): array
    {
        $method = $this->shipping->find($shippingCode, $countryCode);
        if (! $method) throw new InvalidArgumentException('Invalid shipping method.');
        $tax = $this->tax->calculate($subtotalMinor + $method['amount_minor'], $countryCode, $currency);
        return [
            'subtotal_minor' => $subtotalMinor,
            'shipping_method' => $method,
            'shipping_minor' => $method['amount_minor'],
            'tax_policy_code' => $tax['policy_code'],
            'tax_minor' => $tax['amount_minor'],
            'total_minor' => $subtotalMinor + $method['amount_minor'] + $tax['amount_minor'],
            'currency' => $currency,
        ];
    }
}
