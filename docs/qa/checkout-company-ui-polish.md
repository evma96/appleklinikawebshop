# Checkout company UI polish

Local verification: 2026-09-06, based on develop `fc931e59076179a023b41e9c9f3c2976d6df6bc4`.

The company toggle inherited the address form's 48px text-input minimum height, while its
dedicated styling still targeted the old billing-field placement. Styles now use Woo's
native `#order-fields` fieldset and stable field classes. Company/tax inputs share a
two-column group on desktop and stack on mobile. Address rows have consistent spacing;
technical company fields and inactive personal identity fields stay visually hidden
even when React recreates their wrappers.

The existing company presentation function now sets visibility and required/aria-required
before returning when there is no separate billing form. No Woo-owned nodes are moved;
no store, Store API, order, consent, or Buyback logic changes.

Checkout-only WordPress JavaScript translations supply the company heading (including
its accessible legend) and country-selection prompt. A checkout icon fallback uses the
existing brand image only when WordPress has no configured site icon.

## Browser acceptance

Verified in a separate local guest browser context, using native input and checkbox actions.
No order was submitted. Viewports: 1440px and 390px.

- Step 2: company OFF hides the company/tax fields, with no required constraint.
- Company ON shows both fields; empty values fail native required validation.
- OFF after same-address switching removes required and aria-required constraints.
- Valid company name and HU tax number satisfy native validation.
- Three postcode edits and Woo updates preserve company, tax, email, both phones,
  billing/shipping addresses, and COMPANY mode.
- Step 2 → 3 → 4 succeeds; the company entry section is hidden in steps 3 and 4.
- The final review retains the entered company and contact data.
- No duplicated controls, horizontal overflow, English checkout labels, page errors,
  or browser console errors.

## Targeted checks

- Cart/checkout: 56 assertions.
- Company contract: 40 assertions.
- Order finalization: 40 assertions.
- Legal infrastructure: 9 assertions.
- PHP and JavaScript syntax; `git diff --check`.

This visual acceptance does not submit another order or replace the previously approved
functional order E2E. Test-server deployment remains a separate task.

The isolated local QA cart/session and checkout draft #2214 were removed through
WooCommerce APIs. No submitted order was created. The local preview uses read-only
theme, address-book and Buyback source mounts from this worktree because the original
working directory contains an unrelated branch and existing untracked legal files.
