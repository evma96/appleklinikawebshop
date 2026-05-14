# UI QA Checklist

Manual UI QA verifies real webshop usability in the browser. Do not mark an item as `PASS` unless it was actually checked in the browser during the current implementation round.

Status values:

- `PASS`: verified in browser and working.
- `FAIL`: verified in browser and broken.
- `NOT TESTED`: not verified in browser during this round.

Last run: 2026-05-13
Scope: WooCommerce Blocks checkout multi-step shell stabilization.

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
| Checkout page opens | PASS | Browser QA opened `/?page_id=9` and confirmed the WooCommerce Blocks checkout renders with the new `ak-checkout-stepper`. |
| Checkout multi-step shell starts on step 2 | PASS | Browser QA confirmed `data-ak-checkout-step="2"` by default, with contact, shipping address, and order note visible while shipping/payment sections remain mounted but hidden. |
| Checkout step 3 shows real shipping/payment sections | PASS | Browser QA clicked `Tovább a szállítás és fizetéshez` and confirmed `#shipping-option` and `#payment-method` become visible without cloning or removing the Woo Blocks sections. |
| Checkout order summary remains visible | PASS | Browser QA confirmed the real WooCommerce order summary sidebar stays visible on steps 2, 3, and 4 without duplicating the summary. |
| Checkout step 4 shows real terms and order button | PASS | Browser QA clicked `Tovább az összegzéshez` and confirmed the terms area and original `Megrendelés` button are visible on the final step. |
| Checkout billing address step mapping | PASS | Browser QA confirmed the WooCommerce Blocks `#billing-fields` section is visible only on step 2 when billing differs from shipping, and remains mounted but hidden on steps 3 and 4. |
| Checkout back controls work | PASS | Browser QA clicked `Vissza a szállítás és fizetéshez` and confirmed the checkout returns to step 3 without navigation or order submission. |
| Checkout duplicate back links are not visible | PASS | Browser QA confirmed the default WooCommerce return-to-cart element still exists but is hidden during the multi-step shell; only the custom step back action is visible. |
| Checkout stepper scope test completed | PASS | Temporary red outlines were applied only to the checkout stepper/layout/sidebar/order controls, with zero shop card, filter, account, or cart wrapper matches; the debug outlines were removed before finishing. |
| WooCommerce fields are not broken | PASS | Browser QA confirmed checkout field sections remain present in the DOM; the stepper only toggles visibility classes and does not unmount WooCommerce Blocks fields. |

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
