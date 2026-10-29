# Smart product selector — LOCAL acceptance

Date: 2026-10-28. Branch: `feature/smart-product-selector`. Base HEAD: `f3c61e5f64fd687c2ef275e3a7ca5bfe358302ff`. No commit/push/merge/deployment.

## Isolation and preserved work

The starting point is an exact copy of the 34 uncommitted approved storefront, canonical-specification and performance files from `feature/performance-query-fixes`. Those files were hashed before copying. The original checkout, its database and localhost:8082 are untouched by this task. The separate Barion-only and unfinished Back Office worktrees were inspected, not merged or modified. This feature's readiness does not certify that separate consolidation.

The review instance runs at http://localhost:18883 in its own database and feature worktree. Only 125 published devices and 34 public media references were copied, using an explicit public-field allowlist. Original users, orders, sessions, settings and credentials were not copied. Source/uploads are mounted read-only; no provider plugins are active. External HTTP/mail and cron are blocked. The initially proposed full-database and shared-database approaches were rejected by automatic approval review and were not executed; the implemented public-catalog-only approach was accepted. Operational generated credentials remain outside Git and are never printed.

This is a selector preview, not a checkout/payment or Back Office environment. Hidden accessory catalog/settings were not copied. The native per-device purchase fragment remains responsible for accessories and cart identity; that implementation is unchanged. Provider/order end-to-end testing is deliberately out of scope.

## Rules

- A: one exact purchasable device preserves the other primary values and all configured public technical fields; direct switch with its actual price delta.
- A with multiple physical products: show every exact device with its own identity, photo and price; never choose the first silently.
- B: no exact device, but a compatible canonical-model product has the requested value. Show `Más kivitelben` and a compact inline chooser. Even one candidate requires explicit selection. No price delta is shown on the option.
- C: no matching purchasable device: visible, grey, disabled, without a price delta.
- Canonical model grouping uses the existing exact alias registry and existing `WooDeviceQueries::FAMILY` batch query. No nearest-model or generation inference.
- Browser indexing is built once from that public projection. Within alternatives, rank technical differences first, then storage, color and condition; product ID only stabilizes list ordering. No ranking causes an automatic choice.
- Technical differences include SIM, connectivity, chip, RAM, display/case size, case material, keyboard and allowlisted visible native technical attributes. Missing-versus-present values are differences, not inferred equivalence. Taxonomy attributes are cache-primed as a batch. IMEI/serial/private metadata never enter the projection.
- The current actual product ID is the initial reference, including on unavailable pages. Existing `DeviceAvailability` gates candidates; native Woo purchase/backorder semantics remain unchanged.
- Product URL/title, price, selected values, photos, facts, description and native purchase fragment change together. All alternatives share the same exact canonical specification profile, which remains correctly displayed rather than copied into every physical device.
- Existing adaptive selector grids, page layout and Watch storage exclusion are unchanged. Escape, close button and outside click dismiss the chooser; keyboard activation works and focus returns to the opener.

## Real LOCAL inventory matrix

| Case | Actual example | Result |
|---|---|---|
| A exact | iPhone #2513, silver -> #306, same 128 GB/B/SIM | Direct; +75,500 Ft, final 195,490 Ft |
| B other color | iPad Air #382, 256 GB -> #458 | Explicit chooser |
| C other condition | iPhone #328, sierra blue -> #345 | Explicit chooser |
| D other storage | iPad Air #382, pink -> #458 | Explicit chooser |
| E multiple changes | iPad #453 -> #454 | Color, storage, grade and connectivity disclosed |
| F multiple physical devices | iPhone #2513 current blue/128/B | #338 and #2513 separately listed, no automatic switch |
| G unavailable | iPad #453, pink | Grey/disabled, no price |
| H connectivity | #453 Wi-Fi -> #454 Wi-Fi + Cellular | Explicitly displayed and accepted |
| I model boundary | iPad 10 alternatives | No iPad Air/other generations |
| J native cart identity | Select #454 then add to cart | #454, quantity 1, 249,990 Ft; cart then emptied |

The demonstrated iPad #453 remains selected at 169,990 Ft after opening 256 GB. The only alternative #454 is yellow/256 GB/A+/Wi-Fi + Cellular at 249,990 Ft. Selecting it changes actual URL, photo (`bg-2` to `bg-1`), title, facts, selected controls and add-to-cart ID from 453 to 454. No intermediate silent substitution occurs.

