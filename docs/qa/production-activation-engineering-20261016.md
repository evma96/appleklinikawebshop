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

Implementation checkpoint and normal fast-forward develop/public push:
`40d590ce90359cfe8be8b60abeb0e64c912ae15f`.
That exact application release was deployed through `appleklinika-test`; 20 changed
plugin PHP files and the mandatory MU bootstrap were compared against repository
source. The detached TEST release checkout advanced normally, without force/reset.
The following documentation-only closeout does not change those application files.

- Post-maintenance WordPress load: environment `staging`, expected TEST home,
  WordPress 6.8.10 / WooCommerce 10.7.0, lifecycle enabled, production activation OFF.
- Before/after keyed configuration digest: identical. Barion TEST, GLS sandbox,
  BACS OFF, cash pickup enabled, manual MPL enabled/API disabled, shipping methods
  and prices unchanged. Generic free shipping remains disabled; pickup remains free.
- Existing order/user counts unchanged: 73 orders / 16 users. No QA account, order,
  invoice, parcel or email was created by this task. TEST data is not cleaned or
  mistaken for a production launch seed.
- Read-only readiness result: production environment/providers/launch data NOT READY;
  customer email/payment policy/manual MPL READY; MPL API NOT APPLICABLE. OFF is the
  expected safe result on TEST, not an application failure.
- Inventory reports 123 products, 0 variations, 73 orders, 16 unapproved user IDs,
  13 customer/subscriber roles, 1 QA-named user, 2 sessions, 4 persistent carts,
  1 stock reservation and 0 pending provider jobs. These are existing TEST artifacts;
  the production preflight detects them and would refuse launch. The isolated fresh
  production fixture passed with zero forbidden data.
- Homepage, Contact, My Account and protected Back Office login: HTTP 200, no fatal
  output. The Back Office endpoint correctly redirects anonymous visitors to login;
  authenticated workflows were covered by the isolated shared-state regression,
  not claimed as a new authenticated TEST browser test.
- Browser: homepage/carousel renders; selector checked at 1440 desktop and 390 mobile,
  battery choice absent, aligned desktop cards, mobile scroll width equals 390,
  no captured console errors. Canonical store address remains 6722 Szeged, Jósika utca 4.
- Post-deploy container log inspection: zero PHP fatal/warning/parse/uncaught matches.
- No TEST provider E2E repeated; no new provider transaction or customer email.
  Outbound provider adapters were fake/blocked in the offline scenarios; the existing
  TEST provider configuration was inspected read-only.

Private evidence directory:
`~/.codex/visualizations/2026/09/11/01a08fcb-a363-7761-acb7-46d99e2bde2c/production-activation-engineering-20261016/`.
It contains regression/deployment logs, redacted configuration facts and desktop/mobile
screenshots; it is not part of Git. No provider credentials are recorded.

The production readiness CLI is implemented for WP-CLI-capable installations. This
TEST container does not have the WP-CLI executable: the same read-only report was
invoked through PHP; its underlying readiness policy is covered offline. This is tooling
availability, not a production-readiness failure.

## Classification

Engineering: **Critical 0 / High 0 / Medium 0 / Low 0**. The previous HIGH
activation-path gap is closed after offline proof and the focused TEST release check.
Actual production activation is deliberately pending, not a new engineering defect.
**ENGINEERING PRE-PRODUCTION GO** does not authorize or claim production-live.
External: **Medium 2** — independent BX11/restore/key custody; GitHub retained old-object
removal. Neither was rechecked or altered in this task. Legal/VirtualJog approval and
remaining owner business/customer-side evidence stay separate; no new legal opinion.
Demo catalog image mismatches remain disposable TEST data, not production blockers.

Current combined non-legal finding totals: **Critical 0 / High 0 / Medium 2 / Low 0**.
The two Medium items above are external prerequisites, not reopened engineering gaps.
