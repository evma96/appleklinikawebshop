# Canonical model specifications — LOCAL completion, 2026-10-28

## Current result

**READY FOR INTEGRATION. LOCAL only; not committed, pushed or deployed.**

| Measure | Result |
|---|---:|
| Current catalog models | 102 |
| Catalog models with a specification profile | 102 |
| Catalog models missing a profile | 0 |
| Stored profiles, including preserved historical profile | 103 |
| Stored profiles with exact official Apple archive URL | 103 |
| Profiles missing an exact Apple URL | 0 |
| Orphans not referenced by the configured catalog | 1 |
| Profiles added in this pass | 48 |
| Existing profiles revised | 51 |
| Existing profiles with actual technical-row corrections | 8 |
| Existing profiles with source URL replaced | 50 |

The counts overlap: seven of the eight technical corrections also received an archive URL. Four existing profiles remain unchanged. All 55 pre-existing profiles received a focused sanity review against official Apple specifications. The previous 45 mapped missing/generic URLs are resolved; four previously specific current Apple product-page URLs and the historical orphan also received stable model archive links.

Family coverage: iPhone 34, iPad 27, MacBook 32 catalog models plus one historical profile, Apple Watch 9. The orphan is `macbook_pro_16_m3`, whose content explicitly covers the 16-inch M3 Pro/Max family. It is retained with its correct Apple family specification; it is not reclassified as a nonexistent base-M3 16-inch device.

No unresolved source limitation remains for this catalog. Profiles summarize supported hardware, not every sentence on Apple's pages. Regional SIM/weight variations follow each linked source; SIM support remains qualified. New battery estimates explicitly refer to new-device manufacturer ratings. Model configurations do not overwrite an individual device's storage, RAM, condition, battery health or identity.

## Architecture and safe data provisioning

`appleklinika_official_specs_by_model` remains the sole runtime source. Existing `ModelIdentity` aliases, the `ModelSpecifications` port, the device-entry service and the renderer are unchanged in this data-completion pass. No product-level copy, title guess, nearest-model fallback, new schema, provider call or frontend redesign.

`resources/model-specifications-bootstrap.json` is versioned provisioning data, not a parallel runtime registry. `model-specifications-revisions.json` contains SHA-256 fingerprints of only the reviewed previous rows/source URL. The existing installer adds missing keys and updates a previous profile only when its fingerprint matches. Unknown/manual edits, explicit empty entries and unrelated provenance are preserved. Re-running an already installed version performs no writes. This small revision guard is needed to apply verified corrections without blindly overwriting editor changes.

Each revised/new profile retains its exact Apple source URL, source title and review/fetch date. These maintenance fields are not rendered to customers; the existing public Apple specification link remains. No scraped HTML or page dump is stored in the repository. Lookup continues using WordPress option caching, without an additional cache system.

## Original regression and preserved fix

Manual product #2531 selected `iphone_12_pro` when that profile was absent. The prior candidate fixed the shared entry/renderer binding and added the missing 12 Pro profile. The selected model now wins over stale historical spec pointers, title guesses and legacy product JSON. That accepted fix is preserved. The original missing-profile root cause was a coverage gap, not a missing rendering hook.

Existing legacy product metadata is retained but not used as a fallback. Missing/invalid future profiles still produce explicit editor warnings and no borrowed storefront data. The negative cases are now simulated with in-memory option overlays, because the actual catalog has no remaining missing entries.

## Official sources and identity review

Sources were discovered through Apple's model-identification indexes and opened individually, checking the page title, chip, generation and screen-size variant. Only `support.apple.com` / Apple official pages were used. The repository stores concise Hungarian hardware facts and source references, not page copies.

