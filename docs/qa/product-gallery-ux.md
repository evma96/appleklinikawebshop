# Product gallery and image viewer acceptance

## Scope and baseline

LOCAL only, `http://localhost:8080`, based on develop commit
`de224e591630303d2bcddc5c14ec19433e53b940`, feature `feat/product-gallery-ux`.
No checkout, cart, order, Buyback, legal, account, pricing or product data changes.
No TEST SERVER access, deployment or uploaded media changes.

The Inventory plugin's `ProductFrontendDisplay` owns this gallery, through the
theme's single-product delegation. This is not Woo's native gallery/PhotoSwipe.
The previous layer combined inline CSS/JavaScript, server-rendered lightbox markup,
pixel-hit-testing hover zoom and dynamic selector image updates.

## Browser-first findings

- In the actual rendered page, WordPress paragraph formatting inserted wrapper
  nodes among lightbox grid children. The opened viewer could show no usable image,
  with its controls distributed incorrectly across the grid.
- The old overlay did not cover the full viewport (desktop left offset 136px,
  width 1168px at 1440px); the header remained visible around it. Mobile had a
  similar top gap and misplaced controls.
- Single-image products rendered unnecessary thumbnail and previous/next UI.
- The old `data-full` source was the bounded `woocommerce_single` image, not the
  full-size attachment. Lightbox thumbnails eagerly reused larger sources.
- Fixed portrait geometry, mouse-only detail positioning and missing explicit
  focus-return/trapping made the viewer fragile across image shapes and viewports.

Before captures were made before editing production code. Products #476 (square
Watch demo) and #288 (iPhone image with transparent padding) were inspected with
native browser clicks at 1440x1000 and 390x1000. No published LOCAL product had
multiple gallery attachments. For multi-image coverage, an unsaved WC_Product
was rendered by the actual PHP gallery method with existing attachments 521, 522
and 505. Only that gallery HTML was substituted in the test browser response.
No product, attachment or relationship was created or saved.

## Chosen implementation

- One focused gallery JS/CSS owner in the Inventory plugin. The old inline
  gallery/lightbox, hover pixel sampling and obsolete portrait profile are removed.
- The page reserves a square, maximum-500px contain stage, neutral background,
  responsive WP image sources and small lazy-loaded, uncropped medium thumbnails.
- A native `dialog.showModal()` top-layer viewer is created lazily outside formatted
  product content. No existing React/Woo fields are moved. No dependency, observer,
  polling loop or custom image editor was introduced.
- The viewer loads only the selected original, fits without distortion, supports
  2x/4x zoom and clamped pointer pan, explicit fit reset and pointer swipe at fit.
  Mobile uses reliable tap/zoom/pan rather than custom pinch recognition.
- Actual decoded image dimensions take priority over potentially stale WP metadata.
- Native modal semantics/inert background, labelled 44px controls, Tab/Shift+Tab
  containment, ESC, keyboard arrows and return-to-opener focus are supported.
  Background scroll and body styles are restored synchronously, including rapid reopen.
- Existing product selectors pass the same image presentation metadata to the
  gallery owner. Product selection/pricing/cart behavior is not changed.

During acceptance, rapid close/reopen exposed asynchronous scroll restoration;
the new viewer now restores before returning from close. Product-selector testing
also caught its old image projection dropping full/thumb/alt/dimension fields;
the image-only projection now carries these fields without HTML. Both regressions
are covered by real browser interactions, not just string assertions.

## Results

| Gate | Result |
| --- | --- |
| Gallery PHP render | 23 assertions PASS; unsaved objects only |
| Existing inventory frontend structure | 3 assertions PASS |
| Existing theme storefront | 8 assertions PASS |
| Existing catalogue/search | 45 assertions PASS |
| Browser gallery interactions | 207 checks PASS; six scenarios |
| PHP and JS syntax | PASS |
| `git diff --check` | PASS |
| `make test`, `make quality` | Exit 0; existing placeholder targets, not substantive extra coverage |

Browser scenarios: single square, single portrait-source, three-image fixture,
each at 1440px and 390px. Verified normal stage, selected thumbnail, image switching,
native full-viewport modal, originals loaded on demand, zoom/pan, native mobile tap,
previous/next, keyboard and pointer swipe, fit reset, focus containment, ESC,
scroll restoration, repeated open/close, no duplicate dialog, viewport changes,
browser back and real storage-selector image replacement. No add-to-cart action.

There were zero gallery JavaScript/page errors. One pre-existing missing
`http://localhost:8080/favicon.ico` 404 was reported separately; the test excludes
only this exact known resource and still fails on other console errors.

## Visual evidence

