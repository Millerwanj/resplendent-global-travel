# Deployment — v9.6.0 Production

1. Back up the current `public_html`.
2. Upload `Resplendent-Global-Travel-v9.6.0-Production.zip` to `public_html`.
3. Extract it and replace matching files.
4. Do not change `/home/resplend/rgts-mail-config.php`.
5. Visit `https://www.resplendentglobaltravel.com/admin/`.
6. Use the one-time token in `ADMIN-SETUP.md` to create the administrator account.
7. Submit one live enquiry and confirm:
   - Zoho Lead;
   - departmental and central email;
   - customer acknowledgement;
   - Enhanced Success Page;
   - the client appears under Admin → Client Workflow.
8. Generate one test proposal and one test quotation, then use **Print / Save PDF**.

## Rollback

Restore the backed-up v9.5.2 files in `public_html`. Private admin and operations
data outside `public_html` will remain available and are not deleted by rollback.
