<?php
return [
    'checkout' => [
        'country_code' => 'SA',
        'country_name_ar' => 'السعودية',
        'currency' => 'SAR',
        'consent_version' => 'rp01-s06-development-consent-v1',
        'payment_method_code' => 'manual_pending_demo',
    ],
    // DEVELOPMENT / DEMO FIXTURES ONLY — not production carrier, tax, or payment policy.
    'development_shipping_methods' => [
        'demo_standard' => [
            'name_ar' => 'توصيل تجريبي قياسي',
            'amount_minor' => 3500,
        ],
    ],
    'development_tax_policy' => [
        'code' => 'demo_unconfigured_zero',
    ],
];
