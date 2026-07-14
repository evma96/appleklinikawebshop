# Apple Klinika UX Regression Audit V2

Date: 2026-07-14  
Baseline: `docs/qa/ux-defect-backlog.md`  
Scope: browser-based regression QA only; no frontend, backend, product, order, or customer-data changes.

## Executive summary

All 21 baseline issues (`UX-001` to `UX-021`) were retested against the current local webshop.

| Result | Count |
|---|---:|
| Fixed | 13 |
| Still open | 2 |
| Partially fixed | 6 |
| Regressed | 0 |
| New issues | 0 |
| Blocked / not retested | 0 |

The largest improvement is that the main purchase and account entry flows are no longer blocked: a local bank-transfer payment method is visible, the checkout reaches the real summary/place-order step, the logged-out account page is branded, the demo customer can sign in, and the formerly raw empty/error states are now usable and Hungarian.

The audit did not create a test order. The real `Megrendelés` button was reached, but clicking it would have changed order data, which was outside this QA-only task. Guest checkout with a filled cart was therefore not completed end to end either.

## Test coverage

### User states

- Guest / logged-out visitor: tested.
- Invalid login: tested.
- Demo customer `tesztvasarlo@appleklinika.local`: successful login and account endpoints tested.
- Admin session: not available in the audit browser; no admin-only checks were required for the baseline issues.

### Viewports

- Primary desktop viewport: 1440 px wide.
- Narrow checks: 390-444 px wide for favorites and filled cart.
- A final account re-login check also ran in the user's current narrow in-app browser viewport.

### Pages and flows exercised

- Homepage.
- iPhone, MacBook, iPad, and Apple Watch archive pages.
- One single-product page from each device family.
- Empty and filled cart.
- Empty checkout redirect and filled checkout Steps 2, 3, and 4.
- Logged-out account, invalid login, successful demo-customer login.
- Dashboard, purchases/orders, buyback, warranties, returns, settings, and favorites.
- 404, general search no-results, product search no-results, and impossible shop-filter state.

No console errors were captured on the sampled pages.

## Baseline issue status

