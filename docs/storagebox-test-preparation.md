# BX11 TEST integration — disabled preparation

Status 2026-10-15: BX11 does not exist. No account/key, connection, upload, timer,
remote deletion, server change or provider action has occurred in this task.
The code/configuration below is a LOCAL candidate, not active TEST configuration.
[Current published baseline](current-operational-baseline.md) is unchanged.

## Prepared components

- `scripts/operations/offserver-sync.py`: dry-run by default, separate `--apply`
  plus private `enabled: true` required. Upload allowed only for the configured
  TEST host; production is refused. SSH/rsync over port 23, dedicated subaccount,
  pinned host key, no password fallback, local checksums, remote checksums and
  manifest published last. Each run rechecks uploaded bytes even if previously
  successful. No plaintext source path, private recovery key or arbitrary extra
  file is part of an upload set.
- Four expected encrypted streams: `database.cms`, `files.cms`, `inventory.cms`,
  `logs.cms`, with `SHA256SUMS`. Only complete timestamp directories are considered.
  The proven backup producer encrypts before writing backup data; the adapter
  verifies exact set membership/checksums and does not create/encrypt backups.
- Private atomic state and non-overlapping job lock; only status/counts logged,
  never remote stderr/configuration values. An absent/failed/old remote state is
  actionable. “Fresh” means both a successful check and the newest actual backup
  are <=30 hours old; rechecking an old set cannot mask a stopped backup job.
- `test-monitor.py` opt-in hook: `/etc/appleklinika-ops/offserver-sync.json` absent
  or disabled means no change to existing behavior. After enablement, corrupt
  config/state, failed service, missing/stale remote backup generate
  `remote_backup_failure_or_stale` through the existing deduplicated alert route.
  A failed upload exits nonzero; systemd/journal records failure without secrets.
- `docker/operations/appleklinika-test-offserver.service` and `.timer`: opt-in
  templates, 04:30 UTC after the 03:15 UTC existing daily backup window; host guard.
  Nothing installs/enables these automatically. Confirm worst-case backup duration.
- `verify-encrypted-set.py`: validate downloaded checksums; decrypt all four streams
  on a recovery computer without printing/extracting plaintext; inspect DB footer,
  table count and required TEST recovery archive members. This is a decryption
  proof, not a database import/application restore. Archive links still require
  human review before extraction.

## Retention and destination convention

Assign the upload subaccount to a dedicated private directory. Inside its restricted
root use `appleklinika/test/routine/YYYYMMDDTHHMMSSZ/`. Never mix production backup
sets here. `STORAGEBOX_REMOTE_PATH` is relative to the subaccount root; no leading
slash, spaces, `..` or shell syntax. Set local server retention 14 and Mac 30 as
already configured. Proposed remote retention: newest 30 **verified complete sets**.

`--action retention-plan --apply` reads the remote directory and verifies complete
sets, then lists `keep`, `review_expired` and `retain_incomplete_for_investigation`.
It performs **no deletion**. Failed/incomplete/unrecognised directories are never
silently pruned. Until separately reviewed pruning is activated, remote retention
is conservative and unbounded; check usage in Hetzner monthly and before expiry
reviews. Delete only individually approved expired set directories through the
provider file UI/limited shell, keeping the 30 complete sets and independent
restore proof. Snapshots, if configured, have separate retention/capacity costs;
review provider options rather than claim immutability.

## Information still needed from Martin

1. Provisioned BX11 location and main-account control-panel access (no password in
   chat); choose a location different from TEST where possible.
2. Dedicated restricted subaccount and its actual `STORAGEBOX_USER`, corresponding
   `STORAGEBOX_HOST`, and assigned base directory. The host for a subaccount is
   normally `uNNNNN-subN.your-storagebox.de`, not the main-account host.
3. Confirm `STORAGEBOX_REMOTE_PATH`, SSH support and external reachability for
   recovery from a Mac outside Hetzner; confirm storage/snapshot/retention policy.
