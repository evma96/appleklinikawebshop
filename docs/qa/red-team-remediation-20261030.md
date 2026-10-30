# Final red team remediation — 2026-10-30

## Scope and baseline

Verified baseline: develop and actual TEST repository `928b9e63836241c0ae96293cd23a22d0f6c01065`. Scope H1/H2/H3/H4/M1 and the provisioning/public-opening sequence only. Main, production, legal PDFs and historical provider evidence remain untouched. TEST source is partly separately mounted; release verification must compare actual runtime files, not only Git HEAD.

## Fixes

- H1: all Store API `/wc/store/v*/checkout` methods are denied before dispatch while closed; classic checkout, pre-create and order-pay entry points also refuse writes. Missing mandatory safety dependencies fail closed. Authorized staff product entry remains available. Public-open approval and Coming Soon are separate from authenticated provider/callback recovery.
- H2: creation-boundary default for new taxable used iPhones is explicit `ak-used-iphone-margin`, HU 0% rate named K.AFA. New protection accessories use `ak-accessories-27`, HU 27%, without altering gross retail prices. Existing explicit/custom terms, historical orders and other families are not reclassified. Conflicting configured rates fail closed.
- H3: acknowledgement → paid/invoice confirmation → actual-handoff notification. A failed/uncertain prerequisite holds subsequent messages. Safe known failures retry within the existing bounded policy; accepted messages remain idempotent. Fulfillment itself is not blocked. SMTP acceptance is not an Inbox ordering guarantee.
- H4: authorized production recovery uses the verified provider identity, order lock, native payment/stock admission and existing audit. Lost callbacks and stale close markers are independently rechecked. Late captured payment against reassigned stock remains an actionable exception. Complete saved invoices repair interrupted local completion without reissuing; uncertain issuance is never blindly retried. An explicit authorized CLI can retrieve an already issued PDF after operator verification of provider/order/buyer/total, preserving invoice ownership attestation and history. Retry repairs interrupted local finalization without duplicate PDF reads/history. No new public recovery endpoint.
- M1: the exact safe BX11 scripts/templates/monitoring/retention/recovery implementation is versioned in the preceding logical checkpoint. Private configuration, SSH/encryption keys and archives are excluded. Existing automation remains installed; compare script hashes rather than replacing private configuration.

## Focused evidence

- `make test`: 2,166 reported assertions/checks plus 25 Python backup cases; PASS. Includes payment/stock safety, checkout allowlist, Back Office, lifecycle/recovery, scheduler, provider boundaries, security, legal PDF checks, review preview and offline operational checks.
- `make quality`: command PASS; lint/static targets remain placeholders, not full static-analysis coverage. Real PHP syntax checks run in the lifecycle/security suites; changed inventory PHP is checked separately.
- Disposable native WordPress/Woo HPOS production simulation: 86 assertions; actual REST draft boundary, activated-but-closed/Coming Soon, staff product creation, reviewed real-stock opening, native Barion callback 503/recovery/idempotence, invoice interruption, email failure dependency, actual handoff, pickup/staff cash, late capture rejection, existing PDF recovery/authorization/history. Provider adapters are fixtures on an isolated internal network; **zero external HTTP attempts**, no real payment/invoice/label/email. Docker containers, volumes and fixture documents are removed by the test trap.
- Native LOCAL creation/mixed-tax mapping: 24 assertions. Phone K.AFA gross 100,000; case 27% gross 8,000; glass 27% gross 7,000; total 115,000 HUF. Actual installed invoice-line mapping tested offline, including device identifier snapshot and exact general note. Zero provider/mail calls; all owned draft products/order and temporary tax configuration cleaned.
- Runtime pre-release: TEST staging, Barion TEST, GLS sandbox, verified invoice TEST pin, production activation OFF, BACS disabled, actual gateway allowlist Barion only for courier/pickup. COD remains internally registered for staff cash compatibility; never webshop-eligible. Existing #794/#795 protected by before/after fingerprints; #801 already absent in current database and not recreated.

## Release and rollback

Normal integration/push to `develop/post-deploy` only after passing tests. Preserve actual TEST plugin/MU/theme source rollback archive and baseline SHA before deployment, existing private Compose, media-enabled image, secrets and upload mounts. Deploy the exact integrated SHA via `appleklinika-test`; compare tracked runtime hashes, HTTP/WordPress/Back Office health, tax mappings, payment/shipping configuration, protected-order fingerprints, backup script hashes and timer health. Exact final SHA and post-release observations are stored in the external `red-team-remediation-20261030` evidence directory so no self-referential commit hash is required here.

## Remaining gates

H5 real LIVE provider credentials/site licence/configuration and separate production approval remain open. M2 business-job alerts remain distinct from healthy infrastructure/backup alerts. M3 independent encrypted key-vault custody remains pending; no physical drive is required. M4 GitHub historical object purge remains pending. No production readiness PASS or public-opening authorization is implied.

Initial production provisioning must prove empty QA-free seed data once. Thereafter staff may add reviewed real devices while public checkout is closed. Public-opening readiness permits that real stock, rejects unreviewed/demo/QA data and invalid unique stock, and requires the provider/backup readiness evidence. Never delete real inventory to satisfy the initial-state check.
