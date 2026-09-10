# Resplendent eSIM provisioning v12.1

## Lifecycle

1. The customer selects a current supplier-backed offer.
2. A Resplendent order is created in PAYMENT_PENDING.
3. PesaPal or the configured payment provider processes payment.
4. The server independently verifies tracking ID, merchant reference, currency and amount.
5. Only a verified matching payment can enter PAID.
6. A per-order lock prevents concurrent supplier purchases.
7. The supplier purchase reference is stored before any follow-up status check.
8. Immediate activation becomes PROVISIONED; delayed activation becomes AWAITING_SUPPLIER.
9. Customer status requests poll the supplier at most once every 30 seconds.
10. A protected reconciliation endpoint can process delayed orders independently.
11. Activation data is saved before email delivery.
12. Failed delivery can be retried independently and never initiates another supplier purchase.

## Operational states

- PAYMENT_PENDING: checkout created; payment not yet verified.
- PAYMENT_REVIEW: provider response does not match the Resplendent order.
- PAID: payment verified; eligible for one supplier purchase.
- PROVISIONING: supplier purchase is being initiated under lock.
- AWAITING_SUPPLIER: supplier reference saved; activation is not ready yet.
- PROVISIONED: activation details saved.
- FULFILMENT_REVIEW: supplier status could not be determined safely.
- FULFILMENT_FAILED: supplier reported failure; manual payment review is required.

Delivery has independent PENDING, SENT, FAILED, NOT_CONFIGURED, and INVALID_ADDRESS states.

## Production controls

- Production purchasing remains disabled unless allow_production_purchase is true in the private eSIMCARD configuration.
- The protected reconciliation endpoint accepts its token only through the X-RGTS-Reconcile-Token header.
- Configure RGTS_CONNECTIVITY_RECONCILE_TOKEN or reconcile_token in the private eSIMCARD configuration.
- Schedule /api/connectivity/reconcile.php only after setting that secret.
- Never expose supplier, SMTP, payment, or reconciliation secrets under public_html.
