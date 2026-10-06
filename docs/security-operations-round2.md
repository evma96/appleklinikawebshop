# Security / operations Round 2 — 2026-10-05

Scope: TEST and an isolated LOCAL recovery rehearsal. Application source on TEST
remains `0746347ff9ec99d6d4bb614c1412b24adecf2b35`. No production/main, public
history rewrite, provider operation, licence-dependent lifecycle test or Low-finding
policy change. Round 1 source/security fixes were not reopened.

## Restore evidence

The verified encrypted recovery set in
`/Users/apple/AppleKlinika-Private-Backups/round1-20261005/` was actually restored.
The recovery key remained on the Mac; secret values were not printed or committed.

- New private directory: `/Users/apple/AppleKlinika-Private-Backups/restore-rehearsal-20261005/`.
- New project: `appleklinika-restore-round2`; independent database volume and networks.
- Loopback preview: `http://127.0.0.1:28082/`; no active LOCAL/TEST volume was reused.
- Database dump imported with the supported MariaDB client. Runtime plugin overlays,
  theme, persistent WordPress core, uploads, configuration and original secret files
  came from the backup, not a new checkout or another running site's database.
- WordPress and MariaDB used an internal-only Docker network. A separate loopback
  reverse proxy made the preview reachable without granting application egress.
  Its Back Office path rule reproduces the backed-up Caddy host-routing behavior.
- WP HTTP calls, mail and cron/async action execution were blocked. Browser CSP also
  blocked external resources/form destinations. Raw external TCP was tested and
  denied. Google Maps was intentionally blocked in this isolated copy.
- The restored version is correctly **6.8.6**, because the selected backup is the
  pre-Round-1 recovery point. Actual TEST remains 6.8.10.
- **122 published products, 73 orders, 1,072 media files**, fulfillment/state/history
  metadata and all protected business tables were restored and compared with the
  original dump. The encrypted SMTP setting decrypted correctly using restored
  salts/key files; only the success boolean was output.
- Browser verification: homepage/images, product, add-to-cart/cart, checkout render,
  My Account, Contact, authenticated Back Office queue, existing order #537 and
  its history. No order submission/payment/provider action was performed.
- A temporary isolated shop_manager with the Back Office capability was removed,
  together with its single checkout draft/session and temporary local auth helper.
  Original restored business-table contents still match the backup after cleanup.
- **Measured preparation-through-verification time: 17.9 minutes** on the existing
  Mac with Docker images available. This is evidence, not a guaranteed disaster RTO.
  A new machine/image download, key recovery and DNS cutover would add time.
- Rehearsal containers were stopped afterwards. The private recovery copy and
  database volume remain separate for reproducibility, with no active preview.

## TEST backup routine

Installed scripts are under `/usr/local/lib/appleklinika-ops/`; root-only runtime
configuration is `/etc/appleklinika-ops/backup.env`, paired with the safe example
`docker/operations/test-backup.env.example`. Only the public recipient certificate
is installed on TEST. The existing private decryption key remains on the Mac.

`appleklinika-test-backup.timer` runs daily at **03:15 UTC + 0–5 minutes jitter**
(05:15–05:20 Budapest summer time, 04:15–04:20 winter time). Persistent catch-up is
on. It is a systemd service, not an interactive shell or an open Codex task.

Coverage per set:

1. Supported single-transaction MariaDB dump of all WordPress tables, triggers,
   routines and events; no raw live database-directory copy.
2. Uploaded media, actual plugin/theme overlays, core/MU plugins and wp-config.
3. Compose/Caddy, source/version inventory, Docker volume config, required private
   secret files, SSH/WireGuard recovery material, OS config and the operations jobs.
4. Existing container logs, preserved through Docker's supported logs interface.

WordPress is briefly stopped to align database and files; it is restarted even
when the script fails. Do not run independent WP-CLI writes concurrently with a
backup. A lock prevents concurrent runs. The first successful set took **32 seconds**;
this conservative TEST schedule entails a short nightly HTTP maintenance window.
Production scheduling/quiescing is NOT activated or implicitly approved.

All four streams go directly to AES-256 CMS encryption. SHA-256 manifests and
nonempty/CMS checks run before atomic publication of a successful set. On the Mac,
every copied set is also decrypted in memory/streamed and checked for a complete
SQL dump, expected archive entries and required recovery files. Plaintext SQL is
not left on disk. The restore rehearsal necessarily materializes private runtime
configuration inside its separate private directory.

- Server routine sets: `/opt/appleklinika/backups/routine/`, latest **14 successful
  sets**. With daily operation this is about 14 days; extra manual runs count too.
- Independent Mac sets: `/Users/apple/AppleKlinika-Private-Backups/routine-test/`,
  latest **30 verified sets**. The preserved Round 1 set is outside this pruning.
- Mac LaunchAgent: `com.appleklinika.test-backup-sync`, every **15 minutes** and
  login/load, using `appleklinika-test`. It pulls missed server sets when reachable.
- Private Mac operational state: `AppleKlinika-Private-Backups/operations/status.json`.
  Events use a 200 KB rotating log with four archives. No message bodies/secrets.
