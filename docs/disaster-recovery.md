# Apple Klinika disaster recovery — practical runbook

Preparation date: 2026-10-15. No recovery/deployment is performed by this document.
Initial preparation baseline:
**`1d6af15bd116adb66cdd66fd72ab75b7dba39e56`**; superseded by the current operational reference below.
Use [current operational baseline](current-operational-baseline.md) for rewritten
SHAs and quarantined worktrees. Legal documents remain unchanged. Full recovery
of an existing environment is distinct from the [empty production launch](production-empty-catalog-launch.md).

## Sources and current limitations

| Material | Source | Status / boundary |
| --- | --- | --- |
| Current code | Rewritten `evma96/appleklinikawebshop` public refs; exact approved SHA | Current clean refs proven; old direct sensitive-image URLs still need GitHub Support purge. Do not recover old public ancestry by accident. |
| Full TEST data/config/media/code | `/opt/appleklinika/backups/routine/<UTC-set>/` | Daily encrypted four-stream sets, server keeps 14 complete sets. Same-server copy alone does not survive server loss. |
| Additional data copy | `~/AppleKlinika-Private-Backups/routine-test/<UTC-set>/` on Martin's Mac | 30 complete sets; existing pull/decrypt checks. Copy is conditional on Mac availability. Its extra `verification.json` is local evidence, not an upload-set member. |
| BX11 independent copy | Restricted subaccount, SFTP22, `appleklinika/test/routine/<UTC-set>/` | 2026-10-30 transfer/download/decryption verified; daily timer enabled, 30-set retention. See [acceptance](qa/bx11-backup-20261030.md). External decryption-key custody still pending. |
| Private key + certificate + instructions | `~/AppleKlinika-Private-Backups/recovery-custody-20261014/` | Ready, private permissions. Owner's encrypted off-Mac copy is NOT confirmed. Original operational Mac key remains in `round1-20261005/`. TEST has only the public certificate. |
| Git emergency safety | `~/AppleKlinika-Private-Backups/pre-history-rewrite-20261014/` | Verified encrypted archive and private restored bundles. Contains OLD sensitive history; never publish/mirror-push it. Use selectively and map identities. Not yet independent off-Mac. |
| Operational procedures | This repo + a private offline copy of this runbook/reference map | Keep together with recovery instructions, outside dependence on GitHub alone. Never put private key or credentials in Git. |

Latest recorded BX11 round-trip verification: `20261030T120758Z`, 62 DB tables,
25,690 archive entries and 1,133 media files. Transfer and stream/archive decryption
passed; a new database import/application restore was not performed. Recheck current
freshness before any incident recovery. Before recovery choose the newest verified complete set appropriate to
the incident; record its timestamp and estimate data loss since it. Daily scheduling
suggests up to a day of normal data loss, but failures/late copies can make it
longer. No unmeasured RTO guarantee is made.

## Incident sequence and isolation

1. Identify the incident and stop new writes/traffic to the failed target as needed
   under explicit incident authority. Preserve evidence and existing encrypted sets.
   Do not overwrite an original backup or recover directly over a running database.
2. Obtain approved host/cloud access using independent credentials. Provision a new
   isolated recovery machine/network and fresh volumes. No customer email, payment,
   invoice, shipment, DNS switch or public traffic is permitted during validation.
3. Obtain an encrypted set from a surviving source, its manifest, and separately
   the private recovery key/certificate. Check manifest against prior private
   verification evidence where available; an attacker controlling both backup and
   manifest can replace both. These CMS envelopes provide confidentiality, not an
   independently signed authenticity guarantee. Keep independent copies/snapshots.
4. Verify/decrypt as below, restore into the isolated environment, then verify data,
   code, identity/configuration and service behavior. Resolve secrets/host changes.
5. Switch traffic/timers/providers only through a separately approved recovery cutover.
   Keep the old target offline as rollback/evidence until retention is reviewed.

## Scenario A — application/TEST server lost

- Code: clean GitHub baseline or verified repository content from an encrypted set.
- Data: BX11 verified encrypted routine copy, or the existing verified Mac copy.
  Use the SFTP22 download procedure in [the BX11 guide](storagebox-test-preparation.md).
- Key: encrypted external recovery kit; today only the Mac copy is confirmed.
- Recreate server SSH host keys, access keys, WireGuard peer identity/firewall binding,
  DB passwords and certificates appropriate to the replacement host. Do not simply
  activate archived `/etc/ssh`, WireGuard, firewall or root authorized-key files.
- Restore WordPress, DB, media and configuration in isolation; verify exact source,
  private URL/environment guards and provider settings before any later TEST cutover.

## Scenario B — Mac unavailable

- If server survives, existing TEST encrypted backups continue using the public
  certificate. Retrieve encrypted sets through approved independent administration.
- Recover the private key from the independently stored encrypted external device;
  the Storage Box must never be the only key source or contain the private key.
- **Current blocker:** if Mac is lost before external key custody is confirmed,
  encrypted backups may be undecryptable. No reset/replacement key decrypts old sets.
- Use GitHub/BX11 only if actually available and verified. New Mac access requires
  owner-authorized SSH/WireGuard setup; never widen public SSH to the internet.

## Scenario C — GitHub unavailable or corrupted

- Use the repository tree inside a verified current encrypted routine set, or a
  trusted clean local checkout at the approved rewritten SHA. Verify Git objects/tree
  and baseline mapping before use. Runtime source is also part of the files archive.
- The emergency pre-rewrite Git safety package was actually restored (51 public,
  122 local refs). It is last-resort **private** evidence, not a clean publication
  source. Use the known clean replacement images/mapping and explicit review before
  recovering old contents; do not reintroduce the three contaminated blobs or push all refs.
