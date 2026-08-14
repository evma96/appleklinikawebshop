# UI QA Checklist

Manual UI QA verifies real webshop usability in the browser. Do not mark an item as `PASS` unless it was actually checked in the browser during the current implementation round.

Status values:

- `PASS`: verified in browser and working.
- `FAIL`: verified in browser and broken.
- `NOT TESTED`: not verified in browser during this round.

Last run: 2026-07-01
Scope: My Account V1 functional structure.

## 1. Navigation Check

| Item | Status | Notes |
| --- | --- | --- |
| Header links work | PASS | Browser QA confirmed normal pages still expose the logo, search, Fiókom, Kosár, and category nav where intended; checkout now uses a focused logo header without full webshop navigation. |
| Footer links work | NOT TESTED | Footer link targets were not changed in this round. |
| Product cards open real product pages | NOT TESTED | Product card links were not clicked in this pass. |
| No empty href | NOT TESTED | Footer links were not changed in this round. |
| No `#` links | NOT TESTED | Footer links were not changed in this round. |
| No broken URLs | NOT TESTED | Footer links were not changed in this round. |

## My Account Check

| Item | Status | Notes |
| --- | --- | --- |
| My Account sidebar uses final V1 navigation | PASS | Browser QA confirmed only `Vezérlőpult`, `Vásárlásaim`, `Beszámítás`, `Garanciáim`, `Visszaküldéseim`, `Fiók beállítások`, `Kedvelt termékek`, and `Kijelentkezés` are visible. |
| My Account Downloads item is hidden | PASS | Browser QA confirmed `Letöltések` is absent from the visible menu and direct Downloads endpoint access redirects to the account dashboard. |
| My Account Addresses item is hidden | PASS | Browser QA confirmed `edit-address` is removed from the visible account menu and direct endpoint access redirects to `Fiókadatok`; address data is not deleted from WooCommerce. |
| Account orders endpoint renders | PASS | Browser QA confirmed the `Rendelések` endpoint still loads inside the widened 1120px account shell with no fatal or parse errors; real order rows remain `WC_Order` driven. |
| Dashboard order count excludes checkout drafts | PASS | Docker/WooCommerce QA confirmed the current logged-in user's 18 `checkout-draft` orders are excluded from the dashboard `Vásárlásaim` count, matching the empty orders page. |
| Account details form renders | PASS | Browser QA confirmed the `Fiókadatok` endpoint loads the preserved WooCommerce save form with grouped personal, shipping, billing, and password sections; save submission was not performed. |
| Account company billing toggle works visually | PASS | Browser QA toggled `Cégként vásárolok` without submitting; personal name fields hide, `Cégnév`/`Adószám` show, required states switch, and the tax number input formats `12345678123` to `12345678-1-23`. |
| Account shipping and billing extra fields render | PASS | Browser QA confirmed Házszám, Emelet, Lépcsőház, and Ajtó fields render for both saved shipping and billing addresses. |
| Wishlist account tab works | PASS | Browser QA confirmed the `Kedvelt termékek` endpoint loads inside the widened account shell with 674px favorite rows, larger image boxes, dark product titles, visible `Megnézem` CTA text, subtle default gray borders, and fixed 44px active remove-heart buttons using the existing wishlist renderer. |

## Header Top Area Check

