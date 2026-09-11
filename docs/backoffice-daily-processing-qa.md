# Back Office daily-processing refinement — local QA

## Scope and concise audit

Verified on 2026-09-10 in `feature/appleklinika-backoffice-v1`, Docker project `appleklinika-backoffice`, `http://localhost:18080/backoffice/`. No commit, push, merge, deployment, provider activation or external provider request was performed. Existing checkout changes were preserved.

The original open list contained 35 orders while its five queue counts totalled 34: the physically picked-up order #1384 remained in the default list. WooCommerce completion and physical Back Office handover were conflated by the old completed-filter label. The queue now has 34 open orders, excludes physical terminal states, and separates physical handover from WooCommerce `completed` without scanning order history.

The original detail escaped WooCommerce's already-formatted address again, rendering literal `<br/>`. The device panel could show the current product identifier instead of the identifier saved on the order. Next actions, exceptions and document availability lacked clear hierarchy. Manual notes were absent from employee activity; a user with only the Back Office capability was also recorded as `system` in WooCommerce note authorship despite a correct workflow actor.

## Implemented batches

1. Operational queue and detail: linked real summary counts, consolidated queue labels, distinct payment/handover/completed filters, result heading, next-step or attention cue per row, stronger order-title/back-action hierarchy, delivery-specific progress, compact customer/payment/device sections, bounded-height history, responsive layout, and safe multiline addresses on detail and print.
2. Data and daily activity: immutable order-line names and identifier-snapshot precedence; explicit order-time/current identifier source; correct COD wording; manual-note action with employee ID/name, order, unchanged state and time; actual WooCommerce note authorship for BO-only employees without granting wp-admin order-edit capability.
3. Existing documents: read-only provider adapters and protected inline PDF endpoints, accurate unavailable states, actual numeric GLS tracking links, frontend-compatible provider readiness, sandbox guard for local/test hosts and workflow validation before provider effects. No new invoice-generation operation.

## Authenticated browser results

| Check | Result |
| --- | --- |
| Default open queue | 34 orders; summary 30 + 1 + 0 + 3 + 0 = 34 |
| `payment_pending` / Fizetésre vár | 1 order: #1375; select change submits the real GET request |
| `payment_on_hold` / Fizetés ellenőrzendő | 3 orders: #1376, #1206, #537 |
| `handed_to_gls` / Átadva / átvéve | #1384; no longer in the open queue |
| `wc_completed` / Lezárt rendelések | #1377 and #530, including orders without Back Office history |
| Automatic `1376` and `#1376` | Both return #1376 |
| Exact device `QA-GLS-IMEI-001` | Returns #1383; detail and print show the same saved identifier |
| Automatic `GLS QA`, explicit email and phone | Each returns #1383 |
| Unknown device identifier | 0 results and a usable empty state |
| `new` + customer `Pagination QA` | 26 orders: 25 on page 1, #1349 on page 2; stable context |
| Filter + search + detail back | Payment filter and `#1376` retained; second-page name search retained |
| Address/print | Real line breaks; no literal `<br/>`; internal order sheet clearly distinguished from GLS label |
| Inactive invoice/GLS | No fake PDF or generation button; actionable explanation instead |
| Responsive width 390 px | No horizontal document overflow in queue/detail; next action before order data |

A temporary local pickup order #1389 was created with WooCommerce CRUD, a non-stock test line and no external requests/mail. From filtered search page 2 the BO-only QA user exercised start, manual note, prepare pickup, problem, resume, prepare pickup and pickup confirmation. Every mutation preserved all four list-context fields. Returning restored the same filtered page; the now-finished order correctly disappeared from its old queue. Reload did not duplicate the note. A second note verified corrected WooCommerce authorship. Today's activity showed all eight actions under that employee, including both notes, without their contents in the activity payload. The QA order/user and their daily activity entries were removed after verification.

## Automated verification

