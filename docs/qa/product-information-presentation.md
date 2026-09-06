# Product information presentation

## Scope and ownership

`ProductFrontendDisplay.php` owns the product information markup, display labels and existing selector presentation refresh. `assets/product-gallery.css` places the neutral magnifier caption below the contained photo. Gallery/viewer JavaScript, header, checkout, pricing and cart business logic are unchanged.

Upper cards separate condition/color/battery choices, storage choices with equipment facts, and configured purchase assurances. The lower section groups device facts, condition/equipment details and remaining attributes. Canonical SKU is available through a native reference disclosure; complete manufacturer specifications use a native disclosure too. Availability remains in the purchase panel rather than repeating in the equipment card.

Color labels retain the Hungarian portion of the catalogue label. SIM and battery terminology and grade punctuation are presentation-only. Existing warranty durations, accessories and battery percentages are never synthesized. Selected-product descriptions, lead text, quick facts, reference facts and battery-health text update along the existing selector path, so the information describes the selected physical phone.

Only products on `localhost` / `127.0.0.1` with the existing `ak-selector-demo-iphone-` SKU prefix and iPhone device metadata use factual title/description fallback. No stored title, description, canonical key, SKU, image assignment or price is rewritten. Non-demo and non-local business copy remains authoritative.

## Automated checks

- `tests/product-information-presentation.php`: unsaved WC product plus filtered metadata; 49 assertions, no saved fixtures. Includes complete specification preservation, unknown-row retention, non-mutating Hungarian labels, source/date presentation and four-highlight coverage.
- Existing product frontend structure: 3 assertions.
- Existing gallery renderer: 26 assertions, no saved fixtures.
- Existing theme storefront: 18 assertions, including source-level removal of listing image decoration.
- Existing catalogue/search discoverability: 45 assertions.
- `tests/product-information-browser.cjs`: 507 assertions passed in LOCAL Chromium at 1440px and 390px, using native clicks on real three-photo products, all available color/storage/grade/battery choices, current facts/prices/stock, related links and an isolated add/remove cart round-trip. Includes both iPhone listing routes, undecorated 220px media slots, contained images without color blending, red selected outlines, unframed surfaces, 44px viewer controls, ESC, keyboard navigation, focus return, scroll locking/restoration, readable specification expansion and no leaked import date/provider UI. Never opens checkout or creates an order. `EVIDENCE_DIR` must point to an ignored local directory; `PLAYWRIGHT_MODULE` and `CHROME_EXECUTABLE` optionally select the installed runtime. A QA cart storage-state file is saved for exact recovery if cleanup is interrupted; never commit it.

The general `make test` and `make quality` targets currently print unconfigured-suite/tool notices; they are not substitutes for the actual checks above. The known LOCAL `/favicon.ico` 404 is recorded separately from new console/page errors.

## Visual acceptance

Products #288 (128 GB, A+, 84%), #314 (512 GB, B, 84%) and #366 (1 TB, B, 86%) were captured before and after at both widths. Screenshots include full pages, gallery/purchase area, three cards and description/details. Final screenshots were personally opened and inspected, not accepted from DOM geometry alone.

Local evidence is kept outside Git at `.local/header-actions-review/product-information/` in the original workspace, using `before-<id>-<width>-<section>.png`, `after-<id>-<width>-<section>.png` and `functional-results.json`. No target-server or production acceptance is claimed.

The first pass's final run cart was removed through the normal theme remove link. An earlier runner attempt used an incorrect removal selector before recovery state was saved; its isolated anonymous session was left to expire rather than deleting an uncertain session. The later recorded interrupted cart was recovered and emptied. No order was created. All 123 local product post/meta records retained the same SHA-256 fingerprint before/after: `beb6eabed629d8dc1c20e29bb748027346e970dedd4276cd13481125a91de0e6`.

## Visual completion correction — September 7, 2026

Before evidence confirmed a 26px rounded, bordered grey gallery stage with 16px padding and separate framed thumbnails. The viewer was already a native viewport dialog, not a nested modal; its dark-grey surface, outlined control pills and wide side gutters contributed to the heavy visual impression.

