# Authorized history privacy migration — 2026-10-14

This is the current operational identity record. Older dated reports retain their
original SHAs as historical evidence; do not deploy/reintroduce old ancestry.
The owner explicitly authorized the narrowly bounded main/branch/tag rewrite.
No production deployment, legal change or checkout/provider behavior change occurs.

## Execution and independent proof

- Exact preflight inventory: 50 public branches and one annotated checkpoint tag;
  216 reachable commits. Atomic update of all 51 explicit refs, each guarded by
  its exact old SHA. No ref deletion or protection change was required.
- Only `wordpress/wp-content/plugins/appleklinika-inventory/assets/demo/iphone-13-pro-{1,2,3}.jpg`
  blobs are substituted, in 190 affected commit trees (570 file occurrences).
  All 216 commit identities change through ancestry. Authors, committers, dates,
  messages, parent order, file modes and every unrelated tree entry are preserved.
- Pixel decoding, orientation and ICC are identical for all three images. Neither
  original nor clean versions contain HDR payloads; all other image bytes remain
  untouched. No recompression or visual changes are introduced.
- A NEW anonymous GitHub mirror and successful working checkout independently
  reproduce all 51 expected refs and all 216 mapped commits. Full fsck, exact
  tree/commit/tag comparisons and 486 unique reachable image scans pass. None of
  the old affected commits or three contaminated blobs is reachable through those
  public refs. The text scan covers 7,352 blobs / 100,519,895 bytes: no confirmed
  secrets; 64 generic matches in 14 blobs match already-reviewed source/fixture
  false positives. Pattern scanning is not an absolute guarantee.
- Fresh HTTP downloads of 321 active TEST media files match the previously verified
  clean hashes, including all 120 cleaned original/derivative files. The earlier
  active-upload privacy finding is closed without changing catalog assignments.
- `make test` passes (483 offline assertions); `make quality` passes its configured
  gate, which explicitly reports that no linter/static analyzer is configured.

## Residual GitHub retention — still open

All three old-SHA raw image URLs still return HTTP 200 with the old blob bytes.
This is GitHub retained/unreachable object or cache access, not a failed current-ref
rewrite. The owner must request GitHub Support sensitive-data removal/cache cleanup
for this repository, giving the first affected commit and private exact file list
from the recovery record. No support message was sent automatically. No precise
GPS/device identifiers are included in this document.

GitHub documents this limitation and the support procedure:
https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository
Forks, third-party clones and previously downloaded copies cannot be erased by a
force-push. Collaborators must re-clone or explicitly migrate, never merge/push
old ancestry back. A repository-local pre-push guard now rejects the three known
contaminated historical blobs; new clones do not automatically inherit local hooks.

## Important SHA migration map

| Checkpoint | Old SHA | Rewritten SHA |
| --- | --- | --- |
| main | `238c38e43b6e0d2ecbb24c697a4ed59753029cc5` | `66943bad0307ca029f46f0cf1e43bd8ff13bfbe7` |
| develop before documentation follow-up | `5edcec32036fc1ab118232236015b732fc246dd7` | `7940b8c71443e9a6a68c1f6fcc9b090601784418` |
| checkpoint tag target | `c084d7ed3a0cfabfc34ec40029e329d530181678` | `08c848b0189906968efa4472759f689b199fa522` |
| homepage carousel | `1b741ac9432abd2fee571c1b7364dafdcfbe0d19` | `79ace201ccc72c3e3cd6054bcb63947fdd3d8d7b` |
| fulfilment/Back Office integration | `79be7f7e3de1f855929ff143ab8453f6c77072f9` | `c2cc97bb64377d1af7b63696a845e3a051d1f1f5` |
| transactional email | `172366d22f83a4a5e73a533c257861e15adcf518` | `55054c7b14582aa5ad01c7e4de501e44b1e9231b` |
| acknowledgement three-email TEST | `490e6e753bb3c610bc2dc410b8f66a1eac77ae3b` | `44c8accd908916bf26c2aec417e17540f713f29b` |
| security remediation | `9c5241088f8de2d0780090d6ffca546f01a86067` | `2b82c13d77a3044079a6fbca2dbcca57dab3005d` |
| clean image introduction | `0746347ff9ec99d6d4bb614c1412b24adecf2b35` | `8d9e95e705f7b61cf5cee729961b60807823e5b7` |
| non-legal closure | `b7769e996b7d4926ca05dc4b5371b3bc2ce277da` | `ba776c6588823f02e60cbb4771621becc1b7ff66` |
| canonical address | `a5b4c099299940ecc94119b923df4381ec26d7ec` | `1293ba723abc725ab9470e2aafde2a9cd684d68b` |
| durable storefront recovery | `d66dbe8e0d17d3e1f6839c61d7b9c45c21547f2f` | `2f01b89799ffdddcba684f2217292cce1a470467` |
| email recovery package | `ae80d2140e22918a2040d2bc35d21b32bdb2895c` | `0fc71c392cecc317fa284ee841dd90b1fae74903` |
| unique LOCAL Back Office; not pushed | `4c4ee8353da6dcd4797c623b6f480a23fe00e700` | `b58eeb0cd278181dd494490dd48d9508cc926cc1` |

Annotated tag `prelaunch-checkpoint-2026-09`: object `d5c332e13a4b18f461064bd12bfe5388fc835f61` → `e9670e6706a91510927f0abbad22afca06f4ec99`; peeled target is listed above. Tag name/message/tagger preserved.

The documentation-only follow-up descends normally from the mapped develop head;
its final published/deployed SHA is `1d6af15bd116adb66cdd66fd72ab75b7dba39e56`.
See the [active operational reference](../current-operational-baseline.md). The
full exact ref map and verification evidence live outside Git in the owner's
`history-privacy-migration-evidence-20261014` directory. Old private recovery bundles
are never a source for a routine public push.

