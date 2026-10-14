# Final non-legal readiness follow-up — 2026-10-14

Scope: canonical-address checkpoint, existing backup/key readiness, public-history decision package, targeted catalogue review and non-mutating TEST verification. Production, main, protected tags, VirtualJog pages, provider flows and BACS configuration are unchanged. No customer email is resent.

## Checkpoint and preserved configuration

Address checkpoint: `a5b4c099299940ecc94119b923df4381ec26d7ec`, five files, clean durable LOCAL worktree. The equivalent active defaults/expectations were already present on develop/TEST `44f65cfbf81a4554d727730316d2c596ef041972`. Integrate without reverting the newer comment/assertion wording or any security/provider work.

Fresh TEST read confirms store/pickup **6722 Szeged, Jósika utca 4.**, coordinates unchanged. BACS disabled; Barion enabled in TEST mode; cash restricted to `local_pickup`. Generic free_shipping:1 remains disabled. Memory-only cart calculation gives pickup 0, GLS home 1990, GLS point/locker 1490, MPL point/terminal 1040, MPL home 2090 HUF. No provider request, email, saved customer or order. The anonymous CLI fixture does not reproduce authenticated checkout selection: its selected-method session is cleared by the existing guest guard, so it is not used as new evidence of cash-selection behavior. The unchanged supported configuration and previous authenticated desktop/mobile checkout acceptance remain the evidence for cash availability on pickup.

All original 162 LOCAL / 73 TEST orders, users, stocks and protected settings match the canonical-address baseline; ten legal pages per environment unchanged. Pickup email rendering uses the canonical address without saving/sending anything. Existing Barion/GLS/invoice/email/idempotency acceptance is preserved and not mechanically repeated.

## Backup destination and recovery key

TEST backup `20261014T031736Z` succeeded. Mac-side encrypted copy: 469,993,268 bytes, four CMS streams, checksums and actual streaming decryption PASS; 62 SQL tables and 24,210 archive entries. The freshly prepared custody-kit key also decrypts this set. This proves the existing Mac download, not a transfer to an always-on remote target.

No off-server target is configured in TEST operations. The earlier account inspection found no suitable owned target; current Hetzner session requires sign-in. Martin confirms no Storage Box exists and is arranging **BX11 (1 TB)**. No purchase/account or production schedule is created. At current size, 30 daily encrypted sets require about 14.1 GB decimal, plus growth and snapshots. Choose a different site from the application server and a directory-restricted upload identity, pinned SSH, 30 daily sets plus provider snapshots. Keep the current server 14-set and extra Mac copy. Storage Box is billed by provisioned capacity/time; the exact location/tax-adjusted checkout price must be confirmed by Martin, not guessed. Official product: https://www.hetzner.com/storage/storage-box/ ; supported key/SSH details: https://docs.hetzner.com/storage/storage-box/general/ .

Always-on upload, remote checksum, fresh retrieval and decryption remain PENDING until the owned target is available. The prepared `offserver-sync.py` is still disabled. No simulated transfer is labeled PASS.

Owner chose an **encrypted offline external device**. The private handoff kit is `/Users/apple/AppleKlinika-Private-Backups/recovery-custody-20261014/`, containing `recovery-key.pem`, `recovery-cert.pem`, `RECOVERY-INSTRUCTIONS.txt`. Directory mode 0700, key mode 0600; key matches certificate. No secret contents in this repository/report; no key rotation. Only the public recipient certificate is required on TEST. Existing operational Mac key remains unchanged. Martin must copy the kit to his encrypted device and confirm independent custody. In a server-loss restore, obtain encrypted sets from the independent destination and the private key from that offline device. Offline-copy import/retrieval has not been claimed complete. See `../production-backup-readiness.md` and `../security-operations-round2.md` for isolated restore procedure.

## Historical Git privacy — recommendation B

Recommend **COORDINATED HISTORY REWRITE BEFORE PRODUCTION**. The practical benefit is removing easy public retrieval of precise non-store location and cross-photo device/time identifiers. The migration is bounded and already dry-run verified; it is justified before more clones and production references accumulate. It cannot recall prior downloads/forks/caches.

