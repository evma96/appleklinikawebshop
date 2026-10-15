# Non-destructive launch preparation — 2026-10-15

Historical earlier preparation record. The later [engineering run](preproduction-engineering-20261015.md) reviews and checkpoints this package; its deployment status supersedes the earlier uncommitted status below.

Published develop/TEST remains **`1d6af15bd116adb66cdd66fd72ab75b7dba39e56`**.
LOCAL branch: `feature/launch-operations-preparation`. No commit, push, deployment,
production/main/legal change, BACS enablement, provider call or TEST data mutation.

## Completed preparation

- Active operational reference/map introduced; obsolete split Back Office deployment
  pin removed from current instructions; durable LOCAL branch corrected. Dated
  lifecycle/recovery reports retain original evidence SHAs with a current-baseline
  pointer. README/deficiencies mark superseded pre-rewrite readiness explicitly.
- Empty-production runbook expanded and read-only catalog planner added. It refuses
  production and lists IDs to exclude/keep/review without a delete/import mode.
  Shared site media is kept only by an explicit reviewed allowlist. No TEST data
  inventory/delete/reclassification was executed against WordPress in this task.
- Application/config, TEST data, environment secrets and legal content separated
  in the [migration matrix](../production-data-separation.md).
- Disabled BX11 adapter improved: exact subaccount host, strict SSH, checksum retry
  verification, correct backup-age semantics, private state/lock, opt-in monitoring,
  verified download and retention planning. No automatic remote pruning or connection
  in default mode. Service/timer templates remain uninstalled/unstarted.
- Real CMS encrypt/decrypt offline fixture passes. Decrypt verifier checks all four
  streams with no private plaintext output or extraction. The consolidated
  [DR runbook](../disaster-recovery.md) covers all five requested failure scenarios.

## GitHub Support package — draft only

Private owner evidence directory (outside Git):
`launch-preparation-evidence-20261015` beside the active Codex worktrees.

- `github-support-request.txt`: ready to paste, not sent.
- `github-technical-evidence.md`: proof/method/scope and prior rewrite verification.
- `github-exact-urls-and-shas.txt`: exact three URLs, three old blobs, one commit anchor.
- `github-url-recheck.json`: fresh machine-readable check.

Finalization check: **2026-10-15 09:41:29 UTC**. All three raw URLs return HTTP 200;
received bytes hash to the three original contaminated Git blobs. Bytes were held
only in memory, not saved or decoded into private EXIF values. This proves three
URLs / three unique image objects; it does not claim every possible cached alias
has been enumerated. The historical commit is the anchor, not a fourth tested URL.

All **51 current public branch/tag refs** exactly match the previous independently
verified clean public state. Reachability over those exact refs contains none of
the three old blobs. The prior full 486-image scan still applies; no claim of a
new full scan is made. Owner must submit Support and await purge confirmation.

## Verification

- `make test`: **483** existing offline lifecycle/security assertions + PHP syntax PASS.
- `make quality`: configured target PASS; lint/static-analysis tools remain placeholders.
- `test-catalog-launch-plan.php`: **13** fixture guard/separation/shared-media assertions;
  collector/test PHP syntax PASS. No live DB inventory was run.
- `test-offserver-sync.py`: **14 test cases**, mocked SSH/rsync, plus real ephemeral CMS
  encrypt/decrypt fixture. Covers defaults, corruption, permissions, host/environment,
  retry re-verification, no overwrite, retention floor, stale-source/monitor behavior.
- Existing monitor classification **9** and reusable backup/restore/failure **9** checks PASS.
- Diff whitespace and changed-file high-confidence secret scan PASS. No application
  source, legal files, runtime data, production secret or persistent QA artifact added.

No actual BX11 connection/upload/download, scheduled job acceptance or independent
restore can be claimed before provisioning. The synthetic fixtures are not live
remote evidence. Prepared source remains uncommitted for a later authorized checkpoint.

## Remaining non-legal gates

**Critical 0 / High 0 / Medium 2 / Low 0.** Production NON-LEGAL NO-GO remains:

1. MEDIUM / PRIVACY: GitHub retained historical private-image objects/cache purge.
2. MEDIUM / OPERATIONS: BX11 independent encrypted copy, real round-trip/application
   restore and confirmed encrypted off-Mac recovery-key custody.

Empty production catalog is a future launch acceptance gate, not a demo-photo defect.
BACS is intentionally OFF. Cash #753 customer Inbox/mobile evidence remains an
owner-only confirmation checkbox; technical proof is retained, no email resent.
No other proven storefront/payment/fulfilment/security system was reopened.
