# v10.0.0 Production QA

Validated 2 August 2026 against the v9.9.0 International Edition baseline.

## Passed

- All 22 HTML pages passed structural validation using rules compatible with
  the baseline's established XHTML-style void elements and boolean attributes.
- All local `href`, `src` and form-action targets resolve to files included in
  the production package.
- All 22 JSON-LD blocks parse successfully.
- `assets/js/app.js` and `assets/js/site-config.js` pass JavaScript syntax
  checks.
- All five public stylesheets pass CSS syntax validation.
- The contact-page journey test confirms that `Tailor This Journey` prefills:
  `Leisure Travel`, the flagship journey name, `Masai Mara` and the opening
  enquiry message.
- `sitemap.xml` parses successfully and contains 19 public URLs, including both
  Signature Journey routes.
- Chinese, Japanese, German, French and Italian pages retain their v9.9 source
  exactly apart from the v10 release marker. Each retains the expected
  HTML language and seven-link reciprocal hreflang cluster.
- All three interim Signature Journey images were visually inspected,
  stored locally as optimized WebP assets and kept distinct across their uses.

## Deployment notes

- The production package is approved for upload to Verpex.
- No live CRM or email submission was performed during static release QA; the
  established v9.9 server-side enquiry pipeline is preserved unchanged.
- Signature Journey imagery is interim launch media and can be replaced in a
  later content release when approved partner media-kit assets arrive.
