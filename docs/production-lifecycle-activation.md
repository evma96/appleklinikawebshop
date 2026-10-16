# Production lifecycle activation — default OFF

This is engineering preparation, not production authorization. The implementation
is validated with fake providers in an isolated LOCAL Woo database and deployed to
TEST only. No production credentials, DNS, provider transactions or legal wording
are supplied or changed by this work.

## Guard map

| Previous condition | Blocked surface / reason | Production rule now |
| --- | --- | --- |
| `LifecycleConfiguration::isTestEnvironment`: canonical localhost/TEST home, non-production WP environment | Acknowledgement, acceptance, lifecycle email registration/enrolment; prevent unreviewed rollout | Preserve this path verbatim for TEST. Production requires the central readiness policy AND explicit activation. |
| TEST rollout timestamp and validated checkout metadata | Retroactive mail for old/admin/imported orders | Separate private production activation timestamp; validated checkout only; no imported orders at launch. Persisted TEST markers cannot authorize production. |
| Lifecycle store / fulfilment store environment guard | Invoice/mail persistence, staff transitions, correction, cash receipt | Central gate before managed mutation; Woo-admin and Back Office share the same store and policy. |
| Számlázz PRO + one paid `payment_complete` invoice rule + verified TEST Agent fingerprint | Unlicensed/wrong account/early/duplicate invoice | Keep vendor license checks intact. Require real stored PRO activation/key, explicitly approved live account/site license and separately pinned resolved Agent key; reject the known TEST key. |
| Invoice attempt ownership and durable invoice/mail ledgers | Duplicate native invoice/processing email, uncertain retry | Unchanged. Gate direct XML before debug/file/cURL and validate resolved account, including routing. No separate provider customer email. |
| GLS sandbox check and sender/account readiness | Accidental real parcel from TEST | TEST still sandbox only; production requires explicit `production` mode, complete sender/account, private approval and final transport guard. Labels never imply carrier handoff. |
| Barion native TEST/live setting and callback ID/closed checks | Native payment and stock effects; previously no managed production authorization gate | Private live key/payee approval, exact HTTPS production API, guard before cURL for Start/GetPaymentState/refund etc.; Woo pre-payment/stock gates. Existing callback/idempotency logic unchanged. |
| Native Woo stock markers | Duplicate stock reduction | Unchanged; production OFF rejects payment and prevents stock reduction. Offline proof uses actual Woo HPOS and native callback handler. |
| Cash rollout, pickup method, staff acceptance and cash receipt marker | Courier COD / premature paid claim/invoice | Same workflow behind production gate: acknowledgement, explicit stock-checked acceptance, invoice only after actual recorded pickup payment. |
| MPL API TEST-only switch | Unapproved postal API calls | Manual compliance mode required; production MPL API NOT APPLICABLE and blocked. Prices/business model unchanged. |
| TEST monitoring/backup host/environment guards in operations scripts | Wrong-target operational actions | Unchanged. Production requires a separate scoped operator attestation of backup/restore/key custody, monitoring recipient/target and cron. Application does not install/start operational jobs. |
| Public-surface restrictions and independent private operational SMTP guards | Info exposure / unauthenticated mail fallback | Preserved. Production must have the mandatory MU safety bootstrap and exactly one approved authenticated FluentSMTP connection, forced identity and no fallback connection. |

The bootstrap is `wordpress/wp-content/mu-plugins/appleklinika-production-safety.php`.
It remains effective when the normal Back Office plugin is disabled/missing. Missing
safety dependencies block mail, checkout/payment/stock and HTTP. Direct provider
cURL adapters fail closed if the guard class is missing. Deployment must preserve
this MU file and the thin vendor transport adapters; vendor updates require the
same contract tests. This is application protection, not a replacement for egress
isolation, maintenance mode, permissions or a trusted operator.

## Private configuration and capability report

`APPLEKLINIKA_PRODUCTION_CONFIG_FILE` is a **wp-config constant**, not a copied TEST
option or new environment variable. It points outside the web root to a regular,
non-symlink JSON file, maximum 64 KiB, mode 0600 or 0640 (no group-write/world access),
readable by PHP. Missing/malformed/wrong-schema/unreadable/private-permission failure
means OFF. No private values are stored in Git. The disabled example is
[`examples/production-activation.example.json`](examples/production-activation.example.json).
The example origins are proposals; a human must approve the actual domains.

`wp appleklinika production-readiness` is read-only: no providers, email, mutations,
cleanup or activation. Exit 2 indicates NOT READY. It reports READY / NOT READY for:

- production environment, matching canonical HTTPS home/siteurl/staff origin and
  request hostname; no TEST/localhost/IP/.local origin;
- required modules and mandatory MU bootstrap;
- explicit Barion live mode, non-empty key/payee, approved identity;
- Számlázz legitimate PRO/site configuration, exactly one paid invoice automation,
  live-account attestation and approved resolved Agent key;
- GLS production account and complete sender, approved identity;
- one encrypted authenticated SMTP route, forced Apple Klinika identity and approved
  sender credentials; no alternate/PHP fallback configuration;