`COMPOSE_PROJECT_NAME=appleklinika-backoffice make test`: 147 passing assertions across workflow (45), query (16), documents (25), repository (41) and rendered views (20). These suites use isolated stubs; document fixtures are temporary and removed. Render tests call the installed WordPress sanitizer without loading the database/plugins. Coverage includes queue partitioning, HPOS grouping, search, pagination/context, pickup/GLS actions, private customer rendering, note attribution and hook cleanup, inactive/missing PDFs, path traversal/remote/symlink rejection and actual provider-shaped tracking metadata.

Additional read-only checks against the real local fixture: 13 passed for saved employee identity, nonduplicated notes, note author, history privacy, owner/nonowner/anonymous customer rendering, PDF nonce checks, capability checks and unavailable document denial. PHP syntax and whitespace-diff checks pass. `make quality` completes, but currently contains placeholders: no actual linter/static analyzer is configured.

## Integration findings and remaining runtime limits

- Installed Számlázz.hu integration: invoice number/PDF metadata are `_wc_szamlazz_invoice` / `_wc_szamlazz_invoice_pdf`; `generate_download_link($order, 'invoice', true)` resolves provider-owned files beneath `uploads/wc_szamlazz`. Existing PDFs can be opened/printed through the BO endpoint when active and readable. The actual invoice-generation method makes direct service requests; a verified provider test account is required before exposing or testing generation.
- Installed GLS integration: `_gls_print_label` stores a filename under `GLS_LABELS_DIR`; `_gls_tracking_codes` and legacy `_gls_tracking_code` hold parcel numbers. BO reuses the provider's `generate_single_order_label()` operation rather than implementing carrier logic. Local/test generation requires sandbox account and complete sender data. Legacy direct-URL labels are not fetched or migrated.
- Both plugins are inactive in this runtime. Existing-file positive tests therefore use isolated local adapter fixtures, not invented documents attached to business orders. Real provider-generated PDF opening/printing and end-to-end generation remain unverified until controlled test configuration is available.
- The existing local Apache configuration only explicitly routes `/backoffice/` and `/eladas/`; `/fiokom/` returns an Apache 404, including canonical redirects from the query-string page URL. No unrelated rewrite change was made. Customer privacy was checked with the real WooCommerce renderer and local fixture, but normal My Account browser navigation remains blocked by that local routing issue.
- The next high-impact integration batch is controlled document QA with verified GLS sandbox and invoice test credentials, followed by the provider's existing supported invoice-generation flow only after its test safety is established. Simultaneous multi-employee activity updates remain on the existing transient implementation; no analytics architecture was added.

## 2026-09-11 staging-candidate pre-commit review

The intended diff is limited to the Back Office plugin, its five regression scripts, Makefile test wiring and related documentation (17 files). The existing feature branch tracks `origin/feature/appleklinika-backoffice-v1`; it was synchronized before the candidate commit. No environment file, credential, upload, log, generated PDF, temporary QA script or unrelated application change is included. Localhost references are confined to QA documentation/test fixtures and the explicit safety-host check; application navigation uses WordPress URL helpers. No active Git hook or tracked GitHub Actions deployment workflow was found.

One narrowly scoped safety correction was required: a direct HTTP request to `tests/router-views.php` originally ran the isolated test and returned 200. All five test scripts now require CLI and return 404 over HTTP before running fixtures. The existing 147 assertions, PHP syntax checks and `git diff --check` pass. `make quality` was run and still contains only placeholder targets. The plugin uses directly served PHP/CSS; no separate frontend build is configured.

Authenticated, read-only browser smoke checks passed for the 34-order list, GLS detail #1383 with device/address/document states, payment-pending filter (#1375), automatic ID search (#1376), and `new` + customer search page 2 → #1349 detail → the same filtered second page. No workflow action, order mutation or provider request was performed. The temporary BO-only QA user was deleted afterward.

This is a candidate for controlled staging testing, not a deployment or production-readiness declaration. The integration and local My Account routing limitations above remain. Before deployment, set WordPress's environment type explicitly to `staging` and keep providers inactive until test/sandbox credentials and safe test data are verified. The default WordPress `production` label on a non-localhost staging domain would otherwise defeat environment-based sandbox detection.
