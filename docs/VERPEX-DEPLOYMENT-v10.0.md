# Verpex Deployment — v10.0.0 Signature Journeys Edition

## Production upload

1. Create a current backup of `public_html` in Verpex before extracting the
   release.
2. Upload `Resplendent-Global-Travel-v10.0-Signature-Journeys-Production.zip`
   to `public_html`.
3. Extract the ZIP directly into `public_html`, allowing release files to
   replace matching public files. Confirm that `index.html` and `.htaccess`
   sit at the root of `public_html`, not inside an extra folder.
4. Keep these private production resources unchanged and outside `public_html`:
   - `/home/resplend/rgts-mail-config.php`
   - `/home/resplend/rgts-admin-config.php`
   - `/home/resplend/rgts-operations-data/`
   - `/home/resplend/rgts-logs/`
5. Remove the uploaded ZIP from `public_html` after a successful extraction.

## Post-deployment verification

- Open `/`, `/signature-journeys` and
  `/signature-journey-masai-mara` in a private browser window.
- Select **Tailor This Journey** and confirm that `/contact` preselects Leisure
  Travel and prefills the journey name and Masai Mara destination.
- Confirm the Kenya Journal and Mara destination links reach the flagship
  journey and return correctly.
- Confirm `/zh`, `/ja`, `/de`, `/fr` and `/it`, including the language selector
  on desktop and mobile.
- Confirm `/admin/` reaches the private sign-in without changing stored client
  or administrator data.
- Open `/sitemap.xml`; after verification, resubmit the sitemap in Google
  Search Console.
- Submit a live enquiry only when an intentional CRM and mail delivery test is
  authorised.

The deployment ZIP contains no mailbox password, administrator password or
client record. Existing secure configuration and operational data remain
outside the web root.
