# Verpex Deployment - v10.9.0

1. Back up the current `public_html` files and private operational data.
2. Upload the v10.9 ZIP to `public_html` and extract it there.
3. Confirm `.htaccess` remains present after extraction.
4. Do not replace private files under `/home/resplend/`, including mail,
   payment, administrator or operations-data files.
5. Clear any hosting cache, then run the smoke tests in
   `docs/QA-v10.9.0-CANDIDATE.md`.
6. Submit the updated sitemap in Google Search Console after the public pages
   are verified.

The package contains no supplier rate sheets, live credentials, client records
or private operational data.

