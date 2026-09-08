# Resplendent Global Travel Solutions v10.8.2

## International Hero Correction

Deployment candidate prepared: 11 August 2026  
Deployment target: Verpex `public_html`

### Public scope

- Restores the approved premium East African pavilion hero to `/zh`, `/ja`,
  `/de`, `/fr` and `/it`.
- Preserves the deliberate left-side negative space for translated headlines.
- Aligns the visible hero with the image already used by preload, Open Graph,
  Twitter and structured-data metadata.
- Retains the Across the Virungas price of USD 14,100 per person sharing and all
  other v10.8.1 changes.

### Cause corrected

The international metadata referenced `international-premium`, while the shared
international stylesheet still referenced `business-handshake`. The stylesheet
now uses the approved `international-premium` WebP with JPEG fallback.
