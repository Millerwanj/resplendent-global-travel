# Resplendent v12.0 — Unified eSIM Fulfilment

This release replaces the split enquiry/sandbox provisioning journey with one controlled transaction path:

Live catalogue → customer details → PesaPal checkout → independent server verification → amount/currency/reference match → locked idempotent fulfilment → eSIMCARD purchase → activation storage → secure confirmation → email delivery.

## Private configuration (outside public_html)
- PesaPal credentials and notification ID remain in the existing private payment configuration.
- eSIMCARD credentials remain in `/home/resplend/rgts-esimcard-config.php` (or `RGTS_ESIMCARD_CONFIG`).
- `allow_production_purchase` must be explicitly set to `true` only after production credentials, wallet funding and a controlled live acceptance test are ready.
- Do not upload private credentials into public_html or GitHub.

## Important safety behaviour
- Supplier purchase occurs only after PesaPal reports COMPLETED and the amount, currency and merchant reference match the server-created order.
- IPN and browser callback share the same fulfilment service and per-order lock, preventing double purchase when both arrive.
- If a supplier call becomes uncertain, the order enters `PROVISIONING_REVIEW`; v12 does not blindly repurchase.
- Customer activation data is available only through a 256-bit secure result token and is also emailed through the existing private SMTP configuration.
