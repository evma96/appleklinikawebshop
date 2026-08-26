# Apple Klinika Buyback Phase 2B1

> Historical phase note: Phase 2B2 now adds readiness-gated atomic activation and typed current-active resolution. The transient pricing engine and preview behavior documented here remains unchanged.

## Status and scope

Phase 2B1 adds a deterministic pure-PHP pricing engine and a transient calculation preview to the existing draft price-book admin. Plugin version is `0.6.0`; code and installed schema remain `1.1.0`. No migration or table change is included.

The preview is available only to users with `ak_buyback_manage_price_books` on a persisted `draft` price book. It creates no request, snapshot, event, offer, option, transient, session, user meta, or WooCommerce record.

## Canonical conditions

`ConditionDefinition` is the single registry used by rule validation, admin rule fields, preview rendering, and preview input normalization. All preview conditions are required.

- `battery_health`: integer `0..100`; numeric comparison operators.
- `powers_on`, `display_functional`, `touch_functional`, `face_id_functional`, `camera_functional`, `charging_functional`, `liquid_damage`, `motherboard_issue`, `bent_or_dented`: boolean.
- `screen_condition`, `frame_condition`, `back_glass_condition`, `camera_lens_condition`: `like_new`, `excellent`, `very_good`, `good`, `damaged`.
- `replacement_parts`: `none_known`, `original_repair`, `non_original`, `unknown`.

Machine values are identity. Hungarian labels are admin presentation only. Unknown values, missing values, duplicate keys, invalid types, and unsupported operators are rejected.

## Input and result model

`PricingCalculationInput` contains a typed category, inventory-preserving `PricingModelKey`, integer `StorageCapacity`, immutable `ConditionAnswerCollection`, and one `ServiceMode`. It contains no WordPress object, customer PII, IMEI, IBAN, product, or floating-point value.

`PricingCalculationResult` supports:

- `offered`
- `manual_review`
- `rejected`
- `configuration_error`

Offered results contain the price-book identity/version, mode, currency, base amount, post-deduction amount, post-condition-multiplier amount, post-mode amount, raw amount, rounded final amount, calculator version, matched rules, and ordered breakdown. Non-offer results contain safe reason codes and no final offer.

## Deterministic calculation

Execution order is fixed:

1. Validate the draft price book, ownership, rule shapes, currency, policy, rounding, exact base count, and mode-adjustment count.
2. Resolve exactly one enabled base price by category, model key, and storage.
3. Evaluate all enabled hard rejects. A match stops calculation as `rejected`.
4. Evaluate all enabled manual-review rules. A match stops calculation as `manual_review`.
5. Apply matching fixed deductions in priority order, clamped at zero.
6. Apply matching condition multipliers in priority order.
7. Compare the post-condition amount with an enabled exact model-specific automatic-offer minimum, when one exists. At or below the configured threshold, stop on the existing `manual_review` path with `below_model_minimum_offer`.
8. Apply zero or one matching service-mode fixed or multiplier adjustment.
9. Compare the raw amount with the price-book minimum.
10. Apply deterministic nearest-increment half-up rounding to offered results only.

Rules are sorted by ascending priority, persisted rule ID, then rule code. Database return order is never authoritative.

## Integer arithmetic

All money is integer HUF. A multiplier uses integer basis points where `10000` is `1.0000`. Every multiplier step uses:

```text
floor(amount_minor * multiplier_bps / 10000)
```

Multipliers are applied individually and never combined through floating point. Fixed deductions cannot produce a negative amount. The Phase 2A fixed mode adjustment is a non-negative addition; a mode multiplier uses the same basis-point rule.

Nearest-increment rounding is half-up. With a 1,000 Ft increment, `138499` becomes `138000`, while `138500` and `138501` become `139000`.

## Configuration and business outcomes

No matching base produces `configuration_error: missing_base_price`. Multiple enabled exact bases produce `duplicate_base_price`. Multiple enabled adjustments for the selected mode produce `duplicate_mode_adjustment`. Disabled rules are ignored.

Hard reject wins over manual review. An enabled model-specific minimum is scoped only to its canonical model and uses `<=` before a service-mode adjustment; it produces the existing personal-inspection outcome with `below_model_minimum_offer`. The effective service-mode adjustment is then the one explicit matching model/mode rule when present, otherwise the price-book-wide mode rule; the two rules never stack. If no such model minimum applies, the price-book global minimum remains the backward-compatible strict `<` fallback and follows its configured policy with `below_minimum_offer`; the engine never raises the amount to a fake minimum.

Breakdown lines are immutable and follow execution order: base price, fixed deductions, condition multipliers, model-minimum policy when matched, mode adjustment, global minimum policy, and rounding.

## Application and admin preview

`PreviewDraftPriceBookCalculationHandler` loads the existing draft book, rules, and read-only inventory catalog, verifies the stable iPhone model key, and calculates independently for:

- `in_store_instant`
- `fast_online`
- `higher_offer`
- `trade_in`

Admin URL:

```text
/wp-admin/admin.php?page=appleklinika-buyback-price-books
```

Open a draft book to use **Kalkulációs előnézet**. The form is server-rendered, capability- and nonce-protected, and uses the canonical condition registry. Results are rendered on the same request. Submitted values are not persisted or placed in URLs.

## Verification

Run twice:

```bash
make test-buyback-pricing-engine
make test-buyback-pricing-engine
```

Then run:

```bash
make test-buyback-pricing-admin
make test-buyback-legacy
make test-buyback-persistence
make test-buyback-domain
make test-buyback
```

The engine suite covers validation, every comparison operator, base resolution, precedence, deductions, integer multipliers, all four modes, minimum policy, rounding, determinism, no mutation, authorization, repository-backed preview, and finally-style cleanup. It records before/after business-table counts and the legacy user-meta hash.

## Explicit non-goals

Phase 2B1 adds no activation, retirement, cloning, public calculator, REST/AJAX route, shortcode, request linkage, preliminary/final offer persistence, pricing snapshot, inspection, payout, courier, trade-in credit, account flow, WooCommerce order/cart/checkout integration, theme change, inventory write, or legacy import.

Phase 2B2 now defines separately reviewed activation governance and current-active resolution. This Phase 2B1 preview itself still does not imply or perform activation, and no public/live-use boundary was added.
