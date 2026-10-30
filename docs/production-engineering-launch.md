# Production engineering launch — controlled draft

This is a future runbook, not authorization to deploy. Main/production and legal
wording are untouched. The exact approved application commit and current TEST
identity are recorded in [engineering acceptance](qa/preproduction-engineering-20261015.md).
Record that full SHA in a private release manifest before starting; resolve it
from the reviewed clean public history, never from an old recovery bundle.

## Hold points before any production write

1. Obtain explicit production authorization, approved target hostname/domain,
   release SHA, maintenance window, named operator and rollback decision owner.
2. Use the [default-OFF production lifecycle activation procedure](production-lifecycle-activation.md).
   Its offline engineering proof closes the earlier HIGH code-readiness gap; actual
   production account provisioning, zero-data evidence and human activation remain
   separate required steps. Never spoof staging or copy TEST credentials/options.
3. Require approved legal pages/cookie inventory and owner decisions as separate
   prerequisites. Do not author legal wording in deployment code. Keep registered
   company seat separate from store/pickup: 6722 Szeged, Jósika utca 4.
4. Verify remote Git branch and approved tree, reachable secret scan, GitHub retained
   old-object purge status. Main is not part of this workflow without separate approval.
5. Require a complete independently recoverable encrypted backup, verified checksum,
   decryption and isolated application restore; record UTC set/source SHA. BX11 and
   encrypted off-Mac key custody must be completed. Never assume a server-local copy
   survives server loss. See [DR](disaster-recovery.md) and [BX11](storagebox-test-preparation.md).
6. Obtain real environment-specific credentials/licenses privately: Barion live,
   Számlázz service plus Woo PRO/site licence, GLS, authenticated SMTP identity,
   DB/WP salts, monitoring destinations, backups/SSH. No TEST key/ID/state migration.
   Existing .hu SMTP QA evidence does not authorize a nonexistent .com sender.

## Deploy in maintenance with outbound side effects held

1. Verify the intended target by hostname, URL and environment before each write.
   Record current source/containers/config hashes and matched DB/media snapshot.
   If real data already exists, STOP: the fresh empty-catalog procedure is inapplicable.
2. Stage immutable exact application code and reviewed/pinned dependencies; preserve
   required private operational overlays. Do not run demo seeders, QA scripts or old
   migrations/imports from a quarantined worktree. Validate PHP and proxy syntax.
3. Create a fresh database with independent credentials/salts. Activate Woo and custom
   plugins using supported APIs. Use approved HPOS storage. Buyback remains OFF at launch; do not publish its
   submission route or activate a price book as part of launch. Retain source/schema
   for a separately approved future rollout, without importing TEST request history.
4. Recreate reviewed pages/categories/attributes/menu and public settings through an
   explicit allowlist, remapping page/media IDs. Approved homepage/contact/category
   photos may remain; demo-product-only photos and product relationships must not.
   Never import a whole TEST SQL dump/options table/uploads tree/user table.
5. Use [data separation](production-data-separation.md) and the read-only
   `catalog-launch-plan.php` inventory. It defaults to dry-run and refuses production;
   there is **no destructive production apply command**. Do not weaken this guard.
6. Prove all-status zero products, variations, orders/refunds/drafts, QA customers,
   sessions, stock reservations and provider IDs/documents/retry jobs. Keep only named
   approved employee accounts. Stop on unexpected data; never blanket-delete it.
7. Configure approved production domains/callbacks/Back Office origin, TLS, cron,
   permissions and secrets privately. Production indexing policy needs its own
   approval; never copy TEST disallow/noindex policy automatically. Keep transactional
   effects held until the controlled acceptance window.

## Functional acceptance before opening

- Homepage/hero/categories, empty search/shop/featured blocks, stale product 404,
  Contact/map/navigation, account/login and Back Office on desktop/mobile.
- Barion is the only webshop payment method for GLS, MPL and free personal pickup.
  BACS OFF (instructions empty), COD and pay-at-pickup unavailable to webshop
  checkout. Direct in-store cash recording is a separate Back Office operation.
- GLS point/locker 1,490 and home 1,990 HUF; manual MPL point 1,040 and home 2,090;
  pickup 0. No generic free courier. No invented weights or advanced MPL API work.
- Correct store address, legal page mappings and approved content; no localhost,
  TEST recipient override, sandbox endpoint, test tracking/PDF or stale source pin.
- Named staff least privilege, shared Woo/Back Office fulfilment state, correction
  history and customer account presentation.
- Invoice exactly once; acknowledgement without false paid claim/PDF; accepted email
  with PDF; no mail on packing/label; shipping email only after logged handoff.
  Check retries/stock/order/shipment idempotency in the approved activation rehearsal.
- Authenticate SMTP, correct From/Reply-To, actual controlled Inbox delivery and
  SPF/DKIM/DMARC. Keep the existing failed/refund/account/password email paths.
- No fatal/PHP leak/first-party 4xx/5xx, browser error, mobile overflow or trapped focus.

## Open and first real activity (requires separate owner authorization)

Only after the zero-catalog gate may Martin add each physical device through normal
Woo admin: simple physical product, category/model/storage/color/grade, factual
battery health, warranty/equipment, real price, managed stock 1/backorders off,
individual sale, fresh main photo/gallery. No customer battery-choice UI.
Verify card/detail/gallery/selectors/cart and no internal IMEI disclosure.

Authorize one controlled real order separately, including recipient, amount and
provider effects. Verify Barion/payment/invoice/customer delivery/GLS and prepaid personal-pickup
paths as applicable; no real transaction is implied by this document. Confirm
monitoring and the first scheduled independent encrypted backup after launch.

## Rollback decisions and sources

- Before accepting traffic/orders: stop on wrong host/SHA, missing secrets/licence,
  failed migration, non-empty seed, broken checkout, incorrect provider mode, email
  duplication, missing backup or failed health. Keep maintenance/outbound holds.
- Code-only regression with unchanged compatible schema: restore the exact prior
  approved code/config manifest, validate, reload and recheck. Never guess a SHA.
- Schema/data corruption: isolate traffic/providers first. Restore the **matched**
  encrypted DB/media/config backup per DR, including original salts needed to decrypt
  plugin secrets. Re-create/rotate credentials only when recovery requires it and
  with the owner's authorization; keys come from independent secure custody.
- After real orders exist: never blindly restore an old DB and lose accepted orders
  or repeat invoices/charges/shipments. Preserve evidence, reconcile provider records,
  agree the recovery point with the owner, then restore selectively under supervision.
- Record UTC times, source hashes, backup set and checks; never secret values or
  customer payloads in the public report. Retest the affected surface before reopening.