- Existing Hetzner daily backups remain untouched as another recovery layer.

Expected server application-data RPO: **up to 24 hours + 5 minutes**, provided the
job succeeds. Expected independent-copy RPO under an awake, connected Mac is that
plus approximately 15 minutes/transfer time. If the Mac sleeps or WireGuard is off,
independent RPO is the age of its last verified set; do not promise a 24-hour
independent guarantee. Both server and Mac flag a successful-backup age over 30h.
Before production: approve an always-reachable independent destination and schedule,
and arrange safe off-Mac recovery-key custody. Do not put that key into Git/email.

### Restore process / commands

1. Select a complete dated set plus its SHA256SUMS. Obtain the existing recovery
   key through the private Mac recovery location; never paste it into a terminal.
2. Run `shasum -a 256 -c SHA256SUMS` inside the chosen set.
3. Decrypt streams using the key file (the path, not its contents, is an argument):
   `openssl cms -decrypt -binary -inform DER -in database.cms -inkey /private/key/path`.
   Pipe database output to the isolated MariaDB client; never redirect to a public
   SQL file. Decrypt files.cms to a tar reader rooted in a NEW private directory;
   reject absolute/traversal entries and do not activate archived SSH/firewall config.
4. Recreate the backed-up mounts with a separate Compose project/DB volume and
   loopback port. Preserve secret-file mounts/salts. Set LOCAL URLs/environment,
   disable cron/mail/HTTP, attach application/database ONLY to an internal network,
   and apply local-only CSP in the disposable copy before starting WordPress.
5. Import the dump, then start WordPress and the local reverse proxy. Translate the
   backed-up dedicated Back Office proxy route to the local preview route.
6. Compare business data and media hashes; verify pages, order/history and secret
   decryption without transmitting anything; remove disposable QA records.
7. Stop the rehearsal. Restore a live target only under a separate explicit change
   plan, with the correct security patch level and its own pre-change backup.

The exact executed preparation/start/verification recipes are retained in the
private rehearsal directory. The database/key and reconstructed configs are not
part of this repository.

## Monitoring and alert boundaries

`appleklinika-test-monitor.timer` runs every **5 minutes** and checks:

- root disk >=85% full or <2 GiB free;
- all three containers running and health where Docker defines it;
- HTTPS 200 through the actual local Caddy/TLS site;
- recent bounded PHP fatal/parse/warning/uncaught-error lines;
- explicit SMTP failure markers from the existing TEST mail safety hook;
- backup failure and last-success age over 30h;
- TLS verification and certificate expiry under 14 days.

Safe aggregate status: `/var/lib/appleklinika-ops/monitor.json`. Issue changes are
recorded in the systemd journal. The Mac independently checks public HTTPS and
fetches remote status; stale monitor state over 15 minutes and missing/stale
independent copies become local issues. It uses only the private SSH alias.

Nine fixture classification checks cover each failure category. A real initial
backup setup error was detected as `backup_failure_or_stale`; after its narrowly
scoped Git ownership-read fix, the monitor recorded `ok`. No artificial provider
failure, email or shipment was generated to test alerts.

**No unattended external alert-delivery channel has been activated.** A status
file/journal event is not proof of a human receiving a push/email. The Mac also
cannot observe or notify while asleep/disconnected. This is the remaining M8 gap.
Recommended next decision: approve an always-on off-server HTTPS/status-heartbeat
watcher and its notification recipient/channel, or a reviewed existing-service
alert integration. No paid signup or production mail transport change was made.

Other limits: the bounded scan can miss very high-volume/short-lived errors between
checks; it does not prove inbox delivery or provider business success. Independent
scheduled WordPress/provider log-file analysis is not claimed. Existing Woo file
logs were small (~183 KB across 74 files) and were not purged or broadly changed.

## Log rotation / operational safety

Actual TEST Compose now includes per-service `json-file` rotation:

- WordPress: 20 MB x 5 files, compressed rotated files (~100 MB before compression).
- MariaDB and Caddy: each 10 MB x 5 (~50 MB each before compression).

All three containers were recreated from existing images (`--pull never`) using
the same volumes/mounts/application source. Configuration comparison confirmed
only logging blocks changed. Active Docker LogConfig was verified afterwards.
Retention is size-based, not a promised number of days. No daemon-wide unrelated
policy change was made.

Before recreation, existing complete container logs and the original Compose were
encrypted and checksummed at `/opt/appleklinika/backups/round2-log-preservation/`;
a verified independent copy is on the Mac under the same private backup root.
Existing useful evidence was not simply truncated. Routine backups also retain
available logs in encrypted logs.cms.

A setup ordering/validation issue caused a brief early maintenance restart before
activation; the recovery trap restarted the original containers without recreating
or discarding their logs. The service-block matcher/preflight were corrected and
successful scoped activation was then verified. This was tooling/setup, not an
application regression, and is documented rather than omitted.

## Public historical media — assessment only

