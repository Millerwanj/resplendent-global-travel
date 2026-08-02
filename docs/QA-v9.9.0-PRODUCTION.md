# QA — v9.9.0 International Edition

Date: 1 August 2026

## Release verification

- Six-language page cluster verified: English, Chinese, Japanese, German,
  French and Italian.
- Twenty public HTML files passed structural validation, with 1,234 automated
  production checks covering metadata, structured data, files and routes.
- All internal public links, page anchors, assets and clean-route targets were
  resolved locally.
- Forty-two reciprocal page-level hreflang declarations were verified, plus the
  corresponding sitemap alternates and `x-default` references.
- Each international page has a unique localized title, description, keywords,
  canonical URL, Open Graph set, Twitter set, image alternative text and valid
  JSON-LD.
- The Chinese page contains `国际商务合作`, does not contain the superseded
  `国际商务精准对接`, and uses the approved wider hero content area.
- The finalized Chinese page is byte-for-byte unchanged from the approved v9.9
  baseline.
- The German service name is consistently rendered as
  `Geschäfts- und Führungskräftereisen`; the former English wording is absent
  from visible copy, metadata, social descriptions, JSON-LD and footer content.
- The Japanese, German, French and Italian interfaces were checked for the
  defined set of unintended English UI phrases, with no matches remaining.
- The exact language selector labels, links and active states were verified on
  all six language entry points.
- Desktop, tablet and mobile layout constraints were verified, including hero
  text wrapping, selector fit, responsive grids, mobile navigation hooks,
  keyboard focus behavior and reduced-motion support.
- Nine JavaScript and build-validation files passed syntax checks.
- Git whitespace validation passed.
- All 23 PHP files and the private administrator/operations implementation are
  byte-for-byte unchanged from the production baseline.
- Google Analytics measurement ID `G-5WEEFVG6MB` is present on every
  international landing page.

## Packaging gate

The release package must pass ZIP integrity testing and a manifest checksum
comparison before delivery.
