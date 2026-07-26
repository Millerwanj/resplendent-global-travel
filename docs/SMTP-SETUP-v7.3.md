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

## v9.6.3 document senders

Client proposals, quotations and invoices use the same department architecture
for their visible sender. The final admin review panel shows and permits a
controlled override of the selected profile.

The existing `info@` SMTP account may authenticate these addresses when Verpex
has authorised them as aliases or send-as identities. If Verpex requires
separate mailbox authentication, add `smtp_username` and `smtp_password` to
the relevant private `document_senders` profile. See
`rgts-mail-config.example.php`.

## Validation

A success message is shown only after the SMTP server accepts the message. Delivery failures are written to the PHP error log with the enquiry reference, without logging the mailbox password.
