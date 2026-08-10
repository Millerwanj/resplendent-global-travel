<?php
declare(strict_types=1);

/**
 * Copy to /home/resplend/rgts-payment-config.php (outside public_html).
 * Never commit live credentials to the website release.
 */
return [
    'primary_method' => 'rtgs',
    'active_online_provider' => '',
    'providers' => [
        'pesapal' => [
            'enabled' => false,
            'environment' => 'sandbox',
            'consumer_key' => '',
            'consumer_secret' => '',
            'notification_id' => '',
        ],
        // Future provider adapters can be added here without changing invoices.
    ],
    'terminal' => [
        'enabled' => false,
        'provider' => '',
        'label' => 'Contactless terminal',
    ],
];
