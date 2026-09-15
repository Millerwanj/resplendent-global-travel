<?php
declare(strict_types=1);

/**
 * Copy to /home/resplend/rgts-payment-config.php (outside public_html).
 * Never commit live or sandbox credentials to the website release.
 *
 * v12.5 supports Paystack test/live keys and retains Pesapal as a disabled fallback.
 */
return [
    'primary_method' => 'rtgs',
    'active_online_provider' => 'paystack',
    'providers' => [
        'paystack' => [
            'enabled' => true,
            'environment' => 'test', // test | live
            // Optional `channels` may be added later; omitted so Paystack presents
            // every payment method enabled for the Resplendent merchant account.
            'environments' => [
                'test' => ['secret_key' => ''],
                'live' => ['secret_key' => ''],
            ],
        ],
        'pesapal' => [
            'enabled' => false,
            'environment' => 'sandbox', // sandbox | live
            'ipn_url' => 'https://www.resplendentglobaltravel.com/api/payments/ipn.php',
            'environments' => [
                'sandbox' => [
                    'consumer_key' => '',
                    'consumer_secret' => '',
                    'notification_id' => '',
                ],
                'live' => [
                    'consumer_key' => '',
                    'consumer_secret' => '',
                    'notification_id' => '',
                ],
            ],
        ],
    ],
    'terminal' => [
        'enabled' => false,
        'provider' => '',
        'label' => 'Contactless terminal',
    ],
];