- The safety tar contains validated macOS AppleDouble `._*` sidecars; handle/exclude
  only those sidecars during a controlled restore. Never “repair” actual Git objects.
- No GitHub availability is required to decrypt/restore an existing full backup.
  Independent code/runbook custody is not complete until copied beyond the Mac.

## Scenario D — Storage Box unavailable

- Keep TEST local backup schedule and Mac copies running. Do not delete surviving
  copies to compensate for a remote error. Local 14 / Mac 30 retention remains.
- Monitor must report failed/stale remote backup after enablement; a successful
  local backup is not a successful independent copy. Investigate service/credentials/
  capacity via provider tools; never disable strict host-key validation to reconnect.
- A temporary outage uses the surviving verified copies; simultaneous server+Mac
  loss without BX11 is outside current recovery coverage. Obtain a separately
  authorized alternate encrypted destination if the outage exceeds risk tolerance.

## Scenario E — complete WordPress + DB + media + configuration restore

1. Use a 0700 private workspace on a recovery computer. Download a complete set with
   `offserver-sync.py --action download --apply` using the instructions in
   [BX11 preparation](storagebox-test-preparation.md), or copy only the four CMS files
   and `SHA256SUMS` from an existing verified Mac/server set into a new timestamp folder.
   Do not copy local auxiliary reports into this strict five-file verification folder.
2. Run the read-only verifier:

   ```sh
   python3 scripts/operations/verify-encrypted-set.py /private/restore/YYYYMMDDTHHMMSSZ --key /encrypted-device/recovery-key.pem --cert /encrypted-device/recovery-cert.pem
   ```

   It checks all hashes/decryptions, database completion/table count and required
   `compose.yaml`, DB secret and `wp-config.php` archive members without extracting
   or printing private content. Wrong key/corruption/incomplete archive fails.
3. Review archive members/links before extraction. Extract only into the new private
   root, never `/`, never overwrite live system files, and never follow unsafe links.
   Retain file ownership/modes as required by the isolated containers. The active
   backup includes runtime Compose/secrets/uploads/plugin overlays, repository,
   WordPress core volume, Caddy data/config and operational/system config. Inventory
   is a reference for rebuilding versions; do not blindly replay its commands.
4. Recreate isolated Compose/runtime mounts and an empty database volume. Start only
   the database first. The proven SQL stream includes database selection, tables,
   routines/events/triggers; import through the supported MariaDB client into the
   explicitly isolated instance, with the event scheduler OFF. Obtain DB auth from
   a private mounted secret/hidden prompt, not command-line password text. Example
   stream shape (review target/container first):

   ```sh
   set -o pipefail
   openssl cms -decrypt -binary -inform DER -in /private/restore/YYYYMMDDTHHMMSSZ/database.cms -recip /encrypted-device/recovery-cert.pem -inkey /encrypted-device/recovery-key.pem | docker exec -i RECOVERY_DATABASE sh -c 'export MYSQL_PWD="$(cat /run/secrets/mysql_root_password)"; exec mariadb -uroot'
   ```

   This imports a backup via the database's supported client; it is not a raw-SQL
   cleanup/mutation script. Do not run it on TEST/live containers during preparation.
5. Restore source/private mounts and salts for the same-environment isolated restore
   first: encrypted plugin credentials can depend on original salts. Confirm decryption
   without sending mail or contacting providers. Rotate/re-enter secrets in the
   supported plugin workflow when later regenerating salts; do not leave undecryptable
   credentials and claim successful recovery.
6. Set LOCAL-only home/siteurl/Back Office origin using supported WP tooling before
   exposing the application. Keep cron, queues, outbound mail, socket/provider access
   blocked; the existing isolated LOCAL guard pattern is available. Avoid broad SQL
   URL replacement that corrupts serialized settings; use supported dry-run replacement
   after a private snapshot if needed. Original backup remains unchanged.
7. Verify code SHA/tree, expected table counts/row inventory, users/orders/stock,
   order history/idempotency markers, media hashes and authorized invoice/document
   access. Personally inspect homepage/Contact/account/Back Office in isolation.
   Run `make test` and `make quality` plus targeted restore checks. Quality's current
   placeholder lint/static targets are not a substitute for actual syntax/tests.
8. Verify SMTP credential decryptability, environment values, licences and provider
   configuration **without sending/generating anything**. A recovered paid order
   must not trigger another invoice, stock change, email or shipment on boot.
9. Record set timestamp, code SHA, checksums, table/archive counts, isolation and
   observed restore duration. Remove only explicitly created disposable rehearsal
   users/orders/volumes through supported workflows after approval; retain evidence
   summaries without private data. Cutover requires explicit separate authorization.

## Secret recreation versus recovery

- New environment/production: all DB credentials, WP salts, admin credentials/MFA,
  SSH/WireGuard keys, domain/callback URLs and provider modes are configured separately.
- Same-environment disaster: original salts/credential ciphertext are needed temporarily
  to prove faithful restoration. Rotate compromised credentials and re-enter provider/
  SMTP secrets before enabling services. Never expose them in logs or this report.
- TLS identities and host SSH keys should be reissued/re-established for the new host;
  pin trusted host keys through an independent channel. Rotate provider tokens if the
  lost host may be compromised. Licences/site bindings may require supported reactivation.
- Keep the existing decryption key until every retained archive depending on it has
  expired or been safely re-encrypted. Generating a new key does not recover old data.

## Close-out criteria

Independent backup is accepted only after actual upload, remote checksum, download,
decrypt and isolated application restore; timer/failure monitoring verified; offline
key custody confirmed. Today these BX11/off-Mac gates remain open. Existing TEST
restore and email/order proofs are retained, not rerun by this preparation.
