# QA — v11.6.0

Date: 22 August 2026

## Automated checks

- 41 public HTML files scanned.
- 0 missing local page, image, video, stylesheet or script references.
- 0 invalid JSON-LD blocks.
- Front-end JavaScript syntax validated with Node.
- `vercel.json` parsed successfully.
- `git diff --check` passed with no whitespace errors.

## Architecture checks

- Primary navigation: Home, Destinations, Signature Journeys, Executive Mobility, eSIM, Journal and About.
- Contact remains available in the mobile menu and enquiry buttons.
- Global Business Connections remains accessible contextually and from new-page footers.
- `/corporate` and `/concierge`, with and without `.html`, permanently redirect to `/executive-mobility`.
- Sitemap contains `/executive-mobility` and `/esim` and excludes the retired service URLs.
- Chinese, Japanese, German, French and Italian pages each contain localized links to Signature Journeys, Executive Mobility, eSIM, the Journal and Global Business Connections.

## Functional checks

- eSIM enquiry aliases resolve to the existing server-side value.
- eSIM enquiries route to the Bookings mailbox rather than failing validation.
- Existing executive mobility, eSIM and business films remain click-to-play with `preload="none"`.
- Hero images declare dimensions and priority; below-fold images use lazy loading where suitable.
- The new international hero is delivered as a 97 KB WebP with a JPEG fallback and is preloaded on each localized landing page.

## Deployment note

PHP CLI was not available in the build workspace, so server-side syntax must also be confirmed by the Verpex PHP runtime during staging. The `form-handler.php` change is limited to existing routing and allow-list arrays plus acknowledgement branding.
