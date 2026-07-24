# QA — v9.5.2 Production Stable

## Completed checks

- PHP syntax: `form-handler.php` passed.
- PHP syntax: `includes/SmtpMailer.php` passed.
- JavaScript syntax: `assets/js/app.js` passed.
- JavaScript syntax: `assets/js/site-config.js` passed.
- All public HTML files use v9.5.2 cache/version stamps.
- Customer acknowledgement is sequenced after successful Zoho and departmental SMTP stages.
- Customer acknowledgement uses the original customer email as recipient and the Resplendent central mailbox as reply-to.
- Acknowledgement failure is logged as `customer_ack/failed` without losing a successfully captured enquiry.
- ZIP integrity passed.

## Live evidence inherited from v9.5.1

The immediately preceding build was verified in production for Zoho Lead creation and departmental SMTP delivery. v9.5.2 retains that proven path and adds the isolated acknowledgement step. A final live acknowledgement receipt should be confirmed after deployment.
