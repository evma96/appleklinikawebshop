# GLS label creation after packing

## Trigger and boundaries

`ChangeFulfilment` persists `packing_completed`; the existing
`ProviderObservationHooks` / `GlsAutomation` adapter is the sole automatic trigger. It applies to GLS delivery, from `packing` to `ready_for_shipping`, with
Woo paid status and a persisted paid date. Payment/invoice hooks do not create labels.
Both Back Office and Woo admin use this shared application command/event; staff capability is required. The packing
transition is saved before requesting the label, and both events retain the actor.
A correction alone never generates a label; returning to packing and completing it
again reuses existing label metadata.

MPL and cash/personal pickup retain their existing workflows. The change does not
modify Barion, invoice generation, stock deduction, or customer email code. The only
shipping-email trigger remains a separately logged, actual carrier handoff.

## Exactly-once protection and retry

`CreateGlsLabel` protects every single-order vendor entry (automatic, native Woo
manual action and bulk generation) with the existing reentrant per-order database
mutex. `_appleklinika_gls_attempt` is saved **before** dispatch. Existing label,
parcel or tracking evidence always prevents new generation, even if the PDF is lost.

- `ready`: successful label; no regeneration. Reopen the existing PDF.
- `retryable`: no request dispatched, or an explicit authentication/validation
  rejection without label/parcel evidence. Correct configuration/order data and use
  the existing **GLS címke létrehozása** action. No automatic retry loop.
- `attempting` / `uncertain`: crash, timeout, HTTP server error, malformed response,
  partial success or PDF persistence failure. No blind resend. Reconcile with GLS
  and restore the existing parcel/PDF; do not clear the journal merely to retry.
  If GLS confirms no parcel exists, a deliberate supported order-meta repair may
  mark the attempt retryable, with an internal reconciliation note first. There is
  intentionally no one-click bypass of this safeguard.

Failure is visible in Back Office and Woo admin, private order notes and, for the
automatic path, the shared fulfilment history. Recovery stays in the existing
collapsed, confirmation-required exceptional actions; it is not a routine step. Packing remains complete; the order
never advances to `handed_to_gls` and no shipping email is sent by label creation.
No distributed system can promise external exactly-once execution after an unknown
network outcome; the journal chooses reconciliation instead of risking a duplicate.

The vendor's legacy **Bulk Print GLS Labels** actually calls PrintLabels with fresh
parcels rather than downloading existing PDFs. It is hidden and its handler rejected
while the shared lifecycle is active. Guarded bulk generation and individual PDF
view/download remain available.

## Verification

- `make test`: 725 offline assertions plus PHP syntax checks.
- Document printing JavaScript: 22 assertions, preserving existing Open/Print behavior.
- `make quality`: executed; repository lint/static targets remain placeholders.
- Isolated real WooCommerce/HPOS fixture: 39 assertions; real Barion callback handler,
  stock and lifecycle adapters; real GLS request/label persistence adapter with mocked
  HTTP. One GLS label at packing completion, none on payment/invoice; three distinct
  customer notifications, no repeats after correction; cash/pickup unchanged.
- No real provider request, customer delivery claim or production activation is
  included in these automated tests. Existing accepted provider/inbox evidence is
  retained, not repeated.
- TEST deployment and bounded QA result are recorded in the task report. TEST
  verification substitutes GLS HTTP responses, blocks outgoing mail and removes only
  its own QA orders, notes, PDF files and activity entries using Woo APIs.

No database migration or configuration change is required. Rollback requires the
previous source files only; preserve attempt metadata and existing parcel references.

## Concurrent TEST source preservation — 2026-10-17

Deployment preflight found approved Back Office work installed outside the repository
checkout by the parallel Back Office task (document Open/Print, all-order queue,
invoice observation/recovery and packing-event adapter). Deployment stopped before
source replacement. The exact TEST source files were captured and incorporated into
this feature; no unrelated worktree was modified. The original packing-event adapter
is retained instead of installing a second trigger. The existing
`_appleklinika_gls_attempt` journal is reused: legacy generating/failed/uncertain/ready
markers cannot be bypassed. Only blocked-before-dispatch and explicit retryable
rejections may start a new attempt. Native/bulk vendor generation shares this guard.

The earlier candidate `b71975bc` was published but deliberately **not deployed** over
the uncommitted TEST overlay. The subsequent integration commit is the deployable
combined source. Existing accepted #758 provider evidence is preserved; verification
does not repeat payment/invoice/carrier operations.
