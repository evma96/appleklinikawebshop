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

## LOCAL selector cleanup — 2026-10-15

This owner-requested UI update supersedes the historical battery-choice presentation
above. Remove the battery selector markup and its unused private renderer; correct
only the directly related instruction to “Válassz állapotot és színt.” Battery health
remains a factual device property in the existing product details. No product data,
price hooks or order logic changes.

The three lower cards use equal desktop columns and stretch to the same row height.
Shared heading space aligns their content; the final selector/equipment section uses
available space and assurance rows distribute naturally. At <=1100px cards retain
the existing single-column layout with natural heights. No fixed card height, empty
battery placeholder, hidden selectable battery UI or unrelated redesign is added.

Existing browser regressions now expect no battery choice and the normal product
price. Other selector, equipment, trust-copy and purchase-state checks are retained.
LOCAL browser verification: 1440px and 1280px desktop cards have identical top,
bottom and width (1440px baseline: each 358.66px wide and 588px high). At 390px and
320px the cards stack naturally without horizontal overflow. Long price-difference
labels wrap rather than truncate. Native condition/color/storage clicks update the
selected device, price, equipment and unchanged assurance content correctly at
desktop and mobile widths. Battery selector count is zero, including after switches.
Console errors: none. Existing PHP checks pass: structure 3, information rendering
49, purchase rendering 68 (120 total), plus PHP syntax. Tests persist no products.
The broader historical cart/gallery browser suite was updated for the intentional
battery-choice removal but was not rerun; no cart, order or provider flow was needed.
Screenshots: private `selector-ui-local-20261015` evidence directory outside Git.
No commit, push, merge, TEST access or deployment.


## LOCAL two-column product hero — 2026-10-26

Owner requested structural composition only, superseding the three-card desktop
row above. No additional copy, trust block, photography enlargement or filler.
Branch: `feature/local-product-hero-columns`; base: `0841b1ff`.

`ProductFrontendDisplay.php` adds two neutral Grid column wrappers. The left contains
the unchanged gallery followed by the existing condition/color and storage/equipment
cards, stacked at full column width. The right contains the unchanged purchase card
and the existing guarantee card. The guarantee renderer was extracted without any
copy/data change. Existing classes, IDs, data attributes and hook entrypoints remain.
Selectors use document queries and their own group containers, not the previous
outer parent; all selector/add-to-cart JavaScript remains byte-identical.

Column children have natural height and 24px spacing. The previous three-column
heading minimum height / bottom-distribution rules were removed. At <=1100px the
new wrappers use `display:contents` and CSS ordering keeps the existing visual
sequence: gallery, purchase, condition/color, storage/equipment, guarantees.
There is no duplicate component/form, fixed equal height or separate mobile form.
Gallery size, thumbnails, zoom, Extra protection CSS/markup and all lower product
information/reviews/partner/footer sources are unchanged.

Personally inspected full-page screenshots at 1440, 1366, 1280, 1152, 390 and 320.
No horizontal overflow at any requested width. Desktop: gallery-to-configuration
gap reduced from 566.29px to 24px; left width 636px (620px at 1152); purchase and
guarantee share exactly the same x-position and 450px width, with a 24px gap and
22px radii. Purchase height remains 1133.29px. Left/right content ends naturally
about 51px apart, without forced equal heights. Mobile cards remain 342/272px wide
at 390/320 and keep natural heights. Final visual approval remains with Martin.

Focused LOCAL checks: 299 PASS (structure 3, protection 101, association 52,
information 49, purchase 68, gallery 26), plus PHP syntax and diff whitespace checks.
Browser verification covers condition/color/storage updates, changed product ID,
price, equipment, description, guarantee and photos, exactly one regenerated
purchase form, default empty extras and disabled unpriced adapter. Thumbnail
selection, viewer 2x zoom, fit reset, next image, close/focus return and scroll unlock
work. Mobile selected product #310 plus premium case and desktop #288 plus the
same case produce distinct parent-labeled lines and a native 471980 HUF total.
Removing one device removes only its case; cleanup returns the cart to empty.
No console warning/error or provider call was observed. Automated QA fixtures and
order were cleaned through Woo CRUD. Browser-created unpaid draft #2512 is removed
through guarded Woo CRUD. Existing product IDs, order IDs, accessory prices/stock
and checked operational settings are preserved.

Evidence outside Git:
`/Users/apple/.codex/visualizations/2026/09/11/01a08fcb-a363-7761-acb7-46d99e2bde2c/local-product-hero-columns-20261026/`.
Files: `before-1440-full.png`, `product-{1440,1366,1280,1152,390,320}-full.png`,
`cart-regression-1440.png`, `verification.json`.
LOCAL only. No commit, push, merge, TEST access or deployment.

