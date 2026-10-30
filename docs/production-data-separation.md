# Production data separation — preparation only

Baseline: [current operational identity](current-operational-baseline.md).
No TEST data is changed and no production connection/import is performed.
Use a fresh production database and explicit allowlists. A full TEST SQL dump,
`wp_options` export, uploads-tree copy or user-table copy is not a launch seed.

A = application/configuration to review and reproduce; B = TEST data to exclude;
C = secret/environment-specific configuration to provision separately;
D = legal content owned by the legal approval process, unchanged here.

| Area | Class | Production action / acceptance |
| --- | --- | --- |
| Theme/custom plugins, checkout, My Account, Back Office, email templates, fulfilment | A | Exact approved rewritten source; preserve proven behavior. Dependencies from approved versions. No demo seeder/provisioner execution. |
| Products/variations, all statuses and trash | B | Export/import none. Zero products AND zero variations before Martin adds real devices. No model/color/photo curation of TEST. |
| Product media | B, with explicit A exceptions | Exclude photos used only by demo products. Reviewed homepage/logo/contact/shared media may be allowlisted as A; reference review, not attachment parent alone, decides. No wholesale uploads copy. |
| Homepage hero/category sections, Contact, navigation, menu/site identity | A | Preserve approved public layout/settings and sanitized assets. Remap attachment/page IDs after import, replace environment URLs through supported tools. Store address: 6722 Szeged, Jósika utca 4.; registered seat stays separate. Product-specific featured links must be empty or mapped only after real stock exists. |
| Categories/global attributes | A where required | Recreate only reviewed category slugs/attribute definitions needed by storefront filters/navigation; no old term-product relationships or stock. Archive unused demo terms rather than assuming all are needed. |
| Orders, refunds, order notes/history, stock reservations, invoice/parcel IDs, warranties | B | Import none, including checkout drafts, trash and custom statuses. No old callback/email/idempotency markers or order download permissions. |
| Customers, QA users, addresses, carts, sessions, persistent carts, cookies | B | Import none. Empty sessions/carts; no customer passwords/tokens. Do not touch original TEST users. |
| Administrators/employees | C | Recreate named approved humans with minimum roles; new credentials/MFA/recovery. No QA admin, shared password, application password or session token migration. |
| Woo general/store/currency/tax/page configuration | A/C | Review non-secret values individually; regenerate target page IDs/URLs. Do not bulk-copy options/transients/scheduled actions. No invented tax or legal policy. |
| Shipping rules | A/C | Preserve business model: GLS point/locker 1,490 / home 1,990; manual MPL point 1,040 / home 2,090; pickup 0 HUF. No generic free courier. Real dispatch/account details separately verified. |
| BACS/payment selection | A | BACS intentionally OFF, instructions/description empty; do not request bank data. Barion only for all webshop orders, including personal pickup. COD/pay-at-pickup unavailable; separate Back Office in-store cash recording remains. |
| Barion | A/C/B | Keep integration source. Configure separately approved live account/key/environment/callback URLs later. Never import sandbox keys, payment IDs or TEST callback jobs. |
| Számlázz.hu | A/C/B | Keep adapters/email gates; separately authorize production service credentials and Woo PRO/site license. Do not transfer TEST invoice numbering/references/PDFs/retry jobs. Do not bypass licensing. |
| GLS | A/C/B | Keep source/method model; separately authorize real sender/provider account and environment. No TEST parcels, tracking, labels or queued requests. |
| MPL | A/C/B | Preserve approved manual minimum mode/rates and selector; real sender/contact/dispatch procedure separately verified. No fabricated dimensions, paid/API activation or TEST shipments. |
| SMTP | A/C/B | Keep maintainable authenticated transport and failure visibility. Configure approved sender/Reply-To and mailbox secret privately; no TEST allowlist/QA recipient/logs. Re-enter encrypted plugin credentials if salts change. Verify production identity later; current .hu QA acceptance is not automatic .com sender authorization. |
| Monitoring | A/C/B | Keep checks/dedup behavior, configure real hosts/destinations/private SMTP or issue tokens separately. Do not copy active TEST incident state, recovery markers or QA alerts. No automatic human message in preparation. |
| Backups | A/C | Reuse encrypted-set tooling with separate paths/identity/host guards, reviewed coverage, retention and destinations. No TEST restore into production. New environment activation requires explicit approval. Recovery key independent of both application server and Storage Box. |
| WP salts, DB credentials, SSH/WireGuard keys, TLS/certs | C | New environment credentials; never copy TEST identities. If recovering the same TEST, see DR dependency ordering for encrypted plugin options and controlled secret rotation. |
| Domain/siteurl/home, BO origin, proxy, callbacks, cron | C/A | Target-specific approved values. No TEST/localhost URLs or TEST cron/provider retries. Start with cron, mail and external providers blocked until explicit acceptance. |
| Legal pages/checkout declarations/cookie policy/registered company seat | D | Keep unchanged in this task. Transfer only through the separate approved legal-content process; this matrix does not assert legal readiness. |
| Caches, mail logs, exports, temporary files, backups, old Git safety bundles | B/C | Never public website/repo content; no migration except explicitly private operational recovery archives. |

## Evidence to retain for a future authorized launch

Record exact source SHA, private allowlists of page/media/settings keys, ID-remapping
results, zero all-status product/variation/order/refund counts, reviewed named
employee list, no TEST sessions/provider jobs, production-specific secret setup
and guarded smoke-test results. Do not put option values, personal data, keys,
PDFs, invoices or passwords into the public Git report.