Affected historical paths, all under `wordpress/wp-content/plugins/appleklinika-inventory/assets/demo/`: `iphone-13-pro-1.jpg`, `iphone-13-pro-2.jpg`, `iphone-13-pro-3.jpg`. All three retain camera/lens identifiers and capture/edit times; the second has GPS about 638 km from Szeged. No provenance/home-location assumption is made. Exact private metadata is deliberately omitted. Fresh anonymous raw downloads still return HTTP 200 and match the three known historical blobs.

Existing successful dry run (2026-10-09): 210 commits rewritten, 190 trees containing the three old blobs, 50 branches and one protected annotated tag. All non-image tree bytes and history relationships preserved; decoded pixels/orientation/ICC unchanged, no HDR payload in the three JPEGs, other HDR assets untouched; 486 clean unique images scanned, zero identified private capture/GPS/device metadata; fsck passed. Fresh read-only public boundary before this documentation integration: 213 reachable commits, the same 190 old-blob trees, 50 branches and one tag. New descendant commits also need rewritten parents; never publish the stale dry-run mirror.

Required procedure, **not executed**:

1. Separate owner authorization explicitly covering every affected public branch, `main` and `prelaunch-checkpoint-2026-09`. Freeze pushes/deploys; coordinate collaborators; export protections; keep the old rollback mirror encrypted/private.
2. Snapshot all current remote refs and local unpublished work, including this address checkpoint. Fresh isolated mirror; apply only the reviewed three-blob substitution. Re-run tree/pixel/orientation/ICC/HDR/EXIF/fsck verification and produce a fresh exact commit/ref map. Stop for any unexpected ref drift.
3. Approved maintenance window only: temporarily permit the agreed protected-ref changes. Publish one explicit atomic per-ref lease-guarded push from that reviewed mirror. No blanket mirror push, deletion refspec or unguarded force. If any lease/protection fails, stop rather than partially retry.
4. Verify every remote ref and a fresh independent clone; restore protections. Migrate all clones/worktrees/PRs/pinned SHAs using the map, preferably fresh clones with only reviewed unpublished patches reapplied. Do not merge old ancestry back. Retain dirty primary work privately; do not clean it.
5. Separately coordinate TEST deployment references and GitHub support/cache/PR-ref cleanup; production is not part of this authorization. Old links/signatures/SHAs change; downloaded copies cannot be guaranteed erased.

The full old/new maps and tested procedure remain in the private `nonlegal-prelaunch-evidence-20261009/history-dryrun/` record. **No public history, main, tag or remote ref has been force-rewritten by this task.**

## Newly observed active-upload privacy finding

The earlier blanket statement that current served imagery is clean was too broad. Fresh checks of 30 public full-size files confirm the three cleaned repository JPEGs and three corrected banner PNGs have no private capture metadata (AI provenance is separate). However, all **12 `qa-gallery-iphone-set-01..04` scaled JPEGs** in the current TEST product galleries still carry GPS, capture times and Apple photo/runtime identifiers. Their positions are approximately 0.9 km from the confirmed public store; no exact coordinates or identifiers are published here. This is a separate **MEDIUM / PRIVACY / DATA** finding, not proof of a new application regression or a finding about every uploads file.

Exact names: set-01 `img_0833`, `img_0834`, `img_0835`; set-02 `img_0851`, `img_0852`, `img_0853`; set-03 `img_0913`, `img_0914`, `img_0916`; set-04 `img_1162`, `img_1164`, `img_1165`, each named `qa-gallery-iphone-set-NN-img_NNNN-scaled.jpeg` in uploads/2026/09. These active uploads require a separate narrowly scoped metadata-only cleanup including originals/responsive variants, verified pixel/orientation/ICC/HDR preservation and public recheck. The requested risk-assessment task does not silently rewrite these media assets or their history.

## Catalogue LOW — broad data curation, no guessed replacements

122 published products share 16 primary/gallery groups. Browser inspection confirms 31 model mismatches (25 iPhone entries display two-camera phones; six modern MacBook entries display an older thick-bezel MacBook Pro), plus 15 unambiguous color mismatches in other iPhone groups. The other 76 entries need inventory-owner image/model/color review; they are not all declared proven wrong. No visible QA/demo prose was found in product name/descriptions; technical filenames/slugs alone are not a separate presentation defect. No prices, stock, titles or image assignments were changed.

