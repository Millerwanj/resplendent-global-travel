# Deployment - v9.6.3 Production

1. Back up `public_html` and `/home/resplend/rgts-operations-data/`.
2. Upload `Resplendent-Global-Travel-v9.6.3-Production.zip` to `public_html`.
3. Extract with overwrite enabled.
4. Do not delete:
   - `/home/resplend/rgts-mail-config.php`
   - `/home/resplend/rgts-admin-config.php`
   - `/home/resplend/rgts-operations-data/`
5. Confirm the existing `/admin/` login.
6. Open a travel proposal and confirm **Bookings** is selected under
   **Send from**.
7. Confirm Corporate, Business and Invoice documents select Corporate Travel,
   Global Business Connections and Accounts respectively.
8. Send one controlled proposal to an address you can access.
9. Choose **Request changes**, enter a short test note and confirm:
   - the document records **Changes requested**;
   - the client workflow shows **Revision Requested**;
   - no invoice is created.
10. Select **Create Revision**, confirm the new number ends in `R1`, send it
    and accept it.
11. Confirm a linked invoice is prepared automatically with status **Draft**.
12. Review and send the invoice, then confirm its status becomes **Issued**.
13. Open one proposal or quotation accepted before v9.6.3 and confirm the
    **Invoice not yet linked** notice appears.
14. Select **Create Linked Draft Invoice**, confirm the accepted document
    number appears on the draft and do not send it unless it is a real client
    invoice.
15. Recheck one Contact enquiry for Zoho, departmental routing, customer
    acknowledgement and the minimalist inline reference.

## Mailbox authorisation

The standard sender addresses work with the existing SMTP configuration when
Verpex authorises them as mailboxes or send-as aliases. If a mailbox requires
separate authentication, add `smtp_username` and `smtp_password` to that
profile in the private `rgts-mail-config.php`, following
`docs/rgts-mail-config.example.php`.

The release ZIP contains no live mailbox password, administrator password,
client record, generated document or response token.
