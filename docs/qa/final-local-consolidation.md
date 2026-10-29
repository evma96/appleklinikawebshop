# Final LOCAL consolidation — staged, release blocked

Date: 2026-10-29. Branch: `feature/final-local-consolidation`.

## Decision

Independent approved packages are checkpointed and combined locally. **FINAL TEST RELEASE BLOCKED**: the newer Back Office order-command/background-worker integration remains incomplete. No develop merge, push or deployment was performed. Original localhost:8082 and localhost:18082 code/configuration/databases were not changed. The staged candidate is not installed on either URL.

Earlier reports describe their historical uncommitted state. The checkpoint table below supersedes those status statements, not their acceptance evidence.

## Verified boundary and checkpoints

| Package | Source / checkpoint | State |
| --- | --- | --- |
| Local develop, public remote develop, TEST repository | `56c2646f70ad481bc80f20cd8512a1314c653b91` | Unchanged; checked through Git and private `appleklinika-test` SSH |
| Approved presentation, specifications, performance prerequisites | `4a376d3377342408ecbd6d630d7ca4020e48f7e9` | Exactly 34 files, checkpointed in the isolated selector worktree |
| Smart Selector | `92b791351f9d3a15570a52b27c67bedb03c1f554` | Exactly 9 intended files; source worktree clean |
| Barion-only webshop policy | `1207ab7500e6896c72fece69225b8f7e5f385369` | Exactly 12 intended files; source worktree clean |
| Already running TEST Back Office repairs | `b9e43ddd66dabcb3ec8ca9ba4200f39917857292` | Exactly 30 files; both approved deployment patches preserved |
| Prerequisites on latest develop baseline | `10c570d314651082a525ada65be98f4ad2f808a0` | Normal cherry-pick; no application-code conflicts |

The TEST Git checkout is clean, but its running Back Office plugin is a separately mounted deployment. Exactly 30 files differ from the Git baseline. All match the combined `backoffice-workflow-staging.json` and `backoffice-artifact-reconciliation.json` manifests (zero mismatches). The reconstructed plugin matched all 96 inspected runtime source-file hashes. Reporting the repository SHA alone would therefore be insufficient evidence of the entire existing runtime. This drift is now captured in the local candidate; TEST itself was not rewritten.

No whole-file conflict choice was used. Smart Selector and payment integration conflicted only in README/deficiencies appendices; both sets of documentation were retained. Inventory source matches the accepted selector snapshot byte for byte. Six theme differences from that older snapshot were reviewed and retained from the newer develop baseline: existing pickup/checkout/contact presentation and filter-chip styling/tests.

## Included independent work

- Approved three-state selector: exact physical match, explicit other-combination choice, visible disabled unavailable options; multiple physical matches require a choice.
- Batched inventory/listing/search queries and bounded recommendation queries.
- Versioned canonical technical profiles: 102/102 current models, 103 stored exact Apple source profiles including the preserved historical orphan. Guarded provisioning preserves unknown manual edits.
- Existing unique physical-device service, private identity boundary, HEIC derivatives and metadata/color-profile preservation from the verified develop baseline.
- Approved product-page grids, adaptive controls, full-width purchase action, hidden quantity UI and up-to-six same-family recommendations.
- Existing Extra védelem association/native order mapping.
- Current verified TEST Back Office payment/readiness and invoice/GLS reconciliation fixes, captured without changing their behavior.
- Barion-only webshop eligibility for GLS, MPL and free personal pickup, stale session correction, server-side rejection and Blocks eligibility. Internal staff cash recording and activation guards remain unchanged.

## Exact remaining dependency

`/Users/apple/Desktop/appleklinika-backoffice-v1`, branch `feature/device-intake-final-decisions`, HEAD `b58eeb0cd278181dd494490dd48d9508cc926cc1`, contains 115 pending paths. The completed device-intake UI is mixed with the unfinished canonical-runtime/order integration. It is preserved, not blindly copied, committed as complete, or deployed.

The chats “Termékfeltültés” (idle) and “Build Apple Klinika back office” (not loaded) have no active competing turn at inspection. Their completed status does not certify that the documented integration dependency is finished. The latest intake acceptance explicitly leaves the order integration read-only.

Current runtime verification confirms:

- `appleklinika-backoffice-authoritative-wordpress-1`: `AKBO_CANONICAL_PRODUCT_WRITES=1`;
- the same runtime: `AKBO_CANONICAL_WRITES=0`;
- `CanonicalRuntime.php` still refuses general/order writes;
- `docs/backoffice-authoritative-local.md` requires an authenticated command boundary and a single authoritative execution owner for invoice/GLS reconciliation and intake cleanup jobs.

The staged candidate includes the older, already proven TEST reconciliation worker code. That does **not** certify the newer 18082 UI's command routing, permissions, scheduler ownership or full operational replacement. Those shared-source/runtime decisions and their regression proof must be completed before integrating the newer intake/Back Office package and releasing the whole candidate. No architecture implementation was started in this task after discovering this STOP dependency.

## Verification performed

- Merged-candidate `make test`: **1,163 assertions PASS**, including the previously deployed Back Office reconciliation, payment/readiness, lifecycle, security and legal-binary regression suites. Tests use local doubles; no real provider transactions.
- Smart Selector JavaScript: **1,969 PASS** using approved public model pools.
- Woo Blocks payment eligibility: **15 PASS**.
- Offline specification provisioning: **435 PASS**; recommendation selection: **23 PASS**.
- Native isolated LOCAL checks against byte-identical Inventory source: public selector projection/privacy **428**, model options **69**, purchase rendering **116**, information presentation **48**, gallery **28**, query/image equivalence **103**, all PASS. The query suite removes its own fixtures and verifies the copied catalog is unchanged.
- **4,397 total assertions** across those suites. This is independent-package/staged-candidate regression evidence, not a complete new Back Office or TEST acceptance run.
- Native canonical audit: 102 models with profiles, zero missing model profiles or source URLs; 103 exact Apple source profiles. No live Apple requests.
- `make quality` returns success but explicitly has no configured linter/static analyzer; no static-analysis coverage is claimed. PHP syntax checks pass.
- An initial payment test invocation lacked Node on PATH; rerunning with the bundled Node path passed. This was a tooling issue, not an application defect. Simulated GLS failure log lines belong to passing offline failure/recovery tests.
- Existing accepted selector desktop/mobile evidence (four families at 1440/1280/390/320) is retained. No new merged-runtime or TEST visual acceptance is claimed while the dependency is open.

## Performance and deployment status

TEST remains at its original repository SHA. Two read-only homepage health probes returned HTTP 200, TTFB 0.469323 s and 0.688762 s. These are existing-baseline observations, **not** a post-release performance benchmark. Candidate TEST performance, desktop/mobile smoke, deployment/config provisioning and full combined checkout/Back Office acceptance remain pending.

The earlier approved isolated selector benchmark remains applicable to unchanged selector code (iPhone 112.17 -> 110.13 ms; iPad 56.28 -> 64.72 ms; MacBook 48.97 -> 51.45 ms; Watch 54.71 -> 53.17 ms). It must not be relabeled as TEST performance.

## Preservation and cleanup

- Original storefront worktree: 34 pending paths, content hashes unchanged. Its working database/session/gateway settings were not edited or falsely declared released.
- Newer Back Office worktree: 115 pending paths, content hashes unchanged; no unfinished work discarded.
- Smart Selector and payment source worktrees, the final staged candidate and develop worktree are clean after checkpointing.
- Other historical/quarantined worktrees were inspected but not cleaned, reset or rewritten.
- No QA/runtime data, credentials, private configuration or visualization files were committed. Focused secret-pattern scans were clear; native fixtures created by the query test were cleaned by its supported workflow. No TEST QA artifacts were created.
- Main remains `66943bad0307ca029f46f0cf1e43bd8ff13bfbe7`. No production access, main modification, force push, Git history rewrite or real payment/invoice/shipment operation.

Evidence outside Git: sibling `final-consolidation-20261029/` contains original file fingerprints, live TEST plugin hashes, drift comparison and checkpoint staging proof. Earlier acceptance artifacts remain in their original evidence directories.

## Resume point

Complete the newer Back Office canonical order-command/background-job boundary and its acceptance proof first. Then integrate that completed package into this local candidate by reviewed hunks, run full combined native checkout/Back Office/selector/media regressions, and only then merge develop, push normally and deploy/verify the exact SHA to TEST. Preserve the media overlay and all TEST guards. The current candidate must not be deployed as a completed release.
