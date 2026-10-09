# Checkout acknowledgement and later acceptance

Date: 2026-10-08. Base: `f415c471755fb0467fd6c8daf5ffd3b7b94d2b0b`.
Branch: `feature/order-acknowledgement`.

## Scoped behavior

The existing Woo email infrastructure, SMTP transport, invoice adapter, per-order
lock and GLS handoff workflow are retained. No legal document, provider credential,
DNS entry or production setting is changed.

1. `woocommerce_checkout_order_processed` and
   `woocommerce_store_api_checkout_order_processed` record validated submission
   and synchronously dispatch `order_received`, before gateway processing/redirect.
   Only rollout-eligible genuine `checkout` / `store-api` orders are enrolled.
   Draft reads, imports, administrative creation, callbacks and page refreshes do
   not enroll. Persisted submission and per-event send state prevent duplicate POSTs.
2. Subject: **Rendelésed beérkezett**. The acknowledgement explicitly states that
   it is automatic, not final acceptance/confirmation, and does not itself create
   the contract. It includes order date/time, name, products, quantities, totals,
   chosen payment/delivery and the account link for registered customers. No PDF,
   payment-success claim or fulfilment-start claim is included.
3. The existing paid-plus-readable-invoice gate sends **Rendelésed visszaigazoltuk**.
   Its introduction explicitly confirms acceptance and successful payment. Expected
   fulfilment defaults to “Várható teljesítés: 1–2 munkanap” and is editable in this
   email's existing WooCommerce email settings. New submitted orders wait for the
   acknowledgement's transport acceptance; a successful receipt retry releases a
   waiting paid notification. Old enrolled orders have no new acknowledgement gate.
4. **Úton van a rendelésed!** still requires actual audited GLS handoff, paid state
   and valid tracking. Label generation and intermediate stages remain silent.
5. Native processing/completed and provider invoice-message suppression remain for
   managed orders. The new acknowledgement also replaces native customer on-hold
   acknowledgement, while preserving configured BACS instructions/bank details.
   Admin, failure, refund, password/account messages retain their original paths.

The same existing persisted `sending` / `accepted` / `failed` / `uncertain` records
apply to the first message. Known transport rejection allows at most three attempts;
uncertain delivery is never blindly resent. Application transport acceptance is not
an assertion of Inbox delivery or a mathematical exactly-once SMTP guarantee.

## Payment-method limits

The inspected TEST gateways are enabled Barion and BACS; cash/COD is disabled.
Submitted BACS orders receive the acknowledgement immediately. The later custom
acceptance requires paid state and a stored/readable invoice; native automation is
invoked only by the explicit Woo `payment_complete` path. A mere on-hold order
cannot confirm payment. Staff must first genuinely verify bank settlement; a manual
status-only change is not evidence that the invoice-trigger event was executed.

Personal pickup using Barion/BACS receives the same acknowledgement with pickup
information. Disabled cash/COD is not enabled or enrolled into a newly invented
contract-acceptance rule. Its future acceptance point (staff accepts unpaid pickup,
or only settlement at pickup) requires the owner's / VirtualJog's decision before
that gateway is introduced. Existing cash second-stage behavior is not changed.

## LOCAL evidence

- Offline lifecycle/security suites: 416 assertions; PHP syntax checks.
- Isolated real WordPress/WooCommerce database: 41 assertions, captured transport,
  synthetic invoice/tracking, no external network/provider/SMTP access.
- Memory-only email presentation: 122 assertions, seven scenarios, Hungarian text,
  long/multiple products, exact totals, pickup, configurable fulfilment, plain text,
  canonical customer links and single/multiple tracking codes.
- Browser: acknowledgement desktop 1280 and mobile 390; acceptance mobile 390.
  No horizontal overflow on inspected mobile templates.
- A real stale-object issue surfaced in BACS: a status callback retained an order
  loaded before the submitted marker. The native-email suppression adapter now
  reads the fresh persisted order, so the on-hold message cannot duplicate receipt.
- `make quality` remains placeholder-only; it is not static-analysis proof.

TEST deployment and actual three-message Inbox verification remain pending in this
LOCAL checkpoint. Prior two-message TEST acceptance remains historical evidence;
it must not be relabeled as acceptance of this new three-message flow.

## Legal-review inputs

TEST's currently linked terms (#660), privacy (#661), and cookies (#662) still
contain explicitly labeled sample wording. The current VirtualJog document was
requested separately for a factual comparison; no final legal text is inferred.
The cookie inventory must distinguish observed HTTP/browser evidence from configured
source behavior and unobservable third-party state. No cookie values/session tokens
or personal data belong in the shareable inventory.

## Read-only TEST inspection and cleanup (2026-10-08)

Private SSH through `appleklinika-test` works. TEST still runs the protected base
`f415c471755fb0467fd6c8daf5ffd3b7b94d2b0b`; no candidate deployment or new provider
transaction has taken place. Barion TEST, GLS sandbox and legitimate Számlázz.hu
PRO/TEST capability passed the non-secret preflight.

The [CSV inventory](test-cookie-inventory-20261008.csv) and
[readable inventory](test-cookie-inventory-20261008.html) review artifacts list
16 distinct names: six HTTP-observed cookie names, seven configured Sourcebuster
cookie names, and three configured Woo Blocks localStorage keys. Source hashes
match TEST. Google Maps and GLS map resources were observed, but their third-party
cookie jars were not available through the browser tool. Barion Pixel is disabled.
The exact VirtualJog comparison still requires the current authoritative document.

The dedicated cookie-inspection customer, unpaid checkout draft, basket, login
sessions and QA run option were removed through supported WordPress/Woo APIs.
Baseline comparison confirmed all 73 original orders, 16 original users, 123
product stock records and protected settings unchanged. The TEST mail guard is
byte-identical; cleanup made zero external provider requests. No QA account needs
to be retained while awaiting the explicit local checkpoint authorization required
by AGENTS.md. A future controlled three-message run must create its own scoped QA
session and must not reuse the removed draft.
