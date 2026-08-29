<?php
declare(strict_types=1);

/**
 * Copy to /home/resplend/rgts-payment-config.php (outside public_html).
 * Never commit live or sandbox credentials to the website release.
 *
 * v11.7.34 supports separate sandbox and live Pesapal credentials.
 * Switching `environment` selects only the matching private block.
 */
return [
    'primary_method' => 'rtgs',
    'active_online_provider' => 'pesapal',
    'providers' => [
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
