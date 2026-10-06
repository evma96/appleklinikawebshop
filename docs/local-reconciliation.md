# LOCAL fulfillment / Back Office reconciliation

## Scope and protected inputs

- Integration worktree: `/Users/apple/Desktop/appleklinika-integration-active`.
- Branch: `feature/local-fulfillment-backoffice-integration`.
- Base: recovered storefront `d66dbe8e0d17d3e1f6839c61d7b9c45c21547f2f`,
  containing email/fulfillment checkpoint `ae80d2140e22918a2040d2bc35d21b32bdb2895c`.
- Back Office input: `4c4ee8353da6dcd4797c623b6f480a23fe00e700`.
- Original checkpoints/worktrees remain unchanged. No branch merge, push, TEST
  access, provider request or deployment is part of this task.

## Five semantic reconciliations

Paths below are relative to `wordpress/wp-content/plugins/appleklinika-backoffice/`.

| File | Resolution |
| --- | --- |
| `src/Domain/DeliveryMode.php` | Preserve Back Office's rejection of empty/mixed shipping-method IDs; an old display snapshot cannot authorize carrier work. |
| `src/Domain/FulfilmentWorkflow.php` | Keep email candidate's separate post-delivery completion and correction semantics; include Back Office document-view activity labels. |
| `src/Infrastructure/OrderDocuments.php` | Combine detailed provider-owned invoice/label metadata with existing multi-package tracking helper and normalized legacy fallback. No second invoice store, client or generation path. |
| `src/Infrastructure/WooOrderBackOfficeRepository.php` | Use current Woo shipping items as authority; retain detailed Back Office readiness and document-access audit. Remove the unused direct transition entry point. Transition persistence retains actor/reason/history. Notes/document audit take the same order mutex and reload before writes, preventing stale UI snapshots from overwriting state/history. |
| `src/Interfaces/BackOfficeRouter.php` | Retain constructor-injected shared ChangeFulfilment service and expected-state guard; combine shipping/invoice detail panels and authorized PDF-access history. GLS documents require a current GLS mode. Queue/search/pagination/notes/pickup/problem controls and responsive styles are retained. |

The single state is `FulfilmentWorkflow::META_KEY` on the Woo order and its shared
`_appleklinika_backoffice_history`. Both controllers invoke `ChangeFulfilment`;
My Account and lifecycle presentation read that same order. No state synchronizer
or duplicate hooks were added. The application/domain boundary remains intact.

The existing email/invoice marker ownership, per-order mutex, stale-transition
guard, paid-plus-invoice email gate, handoff gate, separate delivered closure,
redundant-email suppression and Barion/payment implementation are preserved.
The GLS label/tracking and environment guards remain in the application/adapters.

## Deliberately included non-overlapping changes

- Back Office's invoice-transport regression suite and its additional workflow,
  document, repository and rendered-view assertions.
- One combined offline runner, exposed through `make test` and the two focused
  aliases; no network or real provider configuration is used by the suite.
- An opt-in durable LOCAL Compose/PHP mail/network guard and existing-permalink
  compatible Back Office route. Runtime copies/private settings are ignored.
- A real Woo-admin crash discovered in-browser: the vendor invoice eligibility
  filter supplies arrays in its metabox and booleans in automation. The lifecycle
  adapter now accepts/preserves either, blocking only unowned managed attempts.
  Sixteen focused assertions cover this; no provider/license code was bypassed.

## Verification evidence

The combined offline suite passes **301 assertions**: dedicated URLs 47, workflow
45, invoice transport 18, lifecycle environment 9, provider hook contract 16,
documents 34, lifecycle 28, queue 16, repository 52, views 31 and GLS tracking 5.
The runner also validates PHP syntax in the Back Office, GLS and invoice plugins.
`make quality` is a placeholder-only repository target, not static-analysis proof.

Both LOCAL runtimes serve the integrated source from the durable worktree with
separate pre-existing databases/core volumes and private upload copies. The
storefront's only active-plugin change is enabling the reconciled Back Office
plugin. Provider-option hashes match their pre-verification baselines in both
databases. Outgoing WordPress HTTP and mail return a local failure by design.

Browser verification:

- Storefront: homepage, product, non-submitted cart/checkout, authenticated account
  order detail, shared timeline and two package-tracking links. Desktop 1280px and
  actual mobile 390px checked; mobile homepage/account/checkout have no horizontal
  document overflow. No redesign or legal/content edits.
