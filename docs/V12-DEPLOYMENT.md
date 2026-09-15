# Resplendent v12.5.2 — Paystack eSIM Fulfilment

This release replaces the split enquiry/sandbox provisioning journey with one controlled transaction path:

Live catalogue → customer details → Paystack checkout → independent server verification → amount/currency/reference match → locked idempotent fulfilment → eSIMCARD purchase → activation storage → secure confirmation → email delivery.

## Private configuration (outside public_html)
- Paystack test/live secret keys remain in `/home/resplend/rgts-payment-config.php`, outside `public_html`.
- Configure Paystack's webhook URL as `https://www.resplendentglobaltravel.com/api/payments/paystack-webhook.php`.
- eSIMCARD credentials remain in `/home/resplend/rgts-esimcard-config.php` (or `RGTS_ESIMCARD_CONFIG`).
- `allow_production_purchase` must be explicitly set to `true` only after production credentials, wallet funding and a controlled live acceptance test are ready.
- Do not upload private credentials into public_html or GitHub.

## Important safety behaviour
- Supplier purchase occurs only after Paystack server verification reports success and the amount, currency, provider reference and merchant reference match the server-created order.
- The signed webhook and browser callback share the same fulfilment service and per-order lock, preventing double purchase when both arrive.
- The webhook is accepted only after HMAC-SHA512 validation of the raw body against `x-paystack-signature`.
- The browser redirect is never payment proof; it only triggers independent server verification.
- Run the protected connectivity reconciliation endpoint by cPanel cron to recover missed or delayed notifications.
- If a supplier call becomes uncertain, the order enters `PROVISIONING_REVIEW`; v12 does not blindly repurchase.
- Customer activation data is available only through a 256-bit secure result token and is also emailed through the existing private SMTP configuration.
