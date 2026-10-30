# BX11 TEST backup integration

The original 2026-10-15 disabled SSH/23 preparation is superseded by the SFTP/22
integration on 2026-10-30. The existing encrypted backup producer and Mac backup
workflow are preserved. No application release or production change is involved.

## Transport and credentials

- Dedicated Storage Box subaccount, Helsinki; subaccount root is the assigned
  `appleklinika-test-backups` directory. The SFTP view starts at `/`, so the base
  directory is not duplicated in paths.
- Inside that restricted root: `appleklinika/test/routine/<UTC-set>/`.
- `offserver-sftp.py` uses Paramiko (`python3-paramiko` from Ubuntu packages on TEST),
  SFTP port 22, pinned known-hosts, explicit key identity, no agent/password fallback.
  Host key is checked against the independently published Hetzner RSA fingerprint:
  `SHA256:EMlfI8GsRIfpVkoW1H2u0zYVpFGKkIMKHFZIRkf2ioI`.
- Dedicated upload private key remains root-only under `/etc/appleklinika-ops/`.
  A separate recovery access key is stored privately on the Mac. Both public keys
  use RFC4716 format in the subaccount `.ssh/authorized_keys`; prior keys preserved.
  The one-time enrollment password was entered through hidden terminal input,
  used in memory only and not written to a file, log, repository or report.
- These SSH access keys are distinct from the encryption recovery key. TEST has
  only the public encryption certificate; the private decryption key stays on the
  recovery computer. Independent encrypted key-vault/password-manager custody is pending; no physical drive is required.

## Archive safety and integrity

The existing `test-backup.sh` produces the same four encrypted CMS streams:
`database.cms`, `files.cms`, `inventory.cms`, `logs.cms`, plus `SHA256SUMS`.
The upload adapter reuses the format, checksums, private-state and host guards in
`offserver-sync.py`. No plaintext database, media or private configuration is uploaded.

Each run selects the newest complete local set (must be <=30 hours old). Uploads
use a unique `.partial-*` directory. Each encrypted file is read back over SFTP and
hashed; the manifest is uploaded last, then the directory is renamed to its final
UTC name. A pre-existing final set is verified, never overwritten. Conflicting or
corrupt bytes fail closed. Incomplete uploads remain identifiable for investigation.

Port 22 does not provide a remote shell. Therefore “remote checksum” means SHA-256
of actual remote ciphertext read back through authenticated SFTP, not a claim that
`sha256sum` executed on the Storage Box. Independent download verification compares
all encrypted files against the original manifest again.

## Schedule, retention and monitoring

- Existing local daily backup: 03:15 UTC with up to five minutes jitter, unchanged.
- Off-server service/timer: 04:30 UTC with up to five minutes jitter, persistent
  catch-up after restart; enabled only after the first round-trip verification.
- Uploads are locked against overlap and bounded by a four-hour service timeout.
- Remote retention: at least newest 30 complete sets. Before pruning, all retained
  sets are verified; only older sets recorded as created by this service may be
  removed. Pre-existing/unmanaged sets, unknown names and partial directories are
  never pruned automatically. No deletion is needed for the first uploaded set.
- Existing local and Mac retention remain unchanged. Storage Box snapshots are
  not enabled or asserted by this task. A writable subaccount is not immutable.
- Existing five-minute TEST monitor now includes `remote_backup_failure_or_stale`:
  missing/corrupt state, failed service/transfer, or latest backup/check older than
  30 hours. A running job has a maximum two-hour grace only while a previously
  successful backup is still fresh. The existing deduplicated SMTP alert/recovery
  route is reused without replacing its credentials or recipients.
- Failed/incomplete operations do not refresh successful backup timestamps.

Offline regression command: `make test-offserver-backup` (no network/provider calls).

## Recovery and operation

Use a private 0700 workspace and 0600 recovery configuration. Recovery uses
`environment: RECOVERY`, exact local hostname, explicit separate identity and the
same pinned host key. All secret paths are local configuration, not Git data.

```sh
python3 scripts/operations/offserver-sftp.py /private/recovery-sync.json --action download --apply --set YYYYMMDDTHHMMSSZ --destination /private/restore/YYYYMMDDTHHMMSSZ
python3 scripts/operations/verify-encrypted-set.py /private/restore/YYYYMMDDTHHMMSSZ --key /private/recovery-key.pem --cert /private/recovery-cert.pem
```

Download refuses an existing target. Verification decrypts in memory/streams and
prints only counts: SQL tables and completion marker, gzip CRC, safe tar paths,
required runtime/config members, media-file presence and nonempty inventory/logs.
It does not print or extract plaintext private data. This is archive/decryption
verification, **not** a new database import or complete application restore drill.
Use the existing [disaster recovery procedure](disaster-recovery.md) for that.

Operational copies are installed outside the TEST application repository from the
reviewed source checkpoint. See
[the acceptance record](qa/bx11-backup-20261030.md) for observed results.

Official references:
[host fingerprints and formats](https://docs.hetzner.com/storage/storage-box/general/),
[key installation](https://docs.hetzner.com/storage/storage-box/ssh-keys/add-ssh-keys/),
[SFTP access](https://docs.hetzner.com/storage/storage-box/access/access-sftp-scp/).

## Reproducible installation (TEST only)

From the reviewed release checkout, install Ubuntu's `python3-paramiko` package.
Install `scripts/operations/{offserver-sync.py,offserver-sftp.py,verify-encrypted-set.py}`
as root-owned 0755 files into `/usr/local/lib/appleklinika-ops/`. Install the tracked
`docker/operations/appleklinika-test-offserver.service` and `.timer` into
`/etc/systemd/system/`, then reload systemd. The existing monitor imports
`offserver-sync.py`; retain its existing alert credentials and timer.

Create private 0700 configuration/state directories. Populate the tracked
`offserver-sync.json.example` as a 0600 private configuration with the verified
TEST hostname, subaccount, pinned known-hosts file and root-only upload identity.
Never copy example placeholders over an existing configuration. Keep the established
backup producer, public encryption certificate and Mac recovery workflow unchanged.
Enable the timer only after a controlled service run, ciphertext read-back and
independent download/decryption pass. Compare installed script hashes to this release;
no untracked code patch is required. No keys or private configuration are in Git.
