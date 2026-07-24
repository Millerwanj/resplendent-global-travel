# v9.5.0 — CRM Pipeline Edition

## Purpose

Make every website enquiry traceable from the browser through Zoho CRM and authenticated departmental email.

## Result states

- `success`: validation, Zoho and SMTP completed.
- `partial`: Zoho accepted the submission, but SMTP configuration or delivery failed.
- `error`: validation or Zoho delivery failed.

Every state includes the enquiry reference. The server log records the exact stage and safe diagnostic details.

## Log location

Preferred: `/home/resplend/rgts-logs/pipeline-YYYY-MM-DD.log`

Each line is JSON. Search by the reference displayed on the Contact page.

## Deployment

Upload the contents of this release into `public_html` and overwrite the existing v9.4 files. Do not delete `/home/resplend/rgts-mail-config.php`.
