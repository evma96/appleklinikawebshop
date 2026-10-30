# Accessory draft consistency and mixed invoice acceptance

## Root cause and scope

The native Woo Store API rebuilds line items when the cart hash changes: delete all current lines, construct the new lines, save them. Interleaving two updates of one existing draft can leave one device and two identical accessory rows for a two-row cart. A deterministic LOCAL reproduction pauses request A after its device insert, runs request B's rebuild, then resumes A's accessory insert. The stored hash then matches the cart, so subsequent native updates do not inspect or repair the extra row. This reproduces the recorded #796 failure shape; no per-request trace from that deleted historical draft exists.

The fix acquires a connection-scoped advisory lock for the validated Woo customer/session before session initialization, and retains it until after Woo saves the session. Locking only around the REST callback was insufficient: concurrent requests could already have loaded an obsolete draft ID and create different drafts. Cart-token and cookie access use the same customer identity. A busy lock returns a retryable 409; no forced write or provider retry is performed.

Only unpaid Store API `checkout-draft` orders with linked accessories are reconciled against the authoritative cart. Matching includes native cart key, parent key, product/variation, intended quantity and native line amounts. The entire draft line set is rebuilt through Woo APIs when different. This is not SKU deduplication. Paid, processing, completed, refunded, transaction-bearing and stock-reduced orders are excluded. No historical order migration runs.

## Approved accounting configuration (Martin, 2026-10-30)

- Used QA iPhone: native Woo class `ak-used-iphone-margin`, Hungarian 0 numerical rate labeled `K.AFA` for supported Számlázz mapping.
- Every current Extra védelem product: native Woo class `ak-accessories-27`, Hungarian 27% rate.
- Existing retail prices remain gross. Enable Woo tax calculation, tax-inclusive entry and inclusive customer display. No extra 27% is added to stored retail prices.
- No global Számlázz VAT override. No automatic K.AFA assignment to other device families.
- The one explicit setup command accepts only a marked disposable used iPhone; future catalog accounting assignments require their approved configuration.
- Device identifiers remain immutable order-line snapshots, never accessory notes.
- Invoice general note, exactly: `használt cikk - különbözet szerinti szabályozás`.
- Shipping tax classification is not changed. Acceptance uses existing free personal pickup, without a paid shipping line.

Explicit LOCAL/TEST configuration command (no production support):
`php wp-content/plugins/appleklinika-inventory/tools/configure-mixed-test-tax.php --apply <marked QA iPhone ID>`

## Verification

- `tests/protection-draft-local.php`: 80 native Woo checks, including deterministic overlap, existing draft repair, replay, session restoration, shipping selection, two devices with the same extra, quantity two, parent/child removal, Back Office line counts and no payment/stock/provider/email effects. Fixtures removed by Woo CRUD.
- `tests/mixed-invoice-tax-local.php`: installed Számlázz vendor line builder, 33 checks; 100000 + 8000 + 7000 HUF gross stays 115000 HUF. K.AFA / 27 / 27 labels, correct net/tax/gross, exact general note and immutable device identity. No transport. Temporary fixtures and settings restored.
- Actual LOCAL Store API parallel HTTP probe: 16 simultaneous fresh-draft GETs and 16 PUT updates resolve to the same draft after early locking.
- Provider acceptance is a separate authorized sandbox action, not an automated test. A local XML pass does not establish issued-PDF acceptance.
