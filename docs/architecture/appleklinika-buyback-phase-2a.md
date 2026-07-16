# Apple Klinika Buyback Phase 2A

Status: implemented, pending final acceptance gate

Date: 2026-07-15

## Scope

Phase 2A adds versioned draft price books, strictly shaped pricing rules, WordPress persistence, and a capability-gated WooCommerce admin screen. It is an internal configuration layer only. No price book can be activated, retired, linked to a request, or used to calculate an offer in this phase.

Plugin version is `0.5.0`. Code and installed schema versions are `1.1.0` after migration.

## Schema migration

The explicit `PricingSchemaMigration` advances schema `1.0.0` to `1.1.0` and creates two plugin-owned InnoDB tables with the active WordPress charset and collation. It uses `dbDelta()` and verifies all required columns and indexes after every run. Existing Phase 1 tables are not altered.

### `{prefix}ak_buyback_price_books`

Columns:

- `id`, `version_number`, `label`, `status`, `currency`
- `effective_from`, `effective_to`
- `minimum_offer_minor`, `rounding_increment_minor`, `minimum_policy`
- `created_by`, `activated_by`, `retired_by`
- `version`
- `created_at`, `updated_at`, `activated_at`, `retired_at`

Indexes:

- primary key `id`
- unique key `version_number`
- `status_effective_from (status, effective_from)`
- `status_updated_at (status, updated_at)`

### `{prefix}ak_buyback_price_rules`

Columns:

- `id`, `price_book_id`, `rule_code`, `rule_kind`, `category`
- `model_key`, `storage_gb`, `service_mode`
- `condition_key`, `comparison_operator`, `comparison_value_json`
- `amount_minor`, `multiplier_bps`, `priority`, `is_enabled`
- `public_label`, `internal_note`, `version`, `created_at`, `updated_at`

Indexes:

- primary key `id`
- unique key `book_rule_code (price_book_id, rule_code)`
- `book_kind (price_book_id, rule_kind)`
- `book_model_storage (price_book_id, model_key, storage_gb)`
- `book_priority (price_book_id, priority, id)`
- `category_model (category, model_key)`

The migration is deterministic and rerunnable. It creates no automatic price book or rule. Deactivation removes plugin capabilities but retains all plugin tables and stored data.

## Pricing domain

The pure `Domain/Pricing` namespace has no WordPress or database dependency. It contains immutable types for:

- `PriceBookId`, `PriceBookVersionNumber`, `PriceBookStatus`
- `CurrencyCode`, `MinimumOfferPolicy`, `PricingActorId`
- `PricingRuleId`, `PricingRuleCode`, `PricingRuleKind`
- `ComparisonOperator`, `BasisPointsMultiplier`, `StorageCapacity`, `RulePriority`
- `ConditionDefinition`, `PricingRuleDefinition`, `PricingRule`, and `PriceBook`

`BasisPointsMultiplier` stores integers only: `10000` is `1.0000`, and the documented safe maximum is `50000`. `StorageCapacity` accepts `1–8192` GB. Rule priority accepts `-100000–100000`.

The domain understands `draft`, `active`, and `retired` for safe reconstitution. Phase 2A creates only `draft` books and exposes no arbitrary status setter, activation, or retirement operation. Every accepted draft mutation increments the optimistic aggregate or rule version. Mutation of an active or retired book is rejected.

## Rule shapes

The admin exposes these rule kinds:

- `base_price`: iPhone `model_key`, canonical integer `storage_gb`, and non-negative HUF amount
- `fixed_deduction`: condition, operator, comparison value, and non-negative HUF amount
- `multiplier`: condition, operator, comparison value, and integer basis-point multiplier
- `mode_adjustment`: one service mode and exactly one fixed amount or multiplier
- `hard_reject`: condition, operator, comparison value, and required customer-safe public label
- `manual_review`: condition, operator, comparison value, and required customer-safe public label

`minimum_offer` remains a recognized future domain code but is deliberately rejected by the Phase 2A shape validator and is not exposed in admin. The price-book-level minimum and policy are the only Phase 2A minimum mechanism. Conflicting model, storage, condition, service-mode, amount, or multiplier fields are rejected rather than silently discarded.

Supported comparison operators are `equals`, `not_equals`, `less_than`, `less_or_equal`, `greater_than`, `greater_or_equal`, `between`, and `in`.

Supported condition keys are:

- `battery_health`
- `powers_on`
- `display_functional`
- `touch_functional`
- `face_id_functional`
- `camera_functional`
- `charging_functional`
- `liquid_damage`
- `motherboard_issue`
- `screen_condition`
- `frame_condition`
- `back_glass_condition`
- `camera_lens_condition`
- `bent_or_dented`
- `replacement_parts`

Condition values are normalized according to their declared numeric, boolean, or text type. There is no executable expression, PHP, SQL, or free-form rule language.

## Persistence and commands

`WordPressPriceBookRepository` provides draft creation, ID/version lookup, paginated/status-filtered listing, optimistic draft save, safe next version lookup, and a read-only active-book check.

`WordPressPricingRuleRepository` provides insert, ID lookup, deterministic per-book listing, optimistic update, draft-only delete, per-book code uniqueness, and per-book counts. Database unique indexes remain the final authority. Typed exceptions distinguish duplicate version/code, missing rows, stale writes, and cross-book access.

Application commands and handlers are limited to:

- `CreateDraftPriceBook`
- `UpdateDraftPriceBookSettings`
- `AddDraftPricingRule`
- `UpdateDraftPricingRule`
- `ToggleDraftPricingRule`
- `DeleteDraftPricingRule`

Handlers receive typed input and use the existing transaction/clock ports where multiple writes are involved. They do not read WordPress capabilities or the current user. There is no activation or calculation command.

## Device catalog adapter

`WordPressDeviceCatalogReader` reads the existing `appleklinika_device_catalog` WordPress option through a read-only application port. It returns only active iPhone model keys and display labels in deterministic order. It never writes inventory data, creates products, or reads WooCommerce prices.

The inventory source does not currently expose a stable canonical storage list. The Phase 2A admin therefore uses the documented fixed numeric V1 list `32, 64, 128, 256, 512, 1024` GB and stores the selected integer through `StorageCapacity`. Arbitrary product-title or display-string parsing is not used.

## WordPress admin

The plugin grants these capabilities to `administrator` and `shop_manager` while active:

- `ak_buyback_view_diagnostics`
- `ak_buyback_manage_price_books`

Customers and subscribers do not receive pricing access. Every page and mutation checks the pricing capability, and every POST requires the plugin nonce. POST/redirect/GET and a short replay token protect creation from browser resubmission. Input is sanitized, output is escaped, and raw SQL errors are never rendered.

Admin pages:

```text
/wp-admin/admin.php?page=appleklinika-buyback
/wp-admin/admin.php?page=appleklinika-buyback-price-books
```

The first page remains read-only diagnostics. The second page lists price books and allows creating and editing drafts, adding/editing/toggling/deleting draft rules, and reading active or retired books without mutation controls. It clearly states that drafts are not live. The admin-only CSS and JavaScript are enqueued only on the price-book screen.

There is no whole-book delete, clone, activate, retire, request link, public REST route, or public AJAX action. Phase 2B1 subsequently adds a transient, admin-only draft calculation preview without changing the Phase 2A schema or lifecycle.

## Verification and cleanup

The real WordPress/MariaDB acceptance suite is:

```bash
make test-buyback-pricing-admin
```

It verifies migration from `1.0.0` to `1.1.0`, idempotency, exact columns/indexes, InnoDB, unchanged Phase 1 table structures, domain boundaries, rule shapes, repository behavior, optimistic locking, authorization, nonces, replay protection, catalog isolation, draft-only behavior, no-live guarantees, deactivation retention, and complete cleanup.

All generated labels use `QA-PRICEBOOK-{run-id}`. Cleanup deletes only those exact price books and their rules. The suite records before/after counts for price books, price rules, requests, snapshots, and events, plus a raw legacy user-meta hash. It must pass twice consecutively.

Regression gates remain:

```bash
make test-buyback-legacy
make test-buyback-persistence
make test-buyback-domain
make test-buyback
```

Repository-wide `make test` and `make quality` remain placeholders and are reported as such.

## Explicit non-goals and Phase 2B

Phase 2A adds no public calculator, questionnaire, preliminary/final offer, customer or account flow, inspection, payout, courier, notification, trade-in credit, WooCommerce cart/order/checkout integration, inventory mutation, price seeding, competitor scraping, or legacy import.

Phase 2B1 now provides a separately documented pure calculation layer and admin-only draft preview. Price-book activation, offer traceability, immutable request linkage, and every public/live use remain deferred to a later reviewed phase.
