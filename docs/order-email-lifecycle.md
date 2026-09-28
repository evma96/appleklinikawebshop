# Order and transactional-email lifecycle — implementation record

Date: 2026-09-16. Branch: `feature/order-email-lifecycle`.

## Status and scope

Implemented and exercised in an isolated LOCAL fixture. **Not deployed to TEST;
the requested live TEST-provider acceptance is still blocked.** TEST SSH timed
out, and the installed invoicing plugin still presents its PRO activation screen.
No TEST settings, orders, provider accounts, subscriptions, or production state
were changed in this phase. No commit, push, or merge was performed.

The worktree starts at `develop/post-deploy` commit
`067d63cbbdd38dc52a95d14fb3bd6ec309088d2d`. It imports the committed Back Office
baseline from `5f4a68c07deb469eca4c92a231078446eed23f6b`. The separate Back Office
worktree's uncommitted operational changes were not overwritten or silently
included. Its existing TLS-verification repair was explicitly carried forward
for the invoicing transport. Before any TEST copy, compare the installed Back
Office files with this baseline and reconcile any differences; do not blindly
replace a newer installed build.

## Audit correction and activation root cause

Számlázz.hu TEST accounts receive the provider's #profi capabilities. The issue
must not be described as a missing live subscription or unavailable TEST feature.
However, the installed Viszt Péter WooCommerce integration checks a **separate
plugin license activation** (`WC_Szamlazz_Pro::is_pro_enabled()`). The Számlázz.hu
Agent key is not that license key.

On 2026-09-16, the actual TEST WordPress automation page still displayed
"PRO verzió vásárlása" and "Aktiválás". The previous read-only audit found no
enabled PRO flag or configured automatic-invoice rule. This establishes the
immediate configuration gate, but does not establish whether an existing plugin
license is absent, inactive, or registered to a different site. Martin's answer
about the existing plugin/test license is still required. No license flag was
forged, localhost entitlement spoofed, or subscription purchased.

Sources:

