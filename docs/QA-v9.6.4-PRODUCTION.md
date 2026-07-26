# QA - v9.6.4 Production

Date: 26 July 2026

## Release scope

- Added full-length Japan, Dubai and Maasai Mara balloon journal articles.
- Connected the corresponding destination cards to their journal entries.
- Updated journal summaries, article metadata, structured data and sitemap dates.
- Preserved the v9.6.3 proposal, quotation, acceptance and invoicing pipeline.

## Automated checks

- All local links across the 14 HTML pages resolve to existing files or valid anchors.
- Journal index, destinations page and all three articles contain titles and meta descriptions.
- Article JSON-LD parses successfully.
- Each destination article contains at least 500 words and seven editorial sections.
- No horizontal-layout changes or new CSS were introduced.
- `git diff --check` passes.

## Environment note

The cloud browser cannot access the workspace's local preview server. Visual risk is
limited because v9.6.4 reuses the existing v9.6.3 article and card components without
CSS changes. A final live check should confirm the journal index, each article and the
three destination links after deployment.

## Post-deployment smoke test

1. Open `/journal.html` and confirm the three cards render.
2. Open the Japan, Dubai and Maasai Mara articles from the journal.
3. Open `/destinations.html` and confirm those three destinations link to the articles.
4. Confirm each article CTA opens the leisure enquiry form.
5. Confirm the operations dashboard, document acceptance and invoice creation remain unchanged.
