# SEO Report — v10.8.1 Virungas Pricing Revision

The v10.8.1 candidate preserves the approved design and operational pipeline,
retains the v10.7 high-intent search architecture and adds accurate starting
Offer data to the three detailed Signature Journey entities.

## High-intent pages

| Search intent | Canonical route | Primary entity |
| --- | --- | --- |
| Luxury Kenya safaris | `/luxury-kenya-safaris` | Service |
| Rwanda and Uganda gorilla safaris | `/rwanda-uganda-gorilla-safari` | Service |
| Private Namibia travel | `/luxury-namibia-journey` | Service |
| Private Morocco travel | `/luxury-morocco-journey` | Service |
| Corporate travel management in Kenya | `/corporate-travel-management-kenya` | Service |
| Across the Virungas itinerary | `/signature-journey-uganda-rwanda-gorillas` | TouristTrip |

Every page has a self-referencing canonical URL, unique title and description,
Open Graph and Twitter metadata, a descriptive hero image alternative, WebPage
data, a service or journey entity and breadcrumb structured data.

## Discovery and internal structure

- The destination hub links to the Kenya, Rwanda–Uganda, Namibia and Morocco
  planning pages using descriptive anchors.
- The Journal, destination, Signature Journey and enquiry paths cross-reference
  one another contextually.
- The corporate hub links to the Kenya corporate-travel page.
- Public internal links use clean canonical paths instead of passing through
  `.html` redirects.
- The sitemap lists all five new landing pages and all three dedicated Signature
  Journey pages with accurate 2026-08-10 modification dates.
- The superseded `/journal-uganda-rwanda-gorillas` route redirects to the current
  `/journal-virungas` canonical.

## Structured data completion

- `Article` data is now present on the Watamu and Virungas Journal pages.
- `TouristTrip`, WebPage and breadcrumb data are now present on the dedicated
  Watamu journey page.
- Each detailed Signature Journey `TouristTrip` now has a matching USD Offer
  aligned with the restrained starting price visible on that page.
- The Signature Journeys collection exposes an ItemList covering the Mara,
  Watamu and Virungas journeys.
- The homepage featured-journey entity now matches Across the Virungas.

## International cluster

The English, Simplified Chinese, Japanese, German, French and Italian homepage
cluster retains self-referencing canonicals, reciprocal `hreflang` annotations
and `x-default`. The international visual design and localized copy are
unchanged.

## Post-deployment indexing

Submit the refreshed sitemap once, then inspect the three updated Signature
Journey detail pages. Request indexing only if Search Console has not already
recrawled them after deployment. Repeated homepage submissions are not
required.
