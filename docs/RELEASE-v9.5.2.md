# Release v9.5.2 — Production Stable

**Release date:** 24 July 2026

## Purpose

v9.5.2 closes Phase 1 as the first verified stable production release of the Resplendent website enquiry pipeline.

## Production workflow

1. Validate the website enquiry.
2. Apply the corrected anti-spam honeypot check.
3. Create the Zoho CRM Lead.
4. Route the full enquiry to the responsible departmental mailbox and copy the central mailbox where configured.
5. Send the customer an acknowledgement containing the enquiry reference and confirmation that the request is being reviewed.
6. Record each pipeline stage in the protected operational log.

The acknowledgement is attempted only after CRM capture and internal routing have succeeded. A temporary acknowledgement failure is logged but does not discard or misrepresent the already-captured enquiry.
