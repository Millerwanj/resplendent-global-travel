# v10.9.0 Candidate QA

Prepared 11 August 2026.

## Passed

- Changed HTML pages parse without structural parser errors.
- All changed-page JSON-LD blocks parse as valid JSON.
- Local links and image/script/stylesheet references resolve.
- `sitemap.xml` parses as valid XML and includes all new routes.
- Serengeti visible starting price and structured Offer both use USD 6,800.
- Signature Journeys collection count is four.
- Serengeti hero and collection images are valid 1366 x 768 WebP assets.
- Approved public layout, navigation, typography and colour system remain
  unchanged.
- Confidential supplier rate files are excluded from the release package.

## Environment-limited checks

- PHP CLI is not installed in the build workspace, so PHP lint was not rerun.
  The PHP application files were not changed in v10.9.0.
- A Playwright browser executable is not installed in the build workspace, so
  automated screenshots were not captured. New imagery was inspected directly,
  and the new pages reuse the established production layouts.

## Post-deployment smoke test

Open `/signature-journeys`, `/signature-journey-serengeti`, `/corporate`,
`/executive-travel-services-nairobi` and
`/exhibition-delegate-travel-nairobi` on desktop and mobile. Confirm clean
routes, imagery, enquiry links and the USD 6,800 price before announcing the
release.

