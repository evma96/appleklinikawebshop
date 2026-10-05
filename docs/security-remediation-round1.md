# Security remediation — Round 1, 2026-10-05

Scope: LOCAL and TEST only, based on the 2026-10-01 audit (finalized October 2).
Approved application candidate: `172366d22f83a4a5e73a533c257861e15adcf518`.
No main/production, provider transaction, DNS/firewall change, licence bypass,
email redesign, restore rehearsal, monitoring or security-header work.

## Recovery safety

Before changes, the TEST web application was briefly stopped so its InnoDB
transactional dump and filesystem copy represent the same recovery point.
All 61 database tables, uploads, actual runtime plugin overlays, persistent
WordPress core/MU plugins, configuration/salts, repository, Compose/Caddy,
SSH/WireGuard configuration and OS/container version inventory were preserved.
Data streamed directly into AES-256 CMS encryption; plaintext backup files were
not written. The decryption key stays only in Martin's private Mac directory.

Server: `/opt/appleklinika/backups/security-round1-20261005/`.
Independent Mac copy: `/Users/apple/AppleKlinika-Private-Backups/round1-20261005/`.
Both locations are mode 0700; backup/key files are 0600. Keep the recovery key
with the private recovery set; do not publish it or copy it into Git.
SHA-256 matches, full streaming decryption, complete SQL footer/61-table inventory
and all 23,283 archive entries were verified. No database was restored. Existing
Hetzner backups remain untouched. Scheduled backups, retention, key escrow and a
measured restore rehearsal remain Round 2 work.

## Source authorization repairs

- The tax fragment is never appended to an empty Woo billing block. The renderer
  requires order-owner plus `view_order`, or Woo staff plus per-order edit
  capability. Guest output requires both a valid order key and an address block
  already authorized by Woo's session/grace-period/email-verification rules.
  Thus an order reference, or a guest key alone, cannot bypass Woo verification.
- GLS label generation, parcel-status lookup and pickup-location update now
  require the existing nonce AND `edit_shop_orders` AND `edit_shop_order` for
  the requested order, before any lookup/mutation/provider operation. Rejections
  use HTTP 403. The Back Office's separately authorized provider adapter remains
  unchanged. This small tracked vendor patch must be retained/rechecked during
  any later GLS upgrade.
- `make test-security` runs actual handler/renderer code against fixtures with
  networking disabled: 45 GLS and 27 tax assertions. The existing offline
  lifecycle suite remains 301 assertions (373 combined). `make quality` is still
  a placeholder; actual PHP syntax checks and targeted source review supplement it.
  Native LOCAL Woo rendering also denied anonymous/no-key tax output.

## TEST maintenance completed before source rollout

- Actual WordPress: **6.8.6 → 6.8.10**, using the existing official WP-CLI Compose
  tool. The database schema was already current. The hu_HU core checksum check
  passed; `wp-config-docker.php` is the expected Docker helper, excluded from
  upstream-core checksums. The supported updater also refreshed available language
  packs; Woo/theme/plugin code versions were not upgraded.
- User #525 matched the pricing test's QA naming convention, shop_manager role,
  and zero content/orders/sessions. Removed through `wp_delete_user`, with no
  reassignment. No real user was changed.
- The invoice directory now belongs to the PHP user, with 0750 directories and
  0640 files. A temporary harmless PDF proved write/read and the installed
  provider's absolute-path resolver; it was deleted without saving an order or
  calling the provider. Direct PDF/directory HTTP access is denied. Email file
  attachments and Back Office's authorized file download use physical paths.
  Native customer static downloads were and remain disabled. PRO-dependent
  invoice generation is NOT claimed verified; if customer browser downloads
  are enabled later, use an authorized route, never open the directory.
- Apache receives the root-owned, read-only `docker/apache/test-file-safety.conf`
  through an explicit TEST Compose mount at
  `/etc/apache2/conf-enabled/zz-appleklinika-file-safety.conf`. Upload overrides
  and executable PHP/PHTML/PHAR are denied; protected invoice/GLS/log/Woo download
  directories remain denied. Ordinary media stays readable. Harmless probes
  returned media 200, upload PHP 403, invoice PDF 403, invoice directory 403;
  all probes were removed.
