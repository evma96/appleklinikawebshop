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

## Changed files

All theme-relative paths below are under `wordpress/wp-content/themes/appleklinika-theme/`.

- Presentation: `functions.php`, `inc/checkout-presentation.php`,
  `assets/js/frontend.js`, `assets/css/frontend.css`, `assets/css/checkout-sidebar.css`.
- Tests: `tests/cart-checkout.php`, `tests/checkout-final-ux.php`,
  `tests/checkout-final-ux.js`, `tests/checkout-final-ux-addresses.js`.
- Documentation: root `README.md`, root `deficiencies.md`,
  `docs/ui-qa-checklist.md`, this document.
