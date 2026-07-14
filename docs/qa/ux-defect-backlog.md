# Apple Klinika UX Defect Backlog

QA pass date: 2026-07-03  
Environment: local `http://localhost:8080`  
Test states: guest, logged-in demo customer `tesztvasarlo@appleklinika.local`, authenticated admin browser session  
Scope: discovery only. No UI, CSS, PHP, template, product, order, or data fixes were made as part of this QA pass.

## Executive Summary

The webshop is usable in several core areas, but multiple trust-damaging empty states and WooCommerce fallback screens are still visible. The largest functional blocker is checkout: a customer cannot complete a purchase because no payment methods are available. The next most urgent fixes are the empty cart, blank 404 page, raw guest login page, raw search/no-results state, and contradictory My Account buyback empty copy.

No browser console errors were captured during the sampled pages.

## Top 10 Issues To Fix First

1. UX-001 — Checkout cannot be completed because no payment method is available.
2. UX-002 — Empty cart state is raw/off-brand and has a green CTA.
3. UX-004 — 404 page is effectively blank.
4. UX-005 — Logged-out My Account/login page still looks like a raw WooCommerce fallback.
5. UX-006 — Search/no-results page is sparse, partly untranslated, and has no helpful recovery path.
6. UX-007 — Buyback/Beszámítás page shows an empty-state message while also showing a demo record.
7. UX-003 — Empty checkout redirects to cart without a clear checkout-specific explanation.
8. UX-016 — Product/category pages still show missing-image signals in sampled runs.
9. UX-011 — Cart item removal is not clearly discoverable.
10. UX-014 — Logged-in checkout shows account-save wording that is confusing for an already logged-in customer.

## Quick Wins

- Style the empty cart state with an Apple Klinika card, red CTA, and links back to shop/category pages.
- Add a proper 404 template with search, category shortcuts, and return-to-shop CTA.
- Replace the generic search/no-results output with a branded no-results state.
- Hide or rewrite contradictory empty copy in the Buyback/Beszámítás account endpoint when records exist.
- Localize raw WooCommerce notices and button labels where they are currently English or generic.
- Make cart remove/coupon actions more explicit without changing cart logic.

## Bigger Tasks

- Checkout payment readiness: configure real payment methods or create a clear pre-launch payment-disabled state.
- Account system V1 content depth: order details, warranty, returns, and buyback populated states need dedicated QA and data rules.
- Product image reliability: audit media IDs, missing thumbnails, gallery fallbacks, and category archive image loading.
- Guest account/login flow: design and implement a branded login/register/forgot-password experience.
- Shared empty-state system: cart, search, shop no-results, account empty endpoints, 404, and checkout-empty should share consistent UX patterns.

## Do-Not-Touch Warnings

- Do not rework the approved shop product card renderer while fixing homepage/account empty states.
- Do not touch checkout stepper, checkout right summary, billing/company field logic, or payment/shipping internals unless the fix specifically targets checkout.
- Do not change WooCommerce product data, prices, stock, wishlist storage, or official specs while fixing visual empty states.
- Do not globally style WooCommerce selectors when a scoped page/template fix is enough.
- Do not hide default WooCommerce warnings without replacing them with a functional, informative Apple Klinika message.

## Testing Coverage Notes

- Empty cart was captured after clearing the test session cart.
- Logged-in checkout was tested with a product and reached the payment block.
- Empty checkout was tested and redirected to cart.
- Guest login page was tested.
- Demo customer login was tested successfully; after login, Home, Shop, My Account, and Cart were reachable.
- Account logged-in dashboard, orders, buyback, warranty, returns, settings, and favorites were sampled.
- A full real order submission was blocked by the missing payment method state.
- Deep order-detail drilldown and every possible filter combination were not exhaustively tested in this pass.

---

## Issues

### UX-001 — Checkout Cannot Be Completed: No Payment Methods Available

