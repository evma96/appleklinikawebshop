# Deficiencies

## LOCAL performance fixes — 2026-10-28

The measured availability JOIN explosion and ignored recommendation family filter are fixed locally. Six representative routes now have 64–155 ms median TTFB, with equivalent eligibility/selector/recommendation results. Search ranking/date ties now have a stable ID tie-breaker. Four unusable HEIC description references use existing JPEG derivatives. The ~1.97 MB desktop homepage image remains unchanged because its source width matches the rendered width. See `docs/qa/performance-query-fixes.md` for evidence, 1,887 assertions, cleanup and limits. Not committed or deployed.

## Physical-device TEST acceptance correction — 2026-10-27

Resolved a photo-processing defect exposed by TEST visual verification: copying
ICC metadata after stripping could replace the main HEIC Display P3 profile with
its HDR gain-map profile. ICC blocks are now retained in place. The focused native
regression compares every source ICC block byte-for-byte; no lifecycle or layout
change is involved. See `docs/qa/physical-device-foundation-test.md`.

## Current disposition — history migration and catalog decision, 2026-10-14

This section supersedes the older dated readiness counts below. **C0 / H0 / M2 / L0**
for the current non-legal scope. TEST remains usable; production is not approved.

- Public-history rewrite completed: 216 commits, 50 branches and one annotated
  checkpoint tag; a new public clone passes the 486-image scan. Only three
  historical JPEG blobs changed, with identical pixels/orientation/ICC. Active
  media cleanup is also closed: all 321 served assets match verified clean bytes.
- MEDIUM / PRIVACY: GitHub still serves the three old images by direct old-SHA
  URLs despite their absence from current branch/tag history. Owner GitHub Support
  follow-up is required for retained objects/caches; third-party copies cannot be
  recalled. This residual exposure is not falsely reported as erased.
- MEDIUM / OPERATIONS: BX11 is not provisioned. Always-on independent encrypted
  upload/round-trip restore and the owner's offline recovery-key custody remain
  open. Existing TEST backups, Mac copies and the restored Git safety package pass.
- Catalog mismatches are **not a production launch blocker**: Martin explicitly
  supersedes curation with an empty production catalog. Preserve demo products
  only on TEST; do not fix the 46 mismatches or review the other 76. Enforce the
  [empty-catalog launch gate](docs/production-empty-catalog-launch.md).
- Cash order #753 retains its technical PASS; owner Inbox/mobile confirmation is
  an evidence-only checkbox, not an application defect. No email is resent.

SHA mappings, worktree quarantine, verification and remaining actions:
[migration record](docs/qa/history-privacy-migration-20261014.md).
Legal content remains outside this task and is unchanged.

## Open Decisions

- Confirm the exact public wording and visual examples for grade explanations.
- Confirm whether accessories should become checkboxes later.
- Confirm the exact official Hungarian marketing names for all Apple colors.
- Confirm when to expand the catalog beyond iPhone, iPad, MacBook, and Apple Watch to AirPods and accessories.
- Confirm the preferred production VPS provider and deployment style later.
- Define how separate products should be grouped in production so color/storage/grade cards switch only between the intended inventory items.
- Confirm whether the current same-model grouping is enough for production, or whether a dedicated model group / SKU group meta field is needed.

## Closed during final TEST visual audit (2026-10-14)

- Active catalogue filter chips retained a 348 px desktop offset on mobile, pushing
  the controls outside a 390 px viewport. The existing <=900 px margin reset now
  includes the chips. Desktop alignment and filter behavior are unchanged.

## Known Limitations

- The publication-safety repair sanitizes 40 images from their first unpublished
  appearance, preserving pixels, orientation, ICC and HDR data. Three local-only
  commits are rebuilt; published history and the protected prelaunch tag are kept.
  See `docs/publication-safety-review.md` for the boundary and verification.

- Martin approved the small LOCAL storefront polish following checkpoint
  `e51f111` for TEST acceptance. Legal pages still explicitly contain test/sample copy,
  and the LOCAL catalogue still includes demo photos/data (including an iPhone
  gallery/color mismatch). These were intentionally preserved; replacing them is
  content preparation outside this small visual pass. See `docs/local-storefront-polish.md`.

- Homepage campaign media and copy are editable WordPress settings, not Git data.
  Split-layout hero/category tiles can use existing catalogue images until dedicated
  media are selected. Artwork slides require their own image and destination;
  disabled or incomplete rows are omitted instead of displaying fallback campaigns.
  The artwork carousel and denser homepage grid are currently LOCAL-only; TEST and
  production deployment and environment-specific media/settings migration are not
  part of this refinement. Martin's supplied service photograph is bundled unchanged and
  cropped in its presentation area to exclude embedded advertising; a chosen Media
  Library replacement is displayed normally. Multi-slide rotation now includes
  5.5-second autoplay, manual dots, swipe, pause and reduced-motion support.
  Only bottom dots remain permanently visible; there are no previous/next arrows
  or separate toolbar. Hover and dot focus do not pause rotation; selecting a dot
  resets its interval. Pause remains keyboard/assistive-technology accessible.
  Card row alignment is homepage-only; the current six-product LOCAL selection has
  been checked at 1440, 900 and 390 px without changing media sizes or fixing outer
  card height. Equal grid rows prevent small height differences in the mobile stack.
  Other product selections still need visual verification before deployment.
  The 2026-09-11 LOCAL visual review adds four supplied banners after the existing
  four and replaces only the MacBook, iPad and Apple Watch category photos. iPhone
  is unchanged. The original supplied artwork and product-photo labels are retained;
  Martin approved this complete visual state on 2026-09-11; future changes require
  a demonstrated defect or new direction. The editor retains up to eight hero slides.
  Approved public settings and all associated media are also preserved under
  `docs/checkpoints/approved-storefront-2026-09-11/` for LOCAL recovery.
  Recommended image sizes and editing instructions are in `docs/homepage-editing.md`.

- GLS 1.4.1 needs the repository's one-line recipient-address filter extension.
  Preserve `gls_shipping_for_woocommerce_api_get_delivery_address` when updating
  that vendor plugin, and rerun `make test-order-provider-mapping`. The custom
  mapping lives in the address-book plugin, not in GLS business logic. See
  `docs/order-provider-mapping.md`; successful previews are not provider issuance.

- Final checkout UX is a LOCAL presentation acceptance, not a new payment/order
  acceptance. Barion/GLS controls are exercised without submission. The definitive
  Barion + GLS + Számlázz.hu E2E remains deliberately deferred until visual approval.
  TEST configuration/data are untouched. See `docs/qa/checkout-final-ux.md`.
  The subsequent address/summary refinement also remains LOCAL-only. GLS locker/shop
  1,490 HUF, home delivery 1,990 HUF and pickup 0 HUF are local Woo settings, not
  deployable source pricing rules. Existing local free-shipping configuration is
  preserved; no equivalent TEST/production changes are implied by this feature.
  The follow-up Step 2 presentation keeps the proven address lifecycle intact.
  Saved editors expand for corrections and before an invalid-field progression
  attempt. Browser checks cover collapsed/expanded states; no order is submitted.
  The control-polish gate found no cross-account leak. Two obsolete checkout
  marketing rules in LOCAL WordPress Additional CSS were backed up and removed;
  the demo provisioner no longer generates them. A future TEST deployment must
  separately inspect/back up/remove those exact legacy rules if present: Git
  deployment alone does not update Additional CSS. Do not rerun full demo content
  provisioning merely to clean CSS. Buyback rules and legal content remain intact.

