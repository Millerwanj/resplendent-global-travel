# QA — v9.7.0 Production

Date: 29 July 2026

## Release scope

- Added one matching Tripadvisor companion section to each of the Maasai Mara,
  Dubai and Japan Journal articles.
- Preserved the existing Resplendent enquiry call-to-action beneath each new
  section.
- Refreshed active release identifiers, Article schema modification dates and
  relevant sitemap dates.
- Preserved the complete operational pipeline without functional changes.

## Automated checks

- Validated all 15 public HTML pages.
- Resolved 255 local links and anchors without missing targets.
- Parsed all Article and page JSON-LD successfully.
- Confirmed exactly one correctly mapped Tripadvisor link on each destination
  article.
- Confirmed every external Trip link has an explicit accessible label, opens
  in a new tab and uses `rel="noopener"`.
- Confirmed all three public Tripadvisor Trip URLs return HTTP 200.
- Parsed `sitemap.xml` successfully and confirmed 29 July 2026 modification
  dates for the three updated articles.
- Parsed `data/site.json` successfully.
- Validated all seven JavaScript files with Node syntax checks.
- Confirmed the main stylesheet has balanced braces and scoped responsive
  companion styles.
- Confirmed 21 operational PHP, configuration and admin files differ from the
  v9.6.4 baseline only by the release identifier.
- `git diff --check` passes.

## Environment note

The local workspace does not include a PHP runtime, so PHP syntax was not
re-executed here. The affected PHP and admin files were already production
verified in v9.6.4 and their functional content remains identical; only the
release identifier changed.

## Post-deployment smoke test

1. Open `/journal-kenya`, `/journal-dubai` and `/journal-japan`.
2. Confirm the **Curated on Tripadvisor** section is balanced on desktop and
   mobile.
3. Confirm each external link opens its matching public Tripadvisor Trip.
4. Confirm each existing **Plan Your Journey** button still opens the leisure
   enquiry form.
5. Submit one controlled website enquiry and confirm Zoho, internal mail,
   acknowledgement and Client Workflow capture.
6. Confirm the protected admin suite, document acceptance and invoice creation
   remain available.
