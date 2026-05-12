# UI QA Checklist

Manual UI QA verifies real webshop usability in the browser. Do not mark an item as `PASS` unless it was actually checked in the browser during the current implementation round.

Status values:

- `PASS`: verified in browser and working.
- `FAIL`: verified in browser and broken.
- `NOT TESTED`: not verified in browser during this round.

Last run: 2026-05-12
Scope: Strictly scoped header top polish.

## 1. Navigation Check

| Item | Status | Notes |
| --- | --- | --- |
| Header links work | PASS | Browser QA confirmed the header still exposes the logo, search, Fiókom, Kosár, and category nav links after the strictly scoped header polish. |
| Footer links work | NOT TESTED | Footer link targets were not changed in this round. |
| Product cards open real product pages | NOT TESTED | Product card links were not clicked in this pass. |
| No empty href | NOT TESTED | Footer links were not changed in this round. |
| No `#` links | NOT TESTED | Footer links were not changed in this round. |
| No broken URLs | NOT TESTED | Footer links were not changed in this round. |

## Header Top Area Check

| Item | Status | Notes |
| --- | --- | --- |
| Piros keretes scope test completed | PASS | Temporary red outlines were applied only to `.ak-header-logo`, `.ak-header-actions`, and `.ak-category-nav`, then removed before final styling. |
| Logo remains functional | PASS | Browser QA confirmed the logo link is still visible; CSS now enlarges the desktop logo through `.ak-header-logo img` only. |
| Search remains functional | NOT TESTED | The search form remains visible and its CSS was not changed; no search query was submitted in this pass. |
| Account and cart links remain functional | PASS | Browser QA confirmed Fiókom and Kosár remain inside the existing `.ak-header-actions` wrapper. |
| Account and cart are visually grouped | PASS | The isolated `.ak-header-actions` desktop rules now keep the two buttons stacked, slightly wider, and more intentional without touching search/logo layout rules. |
| Cart count badge is aligned and dynamic | PASS | Browser QA confirmed the existing `.ak-cart-count` remains visible inside the Kosár link and keeps the dynamic cart number. |
| Active category state remains functional | PASS | Category nav remains link-based; hover/active accent is now scoped to `.ak-category-nav a` and uses Apple Klinika red. |

## 2. Product Page Check

| Item | Status | Notes |
| --- | --- | --- |
| No duplicated product layout | NOT TESTED | Not part of this UI change. |
| Gallery works | NOT TESTED | Not part of this UI change. |
| Thumbnails work | NOT TESTED | Not part of this UI change. |
| Selectors work | NOT TESTED | Not part of this UI change. |
| Selected state stays on clicked option | NOT TESTED | Not part of this UI change. |
| Check icon does not overlap text | PASS | Browser QA showed the selected color check icon still top-right while color names wrap without ellipsis. |
| Add to cart works | NOT TESTED | Not part of this UI change. |

## 3. Shop / Listing Page Check

| Item | Status | Notes |
| --- | --- | --- |
| Product cards are equal size | NOT TESTED | Visual card CSS was intentionally not changed in this round. |
| Product card wishlist toggle works | NOT TESTED | Wishlist was not changed in this round. |
| Images have consistent ratio | NOT TESTED | Product image CSS was intentionally not changed in this round. |
| Savings badge does not overlap product image | NOT TESTED | Savings badge CSS was intentionally not changed in this round. |
| iPhone filters remain unchanged | PASS | Browser clicked the iPhone top nav link and verified: Típus, Ár, Tárhely, Állapot, Szín, SIM. |
| iPad filters show relevant groups | PASS | Browser checked iPad filters after cleanup: Modell / széria, Ár, Tárhely, Szín, Kapcsolat, Állapot. |
| MacBook filters show relevant groups | PASS | Browser checked MacBook filters after cleanup: Modell / széria, Ár, Kijelzőméret, Chip, RAM, Tárhely, Szín, Állapot. Akku ciklus is no longer part of the upload/filter workflow. |
| Apple Watch filters show relevant groups | PASS | Browser checked Apple Watch filters after cleanup: Modell / széria, Ár, Tokméret, Tok anyaga / színe, Kapcsolat, Szíj, Állapot. |
| Category filters actually filter products | PASS | Browser checked iPad Wi-Fi, MacBook M3, and Apple Watch GPS query filters returned matching WooCommerce products. |
| Filters look modern | NOT TESTED | Filter styling was not changed in this round. |
| Filters do not look like default browser UI | NOT TESTED | Filter chip removal links were added without changing sidebar filter controls. |
| Sorting works if available | NOT TESTED | Sorting was not changed in this round. |
| Shop breadcrumb and raw title are hidden | PASS | Browser QA confirmed `.woocommerce-breadcrumb` and `.wp-block-query-title` are no longer visible on the base shop archive and all `ak_type` shop views, while product count, filters, and product cards remain visible. |
| Shop top info looks polished | PASS | Browser QA confirmed the raw breadcrumb/title area is gone and the shop content moves up through the normal main padding, without negative margin hacks. |
| Pagination looks polished and remains functional | PASS | Browser QA confirmed the WooCommerce block pagination wrapper and real page links remain present, with current page and next-page links rendered from WordPress output. |