- The LOCAL visible-polish pass cleans QA product copy in local data only; this is
  not a data migration. Existing Hungarian Woo language packs provide the verified
  checkout labels. No remote translations or media were changed. The pre-existing
  LOCAL `/favicon.ico` 404 remains separate from presentation/runtime errors.
  See `docs/qa/prelaunch-visible-polish.md` for scope, backup and acceptance evidence.

- LOCAL and TEST SERVER now expose all eight clearly marked legal demo documents and
  consent surfaces. They are not launch-approved legal wording. Replace the dummy
  content and remove the demo checkout notice before production; never copy these
  demo settings/content automatically. See `docs/qa/legal-demo-ready-state.md`.
  Existing TEST SERVER WooCommerce core English labels remain a separate localization
  concern; this demo provisioning does not install translations or alter checkout logic.

- Checkout company presentation is verified locally at 1440px and 390px; release deployment
  is still separate. See `docs/qa/checkout-company-ui-polish.md`. Woo/React field ownership
  and the previously approved checkout state contract are unchanged.

- Local checkout state-contract success is not full-order acceptance. An additional-fields-only PUT/PATCH previously triggered false missing-recipient errors; the current feature branch corrects omitted-key validation without weakening final POST requirements. The browser test covers real region-control recreation and Step 2 → 3 → 4, not forced remounting of every contact input. Test-server order placement and persisted-order verification are still separate pending acceptance steps.

- Buyback Phase 2B2 now provides readiness-gated, atomically serialized HUF price-book activation, immutable active/retired configuration, typed active resolution, and safe draft duplication. Public/live customer calculation, request-to-price-book linkage, offer persistence, standalone retirement/reactivation, inspection, payout, courier, trade-in credit, WooCommerce integration, and legacy import remain deferred.
- Price-book labels intentionally remain limited to 120 bytes. Draft duplication preserves its current copy/version suffix and truncates only the source-name portion at a UTF-8 boundary when required by that limit.
- Model-specific automatic-offer minimums are intentionally model-level only. Storage- or offer-mode-specific minimum policies would require separate business approval and validation rules.
- Model-specific offer-mode adjustments intentionally support one explicit override per canonical model and offer mode. They do not add storage-specific adjustment scopes or a second pricebook-level default layer.
- The `/eladas/` calculator remains a local-only demo: service-history and affected-parts answers are intentionally not persisted until the separately scoped request-submission phase.
- The Buyback Phase 1A migration smoke test is a real Docker-backed WordPress integration check (`make test-buyback`), while the repository-wide `make test` and `make quality` commands still remain placeholders.
- WooCommerce is not installed or configured by this bootstrap alone.
- Custom product fields are editable in admin and selected values render on the product page; selector cards can switch matching same-model products without a full page reload when a complete color/storage/grade combination exists.
- Product selector controls currently group by Apple model meta, but richer grouping rules still need real inventory examples.
- Selector purchase-form lifecycle is corrected locally: direct loads and selected-product fragments share Woo's canonical renderer, including absent forms and quantity limits. Native OUT/IN repeated transitions and an isolated cart round-trip have a before-failing/after-passing regression; TEST SERVER deployment remains a separate task. See `docs/qa/product-purchase-lifecycle.md`.
- The product page currently embeds the same-model selector matrix in the page for smooth local switching; this should become a narrower query or endpoint before large production inventory.
- The single product FAQ section is intentionally not rendered until there is an editable FAQ/content source; fake product FAQs should not be added directly to the frontend.
- Single product trust block copy is centralized in `ProductFrontendDisplay::productTrustBlocks()` for this pass, but should move to an admin-backed editable source before production if policy wording changes often.
- Sparse WooCommerce products may render fewer single product information panels because description, attribute, review, and related-product sections are intentionally backed by real available product data only.
- Historical demo-profile coverage did not imply coverage of the model catalog. The 2026-10-28 LOCAL audit found 48 configured models without profiles and 45 catalog-mapped profiles with generic/reused source URLs. These gaps are now resolved in the LOCAL completeness candidate: 102/102 catalog models, 103 profiles with exact Apple archive URLs. See `docs/qa/model-specifications.md`; no source-review UI or runtime fetching was added.
- Battery replacement extra prices are development defaults until final business pricing rules exist.
- Selector demo products are local development fixtures and must not be treated as production inventory.
- The local selector demo matrix is intentionally broad and can create many WooCommerce products for one model; production inventory needs stricter grouping and stock ownership rules.
- The custom theme homepage has admin-editable non-product content and featured product IDs/count, a dynamic homepage block that avoids shortcode paragraph wrapping, and the approved shared shop product-card renderer. The optional artwork-carousel refinement remains LOCAL-only; production copy, images and destinations still need explicit review before a separate deployment.
- Category, cart, checkout, and account pages have shared compact styling, and the checkout company tax number field now masks numeric input as `12345678-1-23` while still relying on server-side validation.
- The WooCommerce My Account page now uses a custom Apple Klinika shell with Rejoy-style sidebar rhythm, final account navigation, real WooCommerce order-card rendering, CTA-only buyback, warranty/return empty states backed by real order/refund data when available, grouped account settings, saved shipping/billing address user meta, and company billing meta reuse from checkout; browser QA confirmed the logged-in shell and endpoints, while actual account-detail save submission, checkout save-to-profile behavior, and real-order/warranty visuals still need production-like data checks.
- The shop listing has a Rejoy-style filter panel, and SIM filtering now uses product meta; production products still need consistent SIM values during admin upload.
- The shop `Állapot` filter uses `_appleklinika_overall_grade`; production listings depend on admins consistently setting this grade for every used-device product.
- The shop sale-first ordering uses WooCommerce's product lookup `onsale` flag; after bulk imports or manual database changes, WooCommerce lookup tables may need regeneration for accurate sale ordering.
- The local `free_shipping#1` instance title is localized in the WooCommerce shipping-zone setting; production shipping zones and newly created method instances still need their own customer-facing title review.
- The Apple category navigation links into category-specific shop views for iPhone, iPad, MacBook, and Apple Watch. Production still needs final taxonomy rules for AirPods and accessories.
- iPad, MacBook, and Apple Watch local demo products currently reuse existing local demo image assets; production needs real category-specific product photos.
- Header links for account/cart pages should be aligned with the final WooCommerce page slugs before production.
- Legal documents require final, approved business/legal copy and explicit selection in **Settings → Jogi dokumentumok** before public links or consent controls become available.
- Product listing, cart, checkout, and account pages still need the functional UX gate audit.
- Desktop-width visual QA is still limited by the current narrow in-app browser viewport; the header/grid CSS is implemented, but full desktop screenshots should be checked on a wider browser window.
- The 4-photo workflow is guidance-only and does not enforce a minimum image count yet.
- Test, lint, formatting, and static analysis commands are placeholders.
- No CI pipeline exists yet.
- Local runtime verification still requires Docker Desktop to be installed and running on the user's machine.
- WooCommerce admin verification is in progress after fixing early translation loading.
- Device catalog color names are seeded as practical Hungarian labels with Apple English names in parentheses and still need final business review.
- Browser-level storefront checks can be blocked by coming-soon mode when the tester is not in a logged-in WordPress session.
- The global design system cleanup is a first pass; header-specific and product-selector-specific visual fixes were intentionally left for separate focused iterations.
- The cart page now renders through a custom dynamic theme layout; nonce-protected item removal and the recalculated filled/empty cart transition were browser-tested, while quantity updates, coupon submission, and checkout navigation still need a focused interaction pass.
- The Kedvencek wishlist is intentionally logged-in-only while the storefront is behind Coming Soon for logged-out users; guest wishlist storage is not implemented.

