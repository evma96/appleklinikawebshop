# Final webshop payment policy — LOCAL candidate

Date: 2026-10-28. Branch: `feature/webshop-barion-only`.
Base: `56c2646f`. No commit, merge, push, TEST access or deployment.

## Final decision and scope

All webshop orders require online Barion prepayment, including personal pickup. This supersedes the earlier LOCAL candidate that offered payment at pickup. It does not change legitimate direct-store cash recording, existing orders, or the proven payment/invoice/email/stock/fulfilment implementation.

| Shipping | Price (HUF) | Webshop payment |
| --- | ---: | --- |
| GLS home | 1990 | Barion only |
| GLS point/locker | 1490 | Barion only |
| MPL home | 2090 | Barion only |
| MPL point/locker/post office | 1040 | Barion only |
| Personal pickup | 0 | Barion only |

BACS, COD, pay-at-pickup and unknown gateways are excluded from checkout. No generic free courier rate is introduced. Shipping configuration and provider code are unchanged.

## Implementation

- `Domain/CheckoutPaymentPolicy` allows only `barion`, independently of delivery method.
- The Woo available-gateways adapter only removes methods; it never enables Barion against native availability or a production activation veto. With no eligible Barion gateway the result is empty, with no fallback payment.
- Classic checkout and the Store API order validation enforce the same policy server-side. Native Woo also rejects unavailable gateways before invoking payment processing.
- On Woo availability refresh, stale `chosen_payment_method` values become the available Barion method, or empty if Barion is unavailable. No bulk session deletion or SQL migration is used.
- The native Blocks extension excludes BACS/COD regardless of selected shipping packages. No checkout replacement or independent order storage.
- Effective BACS settings remain disabled with empty customer instructions. **COD remains internally registered with its existing configuration**: staff cash recording and the existing activation preflight depend on it. Its internal enabled flag does not grant checkout eligibility. The checkout allowlist removes it even if a saved/imported setting enables it. Neither `CashPickup` nor the activation guard is modified.
- Payment success is still handled only by the existing Barion provider callback. For pickup, paid/processing, ready-for-pickup, actual pickup and completed remain distinct facts/events.

## Workspace isolation

The previous payment candidate was in `launch-payment-local`, based on the newer integrated Back Office/lifecycle source. The open localhost:8082 storefront/performance candidate is in `/Users/apple/Desktop/appleklinika-integration-active`, based on `f3c61e5f`, and predates parts of that lifecycle. The payment task stays in its own worktree and updates only its intended payment package. All 34 pending storefront/performance files were hash-compared and preserved. Do not overwrite either worktree during final consolidation.

The final policy is verified at isolated LOCAL port 18882, not installed over the older localhost:8082 runtime. Before edits, the previous ten-file payment candidate was copied to the private local evidence directory for recovery. Existing branch commits and other worktrees were not rewritten.

## Verification

An empty disposable Woo/HPOS database `lifecycle_fixture` is used, with no copied customers, orders, settings or provider secrets. Application/database networking is isolated; a localhost-only proxy provides browser access. WordPress HTTP, direct PHP mail and cURL are blocked. Installed native Barion and Számlázz.hu code is used, with deliberately nonfunctional fixture settings. External payment-state and invoice-generation ports are local doubles. Shipping rates are local fixtures using the approved IDs/prices; this is not a new carrier E2E.

- **137** policy/adapter assertions: all shipping families, stale sessions, missing/unknown/disallowed payment IDs, classic/order validators, absence of Barion and preserved historical settings.
- **15** Blocks assertions: COD/BACS always rejected, no forced Barion availability.
- **111** native Woo checkout assertions: all five rates, unchanged costs, manipulated requests rejected before processing; GLS/MPL/pickup submissions remain unpaid until success; one acknowledgement; failed provider state remains unpaid; verified success reaches one invoice and one accepted email with a fixture PDF. The original Barion IPN handler executes against a local response double; repeated callbacks do not duplicate stock/invoice/email/order effects. Actual pickup and Woo completion remain separate.
- **29** unchanged cash/pickup integration assertions: supported staff cash recording, state/history, invoice and notification idempotency still work even though the gateway is absent from webshop checkout. This fixture exercises the retained internal/legacy workflow directly, never a new cash checkout.
- **39** offline activation/lifecycle assertions: missing activation remains blocked; valid simulated activation works with Barion-only checkout; staff cash recording still works; emergency OFF remains effective. Only the test's obsolete pickup-cash availability expectation changed. No actual production access or activation.
- **331 focused assertions** in total. The full `make test` suite passes; combined with the additional focused scripts, **1,213 assertions** pass without double-counting the 137 policy assertions already included in `make test`.
- `make quality` completes but reports no configured linter/static analyzer; it is not a static-analysis coverage claim. The existing suite's PHP syntax checks pass.
- Browser: GLS, MPL and pickup at **1440, 390 and 320** (9 combinations). Only Barion is visible/selected; no BACS/COD/pay-at-pickup, no horizontal overflow and no console errors. Pickup remains free and the final review shows Barion. English Woo labels and sandbox notice belong to this empty fixture, not a change to the approved storefront copy.

Evidence outside Git: `barion-only-evidence-20261028/`, beside this worktree. Screenshots: `gls-{1440,390,320}.jpg`, `mpl-{1440,390,320}.jpg`, `pickup-{1440,390,320}.jpg`; browser matrix JSON and test logs accompany them.

## Cleanup and handoff

Own orders/users/PDF fixtures are removed through Woo/WordPress APIs after the integration checks. The disposable environment, volumes, browser session and generated database credentials are removed at the end. No actual provider request or email delivery occurred. Main, TEST, production, legal PDFs, activation guards and unrelated LOCAL work remain unchanged.

**READY FOR FINAL CONSOLIDATION.** Integrate this payment candidate together with the preserved storefront/performance work in the next authorized task; do not restore the superseded pickup-payment checkout option.

## Source files in the complete payment candidate

- `README.md`
- `deficiencies.md`
- `docs/qa/launch-payment-eligibility.md`
- `wordpress/wp-content/plugins/appleklinika-backoffice/appleklinika-backoffice.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/src/Domain/CheckoutPaymentPolicy.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/src/Interfaces/CheckoutPaymentHooks.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/assets/checkout-payments.js`
- `wordpress/wp-content/plugins/appleklinika-backoffice/tests/checkout-payment-policy.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/tests/checkout-payments.cjs`
- `wordpress/wp-content/plugins/appleklinika-backoffice/tests/integration/checkout-payment-eligibility.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/tests/integration/checkout-barion-callback.php`
- `wordpress/wp-content/plugins/appleklinika-backoffice/tests/integration/production/scenario.php`
