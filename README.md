# Resplendent Global Travel Solutions

**Current production baseline: v9.5.2 — Production Stable**

Production website for `resplendentglobaltravel.com`.

## Current release

**v9.5.0 — CRM Pipeline Edition**

The Contact form now provides a traceable server-side pipeline:

1. validation and departmental routing;
2. Zoho CRM Web-to-Lead submission with HTTP/response diagnostics;
3. external SMTP configuration check;
4. authenticated SMTP delivery to the selected department and central copy;
5. structured reference-based logging.

The website design, content, Google Analytics ID `G-5WEEFVG6MB`, SEO metadata and public navigation remain unchanged.

## Required private mail configuration

Keep `/home/resplend/rgts-mail-config.php` outside `public_html`. Use `docs/rgts-mail-config.example.php` as the template. Never place the live mailbox password in this ZIP or GitHub.

## Pipeline logs

The handler writes logs to `/home/resplend/rgts-logs/pipeline-YYYY-MM-DD.log`. Search the file for the reference shown after form submission, for example `RGTS-20260724-AB12`.