## Deferred Improvements

- Phase 3 must define immutable request/offer linkage to the resolved active price-book identity and version before any public calculator or customer offer is introduced; the Phase 2B1 admin preview remains transient, and legacy `appleklinika_buyback_records` data remains read-only until a separately approved import design exists.
- Add PHP dependency management with Composer.
- Add PHPUnit or Pest test setup.
- Add PHP_CodeSniffer rules for WordPress coding standards.
- Add PHPStan or Psalm static analysis.
- Add WP-CLI service or command workflow.
- Add production deployment documentation.
- Polish frontend product attribute display for the final theme.
- Add public grade explanation page with images.
- Add fixed option list for accessories if needed.
- Add AirPods and accessory catalog seed data.
- Replace local iPad, MacBook, and Apple Watch demo image fixtures with category-specific demo assets when available.
- Decide whether the 4-photo workflow should be enforced before publishing a product.
- Decide whether selector grouping should use only Apple model or a stricter dedicated inventory group.
- Decide how smooth selector switching should behave when a requested color/storage/grade combination is unavailable in real stock.
- Decide the final pricing logic for new aftermarket and new factory battery options.
- Decide whether local demo seed tools should be removed, hidden behind an environment flag, or kept for staging only before production launch.
- Decide later whether production should support guest wishlist storage, login-required notices, or account import after login/registration.

## Risks

- Storefront header actions now have a scoped responsive layout and LOCAL rendered/browser coverage; deployment and physical-device Safari acceptance remain separate. The legacy standalone header-actions shortcode and checkout header are intentionally unchanged.
- Product information polish is verified on LOCAL real-photo iPhone pages at 1440px/390px, including unframed gallery/viewer surfaces and grouped expandable specifications. Data-derived demo copy is deliberately restricted to localhost selector-demo SKUs; it is not a production content migration. Physical-device Safari and deployment remain separate acceptance steps. Canonical SKUs and manufacturer links stay available as secondary references; import dates are retained in storage, not displayed. See `docs/qa/product-information-presentation.md`.
- WordPress image versions must be reviewed before production deployment.
- Placeholder quality commands do not yet enforce real code quality.
- Internal IMEI handling must be designed carefully to avoid frontend exposure.
- WooCommerce data modeling must avoid product variation complexity for unique phones.
- Manual overall grade selection depends on consistent admin judgment.
- Device-type field visibility and model-dependent option filtering rely on admin JavaScript; the current local browser pass verified iPhone, iPad, MacBook, Apple Watch visibility, iPad/Watch connectivity restrictions, and Apple Watch model-specific case size/material/color pairing, but this should be included in regression testing before launch.
- Photo quality and angle consistency still depend on the admin uploader.
- The current local logo asset came from the provided Facebook image and should be replaced with final production brand artwork if a higher-resolution source exists.
- Product page trust text is first-pass business copy and should be reviewed before production.
- The product page currently styles around the active default WordPress theme; final theme work may move these styles into a dedicated theme layer.
- Automatically creating missing information pages from the theme is useful during bootstrap, but production content ownership should be reviewed before launch.
- The product page now uses a WooCommerce template override to prevent duplicate default output, but the final production theme should still receive a full WooCommerce template audit before launch.
- Footer information pages now exist with realistic placeholder content, but ÁSZF, privacy, shipping, warranty, contact, and returns copy still requires final business/legal approval before launch.
- Footer information page trust-block copy is intentionally simple bootstrap text and should be reviewed against final shipping, warranty, and returns policies before production.
- Contact directions now offer Google Maps, Apple Maps and Waze on mobile, using HTTPS app/web links; desktop keeps Google Maps. The exact store pin avoids Apple's incorrect `2-4` → `24` address interpretation. Postcode and navigation coordinates are editable with the Contact content. Physical-device installed-app handoff remains unverified. Martin's latest instruction replaces the earlier two-click map requirement: the existing Google iframe now loads immediately with the Contact page. Manual-load controls and copy are removed; no new consent framework or reuse of the unrelated marketing flag was introduced. LOCAL verification is in `docs/contact-editing.md`.
- LOCAL Contact now uses the owner-provided Szeged, Jósika utca 2–4. address and the +36 30 970 6700 phone from the existing owner-supplied service artwork. At Martin's request, the map now uses the standard Google Maps share/embed iframe, loaded immediately without an API key; directions also use Google Maps. Martin previously confirmed the Google map in his normal browser while the Codex preview was blank. The 2026-09-11 immediate-loading pass also visually verified the real Google map and store pin in the Codex in-app browser at desktop/mobile sizes; the earlier blank-iframe limitation did not recur. Public email and opening hours remain hidden until confirmed. Real mail delivery is not verified: automated checks use mail stubs, and the form now reports a transport failure instead of always claiming success. Settings and LOCAL-only verification are in `docs/contact-editing.md`. This work has not been merged or deployed.
- The custom cart layout depends on WooCommerce cart hooks and form handling staying compatible with future WooCommerce updates, so it should be regression-tested after WooCommerce upgrades.
- The company purchase checkout fields now include required-state handling, Hungarian tax number format validation, immutable order display, safe local e-mail rendering, and a local runtime contract covering keyboard/fill input, Woo store state, Store API responses, and three React field rebuilds. The same state contract still requires one TEST SERVER acceptance run before release; final invoice plugin integration and any official invoice-field mapping remain separate work.
- Checkout address detail fields for house number, floor, staircase, and door are saved as WooCommerce Blocks address meta and shown once through formatted order addresses. Final invoice and shipping-label field mapping still needs a separate plugin-specific audit.
- The checkout multi-step shell deliberately hides non-active WooCommerce Blocks form sections without moving or unmounting them, while keeping the live order summary visible; it still needs a full regression pass after GLS shipping and Barion payment plugins are installed because those plugins may add their own fields inside the shipping and payment sections.
- Product gallery multi-image behavior has a LOCAL browser regression using the real PHP renderer with unsaved single/multi-image products and existing media (no database product/gallery edits). LOCAL iPhones now have real three-photo QA sets; their 1440px/390px framing correction was visually reviewed, with one redundant wrapper removed and a single dark viewer backdrop. The gallery gate passes 237 checks, including native touch taps, pointer swipe/pan and viewport changes. Target deployment and physical mobile Safari remain unverified. See `docs/qa/product-gallery-ux.md`.
- The remaining listing-card grey image panel is removed from both original theme rule scopes (nine CSS declarations deleted, no new overrides). LOCAL 1440px/390px screenshot review confirms the panel is gone and all 16 visible card boxes per viewport retain their exact geometry. Existing image assignments and Woo thumbnail derivatives are unchanged; deployment and physical-device Safari remain separate checks. See `docs/qa/product-information-presentation.md`.
- The local iPhone transparent original and its existing responsive derivatives have inconsistent orientation; stored attachment dimensions also differ from the decoded original. The viewer now uses decoded dimensions, but does not regenerate derivatives, crop transparent padding or retouch uploads. Photo consistency remains a media preparation task. The existing LOCAL `/favicon.ico` 404 also remains outside gallery scope.
- The local image normalization pipeline has only been approved for the first iPhone phone-portrait output. MacBook, iPad, and Apple Watch profiles exist in the script but must not be batch-run until each family has a reviewed source/preview pass.
- Only the tested iPhone 13 Pro product currently uses `_ak_single_product_gallery_image_id`; future products need an admin workflow or import step so portrait/detail images are assigned separately from archive featured images.

