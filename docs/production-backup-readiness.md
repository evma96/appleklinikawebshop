# Disabled production backup readiness

No production configuration, account, container or timer is changed. The proven TEST `test-backup.sh` and its active timer remain byte-for-byte unchanged. `reusable-backup.sh` is a parameterized candidate, disabled unless an explicitly approved root-private configuration sets `AK_BACKUP_ENABLED=yes` and matches the host guard.

## Coverage and consistency

The candidate keeps the proven writer-pause → supported MariaDB transaction dump → files → resume sequence. It streams database, files, runtime inventory and logs into separate AES-256 CMS envelopes before writing backup data to disk. Only the recovery **public certificate** is needed on the server. Checksums, atomic complete-set publication, bounded successful-set retention, private failure state and restart-on-error are retained. A failed run never publishes a complete set.

`docker/operations/production-backup.env.example` contains disabled placeholders only. Before activation, approve exact host/container/database names and an explicit root-relative archive paths file covering uploads/media, source, WordPress data, private/environment config, certificates and operations jobs. Do not include the backup destination in its own sources. Review maintenance downtime and non-WordPress writers before enabling; writer pause is not a promise of zero downtime. Protect configuration/path lists and the recovery private key separately.

The active TEST monitor already reports backup failures/staleness. The reusable job writes the same `backup.json` state contract; a future production monitor and human recipient must be explicitly configured before enabling production. A draft service is not silently installed or started.

## Always-on independent destination

Recommended smallest existing-provider option: **Hetzner Storage Box BX11 (1 TB)**, preferably in a different location from the application server, with a dedicated directory-restricted subaccount. This is a paid resource requiring Martin's approval; no order/account is created here. The existing Mac copy remains an additional copy, not the sole independent production destination.

`offserver-sync.py` uploads only the four encrypted archives and their manifest over authenticated SSH/rsync port 23, with pinned host verification and a dedicated key. It checks local SHA-256, verifies the remote encrypted bytes with `sha256sum`, and publishes `SHA256SUMS` last. It never uploads plaintext dumps or the decryption key. It never mirrors deletions. A root-private state file records success/failure; the future host monitor must check both failure and freshness. Configuration is disabled in `docker/operations/offserver-sync.json.example`.

Keep local successful retention at 14 sets. For the independent destination approve a separate retention window (initial recommendation: 30 daily sets plus provider snapshots), storage-capacity alert and a maintenance identity distinct from the upload identity. Remote deletion/snapshot policy is deliberately not activated without the purchased target and an account-specific review. Directory restriction is not immutable storage; a compromised upload identity can still affect its directory. Storage Box snapshots and separate recovery-key custody reduce that risk.

Official sources checked 2026-10-09: https://www.hetzner.com/storage/storage-box/ and https://docs.hetzner.com/storage/storage-box/access/access-ssh-rsync-borg/ . Port 23 supports rsync and SHA-256 checks. No price is guessed; confirm current checkout pricing/tax before purchase.

## Restore procedure and acceptance gate

1. Select a complete remotely verified set and validate its manifest locally. Obtain the recovery private key through an approved secure channel, never chat/Git/email.
2. Decrypt into an isolated private restore workspace. Inspect archive member names before extraction; reject traversal/absolute paths. Do not activate archived firewall/SSH/provider settings.
3. Recreate a separate database and internal-network Compose environment with outbound provider calls, email and cron disabled. Stream the decrypted dump into MariaDB. Restore media/source/private mounts with original permissions.
4. Set isolated LOCAL URLs; verify users/orders/history/stock/media hashes and document access. Run the focused regression suite and record recovery duration.
5. Only a separately approved production recovery change may switch real traffic or credentials. Keep a rollback set and validate exact source SHA, provider environment and mail recipient guard first.

The previous TEST restore rehearsal remains proven. The new generic template and mocked transfer tests prove preparation and guards, **not a production restore or actual Storage Box transfer**. Before production enablement: purchase/authorize the target, install a restricted upload key and verified host key, test a real encrypted transfer plus isolated restore, provide off-Mac recovery-key custody, approve retention/capacity alerts and explicitly enable the production timer.
