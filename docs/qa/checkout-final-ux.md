# Final checkout UX

## Scope and ownership

LOCAL presentation work from `c084d7ed3a0cfabfc34ec40029e329d530181678` on
`feat/checkout-final-ux`. No TEST writes, deployment, order submission or integration
configuration changes. This is not evidence of a completed Barion payment, parcel
registration or invoice. That integrated acceptance remains a separate next task.

The flow is cart → contact/addresses → shipping/payment → review/declarations/order.

- `inc/checkout-presentation.php` orders the existing additional-information block
  immediately before billing **before initial React rendering**. Block contents,
  registration, attributes, saved page content and field locations remain unchanged.
- One original Woo company checkbox, name and tax input remain in `#order-fields`.
  The fieldset is called "Számlázási adatok" through the existing WP/Woo translation
  presentation path. Billing address is its smaller subordinate section.
- Stable `#order-fields`, `#billing`, `#shipping` identify presentation owners.
  Woo may discard JS-added classes on rerender, so those classes no longer decide
  company section visibility or the address grid layout.
- The original marketing input stays registered in Woo's contact block. Scoped
  visibility and flex ordering show it only in final declarations. No cloned input,
  control reparenting, extra form, custom persistence or state store is introduced.
- Required legal acceptance remains the original Woo checkbox/validation. Optional
  marketing is initially unchecked and declining it does not block the order button.
- Review reads the current existing controls. It now includes delivery phone and
  resolves image-only gateway labels using their accessible alternative text.
- Existing CSS owners are edited in place: stable form grids, coherent checkboxes,
  compact mobile stepper, one main form surface, nested review/summary decoration
  removed, inactive final-actions spacing hidden. The duplicate checkout-header
  cart icon is hidden; the existing visible return-to-cart link remains.
- No new observer, polling, retry timer or checkout state machine.

## Reproduced presentation defects

Before: company identity was downstream of payment in initial markup, optional
marketing mixed with contact entry, company fields and duplicated surfaces looked
detached. An ephemeral company class could disappear during a Woo rerender and
make the company control leak into Step 3. Stable fieldset ownership fixes that
presentation bug without changing company or Store API synchronization.

During native QA, `networkidle` alone was insufficient for Woo's debounced address
recalculation: shipping-rate responses still carried the previous postcode. The
test now waits for the authoritative package postcode and finished Woo request
flags, then the selected GLS rate. No production shipping/state patch was made to
accommodate that test timing. Failed intermediate evidence is retained separately.

## Reproduction

Prerequisites: running LOCAL WordPress container, Playwright, Chrome, a safe in-stock
local product (default 334), configured local Barion TEST / GLS options and legal
demo mappings. No credentials or integration keys are stored in the tests.

```sh
node wordpress/wp-content/themes/appleklinika-theme/tests/checkout-final-ux.js
node wordpress/wp-content/themes/appleklinika-theme/tests/checkout-final-ux-addresses.js
```

Use `NODE_PATH` for a non-project Playwright runtime, `AK_CHROME_PATH` for Chrome,
`AK_WP_CONTAINER` for the local container and `AK_UX_OUTPUT` for evidence output.
The guest test also accepts `AK_CHECKOUT_PRODUCT_ID`. Both refuse non-local writes.

The guest gate uses native fill/click/check actions in fresh contexts at 1440px and
390px. PERSONAL and COMPANY each complete Step 2 → 3 → 4, with three completed
postcode/shipping recalculations. It asserts latest email, company, tax, separate
phones, names and addresses; original control ownership; validation; GLS/Barion
selection; unchanged totals; consent gates; no console errors or submissions.

The saved-address gate creates a uniquely marked LOCAL customer and two addresses
through WordPress and AddressBookService, checks 1 → 0 → 1 → 0 → 1, then uses
supported service/Woo/user deletion to remove only its fixtures. Guest carts and
session keys are similarly captured and cleaned. Preexisting drafts are protected;
stock stays at its baseline. Test cookies are never logged or persisted as evidence.

## Acceptance evidence