## Verification

- 16 browser cases: iPhone #2513, iPad #453, MacBook #385, Watch #473 at 1440/1280/390/320. Initial and open chooser: no horizontal overflow; opening never switches the current device. Watch storage remains absent. Screenshots personally inspected.
- Actual iPad selection, native add-to-cart and cart removal; exact iPhone switch and real price delta; duplicate physical-device chooser; desktop keyboard Enter/Escape/focus; mobile close/selection. No console errors.
- 1,969 JavaScript assertions: contract fixtures plus exhaustive safety properties over five real public model pools and explicit A–J inventory cases.
- PHP: 69 existing model-option, 116 purchase-render, 48 information-presentation, 23 recommendation, 28 gallery, 435 canonical-specification, 428 smart-selector public-projection/privacy and 103 query/performance assertions. Total focused assertions: **3,219**.
- `make test`: **388** offline lifecycle/security/legal-PDF assertions, no provider calls; these check preservation, not a fresh provider E2E. Total assertions: **3,607**, plus the browser cases above.
- `make quality` exits successfully but reports that no linter/static analyzer is configured; it is not counted as analysis coverage. PHP/JS syntax checks pass.
- The query test removes its own temporary fixtures through Woo APIs and verifies the original copied catalog is unchanged. The UI QA cart is emptied and its isolated Woo session removed through the supported session API. Woo Store API automatically created anonymous checkout-draft #2542 while the QA cart was viewed; its single #454 item, missing customer/email and unpaid draft status were verified before deleting it through Woo API. No orders remain, and no payment, invoice, email or shipment was triggered. The isolated preview retains its 125 public catalog copies for review.

A draft change that tightened the existing payload `available` flag failed the native-backorder contract test and was removed. The final implementation preserves that native contract and uses a separate existing-availability projection only for selector candidacy. A gallery test initially lacked its old default media IDs in the filtered preview; it was rerun successfully with three actually present media IDs. These were resolved development/test setup findings, not remaining application failures.

## Performance

Same isolated database/runtime, five sequential HTTP requests per route, first two warm-up, median of final three. No provider/network calls. SQL counts are a separate instrumented local request. Latest serialized run follows completion of background Docker tests; raw earlier runs are retained, including transient host-load variance.

| Page | Before median TTFB | After median TTFB | SQL before -> after |
|---|---:|---:|---:|
| iPhone | 112.17 ms | 110.13 ms | 124 -> 124 |
| iPad | 56.28 ms | 64.72 ms | 102 -> 102 |
| MacBook | 48.97 ms | 51.45 ms | 100 -> 102 |
| Watch | 54.71 ms | 53.17 ms | 102 -> 102 |

MacBook now includes the second actual same-model alias device (#432), explaining the additional bounded rendering work. iPhone's 49-device payload rises from 526,297 to about 539 KB (technical labels/eligibility); no per-click requests or full-catalog query are added. There is no significant selector-query regression or multi-second response. These are LOCAL comparative measurements, not production throughput claims.

## Reproduction

Run the standalone JavaScript test with Node; optionally pass the exported public `pools.json` to include the actual inventory assertions. Run `smart-selector-local.php 2513 453 385 473` in the guarded isolated WordPress container. It reads products and uses only in-memory privacy fixtures. Use the existing render/catalog/performance scripts in the same guarded runtime.

Evidence directory (outside Git): `smart-selector-evidence-20261028` beside the worktree. Contains baseline hashes, before/after timings, public pools, 16 viewport screenshots, multiple-device screenshot, selection/cart proof and full mobile page.

## Task-only files

1. `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Frontend/ProductFrontendDisplay.php`
2. `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/WooSelectorConfiguration.php`
3. `wordpress/wp-content/plugins/appleklinika-inventory/assets/smart-product-selector.js`
4. `wordpress/wp-content/plugins/appleklinika-inventory/assets/smart-product-selector.css`
5. `wordpress/wp-content/plugins/appleklinika-inventory/tests/smart-product-selector.cjs`
6. `wordpress/wp-content/plugins/appleklinika-inventory/tests/smart-selector-local.php`
7. `README.md`
8. `deficiencies.md`
9. `docs/qa/smart-product-selector.md`

The remaining dirty files are the preserved baseline, not new changes from this task. Final status: **SAFE TO INTEGRATE** for this isolated selector feature. Integration/release remains unperformed and requires the separately controlled consolidation workflow.
