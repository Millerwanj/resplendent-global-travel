# Verpex Deployment — v10.7.0

1. Back up the current `public_html` directory and the private mail/admin
   configuration held outside it.
2. Upload the v10.7 ZIP to the hosting file manager.
3. Extract directly into `public_html`, allowing the release files to replace
   their earlier versions.
4. Do not delete or move the private files outside `public_html`:
   `rgts-mail-config.php`, `rgts-admin-config.php`, `rgts-operations-data` and
   `rgts-logs`.
5. Confirm the homepage, the five new SEO routes, all three Signature Journey
   routes, Contact form and `/admin/` login.
6. Confirm `sitemap.xml` and `robots.txt`, then submit the sitemap once in Search
   Console and request indexing for the new or meaningfully updated routes.

The release contains no mailbox password, administrator password or client
records.