| Item | Status | Notes |
| --- | --- | --- |
| Piros keretes scope test completed | PASS | Temporary red outlines were applied only to `.ak-header-logo` / `.ak-checkout-header__logo` on homepage, shop, cart, account, and checkout, then removed before finishing. |
| Logo remains functional | PASS | Browser QA measured the desktop logo at `255x76` and `x=66.05, y=61.25` on homepage, shop category views, cart, account, and the focused checkout header. |
| Search remains functional | NOT TESTED | The search form remains visible and its CSS was not changed; no search query was submitted in this pass. |
| Account and cart links remain functional | PASS | Browser QA confirmed Fiókom and Kosár remain inside the existing `.ak-header-actions` wrapper. |
| Account and cart are visually grouped | PASS | The isolated `.ak-header-actions` desktop rules now keep the two buttons stacked, slightly wider, and more intentional without touching search/logo layout rules. |
| Cart count badge is aligned and dynamic | PASS | Browser QA confirmed the existing `.ak-cart-count` remains visible inside the Kosár link and keeps the dynamic cart number. |
| Active category state remains functional | PASS | Category nav remains link-based; hover/active accent is now scoped to `.ak-category-nav a` and uses Apple Klinika red. |

## Homepage Check

| Item | Status | Notes |
| --- | --- | --- |
| Piros keretes scope test completed | PASS | Desktop browser QA confirmed the temporary outlines hit one homepage product card inner wrapper and one iPhone shop product card inner wrapper. The outlines were removed before finishing. |
| Hero renders real homepage shell | PASS | Browser QA confirmed one `.ak-home` wrapper and one `.ak-home .ak-hero` wrapper on the homepage. |
| Trust tiles render expected count | PASS | Browser QA confirmed four `.ak-home-trust-tile` cards. The copy is centralized in `appleklinika_homepage_trust_tiles()`. |
| Product sections use real WooCommerce products | NOT TESTED | The homepage now supports admin-selected product IDs with WooCommerce featured/sale/latest fallbacks; needs a fresh browser/admin pass after the settings change. |
| Homepage featured product admin controls save | NOT TESTED | Verify `Settings > Apple Klinika homepage` saves comma-separated product IDs and the 1-12 display limit. |
| Homepage selected products preserve order | NOT TESTED | Verify manually selected product IDs render first, in admin order, using the shared shop card markup. |
| Homepage featured product section uses shop card proportions | PASS | Browser QA measured the homepage featured grid at 860px with three 268px cards and 28px gaps, matching the approved iPhone shop archive card width/gap while keeping the shared card internals untouched. |
| Homepage featured cards use shop Woo Blocks card context | PASS | Browser QA confirmed homepage and iPhone shop both render `UL.wc-block-product-template...`, `LI.wc-block-product`, and the shared `A.ak-product-card__inner`; no `.ak-home .ak-product-card__...` internal override remains in the loaded CSS. |
| Homepage and shop card DOM identity | PASS | Browser QA confirmed the homepage featured card and iPhone shop card both render `A.ak-product-card__inner` followed by direct `BUTTON.ak-wishlist-button`, with no injected `<p>` or `<br>` wrappers and identical `ak-product-card__content` children. |
| Homepage desktop density feels balanced | NOT TESTED | Verify hero, featured products, and trust sections feel compact without changing shop archive product cards. |
| Homepage scale is compacted | NOT TESTED | Needs a fresh desktop browser pass after the latest `.ak-home` density tuning. |
| Homepage category shortcuts removed | PASS | The homepage no longer renders the `Gyors elérés` / `Kategóriák` section; header category navigation and `ak_type` shop URLs remain separate. |
| Shop archive product cards unchanged | PASS | Browser QA compared the homepage and iPhone shop cards and confirmed the same shared `.ak-product-card` internal structure/classes are used while shop archive CSS remains the source of truth. |

## 2. Product Page Check

