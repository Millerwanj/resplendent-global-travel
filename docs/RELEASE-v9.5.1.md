# v9.5.1 — Anti-Spam Reliability Patch

## Purpose
Prevent legitimate Chrome/password-manager submissions from being discarded before Zoho CRM and SMTP.

## Expected pipeline
`received → antispam/ok → validation → zoho → smtp_config → smtp → complete`

A blocked submission records `antispam/blocked` with the trap field, value length and a short private-log excerpt.
