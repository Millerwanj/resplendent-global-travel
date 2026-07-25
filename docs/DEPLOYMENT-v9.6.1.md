# Deployment — v9.6.1 Production

1. Back up the current `public_html`.
2. Upload `Resplendent-Global-Travel-v9.6.1-Production.zip` to `public_html`.
3. Extract it and replace matching files.
4. Do not change `/home/resplend/rgts-mail-config.php`.
5. Sign in at `https://www.resplendentglobaltravel.com/admin/`.
6. Open **Payment Settings**. Leave the default instruction in place until the
   approved business account is ready.
7. Verify Proposal, Quotation and Invoice Generator live previews without
   generating test documents.
8. Submit one Begin Your Journey enquiry and verify the Enhanced Success Page,
   Zoho Lead, emails and Client Workflow entry.
9. Convert an approved quotation into the first genuine invoice when ready.

## Existing v9.6.0 admin account

The administrator configuration is stored outside `public_html`. Deploying
v9.6.1 preserves the existing admin email and password automatically.