- Area: Checkout
- URL tested: `http://localhost:8080/?page_id=9`
- User state: logged-in demo customer
- Severity: Critical
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-checkout-current.png`
- Steps to reproduce:
  1. Log in as the demo customer.
  2. Add or keep a product in cart.
  3. Open checkout.
  4. Continue to payment section.
- Expected result: Customer can choose a real payment method or sees a deliberate pre-launch message explaining that checkout is disabled.
- Actual result: WooCommerce shows: “There are no payment methods available. Please contact us for help placing your order.”
- Likely file/template/CSS area: WooCommerce payment gateway configuration; checkout rendering in `wordpress/wp-content/themes/appleklinika-theme/functions.php`; checkout styles in `assets/css/frontend.css` and `assets/css/checkout-sidebar.css`.
- Suggested fix direction: Configure at least one real test payment method for local QA, or create a clear temporary pre-launch payment-disabled state that does not look like a broken checkout.
- Risk: Medium. Payment logic is sensitive; do not fake gateways or hardcode payment options.

### UX-002 — Empty Cart State Looks Raw And Off-Brand

- Area: Cart
- URL tested: `http://localhost:8080/?page_id=8`
- User state: guest and logged-in
- Severity: High
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-cart-empty-viewport.png`
- Steps to reproduce:
  1. Empty the cart.
  2. Open the cart page.
- Expected result: Apple Klinika styled empty-cart card with clear message, red CTA, and helpful links back to shop/categories.
- Actual result: Minimal WooCommerce-style empty state appears with a green “Vásárlás folytatása” action.
- Likely file/template/CSS area: cart rendering helpers in `functions.php`; cart styles in `assets/css/frontend.css`.
- Suggested fix direction: Add a scoped empty-cart layout and style, preserving WooCommerce cart behavior.
- Risk: Low. Mostly presentation if scoped to cart empty state.

### UX-003 — Empty Checkout Redirect Gives Weak Context

- Area: Checkout / Cart
- URL tested: `http://localhost:8080/?page_id=9`
- User state: guest and logged-in with empty cart
- Severity: High
- Screenshot path: `/tmp/appleklinika-ux-qa/guest-checkout-empty.png`
- Steps to reproduce:
  1. Empty the cart.
  2. Open checkout URL directly.
- Expected result: A clear message explains that checkout needs products, with a strong CTA back to products.
- Actual result: User is redirected to the cart empty state, which itself is raw and does not explain the checkout redirect.
- Likely file/template/CSS area: WooCommerce checkout/cart flow; empty cart UI in `functions.php` and `assets/css/frontend.css`.
- Suggested fix direction: Keep the redirect if WooCommerce requires it, but make the destination state clear and branded.
- Risk: Low to Medium. Avoid changing checkout routing logic unless needed.

### UX-004 — 404 Page Is Effectively Blank

- Area: General system pages
- URL tested: `http://localhost:8080/?pagename=ak-qa-not-existing-page`
- User state: logged-in
- Severity: High
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-404.png`
- Steps to reproduce:
  1. Open a non-existing page URL.
- Expected result: Branded 404 page with explanation, search, category links, and CTA back to webshop.
- Actual result: Page is mostly empty between header and footer.
- Likely file/template/CSS area: missing/weak `404` handling in block templates, likely `templates/index.html` or a missing `templates/404.html`; theme helpers in `functions.php`.
- Suggested fix direction: Add a scoped 404 template or render block with Apple Klinika recovery actions.
- Risk: Low.

### UX-005 — Logged-Out My Account Login Looks Like A Raw WooCommerce Fallback

- Area: My Account / Authentication
- URL tested: `http://localhost:8080/?page_id=10`
- User state: guest
- Severity: High
- Screenshot path: `/tmp/appleklinika-ux-qa/guest-account-login-state-viewport.png`
- Steps to reproduce:
  1. Log out.
  2. Open My Account.
- Expected result: Branded Apple Klinika login page with clean account card, clear labels, red primary button, forgot password path, and helpful context.
- Actual result: Plain WooCommerce-style login panel appears, with generic styling and mixed label tone.
- Likely file/template/CSS area: WooCommerce account login template hooks in `functions.php`; account CSS in `assets/css/frontend.css`.
- Suggested fix direction: Add a scoped logged-out account/login state while preserving WooCommerce authentication.
- Risk: Medium. Login forms must keep WooCommerce names, nonces, and validation.

### UX-006 — Search No-Results State Is Sparse And Partly Untranslated

