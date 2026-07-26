<?php
// Copy this file to /home/resplend/rgts-mail-config.php on Verpex.
// Never place the real password in public_html or commit it to GitHub.
return [
    'smtp_host' => 'mail.resplendentglobaltravel.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl', // ssl for 465; tls for 587
    'smtp_username' => 'info@resplendentglobaltravel.com',
    'smtp_password' => 'REPLACE_WITH_INFO_MAILBOX_PASSWORD',
    'smtp_timeout' => 20,
    'from_email' => 'info@resplendentglobaltravel.com',
    'from_name' => 'Resplendent Global Travel Solutions',
    'central_copy' => 'info@resplendentglobaltravel.com',
    'public_base_url' => 'https://www.resplendentglobaltravel.com',
    // Client documents use these profiles automatically by service.
    // The main SMTP credentials above may be used when these are authorised
    // send-as addresses or aliases. If Verpex requires separate authentication
    // for a mailbox, add smtp_username and smtp_password inside that profile.
    'document_senders' => [
        'bookings' => [
            'label' => 'Bookings',
            'email' => 'bookings@resplendentglobaltravel.com',
            'name' => 'Resplendent Bookings',
            'reply_to' => 'bookings@resplendentglobaltravel.com',
        ],
        'corporate' => [
            'label' => 'Corporate Travel',
            'email' => 'corporate@resplendentglobaltravel.com',
            'name' => 'Resplendent Corporate Travel',
            'reply_to' => 'corporate@resplendentglobaltravel.com',
        ],
        'business' => [
            'label' => 'Global Business Connections',
            'email' => 'business@resplendentglobaltravel.com',
            'name' => 'Resplendent Global Business Connections',
            'reply_to' => 'business@resplendentglobaltravel.com',
        ],
        'accounts' => [
            'label' => 'Accounts',
            'email' => 'accounts@resplendentglobaltravel.com',
            'name' => 'Resplendent Accounts',
            'reply_to' => 'accounts@resplendentglobaltravel.com',
        ],
        'info' => [
            'label' => 'General',
            'email' => 'info@resplendentglobaltravel.com',
            'name' => 'Resplendent Global Travel Solutions',
            'reply_to' => 'info@resplendentglobaltravel.com',
        ],
    ],
];
