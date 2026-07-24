# Changelog

## v9.5.2 — Production Stable (2026-07-24)

- Marked the verified CRM and departmental email workflow as the Phase 1 production baseline.
- Added an automatic customer acknowledgement email after Zoho capture and internal departmental delivery succeed.
- Customer acknowledgement confirms the reference number and states that the request is being reviewed.
- Added `customer_ack` pipeline logging without exposing SMTP credentials or CRM tokens.
- Preserved successful enquiry handling if customer acknowledgement delivery is temporarily unavailable.
- Retained the v9.5.1 anti-spam reliability fix.
- Standardized public asset cache versions and visible footer versions to v9.5.2.

## v9.4.0 — CRM Integration Edition (24 July 2026)

- Connected the existing premium Contact form directly to the Zoho CRM Leads module.
- Preserved the approved Resplendent form design instead of embedding Zoho's generic form.
- Mapped the single Full Name field safely into Zoho First Name and required Last Name fields.
- Consolidated service, company, journey details, message and enquiry reference into each CRM lead.
- Enabled the configured Zoho visitor acknowledgement and lead-owner notification workflow.
- Preserved departmental SMTP routing as an optional secondary delivery channel.
- Added a GA4 `generate_lead` event after confirmed successful submissions.
- Updated active version labels and cache parameters to v9.4.0 without changing URLs, editorial content or SEO metadata.

## v9.3.0 — Analytics Foundation Edition (23 July 2026)

- Installed the official Google Analytics 4 Google tag on every HTML page.
- Configured Measurement ID `G-5WEEFVG6MB` consistently across the website.
- Ensured the tag loads asynchronously and appears only once per page immediately after the opening `<head>` element.
- Updated production asset version parameters to v9.3.0 for reliable cache refresh.
- Rechecked canonical URLs, metadata, sitemap references, internal links and core production files.
- Preserved the approved v9.2 design, content and functionality without editorial changes.

## v9.2.0 — Technical SEO Edition (23 July 2026)

- Shortened search-result titles to remove the Bing title-length warning.
- Added descriptive alternative text to the brand logo across all pages.
- Added complete Open Graph image metadata and image alternative text.
- Standardised canonical URLs on HTTPS, the `www` host and clean URL paths.
- Standardised Twitter/X card metadata across all public pages.
- Normalised Schema.org URLs and removed duplicate minimal TravelAgency markup.
- Updated the XML sitemap to canonical clean URLs and removed the noindex payments page.
- Updated production asset version parameters to v9.2.0.
- Preserved the approved v9.1 visual design and functionality.

## v9.1.0 — Resplendent Journal
- Added a new Journal section to the production website.
- Published original guides for Japan, Kenya and Dubai.
- Added editorial Article schema and CollectionPage schema.
- Added site-wide Journal navigation and updated the sitemap.
- Added responsive editorial layouts and accessible image metadata.

## v8.1.0 — 22 July 2026
- Preserved separate warm hero images for About Us and Contact.
- Slightly enlarged the six homepage navigation labels.
- Fixed remaining static-host contact links with query parameters.
- Updated metadata, cache-busting and release documentation.

## v7.2 — Business Operations Edition (20 July 2026)
- Added secure server-side enquiry handling through PHP.
- Added automatic department routing for bookings, corporate, business, support, accounts and general enquiries.
- Made Phone / WhatsApp mandatory with browser and server validation.
- Added enquiry references, spam honeypot protection and tailored success/error feedback.
- Updated service-page calls to action to preselect the correct department.
- Preserved the approved v7.1 design, branding and editorial presentation.

## v7.1 — Operational Intelligence

- Added structured service classification across leisure, corporate, business connections and supporting travel services.
- Added context-sensitive enquiry fields without changing the approved visual identity or page copy.
- Added enquiry reference generation and submission timestamps.
- Prepared quotation-ready email summaries with clearly grouped client and request details.
- Added multi-service selection, improved validation, keyboard accessibility and mobile form behaviour.
- Preserved the approved homepage, navigation, photography, typography and editorial content.

## v7.0 — Engineering & Production Refinement (16 July 2026)

### Improved
- Centralised the enquiry recipient through `site-config.js` and removed the obsolete Gmail fallback from the form workflow.
- Added keyboard focus containment and improved focus restoration for the mobile navigation.
- Added intrinsic image dimensions, lazy loading and asynchronous image decoding to reduce layout shift and improve loading performance.
- Expanded social metadata, crawl directives and homepage TravelAgency structured data.
- Added long-lived immutable caching for versioned static assets and revalidation for HTML documents.
- Improved form status announcements, input sizing, textarea resizing and cross-browser resilience.
- Versioned production CSS and JavaScript references for reliable cache refresh.

### Preserved
- All approved visible editorial copy.
- Santorini homepage hero and all approved imagery.
- Navigation, layout, typography, colours and minimalist quiet-luxury direction.

## v6.0
- Final refined production build.

## v7.3 — Enterprise SMTP Edition

- Replaced PHP `mail()` with authenticated SMTP over SSL/TLS port 465.
- Added reliable departmental delivery with a central copy to `info@`.
- Added an external secret configuration workflow outside `public_html`.
- Success is now shown only after the SMTP server accepts the message.
- Added server-side error logging without exposing credentials to visitors.

## v7.4.0 — 2026-07-21
- Site-wide SEO metadata and structured data refinement.
- High-quality WebP image delivery with original JPEGs retained.
- Apache performance, HTTPS and security configuration.
- Updated sitemap, robots directives and release documentation.


## v9.5.0 — CRM Pipeline Edition (24 July 2026)

- Rebuilt the enquiry handler as a reference-traceable pipeline.
- Added structured stage logs for received, validation, Zoho, SMTP configuration, SMTP and completion.
- Added Zoho HTTP status, redirect location and safe response-excerpt diagnostics.
- Removed silent SMTP failure: the Contact page now reports a partial result when CRM succeeds but email fails.
- Added SSL, STARTTLS and unencrypted SMTP transport support through configuration.
- Added stage-specific SMTP errors without exposing credentials.
- Added protected fallback logging if the preferred private log directory cannot be created.
- Preserved the approved v9.4 design, analytics and SEO implementation.
- Rebuilt the release from clean source without `.git`, credentials, executables or generated cache files.


## v9.5.1 — Anti-Spam Reliability Patch (24 July 2026)

- Replaced the semantic `website` honeypot name with `rgts_fax_number` to prevent browser autofill.
- Added client-side clearing of the honeypot at page initialization and immediately before submission.
- Added an explicit `antispam/ok` pipeline event for genuine submissions.
- Added safe diagnostic details when the honeypot blocks a submission.
- Updated Contact page cache-busting and visible release number to v9.5.1.
- Preserved Zoho tokens, SMTP routing, analytics, SEO, design and all approved content.
