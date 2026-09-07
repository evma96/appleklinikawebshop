# Product selector purchase lifecycle

## Contract and implementation

The selected physical product must expose the same purchase state as a fresh page
load: WooCommerce decides form presence, stock text, purchasability, minimum,
maximum and sold-individually/backorder behavior. No parallel stock rule exists
in the browser.

`ProductFrontendDisplay::renderPurchaseArea()` is used by both the initial buy
panel and each selector payload's `purchaseHtml`. It invokes Woo's type-specific
add-to-cart template in the selected product context and restores the previous
global product in `finally`. Selection replaces the existing purchase area,
rather than just changing an old button ID. The stable product shell delegates
form submissions, so created/replaced forms retain the existing Woo AJAX cart
flow without accumulating handlers. The current battery option is reapplied by
the existing battery synchronization. Gallery, prices, inventory rules, cart
semantics and checkout are not changed.

## Reproducible LOCAL regression

`tests/product-purchase-browser.cjs` uses native clicks, fresh browser contexts
and existing local same-model products: #366 (out), #318 (in), #334 (in).
The stock preconditions are checked; the runner never changes them. Update these
explicit fixture IDs deliberately if the local catalogue changes.

Direct server page loads are the semantic oracle. At 1440px and 390px the same
script tests OUT → IN → OUT → IN → different IN → OUT → IN, starting both from
an absent and an existing form. It compares form/button counts, physical ID,
action, stock text, quantity min/max/default, and battery state, alongside title,
price and gallery coherence. Only after all transitions pass does it perform one
isolated real add of #334, verify the actual Woo cart item/quantity/limit and
remove it. No checkout or order is created. Failed transition baselines perform
no cart writes. An interrupted cart run retains an ignored recovery storage-state
file; successful cleanup removes it.

On untouched `47e8d950f92247de30c02d1ebfddc36003dd74e4`, this regression exited 1:
84 checks, 10 failed comparisons, both viewport sizes. The unchanged script
then exited 0 after the fix: 90 checks (including the success-only cart checks).
Test SHA-256:
`19074206ac02b8b7053c1a317458c816bbf17bcc3231c0edc0258d2127d0d941`.

`tests/product-purchase-render.php` uses unsaved Woo product objects and asserts
canonical direct/payload markup parity, stock maxima 5 and 2, out-of-stock,
backorders, sold-individually and non-purchasable states, unchanged product data,
and global context restoration including a throwing template hook: 68 checks.

Run with the feature's plugin mounted into LOCAL WordPress:

```sh
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/appleklinika-inventory/tests/product-purchase-render.php
EVIDENCE_DIR=/tmp/product-purchase-proof node wordpress/wp-content/plugins/appleklinika-inventory/tests/product-purchase-browser.cjs
make test-inventory-product-frontend test-theme-storefront test-theme-catalog-search test-cart-checkout
```

`PLAYWRIGHT_MODULE` and `CHROME_EXECUTABLE` may point to an existing installed
runtime. `BASE_URL` is restricted to localhost. Evidence stays outside Git.
The known LOCAL favicon 404 is reported separately, not suppressed together
with application errors. This is LOCAL verification, not deployment approval.

Final focused verification: purchase renderer 68, native purchase lifecycle 90,
inventory catalogue 185, frontend structure 3, storefront 18, catalogue/search
45, cart/checkout 56, product-information PHP 49, gallery PHP 26,
product-information browser 507 and gallery browser 237: **1,284 checks passed**.
Changed PHP files, the runtime runner and the actual page's eight inline scripts
passed syntax validation; `git diff --check` passed. Required `make test` and
`make quality` returned success but remain generic placeholders, not additional
test coverage. Real-photo before/after purchase screenshots were opened and
reviewed. Local products #288/#318/#334/#366 retained their stock baselines;
the isolated cart round-trips ended empty and never opened checkout.
