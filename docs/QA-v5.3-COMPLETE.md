# QA Report — v5.3 Complete

## Automated checks

- [x] Six primary pages plus 404 page present.
- [x] Shared CSS and JavaScript present.
- [x] Final locked homepage copy preserved.
- [x] Vertical gold Scroll cue preserved.
- [x] Internal hero copy preserved.
- [x] Content images include intrinsic dimensions.
- [x] Homepage hero image is preloaded.
- [x] Open Graph image URLs are absolute.
- [x] Breadcrumb structured data exists on internal pages.
- [x] Contact consent has accessible error messaging.
- [x] Mobile navigation supports Escape, outside click and resize closure.
- [x] Version markers updated to v5.3.

## Manual checks before commit

1. Preview all seven HTML pages with Live Server.
2. Test the mobile menu at 390px and 768px.
3. Submit the contact form with missing fields.
4. Confirm focus moves to the first invalid control.
5. Confirm the mail application opens after valid submission.
6. Confirm `404.html` renders correctly.
7. Run `git diff` after copying into the Git working folder.