The correction removes the gallery surface/border/padding and neutral thumbnail boxes while preserving the stable contain geometry and all red selected outlines. The viewer uses a white viewport with no image/canvas frame, lower-contrast borderless 44px controls and narrower side gutters. No viewer JavaScript or navigation/zoom/scroll algorithm changed.

Device and equipment facts now use unboxed label/value groups. The exact SKU sits behind a quiet “Cikkszám megtekintése” disclosure. Manufacturer specifications show display, chip, rear camera and connectivity highlights (when present), followed by native “Összes műszaki adat” expansion grouped by topic. All full rows, including unknown labels, remain available. Manufacturer model options are clearly separate from the selected physical device configuration. Stored specification dates/provider titles are not rendered; the source URL remains a secondary link. Stored specification data is not rewritten. The known model-level nature of these specifications is unchanged.

The LOCAL demo description now reads as compact prose; non-demo descriptions are untouched. Similar products retain the same query/URLs/prices and use full, unboxed portrait thumbnails with quieter row separators. The approved header, upper-card red option states and configured guarantee promises remain unchanged.

Correction evidence: `.local/header-actions-review/product-information-completion/` in the original workspace. Products #288/#314/#366 were captured at 1440px/390px, including full page, gallery/thumbs, fit/zoom viewer, cards, description, facts, expanded specifications and related products. The final screenshots were personally opened and reviewed. The correction's isolated cart round-trip ended empty; no order or saved fixture was created.

The subsequent focused [gallery framing correction](product-gallery-ux.md#framing-correction--september-7-2026) removes the redundant stage div and replaces the painted white viewer with an unpainted dialog over a single dark backdrop. Product information presentation above remains unchanged.

## Listing image panel removal — September 7, 2026

The owner's screenshot showed a separate remaining theme-owned panel. On baseline `366766ebbf6db3079d821f5877108160a2b6d731`, `appleklinika_render_product_card()` renders `.ak-product-card__image`. Its base rules in `assets/css/frontend.css` painted a background, border and radius; the scoped Woo listing rules painted another gradient, border, radius and inset shadow. The image also had padding and multiply blending. The already corrected Inventory PDP/related gallery is a separate owner and was not changed.

Nine production CSS declarations were deleted at their original locations; no new declarations, wrappers or overrides were added. The useful fixed-size flex slot remains for alignment, with `object-fit: contain` and the existing hover behavior. The old theme regression that required gradient/blending was replaced with checks against reintroducing decoration. Browser coverage checks both `/?post_type=product&ak_type=iphone` and `/?product_cat=iphone` at both widths.

Before/after evidence is in the original workspace's ignored `.local/header-actions-review/plp-image-layer/` directory. All ten final screenshots (listing, individual card, PDP, related products and opened viewer at 1440px/390px) were personally opened and reviewed. The nested panel is visibly gone, not merely recolored. White/light phones remain readable against their original photo backgrounds; the red selected-thumbnail state is unchanged.

All 16 listing cards per viewport retain identical x/y/width/height, text, links and image sources. The first card remains 278.65625×500px on desktop and 318×500px on mobile; media height remains 220px. PDP and related-image computed styles match the baseline. Removing image padding increases usable photo space without changing image sources, fit mode or cropping. Existing square Woo thumbnail derivatives are not regenerated by this change.

The isolated cart round-trip ended empty, with no order created. No product/image assignments, TEST SERVER or production data were changed. This is LOCAL Chromium acceptance, not a deployment or physical Safari sign-off.

Final focused regression: 141 PHP assertions (18 storefront + 45 catalogue/search + 3 frontend structure + 26 gallery render + 49 information), 507 product-information browser assertions and 237 gallery browser checks: 885 total. PHP/JavaScript syntax and `git diff --check` pass. Required `make test` / `make quality` commands exit successfully but still report unconfigured generic tools. The pre-existing isolated favicon 404 is the only recorded browser warning; no new page/console error was found.
