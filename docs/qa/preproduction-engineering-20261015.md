# Pre-production engineering — 2026-10-15

Initial develop and TEST: `1d6af15bd116adb66cdd66fd72ab75b7dba39e56`.
Approved LOCAL selector checkpoint: `923b8e81556bcd73b1355ceed62974ce6d6db7b9`.
Normal integration and TEST selector deployment: `9184126f7ea22572cca3ad0f3f00221ad146e9de`.
Application/operations checkpoint: `0cc97ce32b1df00c38d511ea0edcae379cae9a96`.
This follow-up corrects the TEST robots multiline body and records verification.
The exact final remote/deployed SHA is pinned in the private release manifest
and owner report; do not infer it from an older historical SHA or a moving branch.

## Scope and evidence

Fresh isolated LOCAL database at port 28082, separate Compose project/volume,
application-only internal Docker network, loopback HTTP proxy. No TEST DB dump,
users/orders/provider options were imported. Public pages/media/settings were
explicitly allowlisted. Mail, WordPress external HTTP, direct cURL and cron were
blocked; provider plugins remained inactive. This proves catalog/UI workflow,
not a new provider E2E or a fully configured production checkout.

Desktop 1440 and mobile 390: homepage/header/search/categories/shop/empty featured
section/cart/checkout entry/account/Contact/footer/stale-product 404 and Back Office.
Empty checkout returns to the designed empty cart; no orphan product selectors.
The real normal Woo admin UI created one simple physical QA product (#731), with
category/model/128 GB/Graphite/A grade/equipment/price/stock 1 and two safe fixture
images. Card/detail/gallery/selected options and mobile worked. Repeated add was
rejected at one item; stock stayed 1 without payment. With only one product, no
alternative stocked-device combination was claimed. The battery choice is absent;
factual battery health remains a product specification.

Supported Woo/WordPress cleanup removed that product, both media, and the automatic
Store API checkout draft (#735) generated while viewing the cart. No payment, invoice,
shipment or email was attempted. Final all-status counts: products 0, variations 0,
orders 0, customers 0, QA media 0, Woo sessions 0. Temporary administrator existed
only in the disposable database; the three rehearsal containers, their two networks
and named database volume were then removed. No QA account or session remains.
Fresh default CPT emitted unsupported meta-query diagnostics; the approved TEST
configuration is HPOS=yes. Applying that intended setting removed the diagnostic.
Back Office zero work queues, zero-result search and activity were rechecked on HPOS.

## Objective fixes

1. Buyback fresh migrations: the 1.0/1.1 verification incorrectly demanded columns
   introduced by 1.2/1.5, preventing a clean activation. SchemaInspector now validates
   against the migration's declared version; current-runtime checks stay strict.
   Fresh activation reaches 1.5.0. Ten offline assertions use the real CREATE statements.
2. Mobile Back Office empty-row text inherited first-column nowrap and clipped a
   688 px line into a 328 px cell. One scoped white-space rule fixes this; verified
   width and scroll width both 328 px, readable screenshot and zero body overflow.
3. TEST already had X-Robots-Tag noindex/nofollow/noarchive, but blog_public=1 and
   robots.txt redirected to the homepage. Explicit TEST robots response plus
   blog_public=0 align the protections. No production indexing policy is changed.

## Active leakage versus historical evidence

HIGH / APPLICATION: LifecycleConfiguration and related Woo-admin/invoice/GLS gates
are intentionally restricted to canonical LOCAL/TEST. Production cannot obtain
three-email acceptance merely by copying options or setting live keys. Preserve
this safety boundary; prepare an explicit reviewed production activation contract
and offline readiness tests before authorizing production. This is an engineering
launch gate, not a failure of the accepted TEST order lifecycle.

TEST hostnames in guarded operational scripts/local Compose/fixtures are deliberate;
never deploy them as production configuration. BACS compatibility branches for old
orders do not enable the gateway: actual TEST BACS=no, instructions empty. Generic
free_shipping is disabled and pickup remains enabled at 0. Dated SHA/order/recipient
references are historical proof. Obsolete active split Back Office deployment pins
are replaced by a current-baseline reference. The legacy unused Contact panel still
contains sample copy, but the approved active Contact renderer/settings use the
canonical address; no legal or dormant renderer rewrite was made.

## Health, security and limits

Six representative TEST routes (home, Contact, account, product, category, empty cart)
inspected personally at desktop/mobile widths: no broken images, duplicate IDs,
body overflow, browser console errors or visible PHP messages. 121 unique observed
first-party asset URLs return success; none exceeds 2 MB by Content-Length. This is
practical health evidence, not a synthetic performance/accessibility certification.
Keyboard focus is visible on selectors; Enter opens and Escape closes mobile map
choices; form labels and named navigation controls are present. No provider/customer
submission was made. TEST PHP logs over the inspection window: 0 fatal, 0 warning,
0 HTTP 5xx. Embedded Google Maps rendered during this run.

Reachable-history high-confidence credential scan: 15,090 objects / 10,673 blobs /
10,036 text blobs, zero candidates and zero tracked credential/backup artifacts.
It is a bounded pattern/manual review, not proof against every possible encoded
secret. Images were not modified; prior comprehensive EXIF rewrite evidence stands.
Runtime/secrets/recovery keys/backups/screenshots stay outside Git. Main and the
quarantined primary worktree remain untouched.

## Automated verification

`make test`: 493 offline assertions passed (including 10 new fresh-schema checks).
The approved selector suite: 120 checks passed. Catalog separation: 13; encrypted
off-server/recovery fixtures: 14; monitor classification: 9; reusable backup: 9.
No provider was called. `make quality` exits successfully, but currently only reports
that no linter/static analyzer is configured; it is not a substantive lint pass.
PHP syntax and `git diff --check` pass. All 31 changed/untracked intended files
received the high-confidence secret-pattern review with zero candidates.

## TEST release verification

Normal fast-forward integration/push only. Main stays
`66943bad0307ca029f46f0cf1e43bd8ff13bfbe7`. Runtime backup before the application
update: `/opt/appleklinika/backups/deploy-engineering-20261015T144657Z`. The two
changed live plugin files match the exact repository sources; PHP/Caddy validation
passes. The runtime directory contains TEST despite its `production` name.

The existing server clone fetched only main by default; a stale develop tracking
ref caused the deployment guard to stop **before** application replacement. Fetching
the explicit develop ref resolved this without force or any main change. A separate
HTTP probe caught literal newline escapes in the first robots response; the follow-up
uses a Caddy multiline body, verified against the actual Caddy parser before release.

TEST visibility is now `blog_public=0`, with noindex/nofollow metadata and the existing
noindex/nofollow/noarchive header. Both TEST origins serve a plain-text disallow robots
response. Core sitemap query returns 404; the legacy pretty sitemap route returns
noindex HTML rather than an XML sitemap. Canonical links remain on TEST.
Home/Contact/account and protected Back Office login are healthy. BACS remains off,
its instructions empty, free shipping disabled and pickup zero. No provider setting,
transaction, account permission or order state was changed. Off-server scripts are
prepared repository code only; no BX11 timer/service/monitor hook was activated.

## External gates and classification

MEDIUM / PRIVACY: exactly three known old raw GitHub URLs rechecked once; all remain
HTTP 200 with the original old blob hashes. Updated private Support draft/evidence;
not submitted, no additional history rewrite.

MEDIUM / OPERATIONS: BX11 provisioning, restricted account/host/path/pinned fingerprint,
secure key onboarding, live encrypted round trip/restore and off-Mac key custody
remain external prerequisites. The default-off adapter, monitor hook, service/timer,
retention plan and recovery verifier are prepared; 14 mocked/offline tests include
actual ephemeral CMS encryption/decryption. No connection or service activation.

Legal/VirtualJog content and cash/pickup owner evidence are separate prerequisites;
this run does not invent or reassess legal acceptance. Demo-photo mismatches are
explicitly not a production blocker; the production catalog starts empty.

Open engineering/non-legal severity totals: Critical 0 / High 1 / Medium 2 / Low 0.
The two Medium gates are external. The unresolved production activation guard is
an engineering hold point, so engineering readiness is NO-GO until addressed.

[Launch-day runbook](../production-engineering-launch.md),
[data separation](../production-data-separation.md),
[DR](../disaster-recovery.md), [BX11 preparation](../storagebox-test-preparation.md).
Evidence screenshots and machine checks are private under
`preproduction-engineering-20261015` beside the Codex task workspace.
