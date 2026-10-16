# Production activation engineering — 2026-10-16

Initial public develop/TEST: `e56fd9f521e5e72b77827a7eebc7b0ced6bc8d7c`.
Feature: `feature/production-lifecycle-readiness`. Main remains
`66943bad0307ca029f46f0cf1e43bd8ff13bfbe7`.

## Scope and guard review

[Complete guard map, capability checklist and activation/OFF procedure](../production-lifecycle-activation.md).
Production is OFF by default; no production connection, configuration, transaction
or deployment is performed. No legal text, customer email design, price, catalog,
shipping model or TEST provider setting is changed. The mandatory MU bootstrap is
inert in staging/local and preserves the independent existing private MU overlays.

Production approval lives outside the web root/Git. All required capabilities must
be ready independently of the explicit enable flag. Missing/invalid configuration,
wrong host/mode/account, missing legitimate PRO, unapproved SMTP or operations
prerequisites fail closed. Live Számlázz account/site license and actual operational
readiness require human verification; this code does not spoof licenses or claim to
probe live services. The signed zero-data receipt is tied to the site salt/home and
activation window; runtime can subsequently serve legitimate manually entered stock.

## Offline evidence

- `make test`: **550** assertions, including **53** new production readiness and
  **4** actual vendor transport-boundary checks. PHP syntax also verified.
- Isolated production-mode WordPress/Woo HPOS: **35** assertions. Real Woo orders,
  persisted stock and native Barion IPN handler; fake Barion response, Agent invoice,
  GLS label and mail transport. No external HTTP attempt, no actual email or provider
  transaction. Containers/volumes/credentials/QA artifacts destroyed afterward.
- Selector/render regressions: **120** assertions; unsaved fixture objects only.
- Existing operations/data-separation fixtures: **45** checks (13 catalog, 14 encrypted
  off-server/recovery, 9 monitoring classification, 9 reusable backup).
- Combined: **750** passing checks. `make quality` succeeds but only reports that no
  linter/static analyzer is configured; it is not a substantive static-analysis pass.
- `git diff --check` with `cr-at-eol` passes; Barion's existing CRLF format is preserved.
  Intended changed files reviewed; no real keys/private configuration/runtime artifacts.

| Scenario | Evidence |
| --- | --- |
| A: absent activation | Lifecycle/mail/invoice/GLS/Barion/stock denied, including direct vendor transport calls before cURL/files. |
| B: one required capability absent | Each provider pin removed in turn; operation blocked with fixed actionable code, no partial external side effect. Actual Woo payment/stock blocked with GLS capability missing. |
| C: valid simulated production | Exactly three lifecycle messages, one invoice/PDF, one GLS label; real native callback replay, repeated hooks and correction/re-handoff do not repeat mail/invoice/stock/parcel. Completed remains separate. |
| D: cash pickup | Acknowledgement and explicit staff acceptance remain unpaid/no PDF/no false successful-payment wording. Recorded pickup payment creates one invoice; stock reservation is not deducted again. |
| E: BACS | Never exposed; enabling stale BACS configuration blocks readiness. Cash unavailable for courier. |
| Empty launch | Fresh real Woo DB passes; products/orders/customer data later refuse a new empty certificate. Named QA or customer roles cannot be allowlisted as staff; inventory errors refuse evidence. |
| Emergency OFF | Overrides otherwise valid activation; blocks new mail/provider/payment/stock/fulfilment work without code replacement. |

Five fixture messages total = three Barion messages plus two cash messages; two fake
invoices and one fake GLS parcel across those independent scenarios. This is application
engineering proof, not actual production inbox or provider availability acceptance.

LOCAL selector was personally viewed at desktop/mobile widths; battery choice remains
absent, three desktop cards align, no UI changes. Its source is identical to the
accepted selector. The durable LOCAL branch is an earlier protected baseline, not a
claim that it serves this new engineering code; the new code ran in isolated LOCAL.

## TEST release verification

Pending authorized normal integration/deployment of this reviewed checkpoint.
The final delivery record will add exact source identity, read-only configuration
comparison, HTTP/WordPress/Back Office health and production OFF evidence. No TEST
order/provider E2E is repeated: live behavior is guarded by the unchanged TEST branch
and the focused offline regressions. No new QA account/order/email/provider record.

## Classification

Engineering: previous HIGH activation-path gap can close after the focused TEST
release check; production activation itself is deliberately pending, not a new defect.
External: **Medium 2** — independent BX11/restore/key custody; GitHub retained old-object
removal. Neither was rechecked or altered in this task. Legal/VirtualJog approval and
remaining owner business/customer-side evidence stay separate; no new legal opinion.
Demo catalog image mismatches remain disposable TEST data, not production blockers.