- three customer emails enabled, cash rollout, BACS OFF, cash restricted to pickup;
- manual MPL, separate production operations prerequisites;
- fresh zero-data inventory: all-status products/variations/orders/refunds/drafts,
  non-approved users, any customers/subscribers or named QA users, sessions/persistent carts, stock reservations and pending provider jobs (Action Scheduler and WP cron).

MPL API reports NOT APPLICABLE. Presence/approval is not a real provider availability,
license-server, inbox, backup-storage or legal test. Számlázz uses the same Agent
endpoint for TEST/live: **the account's production capability and site license must
be verified through the legitimate provider workflow by the human operator**.
Operational timestamps/target/recipient are explicit attestations, not fabricated
remote checks. Do not mark them verified until actual separate production checks.

`--fingerprints` outputs only keyed, site-salt-bound identity digests for private
operator approval. It refuses TEST export. It neither supplies nor approves secrets.
Never blindly run it and adopt its output: first verify each account is production
and the sender is authorized. Changing credentials invalidates approval until the
new identity is deliberately verified. A cloned TEST manifest fails canonical/site
binding; TEST mode/key cannot silently become live. Fresh production salts are required.

## Human-only activation procedure (not executed)

1. Obtain explicit owner authorization, legal/business approval, target domains,
   exact approved SHA, named operator/window and recovery owner. Keep maintenance
   and outbound holds. Prepare a **fresh** database; never restore TEST as launch seed.
2. Deploy exact approved code/dependencies including MU safety bootstrap and provider
   transport adapters. Configure separate production credentials through supported
   private/provider workflows. Obtain legitimate Woo PRO/site activation; no bypass.
   Configure shipping/payment model, approved sender/Reply-To, own cron, monitor,
   independent backup and restore/key custody. Preserve native failed/refund/account/
   password emails; they use the same approved transport once activated.
3. Place the private JSON using the disabled example, set the wp-config file-path
   constant, populate approved origins/staff IDs, account attestations and completed
   operations evidence. Keep `enabled=false`, `activated_at=0`. Never use TEST keys,
   recipient overrides, provider IDs, old incident state, sessions or SQL dumps.
4. Verify provider identities in their official accounts. Capture `--fingerprints`
   privately and approve each digest in the JSON. Run the default readiness command;
   every required capability must be READY. Fix the named reason; do not weaken a gate.
5. Freeze catalog/order/user/session writes under maintenance. Run
   `wp appleklinika production-readiness --launch-evidence` and save the returned
   signed zero-count object as `launch_evidence` in the private JSON. The command does
   not change WordPress/configuration. Inventory errors refuse evidence; nothing is
   deleted. No application/test data may change between this check and activation.
6. With final owner approval, atomically set `activated_at` to the current Unix UTC
   timestamp (within one hour after evidence) and `enabled=true`. Do not alter the
   signed evidence. If an emergency OFF constant was used during preparation, clear
   it only within this same authorized activation step. Confirm the runtime policy
   reports ON. The evidence records that
   launch was empty; legitimate products/orders added **after** activation do not
   disable the shop. The default command remains a fresh *prelaunch* empty-data audit,
   so a legitimately populated live shop is not expected to pass it later.
7. Run separately authorized production smoke checks: URLs, least privilege, checkout
   options, private document access, authenticated transport/delivery and first scheduled
   backup/monitoring. A real payment/invoice/parcel requires its own approved controlled
   transaction. Only after the empty-launch gate may Martin add real physical devices.
   Offline/TEST success is not evidence of actual production credentials or live delivery.

## Emergency OFF / rollback

Atomically set private JSON `enabled=false`, or set wp-config constant
`APPLEKLINIKA_PRODUCTION_EMERGENCY_OFF=true`. Removing/unreading the JSON also fails
closed. No code rewrite/deploy is needed. This stops **new** managed payment/invoice/
fulfilment/mail attempts and stock reduction; it does not undo a provider request
already in flight. All application mail is held, including account/password mail,
while emergency OFF is active. Keep a separate out-of-band monitor/admin channel.

Put maintenance in place; preserve durable ledgers and provider references. Inspect
pending/uncertain operations and reconcile with provider records before reopening.
Do not blindly replay callbacks or restore an older database after real orders.
Restore code/config only from the recorded exact release/backup manifest; never
roll back to a build without the guards while outbound traffic remains enabled.
Changing the receipt/activation time is not a routine credential-rotation step.

## Offline proof / reproducibility

`make test` includes pure capability/invalid-config/transport boundary tests, existing
workflow/idempotency/security tests and PHP syntax checks with network disabled.
`scripts/test-production-lifecycle.sh /absolute/path/to/woocommerce` uses an already
installed Woo source (no downloads), a fresh Docker internal-only network/DB, no public
ports, blocked WordPress HTTP and disabled direct cURL/PHP mail transport. It runs
actual Woo HPOS/orders/stock and the native Barion IPN handler, with fake provider and
mail adapters. Fresh random fixture DB/admin credentials are never printed. A trap
removes containers, volumes, credentials, PDFs and QA data on success/failure.

This proves the **engineering activation/lifecycle path**, not real production
provider/inbox/GLS handoff. Existing TEST acceptance remains separate evidence.