- Baseline screenshots: `/private/tmp/checkout-final-ux-LBr3FF/before/`.
- Final guest screenshots/results: `/private/tmp/checkout-final-ux-LBr3FF/final-acceptance/`.
- Saved-address screenshots/results: `/private/tmp/checkout-final-ux-LBr3FF/saved-address/`.
- Screenshots are personally opened at both widths, including close views of company fields.
- Native guest gate: 328 assertions. Saved-address gate: 32 assertions.
- PHP gates: cart/checkout 56; company 40; finalization 40; legal 9;
  address-book checkout 94; final-UX presentation 20.
- PHP/JS syntax and `git diff --check` are required before commit.
- `make test` / `make quality` are also run, but currently remain placeholder
  repository targets; they do not replace the concrete gates above.

The existing clearly marked legal demo notice and Barion TEST help remain visible
by design. Final legal wording and the TEST/production rollout are not part of this task.

## Address and summary refinement

Follow-up on the same feature branch from `ea9a7e8b5fb3ac271fc7a522faa8d76b1c515233`:

- The existing summary renderer marks its two method rows. Existing step classes
  show neutral "Kiválasztás a 3. lépésben" until Step 4; final review still shows
  selected GLS/Barion. Woo selections and calculated totals are never cleared or
  substituted. Returning to Step 2 also returns to the neutral presentation.
- Saved options precede the final "Másik cím használata" option. Its internal
  `__one_off__` value, selection request queue, save intent and server handlers
  are unchanged. Empty lists have no select; the existing manual-field reader
  now also accepts the absence of that select.
- Saved state has explicit explanatory copy and a WordPress-generated link to
  the existing Címeim editor; saving is shown only for manual entry. Native Woo
  inputs remain in their original tree. Manual mode exposes the existing form
  in place even if Woo collapsed a legacy profile address; no cloned form,
  synthetic editing state or new persistence is added.
- The single native same-address control already unmounts redundant billing UI.
  Existing declaration synchronization now explains that shipping is used for
  billing. Company identity remains in the same original billing fieldset.
  Both existing summary renderers use a shared, read-only field-prefix resolver
  in this mode: physical billing address comes from shipping, while company/tax
  still comes from billing identity. The final screenshot review exposed that
  company/tax previously made a partial review count as nonempty, omitting the
  shared billing street. A scoped runtime assertion failed before this correction
  (`shared-billing-before/`) and now verifies the billing section itself.
- CSS is limited to the existing summary/address owners and the new help text.
  No new observer, timer, DOM relocation, store action or Store API contract.

### LOCAL-only configuration

Through the existing shipping settings API, GLS global `shipping_price` is now
1990 for home delivery and 1490 for both parcel shop and locker. Weight-based
rates remain empty and free thresholds remain zero. Zone 1 has local pickup
instance 2 at 0 HUF (saved through the Woo instance settings handler).
Preexisting free-shipping instance 1 is preserved. No payment/plugin internals,
TEST configuration, orders, inventory rules or Buyback data are modified.

The local configuration evidence is outside the webroot at
`/private/tmp/checkout-address-ux-X1tmjg/local-shipping-before.json` and
`/private/tmp/checkout-address-ux-X1tmjg/local-shipping-after.json`.
These settings are not a migration and **will not be deployed with Git**.

### Focused acceptance

- Before: `/private/tmp/checkout-address-ux-X1tmjg/before-guest/` and `before-saved/`.
- Guest final: `/private/tmp/checkout-address-ux-X1tmjg/accepted-guest/`.
- Saved/manual final: `/private/tmp/checkout-address-ux-X1tmjg/accepted-saved/`.
- No saved entries, including retained profile values:
  `/private/tmp/checkout-address-ux-X1tmjg/accepted-empty/`.
- Run the address test normally and again with `AK_UX_NO_SAVED=1`.
- Native PERSONAL/COMPANY, 1440/390, same-address ON/OFF and three completed Woo
  recalculations are checked. Step 2 → 3 → 4 preserves latest identity, contact
  and addresses. No duplicate live selectors, stale validation, console errors,
  or order submission. Exact QA users, addresses, carts and sessions are removed.