4. Independently verified host-key fingerprint from a trusted provider channel;
   never accept a key solely because `ssh-keyscan` returned it.
5. Confirmation that the existing recovery kit has been copied to an encrypted
   external device independently of the Mac/TEST. Do not send the key here.

## One-time secure setup after provisioning/authorization

1. Create a separate directory-restricted write subaccount for TEST uploads and,
   where supported by the chosen account setup, a read-only recovery subaccount
   for downloading. No main Storage Box credential goes on TEST. Restriction to
   a directory is least privilege, **not append-only/immutable storage**; a writer
   can alter its own directory. A separate administrative identity reviews pruning.
2. Generate the dedicated upload SSH key only during the authorized setup, store
   it root-owned 0600 in `/etc/appleklinika-ops/`, and install its public half via
   Hetzner's supported port-23 `install-ssh-key` workflow. Preserve existing keys;
   do not overwrite `authorized_keys`. Keep passwords in hidden interactive input.
3. Pin the independently verified host key to a private known-hosts file using the
   `[host]:23` entry. Use a separate recovery identity on the recovery computer.
4. Copy `docker/operations/offserver-sync.json.example` into root-private
   `/etc/appleklinika-ops/offserver-sync.json`. Fill the three STORAGEBOX placeholders,
   confirm actual hostname/path/permissions. Leave disabled until these are checked.
   No new application environment keys or mailbox/provider settings are needed.
5. Install the reviewed scripts and service/timer templates in the existing TEST
   operations paths. Keep both enabled state and timer OFF until a manual round trip
   succeeds. `python3 offserver-sync.py /private/config.json` is offline dry-run.
6. Set `enabled: true` in private TEST config only, then invoke the explicit upload:

   ```sh
   python3 /usr/local/lib/appleklinika-ops/offserver-sync.py /etc/appleklinika-ops/offserver-sync.json --apply
   python3 /usr/local/lib/appleklinika-ops/offserver-sync.py /etc/appleklinika-ops/offserver-sync.json --action retention-plan --apply
   ```

7. On the recovery computer create a private 0700 parent directory and private
   download configuration (`environment: RECOVERY`, its exact hostname, read-only
   account/key/known-hosts, enabled true). Download a selected complete timestamp
   into a **new** directory; existing targets are refused:

   ```sh
   python3 scripts/operations/offserver-sync.py /private/recovery-sync.json --action download --apply --set YYYYMMDDTHHMMSSZ --destination /private/restore/YYYYMMDDTHHMMSSZ
   python3 scripts/operations/verify-encrypted-set.py /private/restore/YYYYMMDDTHHMMSSZ --key /encrypted-device/recovery-key.pem --cert /encrypted-device/recovery-cert.pem
   ```

   The decryption key stays on the recovery computer/device, never TEST/Storage Box.
   If a passphrase-protected key needs entry, use the terminal's hidden prompt;
   never command-line password arguments or plaintext password files.
8. Complete the isolated WordPress/DB/media restore in the [DR runbook](disaster-recovery.md).
   Record timestamp, source SHA, checksums, table/archive counts and restore result,
   no personal data. Test one controlled failed remote state in isolation to confirm
   monitor classification; authorize any real human alert separately.
9. Only then enable the TEST off-server timer, check the first scheduled success and
   freshness/alert hook, and record remote capacity and retained set count. This
   does not authorize a production timer or production secrets.

The limited Storage Box shell supports explicit `ls`, `cat`, `sha256sum`, `mkdir`
and rsync. Scripts do not depend on `find`, shell pipes, redirections or uploaded
server scripts. Commands above run on the client, not inside the Storage Box shell.

Official references checked 2026-10-15:
[SSH/rsync](https://docs.hetzner.com/storage/storage-box/access/access-ssh-rsync-borg/),
[subaccount access](https://docs.hetzner.com/storage/storage-box/access/access-overview/),
[key installation](https://docs.hetzner.com/storage/storage-box/ssh-keys/add-ssh-keys/).
