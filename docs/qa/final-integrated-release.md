# Final Storefront / Back Office integration — 2026-10-29

## Inputs and ownership

The verified develop and TEST repository baseline was `56c2646f70ad481bc80f20cd8512a1314c653b91`. Storefront candidate `f14e2d552d728e0d15ae2da86ea5d278877918a1` preserves the approved Smart Selector checkpoint `92b791351f9d3a15570a52b27c67bedb03c1f554`, specifications/performance/presentation prerequisites and Barion-only checkout. Back Office input is `be2854b0fabcd9ff9933264de372eb58e61fe78f`.

This supersedes the integration dependency in `final-local-consolidation.md`; that file remains historical evidence. The final Back Office handoff and its actual 100-file TEST snapshot were checked. All 100 runtime files matched the approved archive. The latest five operational and six test-file updates were applied only after both before/after hashes matched. The supplied patch wrapper was malformed, so verified source blobs were used for those precise paths.

## Semantic reconciliation

- Keep current TEST order routing, invoice reconciliation, GLS automation, cash/MPL handling, order locks, expected-state validation, accessory association and security guards.
- Add the accepted device-intake/list/edit UI and application adapters. The three additive intake schema/offer/options types use the current Inventory service/store; older versions of Inventory were not copied.
- Shared router changes are limited to device routes/navigation and intake activity presentation. The repository gains intake audit recording; existing order mutations are retained.
- Native TEST uses the existing strict boolean plus matching-environment write authorization for both order and product commands. No separate LOCAL database/URL adapter is deployed; no production authorization is enabled. Capabilities/nonces remain required.
- Preserve current order-specific CSS; append only approved intake styling and navigation wrapping.
- Update two older offline router test fixtures to explicitly configure the existing staging write gate. Extend the gate matrix to product writes and native URL preservation.
- Update the old physical-device regression to assert Smart Selector eligibility rather than absence from its contextual product pool. The approved selector intentionally retains the current sold product for page context but never offers it for purchase. Application selector behavior is unchanged.

## Focused LOCAL evidence

The merged source is exercised in a separate disposable localhost:18884 runtime with a fresh database, public catalog fixtures only, isolated DB network, and blocked provider/mail calls. Original localhost:8082 source/data and other worktrees are not overwritten.

- Mandatory `make test`: lifecycle, operational repairs, security, legal PDF hashes and review-preview suites.
- Native physical-device foundation: 204 assertions, including HEIC/ICC/private metadata, four families, private identity, native stock/retry and real invoice-line mapping without issuing an invoice.
- New native Back Office intake boundary: 112 assertions; four families × draft/publish/save-next, current model specifications, shared photo/store/naming, one native unit, replay and cleanup.
- Native selector projection: 78 assertions on four existing public devices.
- Optimized query/image equivalence: 103 assertions, owned fixtures cleaned.
- Accessory association: 52 assertions, native draft/order cleanup, no providers/mail.
- Visual LOCAL 1440/390: exact iPhone switch, accepted purchase/selector layout, no horizontal overflow or console errors.
- `make quality` is a successful placeholder; it does not provide configured static-analysis coverage.

## Deployment requirements and rollback

TEST's repository alone does not represent the previous separately mounted runtime plugin. A fresh source archive of the actual plugin mount and theme is required in addition to the baseline SHA. The pre-release consistent encrypted DB/media backup completed as `20261029T160508Z`. Source/config recovery material is private under `/opt/appleklinika/release-checkpoints/20261029-final/`; private Compose content is never committed.

The inspected running TEST container had reverted to the base WordPress image, missing ExifTool. Reuse the already approved `docker-compose.test-media.yml` and existing media image for this release; keep this overlay in subsequent recreation commands. Preserve private base configuration, guards, secrets, source mounts and routing. Verify actual decoder/metadata-tool availability, not the image filename alone.

Provision the existing canonical specification option through `WordPressModelSpecifications::installMissingProfiles()` (already called during plugin bootstrap). Verify all 102 configured models resolve to exact Apple source profiles on the actual target. This is reviewed, versioned, idempotent provisioning; unknown manual edits are preserved. No catalog/customer/order database import.

Rollback: stop only TEST WordPress; restore the previous actual plugins/theme archive and saved private Compose configuration; restore repository to the recorded baseline using normal checkout without rewriting remote history; recreate the prior WordPress image/configuration. Restore encrypted DB/media only if needed, through the documented recovery procedure. Provider records must not be replayed. Preserve historical orders #794/#795 and their existing invoice/GLS artifacts.

## Payment and release acceptance boundaries

Only Barion is eligible in webshop checkout for GLS, MPL and zero-cost pickup. BACS is disabled and its instructions suppressed. COD remains internally registered for existing staff cash/activation compatibility but is never webshop-eligible; the server-side allowlist denies it irrespective of legacy options. No provider credentials or production activation settings change. Google Reviews live access remains externally pending; TEST must not display QA fixtures. Production starts with an empty real-device catalog; TEST products are not production content.

Post-deploy evidence is recorded outside Git with the exact deployed SHA: actual runtime hashes, 102-model audit, shipping/payment matrix, 1440/390 browser smoke, PHP/JS observations, before/after TTFB, cleanup and historical-order fingerprints. A smoke pass is not full integrated provider E2E acceptance.

## Minimal next TEST acceptance

1. Two controlled buyers attempt the same unique GLS device. Prove one stock reservation/order winner; failed/expired payment releases availability according to the existing policy. Recheck a late callback against a changed/expired reservation.
2. One controlled successful Barion TEST payment and repeated callback: one stock deduction, one automatic TEST invoice, one acknowledgement then one accepted email. Verify inbox/PDF and My Account/Back Office equality.
3. On that order, packing complete creates one GLS label, repeat/retry cannot duplicate; no shipping email before actual recorded handoff. Confirm one handoff email/tracking and separate final completion.
4. One prepaid personal-pickup order: no cash webshop option, no GLS label, ready-for-pickup and actual collection remain separate from payment and completion.

Reuse #794/#795 historical document/provider evidence where the relevant surface is unchanged, but do not label the new SHA fully E2E accepted before the reservation/concurrency/late-callback and combined lifecycle scenarios above are exercised. No new provider transaction was necessary for source consolidation.
