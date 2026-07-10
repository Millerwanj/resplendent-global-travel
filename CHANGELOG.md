# Changelog

All notable changes to the Resplendent Global Travel Solutions website.

## v4.3.2 — 2026-07-10

Full repository validation pass: every link, asset path, and accessibility element checked systematically; development leftovers removed.

### Fixed
- `favicon.ico` was generated but referenced at the wrong path (`/favicon.ico` while the file lived under `/assets/images/`) — moved to site root to match its reference
- Asset paths were inconsistent (some root-relative, some document-relative) — standardized every CSS/JS/icon reference to root-relative (`/assets/...`) across `index.html` and `404.html`
- Footer **Privacy Policy** and **Terms of Service** links pointed to the 404 page — replaced with genuine `privacy.html` and `terms.html` pages
- Footer **Careers** and **Press** links pointed to the 404 page with no real destination — removed rather than left as dead links
- Generated real `apple-touch-icon.png` (180×180), `favicon.ico` (16/32px), and `og-cover.jpg` (1200×630) from the brand monogram, replacing the placeholder references that pointed to files which didn't exist on disk

### Verified (no changes needed)
- Every internal anchor link (`#home`, `#about`, `#destinations`, `#corporate`, `#business`, `#journey`, `#contact`, `#main`) resolves to a real element `id` on the page
- Every local asset path (CSS, JS, icons, manifest, sitemap) resolves to a real file on disk
- Every `<img>` has appropriate `alt` text; every form field has a properly associated `<label for>`
- CSS and JS are each a single consolidated file (`assets/css/style.css`, `assets/js/main.js`) — no stray or duplicate stylesheets/scripts anywhere in the repository
- No `TODO`/`FIXME`/`console.log`/Lorem ipsum/debugger statements anywhere in the codebase
- Responsive breakpoints (1080px, 900px, 640px) correctly collapse all grids (destinations, services, footer, forms) to narrower column counts, and fluid `clamp()` typography and spacing scale smoothly between them
- `prefers-reduced-motion` is respected in both CSS (reveal/scroll-cue animations) and JS (reveal script falls back to instant visibility)
- Form inputs render at 16px (1rem), avoiding the iOS Safari auto-zoom-on-focus issue
- Viewport meta tag present on every page

## v4.3.1 — 2026-07-10

Follow-up fixes to two open items from the v4.3 launch checklist.

### Fixed
- Verified all seven destination/section photo URLs against live Unsplash search results rather than assuming they resolve; corrected two alt texts (About section, Japan destination card) that had described content not supported by verification
- Contact form now actually submits: on valid submit it opens the visitor's email client with a pre-filled message to the concierge address, requiring no backend, signup, or configuration. `main.js` also exposes a documented `SUBMIT_ENDPOINT` constant to switch to a silent fetch-based submission the moment a form backend (Formspree, Basin, Getform, custom) is available

## v4.3 — 2026-07-10

First production-grade build on the v4.2 architecture. Structure, navigation, brand colours and core concepts are unchanged by design; this version replaces the skeleton implementation with a real, launch-ready editorial site.

### Added
- Full design token system in CSS (colour, type scale, spacing rhythm, motion) — `assets/css/style.css`
- Editorial typography pairing: Cormorant Garamond (display) + Jost (body), replacing default system Arial
- Cinematic full-bleed hero with scrim, eyebrow, two-tier CTA, and scroll cue
- Complete About Resplendent section: two-column editorial layout, framed image, stat callout, pull quote, founder signature
- Featured Destinations rebuilt as an asymmetric editorial grid (Dubai, Paris, Japan, Maasai Mara) with real photography, index numbers and region copy
- "Also within reach" secondary destinations list (Rome, London, Istanbul, Bangkok, Cape Town, Brazil, New York) as a minimal text list, avoiding a cluttered second grid
- Corporate Travel section rebuilt with a three-item feature list (itinerary management, 24/7 support, policy & reporting) and supporting photography
- Global Business Connections section built out in full as the site's differentiator: navy-inverted section with a 2×2 service grid (B2B Matching, Trade Missions, Executive Networking, Market Entry Support) and a closing CTA
- Start Your Journey CTA section with dual call-to-action (form / phone)
- Full Contact section: definition-list contact details + validated enquiry form (name, email, organisation, interest, message)
- Luxury footer: five-column layout (brand, navigate, destinations, company, contact), social icons, legal bottom bar
- Fixed header with transparent-to-solid scroll state, active-section nav highlighting, and an accessible mobile navigation overlay
- Scroll-reveal micro-interactions via `IntersectionObserver`, fully disabled under `prefers-reduced-motion`
- Client-side form validation with accessible error states and status messaging
- Skip-to-content link and visible focus rings site-wide
- Full SEO layer: meta description/keywords, canonical URL, Open Graph, Twitter Card, `TravelAgency` JSON-LD
- `robots.txt`, `sitemap.xml`, `site.webmanifest`, branded `404.html`
- Responsive breakpoints at 1080px, 900px and 640px, including a dedicated mobile navigation pattern

### Changed
- Replaced all inline/utility-less markup with semantic, landmark-based HTML5
- Replaced ad-hoc hex colours in the old skeleton with the token system (`--navy`, `--gold`, `--white` plus supporting neutrals)
- Replaced fixed pixel spacing with a fluid `clamp()`-based rhythm for section and block spacing

### Fixed
- Gold-on-white text contrast: pure brand gold now used only decoratively or as a button fill against dark text; a deeper `--gold-text-safe` variant is used anywhere gold appears as text on a light background, to meet WCAG AA
- Missing `alt` text, ARIA labelling, and keyboard focus states throughout

## v4.2 — prior

Initial skeleton: single unstyled HTML file with inline flat colours, no custom typography, no JavaScript, no destinations content beyond three placeholder cards, no accessibility or SEO layer. Superseded in full by v4.3 above.