- Both same-address ON and OFF complete the entire customer journey through
  final review. Native counts: guest 380, saved/manual 165, no saved entries 144.
  PHP counts: checkout 56, company 40, finalization 40, legal 9, address-book 96,
  final-UX 23. Total: 953 assertions. Screenshots are personally opened/reviewed;
  the original header remains fixed, so field-only screenshots can include its
  overlay at the crop edge; full-page views are the visual layout reference.
- Each shipping choice is checked through the native radio: for the 314,490 HUF
  QA product, locker/shop total 315,980 HUF; home total 316,480 HUF; pickup total
  314,490 HUF. Step 4 retains the chosen 1,990 HUF home rate.
- An intermediate test assertion incorrectly assumed two currency decimals;
  LOCAL HUF uses zero. The assertion now uses Woo's `currency_minor_unit`.
  A legacy-address edit test also now targets Woo's stable edit control rather
  than its translated visible text (its accessible name can be English).
  Neither test correction required a checkout state change.
- One intermediate browser run encountered an externally loaded GLS map module
  network suspension. The focused runners now stub only that external map host;
  opening a locator explicitly throws instead of pretending to test it. Real
  local Woo/GLS radios, shipping calculation, Store API and Barion presentation
  remain exercised. No external parcel lookup, payment or shipping submission is
  performed; integrated GLS locator/payment acceptance remains separate.

## Initial address provenance and Step 2 polish

Follow-up from `0ec144778cb30366f5afa9a2a4b3a4a9e7ae80b7`, LOCAL only.
The initial address-state suspicion was **not** a data leak. The baseline native
gate passed 1300 source/state assertions before any production modification.

### Authoritative sources

1. **Fresh guest:** new non-persistent Chromium context, no initial cookies,
   localStorage or sessionStorage; no browser autofill database. Autofill features
   are disabled and no `:-webkit-autofill` input is present. Names, streets, cities,
   postcodes and phones start empty. HU/CS defaults come from Woo's store location,
   not a previous customer. Woo creates its own cart cache keys after startup;
   their existence is not evidence of inherited QA state.
2. **Returning guest:** native edits travel through `/cart/update-customer` (which
   legitimately supports partial billing or shipping updates), Store API response,
   Woo's `customer` session entry and the recreated React fields on reload. Distinct
   billing/shipping addresses and phones persist in the same session.
3. **Logged-in / no custom entries / empty profile:** address fields remain empty;
   account email is a legitimate WP user default. No custom selector is rendered.
4. **Logged-in / no custom entries / existing Woo profile:** exact controlled
   `WC_Customer` billing/shipping values match the initial API/store/visible fields.
   `WC_Customer::__construct()` reads profile metadata first, then the matching
   current-customer session. `WC_Customer_Data_Store_Session::read()` checks customer
   identity and profile modification date; `set_defaults()` supplies location/email
   defaults. Zero Apple Klinika entries does not imply zero Woo profile metadata.
5. **Custom address book:** `CheckoutAddressSelection::options()` supplies only the
   authenticated customer's entries/defaults. Existing `renderPurpose()` and
   `setCustomFields()` apply the explicit saved selection. Both saved/manual states,
   COMPANY identity and repeated billing-host remounts remain covered.
6. **Drafts / stale QA / browser autofill:** source-traced initial fixtures have no
   existing checkout draft and no inherited session/storage. The recorded guest
   session before reload contains its own native input values, not an order-derived
   address. No old QA/profile/session is read or cleared to obtain a pass. Real
   user password-manager/autofill behavior is outside this controlled browser gate.

Woo's `CartSchema::get_item_response()` serializes `wc()->customer` into billing and
shipping addresses. Each trace records the live input, customer store, hydrated
cart, actual request/response address payloads, profile baseline and session snapshot.
Auth cookies and nonce values are never written into evidence.

### Reproduced defect and minimal correction