## Checkout address initialization acceptance

- LOCAL source tracing found no cross-customer address initialization leak. Woo
  profile metadata, current-session restoration and the custom address book are
  separate valid sources. Fresh guests and empty profiles have empty personal
  addresses; store country/region defaults are not stale customer data.
- The actual defect was a sidebar-only fallback: an empty independent billing
  address appeared as shipping. The fallback is removed, without clearing inputs,
  changing API/state logic or replacing native controls. Address choices now follow
  their native headings; empty lists explain manual entry and mobile short fields
  keep the existing two-column grid. See `docs/qa/checkout-final-ux.md`.
- Integrated payment/parcel/invoice acceptance is still deferred. These LOCAL
  tests block order submission and stub the external GLS locator only; they do not
  prove real payment or parcel-service calls, browser password-manager autofill,
  or a TEST SERVER rollout of this follow-up.

## Next Iteration Questions

- Which fields should be visible on the frontend product page?
- What validation rules are required for battery health, warranty duration, and IMEI?
- Should grade explanations be managed as editable WordPress content or hardcoded design content?
- Should the next step be real PHP test tooling or better admin input fields?
- Which Apple product family should be added after iPad, MacBook, and Apple Watch: AirPods, accessories, or another device line?
- Should production catalog deletion become archive-only after real products depend on catalog values?
- Who owns the final legal text for ÁSZF, privacy, warranty, shipping, and returns pages?
# 2026-09-16 lifecycle implementation follow-up

- **HIGH / CONFIG + TEST access:** The actual TEST invoice plugin still displays
  its separate PRO activation gate; TEST SSH times out. Számlázz.hu TEST itself
  provides #profi capability. Existing plugin entitlement must be activated or
  clarified; no live subscription change is implied. The new lifecycle is local
  only, and the requested real provider acceptance remains blocked.
- **APPLICATION, locally addressed / pending TEST:** Current-array GLS tracking,
  invoice-ready primary mail, one handoff-only shipping mail, durable duplicate
  guards and shared Back Office/Woo-admin transitions are implemented and locally
  exercised. They must not be described as a completed TEST fix yet.
- **SMTP / delivery evidence outstanding:** Application acceptance and actual
  recipient inbox delivery remain separate. No transport provider was installed.
- Known legal/sample content and the previously resolved storefront media-access
  incident retain their existing classification. This phase does not reopen them.
- Detailed scope, evidence, source ownership and TEST rollout prerequisites:
  [Order/email lifecycle](docs/order-email-lifecycle.md).

## LOCAL recovery — 2026-09-28

- Deleted temporary-worktree mounts were replaced by the durable storefront source documented in `docs/local-development.md`; existing LOCAL database/core volumes and all uploads were preserved. The two implementation checkpoints and their five differing shared files remain unreconciled.
- At the initial restoration checkpoint, Contact map iframe output was unchanged and immediate, but the in-app browser map surface remained blank; authenticated Back Office work-queue verification awaited a separate LOCAL login. Final recovery verification is a separate follow-up to these initial observations. Neither observation is a deployment or provider integration result.

## LOCAL reconciliation — 2026-09-28

- The five source conflicts described in the historical recovery record are now
  reconciled in the separate integration candidate; neither protected branch was
  modified. See `docs/local-reconciliation.md` for the per-file decisions.
- Resolved application defect found during LOCAL verification: the Számlázz.hu
  order metabox passes a status array through `wc_szamlazz_should_generate_auto_invoice`,
  whereas the custom lifecycle hook previously accepted only a boolean. This
  caused a Woo-admin order-page TypeError. The hook now preserves the provider's
  value/type for unmanaged or owned attempts and still blocks unowned managed
  attempts. Sixteen focused offline assertions cover the contract; the order
  admin page renders again. No license check or provider guard was bypassed.
- The previous LOCAL Woo-admin correction verification blocker was resolved on
  2026-09-29 with explicit owner authorization for the temporary QA user's
  `edit_others_posts` capability. No Administrator role or application permission
  bypass was needed. Correction, shared Back Office/My Account state, actor/reason
  audit and duplicate-submit rejection passed; the QA account/order were removed.
  See `docs/local-reconciliation.md` for cleanup and unchanged-data proof.
- LOCAL mail/provider transports are deliberately disabled. This reconciliation
  provides no new TEST provider E2E or actual inbox-delivery proof. Existing
  legal/sample content, catalog imperfections and provider/license/SMTP acceptance
  remain outside this task. The prior corrected deployment/media issue remains
  recorded in the earlier audit notes and was not reopened.


## Transactional email readiness — initial audit, 2026-10-01

- **HIGH / CONFIG — authenticated transport not ready:** Read-only TEST inspection
  found no SMTP plugin/configuration or active mail override. PHP points to a
  missing sendmail executable. Woo uses a Gmail From address, not an authenticated
  Apple Klinika domain sender. No email was sent and no transport was changed.
- Public DNS has one hosting-oriented SPF record and a 2048-bit RSA DKIM key at
  `default._domainkey`. DMARC exists with `p=none` and no aggregate-report address.
  DNS presence does not prove outgoing signing/alignment or inbox delivery.
  Current DNS indicates Tárhely.com mail hosting; usable SMTP entitlement and
  limits require owner/provider confirmation. No DNS records were modified.
- **LOCAL presentation verified / delivery pending:** The two lifecycle messages
  have branded scoped HTML/plain templates and five memory-only Woo previews.
  Desktop, 390px and 320px checks found no horizontal overflow. Invoice, actual
  carrier-handoff eligibility, correction/history and duplicate guards are
  unchanged. No real invoice, parcel or email was created in this pass.
- TEST SSH is restored through the private alias. The historical timeout is no
  longer the access blocker; Martin separately handles Woo PRO activation. The
  approved integration checkpoint is preserved and the lifecycle remains
  undeployed. Full provider acceptance and native email-client/inbox checks are
  still pending, as are the previously documented legal/content blockers.
- Plan and evidence: [transactional email readiness](docs/transactional-email-readiness.md).


## Mail provider correction — 2026-10-01

- Martin confirmed existing **Hetzner Webhosting** mailboxes. The earlier
  Tárhely.com provider inference from public DNS is superseded for SMTP setup.
  Do not use mHosting/Websupport. Keep the dated DNS observations; they do not
  establish the actual mailbox account or prove outbound authentication failure.
- TEST can reach `mail.your-server.de:587` with verified STARTTLS/TLS 1.3 and
  advertised SMTP authentication. Actual mailbox selection, authentication and
  QA inbox delivery remain pending a Hetzner account session and the authorized
  mailbox credential. No password reset, new mailbox, message or DNS change was
  performed. Details: `docs/transactional-email-readiness.md`.

- Authenticated Hetzner inspection resolved a domain mismatch: this account holds
  `appleklinika.hu` with active `info`, plus `szamlazas` and `webmaster`; no .com
  domain or requested order-mailbox aliases were listed. Martin approved the
  existing `info@appleklinika.hu` as From and Reply-To for QA only. The current
  blocker is secure entry of its existing mailbox password. No authentication,
  send, password reset, mailbox creation or DNS modification has occurred.


