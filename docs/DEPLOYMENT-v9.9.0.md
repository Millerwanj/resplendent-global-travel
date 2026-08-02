# Deployment v9.9.0 — International Edition

## Verpex production deployment

1. Keep the current `public_html` backup and the existing private files outside
   `public_html`.
2. Upload `Resplendent-Global-Travel-v9.9-International-Edition.zip` to
   `public_html`.
3. Extract the complete package into `public_html`, allowing the v9.9 files to
   replace matching public files.
4. Do not replace or move `/home/resplend/rgts-mail-config.php`,
   `/home/resplend/rgts-admin-config.php`, `/home/resplend/rgts-operations-data/`
   or `/home/resplend/rgts-logs/`.
5. Confirm the clean URLs `/`, `/zh`, `/ja`, `/de`, `/fr` and `/it`.
6. Open the language selector on desktop and mobile and confirm every language
   target.
7. Confirm `/contact`, submit no test enquiry unless a CRM test is intentionally
   required, and verify that `/admin/` still opens the private sign-in.
8. Open `/sitemap.xml` and resubmit it in Google Search Console after the release
   is live.

The package contains no mailbox password, administrator password or client
record. Existing secure configuration and operational data remain outside the
web root.

