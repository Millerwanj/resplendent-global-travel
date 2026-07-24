# v9.4.0 — CRM Integration Edition

## Purpose
Transform the existing contact journey into a CRM-connected client-intake workflow without embedding Zoho's generic form design.

## Included
- Existing Resplendent contact form submits validated enquiries to the Zoho CRM Leads module.
- Full Name is safely mapped to Zoho First Name and required Last Name fields.
- Service, company, journey details, message and RGTS reference are consolidated into the Zoho Description field.
- Zoho's configured default acknowledgement template is triggered for visitors.
- Lead-owner notification remains controlled by the Zoho webform configuration.
- Existing departmental SMTP routing is preserved as a secondary delivery channel when configured.
- If SMTP is temporarily unavailable after Zoho accepts the lead, the client enquiry is not lost.
- Existing Google Analytics foundation and contact success-state tracking remain intact.
- No visible redesign, URL change, metadata change or indexing request is required.

## Deployment
Upload and extract the complete archive over the current site, preserving the external `rgts-mail-config.php` file if SMTP routing is already configured.

## Required production test
Submit one real test enquiry using an email address you can access. Confirm:
1. the success reference appears on the Contact page;
2. a Lead appears in Zoho CRM;
3. the visitor receives the acknowledgement email;
4. the lead owner receives the Zoho notification;
5. departmental email delivery is received when SMTP is configured.
