# Product information presentation

## Scope and ownership

`ProductFrontendDisplay.php` owns the product information markup, display labels and existing selector presentation refresh. `assets/product-gallery.css` places the neutral magnifier caption below the contained photo. Gallery/viewer JavaScript, header, checkout, pricing and cart business logic are unchanged.

Upper cards separate condition/color/battery choices, storage choices with equipment facts, and configured purchase assurances. The lower section groups device facts and condition/equipment details. Canonical SKU and remaining attributes are available through a native reference disclosure; manufacturer specifications use a native disclosure too. Availability remains in the purchase panel rather than repeating in the equipment card.

Color labels retain the Hungarian portion of the catalogue label. SIM and battery terminology and grade punctuation are presentation-only. Existing warranty durations, accessories and battery percentages are never synthesized. Selected-product descriptions, lead text, quick facts, reference facts and battery-health text update along the existing selector path, so the information describes the selected physical phone.

Only products on `localhost` / `127.0.0.1` with the existing `ak-selector-demo-iphone-` SKU prefix and iPhone device metadata use factual title/description fallback. No stored title, description, canonical key, SKU, image assignment or price is rewritten. Non-demo and non-local business copy remains authoritative.

## Automated checks

- `tests/product-information-presentation.php`: unsaved WC product plus filtered metadata; 30 assertions, no saved fixtures.
- Existing product frontend structure: 3 assertions.
- Existing gallery renderer: 23 assertions, no saved fixtures.
- Existing theme storefront: 12 assertions.
- Existing catalogue/search discoverability: 45 assertions.
- `tests/product-information-browser.cjs`: 407 assertions passed in LOCAL Chromium at 1440px and 390px, using native clicks on real three-photo products, all available color/storage/grade/battery choices, current facts/prices/stock, related links and an isolated add/remove cart round-trip. Never opens checkout or creates an order. `EVIDENCE_DIR` must point to an ignored local directory; `PLAYWRIGHT_MODULE` and `CHROME_EXECUTABLE` optionally select the installed runtime. A QA cart storage-state file is saved for exact recovery if cleanup is interrupted; never commit it.

The general `make test` and `make quality` targets currently print unconfigured-suite/tool notices; they are not substitutes for the actual checks above. The known LOCAL `/favicon.ico` 404 is recorded separately from new console/page errors.

## Visual acceptance

Products #288 (128 GB, A+, 84%), #314 (512 GB, B, 84%) and #366 (1 TB, B, 86%) were captured before and after at both widths. Screenshots include full pages, gallery/purchase area, three cards and description/details. Final screenshots were personally opened and inspected, not accepted from DOM geometry alone.

Local evidence is kept outside Git at `.local/header-actions-review/product-information/` in the original workspace, using `before-<id>-<width>-<section>.png`, `after-<id>-<width>-<section>.png` and `functional-results.json`. No target-server or production acceptance is claimed.

The final run's cart was removed through the normal theme remove link. An earlier runner attempt used an incorrect removal selector before recovery state was saved; its isolated anonymous session was left to expire rather than deleting an uncertain session. The later recorded interrupted cart was recovered and emptied. No order was created. All 123 local product post/meta records retained the same SHA-256 fingerprint before/after: `beb6eabed629d8dc1c20e29bb748027346e970dedd4276cd13481125a91de0e6`.
