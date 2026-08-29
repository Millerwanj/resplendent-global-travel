# API Backend Foundation — v11.7.15

## What is now built

### Payments
- Provider-neutral `PaymentProviderInterface` remains the contract.
- New Pesapal API 3.0 adapter supports sandbox/live base URLs, token generation, hosted checkout, IPN parsing, server-to-server status verification and IPN registration.
- Checkout amount/currency come from the server-side Resplendent invoice, not browser input.
- New `/api/payments/create.php`, `/api/payments/ipn.php`, and `/api/payments/callback.php` endpoints.
- Existing idempotent payment ledger is reused. Card/PIN details are never stored.
- Pesapal remains **disabled by default** until credentials and a registered `notification_id` are placed in the private config.

### Connectivity
- New `ConnectivityProviderInterface` defines the adapter contract for Carlos/eSIMCard, Firsty, 2Sky or future suppliers.
- New `ConnectivityOrchestrator` merges normalised offers, applies Resplendent retail pricing/margin logic, and ranks offers using quality, margin and value signals.
- Development-only mock adapters prove that multiple suppliers can coexist before live API credentials arrive.
- `/api/connectivity/offers.php` is a development endpoint only and must not be exposed as a live catalogue until mocks are replaced.

## Pesapal private configuration
Copy `docs/rgts-payment-config.example.php` to `/home/resplend/rgts-payment-config.php`, keep `enabled=false`, then insert sandbox credentials when issued. Register `https://www.resplendentglobaltravel.com/api/payments/ipn.php` with Pesapal and place the returned GUID in `notification_id`.

## Provider onboarding later
For each connectivity supplier we only add one adapter implementing:
1. `searchOffers()` — map supplier catalogue into Resplendent fields.
2. `provision()` — place the paid order and return eSIM/activation data.
3. `status()` — retrieve lifecycle/usage state.

The storefront and ranking engine do not need to be rewritten when a supplier is added.

## Go-live gates
- Never enable Pesapal until merchant approval, credentials, sandbox tests and one controlled production settlement are complete.
- Never provision connectivity from a browser callback alone. Fulfil only after verified payment and server-side supplier order creation.
- Remove mock connectivity providers before production.
