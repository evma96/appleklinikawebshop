# LOCAL trust, Watch storage and protection candidate

Date: 2026-10-26. Branch: `feature/local-trust-watch-protection`.
Base: `eecad886bcdcb7b6187af715bc2ff18371167f44`.
Scope: LOCAL only, uncommitted, no integration/push/deploy or TEST access.

## Delivered presentation

The lightweight benefit list is the first sibling after the homepage hero, before
categories. It uses existing shield/tools/return/truck SVGs and the four exact
owner-provided claims. Four columns on desktop, two on mobile. Hero behavior and
Google Reviews UI/fixtures are unchanged.

Eight existing LOCAL Watch products store `32_gb`. Both the theme's
`appleklinika_storage_label()` and Inventory's `storageLabel()` omitted that key
and returned it raw. Adding only the exact `32_gb` display mapping fixes cards,
search/category results, selectors and specifications. No product values or
catalog choices were rewritten; unknown values still retain their existing fallback.

## Protection architecture and administration

**APPROVED FOR INTEGRATION — LOCAL implementation**: ordinary hidden Woo simple products,
ordinary separate cart/order lines, no second order/inventory database, no new
invoice transformation or payment/fulfilment hooks. The owner approved the architecture and required the final parent-association gate,
which is now verified. No integration/commit/deployment is authorized in this task.

| LOCAL product ID | SKU suffix (`ak-protection-`) | Name | Price HUF | Slot |
|---|---|---|---:|---|
| 2471 | glass-standard | Közepes üvegfólia | 4000 | glass |
| 2472 | glass-premium | Prémium üvegfólia | 7000 | glass |
| 2473 | case-standard | Egyszerű tok | 3000 | case |
| 2474 | case-premium | Prémium tok | 8000 | case |
| 2475 | camera | Kamera védő | 5000 | camera |
| 2476 | adapter-20w | 20 W gyorstöltő adapter | unset | adapter |

These are intentional LOCAL feature configuration, retained for review, not QA
order artifacts. No legitimate existing adapter/product price was found. Its option
is disabled, with no invented amount. The helper `scripts/provision-protection.php`
is CLI-only with an explicit `--target=local` or `--target=test` guard. TEST requires
staging plus the exact teszt.appleklinika.com home host; production is denied. It
prints a plan unless `--apply` is supplied and is never an automatic activation or
deployment migration. Reruns preserve existing SKU configuration and prices.

Woo admin: **Products → edit the named hidden product → Product data → General**.
Use ordinary regular/sale price fields; Inventory controls stock, backorders and
sold-individually behavior normally. Additional General fields configure the
protection slot, eligible device types, and optional comma-separated existing
Apple Klinika model keys. This model list narrows the selected types; empty means
all models of the explicitly selected type. Only real verified compatibility should
be configured. LOCAL initially uses the requested iPhone type mapping, with no
invented per-model table. iPad, Watch and MacBook options remain absent.

A configured protection product is kept hidden on admin save. Native Woo visibility
excludes it from normal searches/listings; the four custom homepage queries now
also explicitly request catalog-visible products. If multiple camera or adapter
products match one device, that independent slot is unavailable until its mapping
is made unambiguous. This avoids silently discarding an extra checked option.

## Cart, pricing and safety

The block uses the native before-add-to-cart-button hook inside Woo's form; the
existing device selector includes the same renderer in its replacement form.
Glass and case each default to `Nincs`, with mutually exclusive radio choices;
camera and adapter are independent checkboxes. No extra is preselected. The block
is responsive, has labeled keyboard-operable controls, and does not change device
price or device selector logic.

Domain selection validates slots and single IDs. The application handler resolves
eligibility and current availability from the server-side Woo catalog. The frontend
adapter checks a product-specific nonce. Neither client prices nor arbitrary IDs
are trusted. Empty, unpriced, wrong-slot, wrong-type/model or unavailable choices
cannot be silently purchased through this selector.

Each chosen extra adds one normal Woo line unit, independent of device quantity.
Identical accessory products merge only for the same native parent cart key, using
native Woo quantities. Different parent devices always retain separate extra lines. Normal cart
controls change/remove extras and calculate totals. Woo stock validation remains
in charge. The cart adapter validates before adding, revalidates immediately before
append, and restores the pre-request cart if any accessory addition fails, including
failure after another extra has already been added. Woo's own session API also
restores the logged-in persistent cart; otherwise earlier native add hooks could
retain a partial cart across sessions. A consumed cart operation does
not append again. Payment callbacks, invoices, fulfilment transitions and emails do
not own or create any accessory line.