## TEST SMTP progress — 2026-10-01

- **Previous authenticated-transport blocker resolved on TEST:** official
  FluentSMTP 2.4.1 uses the existing Hetzner mailbox over authenticated STARTTLS
  on port 587. Approved QA From/Reply-To: `info@appleklinika.hu`, name
  `Apple Klinika`. The mailbox password was entered by Martin in the masked
  HTTPS UI and stored with plugin-supported encryption; never exported to
  plaintext or Git. The future production .com sender remains unestablished.
- **TEST email acceptance complete — PASS:** application generation, authenticated
  SMTP, actual Gmail Inbox delivery, SPF, DKIM, DMARC, mobile rendering and the
  received PDF are verified. Martin confirmed all three messages in the normal
  Inbox (none in Spam), correct mobile layout/typography/buttons/spacing/Hungarian
  text and successful PDF opening. His supplied Gmail headers show authentication
  PASS on all three with aligned `.hu` sender/envelope/DKIM (`default2607`). No DNS
  correction is indicated for this QA identity. The earlier pasted-source PDF
  omission was an evidence limitation, now resolved by manual verification.
  No further email-design changes are required; templates remain unchanged.
  This closes the TEST transport/customer-email verification only, not the
  separate production-sender or invoice-dependent provider lifecycle work.
- **Safety/persistence PASS:** 18 focused checks passed after TEST WordPress
  restart; non-QA recipients/CC/BCC, ordinary web/cron sends, missing transport
  and fallback are blocked. Existing failed/refund/account/password mail retains
  its standard transport path. HTTP health passed; temporary QA user/session and
  access helper were cleaned up. No customer orders, invoices or parcels were
  created, and the accepted email source/templates were not changed or deployed.
- No DNS, mailbox, production, main, Barion/GLS/Szamlazz lifecycle or license
  changes. Exact scope and historical blockers remain in
  `docs/transactional-email-readiness.md`.

## Security remediation — Round 1 (2026-10-05)

See [the scoped repair record](docs/security-remediation-round1.md). The missing
WordPress release, current tax/GLS authorization gaps, stale QA #525, invoice
filesystem permissions and upload execution/core write containment are addressed.
Current old demo images are sanitized; their already-published history remains.
After source rollout verification the audit remainder is Critical 0 / High 0 /
Medium 3 / Low 5. Medium: historical image exposure, recovery rehearsal/automation,
and monitoring. All five low findings remain scoped follow-up work. The separate
legal-content and PRO/full invoice-lifecycle gates remain; no launch sign-off is
implied. No restore rehearsal, monitoring or header work was started.

## Security / operations Round 2 — 2026-10-05

- Closed M7 for TEST: actual isolated restore passed (17.9 minutes); database,
  1,072 media files, private configuration, orders/history and Back Office were
  verified. Daily encrypted routine and verified Mac copies are implemented.
  Independent RPO remains conditional on Mac availability; approve an always-on
  destination, key custody and production schedule before production activation.
- M8 partially addressed: five-minute disk/container/HTTPS/PHP/SMTP/backup/TLS
  checks, Mac public checks and Docker log rotation are active. Actual unattended
  human notification delivery is still missing; do not label it complete.
- M6 remains: old image blobs are downloadable in 184 public commits and 50 ref
  tips, including main/protected tag. GPS is not the Szeged business location.
  No public history was rewritten; provenance and explicit risk/rewrite decision
  are required. Exact coordinates/serials are not included in documentation.
- Remaining audit counts: C0 / H0 / M2 / L5. Low findings, sample legal content and
  Woo Szamlazz PRO/full lifecycle acceptance were not remediated in this round.
- See `docs/security-operations-round2.md` for scope, evidence, limitations,
  maintenance behavior and the documented setup errors corrected during rollout.

## TEST lifecycle acceptance — 2026-10-06

- Found and fixed: dedicated Back Office URL rewriting disabled TEST lifecycle hooks and could point customer email account links at the staff host. Canonical installation checks and customer-only URL normalization preserve the existing staff login/cookie boundary. Focused regression and deployed acceptance results: `docs/test-lifecycle-acceptance-20261006.md`.
- Existing legal/sample content and previously documented operations/public-history findings remain separate launch gates; this change does not resolve them.

## TEST GLS label persistence regression — 2026-10-07

The E2E found a successful GLS sandbox parcel whose PDF was not saved: WordPress selected an FTP code-update transport on the immutable code mount. The single-order label adapter now writes only the protected runtime label directory through the native direct filesystem adapter (0640), reports write failures and preserves provider IDs before saving the PDF. Any existing label/tracking/parcel reference rejects a repeated single-order creation. Recovered-label and remaining lifecycle acceptance are recorded separately; no full PASS is claimed here. Bulk generation and generic ambiguous network-timeout recovery are outside this targeted repair.

## Checkout acknowledgement candidate — 2026-10-08

- LOCAL candidate separates automatic receipt from paid/invoiced order acceptance;
  TEST three-message delivery is pending. See `docs/order-acknowledgement.md`.
- Fixed in the candidate: BACS status callbacks can carry stale order objects and
  miss a freshly persisted acknowledgement marker. Suppression now reloads the
  order; a real Woo fixture proves one customer acknowledgement and retained bank
  instructions. No provider/SMTP behavior was changed.
- Cash/COD is disabled; its eventual contractual acceptance trigger remains a
  business/legal decision. It was not silently enabled or given a new rule.
- Current TEST legal pages are still sample documents. Exact comparison against
  current VirtualJog wording requires that document; no authoritative copy is invented.

## 2026-10-09 cash/personal-pickup candidate

The previous missing operational acceptance decision is now supplied by the owner: explicit staff stock verification/acceptance, then cash payment at actual pickup. The LOCAL candidate implements this using shared state/history and existing invoice/notification idempotency. Real TEST provider verification remains a deployment gate; no overall non-legal launch PASS is claimed. VirtualJog wording is untouched. See `docs/cash-personal-pickup.md`.

The cash TEST run generated `E-APP-2026-4` once but exposed a missing customer invoice link. The scoped correction adds an owner/nonce-protected cash-pickup download; public upload protection is retained. Provider generation and email behavior are unchanged.

## MPL prelaunch gates (2026-10-09)
- Posta webshop registration and Sandbox API/agreement credentials are not yet present; supported selector PRO/test licence is additionally needed for automatic labels. No provider result is claimed.
- All 122 published TEST products lack weight. Correct MPL availability requires approved packaged product weights/dimensions; QA fixtures do not repair catalog data.
- Shared MPL state/email behavior and current editable tariff bands are implemented separately from these external/configuration gates; see `docs/mpl-checkout-fulfilment.md`.

- MPL initial TEST compatibility finding: the installed rate did not render its selector until the maintained vendor block and native Woo pickup feature were mounted. The focused checkout follow-up addresses this; provider credentials and real catalog packing data remain separate external/data blockers.

MPL 4.2.8 map compatibility: the upstream markercluster bundle reads mutable global `L`, allowing another map widget to break initialization. A checksum-guarded, one-line Browserify module binding (`scripts/mpl-selector-compat.py`) uses the same bundled Leaflet instance. No provider, licensing, GLS or legal behavior is changed. Re-review this patch when updating the pinned dependency.

## TEST human monitoring follow-up