Exact review scope by primary image (full product names/URLs and confirmed-vs-review classification are in the private `final-readiness-evidence-20261014/catalog-review.csv`):

| Primary image | Published product IDs |
| --- | --- |
| `qa-gallery-iphone-set-02-img_0851-scaled.jpeg` | 288, 294, 297, 298, 302, 330, 332, 334, 339, 342, 348, 355 |
| `qa-gallery-iphone-set-01-img_0833-scaled.jpeg` | 290, 307, 313, 318, 326, 328, 338, 340, 344, 350, 354, 356, 361 |
| `qa-gallery-iphone-set-04-img_1162-scaled.jpeg` | 291, 292, 296, 306, 310, 314, 316, 320, 323, 324, 345, 362, 364 |
| `qa-gallery-iphone-set-03-img_0913-scaled.jpeg` | 300, 304, 308, 312, 322, 329, 336, 346, 352, 358, 360, 366 |
| `appleklinika-demo-ipad-mint-bg-2.jpg` | 382, 384, 453, 458, 460, 472 |
| `appleklinika-demo-ipad-mint-bg-1.jpg` | 383, 454, 457, 463, 464, 470 |
| `appleklinika-demo-macbook-mint-bg-4.jpg` | 385, 433, 440, 442, 449, 451 |
| `appleklinika-demo-macbook-mint-bg-3.jpg` | 386, 431, 436, 443, 446, 447 |
| `appleklinika-demo-macbook-mint-bg-2.jpg` | 387, 435, 437, 444, 445, 448 |
| `appleklinika-demo-macbook-mint-bg-1.jpg` | 432, 434, 438, 439, 441, 450 |
| `appleklinika-demo-ipad-mint-bg-3.jpg` | 452, 455, 459, 462, 465, 466 |
| `appleklinika-demo-ipad-mint-bg-4.jpg` | 456, 461, 467, 468, 469, 471 |
| `appleklinika-demo-apple_watch-mint-bg-2.jpg` | 473, 477, 479, 485, 491, 494 |
| `appleklinika-demo-apple_watch-mint-bg-4.jpg` | 474, 480, 483, 484, 486, 489 |
| `appleklinika-demo-apple_watch-mint-bg-1.jpg` | 475, 476, 478, 482, 490, 495 |
| `appleklinika-demo-apple_watch-mint-bg-3.jpg` | 481, 487, 488, 492, 493, 496 |

Keep this as one existing LOW / CONTENT-DATA finding. Replace only with validated photos of the actual listed devices; do not relabel stock to match a random photograph. This is broad inventory preparation, not a few safe blind swaps.

## Cash #753 evidence

Existing technical proof remains PASS: exactly one acknowledgement and one staff-acceptance email, no invoice/false payment claim before payment. Existing work record and supplied conversation do not contain a specific manual Inbox/mobile acceptance for #753. Earlier confirmations refer to other courier orders. Keep one owner evidence checkbox pending; no resend and no application defect inferred.

## Focused health and browser verification

Fresh TEST homepage and Contact were personally inspected at 1280x900 and 390x844; no horizontal overflow or captured JavaScript error. Eight hero slides, product/category imagery and canonical Contact text remain. The embedded browser needed visible mode before its viewport override took effect; the final mobile proof uses a measured 390 px viewport, not a mislabeled desktop screenshot. Back Office correctly rejects the expired former QA session and redirects to its login page. Current HTTP/backend health and unchanged source are checked; no new authenticated Back Office workflow traversal is claimed or needed to repeat the already proven lifecycle. WireGuard alias reconnect and TEST monitor are healthy, with no PHP/SMTP errors in the current monitor window. Fresh LOCAL canonical rendering and protected-data comparisons pass.

## Current decision

**Critical 0 / High 0 / Medium 3 / Low 1.** Medium: always-on destination plus independent recovery-key custody; historical public Git metadata; newly confirmed active-upload metadata. Low: catalog image/model/color curation. Cash #753 is an evidence-only checkbox outside defect counts. No production approval is implied. Remaining gates are Storage Box provisioning plus real transfer/retrieval/decryption, offline key-copy confirmation, coordinated history approval/execution, active-upload privacy cleanup and catalog preparation. Legal documents are separate and untouched.

NON-LEGAL CONDITIONAL GO for the retained TEST baseline; all listed pre-production gates must be closed before production launch.
