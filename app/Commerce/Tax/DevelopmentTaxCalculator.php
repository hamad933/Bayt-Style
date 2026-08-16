<?php
namespace App\Commerce\Tax;
class DevelopmentTaxCalculator implements TaxCalculator
{
    public function calculate(int $taxableMinor, string $countryCode, string $currency): array
    {
        return [
            'policy_code' => (string) config('commerce.development_tax_policy.code', 'demo_unconfigured_zero'),
            'amount_minor' => 0,
            'configured_for_production' => false,
        ];
    }
}
