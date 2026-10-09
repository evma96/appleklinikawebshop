# Cash personal-pickup order flow

Date: 2026-10-09. Proven TEST baseline: `490e6e753bb3c610bc2dc410b8f66a1eac77ae3b`.
The owner supplied the missing business decision: staff stock verification and
explicit acceptance precede payment at the actual personal pickup. This document
records application behaviour, not changes to VirtualJog or legal wording.

## Shared lifecycle

- Enable only the native Woo COD gateway restricted to `local_pickup`. Use title
  `Készpénz személyes átvételkor`, no virtual orders, and rollout option
  `appleklinika_cash_pickup_enabled=yes`. Default is disabled. The final available
  gateway filter also checks the selected shipping method, so courier COD cannot
  accidentally enter this branch. Existing Barion/BACS settings stay unchanged.
- Validated online submission sends the existing acknowledgement once. COD moves
  to Woo `on-hold`, not paid/processing. Native Woo reserves the stock once at this
  point. Staff then physically verifies/sets aside those reserved items and uses
  the explicit `accept_cash_pickup` shared transition from new to preparation.
  Acceptance checks native item-level stock-reduction markers, preventing an
  unreserved order from silently proceeding. A correction cannot impersonate it.
- Acceptance is recorded with the existing actor/timestamp/history and uses the
  existing `paid_invoice` notification ledger as the single accepted-order event.
  For cash pickup only, acceptance requires staff history, not money or a PDF.
  Its existing visual template states cash payment at pickup, store address and
  actual pickup progression; it makes no paid/invoice claim and attaches no PDF.
- `prepare_pickup` changes the existing state to `ready_for_pickup` without mail.
- At real pickup, `record_cash_pickup` records cash amount/currency/actor/time, calls
  Woo `payment_complete`, and reaches `picked_up`. The same licensed Szamlazz.hu
  invoice adapter handles that one paid event. Native order/item stock markers,
  invoice attempt records, notification records and the existing reentrant order
  mutex prevent duplicate side effects. Failed/uncertain invoices remain visible
  for reconciliation; they are not blindly retried.
- The invoice uses the existing provider file resolver. A cash-pickup My Account
  link downloads it through an authenticated owner/nonce check; private upload
  directories stay blocked. The first TEST browser run exposed that the existing
  provider integration had no usable customer download route, so this was added. No extra
  pickup-completion or repeated acceptance email is added. Woo `completed` remains
  a separate closure. For managed cash orders `is_paid` also requires the explicit
  cash receipt record, preventing a status-only edit from asserting actual payment.
- Back Office and Woo admin call the same application service. Existing permissions,
  nonce checks and internal correction/history behaviour remain in force. The
  generic picked-up action cannot bypass cash collection. Provider/native duplicate
  customer email suppression is retained; no new SMTP or provider client exists.

## TEST configuration after source deployment

Back up first. Preserve all other gateway/shipping/provider settings. Enable the
native COD settings with `enable_for_methods=[local_pickup]`, `enable_for_virtual=no`,
and the rollout option above. In the existing Szamlazz payment-method settings,
map this COD gateway to `Készpénz`, immediate due date/paid settlement; do not change
Agent identity, automation rule, licence or other payment methods. Keep TEST account
and the existing fail-closed QA recipient guard. Production remains disabled.

## LOCAL evidence

- Offline shared lifecycle/security regression, including unpaid acceptance,
  prohibited correction bypass, silent preparation, repeated payment/dispatch and
  pickup-vs-courier isolation.
- Disposable real Woo fixture: original lifecycle 41 assertions; existing email
  presentation 122 assertions across seven memory-only scenarios.
- `tests/integration/cash-pickup.php`: real Woo COD submission, stock reservation,
  two captured emails, shared pickup state, actor history, cash receipt, fixture
  invoice, no invoice/paid claim before pickup, and duplicate protection. This uses
  an injected invoice port in an internal-network LOCAL database; it is not proof
  of a provider invoice or actual Inbox delivery. Dedicated QA records are removed.
- Actual local browser at 1440px and 390px: Hungarian cash acceptance, store address,
  correct amounts, unchanged brand layout, no horizontal overflow or invoice claim.
- Empty-fixture prerequisites (HU language pack/locale, HUF price position and GLS
  tracking country) were explicitly set; initial missing-fixture failures were
  tooling/configuration mismatches, not failures on the proven TEST storefront.

TEST acceptance evidence is recorded separately after deployment. No legal PDF,
private configuration, credentials, customer data or runtime artifact belongs in
this checkpoint.