| ID | Area | Previous severity | Current status | Current severity | URL tested | Current observation | Screenshot | Recommended next action |
|---|---|---:|---|---:|---|---|---|---|
| UX-001 | Checkout payment | Critical | **FIXED** | Low | `/?page_id=9` | Step 3 shows selectable `Banki átutalás (helyi teszt)` and Step 4 exposes the real `Megrendelés` button. No production gateway or secret is involved. Order creation itself was not executed in this QA-only pass. | `/tmp/appleklinika-ux-qa-v2/desktop-checkout-step3-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-checkout-step4-current.png` | Run one separately authorized local order-placement smoke test before release. |
| UX-002 | Empty cart | High | **FIXED** | – | `/?page_id=8` | Branded Hungarian empty state, clear explanation, category links, and product browsing CTA are visible. | `/tmp/appleklinika-ux-qa-v2/desktop-cart-current.png` | Preserve this state while changing filled-cart UI. |
| UX-003 | Empty checkout | High | **FIXED** | – | `/?page_id=9` → `/?page_id=8` | Empty checkout redirects safely to the branded empty-cart state and explains that a product is required. | `/tmp/appleklinika-ux-qa-v2/desktop-checkout-current.png` | No change recommended. |
| UX-004 | 404 | High | **FIXED** | – | `/this-page-does-not-exist-qa-v2` | Branded 404 with Hungarian heading, search, shop CTA, and device-category shortcuts. | `/tmp/appleklinika-ux-qa-v2/ux004-404-current.png` | No change recommended. |
| UX-005 | Logged-out account/login | High | **FIXED** | – | `/?page_id=10` | Branded login card is shown; invalid email produces a styled Hungarian Woo notice; forgot-password link is present; demo-customer login succeeds. Registration is not faked when disabled. | `/tmp/appleklinika-ux-qa-v2/ux005-guest-login-current.png`, `/tmp/appleklinika-ux-qa-v2/ux005-invalid-login-current.png`, `/tmp/appleklinika-ux-qa-v2/ux005-demo-login-result.png` | Preserve Woo field names, nonce, and authentication flow. |
| UX-006 | Search no-results | High | **FIXED** | – | `/?s=...` and `/?s=...&post_type=product` | Both general and product-search no-results pages are branded, Hungarian, and provide search and category/shop recovery paths. | `/tmp/appleklinika-ux-qa-v2/ux006-general-search-no-results-current.png`, `/tmp/appleklinika-ux-qa-v2/ux006-product-search-no-results-current.png` | No change recommended. |
| UX-007 | Buyback contradictory copy | High | **FIXED** | – | `/?page_id=10&beszamitasaim` | The intro now correctly describes the populated record list; the demo iPhone buyback record appears without the previous contradictory empty/module copy. | `/tmp/appleklinika-ux-qa-v2/desktop-account-buyback-current.png`, `/tmp/appleklinika-ux-qa-v2/account-buyback-relogin-2026-07-14.png` | Keep the copy conditional on record count. |
| UX-008 | Buyback thumbnail/fallback | Medium | **PARTIALLY FIXED** | Medium | `/?page_id=10&beszamitasaim` | The record is readable and populated, but the device visual is still an `iP` initials-style fallback rather than a convincing device thumbnail. | `/tmp/appleklinika-ux-qa-v2/desktop-account-buyback-current.png` | Add a safe category/device fallback image without changing the data source. |
| UX-009 | Favorites narrow layout | Medium | **PARTIALLY FIXED** | Medium | `/?page_id=10&kedvelt-termekek` | Desktop rows are much more stable and the 390 px view remains usable, but the account shell and content are still vertically dense on narrow screens. | `/tmp/appleklinika-ux-qa-v2/desktop-account-favorites-current.png`, `/tmp/appleklinika-ux-qa-v2/narrow-account-favorites-current.png` | Do a mobile-only density pass for the account shell and wishlist rows. |
| UX-010 | Favorites CTA/heart crowding | Medium | **FIXED** | – | `/?page_id=10&kedvelt-termekek` | `Megnézem` and the heart control have stable separate positions; titles, chips, price, CTA, and remove control no longer visibly collide in the sampled desktop view. | `/tmp/appleklinika-ux-qa-v2/desktop-account-favorites-current.png` | Preserve current row geometry; test remove behavior in a data-mutation-approved pass. |
| UX-011 | Cart remove discoverability | Medium | **FIXED** | – | `/?page_id=8` | Every filled-cart row now has a visible, keyboard-focusable `Eltávolítás` link backed by WooCommerce's cart-item key and nonce-protected remove URL. Removing one of two items kept the other item, recalculated totals, and produced no nonce error. | Browser QA at 1440×1000 and 390×844 | Preserve the scoped link and Woo remove URL behavior. |
| UX-012 | Coupon action copy | Medium | **FIXED** | – | `/?page_id=8` | The existing coupon form now labels its submit action `Kupon alkalmazása`; its field name, submit name/value, validation path, and calculation logic are unchanged. | Browser QA at 1440×1000 and 390×844 | No change recommended. |
| UX-013 | Header-to-content whitespace | Medium | **PARTIALLY FIXED** | Low | Homepage, account, cart, checkout | Main pages are coherent, but some account/checkout states still use generous vertical gaps before the primary content. This is now polish rather than a broken layout. | `/tmp/appleklinika-ux-qa-v2/desktop-account-dashboard-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-checkout-filled-current.png` | Tune spacing per page type; avoid global header/grid changes. |
| UX-014 | Logged-in checkout account-save wording | Medium | **STILL OPEN** | Medium | `/?page_id=9` Step 2 | Logged-in demo customer still sees `Adatok mentése a fiókomba a következő vásárláshoz`, which reads like a guest/account-creation option and is confusing. | `/tmp/appleklinika-ux-qa-v2/desktop-checkout-filled-current.png` | Make the copy/state account-aware without changing Woo validation or Store API behavior. |
| UX-015 | Mixed English/Hungarian Woo text | Medium | **PARTIALLY FIXED** | Medium | Cart, checkout Step 3, favorites | Most notices are Hungarian, but `Free shipping` remains visible in cart/checkout. Wishlist price accessibility text also exposes `Original price was` / `Current price is` in the rendered text layer. | `/tmp/appleklinika-ux-qa-v2/desktop-checkout-step3-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-cart-filled-current.png` | Translate remaining Woo strings through supported filters/translation, including accessible price text. |
| UX-016 | Missing/broken product image signals | High | **FIXED** | – | Homepage, four archives, four product pages, account favorites | No persistent broken product images were detected in the sampled homepage, archives, single-product pages, or favorites. A transient late image load on favorites resolved immediately and was not reproducible. | `/tmp/appleklinika-ux-qa-v2/desktop-home-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-product-iphone-current.png` | Keep image normalization isolated from archive-card sources. |
| UX-017 | Phone-specific sort copy | Low | **STILL OPEN** | Low | `/?post_type=product&ak_type=macbook`, `ipad`, `apple_watch` | MacBook, iPad, and Apple Watch archives still show `Akciós telefonok elöl`. | `/tmp/appleklinika-ux-qa-v2/desktop-shop-macbook-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-shop-ipad-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-shop-watch-current.png` | Replace with category-neutral `Akciós termékek elöl`. |
| UX-018 | Single-product gallery/fallback QA | Medium | **PARTIALLY FIXED** | Low | Four sampled product URLs | Main gallery images load for iPhone, MacBook, iPad, and Watch; add-to-cart and `Mutass többet` data controls are present. Hover zoom/lightbox and missing-image fallback were not exhaustively interaction-tested in this pass. | `/tmp/appleklinika-ux-qa-v2/desktop-product-iphone-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-product-macbook-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-product-ipad-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-product-watch-current.png` | Run a focused gallery interaction/fallback matrix before declaring fully fixed. |
| UX-019 | Shop filter no-results | Medium | **FIXED** | – | Impossible archive filter state | Branded Hungarian no-results state provides `Szűrők törlése`, all-products, and category recovery paths. | `/tmp/appleklinika-ux-qa-v2/ux019-shop-filter-no-results-current.png` | No change recommended. |
| UX-020 | Footer dominates sparse pages | Low | **FIXED** | – | Empty cart, 404, search/filter no-results | Sparse system pages now have purposeful main content and recovery actions before the footer. | `/tmp/appleklinika-ux-qa-v2/ux004-404-current.png`, `/tmp/appleklinika-ux-qa-v2/ux006-product-search-no-results-current.png` | Preserve the current empty-state minimum content depth. |
| UX-021 | Account endpoint depth/consistency | Medium | **PARTIALLY FIXED** | Low | All visible account endpoints | Dashboard, purchases, buyback, warranty, returns, settings, and favorites now expose meaningful real/demo-backed content. Depth is much better, but visual/content richness remains uneven and the buyback thumbnail/favorites narrow state still lag behind the strongest endpoints. | `/tmp/appleklinika-ux-qa-v2/desktop-account-dashboard-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-account-orders-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-account-warranty-current.png`, `/tmp/appleklinika-ux-qa-v2/desktop-account-returns-current.png` | Normalize endpoint headings, empty/populated states, and media treatment in a separate account-only batch. |