With same-address OFF and shipping filled but billing blank, all authoritative
billing values remained blank, yet the sidebar displayed the shipping address as
billing. The old unconditional `billing.address_1 ? billing : shipping` fallback was
the owner. The final baseline regression fails specifically on this presentation
contract in `before-proven/`; no source change was present during reproduction.

Only that fallback is removed. Shared-address mode still reads Woo's effective
billing data and the already-existing shared field-prefix resolver. No field clear,
new store, persistence, request, observer, timeout or native-input reparenting.

Own address selector/help nodes now appear **after** their native heading and before
the native content. Manual help distinguishes current order fields from separately
saved entries; saved help links to the existing Címeim editor. Guests never load
the authenticated save UI. The existing save separator is removed, billing helper
copy is shorter, and removing two mobile single-column overrides retains the existing
two-column short-field grid. Full-width street/phone/country fields stay full width.
There is no new wrapper/card or CSS override layer.

### Reproduction and evidence

```sh
node wordpress/wp-content/themes/appleklinika-theme/tests/checkout-address-initialization.js
node wordpress/wp-content/themes/appleklinika-theme/tests/checkout-final-ux.js
node wordpress/wp-content/themes/appleklinika-theme/tests/checkout-final-ux-addresses.js
AK_UX_NO_SAVED=1 node wordpress/wp-content/themes/appleklinika-theme/tests/checkout-final-ux-addresses.js
```

`AK_UX_OUTPUT` selects the evidence directory. Optional diagnostic filters for the
initialization gate: `AK_UX_WIDTHS=1440` and `AK_UX_CASES=guest`; omit them for full
1440/390 and guest/empty-profile/woo-profile coverage. Each case has a new context,
exact marked user/session ownership, supported Woo cleanup and unchanged stock.

Evidence root: `/private/tmp/checkout-initial-address-gvcDD0/`.

- Before: `before-settled/` (six initial-source cases), `before-saved/`, and the
  failing sidebar reproduction `before-proven/`.
- After: `after-origin/` (same regression plus all source cases), `after-guest/`,
  `final-saved/`, `final-empty/`. Both widths and PERSONAL/COMPANY screenshots are
  personally opened; shared/independent billing and final review are also inspected.
- Runtime gates exercise three completed Woo shipping recalculations, reload,
  Step 2 → 3 → 4, original field ownership, save visibility, legal/marketing gates,
  GLS/Barion presentation, local shipping totals, no duplicate controls or console
  errors. No Place order click is allowed.
- Test timing waits for the returned shipping-package postcode, not just optimistic
  `getCartData()`: reloading before the debounce request completes is not session
  restoration. Intermediate timing/partial-request test corrections are retained
  in the evidence and did not cause application-state modifications.
- Final counts: initialization/source trace 1467, guest UX 380, saved/manual 173,
  zero-entry PERSONAL/COMPANY 152; PHP address-book 99, cart/checkout 56, company
  40, finalization 40, legal 9, final-UX 24. Total: **2440 assertions**.
  PHP/JS syntax and diff whitespace checks pass. The repository `make test` and
  `make quality` targets are also run but are still placeholders, not additional
  coverage. One old static cart test required the exact buggy fallback text; it
  now checks the native same-address guard, while runtime proves both cases.
- Final read-only cleanup audit: 12 exact QA users, 21 captured sessions and 18
  exact QA emails checked across this task's runs; zero remaining users/sessions/
  orders, including drafts. Product 334 stock remains 1. See `cleanup-verified.json`.
  No local business customer/profile, existing draft, shipping configuration,
  TEST SERVER or Buyback data was changed by this follow-up.

## Changed files

All theme-relative paths below are under `wordpress/wp-content/themes/appleklinika-theme/`.

- Presentation: `functions.php`, `inc/checkout-presentation.php`,
  `assets/js/frontend.js`, `assets/css/frontend.css`, `assets/css/checkout-sidebar.css`.
- Tests: `tests/cart-checkout.php`, `tests/checkout-final-ux.php`,
  `tests/checkout-final-ux.js`, `tests/checkout-final-ux-addresses.js`.
- Documentation: root `README.md`, root `deficiencies.md`,
  `docs/ui-qa-checklist.md`, this document.