- Area: Search / Shop no-results
- URL tested: `http://localhost:8080/?s=ak-qa-no-results-zzzz&post_type=product`
- User state: guest and logged-in
- Severity: High
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-search-no-results.png`
- Steps to reproduce:
  1. Search for a term that returns no products.
- Expected result: Branded no-results state with Hungarian copy, search retry, category shortcuts, and CTA to all products.
- Actual result: Sparse message appears with generic “Search” copy and weak recovery options.
- Likely file/template/CSS area: search/no-products rendering in `templates/index.html`, `templates/page.html`, and Woo loop/no-products hooks in `functions.php`.
- Suggested fix direction: Create a shared Apple Klinika no-results component for search and shop zero-result states.
- Risk: Low.

### UX-007 — Buyback Page Shows Empty Copy While A Record Exists

- Area: My Account / Buyback / Beszámítás
- URL tested: `http://localhost:8080/?page_id=10&beszamitasaim`
- User state: logged-in demo customer
- Severity: High
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-account-buyback.png`
- Steps to reproduce:
  1. Log in as demo customer.
  2. Open Beszámítás/Eladásaim.
- Expected result: If a buyback record exists, the page should introduce the record list and not say the module is empty.
- Actual result: Page text says there is currently no separate submitted-device module, while a demo buyback record is present below.
- Likely file/template/CSS area: `appleklinika_render_account_buyback_content()` around buyback rendering in `functions.php`.
- Suggested fix direction: Make the intro copy conditional: empty message only when no records exist; record-list intro when records exist.
- Risk: Low.

### UX-008 — Buyback Record Uses Placeholder-Like Thumbnail

- Area: My Account / Buyback / Beszámítás
- URL tested: `http://localhost:8080/?page_id=10&beszamitasaim`
- User state: logged-in demo customer
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-account-buyback.png`
- Steps to reproduce:
  1. Open the demo customer buyback page.
- Expected result: Demo record should have a product/device visual or a clean neutral placeholder.
- Actual result: The record appears with a rough text placeholder instead of a polished product image state.
- Likely file/template/CSS area: buyback record rendering in `functions.php`; account record styles in `assets/css/frontend.css`.
- Suggested fix direction: Add a scoped fallback device thumbnail for buyback records without touching product media.
- Risk: Low.

### UX-009 — Favorites Page Has Weak Narrow/Mobile Stacking

- Area: My Account / Favorites
- URL tested: `http://localhost:8080/?page_id=10&kedvelt-termekek`
- User state: logged-in demo customer
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/account-favorites.png`
- Steps to reproduce:
  1. Open Favorites in a narrow browser viewport.
- Expected result: Favorites content should be prioritized and the account nav should not consume excessive vertical space.
- Actual result: The account sidebar/card takes a lot of space before the favorite list, making the page feel long and heavy.
- Likely file/template/CSS area: account layout CSS in `assets/css/frontend.css`, especially responsive rules for account nav and `.ak-account-wishlist`.
- Suggested fix direction: Add mobile-first account layout rules: compact nav, horizontal tabs, or collapsible account menu.
- Risk: Medium. Account layout is shared across endpoints.

### UX-010 — Favorites Product Row Can Feel Crowded Around CTA/Heart

- Area: My Account / Favorites
- URL tested: `http://localhost:8080/?page_id=10&kedvelt-termekek`
- User state: logged-in demo customer
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-account-favorites.png`
- Steps to reproduce:
  1. Open Favorites with several saved products.
- Expected result: Product title, chips, price, CTA, and heart button should have clear separation at all widths.
- Actual result: Product rows are mostly polished, but the CTA/heart cluster can feel tight and should be checked across widths.
- Likely file/template/CSS area: `.ak-account-wishlist__item`, `.ak-account-wishlist__actions`, `.ak-account-wishlist__link` in `assets/css/frontend.css`.
- Suggested fix direction: Continue with scoped account-favorites spacing, without changing wishlist storage or shop cards.
- Risk: Low to Medium.

### UX-011 — Cart Item Removal Is Not Clearly Discoverable

- Area: Cart
- URL tested: `http://localhost:8080/?page_id=8`
- User state: logged-in with product in cart
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-cart-current.png`
- Steps to reproduce:
  1. Add a product to cart.
  2. Open cart.
- Expected result: Customer can clearly remove an item with a visible “Eltávolítás” or trash action.
- Actual result: Quantity controls and update button are visible, but item removal is not obvious.
- Likely file/template/CSS area: cart item renderer in `functions.php`; cart item CSS in `assets/css/frontend.css`.
- Suggested fix direction: Add a clear, scoped remove action while preserving WooCommerce cart item keys and nonce behavior.
- Risk: Medium. Cart item actions must remain secure and compatible with WooCommerce.

### UX-012 — Cart Coupon Action Is Too Generic

- Area: Cart
- URL tested: `http://localhost:8080/?page_id=8`
- User state: logged-in with product in cart
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-cart-current.png`
- Steps to reproduce:
  1. Open cart with product.
  2. Inspect coupon area.
- Expected result: Coupon form should use clear Hungarian copy such as “Kupon alkalmazása”, with useful feedback states.
- Actual result: Coupon control is minimal and uses a tiny “OK” action.
- Likely file/template/CSS area: cart coupon rendering in `functions.php`; cart form CSS in `assets/css/frontend.css`.
- Suggested fix direction: Improve coupon labels and feedback styling only; keep WooCommerce coupon logic unchanged.
- Risk: Low.

### UX-013 — Some Pages Have Excessive Header-To-Content Whitespace

- Area: Header / Page shell
- URL tested: cart, account, product/category pages
- User state: guest and logged-in
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-cart-current.png`
- Steps to reproduce:
  1. Open cart or account pages on desktop.
