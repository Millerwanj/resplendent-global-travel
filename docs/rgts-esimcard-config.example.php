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
    'retail_markup_percent' => 25,
    'allow_sandbox_purchase' => false,
    'allow_production_purchase' => false, // Set true only after live acceptance testing and wallet funding.
];