- [iPhone model identification](https://support.apple.com/en-us/108044)
- [iPad model identification](https://support.apple.com/en-us/108043)
- [MacBook Pro model identification](https://support.apple.com/en-gb/108052)
- [MacBook Air model identification](https://support.apple.com/en-gb/102869)
- [Apple Watch model identification](https://support.apple.com/en-us/108056)

Some Apple pages intentionally cover Pro and Max chip configurations together. Separate canonical profiles contain the appropriate chip-specific CPU/GPU, memory, storage and battery values; sharing a verified source URL does not merge model identity.

## Technical corrections to existing profiles

| Profile | Evidence-backed correction | Apple source |
|---|---|---|
| `apple_watch_series_10` | Replace resolution placeholder with 42 mm: 374 × 446; 46 mm: 416 × 496. | [Apple](https://support.apple.com/en-us/121202) |
| `macbook_air_m2` | Bluetooth 5.3, as specified on the exact M2 Air support page. | [Apple](https://support.apple.com/en-us/111867) |
| `macbook_pro_14_m4_pro` | 24/48 GB memory and up to 4 TB SSD; remove Max-only 64 GB / 8 TB claims. | [Apple](https://support.apple.com/en-gb/121553) |
| `macbook_pro_16_m4_pro` | 24/48 GB memory and up to 4 TB SSD; remove Max-only 64 GB / 8 TB claims. | [Apple](https://support.apple.com/en-gb/121554) |
| `macbook_pro_16_m4_max` | Up to 21 hours video streaming / 14 hours web, instead of the Pro 24-hour rating. | [Apple](https://support.apple.com/en-gb/121554) |
| `macbook_pro_14_m5` | 16/24/32 GB, 512 GB–4 TB SSD, Wi-Fi 6E and Bluetooth 5.3; no Pro/Max radio inheritance. | [Apple](https://support.apple.com/en-gb/125405) |
| `macbook_pro_14_m5_pro` | Include 15 CPU / 16 GPU and 18 CPU / 20 GPU variants; 24/48 GB and conditional 64 GB; 1/2/4 TB SSD. | [Apple](https://support.apple.com/en-gb/126318) |
| `macbook_pro_16_m5_pro` | 24/48/64 GB memory and 1/2/4 TB SSD; remove misleading 48 GB-only base claim. | [Apple](https://support.apple.com/en-gb/126319) |

Other existing technical rows remain unchanged. Existing original launch configurations are not rewritten merely because Apple later sold another base configuration.

## Added models

The exact 48 keys below come from the prior audit, not an expanded speculative model universe.

| Family | Catalog / canonical profile key | Model | Exact Apple source |
|---|---|---|---|
| iphone | `iphone_xr` / `iphone_xr` | iPhone XR | [Apple](https://support.apple.com/en-gb/111868) |
| iphone | `iphone_xs` / `iphone_xs` | iPhone XS | [Apple](https://support.apple.com/en-gb/111881) |
| iphone | `iphone_xs_max` / `iphone_xs_max` | iPhone XS Max | [Apple](https://support.apple.com/en-gb/111880) |
| iphone | `iphone_11` / `iphone_11` | iPhone 11 | [Apple](https://support.apple.com/en-gb/111865) |
| iphone | `iphone_11_pro` / `iphone_11_pro` | iPhone 11 Pro | [Apple](https://support.apple.com/en-gb/111879) |
| iphone | `iphone_11_pro_max` / `iphone_11_pro_max` | iPhone 11 Pro Max | [Apple](https://support.apple.com/en-gb/111878) |
| iphone | `iphone_se_2nd_generation` / `iphone_se_2nd_generation` | iPhone SE (2nd generation) | [Apple](https://support.apple.com/en-gb/111882) |
| iphone | `iphone_12_mini` / `iphone_12_mini` | iPhone 12 mini | [Apple](https://support.apple.com/en-gb/111877) |
| iphone | `iphone_12` / `iphone_12` | iPhone 12 | [Apple](https://support.apple.com/en-gb/111876) |
| iphone | `iphone_12_pro_max` / `iphone_12_pro_max` | iPhone 12 Pro Max | [Apple](https://support.apple.com/en-gb/111874) |
| iphone | `iphone_13_mini` / `iphone_13_mini` | iPhone 13 mini | [Apple](https://support.apple.com/en-gb/111873) |
| iphone | `iphone_13` / `iphone_13` | iPhone 13 | [Apple](https://support.apple.com/en-gb/111872) |
| iphone | `iphone_13_pro_max` / `iphone_13_pro_max` | iPhone 13 Pro Max | [Apple](https://support.apple.com/en-gb/111870) |
| iphone | `iphone_se_3rd_generation` / `iphone_se_3rd_generation` | iPhone SE (3rd generation) | [Apple](https://support.apple.com/en-gb/111866) |
| iphone | `iphone_14` / `iphone_14` | iPhone 14 | [Apple](https://support.apple.com/en-gb/111850) |
| iphone | `iphone_14_plus` / `iphone_14_plus` | iPhone 14 Plus | [Apple](https://support.apple.com/en-gb/111854) |
| iphone | `iphone_14_pro` / `iphone_14_pro` | iPhone 14 Pro | [Apple](https://support.apple.com/en-gb/111849) |
| iphone | `iphone_14_pro_max` / `iphone_14_pro_max` | iPhone 14 Pro Max | [Apple](https://support.apple.com/en-gb/111846) |
| iphone | `iphone_15` / `iphone_15` | iPhone 15 | [Apple](https://support.apple.com/en-gb/111831) |
| iphone | `iphone_15_plus` / `iphone_15_plus` | iPhone 15 Plus | [Apple](https://support.apple.com/en-gb/111830) |
| iphone | `iphone_15_pro` / `iphone_15_pro` | iPhone 15 Pro | [Apple](https://support.apple.com/en-gb/111829) |
| iphone | `iphone_15_pro_max` / `iphone_15_pro_max` | iPhone 15 Pro Max | [Apple](https://support.apple.com/en-gb/111828) |
| iphone | `iphone_16` / `iphone_16` | iPhone 16 | [Apple](https://support.apple.com/en-gb/121029) |
| iphone | `iphone_16_plus` / `iphone_16_plus` | iPhone 16 Plus | [Apple](https://support.apple.com/en-gb/121030) |
| iphone | `iphone_16_pro` / `iphone_16_pro` | iPhone 16 Pro | [Apple](https://support.apple.com/en-gb/121031) |
| iphone | `iphone_16_pro_max` / `iphone_16_pro_max` | iPhone 16 Pro Max | [Apple](https://support.apple.com/en-gb/121032) |
| iphone | `iphone_16e` / `iphone_16e` | iPhone 16e | [Apple](https://support.apple.com/en-gb/122208) |
| iphone | `iphone_17` / `iphone_17` | iPhone 17 | [Apple](https://support.apple.com/en-gb/125089) |
| iphone | `iphone_air` / `iphone_air` | iPhone Air | [Apple](https://support.apple.com/en-gb/125092) |
| iphone | `iphone_17_pro` / `iphone_17_pro` | iPhone 17 Pro | [Apple](https://support.apple.com/en-gb/125090) |
| iphone | `iphone_17_pro_max` / `iphone_17_pro_max` | iPhone 17 Pro Max | [Apple](https://support.apple.com/en-gb/125091) |
| iphone | `iphone_17e` / `iphone_17e` | iPhone 17e | [Apple](https://support.apple.com/en-gb/126470) |
| ipad | `ipad_7th_generation` / `ipad_7th_generation` | iPad (7th generation) | [Apple](https://support.apple.com/en-us/111911) |
| ipad | `ipad_air_3rd_generation` / `ipad_air_3rd_generation` | iPad Air (3rd generation) | [Apple](https://support.apple.com/en-us/111939) |
| ipad | `ipad_mini_5th_generation` / `ipad_mini_5th_generation` | iPad mini (5th generation) | [Apple](https://support.apple.com/en-us/111904) |
| ipad | `ipad_pro_11_inch_2nd_generation` / `ipad_pro_11_inch_2nd_generation` | iPad Pro 11-inch (2nd generation) | [Apple](https://support.apple.com/en-us/118452) |
| ipad | `ipad_pro_12_9_inch_4th_generation` / `ipad_pro_12_9_inch_4th_generation` | iPad Pro 12.9-inch (4th generation) | [Apple](https://support.apple.com/en-us/111977) |
| ipad | `ipad_8th_generation` / `ipad_8th_generation` | iPad (8th generation) | [Apple](https://support.apple.com/en-us/118451) |
| mac | `macbook_pro_13_inch_m1` / `macbook_pro_13_inch_m1` | MacBook Pro 13-inch M1 | [Apple](https://support.apple.com/en-gb/111893) |
| mac | `macbook_pro_14_inch_m1_pro` / `macbook_pro_14_inch_m1_pro` | MacBook Pro 14-inch M1 Pro | [Apple](https://support.apple.com/en-gb/111902) |
| mac | `macbook_pro_14_inch_m1_max` / `macbook_pro_14_inch_m1_max` | MacBook Pro 14-inch M1 Max | [Apple](https://support.apple.com/en-gb/111902) |
| mac | `macbook_pro_16_inch_m1_pro` / `macbook_pro_16_inch_m1_pro` | MacBook Pro 16-inch M1 Pro | [Apple](https://support.apple.com/en-gb/111901) |
| mac | `macbook_pro_16_inch_m1_max` / `macbook_pro_16_inch_m1_max` | MacBook Pro 16-inch M1 Max | [Apple](https://support.apple.com/en-gb/111901) |
| mac | `macbook_pro_14_inch_m2_max` / `macbook_pro_14_inch_m2_max` | MacBook Pro 14-inch M2 Max | [Apple](https://support.apple.com/en-gb/111340) |
| mac | `macbook_pro_16_inch_m2_max` / `macbook_pro_16_inch_m2_max` | MacBook Pro 16-inch M2 Max | [Apple](https://support.apple.com/en-gb/111838) |
| mac | `macbook_pro_14_inch_m4_max` / `macbook_pro_14_inch_m4_max` | MacBook Pro 14-inch M4 Max | [Apple](https://support.apple.com/en-gb/121553) |
| mac | `macbook_pro_14_inch_m5_max` / `macbook_pro_14_inch_m5_max` | MacBook Pro 14-inch M5 Max | [Apple](https://support.apple.com/en-gb/126318) |
| mac | `macbook_pro_16_inch_m5_max` / `macbook_pro_16_inch_m5_max` | MacBook Pro 16-inch M5 Max | [Apple](https://support.apple.com/en-gb/126319) |

## Repeatable checks

Run from the active LOCAL worktree:

```sh
docker compose exec -T wordpress php wp-content/plugins/appleklinika-inventory/tests/model-specifications-audit.php
docker compose exec -T wordpress php wp-content/plugins/appleklinika-inventory/tests/model-specifications-catalog.php
docker compose exec -T -e AK_LOCAL_VERIFICATION=1 wordpress php wp-content/plugins/appleklinika-inventory/tests/model-specifications-local.php
```

The audit prints current catalog count, profile coverage, exact-source coverage, orphan keys and a per-model mapping. It exits nonzero for missing profiles/source links and makes no external request. Offline catalog tests exercise canonical coverage and revision provisioning without WordPress/database/network. The LOCAL integration test requires the local environment guard and external HTTP blocking; mail/HTTP calls are counted and blocked.

## Verification result

- **435** offline catalog/provisioning assertions: 102 exact identities, all 103 archive links, no product-data provenance in the shipped bundle, chip/size boundary cases, guarded correction, preservation of manual/empty/malformed entries and zero-write repeat provisioning.
- **71** LOCAL binding/creation/rendering/scale assertions: ordinary physical-device entry, automatic profiles, no manual rows/URL entry, private identity exclusion, model changes, controlled missing-data warnings and original-product preservation.
- Existing regressions: **69** selector + **48** product-information + **116** purchase-render + **23** recommendation assertions.
- **Total: 762 automated assertions PASS.** No provider transaction, email or external HTTP request in these tests.
- Three newly created iPhone 13 Pro physical devices with different capacities resolve the same canonical rows; no full specification is copied into product metadata.
- 1000 consecutive same-model lookups: **one SQL read**, approximately **143 ms** in the LOCAL CLI run. Existing WordPress cache behavior is sufficient; this is a lookup sanity test, not a production load benchmark.
- Browser: new iPhone XR, iPad 7th generation, MacBook Pro 14-inch M1 Max and Apple Watch Series 10, each at **1440 and 390 px**. Also existing manual iPhone 12 Pro #2531 and seeded iPhone 13 Pro #288 at both sizes: **12 page/viewport checks PASS**.
- `Műszaki adatok` appears automatically, `Összes műszaki adat` expands/collapses, the exact Apple link is correct, and different generations/chips retain their own rows. No horizontal overflow, application console error or visible IMEI/serial/source-maintenance metadata. Web-container logs show no PHP fatal/warning during browser verification.
- No original physical product required save, recreation or migration. All **169 original product objects** retain identical serialized Woo hashes (131 published, 38 pre-existing trash). Pricing/stock/cart/order/invoice/lifecycle source was not changed by this pass.
- Disposable QA products #2741, #2743, #2745, #2747, #2749, #2750–#2752 and photos #2742, #2744, #2746, #2748 were removed using Woo/WordPress APIs. The earlier failed fixture setup also cleaned itself. No owned QA product/media remains; no order/account/cart was created.

Evidence is stored outside Git in the task workspace's `model-catalog-completeness/` directory: twelve full-page screenshots and `browser-results.json`. The blank photos in four QA screenshots are deliberately disposable fixtures, not catalog image changes.

The working branch is `feature/model-specification-completeness`; HEAD remains `f3c61e5f64fd687c2ef275e3a7ca5bfe358302ff`. The earlier accepted uncommitted frontend and model-binding work is preserved. No TEST, production, main, commit, push or deployment action occurred.