Independent GitHub availability and standalone authenticated internal SMTP alerting are implemented in `docs/test-monitoring.md`. Actual workflow execution and controlled failure/recovery delivery must be verified separately; SMTP acceptance alone does not prove Inbox receipt. GitHub schedules are best-effort and require repository/issue notification subscription for human notification.

External monitor follow-up: GitHub issue-list eventual consistency during the first rapid drill produced duplicate surfaces. The monitor now reads once per run and carries mutation responses through the sequence, with an offline stale-list regression check. Exact-marker duplicates are closed while the oldest incident is retained.

The owner confirmed receipt of both operations QA messages. Inbox display/arrival ordering is clarified separately; alerts include explicit RFC Date, unique Message-ID and UTC event time. These presentation headers do not change incident deduplication.

## Production backup readiness gates

The reusable encrypted backup candidate and Storage Box transfer adapter are disabled. Actual always-on independent storage, restricted credentials/host pin, remote retention/snapshots and off-Mac recovery-key custody need owner provisioning/approval. A real remote transfer and isolated restore are required before production backup acceptance. Existing proven TEST backups remain active and unchanged.

## Conservative LOW follow-up

LOCAL private-config permissions, unused XML-RPC, trivial public user enumeration and compatible TEST headers are addressed in the scoped candidate. Obsolete inactive defaults are removed while active integrations and a fallback theme remain. Legacy cloud SSH /32 removal still needs the expired Hetzner session restored; WireGuard is healthy. See `docs/security-low-followup.md`.

## Final non-legal closure — manual MPL scope correction
The previous fail-closed weight requirement hid MPL for the real catalog. The owner now authorizes manual dispatch at fixed customer charges (point HUF 1,040; home HUF 2,090), absorbing any carrier-cost difference. The targeted correction permits unknown measurements in manual mode while retaining known service/value limits, editable fees and the supported point selector. No product weight is fabricated, no API project or licence bypass is introduced. Earlier missing advanced MPL automation is no longer classified as a High launch finding by itself.

## Targeted checkout follow-up — 2026-10-14

Generic free courier shipping is removed through the TEST Woo zone setting. The pickup missing-address label and Contact/navigation postcode mismatch are fixed without order/provider changes. The owner subsequently removed bank transfer from the business model: BACS is disabled on TEST, customer transfer copy is cleared, and the missing-bank-data HIGH is resolved. Cash order #753 still needs only the owner’s Inbox/mobile confirmation; do not resend. Independent backup/key custody, historical public-image metadata and demo catalogue content remain open; legal documents are untouched. See `docs/qa/checkout-configuration-closure.md`.

BACS removal verification originally left C0 / H0 / M2 / L2; the subsequent owner-confirmed store-address correction closes the address LOW, leaving C0 / H0 / M2 / L1. The existing two MEDIUM findings (independent backup/key custody and historical public image metadata) remain. LOW: known catalogue mismatches. Store-address metadata, three banner graphics and LOCAL defaults now use the owner-confirmed 6722 Szeged, Jósika utca 4.; the earlier BACS task itself did not alter the address. NON-LEGAL CONDITIONAL GO describes the tested checkout, not unconditional production readiness.

Store-address follow-up: no checkout/order lifecycle operation or delivered email was needed. Read-only rendering confirms the pickup-email address. Historical dated snapshots retain their original text as evidence; use the canonical-address override when restoring them. See `docs/qa/store-address-consistency.md`.


## Final non-legal readiness follow-up — 2026-10-14

The canonical-address LOCAL checkpoint is protected, and BACS remains intentionally disabled. Backup target provisioning and offline key custody require the owner steps; no service is purchased. The historical three-image privacy rewrite is recommendation B, not executed. A fresh public-asset check found 12 active iPhone gallery uploads with private capture metadata, correcting the earlier overbroad clean-assets statement. Catalogue review is broad (122 entries / 16 image groups), so no stock or imagery is guessed. Current C0 / H0 / M3 / L1, conditional TEST readiness only. See [final factual record](docs/qa/final-nonlegal-readiness-20261014.md).

## LOCAL selector cleanup — 2026-10-15

The product selector battery-choice presentation and uneven desktop card heights are addressed locally. No catalog, cart pricing hooks, checkout/provider behavior, TEST or production configuration changes. This UI candidate was uncommitted at that review; it is now protected and deployed as recorded in the engineering section below.


## Pre-production engineering — 2026-10-15

The approved battery-free product selector is checkpointed and integrated. The isolated
empty-catalog rehearsal found and fixed version-inappropriate Buyback migration
validation and clipped mobile Back Office empty-state text. TEST robots/visibility
are aligned with its existing noindex response header. No production, main,
legal text or provider transaction is changed.

See [engineering evidence](docs/qa/preproduction-engineering-20261015.md),
[launch runbook](docs/production-engineering-launch.md), and
[current baseline](docs/current-operational-baseline.md). A **HIGH engineering gate**
remains: lifecycle/fulfilment activation deliberately rejects production, so copying
TEST credentials/options is not a production rollout. GitHub old-object purge and
independent backup/key custody remain external gates. Demo catalog mismatches are
not a launch blocker.


## Production activation engineering — 2026-10-16

The previous HIGH production-lifecycle engineering gap is addressed by an explicit,
default-OFF private activation manifest, mandatory MU safety bootstrap, per-capability
readiness report, provider transport gates and signed empty-launch evidence. Existing
TEST enrolment and provider behavior remain separate. A real Woo/HPOS offline fixture
exercises the native Barion callback handler, three-email flow, invoice/GLS adapters,
stock/idempotency, staff correction and cash-at-pickup with fake provider boundaries.
No production account or provider is activated. Legal documents remain untouched.

See [activation/guard map](docs/production-lifecycle-activation.md),
[engineering acceptance](docs/qa/production-activation-engineering-20261016.md), and
[disabled manifest example](docs/examples/production-activation.example.json).
GitHub retained old-object removal and independent backup/key custody remain external
Medium prerequisites; legal/business acceptance and eventual human production
activation remain separate. The earlier 2026-10-15 HIGH entry is historical.

2026-10-16 closeout: 750 offline/regression checks pass; exact application release
`40d590ce90359cfe8be8b60abeb0e64c912ae15f` is verified on TEST with unchanged provider
configuration and production OFF. Engineering C0/H0/M0/L0; combined non-legal
C0/H0/M2/L0 (external backup/key custody and retained-public-object removal).
Production activation is still a future explicit owner-authorized procedure.

## Final VirtualJog terms and privacy PDFs — 2026-10-16

The two authoritative v2 binaries replace active terms/privacy sample content without
legal editing. Existing page IDs remain, `/aszf/` is canonical, and privacy retains its
native route. Legacy #36/#37 also show the final documents; history is preserved.
The other six sample legal document types are outside this two-document installation
and must not be mistaken for finalized text. Embedded PDF preview is unavailable in
Codex; original/downloading hashes and independent PDF rendering provide separate proof.
See [exact files, scope and verification](docs/legal-documents-final.md).

## GLS packing-completion automation — 2026-10-16

The owner-approved trigger is implemented: verified payment plus completed packing
creates one GLS label; payment/invoice alone do not. Known rejection can be retried;
unknown outcomes require provider reconciliation. Label creation never represents
carrier handoff or sends shipping mail. Cash/pickup and MPL are unchanged.
The legacy bulk-print generation bypass is disabled; existing PDF downloads remain.
See [verification and operational limits](docs/gls-label-after-packing.md).