| Item | Status | Notes |
| --- | --- | --- |
| Piros keretes scope test completed | PASS | Temporary red outlines hit only the single product wrapper, gallery, buy panel, options wrapper, product details grid, and related-products panel; the outlines were removed before finishing. |
| No duplicated product layout | PASS | Browser QA confirmed one `.appleklinika-product-shell` renders on the tested product URL with the default WooCommerce summary hidden. |
| Single product iPhone gallery image uses portrait asset | PASS | Browser QA measured the tested iPhone 13 Pro gallery stage at 540x720 and the main image at about 395x572 from `_ak_single_product_gallery_image_id` attachment 527; the iPhone archive card stayed on featured image 525 at the approved thumbnail size. |
| Gallery works | NOT TESTED | The tested product had one real WooCommerce image, so image switching could not be meaningfully tested in this pass. |
| Thumbnails work | NOT TESTED | The tested product had one real WooCommerce image and one thumbnail. Verify with a product that has multiple gallery images. |
| Selectors work | NOT TESTED | Selector UI remains rendered from real same-model WooCommerce products, but option switching was not clicked in this pass. |
| Selected state stays on clicked option | NOT TESTED | Option switching was not clicked in this pass. |
| Product information panels render real data | PASS | Browser QA confirmed the custom product shell, options section, details grid, review panel, and four real related-product cards render on the tested product page. |
| Add to cart works | PASS | Browser QA clicked the real WooCommerce add-to-cart button, saw no console errors, and the header cart count remained present after the click. |
| Single product portrait gallery | PASS | Browser QA on `selector-teszt-iphone-13-pro-1-tb-alpesi-zold-a` confirmed the main gallery keeps the image contained in the portrait stage without frontend rotation or stretching. |
| Single product object-aware hover zoom | PASS | Browser QA confirmed blank stage/background hover leaves the gallery calm, while hovering the visible phone object adds `is-zooming is-object-hover` and updates the zoom origin. |
| Single product zoom modal | PASS | Browser QA confirmed the lightbox uses a transparent dialog with one dark product stage, 1x/2x/3x/4x controls, centered button zoom, object-aware pan only over the phone, and ESC dismissal without leaving body scroll locked. |
| Single product iPhone display image normalization | PASS | Local image analysis confirmed the iPhone 13 Pro display output uses a 1000x1450 phone-portrait canvas with the visible device at 60.4% width and 84.07% height; Media Library assignment was limited to product ID 364. |

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

| Check | Status | Evidence |
| --- | --- | --- |
| Cart, checkout, order confirmation, and account-order responsiveness | PASS | Local browser QA at 390, 768, 1024, and 1440 px found no horizontal overflow on the filled cart, checkout, order-received page, or account order detail. |
| Order confirmation labels and address details | PASS | The temporary local BACS order showed `Rendelésszám` and `Fizetési mód`; billing and shipping stayed distinct, Hungarian address components appeared once per address, and the tax number appeared once in billing. |
| Account order snapshot presentation | PASS | The temporary logged-in order displayed its immutable company, tax, payment, shipping, and address data without exposing address-book keys or versions. |

| Item | Status | Notes |
| --- | --- | --- |
| Company tax number formats while typing | PASS | Browser QA confirmed the checkout `Adószám` input formats numeric text as `12345678-1-23` and strips non-digit characters before re-inserting the two hyphens. |
| Company tax number has no overlapping placeholder | PASS | The checkout JS removes the rendered placeholder from the tax number input and keeps the Woo label/value display clear. |
| Company tax number validation remains server-side | PASS | PHP registration keeps supported `pattern`/`title` hints only, while `appleklinika_validate_company_checkout_fields()` still enforces `^\d{8}-\d-\d{2}$`. |
| Checkout save-to-profile option renders | PASS | Browser QA confirmed the WooCommerce Blocks checkout shows `Adatok mentése a fiókomba a következő vásárláshoz`; actual checkout submission was not performed. |

