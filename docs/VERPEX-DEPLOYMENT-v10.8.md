# Verpex Deployment — v10.8.1

1. Back up the current `public_html` directory and the private mail, admin and
   operations data held outside it.
2. Upload the v10.8 ZIP to Verpex File Manager.
3. Extract it directly into `public_html`, allowing release files to replace
   earlier public files.
4. Do not delete or move `rgts-mail-config.php`, `rgts-admin-config.php`,
   `rgts-operations-data` or `rgts-logs` outside `public_html`.
5. Open `/admin/settings.php`, confirm **RTGS bank transfer** is the preferred
   method, and verify the bank details and client-facing payment note.
6. Keep online checkout and contactless status set to **Not enabled** until the
   selected provider is approved and production-verified.
7. Confirm the three detailed Signature Journey pages show the correct single
   Journey Investment block after their itineraries. Confirm the homepage,
   hero areas and Signature Journeys overview remain price-free.
8. Confirm `/terms`, `/privacy`, `/payments`, Contact, the enquiry confirmation
   flow and `/admin/` login.
9. Create one private test quotation using known supplier costs. Confirm the
   generator blocks a total below the internal floor and generates a quotation
   at or above it without exposing supplier costs.
10. Confirm `sitemap.xml` and `robots.txt`, submit the sitemap once in Search
    Console and inspect the three updated Signature Journey URLs.

The release contains no mailbox password, administrator password, payment
provider credential or client record. `rgts-payment-config.php` is not required
while RTGS is the only active method.
