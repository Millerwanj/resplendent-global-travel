# v10.1.0 Candidate QA

Validated 4 August 2026 against the v10.0.0 production baseline.

## Passed

- All 24 public HTML files passed structural validation using the established
  XHTML-style void and boolean-attribute conventions.
- All 25 JSON-LD blocks parse successfully.
- 855 `href`, `src` and form-action references were inspected; every local
  target resolves to a file included in the build.
- No iframe, YouTube embed or video asset remains in the Rwanda–Uganda feature,
  journey or Journal entry.
- All links that open a new tab include a safe `rel` value.
- No duplicate HTML IDs were found.
- `sitemap.xml` parses successfully and contains 21 public routes, including
  the two Rwanda–Uganda additions.
- All 24 HTML files, public asset cache markers, the enquiry handler, private
  workflow store and site configuration carry release `10.1.0`.
- JavaScript syntax checks pass for the public and private admin scripts.
- The five modified HTML pages and Signature Journey stylesheet pass Prettier
  formatting checks; the complete worktree passes whitespace/error checks.
- The eight Virungas image positions use different local WebP files. Their
  formats, dimensions and file integrity were verified: 1920×1080, 1800×1200,
  1440×1080, 1920×1080, 1600×1000, 1400×900, 1600×900 and 1600×900.
- Seven source photographs serve the eight positions. The adult-and-infant
  gorilla photograph intentionally links the collection card and journey hero
  through different 4:3 and 16:9 crops; every other source is unique.
- Every photograph is recorded against its Unsplash source, photographer,
  licence and crop in `docs/IMAGE-CREDITS.md`; the licence does not require a
  visible public caption.
- The release manifest contains 210 payload files and every recorded byte size
  and SHA-256 hash matches its source file.
- The deployment archive contains the manifest plus every recorded payload
  file, passes ZIP integrity testing, includes all eight Virungas images and
  excludes Git and temporary QA paths.
- The international landing pages remain content-identical apart from the
  release/cache marker update.
- The enquiry links use the existing verified prefill fields: `Leisure Travel`,
  journey name and `Rwanda and Uganda` destination.
- All eight locally stored image derivatives came from verified Unsplash photo pages;
  no supplier image, public-site image, film or video file is included.

## Review hold

- Full-page desktop and mobile screenshots could not be generated: no local
  browser binary is installed, and the cloud browser cannot access the
  workspace-only preview server. Complete the planned visual review in VS Code
  before supplier submission or deployment.
- PHP CLI is not installed in the current container, so the unchanged PHP
  application files could not be re-linted during this media-only correction.
- No live CRM submission, partner booking request, external email, Git push or
  Verpex deployment was performed.
- The completed feature still requires written Volcanoes Safaris approval
  before publication, as committed in Resplendent's supplier correspondence.
