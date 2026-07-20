# v7.3 SMTP setup

The production form sends through authenticated SMTP over implicit TLS.

## Server settings

- Host: `mail.resplendentglobaltravel.com`
- Port: `465`
- Username: `info@resplendentglobaltravel.com`
- Encryption: SSL/TLS

## Secret configuration

Create this file outside the public website directory:

`/home/resplend/rgts-mail-config.php`

Use `docs/rgts-mail-config.example.php` as the template and insert the current password for the `info@` mailbox. Do not upload the real configuration into `public_html`, GitHub, or a ZIP shared publicly.

## Routing

- Leisure and reservations → `bookings@resplendentglobaltravel.com`
- Corporate Travel → `corporate@resplendentglobaltravel.com`
- Global Business Connections → `business@resplendentglobaltravel.com`
- Customer Support → `support@resplendentglobaltravel.com`
- Accounts & Billing → `accounts@resplendentglobaltravel.com`
- General and multi-service enquiries → `info@resplendentglobaltravel.com`

Non-general enquiries also copy `info@` for central oversight.

## Validation

A success message is shown only after the SMTP server accepts the message. Delivery failures are written to the PHP error log with the enquiry reference, without logging the mailbox password.