The Back Office already reads all normal product lines; it now also renders Woo's
formatted line subtotal. Device metadata remains optional for accessory items.
Prices/stock/taxes come from native Woo. Current LOCAL taxes are disabled, so the
listed prices and tested invoice gross amounts match directly. This task does not
change tax/payment/shipping settings.

## Verification and its limits

- Protection LOCAL integration: **101 assertions**. Native Woo cart, temporary QA
  device, memory-only order/session. Covers no extras, glass, case, combined choice,
  ineligible types/models, malformed/forged choices, missing price, real Woo price
  edits, independent quantities, removal, native stock rejection, early/late rollback,
  logged-in persistent-cart restoration, consumed-action replay and ambiguous
  configuration. The persistent-cart test intercepts metadata writes for an in-memory
  identity; no actual user is created or modified. QA device deleted in `finally`.
- Trust/Watch LOCAL checks: **36 assertions**, including all eight affected Watch
  products, no metadata writes, exact benefit claims and placement.
- Existing purchase renderer: **68**; product information presentation: **49**;
  frontend structure: **3**; model/storage: **185**; homepage: **112**;
  catalog/search: **45** assertions.
- `make test`: **388 assertions** across offline lifecycle, Back Office, provider
  adapter contracts, security and existing legal-PDF tests, plus PHP syntax checks.
  No legal file was changed. `test-unit`/`test-integration` remain placeholder targets.
- Original batch: **987 passing assertions**, plus manual browser verification.
  The association follow-up below records the current rerun and added checks. `make quality`
  exits successfully but still reports that lint/static-analysis tools are not configured;
  it is not claimed as a real static-analysis pass. New PHP sources were syntax checked.
- Existing homepage test emits an already-defined LOCAL constant warning during
  bootstrap; its 112 assertions pass. No customer-page PHP/JS failure was observed.

The order simulation uses actual unsaved `WC_Order` / `WC_Order_Item_Product`
objects with four lines, including two glass units. Native checkout summary and
My Account order-item templates, Back Office detail renderer, and paid-order email
HTML/plain output preserve product names, quantities and prices. This is LOCAL
rendering evidence, not inbox delivery or a new provider E2E claim.

Invoice proof executes only the installed Számlázz.hu plugin's existing product-line
XML mapping block in memory. It verifies four names, quantities and amounts. It
does **not** call `generate_invoice`, load an authentication XML block, modify any
license check, generate a PDF, or invoke transport. HTTP and mail attempt counters
both remain zero. Barion, GLS, invoice and notification lifecycle source is unchanged.

Browser: homepage and protection at **1440, 1280, 390, 320**; no horizontal overflow.
Watch category/detail/search desktop/mobile: `32 GB` displayed, no `32_gb` leak,
no phone-specific extras. Existing storage switching updates the purchase form with
one protection block and no selected extras. A real browser cart showed separate
lines, a quantity edit, matching checkout summary, and correct removal totals.
The adapter remained disabled and hidden extras produced no catalog search results.
No new console warnings/errors were recorded.

## Cleanup and retained state

The browser checkout opened Woo's automatic LOCAL draft **2481**, containing only
the QA cart and no email/payment/invoice/tracking. It was removed through Woo CRUD.
The cart was emptied through native controls. No new order remains, no QA user was
created, and temporary QA device products were deleted. The six intentional LOCAL
accessory products remain for Martin's review.

Original 122 product records and seven selected operational/home/contact/payment
settings retain their pre-task hashes. The original six user IDs are unchanged; no
real account was modified. No original order has a modification timestamp
during the task; the original set of 162 order IDs remains. Raw serialized Woo order
objects were not used as final equality evidence because they include runtime object
state. No main, TEST, production, legal source or Google Reviews data was modified.

Screenshots are outside Git in the task artifact directory:
`/Users/apple/.codex/visualizations/2026/09/11/01a08fcb-a363-7761-acb7-46d99e2bde2c/local-trust-watch-protection-20261026/`.
Files include `home-{1440,1280,390,320}.png`,
`protection-{1440,1280,390,320}.png`, `cart-1440.png`, `cart-320.png`,
`checkout-1440.png`, `checkout-summary-390.png`, `watch-category-1440.png`,
`watch-detail-{1440,390}.png`, and `watch-search-390.png`.