- WordPress root, top-level core files, wp-admin/wp-includes and wp-content root
  are root-owned, not PHP-writable. wp-config is root:www-data 0640. Uploads and
  required runtime directories stay writable; theme/plugin mounts stay read-only.
  Supported core maintenance continues via root WP-CLI, not web-process writes.
  After future core updates, reapply ownership and verify checksums. The Compose
  image label is not the version of the persisted WordPress core volume.
- OpenSSL/libssl were already automatically updated to `3.0.13-0ubuntu3.16`
  by this task's start. A refreshed package index showed no newer installed-package
  candidates in the Ubuntu noble-security channel. No unrelated Docker/distro
  upgrade was performed. TEST reboot activated kernel **6.8.0-142-generic**
  (previously 6.8.0-136); WireGuard/private SSH, all three containers and HTTP 200
  returned. No reboot-required marker remains.

## Image privacy

Three tracked old demo originals and their 23 existing TEST upload copies were
sanitized without JPEG recompression. Decoded pixels, orientation, EXIF colour
space and ICC bytes match their originals; all are single-frame JPEGs without
HDR/MPF. Sensitive EXIF/XMP identifiers, GPS and capture metadata are absent.
All 112 previously approved September assets still match their original sanitized
manifest. Filenames, product assignments and dimensions are unchanged.

The old originals remain in already-published Git history. **No history rewrite
was performed.** That residual privacy exposure needs a separate decision about
provenance, removal expectations and any coordinated history repair; cleaning
current files cannot withdraw copies already downloaded by others.

## Scoped TEST rollout and rollback

The LOCAL checkpoint preserves the accepted email/fulfilment candidate and the
previously uncommitted, accepted SMTP verification record. For TEST, port only
this round's security changes onto published `develop/post-deploy` (067d63c base).
Keep the actual installed Back Office overlay and all unrelated provider/runtime
files; do not deploy or enable the licence-blocked invoice-dependent lifecycle.
Before replacing files, compare the exact current hashes with the recorded base.
Normal fast-forward public push only; retain the full pre-change recovery set.

Core rollback uses the encrypted pre-change core/config/files plus matching DB if
needed. Source rollback selects the recorded previous Git version and restores
only the scoped runtime overlays from the recovery set. Permissions and the Apache
mount must be considered together; never make invoice PDFs publicly readable to
work around a download failure. OS rollback/recovery remains supported by the
untouched Hetzner backups; an isolated restore exercise is deliberately not run.

## Remaining security findings / acceptance boundary

After successful source deployment and native verification: **Critical 0, High 0,
Medium 3, Low 5** from the original audit. This is not production launch sign-off.

- MEDIUM M6: old published image-history metadata remains outside current copies.
- MEDIUM M7: restore rehearsal, scheduled consistent independent backups/retention
  and measured RPO/RTO remain unproven despite the verified pre-change recovery set.
- MEDIUM M8: operational alerts and log rotation remain the explicitly deferred
  monitoring task.
- LOW L1–L5: headers/version disclosure; login/XML-RPC/2FA policy; six historical
  SSH recovery /32s; dependency/PHP maintenance plan; historical Back Office
  ignored .env local-user readability. None was silently broadened into this round.

The existing sample legal content and Woo Szamlazz PRO/full invoice acceptance
remain separate pre-production blockers, not newly repaired application features.
Final deployed SHA, native checks and browser evidence belong in the accompanying
private Round 1 execution report. No live provider E2E is needed for these guards.

References: [WordPress 6.8.10](https://wordpress.org/documentation/wordpress-version/version-6-8-10/),
[WP-CLI core update](https://developer.wordpress.org/cli/commands/core/update/),
[Ubuntu OpenSSL security notice](https://ubuntu.com/security/notices/USN-8847-1).
