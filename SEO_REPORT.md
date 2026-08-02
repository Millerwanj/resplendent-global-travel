# SEO Report — v10.0.0 Signature Journeys Edition

The v9.9.0 international cluster remains intact. This release adds two
English-only editorial journey routes without changing the localized landing
pages or their reciprocal hreflang relationships.

## Language cluster

| Market | Route | HTML language | Open Graph locale |
| --- | --- | --- | --- |
| English | `/` | `en` | `en_GB` |
| Simplified Chinese | `/zh` | `zh-CN` | `zh_CN` |
| Japanese | `/ja` | `ja-JP` | `ja_JP` |
| German | `/de` | `de-DE` | `de_DE` |
| French | `/fr` | `fr-FR` | `fr_FR` |
| Italian | `/it` | `it-IT` | `it_IT` |

Each route has a self-referencing canonical URL and a reciprocal seven-link
alternate cluster covering all six languages plus `x-default`.

## Localized search and sharing data

- Unique localized title, description and keyword targeting on every market
  page.
- Localized Open Graph and Twitter titles, descriptions and image alternative
  text, with the correct URL and locale for each route.
- Localized TravelAgency, WebSite, WebPage and service-catalogue JSON-LD with
  the page language and canonical URL aligned.
- Descriptive localized logo and social-image alternative text.
- German business-travel terminology is fully translated across visible copy,
  metadata and structured data.

## Discovery

- `sitemap.xml` lists the complete six-language cluster with reciprocal XHTML
  alternates and `x-default`.
- `robots.txt` exposes the canonical sitemap and continues to protect private
  implementation routes.
- Google Analytics measurement ID `G-5WEEFVG6MB` remains active on every
  international landing page.

## Signature Journeys additions

- `/signature-journeys` has a self-referencing canonical URL, unique title and
  description, Open Graph and Twitter data, CollectionPage JSON-LD and
  breadcrumb structured data.
- `/signature-journey-masai-mara` has a self-referencing canonical URL, unique
  social metadata, TouristTrip and WebPage JSON-LD, a seven-item itinerary and
  breadcrumb structured data.
- Both routes appear in `sitemap.xml`; the Kenya Journal modification date is
  refreshed to reflect the new Journal-to-Journey connection.
- The homepage exposes the flagship TouristTrip entity while retaining its
  existing Organization, TravelAgency, WebSite and WebPage graph.