## Exact source/documentation files changed

- `Makefile`
- `README.md`
- `deficiencies.md`
- `docs/local-trust-watch-protection.md`
- `scripts/provision-protection.php`
- `wordpress/wp-content/themes/appleklinika-theme/inc/homepage-presentation.php`
- `wordpress/wp-content/themes/appleklinika-theme/assets/css/homepage.css`
- `wordpress/wp-content/themes/appleklinika-theme/functions.php`
- `wordpress/wp-content/themes/appleklinika-theme/assets/js/frontend.js`
- `wordpress/wp-content/plugins/appleklinika-inventory/appleklinika-inventory.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/assets/product-protection.css`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Domain/Protection/ProtectionSelection.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Application/Protection/ProtectionCatalog.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Application/Protection/SelectProtection.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/WooProtectionCatalog.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/WooProtectionCart.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/WooProtectionAssociation.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Admin/ProductProtectionFields.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Frontend/ProductProtection.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Frontend/ProductFrontendDisplay.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/tests/protection-local.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/tests/protection-association-local.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/tests/trust-watch-local.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/src/Infrastructure/WooOrderBackOfficeRepository.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/src/Interfaces/BackOfficeRouter.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/tests/repository-operations.php`

Remaining action: in a separately authorized integration
step, carry only these scoped changes onto the current canonical develop baseline,
configure real accessory compatibility/stock/images and adapter price as appropriate,
and perform focused TEST checks. Do not deploy the older LOCAL base over newer
security/order lifecycle work. No such integration is part of this task.

## Parent association follow-up — 2026-10-26

The initial implementation merged identical extra products regardless of their
main device. This was a genuine edge case and is now fixed without changing the
approved architecture or payment/invoice/GLS/email/fulfilment lifecycle.

- Accessory cart metadata `_ak_protection_parent_key` contains the exact native
  parent cart item key and participates in Woo's own cart-key generation. Two
  iPhones with the same premium case produce four distinct cart/order lines.
- During standard Woo checkout item creation (also used by Store API), related
  lines receive `_ak_protection_line_key`; accessories retain the parent key and
  a public `Készülék` name snapshot. Within each order the hidden keys resolve the
  exact parent item even after product names or catalog data change.
- Native formatted item metadata displays this snapshot in My Account and Woo
  admin. The existing custom cart/checkout summary and Back Office renderer show
  the same association. The checkout JavaScript only renders the existing native
  Store API `item_data` value; it does not change checkout/payment behavior.
- Removing an accessory never removes its device. Removing a device prunes only
  its linked extras through native Woo removal methods and recalculates totals.
  Accessory undo is allowed when its parent is present. Undo/session restoration
  cannot leave an orphan; order-item creation rejects a missing parent as well.
- One explicit add adds one extra unit. Deliberately adding the same device again
  uses the same associated accessory line and increments its quantity normally;
  it does not create stray new rows. Replaying the already-consumed handler changes
  nothing. Stock checks aggregate the same product across distinct parent keys.
- Szamlazz.hu already reads ordinary visible item metadata into its item note.
  The offline mapping proves `Készülék: <device name>` on each case line, preserving
  names, quantities and amounts. Internal cart keys stay hidden. No vendor source,
  invoice pipeline or settings changed. A future `hide_item_notes=yes` configuration
  would suppress these notes using the plugin's own setting; it is not overridden.

This follow-up freshly passed **715 assertions**: protection 101, association 50,
cart/checkout 56, purchase renderer 68, product presentation 49, frontend structure 3,
existing lifecycle/security/legal regression suite 388. PHP syntax checks passed.
`make quality` reports no configured linter/static analyzer, as before.

The association test saved/reloaded a real unpaid LOCAL Woo draft and then deleted
it and both QA devices via supported CRUD in `finally`. No real user was created.
HTTP/mail attempt counters stayed zero. The original protection test now uses the
actual checkout line-item creation API instead of hand-assembling order items.

Browser checks at 1280 and 390 verified two clearly labeled case lines, correct
520980 HUF total, independent removal to 512980 HUF, and device-plus-linked-case
removal leaving the other device at 226990 HUF. Mobile cart/checkout labels wrap
without overflow. No console errors. Browser cart emptied and its identified
unpaid/email-empty draft #2491 deleted through Woo CRUD. Unrelated draft #2485,
which was not created by these tests, was explicitly preserved.

Evidence added beside the original screenshots: `association-cart-1280.png`,
`association-cart-390.png`, `association-checkout-390.png`.

Result: **APPROVED FOR INTEGRATION**. The 20 W adapter remains unavailable without
a legitimate Woo price. LOCAL only; no commit, push, merge, TEST or deployment.

## Approved checkpoint/integration scope — 2026-10-26

The owner authorized checkpointing this complete package, transferring only its
approved delta onto current develop, a normal push and exact-SHA TEST deployment.
Older LOCAL-only statements above are historical. No main/production deployment.
Provision TEST products explicitly through `scripts/provision-protection.php
--target=test --apply`; stable SKUs are the six `ak-protection-*` values above, never
LOCAL numeric IDs. Existing matching hidden published products retain all admin
prices, stock and compatibility. Duplicate/unexpected identities stop before writes.
Rerun the command to prove reuse. Normal releases do not provision or reset prices.

Pre-checkpoint rerun: **753 assertions** (388 common regression, 101 protection,
52 association including rendered email/combined accessories, 36 trust/Watch,
68 purchase, 49 presentation, 3 structure, 56 cart/checkout). Provisioning was run
twice locally, reusing all six SKUs with no configuration changes. TEST verification
of the association script requires an explicit CLI target, named staging host,
external HTTP block and disabled direct socket/cURL functions. It does not modify
existing TEST accessory stock; QA devices/orders are cleaned through Woo CRUD.

## 2026-10-26 — Extra protection visual revision, LOCAL only

Branch: `feature/local-protection-visual-layout`; baseline HEAD
`0841b1ff7eab5d0f81ac17cdddd84d1708fe0fe6`. The functional implementation is retained;
the previous narrow three-column protection UI was rejected. This revision awaits
Martin's screenshot-based visual approval and is not committed or deployed.

The purchase panel now uses full-width option rows with a consistent border/radius,
left-aligned names, right-aligned Woo price increments and a restrained red selected
state. The extra nested outer box is removed. `Nincs` remains the quiet default for
glass/case; no paid accessory is preselected. Native labeled inputs remain keyboard
operable with a visible focus outline, and the whole label is clickable. Touch targets
are at least 44 px high; option text stays 15 px and prices 14 px on mobile. At 320 px,
long glass names wrap naturally while prices remain aligned. The unpriced adapter
remains a disabled input, displayed as a calm unavailable row without a misleading
checkbox. The block remains immediately before Add to Cart.

Only products rendering this block receive a desktop purchase-column increase from
410 to 450 px and modest panel spacing adjustments. The existing stacked breakpoint
is preserved. The image, configuration selectors and other product content are not
reimplemented. The Google Reviews source/styles/tests/documentation/private fixture
were fingerprinted before this task and remain byte-identical afterward. Its earlier
uncommitted work is retained separately.

Application code changes are limited to:
- `assets/product-protection.css`
- `src/Interfaces/Frontend/ProductProtection.php`: render/option markup only.

Registration, validation, nonce, input names/values, disabled flags, Woo prices, cart
commands, device association, removal, stock and provider/email lifecycle code are
unchanged. README and deficiencies record this presentation status.

Verification: **153 existing LOCAL checks PASS** (101 protection + 52 association).
They cover native prices/stock, exclusive groups, independent camera protection,
unavailable adapter, multiple-device relationships, removal/orphans, Back Office,
My Account/email output and offline invoice-line mapping. The scripts report no
provider or email attempts and successful cleanup of their QA devices/draft/order
items. Browser interaction changes only unsaved selector state, with no cart/order
submission.

Actual full product-page/hero screenshots were personally inspected at
1440, 1280, 390 and 320 px. No document horizontal overflow or captured browser
console warning/error. Whole-row clicks switch the exclusive choices correctly;
keyboard Space independently toggles camera protection and exposes the focus ring.
The browser was returned to `Nincs`/`Nincs`, camera unchecked, adapter disabled.
PHP syntax and diff whitespace checks pass.

Evidence outside Git:
`/Users/apple/.codex/visualizations/2026/09/11/01a08fcb-a363-7761-acb7-46d99e2bde2c/local-protection-visual-20261026/`

No commit, push, merge, TEST access, deployment, production or main change.
