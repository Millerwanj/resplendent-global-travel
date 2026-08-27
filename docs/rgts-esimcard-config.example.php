<?php
declare(strict_types=1);

/**
 * Copy to /home/resplend/rgts-esimcard-config.php, outside public_html.
 * Never commit the sandbox or production password.
 */
return [
    'environment' => 'sandbox',
    'base_url' => 'https://sandbox.esimcard.com/api',
    'email' => 'accounts@resplendentglobaltravel.com',
    'password' => '',
    'timeout' => 20,
    'allow_sandbox_purchase' => false,
];
