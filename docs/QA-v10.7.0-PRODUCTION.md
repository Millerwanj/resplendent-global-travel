# Production QA — v10.7.0

## Required checks

- HTML structure, unique titles, descriptions, canonicals and one visible H1.
- JSON-LD parses on all five new pages, the three Signature Journey pages and
  the updated Journal pages.
- Every sitemap URL resolves to a production HTML source file.
- Every local image, stylesheet and script reference exists.
- Public internal links use clean canonical URLs.
- Legacy `.html` and non-`www` requests remain covered by Verpex redirects.
- Desktop, tablet and mobile layouts retain the approved Resplendent design.
- Contact form field names, handler, Zoho mapping and acknowledgement pipeline
  remain unchanged.
- Admin, proposal, quotation, invoice and PDF implementation files remain
  functionally unchanged.

## Final results — 10 August 2026

- 31 sitemap URLs checked; every route maps to a release source file.
- 35 HTML files checked; zero metadata, canonical, H1, JSON-LD, local-link or
  local-asset errors.
- HTMLHint checked all 35 HTML files; zero errors.
- `git diff --check` passed with zero whitespace errors.
- No public internal `.html` links remain.
- `styles.css`, `assets/css/main.css`, `assets/css/signature-journeys.css`,
  `assets/js/app.js` and `assets/js/site-config.js` are byte-for-byte identical
  to the approved live v10.6 production assets. This preserves the current
  typography, colour system, spacing, components and responsive behaviour.
- `admin/`, `includes/` and `form-handler.php` have no release diff.
- The only change in `accept.php` is its public Terms link moving from the
  legacy `.html` URL to the existing clean canonical route.
- PHP linting was unavailable in the packaging environment; PHP application
  logic was therefore protected through direct release-diff verification.

Status: **PASS — ready for Verpex deployment packaging.**