## Recovery safety and independent-copy limitation

The encrypted pre-rewrite archive outside Git was checksum-verified, decrypted and
actually restored. Public bundle: 51 refs; all-local bundle: 122 refs. Both bundles
and the archived mirror pass fsck/ref equality; main/develop/tag are readable.
The tar includes 30 macOS AppleDouble `._*` sidecars. Ignore validated AppleDouble
sidecars on restore (or extract with macOS copyfile handling); they are not Git
objects. No actual Git bytes required repair. Keep the archive and original
bundles in `~/AppleKlinika-Private-Backups/pre-history-rewrite-20261014/`.

No second independent destination was available: owner confirms BX11 is not yet
created; no external drive is mounted. This limitation was disclosed before push.
The verified private encrypted archive, restored bundles and original local object
store were retained; no Git garbage collection or destructive local cleanup ran.
This permits repository recovery but does not close independent disaster recovery.

## Worktree consolidation

Six clean worktrees were migrated with only the three historical image substitutions
where needed; their own source implementations remain intact. 119 inventoried local
refs were updated. One unique unpublished Back Office commit was rewritten locally
and kept, not replaced by the public branch version and not pushed.

| Path | Classification / purpose |
| --- | --- |
| `~/Desktop/appleklinika-integration-active` | ACTIVE / KEEP — durable integrated LOCAL runtime, canonical-address checkpoint |
| `~/Desktop/appleklinika-storefront-active` | ACTIVE / KEEP — durable storefront recovery baseline |
| `~/Desktop/appleklinika-backoffice-v1` | ACTIVE / KEEP — durable Back Office candidate with its unique LOCAL commit |
| Codex `order-acknowledgement-worktree` | ACTIVE / KEEP — current migration documentation task |
| Codex `security-round1-test/appleklinikawebshop` | ACTIVE / KEEP — clean develop integration/deployment checkout |
| Codex `order-email-lifecycle-worktree` | MERGED / RETAIN — read-only recovery reference; no unique dirty work; optional later archive |
| `~/Desktop/appleklinikawebshop` | UNCERTAIN / QUARANTINE — 2,849 existing status entries; files, index and old HEAD unchanged; never use as source of truth |
| `/private/tmp/ak-test-monitor-worktree` | STALE INVALID — only broken Git registration pruned; residual directory files retained |

No branches or unique uncommitted files were deleted. The dirty primary's old branch
and Codex-managed local snapshot refs are intentionally retained and are not public
publication candidates. Do not run blanket `push --all` or `push --mirror` from this
shared local repository. The private bundle preserves rollback refs; no old ancestry
was merged into clean branches.

## TEST reconciliation and preserved behavior

Using only `appleklinika-test`, TEST moves from old develop to mapped develop after
proving the entire Git tree identical. No restart, provider action, order, shipment,
invoice or email is required. The subsequent normal documentation commit changes
only operational Markdown. Source/config/runtime content remains unchanged.

Focused verification: storefront, Contact, My Account and Back Office login surfaces
load; canonical address is **6722 Szeged, Jósika utca 4.**, original coordinates kept.
The embedded browser's known external Google Maps preview limitation is unchanged.
BACS remains disabled, Barion TEST enabled, cash restricted to personal pickup.
Memory-only rate calculation: GLS point/locker 1,490; GLS home 1,990; MPL point 1,040;
MPL home 2,090; personal pickup 0 HUF. Generic free shipping remains disabled and a
stale free-shipping selection is rejected. No provider HTTP call, mail call or saved
QA order occurs. This narrow check does not claim a new provider E2E or a new
positive authenticated cash-checkout proof; the previously accepted behavior and
unchanged configuration/source remain the evidence.

Post-rewrite comparison confirms all 73 original TEST orders, original users,
stocks, protected settings and ten legal pages unchanged. The complete public
configuration/catalog inventory before and after is identical. All four requested
HTTP surfaces return 200 (Back Office correctly redirects to its login page).
The personally inspected desktop pages show no new application error; captured
browser error logs are empty. No authenticated order workflow was reopened.

## Remaining operations / owner actions

BX11 is not yet created, so automated upload, remote checksum, download and decrypt
are NOT marked complete. Martin must finish the BX11 order (prefer a site different
from TEST), then provide access to its settings so a directory-restricted backup
identity and pinned SSH can be configured. Only encrypted archives are uploaded;
never the private recovery key. Existing server/Mac backups remain active, including
verified `20261014T142155Z`: 62 tables, 24,250 archive entries, cleaned media included.
Retain server 14 / Mac 30 complete daily sets; independent target retention must be
configured and tested after provisioning. See `../production-backup-readiness.md`.

`~/AppleKlinika-Private-Backups/recovery-custody-20261014/` remains mode 0700, files
0600; the key matches its certificate. Owner must copy the COMPLETE directory to
his encrypted external device and confirm independent custody. No key is exposed,
committed, uploaded to TEST or rotated. TEST needs only the public certificate.

Owner supersedes catalog curation: start production EMPTY, never import TEST/demo
products. The 46 mismatches / 76 review candidates are retained as historical TEST
evidence, not a production blocker. See `../production-empty-catalog-launch.md`.

Current non-legal counts: **Critical 0 / High 0 / Medium 2 / Low 0**. Remaining
Medium items are independent backup/key custody and GitHub retained private-image
objects. TEST usable; production remains NO-GO until these gates close. The empty
catalog check is a mandatory future launch procedure, not a current catalog defect.
Cash #753 Inbox/mobile acceptance remains an evidence-only owner checkbox; no resend.
No legal-document status is changed or inferred by this non-legal report.
