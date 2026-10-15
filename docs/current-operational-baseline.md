# Current operational baseline — 2026-10-15

Initial 2026-10-15 baseline: **`1d6af15bd116adb66cdd66fd72ab75b7dba39e56`**.
The approved selector was subsequently deployed as **`9184126f7ea22572cca3ad0f3f00221ad146e9de`**.
For the completed engineering release and deployment evidence, use
[the latest acceptance record](qa/preproduction-engineering-20261015.md).
This is the normal documentation follow-up after the authorized history rewrite,
not the intermediate rewritten develop head `7940b8c71443e9a6a68c1f6fcc9b090601784418`.
Use the published baseline for deployment/recovery identity checks. No deployment
is authorized by this document; newer approved deployments must record their exact SHA.

## Active checkout and recovery rules

- TEST SSH: `appleklinika-test` only. Host `appleklinika-staging-01`, repo
  `/opt/appleklinika/repo`, runtime `/opt/appleklinika/production` (despite that
  directory name, this is TEST). Validate hostname, WordPress environment `staging`
  and `https://teszt.appleklinika.com` before a future authorized operation.
- Clean integration/deployment checkout:
  `~/.codex/worktrees/security-round1-test/appleklinikawebshop`, `develop/post-deploy`.
  Validate a fetched commit against the exact approved SHA, not a stale audit label.
- Durable LOCAL runtime: `~/Desktop/appleklinika-integration-active`, branch
  `feature/product-selector-ui-cleanup`, HEAD
  `923b8e81556bcd73b1355ceed62974ce6d6db7b9`. It is a protected LOCAL checkpoint,
  not a claim that LOCAL is currently running the latest published TEST source.
- Protected storefront recovery: `~/Desktop/appleklinika-storefront-active`,
  `feature/local-environment-recovery`, `2f01b89799ffdddcba684f2217292cce1a470467`.
- Protected unique LOCAL Back Office: `~/Desktop/appleklinika-backoffice-v1`,
  `feature/appleklinika-backoffice-v1`, `b58eeb0cd278181dd494490dd48d9508cc926cc1`.
  Do not replace its unique unpublished work with the public branch tip.
- Dirty `~/Desktop/appleklinikawebshop` remains quarantined. Do not reset/clean it,
  deploy from it, delete its shared Git storage, or push all of its refs.
- Old clones/bundles are private recovery evidence. Re-clone rewritten public
  history for ordinary work. Never merge old ancestry or publish a recovery mirror.
  Repository-local pre-push guards do not automatically travel with a fresh clone.

## Compact checkpoint map

These entries identify equivalent code, not a request to deploy an older checkpoint.

| Purpose | Old identity | Rewritten identity |
| --- | --- | --- |
| Pre-rewrite develop | `5edcec32036fc1ab118232236015b732fc246dd7` | `7940b8c71443e9a6a68c1f6fcc9b090601784418` |
| Integrated fulfilment | `79be7f7e3de1f855929ff143ab8453f6c77072f9` | `c2cc97bb64377d1af7b63696a845e3a051d1f1f5` |
| Transactional templates | `172366d22f83a4a5e73a533c257861e15adcf518` | `55054c7b14582aa5ad01c7e4de501e44b1e9231b` |
| Accepted three-email TEST | `490e6e753bb3c610bc2dc410b8f66a1eac77ae3b` | `44c8accd908916bf26c2aec417e17540f713f29b` |
| Non-legal checkout baseline | `b7769e996b7d4926ca05dc4b5371b3bc2ce277da` | `ba776c6588823f02e60cbb4771621becc1b7ff66` |
| Canonical store address | `a5b4c099299940ecc94119b923df4381ec26d7ec` | `1293ba723abc725ab9470e2aafde2a9cd684d68b` |

The complete main/tag/branch mapping and proof remain in the
[history migration record](qa/history-privacy-migration-20261014.md). Main and
production are not modified by this preparation. Older dated reports retain their
original evidence SHAs. Their historical “current”, blocked or pending statements
are not active deployment instructions.
