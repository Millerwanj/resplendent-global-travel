# Resplendent v9.6.1 Admin Setup

This file is private. Do not publish it separately or share its setup token.

## First sign-in

1. Deploy the complete v9.6.1 Production package to `public_html`.
2. Visit `https://www.resplendentglobaltravel.com/admin/`.
3. Enter this one-time setup token:

   `281af373040dc162b1411ac9e452e51b`

4. Create the administrator email and a strong password of at least 12 characters.
5. Store that password securely.

The setup page creates `/home/resplend/rgts-admin-config.php` outside
`public_html`. Once the account exists, the setup token cannot be used again.

## Manual fallback

If the first-time setup page reports that it cannot write the private file,
create `/home/resplend/rgts-admin-config.php` through cPanel and paste:

```php
<?php
return [
    'display_name' => 'Resplendent Team',
    'email' => 'YOUR-ADMIN-EMAIL',
    'password_hash' => 'PASTE-A-PHP-PASSWORD-HASH-HERE',
    'created_at' => '2026-07-25T00:00:00Z',
];
```

Generate the password hash using cPanel Terminal:

```bash
php -r "echo password_hash('YOUR-STRONG-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```
