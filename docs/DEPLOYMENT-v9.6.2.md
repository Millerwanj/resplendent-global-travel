# Deployment — v9.6.2 Production

1. Back up the current `public_html` files and the private
   `/home/resplend/rgts-operations-data/` directory.
2. Upload `Resplendent-Global-Travel-v9.6.2-Production.zip` to `public_html`.
3. Extract with overwrite enabled. Do not delete the private mail, admin or
   operations files outside `public_html`.
4. Confirm `/home/resplend/rgts-mail-config.php` still contains the existing
   SMTP values. `public_base_url` is optional and defaults to the production
   domain.
5. Open `/admin/`, confirm the existing login still works, and open a generated
   proposal.
6. Download its PDF and confirm the approved logo, figures and important terms.
7. Send one controlled proposal to an email address you can access:
   - confirm the PDF is attached;
   - open the secure acceptance link;
   - accept with the receiving email;
   - confirm the document status and Client Workflow move to **Approval**;
   - confirm the internal acceptance notification arrives.
8. Submit one live Contact enquiry and confirm:
   - the page remains on Contact;
   - the button reads **Submitted**;
   - the enquiry reference appears below it;
   - Zoho, departmental email, central copy and customer acknowledgement work.
9. Verify no public `/success` page remains.

The release contains no mailbox password, administrator password, client
record, generated document or acceptance token.