## 4. Layout Check

| Item | Status | Notes |
| --- | --- | --- |
| No oversized sections | NOT TESTED | Final visual cleanup changed shared spacing only; verify at 1440px desktop. |
| No huge empty spacing | NOT TESTED | Narrow browser QA shows compact stacked cart cards; 1440px desktop spacing still needs visual verification in the user's browser. |
| Compact ecommerce proportions | NOT TESTED | Cart item cards now have a subtle hover lift/movement like storefront product cards. Verify at 1440px desktop. |
| Mobile layout is usable | PASS | Narrow browser QA shows the logo/search on the left and Fiókom/Kosár stacked in the right column. |
| Desktop layout is balanced | PASS | Browser checked short category pages after adding `flow-root`; filter/product content remains in the shop main flow and no fatal errors appeared. |

## 5. Cart / Checkout Check

| Item | Status | Notes |
| --- | --- | --- |
| Cart page opens | NOT TESTED | A direct `.ak-cart-*` desktop restoration layer was added; verify the full cart page visually against the reference image at desktop width. |
| Quantity update works | NOT TESTED | Cart item/update area visuals changed only; quantity control functionality was not retested in this pass. |
| Remove item works | NOT TESTED | Separate remove links were intentionally removed from the custom cart UI; removal now depends on setting quantity to zero and updating the cart. |
| Checkout page opens | NOT TESTED | Checkout sidebar product rows were changed with CSS only; browser verification is still needed at desktop width. |
| WooCommerce fields are not broken | NOT TESTED | Checkout form fields, shipping, payment, notices, terms, and order submit were not changed. |

## 6. WooCommerce Admin Check

| Item | Status | Notes |
| --- | --- | --- |
| Product editor device type selector exists | PASS | Browser opened a real WooCommerce product edit screen and found `Készüléktípus`. |
| iPhone upload shows only iPhone-specific option fields | PASS | Browser selected iPhone; SIM, storage, color, and battery option remained visible while iPad/MacBook/Watch-only fields were hidden. |
| iPad upload shows only iPad-specific option fields | PASS | Browser selected iPad; connectivity, storage, and color remained visible while SIM, MacBook, and Watch-only fields were hidden. Connectivity options are limited to Wi-Fi and Wi-Fi + Cellular. |
| MacBook upload shows only MacBook-specific option fields | PASS | Browser selected MacBook; screen size, Apple Silicon chip family, RAM, storage, and color remained visible while SIM/connectivity/Watch-only fields were hidden. Akku ciklus is intentionally not shown. |
| Apple Watch upload shows only Watch-specific option fields | PASS | Browser selected Apple Watch; connectivity, case size, case material, and strap remained visible while iPhone/iPad/MacBook-only fields were hidden. Connectivity options are limited to GPS and GPS + Cellular. Case size options include 42 mm and 46 mm. |
| Apple Watch model options stay paired | PASS | Browser selected Apple Watch Ultra 2 and verified only GPS + Cellular, 49 mm, Titán, and Ultra titanium colors are selectable; Series 10 correctly switches between aluminium and titanium color sets. |
| Apple model dropdown follows device type | PASS | Browser checked that iPad has 2019+ models, MacBook has Apple Silicon M-series models, Apple Watch has SE 2 / Series 8+ models, and old duplicate catalog labels were removed. |
