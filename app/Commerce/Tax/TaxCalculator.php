<?php
namespace App\Commerce\Tax;
interface TaxCalculator
{
    public function calculate(int $taxableMinor, string $countryCode, string $currency): array;
}
