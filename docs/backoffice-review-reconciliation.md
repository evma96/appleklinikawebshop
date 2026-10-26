# Back Office review reconciliation

## Provenance and scope

The owner authorized preserving the previously accepted, selectively deployed TEST review package in Git on 2026-10-26. The source was read from the verified TEST host through `appleklinika-test`, based on repository commit `1cc50ee825bfc656a53756d8fd05aaa3db6736e8`. Five tracked runtime files and six additional runtime files belonged to this package; its focused test changes are retained. No credentials, order exports, private runtime configuration or backups are included.

A three-way merge with protection release `01e9392c18d59438799c1122ed4dae9a5d24e5cf` preserves every existing runtime change. Compared with the inspected TEST Back Office source, the only application differences are the approved accessory parent-device detail and line-total display. Normal payment/invoice/stock/carrier automations are unchanged. The older independent Back Office working copy is not overwritten or copied wholesale.

## Existing behavior (preserved)

`ResolveOrderReview` coordinates the command under the existing per-order mutex. `OrderReview` holds the recovery policy; `WooOrderReview` adapts current WooCommerce data. HTTP handling only validates/dispatches and preserves the allowlisted worklist context.

Every correction requires the Back Office capability (or administrator), an order/action nonce, POST, explicit confirmation, a 5–500 character reason/source, and a fingerprint of the state shown to the employee. A changed order requires refresh. Provider operations never run on GET.

### Shipping resolution

Shipping resolution is offered for unknown fulfilment, or as “Korábbi kézi átvételi mód javítása” when the current canonical item exactly matches the latest completed Back Office correction. Both require an open, early-stage order without downstream fulfilment effects. Options come from enabled methods in the order address's matching WooCommerce zone plus enabled non-zone methods. The existing supported pickup/GLS classification is reused. Zone prototypes, disabled instances, unsupported methods and parcel-point/locker services without a supported point-selection operation are not offered. Global GLS also requires its configured destination country and a usable address. COD corrections are limited to pickup. Every new decision requires a fresh internal reason and confirmation; a no-op is rejected.

The employee chooses explicitly; there is no default guess. Missing shipping data receives a real `WC_Order_Item_Shipping`; an existing single item is repaired in place. Method ID, instance ID and title are updated; the existing worklist shipping-title snapshot is refreshed. Shipping amount, tax, order total, billing/customer data, invoices and provider documents are not recalculated or replaced. The confirmation explicitly requires agreement with unchanged charges.

Multiple shipping items, inconsistent shipping totals/taxes, late fulfilment stages, or recorded/uncertain carrier attempts require reconciliation rather than an automatic rewrite. Existing labels/parcel/tracking data prohibit reassignment. Known preflight blocks and explicitly retryable rejected GLS attempts do not count as an issued shipment.

### Payment resolution

| Actual method | Supported recovery | Authoritative change |
| --- | --- | --- |
| Native bank transfer / cheque | Confirm verified receipt of the full amount on a pending/on-hold, positive, itemized order with no prior payment evidence | Native `WC_Order::payment_complete()` sets payment date/status and invokes existing stock/provider lifecycle hooks; no transaction ID is invented |
| Barion | “Barion állapot újraellenőrzése” | Existing gateway client, captured through its supported `woocommerce_barion_init` hook, queries actual payment state. Only matching Succeeded payment/order/amount/currency and a real matching transaction can use the existing gateway completion method |
| Managed cash pickup on current TEST | Existing stock acceptance → ready for pickup → explicit cash-and-handover confirmation | Existing `CashPickup`/`ChangeFulfilment` path and its cash-payment audit marker; no second cash workflow |
| Unknown gateway / inconsistent or unsupported state | Precise provider, stock, payment-record or exceptional-order-administration requirement | No generic paid override |

Barion incomplete/expired/cancelled/failed/authorized states are displayed precisely and never treated as paid. Missing payment ID requires a real customer payment. Closed/inconsistent provider markers and custom callback handlers are not bypassed. Safe diagnostic state/time is stored in `_appleklinika_barion_review`; no raw provider payload, API secret or fabricated transaction is persisted.

The new recovery rollout is limited to positively identified LOCAL/TEST environments. Active Barion mode must be `test`. Production behavior is not enabled by this pass.

### Audit

Existing order history and today's activity record employee ID/display name, order, action, unchanged fulfilment from/to, timestamp, old/new authoritative facts, reason/source and result. Internal WooCommerce notes remain non-customer notes. Shipping corrections are readable as old method/instance → new method/instance in activity history. No analytics storage or customer-visible staff history was added.


### Persistence and retry contract

`ResolveOrderReview` retains the existing per-order `WooOrderMutex`, authorization, order/action nonce, POST/redirect/GET and explicit confirmation. Shipping persistence now follows this bounded sequence; payment commands remain on their existing path:

1. Validate fresh canonical order facts, completed shipping-review revision, configured method, charges and downstream barriers under the order lock.
2. Save `_appleklinika_shipping_review_operation` with a stable operation ID, `prepared` stage, previous facts, exact selected method/instance/title, original employee ID/name, decision time and internal reason. Force a fresh metadata read and verify the saved record **before** writing shipping.
3. Update the authoritative Woo shipping item in place (or create a zero-charge item only when allowed), preserve amounts/taxes, and verify the target plus protected totals/address/item identity. Refresh the existing shipping-title snapshot.
4. Append `_appleklinika_backoffice_history` once per `review_operation`, verify it from storage, ensure one private Woo note with the same operation marker using `wc_get_order_notes`, and publish once to the existing current-day activity index. The Woo note API is important: raw frontend comment queries normally exclude order notes.
5. Mark the intent `complete` only after these writes verify. Prior completed history is never rewritten or removed. The current operation record is reused only after completion; previous completed decisions remain in ordinary history and internal notes.

The operation ID includes order, employee, displayed fingerprint, choice and reason. The fingerprint also includes the number of completed shipping corrections, preventing an old form from becoming valid again after an A → B → A sequence. Identical POSTs resume or return the existing result. An old duplicate discovered in history cannot undo a newer decision. A new reason/choice with a stale fingerprint is rejected.

Before intent verification fails: no canonical shipping write begins. After intent verification, any exception or unverified history/note/activity save leaves the operation pending and reports explicitly that shipping may already have changed. Normal Back Office fulfilment, another correction and manual payment review are blocked. Existing invoices stay visible and independent. The employee sees current/target method, original actor/time/reason, and the nonce-protected **Mentés és napló egyeztetése** action. GET never repairs data.

Reconciliation applies the original decision only if the exact original source is unchanged and the original configured choice still matches. If the canonical target is already present, it only completes the missing snapshot/audit projections. If a completed history event exists, it never reapplies shipping. Partial/conflicting canonical data or changed configuration fails closed with exceptional-order-administration instructions; the form does not overwrite an intervening change. No invoice, payment, provider or notification operation is added to the correction.

Existing TEST transition/document/note mutexes are preserved. The older local adapter's history writers now also acquire the same per-order lock and read fresh order data, preventing a concurrent local note/document append from overwriting a correction's history. The existing daily transient uses a short shared mutex to prevent concurrent lost updates; correction entries are deduplicated and verified. The history field `finalized_by` identifies the employee who first persisted that history event, **not** a claim about the later projection-completion time; its visible label says “Naplóbejegyzést rögzítő munkatárs”. Original decision time is separate in `review.requested_at`.

### Correcting an earlier manual decision

The collapsed additional action is available only when the current shipping item matches the latest manual correction's saved result. It is not a general checkout-method editor or an Undo. The calculated preview includes both current and intended canonical methods. Another save requires a newly entered reason and confirmation, preserves every previous event, and re-evaluates the ordinary workflow from fresh data.

Simple reassignment is blocked by recorded GLS/MPL label, parcel or tracking evidence; unresolved GLS attempts (except explicit blocked/retryable preflight results); closed/late orders; prior packing, pickup acceptance/preparation, handover/delivery or label-generation actions; and recorded cash handover. Provider evidence gives the specific instruction to reconcile the existing shipment and any required cancellation with the provider first, then use exceptional order administration. No provider record is deleted, cancelled or reset by this form. Existing invoice metadata alone is not a shipping-correction blocker.


### Remaining boundary

This is deliberately **not an atomic transaction** across shipping-item storage, order metadata, comments, transient/object cache or arbitrary Woo save hooks. Durable verified intent is the recovery evidence during an interruption; successful completion requires verified projections. A partially written method/instance/title that matches neither source nor target, an externally changed source/configuration, or writers outside the Back Office mutex needs explicit administrator reconciliation. No automatic rollback, guessed continuation, historical audit migration or cross-system compensation is introduced. Existing durable history is the authoritative audit; today's transient can expire or be evicted and is not an archival guarantee. A later-day reconciliation does not fabricate a new-day correction count for an already recorded older event. Native payment/Barion audit persistence was intentionally not redesigned in this shipping-only reliability pass.


## Verification boundary

Run `make test` and `make quality` before integration. PHP lifecycle/review tests run with the network disabled and provider doubles. The nine review-preview JavaScript assertions use a local DOM double. Quality currently has placeholder lint/static targets and is not a full static-analysis claim. Actual TEST acceptance uses guarded native Woo QA artifacts with outbound HTTP/mail blocked, followed by API cleanup. It does not replay payment, invoice or carrier calls.