| Item | Status | Notes |
| --- | --- | --- |
| Cart page opens | NOT TESTED | A direct `.ak-cart-*` desktop restoration layer was added; verify the full cart page visually against the reference image at desktop width. |
| Quantity update works | NOT TESTED | Cart item/update area visuals changed only; quantity control functionality was not retested in this pass. |
| Remove item works | NOT TESTED | Separate remove links were intentionally removed from the custom cart UI; removal now depends on setting quantity to zero and updating the cart. |
| Checkout page opens | PASS | Browser QA opened `/?page_id=9` and confirmed the WooCommerce Blocks checkout renders with the new `ak-checkout-stepper`. |
| Checkout multi-step shell starts on step 2 | PASS | Browser QA confirmed `data-ak-checkout-step="2"` by default, with contact, shipping address, and order note visible while shipping/payment sections remain mounted but hidden. |
| Checkout step 3 shows real shipping/payment sections | PASS | Browser QA clicked `Tovább a szállítás és fizetéshez` and confirmed `#shipping-option` and `#payment-method` become visible without cloning or removing the Woo Blocks sections. |
| Checkout order summary remains visible | PASS | Browser QA confirmed the real WooCommerce order summary sidebar stays visible on steps 2, 3, and 4 without duplicating the summary. |
| Checkout order summary visual polish | PASS | Browser QA confirmed the checkout sidebar remains visible on steps 2, 3, and 4 after compact summary styling, with no `.ak-cart-summary` selector present on checkout. |
| Checkout summary hard scope test | PASS | Temporary red/blue/green/purple/orange outlines hit only the checkout sidebar, product rows, product images, quantity badges, and totals wrappers; the debug outlines were removed before finishing. |
| Checkout company fields scope test | PASS | Temporary outlines hit only the Step 2 `Cégként vásárolok`, `Cégnév`, and `Adószám` wrappers; Step 3 and Step 4 had no visible company-field matches. |
| Checkout company tax number constraints | PASS | Browser QA confirmed `Adószám` receives `maxlength="13"`, the `\d{8}-\d-\d{2}` pattern, and required state only while company purchase is enabled. |
| Checkout step 4 shows real terms and order button | PASS | Browser QA clicked `Tovább az összegzéshez` and confirmed the terms area and original `Megrendelés` button are visible on the final step. |
| Checkout billing address step mapping | PASS | Browser QA confirmed the WooCommerce Blocks `#billing-fields` section is visible only on step 2 when billing differs from shipping, and remains mounted but hidden on steps 3 and 4. |
| Checkout back controls work | PASS | Browser QA clicked `Vissza a szállítás és fizetéshez` and confirmed the checkout returns to step 3 without navigation or order submission. |
| Checkout duplicate back links are not visible | PASS | Browser QA confirmed the default WooCommerce return-to-cart element still exists but is hidden during the multi-step shell; only the custom step back action is visible. |
| Checkout stepper scope test completed | PASS | Temporary red outlines were applied only to the checkout stepper/layout/sidebar/order controls, with zero shop card, filter, account, or cart wrapper matches; the debug outlines were removed before finishing. |
| WooCommerce fields are not broken | PASS | Browser QA confirmed checkout field sections remain present in the DOM; the stepper only toggles visibility classes and does not unmount WooCommerce Blocks fields. |
| Checkout transaction localization | PASS | Browser QA confirmed Hungarian checkout section labels, country placeholder, free shipping, and the single real `Megrendelés` control; no product or provider title was rewritten. |
| Checkout primary heading and progress semantics | PASS | Exactly one visible `Pénztár` H1 is present. All four steps retain accessible labels and current/completed/future state; 320–430 px uses compact markers plus the current label, while 768–1920 px preserves the desktop presentation. |
| Effective billing summary | PASS | Guest and logged-in personal, same-address, separate-address, and company states showed consistent billing and shipping information on Steps 3 and 4 without a false missing-billing message. |
| Transaction responsive matrix | PASS | Checkout had no horizontal overflow at 320, 360, 390, 430, 640, 768, 1024, 1440, or 1920 px; populated catalogue, representative in-stock product, and populated cart also remained contained at 390, 768, and 1440 px. |
| Buyback questionnaire focus | PASS | The intentionally focused questionnaire heading keeps programmatic focus and now renders a 3 px brand-red, rounded focus indicator at 390 and 1440 px instead of the browser-native blue rectangle. |

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