- Expected result: Page content begins at a balanced distance under the header.
- Actual result: Several pages feel vertically loose, with large blank space before page content.
- Likely file/template/CSS area: page shell and content spacing in `assets/css/frontend.css`; header block in `parts/header.html`; account/cart wrappers in `functions.php`.
- Suggested fix direction: Audit page shell spacing by page type. Use scoped spacing tokens instead of global margin changes.
- Risk: Medium. Header and page spacing is shared across the site.

### UX-014 — Logged-In Checkout Shows Confusing Account-Save Copy

- Area: Checkout
- URL tested: `http://localhost:8080/?page_id=9`
- User state: logged-in demo customer
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-checkout-current.png`
- Steps to reproduce:
  1. Log in.
  2. Open checkout with product.
- Expected result: Since the user is already logged in, account-save prompts should be hidden or rewritten.
- Actual result: Checkout text includes account-save wording that can read as if the logged-in state is not recognized.
- Likely file/template/CSS area: WooCommerce Blocks checkout field/checkbox configuration; checkout JS/CSS in `assets/js/frontend.js` and `assets/css/frontend.css`.
- Suggested fix direction: Verify whether this is a Woo Blocks setting. Hide/reword only if it does not affect checkout state.
- Risk: Medium.

### UX-015 — WooCommerce Notices Are Still Mixed English/Hungarian

- Area: Checkout / General Woo notices
- URL tested: checkout and no-results states
- User state: guest and logged-in
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-checkout-current.png`
- Steps to reproduce:
  1. Trigger checkout payment unavailable state.
  2. Trigger search/no-results state.
- Expected result: All user-facing notices should be Hungarian and Apple Klinika styled.
- Actual result: At least one checkout notice is raw English. Search state also exposes generic English copy.
- Likely file/template/CSS area: WooCommerce translations/settings; notice styling in `assets/css/frontend.css`; checkout payment state.
- Suggested fix direction: Add translation/configuration pass for Woo notices, then style notice wrappers consistently.
- Risk: Low to Medium.

### UX-016 — Product Image Missing/Broken Signals Still Appear In Sampled Pages

