# Resplendent Global Travel Solutions — v4.3

Production codebase for the Resplendent marketing website. Vanilla HTML5, CSS3 and JavaScript — no build step, no frameworks, no dependencies beyond two Google Fonts.

## What changed from v4.2

v4.2, as delivered, was a structural skeleton: unstyled system fonts, a single flat-colour hero, three bare placeholder cards for destinations, and no JavaScript, accessibility layer, SEO metadata, or responsive behaviour.

v4.3 is the first genuinely production-grade build on that structure. Nothing in the locked architecture changed — navigation labels and order, homepage section order, brand colours, the four core concepts (About, Featured Destinations, Corporate Travel, Global Business Connections), and the destination list are all exactly as specified. See `CHANGELOG.md` for the full list of what was added.

## File structure

```
index.html                     Homepage — full single-page structure
404.html                       Branded error page
privacy.html                   Privacy Policy
terms.html                     Terms of Service
favicon.ico                    Multi-size favicon (root, per convention)
robots.txt                     Crawl directives + sitemap reference
sitemap.xml                    XML sitemap (homepage + anchor sections)
site.webmanifest               Web app manifest (PWA metadata)
assets/
  css/style.css                Complete design system + responsive styles (single file)
  js/main.js                   Header state, nav, scroll reveal, form validation (single file)
  images/
    favicon.svg                Monogram favicon (navy + gold)
    apple-touch-icon.png        Generated 180×180 monogram icon
    og-cover.jpg                Generated 1200×630 social share image
```

## Design system

- **Colours:** Navy `#0A2342`, Gold `#C9A227`, White `#FFFFFF` as specified, plus a small set of supporting neutrals (Ivory, Stone, Charcoal, Slate) required for a real editorial layout — all defined as CSS custom properties in `style.css §1`.
- **Type:** Cormorant Garamond (display serif, headings and eyebrows-in-italic) paired with Jost (light geometric sans, body and UI). Loaded from Google Fonts with `preconnect` for performance.
- **Accessibility note on gold:** pure gold (`#C9A227`) on white fails WCAG AA for body text, so it is used only decoratively (hairlines, icons, large display accents) or as a background paired with dark navy text (`.btn--primary`, contrast ≈ 6.9:1). Any gold *text* on light backgrounds uses `--gold-text-safe` (`#8A6C16`), which passes AA.
- **Motion:** a single scroll-reveal pattern (fade + rise, `IntersectionObserver`-driven) and a header state change on scroll. Both fully respect `prefers-reduced-motion`. No carousels, counters, or parallax.

## Accessibility

- Semantic landmarks (`header`, `main`, `section[aria-labelledby]`, `footer`)
- Skip-to-content link
- Visible focus rings on every interactive element
- Full keyboard support for the mobile navigation (`Escape` closes it, focus is trapped visually within an opaque overlay)
- Form fields have associated `<label>`s, `aria-describedby` error text, and `role="alert"` / `aria-live="polite"` status messaging
- All decorative images and icons are `aria-hidden`; all content images have descriptive `alt` text

## SEO

- Descriptive `<title>` and meta description
- Open Graph and Twitter Card metadata
- `TravelAgency` JSON-LD structured data
- `robots.txt` + `sitemap.xml`
- Semantic heading hierarchy (single `h1` in the hero, `h2` per section, `h3` for cards/services)

## Before launch — please action

1. **Photography — verified.** Every image URL was checked via search-index cross-reference and confirmed to resolve as a live Unsplash asset; two alt texts were corrected to match verified image content (the About section image and the Japan destination card). These are still free Unsplash stock, not brand-exclusive photography — replace with commissioned or licensed images when budget allows, but nothing is broken or unverified as shipped.
2. **Contact form — solved, no backend required.** On submit, after validation, the form opens the visitor's default email client with a pre-filled message (name, email, organisation, enquiry type, message) addressed to `concierge@resplendentgts.com`. This works immediately with zero setup, zero signup, and zero server. If you'd prefer a silent in-page submission instead of an email-client popup, set `SUBMIT_ENDPOINT` near the top of `main.js` to a form backend URL (Formspree, Basin, Getform, or your own serverless function) — the code already has the fetch-based path wired and documented, it just needs that one value.
3. **Icons and social preview image — generated.** `favicon.ico`, `apple-touch-icon.png` and `og-cover.jpg` are real files built from the brand monogram (navy `#0A2342` + gold `#C9A227`). They're functional placeholders — swap them for professionally designed versions when your final brand mark is ready, but every reference resolves as shipped.
4. **Contact details** (email, phone, office address) are placeholder and must be replaced with real ones — update both the visible contact section and `CONCIERGE_EMAIL` in `main.js`.
5. **Social links** (`Instagram`, `LinkedIn` in the footer and structured data) point to handles that were assumed, not confirmed to exist — replace with your real profile URLs, or remove until they do.
6. **Domain.** All canonical/OG URLs assume `https://www.resplendentgts.com` — update if the real domain differs.

## Browser support

Modern evergreen browsers (Chrome, Firefox, Safari, Edge). Uses `IntersectionObserver`, CSS `clamp()`, and `aspect-ratio`, all with graceful fallback (reveal animations simply show immediately if `IntersectionObserver` is unavailable).