## New issues found

No new independent regression was confirmed. Newly observed details are already covered by existing baseline items:

- English shipping and price accessibility strings are tracked under `UX-015`.
- The weak buyback device visual is tracked under `UX-008`.
- Remaining narrow account density is tracked under `UX-009` and `UX-021`.

## Top remaining fixes

1. `UX-014` — Correct the logged-in checkout account-save wording/state.
2. `UX-015` — Remove remaining English Woo strings, including `Free shipping`.
3. `UX-017` — Replace phone-specific sorting text on MacBook, iPad, and Watch archives.
4. `UX-008` — Replace the buyback initials placeholder with a useful device/category fallback.
5. `UX-021` — Normalize account endpoint content depth and presentation.
6. `UX-009` — Improve narrow favorites/account density.
7. `UX-013` — Tune remaining page-specific header-to-content whitespace.
8. `UX-018` — Complete focused gallery zoom/lightbox and missing-image fallback QA.

## Quick wins

- `UX-017`: use category-neutral sort wording.
- `UX-015`: translate the visible shipping label and Woo price accessibility strings.
- `UX-014`: hide or rewrite the account-save option for authenticated customers.

## Bigger tasks

- `UX-021` / `UX-009`: account endpoint consistency and narrow-layout pass.
- `UX-018`: systematic product-gallery interaction and fallback matrix across all device families.
- Local checkout end-to-end smoke test with explicit permission to create and then clean up a test order.

## Do-not-touch warnings

- Do not redesign the checkout stepper, persistent summary, company fields, shipping/payment internals, or the real place-order button while fixing copy issues.
- Do not change the shared shop/homepage product-card renderer to address account wishlist rows.
- Do not run another broad product-image rewrite; sampled product/archive imagery is currently stable.
- Do not replace the branded empty cart, 404, search no-results, or filter no-results markup with raw Woo fallbacks.
- Do not alter Woo login field names, nonce, validation, or authentication hooks; the branded guest login now works.
- Do not remove the local bank-transfer gateway until an equivalent QA-safe payment path exists.
- Keep the buyback record source and conditional intro logic intact; only its media fallback needs polish.

## Verification limitations

- No order was placed because the task prohibited order/data changes.
- Guest filled-cart checkout was not completed end to end for the same reason.
- Gallery hover zoom/lightbox and forced missing-image fallback were not exhaustively exercised.
- The late account re-login check was performed successfully, but the user's current in-app browser viewport was narrow; the earlier 1440 px desktop captures remain the desktop evidence source.
