# QA — v9.5.0 CRM Pipeline Edition

## Automated checks

- PHP syntax: passed for `form-handler.php` and `includes/SmtpMailer.php`.
- JavaScript syntax: passed for `assets/js/app.js`.
- Invalid POST flow: confirmed HTTP 303 redirect with `stage=validation` and an RGTS reference.
- Required project files: present.
- Internal HTML file references: checked.
- Release excludes `.git`, mailbox credentials, logs, cache files and executable binaries.
- Suspicious PHP execution primitives (`eval`, `gzinflate`, `shell_exec`, `passthru`) absent.

## Live verification after deployment

Submit one enquiry and use the returned reference to inspect `/home/resplend/rgts-logs/pipeline-YYYY-MM-DD.log`. The final events identify whether the submission reached Zoho, SMTP, or both.