## LOCAL lower-grid alignment — 2026-10-26

The approved hero remains unchanged. The lower description/data and similar-products
grid now consumes the same `--ak-product-columns` and `--ak-product-column-gap`
properties as the hero. Extra protection sets its existing 410–450px right-column
range on the shared shell, instead of overriding only the hero. The previous
independent lower 360px sidebar / 30px column gap is superseded. Desktop column
gap is 34px; the existing mobile stack and 30px lower row gap are unchanged.

Full-page browser inspection: 1440, 1366, 1280, 1152, 390 and 320px. Upper and
lower desktop edges/widths match exactly: 636/450px at 1440, 1366 and 1280;
620/450px at 1152. All measured upper geometry is unchanged from this task's
baseline. Mobile remains 342/272px wide with identical geometry and no horizontal
overflow. Existing structure checks: 3 PASS; PHP syntax: PASS; browser warning/error
log: empty. No markup, copy, JavaScript, business logic or database changes.

Evidence outside Git: sibling `local-product-grid-alignment-20261026/` in the
visualizations directory above, containing six full-page PNGs and `verification.json`.
Branch: `feature/local-product-grid-alignment`. LOCAL only; no commit, push, merge,
TEST access or deployment.

## LOCAL model selector options — 2026-10-26

Branch: `feature/local-model-selector-options`. Preserve the approved iPhone
presentation and shared desktop grid. No commit, push, TEST access or deployment.

- `Application/ProductSelector/ModelSelectorOptions.php` is a read-only projection,
  not a new product database. It uses `appleklinika_device_catalog` exact-model
  `colors` / `storage_capacity_keys`, resolving the existing official-spec model
  aliases in the frontend adapter. Where capacities are absent, it reads only
  `Kapacitások` / `Tárhely` rows from the existing exact-model
  `appleklinika_official_specs_by_model` or the already-supported `_ak_official_specs`
  fallback. RAM and other numbers are never interpreted as disk capacity. If a
  model has no catalog colors, explicit `Színek` rows can resolve existing catalogue
  color names; unrelated palette colors are not appended.
- Known same-model product values remain the fallback when no explicit source
  exists. No capacity or color is guessed, no catalogue entry is written.
- Product-backed options retain their existing ordering/deltas; missing valid
  options append as disabled buttons, including native disabled attributes before
  JavaScript. The existing color/storage/condition combination check additionally
  requires native Woo `is_purchasable()` and `is_in_stock()` eligibility.
- The Watch storage/equipment panel is not rendered, including its wrapper and
  quick-facts area. Stored storage and lower product information remain intact.
  Product matching permits the absent storage control, preserving color/condition
  switches and one canonical purchase form.
- Sparse iPad/Mac/Watch color controls use 104px width, storage 132px maximum,
  wrapping with an 8px gap. Storage can shrink to fit two at 320px. Existing
  heights, radii, swatches, red selection, disabled appearance and typography
  remain shared. iPhone sizing is untouched and matches the measured baseline.

Representative existing LOCAL products: #288 iPhone 13 Pro, #383 iPad Air 5
(256 GB), #385 MacBook Air M2 (256 GB), #473 Watch Series 9. Each was visually
checked at 1440, 1280, 390 and 320: 16 combinations, no horizontal overflow.
iPad shows 64/256 GB, Mac shows 256/512 GB/1/2 TB; unavailable options are visible
and disabled. Watch has zero storage selector panels. iPhone option structure/states
and 1440 geometry equal the pre-task snapshot. Existing price-delta computation
and candidate ordering are unchanged; no pricing correction is included. Browser clicks switched iPhone
storage/color/condition (#288 -> #292 -> #308 -> #310) and Watch color
(#473 -> #480 -> #473). Repeated Watch selection keeps one purchase form.
No cart/order fixture or provider action was needed.

Focused regression: 169 PASS (model selector 49, information 49, purchase 68,
structure 3); syntax and diff checks pass. Browser warning/error logs are empty.
No price calculation, inventory mutation, purchase/extra-protection, gallery,
cart, Back Office or lifecycle implementation changed.

Known source limitation, not guessed: no explicit capacity list exists in the
current stored model/spec data for `ipad_pro_11_inch_m5` (#467),
`ipad_pro_13_inch_m5` (#468), `ipad_a16` (#469), `ipad_air_11_inch_m4` (#470),
`ipad_air_13_inch_m4` (#471), `ipad_mini_a17_pro` (#456). Those models show known
exact-model product capacities only, with the same compact controls; a complete
alternative list requires verified model data later.

Screenshots / DOM measurements outside Git:
`/Users/apple/.codex/visualizations/2026/09/11/01a08fcb-a363-7761-acb7-46d99e2bde2c/local-model-selector-options-20261026/`.