- [Számlázz.hu TEST capabilities](https://docs.szamlazz.hu/hu/third-party-invoicing/testing)
- [WooCommerce integration licensing](https://visztpeter.me/woocommerce-szamlazz-hu/)
- [Native automation triggers](https://visztpeter.me/kb-article/woocommerce-szamlazz-hu/egyedi-automatizalas/)

Provider TEST document emails are not evidence of delivery to the requested
customer address; provider TEST routing and application SMTP delivery are separate.
This corrects the interpretation of the 2026-09-15 audit without discarding its
observed current-configuration evidence.

## Invoice trigger and failure handling

The new application handler runs on `woocommerce_payment_complete`, priority 20,
after Woo has saved the paid order. It invokes the existing **licensed**
`WC_Szamlazz_Automations::on_payment_complete()` implementation, with exactly one
native unconditional `payment_complete` → `invoice`, paid=true rule. It does not
implement a second invoice client or change Barion's callback/stock logic.

For managed orders, the provider's ordinary status/payment hooks and manual/bulk
invoice action cannot bypass this serialized path. A MariaDB named lock serializes
invoice/email/fulfillment handling per order. The order records a durable invoice
attempt **before** the provider call. An existing invoice number is never invoiced
again, even if its PDF is missing. A failed/uncertain/interrupted attempt requires
provider reconciliation before an operator explicitly resets the attempt; page
refreshes and duplicate callbacks never retry it blindly. This deliberately favors
duplicate prevention over automatic retry when provider acceptance is unknown.

Invoice failure leaves the order paid, blocks the positive order/invoice email,
records a private order note and a sanitized Woo log entry, and appears in Woo
admin. Only order IDs and diagnostic codes are logged by the new code. Provider
XML debug logging is disabled while rollout is enabled. TLS peer and hostname
verification are enabled; temporary XML is deleted on transport failures as well.

## Customer emails

| Event | Eligibility and source | Existing messages |
| --- | --- | --- |
| `paid_invoice` | Managed paid order plus stored invoice number and readable provider-resolved PDF; subject “Köszönjük, megkaptuk a rendelésed!” | Customer processing and completed messages are suppressed only for managed orders. Provider XML `vevo/sendEmail` is false for the managed invoice. |
| `carrier_handoff` | An audited `handed_to_gls` action exists, current operational state is handed/delivered, order is paid, and valid GLS tracking exists; subject “Úton van a rendelésed!” | Label creation, tracking generation, packing, preparation and final delivery generate no new customer email. |

Two `WC_Email` subclasses reuse Woo sender settings, HTML/plain templates,
localization, transport and admin email settings. Email 1 takes products, prices,
totals, payment/shipping methods and addresses from the Woo **order snapshot**,
attaches its invoice PDF and links to My Account. Email 2 includes all validated
tracking numbers/links and the account link. No new SMTP provider is installed.

Per-event order metadata stores `sending`, `accepted`, `failed` or `uncertain`,
attempt count and time. `sending` is persisted before transport. Accepted messages
never resend. A known rejection has at most three attempts; uncertain/crashed
sends require investigation. Worker lock failures defer at most three times.
Queue duplication is harmless because order-level state and the lock enforce
eligibility. This is application send-acceptance protection, **not a guarantee of
exactly-once inbox delivery**.

Admin new-order/error/cancellation messages, customer failures/refunds/on-hold,
account/password messages and explicit customer notes retain their existing
settings. Manual “order details” remains manual. Legacy orders and unrelated
payment methods keep their prior email behavior.

## One fulfillment source and tracking repair

`_appleklinika_backoffice_state` and its existing history remain the operational
source of truth. `ChangeFulfilment` is the shared application handler used by Back
Office and the TEST Woo admin panel. It validates payment/delivery mode, checks
the submitted expected state, records actor/reason/history and serializes writes.
Admin controls require order-specific nonces plus Woo management/order permissions.

Progress: `new` → `preparation` → `packing` → `ready_for_shipping` →
`handed_to_gls` → `delivered`. The last state is a separate, manually confirmed
delivery completion, pending Martin's confirmation of that operational convention.
The legacy `completed` value continues to mean handoff; it was not reinterpreted
as delivered. Pickup and internal-problem handling remain separate. The native
Woo payment/status field is not overwritten by these operational transitions.

Corrections to internal stages require a reason and remain in the same history.
They cannot invent carrier/pickup handoff, resend a previously accepted message,
or undo an already-issued invoice/parcel. The panel follows existing eligibility:
native Woo closed/cancelled/refunded orders are not reopened automatically.

The provider account helper now resolves `_gls_tracking_codes` first, supports
multiple numbers, removes duplicates/malformed values, and falls back to
`_gls_tracking_code` for legacy orders. Both the GLS account renderer and the new
handoff email use that helper through `OrderDocuments`. It uses the active account
country and the existing GLS tracking destination. Empty GLS panels are omitted;
provider customer-detail hooks no longer add tracking to unrelated/admin emails.
These two GLS files and the invoicing XML transport are vendored patches; retain
and re-verify them when updating those vendor plugins.

## Explicit TEST rollout prerequisites — not applied yet

1. Restore TEST server access and verify the exact installed code/base; back up
   only affected TEST files/settings privately. Do not touch main or production.
2. Activate the existing Woo plugin PRO/test entitlement through its normal UI.
   Verify Barion TEST, GLS Sandbox and the existing Számlázz.hu TEST account.
3. Configure the one native paid-invoice rule described above using the provider
   UI. The stored `paid` field is boolean true, not the HTML checkbox string.
   Avoid conflicting native automations or extra delivery documents.
4. After independently confirming the actual Agent account is TEST, store its
   SHA-256 fingerprint in `appleklinika_szamlazz_test_agent_sha256` without logging
   the Agent key. Readiness checks the current default key; the XML filter also
   checks the actual resolved key, including account-routing overrides, before
   network transmission. A changed/unverified key fails closed.
5. Set `appleklinika_lifecycle_enabled_at` to the rollout Unix timestamp. This
   enrolls new Barion orders only; `woocommerce_pre_payment_complete` persists
   `_appleklinika_lifecycle_version=1`. Disabling new enrollment does not make
   previously enrolled orders fall back to duplicate native customer messages.
6. Host must be `teszt.appleklinika.com`, `localhost` or `127.0.0.1`, with a
   non-production WP environment. The new admin mutation path enforces the same
   restriction; GLS label calls additionally require sandbox mode.
7. Run the authorized focused TEST lifecycle, capture actual provider references,
   two application mail acceptances, account state/tracking, replay and failure
   evidence; clean only the created QA data. Verify Action Scheduler execution.

Do not blindly clear an uncertain invoice/send marker: first inspect the provider
and mail transport to determine whether the external side effect already happened.
The persistent records must survive retries, redeploys and operator corrections.

## Verification and limitations

- `make test-order-lifecycle`: **235 assertions passed**, plus syntax checks; isolated domain/repository/rendering/environment/
  tracking tests and PHP syntax checks, no network or shared database.
- **30 assertions passed** in disposable WordPress 7.0.2 / WooCommerce 10.7.0 / MariaDB fixture: real HPOS Woo
  orders, real mail rendering and database lock, captured `pre_wp_mail` transport.
  Repeated Woo payment events reduced stock once; one primary and one handoff
  message were captured; intermediate steps, label metadata and corrected/replayed
  handoff did not duplicate mail. Missing plugin activation blocked invoicing.
  A synthetic local PDF/reference was inserted to test invoice-ready mail; this
  is **not** proof of automatic Számlázz.hu document generation.
- Browser: generated LOCAL primary/shipping email and account progression inspected
  at desktop/mobile widths. Fixture branding/language settings are not TEST design
  acceptance. Final TEST screenshots and the interactive Woo-admin action still
  require a deployed TEST build.
- Root `make test` / `make quality` currently contain placeholder unit/integration/
  lint/static targets; their success must not be presented as a full project suite.
- Focused actual Barion → Számlázz.hu TEST → GLS TEST E2E: **NOT RUN in this phase**.
  SSH and PRO activation are blockers. No QA orders were created on TEST.
- Existing Barion source/callback idempotency and stock implementation are unchanged.
  Previous integrated-provider proof remains historical evidence, not acceptance of
  the newly added automatic invoice/email path.
- No configured SMTP transport/inbox proof was established. Decide TEST mail
  transport/recipient handling explicitly, then verify delivery separately. No
  production SMTP provider was selected or installed.

The prior corrected storefront media-permission issue remains a historical resolved
finding. It is not reopened by this task. Known pre-production legal/sample-content
blockers are unchanged and no legal wording was edited.