- Area: Shop / Product / Media
- URL tested: shop category pages and single product pages
- User state: logged-in
- Severity: High
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-shop-iphone.png`
- Steps to reproduce:
  1. Open shop/category pages.
  2. Open a single product page.
  3. Inspect product images and thumbnails.
- Expected result: Product and gallery images should either load correctly or show an intentional fallback.
- Actual result: QA script detected missing/broken-image signals on sampled shop/product pages. Some may be lazy-loading related, but this still needs visual/media audit.
- Likely file/template/CSS area: product image assignment/media library; product card renderer in `functions.php`; single product gallery renderer; CSS in `assets/css/frontend.css`.
- Suggested fix direction: Audit image `src`, `srcset`, attachment IDs, lazy-load behavior, and fallback images by product type.
- Risk: Medium. Do not change product images in bulk without approval.

### UX-017 — Sort Copy Is Phone-Specific On Non-Phone Categories

- Area: Shop / Category pages
- URL tested: MacBook, iPad, Apple Watch category URLs
- User state: logged-in
- Severity: Low
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-shop-iphone.png`
- Steps to reproduce:
  1. Open non-iPhone categories.
  2. Inspect sort/filter copy.
- Expected result: Sort labels should be generic across product families.
- Actual result: Copy such as “Akciós telefonok elöl” appears even when the user is viewing MacBook/iPad/Watch.
- Likely file/template/CSS area: shop filter/sort labels in `functions.php`.
- Suggested fix direction: Rename to generic “Akciós termékek elöl” or category-aware copy.
- Risk: Low.

### UX-018 — Single Product Gallery Needs Missing-Image/Fallback QA

- Area: Single product
- URL tested: `http://localhost:8080/?product=selector-teszt-iphone-13-pro-1-tb-alpesi-zold-a`
- User state: logged-in
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-product-iphone.png`
- Steps to reproduce:
  1. Open a single product page.
  2. Inspect gallery, thumbnails, and zoom controls.
- Expected result: Gallery should show consistent product image presentation and graceful fallback if an image is missing.
- Actual result: One missing/broken-image signal was detected in the sampled product page. The gallery controls are functional but still sensitive to media quality.
- Likely file/template/CSS area: single product gallery renderer in `functions.php`; gallery JS in `assets/js/frontend.js`; gallery CSS in `assets/css/frontend.css`.
- Suggested fix direction: Add defensive fallback for missing attachment images and retest gallery on iPhone, MacBook, iPad, Watch products.
- Risk: Medium.

### UX-019 — Shop Filter No-Results State Needs Dedicated Styled Path

- Area: Shop / Filters
- URL tested: search no-results and category pages
- User state: guest and logged-in
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-search-no-results.png`
- Steps to reproduce:
  1. Use a search or filter combination that returns no products.
- Expected result: Empty state explains no matching products and offers filter reset/category/shop actions.
- Actual result: Search no-results is sparse; filter no-results should not share the same raw fallback.
- Likely file/template/CSS area: shop filtering/no-products hook in `functions.php`; no-results template styles in `assets/css/frontend.css`.
- Suggested fix direction: Build one shared no-products component with filter reset support.
- Risk: Low to Medium.

### UX-020 — Footer Dominates Sparse Error/Empty Pages

- Area: General system pages
- URL tested: 404, search no-results, empty cart
- User state: guest and logged-in
- Severity: Low
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-404.png`
- Steps to reproduce:
  1. Open sparse system pages.
- Expected result: The main empty/error content should feel complete before the footer appears.
- Actual result: Footer appears immediately after weak/empty content, making pages feel unfinished.
- Likely file/template/CSS area: `parts/footer.html`, page templates, empty-state wrappers in `functions.php` and `assets/css/frontend.css`.
- Suggested fix direction: Fix the main empty/error states first; footer spacing may then be fine without direct footer changes.
- Risk: Low.

### UX-021 — Account Endpoint Content Depth Is Uneven

- Area: My Account
- URL tested: dashboard, orders, buyback, warranty, returns, settings, favorites
- User state: logged-in demo customer
- Severity: Medium
- Screenshot path: `/tmp/appleklinika-ux-qa/desktop-account-dashboard.png`
- Steps to reproduce:
  1. Log in as the demo customer.
  2. Click through account endpoints.
- Expected result: Each endpoint should have a clear populated and empty state with consistent card density and actions.
- Actual result: Dashboard/settings/favorites are stronger than buyback/warranty/returns empty or semi-empty states. Some endpoint copy still feels placeholder-like.
- Likely file/template/CSS area: account render helpers in `functions.php`; account styles in `assets/css/frontend.css`.
- Suggested fix direction: Treat account endpoints as a phased content system: first correct copy/state logic, then visual consistency.
- Risk: Medium.