Exactly these three paths were affected under
`wordpress/wp-content/plugins/appleklinika-inventory/assets/demo/`:
`iphone-13-pro-1.jpg`, `iphone-13-pro-2.jpg`, `iphone-13-pro-3.jpg`.

Introduced by `bb412f71b33c3fdfad56ddf16de6af0c165b8745`; cleaned in the current
published develop tree at `0746347ff9ec99d6d4bb614c1412b24adecf2b35`.
The public-ref scan covered **51 actual refs and 185 reachable commits**. The old
three blobs exist in **184 commits and 50 public ref tips**, including old main
and the protected prelaunch tag. Annotated-tag peeled output is counted once.
Exact listings: `security-round2-affected-published-commits.csv` and
`security-round2-affected-public-refs.csv`. No ref was changed by this assessment.

All three initial-commit raw GitHub downloads returned HTTP 200 without credentials
and matched the historical blob bytes. This is actual downloadable exposure, not
only a locally retained Git object.

All three contain camera/lens serial identifiers and capture/edit timestamps; the
second also contains precise GPS. Its location is approximately **638 km from
central Szeged**, so it cannot be justified as merely Apple Klinika's public store
address. Exact coordinates, times and identifier values are intentionally omitted.
Copyright/creator metadata exists, but the repository does not establish consent,
photo provenance or whether the location is a home/public photo venue. These are
not SMTP/provider credentials; their risk is location disclosure and cross-photo
linkability, potentially concerning a third-party photographer.

Options:

A. Keep immutable public history and explicitly accept/document the residual risk
   after confirming provenance/location sensitivity. No collaborator disruption;
   the exposed files remain anonymously downloadable.
B. Coordinated full ref filtering and force push after separate explicit approval,
   including decisions for main, all affected branches and the protected tag.
   This changes many SHAs/signatures, invalidates existing clones/links and requires
   collaborator coordination and possible GitHub cache/support work. Filtering just
   develop is insufficient. Copies/forks already obtained cannot be recalled.
C. Publish a clean-history replacement repository and restrict the old repository
   if permitted. Update remotes/CI/docs and collaborator workflows; old forks/caches
   can still persist. Migration alone while the old repository stays public does
   not remove the exposure.

Recommendation: **do not initiate an immediate global rewrite without provenance
and owner approval**. Resolve the provenance/location question first. If a private
location/identifier exposure is confirmed and risk acceptance is unsuitable, B or
C is justified as a separate coordinated privacy task. M6 stays open meanwhile.

## Remaining findings and launch boundary

Original audit residuals after this round: **Critical 0 / High 0 / Medium 2 / Low 5**.
M7's TEST restore-and-routine gap is closed with the documented conditional Mac
RPO. Production independent-copy/key-custody decisions remain launch prerequisites.
M6 (historical photo privacy) and M8 (unattended actionable external alert delivery)
remain open; M8 is partially remediated by active checks and bounded logs.
Low policy findings remain unchanged and are not automatically escalated by this
round. The existing sample legal wording and Woo Szamlazz PRO/invoice lifecycle
acceptance remain separate launch gates. Production backup/monitoring activation
needs a separate approved plan; this TEST work does not authorize it.

No commit/push or application deployment is part of this round; operational
source/docs remain reviewable on `feature/security-operations-round2`.

References:
- https://docs.docker.com/reference/compose-file/networks/#internal
- https://docs.docker.com/engine/logging/drivers/json-file/
- https://docs.docker.com/engine/logging/configure/

## Final execution evidence

- Initial routine set: `20261005T114612Z` (32 seconds).
- Final routine set after timer/log setup: `20261005T115420Z` (28 seconds).
  Server checksums passed; the Mac LaunchAgent independently copied/decrypted it:
  61 database tables, 23,260 archive entries, required recovery files present.
- Both routine copies and the separate pre-rotation log archive are independently
  verified on the Mac. Private directories are 0700; runtime backup config is 0600.
- Source checks: 72 existing authorization assertions, 9 monitor classification
  checks, 2 sync path/filename checks, Bash/Python syntax and diff whitespace passed.
  `make quality` still has no real linter/static analyzer; no stronger claim is made.
- Final TEST monitor: status `ok`, no issues, no recent matched PHP/SMTP errors,
  disk ~24% used, valid certificate with ~81 days remaining. Both timers enabled.
- Final browser: TEST homepage eight slides/loaded media, authenticated Back Office
  two queue rows, zero observed browser errors. No additional storefront audit.
- Protected TEST business tables remain identical to the pre-remediation baseline;
  no TEST QA user/order was created by this round. Isolated QA artifacts were removed.
- Private SSH/WireGuard and WordPress 6.8.10 remained healthy. Application SHA is
  unchanged; installed operations script hashes match the reviewed LOCAL files.
- Accepted integration checkpoint `9c5241088f8de2d0780090d6ffca546f01a86067` remains
  clean and untouched. Main stayed `238c38e43b6e0d2ecbb24c697a4ed59753029cc5`.
- Operational changes/docs are intentionally uncommitted: this task authorized
  implementation but did not request a Git commit or push under AGENTS.md.
