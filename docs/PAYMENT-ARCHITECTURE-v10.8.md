# Payment architecture — v10.8

## Current position

RTGS bank transfer remains the preferred live payment method. The website does
not claim that online or contactless checkout is active until a provider has
approved the merchant account, issued credentials and passed production tests.

## Provider-neutral design

- `PaymentProviderInterface` defines checkout, notification parsing and
  server-to-server verification for any online provider.
- `PaymentCoordinator` selects an enabled adapter from private configuration.
- Provider credentials remain outside `public_html` in
  `/home/resplend/rgts-payment-config.php`.
- The operations store records a small, idempotent transaction ledger using
  provider references. It does not store card numbers, PINs or raw payloads.
- Physical contactless terminals remain a separate channel. Staff can reconcile
  their provider reference against the Resplendent invoice; a terminal API can
  use the same ledger later.

## Non-negotiable verification rules

1. Amount, currency and invoice reference come from the server-side invoice.
2. Callback or notification parameters are references, not proof of payment.
3. The provider adapter must query the provider server for transaction status.
4. Only a completed, verified transaction may update an invoice.
5. The idempotency key prevents repeated callbacks from double-crediting it.
6. A payment receipt does not confirm supplier space, permits or ticketing;
   confirmation is issued separately in writing.

## Provider activation checklist

1. Complete merchant approval and settlement-account checks.
2. Install the provider adapter implementing `PaymentProviderInterface`.
3. Add credentials to the private configuration file.
4. Register the HTTPS notification URL required by that provider.
5. Test success, failure, cancellation, duplicate notification, partial payment,
   refund/reversal and currency mismatch scenarios in sandbox.
6. Complete a controlled production transaction and reconcile settlement before
   enabling the public payment link.