2026-10-17: deployment preflight detected concurrent approved Back Office changes
outside TEST Git. The release now preserves those document/queue/recovery changes and
uses the existing packing-event adapter and attempt metadata. The earlier candidate
was not installed over them. See the GLS integration document for scope and evidence.

## LOCAL Google Reviews connection — 2026-10-19

**OPEN — live Google authorization:** no usable owner-authorized Business Profile API/OAuth connection or Places key/Place ID is available in the accessible LOCAL configuration. No genuine review, live score or total has been retrieved. This is a connection blocker, not an empty-card implementation: actual LOCAL homepage, category and product pages now render the owner-authorized, clearly labeled QA cards. The obsolete link-only fallback is removed. The fixture is ignored, outside webroot, uncacheable and guarded against TEST/production display. The official live adapter and UI are covered by focused mocked/local checks. [Evidence and exact owner setup](docs/google-reviews-local.md). Approved TEST deployment must keep reviews hidden until real Google data is configured; the provider strip remains independent.


**2026-10-26 visual review pending:** the compact product/category and larger homepage review composition now replace the rejected yellow-banner presentation. A small LOCAL badge and synthetic identities preserve disclosure, with all fixture protection unchanged. This presentation-only revision is not committed or deployed; the live Google connection remains the same open item.

### 2026-10-26 — LOCAL trust / Watch / protection candidate

- Watch `32_gb` display leakage is corrected in the two customer-facing formatters;
  stored metadata is unchanged. The homepage uses the four owner-supplied claims verbatim.
- Optional protection uses native hidden Woo products and separate normal cart/order
  lines. LOCAL checks cover quantity/removal, server validation, stock failure rollback,
  Woo price edits, account/email/Back Office rendering and the installed invoice
  plugin's product-line mapping without invoking its invoice/transport entrypoint.
- SAFE PRE-LAUNCH implementation candidate, pending Martin's LOCAL visual approval.
  No integration, push, TEST access or deployment was performed.
- 20 W adapter price remains unset/unavailable. LOCAL compatibility is explicitly
  configured for iPhone; actual stock/model coverage must be configured in Woo before
  later activation. No model compatibility table was invented. Other device types
  currently have no protection options. New accessory photos were not supplied;
  native cart placeholders remain until images are configured in Woo.
- Provider/lifecycle E2E and real invoices/emails were intentionally not run. Existing
  payment, invoice, stock, GLS and customer-notification lifecycle source is unchanged.
  The active LOCAL base is `eecad886bcdcb7b6187af715bc2ff18371167f44`; this work does
  not reconcile or replace the newer develop/TEST history.
- Details and evidence: `docs/local-trust-watch-protection.md`.

### 2026-10-26 — Protection parent association closed, LOCAL only

- RESOLVED: identical accessory products could previously merge across different
  devices. Native parent cart keys now distinguish lines and persist on Woo order
  items, with a public device-name snapshot. No parallel order database.
- Device removal cascades only to its own extras. Session restoration and accessory
  undo cannot introduce an orphan; checkout item creation also rejects missing parents.
- Native quantities, stock aggregation and failure rollback remain in use. Replaying
  a consumed add handler adds nothing; a deliberate new purchase increments only the
  matching device/accessory group using normal Woo quantities.
- Existing invoice notes include the association without invoice/plugin changes.
  LOCAL direct verification passed; status: APPROVED FOR INTEGRATION. This status
  does not authorize a push/deploy. Adapter remains unpriced/unavailable.

### 2026-10-26 — Approved protection release preparation

The owner authorized normal checkpoint/integration/push and TEST deployment of the
complete trust/Watch/protection package. Explicit guarded provisioning resolves the
existing `ak-protection-*` SKU identities independently in each environment, preserves
existing admin prices, and stops on conflicting/duplicate identities. Adapter price
remains a business input; production/main and provider transactions stay excluded.

## 2026-10-26 — TEST Back Office source drift reconciliation

The already accepted TEST staff-review runtime package was missing from Git. Its exact source is now preserved with the approved protection presentation additions. Shipping correction remains recoverable rather than cross-store atomic; incomplete writes block ordinary progression until the existing explicit reconciliation completes. No payment, invoice or carrier E2E is repeated by this source-preservation task. TEST acceptance of the combined release is recorded separately after deployment.


### 2026-10-26 — Extra protection visual approval pending

The cramped purchase-card UI has been recomposed locally with full-width clickable
rows, aligned Woo prices and an understated unavailable adapter. Functional checks:
153 PASS; actual product pages inspected at 1440/1280/390/320. No paid extra is
preselected; selection, association, removal and downstream lifecycle remain
unchanged. Martin's screenshot-based visual approval is still required. No commit,
push, TEST access or deployment. Previous Google Reviews work is untouched.


### 2026-10-26 — LOCAL product hero composition review

The gallery's large desktop gap is resolved by moving the two existing configuration
cards beneath it, with purchase and guarantees aligned in the other column. No
filler or gallery/protection redesign. Six full-page widths were visually reviewed;
299 focused assertions and browser selector/gallery/cart checks pass. Martin's
visual acceptance is pending. LOCAL only; no commit, push or deployment.
Details: `docs/qa/product-information-presentation.md`.

### 2026-10-26 — LOCAL product lower-grid alignment

Resolved the lower description/data and similar-products width mismatch by sharing
the approved hero's CSS column definitions and desktop gap. Six full-page viewport
checks pass; upper geometry and mobile stacking remain unchanged. No functional
changes. LOCAL only, uncommitted; details in `docs/qa/product-information-presentation.md`.

### 2026-10-26 — LOCAL valid-model selectors and Watch storage visibility

Sparse iPad/Mac color and storage controls no longer stretch; known valid model
options remain visible disabled when unavailable. Watch omits the entire storage
selector panel. Approved iPhone layout retained. 169 focused checks and 16 browser
viewport inspections pass, LOCAL only and uncommitted.

Data limitation: six iPad models lack an explicit capacity list in existing
model/spec data (Pro 11/13 M5, A16, Air 11/13 M4, mini A17 Pro). Only known
same-model product capacities are shown until verified model data is available.
Exact keys/IDs and evidence: `docs/qa/product-information-presentation.md`.


## LOCAL manual product entry / HEIC recovery — 2026-10-26

Resolved locally: product 2513 was mistakenly classified as an Extra védelem
case and therefore hidden; its main image/category were unassigned. Corrected
only its normal Woo fields. The original LOCAL HEIC decoder failed on all four
uploaded iPhone photos; an opt-in local image with Debian's compatible decoder
now lets native WordPress generate web images using the same attachment IDs.
Original images preserved. Fixed string/int image-ID deduplication in the gallery
and added focused coverage. No index/cache rebuild or lifecycle change.

Stock quantity tracking remains disabled as entered, without inventing a count.
TEST/production HEIC support has not been checked or changed by this LOCAL task.
See [full diagnosis and normal entry workflow](docs/qa/manual-product-entry-local.md).


## 2026-10-26 — Physical-device foundation (LOCAL only)

- Implemented a shared native Woo device-entry service, one-unit defaults, automatic/custom naming, unique native SKU, separate private identities and immutable order-item snapshots.
- Added native ordered photos, HEIC/HEIF processing and private metadata stripping with an explicit runtime prerequisite; no media/inventory parallel database.
- Prevented device/accessory conflicts and cross-device photo copying. Existing legacy #2513 remains a flagged classification conflict; its legitimate identity data is not invented or silently migrated.
- Verified stock zero exclusion and native restock, supported offline invoice identity/note mapping, and regression compatibility. See [evidence, counts and file manifest](docs/qa/physical-device-foundation-local.md).
- **COD deferred until after launch:** native GLS supports a COD amount, but the accepted lifecycle requires verified payment for invoice/fulfilment/label. Do not enable the gateway without a separate approved lifecycle design.
- No commit, push, TEST access or deployment in this task. Future integration must include verification of the compatible image-processing runtime on its target environment.