- Back Office: authenticated queue, pagination page 1/2 to 2/2, search, order
  details, fulfillment controls, internal notes/history, shipping/GLS and
  invoice/document panels. Desktop 1280px and mobile 390px checked. Existing
  provider-unavailable messages are expected LOCAL configuration, not failures.
- Temporary storefront fixture #2457: Back Office `start` changes Woo fulfillment
  state from `new` to `preparation`; its order history records actor/action. A
  private note appends without overwriting state. My Account shows the same
  preparation state and two synthetic tracking links, never the internal note.
  No real product/stock, payment, invoice, shipment or external email is involved.
- The Woo-admin order page renders after the type-contract repair. Its custom
  correction panel needs the existing Woo/HPOS `edit_others_posts` permission.
  The initial approval blocker was resolved by explicit owner authorization on
  2026-09-29; the completed correction verification is recorded below. No
  authorization bypass or Administrator role was used.
- Browser console checks show no JavaScript warnings/errors on inspected pages.

Both temporary QA users, fixture #2457, checkout-created draft #2458 and the
fixture activity-index entries were removed after verification. The browser cart
is empty and QA sessions/credential file are removed. Both databases retain their
exact original order-ID sets, six original users and unchanged provider-option
hashes. No real order/customer was edited. Two unused task-only network bridges
were also removed; existing database networks remain.

Fresh login requests on both environments produce no PHP warnings/fatal errors;
the earlier blocked WordPress.org language-picker warning is resolved in the
opt-in offline runtime guard. Both Apache configurations pass syntax checks.
`make test` and `make quality` completed successfully, with the placeholder
quality limitation noted above. Protected refs and worktrees are unchanged/clean.

Private screenshot evidence is retained outside Git under the task's
`local-recovery-20260928/reconciliation` artifact directory.

## Final correction acceptance — 2026-09-29

The earlier QA fixtures had already been removed. A fresh disposable LOCAL user
and synthetic order #2459 were created for this last check. The user remained a
subscriber with explicit Back Office/Woo order capabilities, plus the authorized
`edit_others_posts` needed by Woo/HPOS. `manage_options`, plugin administration and
the Administrator role were absent. Real accounts/orders were never edited.

Using the real Woo-admin form, the QA operator corrected `preparation` to
`packing`, with reason `LOCAL QA correction — verify shared state and history`.
A second admin tab retained the old expected state; repeating the same submission
was rejected with the existing stale-state message. The Back Office and customer
My Account pages both displayed `Csomagolás alatt`. The customer view did not
expose the internal correction reason or operational audit action.

Nine additional persisted-state checks passed: corrected state; exactly one
correction event; correct from/to values; actor/timestamp; exact reason; one new
private order note; unchanged email/invoice/shipment metadata; no new queued
lifecycle/provider actions; unchanged Woo processing/payment status. The notes
probe initially used an unsupported negative limit (normalized by WordPress to
one result); the verification script was corrected to a supported positive limit.
This was a test-tooling issue and required no application change. No provider
request, shipment, invoice or outgoing email was initiated.

The added capability, QA account, order, audit-index entry, notice, authentication
sessions and private credential file were removed. No checkout/cart session was
created in this follow-up. Hash comparisons confirm unchanged original user and
usermeta rows, all original Woo order/address/operational/meta/item tables, stock
values and application/provider configuration. The only changed non-transient
option was `action_scheduler_lock_async-request-runner`, confirmed in the vendor
source as a short-lived runtime lock rather than configuration; it was not reset.

The admin visit logged three WordPress.org update-check warnings (core, plugins,
themes) because the intentional LOCAL HTTP guard rejects those requests. They
are offline-runtime/tooling observations, not fulfillment failures; no update
was performed. No PHP fatal or browser JavaScript error occurred. After cleanup,
the former QA session redirects back to login as expected.

Evidence: `woo-admin-correction.png`, `woo-admin-stale-rejected.png`,
`backoffice-corrected-state.png`, `account-corrected-state.png` in the same private
artifact directory. The offline regression suite remains 301 assertions, with
nine additional successful LOCAL persistence checks above.

## Acceptance state

The controlled LOCAL reconciliation and its browser verification are complete.
The approved candidate is checkpointed on the integration branch only after the
final `make test`, `make quality`, diff and cleanup checks. No merge, push, TEST
access, provider acceptance run or deployment is included. Original protected
checkpoints remain unchanged. There is no remaining LOCAL acceptance blocker.