Before: `/private/tmp/gallery-before-{476|288}-{1440|390}-{normal|open|zoom}.png`
and `/private/tmp/gallery-before-multi-{1440|390}-{normal|open|zoom}.png`.

Final: `/private/tmp/ak-gallery-after/`:

- `single-square-{1440|390}-{normal|open|zoom}.png` (6)
- `single-portrait-{1440|390}-{normal|open|zoom}.png` (6)
- `multi-{1440|390}-{normal|open|navigation|zoom}.png` (8)

All 20 final screenshots were opened and personally visually reviewed, alongside
the before evidence. The acceptance is not inferred from DOM assertions alone.
The overlay now covers the viewport, controls are aligned/reachable, and the page
does not bleed through. Product stage/thumbnail layout remains stable.

Media limitation: the original local transparent iPhone image is 1900x1400 while
WP metadata says 1400x1900; existing responsive derivatives show the device rotated
relative to the original. Its transparent padding and photo quality are retained.
Decoded geometry prevents an additional viewer sizing error; the gallery does not
silently rewrite/rotate/crop source media or bypass responsive loading to mask this.
Physical-device Safari/pinch behavior and deployment are not claimed as tested.

## Repeating the focused tests

Run against LOCAL WordPress mounting this feature's Inventory plugin:

```sh
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-inventory/tests/product-gallery-render.php
make test-inventory-product-frontend test-theme-storefront test-theme-catalog-search
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-inventory/tests/product-gallery-render.php --fixture > /tmp/product-gallery-fixture.html
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-inventory/tests/product-gallery-render.php --single-fixture > /tmp/product-gallery-single-fixture.html
GALLERY_FIXTURE_HTML=/tmp/product-gallery-fixture.html GALLERY_SINGLE_FIXTURE_HTML=/tmp/product-gallery-single-fixture.html node wordpress/wp-content/plugins/appleklinika-inventory/tests/product-gallery-browser.cjs
```

Use `GALLERY_MEDIA_IDS` for three existing LOCAL attachments when different IDs
are needed; do not create business data for this test. Browser demo product IDs
are intentionally explicit (#476, #288); update fixture configuration deliberately
if that LOCAL catalogue changes. `PLAYWRIGHT_MODULE`, `CHROME_EXECUTABLE`,
`EVIDENCE_DIR` and `BASE_URL` may select an already installed runtime and output
location; the browser test rejects non-localhost targets. Each context is fresh
and closed normally; no persistent profile or shared QA account is used.

## Framing correction — September 7, 2026

Baseline: `5fbf493a3f0b7978ef1116b396a9b73785243b5f`, continuing
`feature/product-page-information-polish`. This supersedes the white viewer
presentation in the preceding information-polish pass, not the interaction model.

Computed DOM/CSS inspection on real three-photo products #288 and #314 found no
remaining painted border, shadow, padding, radius or pseudo-element around the
normal image. The previous pass had removed those styles, not merely recolored
the original border. However, `div.appleklinika-product-gallery__stage` still
wrapped a full-size image link. The separate div had no JS ownership and was
removed; its stable square sizing/grid now belongs directly to the link. This
retains intrinsic image containment and avoids shifts when switching image shapes.
Thumbnails are centered with safe overflow alignment; their red selected style
and 72px/64px target geometry are unchanged.

The viewer's light surround came from the white `dialog.ak-image-viewer` background
and its white `::backdrop`, not inherited Woo styling or a second modal. The dialog
is now unpainted and only `::backdrop` paints an opaque dark surface. An initial
translucent-backdrop trial was rejected visually because the page showed through.
The existing canvas is necessary for fit/zoom/pan clipping and remains unpainted.
No gallery JS, zoom/gesture algorithm, product assignment, selector, pricing,
stock, information layout or cart business logic changed.

Current results: gallery PHP 26, product-information PHP 49, frontend structure 3,
gallery browser 237 and product-information browser 479 assertions PASS (794 total).
The single-portrait fixture now explicitly uses the unsaved single-image renderer,
rather than assuming product #288 still has one photo. Existing attachments
2255/2256/2257 supply both fixture modes. No fixture/product/media is saved.
The isolated product-browser add/remove check ended with an empty cart; no order.
PHP/JS syntax and diff checks pass; the generic make gates remain placeholders.
No new console errors; the existing LOCAL favicon 404 remains separately recorded.

Evidence is ignored locally under `.local/header-actions-review/gallery-layer-correction/`
in the original workspace. `before.json` / `after.json` contain computed ancestor
and pseudo-element styles. Twelve final screenshots (two products, two viewports,
normal/fit/zoom) were personally opened and reviewed against the before captures.
No TEST SERVER, production, deployment or main access occurred.