## 2026-10-27 — Physical-device integration acceptance

- Owner approved integration of the foundation and current LOCAL visuals.
- LOCAL #2513 classification conflict corrected using Woo APIs; existing price, stock settings and physical configuration/photos preserved, no invented identity.
- TEST ExifTool absence addressed through the versioned TEST media Dockerfile/Compose overlay, not an undocumented live-container installation. Execution evidence follows in the release receipt.
- COD remains deferred until after launch; it is not a blocker for the current approved payment model.


## 2026-10-27 — LOCAL compact storage and summary removal

Resolved locally: redundant selected-device summary/link/divider removed; storage
controls no longer stretch. Watch storage remains absent. Existing product data,
selector matching/prices and other product-page components are unchanged.
236 focused PHP assertions and 16 family/viewport visual checks pass; native
128→256→128 GB selection preserves the matching device and price. No overflow or
console error observed. LOCAL only, uncommitted and not deployed. Evidence and
limits: `docs/qa/product-information-presentation.md`.


## 2026-10-28 — LOCAL stock presentation / adaptive selectors

- Removed public available-stock badge/count and the add-to-cart stock footer.
  Native stock fields, purchase limits, unavailable/backorder state and admin
  rendering remain unchanged. No new availability copy replaces the count.
- Existing option counts determine equal-width rows, with compact fixed storage
  height and separate readable condition widths. Watch storage remains absent.
- 284 focused PHP assertions PASS; 16 family/viewport visual checks PASS, including
  disabled valid options, no overflow and no stock-count copy. Native selection
  changes preserve the correct product/title/price. No data or provider actions.
- LOCAL only, uncommitted. No TEST, push or deployment. Detailed rules/evidence:
  `docs/qa/product-information-presentation.md`.


## 2026-10-28 — LOCAL missing condition choices corrected

Sparse inventory previously limited condition buttons to grades found on physical
products. The frontend now includes all four existing domain grades (A+/A/B/C),
with missing grades disabled and no fabricated price. The shared admin/domain
definitions, selector matching and adaptive CSS are unchanged. 236 focused PHP
checks and 16 family/viewport inspections pass. LOCAL only; no saved product/QA
data, commit, push or deployment.


### Resolved locally: product-page quantity and uneven column spacing (2026-10-28)

- Removed the visible purchase quantity control without changing native Woo quantity/stock behavior; the add-to-cart button is full width.
- Removed the shared desktop row-height dependency that left an oversized gap before iPhone product details. Both columns now stack their own cards with 24px gaps; the existing mobile order remains intact.
- Replaced the four-item, category-dependent recommendation fallback with a read-only same-family query capped at six distinct active devices. Sparse/new model pages can use canonical family/model metadata without altering the selector's matching pool.
- LOCAL verification: 477 focused checks passed (259 PHP, 192 viewport measurements, 26 interactive selector/cart checks), plus personal review of all four families at 1440/1280/390/320. No console/PHP errors. Each QA cart item was removed; no order or saved fixture was created. Not committed or deployed.

## 2026-10-28 — LOCAL model specification binding

Resolved: manual iPhone 12 Pro lacked a canonical profile; device entry did not resolve/report profile completeness. The shared service now binds selected models automatically, handles exact aliases and prevents stale cross-model fallback. The correct iPhone 12 Pro profile is added centrally from Apple 111875; no original product is edited. Existing 54 profiles and 123 resolved row sets are preserved.

Data-completeness follow-up resolved: 48 missing profiles added from official Apple sources; 51 existing profiles revised (8 technical corrections, 50 archive URL updates, overlapping). Current 102-model catalog is fully covered; 103 stored profiles include the preserved historical `macbook_pro_16_m3` orphan. Missing profiles / exact URLs: 0 / 0. Guarded provisioning avoids overwriting unknown manual edits. 762 focused assertions and 12 desktop/mobile page checks PASS; all 169 original product objects unchanged and QA artifacts removed. LOCAL only; not committed or deployed. See `docs/qa/model-specifications.md`.

LOCAL only, no commit/push/deployment. The pre-existing selector and product-layout changes remain intact.


## 2026-10-28 — Smart selector resolved in isolated LOCAL candidate

Previously a valid option available only with another color/condition/storage or technical configuration was disabled, and identical configurations could silently select an arbitrary physical product. The candidate shows explicit alternatives with real prices/photos/differences, retains exact-match deltas and disables truly unavailable choices without misleading prices. Current product identity is explicit; shared canonical aliases are queried together. Native stock/cart behavior and model profiles are preserved.

Status: SAFE TO INTEGRATE for this feature, subject to the separate consolidation task; not merged, committed, pushed or deployed. Validation and limits are recorded in `docs/qa/smart-product-selector.md`. The original localhost:8082 runtime and its 34 pending files remain unchanged.

### Launch payment eligibility — LOCAL candidate (2026-10-28)

Closed in the separate LOCAL candidate: every webshop shipping method now allows Barion only; BACS, COD and pay-at-pickup are unavailable, including manipulated requests and stale sessions. See [verification and isolation boundaries](docs/qa/launch-payment-eligibility.md). This final decision supersedes the previous pickup-payment checkout option. The existing `cod` gateway/settings remain registered internally for staff cash recording and the unchanged activation preflight, but cannot be selected or processed through webshop checkout. Final consolidation and separately authorized TEST verification remain pending; the localhost:8082 storefront/performance work is unchanged.

## 2026-10-29 — Consolidation staged; Back Office release dependency remains

The completed independent packages and 30 already-deployed Back Office repair files are checkpointed. The staged candidate passes 4,397 focused/offline assertions. The newer Back Office intake package still requires the canonical order-command/background-worker boundary; its 115 pending paths and the original 34 storefront paths are preserved. No partial TEST release is claimed or deployed. See `docs/qa/final-local-consolidation.md` for exact SHAs, source drift, test limits and the required resume step.

## 2026-10-29 — Final integrated release boundary

The previously pending Back Office source dependency is reconciled with the approved Storefront/Smart Selector candidate; see `docs/qa/final-integrated-release.md`. The current TEST runtime was found using the base image without the approved metadata tool; deployment must retain the existing media overlay. The canonical target registry must be verified after safe provisioning (previous TEST count: 54 profiles). Full integrated concurrency/reservation/late-callback and controlled provider E2E acceptance remains a separate explicit next task. Google Reviews live authorization, production activation and production empty-catalog preparation are not silently completed by this release.

## Accessory draft / mixed invoice release gate (2026-10-30)

An overlapping native Store API draft rebuild can preserve a duplicate accessory behind an unchanged cart hash. The candidate now serializes the cart session before it is loaded and reconciles only unpaid linked-accessory drafts through Woo APIs. LOCAL reproduction and repair pass. The approved K.AFA used-iPhone / 27% accessory mapping passes the real vendor XML builder with unchanged gross prices. Actual issued TEST PDF acceptance is still required before closing this gate; see `docs/qa/accessory-draft-mixed-invoice.md`.
