# QA — v9.4.0 CRM Integration Edition

## Static verification
- PHP syntax validated.
- Zoho Web-to-Lead endpoint and generated identifiers retained.
- Required Zoho Last Name mapping implemented.
- Website validation, honeypot, consent, phone and service allow-list preserved.
- CRM description includes enquiry reference, service, company, conditional details and message.
- SMTP remains optional secondary delivery after successful CRM creation.
- Existing contact design and public URL preserved.
- Active-version labels and cache query strings updated to v9.4.0.

## Production-only verification
A live submission is required because Zoho CRM and SMTP are external services. Follow the five-point test in the release notes immediately after deployment.
