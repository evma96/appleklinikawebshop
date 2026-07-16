# Apple Klinika Buyback

Phase 1A provides the schema and read-only diagnostics foundation for the Apple Klinika buyback system. Phase 1B-A adds the pure request domain model. Phase 1B-B1 connects that domain to WordPress persistence, and Phase 1B-B2 adds read-only legacy reporting. Phase 2A adds versioned draft price books and their admin. Phase 2B1 adds a deterministic pure-PHP pricing engine and a transient, capability-gated draft preview. The plugin still exposes no public buyback workflow or live pricing.

## Scope

- Versioned, idempotent schema migrations.
- Three core tables for requests, immutable snapshots, and append-only events.
- A read-only WooCommerce diagnostics screen.
- A read-only detector for the legacy `appleklinika_buyback_records` user meta.
- Activation, deactivation, migration, capability, and legacy-integrity smoke tests.
- Immutable request, customer, money, service-mode, handover, status, actor, and aggregate-version types.
- A complete actor-, mode-, expiry-, evidence-, settlement-, and trade-in-aware status-transition policy.
- A minimal `BuybackRequest` aggregate that controls status changes, optimistic version increments, and pending domain events.
- Repository, request-number, clock, transaction, and event-publishing application ports without WordPress implementations.
- A deterministic pure-PHP domain test suite that does not bootstrap WordPress or access the database.
- Immutable category, model-key, device-name, and request-source types required by the request schema.
- A strict WordPress row mapper and repository with database-generated IDs, typed lookup/pagination, duplicate checks, and expected-version updates.
- An explicit WordPress transaction manager, UTC system clock, and bounded collision-aware `AKB-YYYYMMDD-XXXXXX` request-number generator.
- Transactional `buyback_status_changed` persistence with deterministic idempotency keys and no customer payload by default.
- Internal create/transition application handlers with no public interface registration.
- A rerunnable real MariaDB persistence suite that cleans every exact QA marker and proves row counts plus legacy user meta are unchanged.
- A typed read-only source for `appleklinika_buyback_records`, strict field parsing, deterministic PII-free references, and explicit dry-run classifications.
- A CLI-only `wp ak-buyback legacy-report` command with deterministic table/JSON output, optional user filtering, and strict validation mode.
- A pure pricing domain with draft-only price books, typed rule definitions, and strict rule-shape validation.
- WordPress repositories for price books and rules with optimistic locking and deterministic ordering.
- A read-only adapter for the existing Apple Klinika iPhone device catalog.
- A WooCommerce admin page for creating/editing draft price books and draft rules.
- A real WordPress/MariaDB pricing/admin suite with complete QA cleanup and no-live-behavior checks.
- A canonical condition registry shared by pricing-rule validation, admin rule fields, preview fields, and calculation input normalization.
- A deterministic integer-money pricing engine with immutable outcomes, matched rules, and ordered breakdown lines.
- A read-only application preview that calculates all four service modes without persisting any calculation or offer data.
- A real pricing-engine domain/integration suite with authorization and complete QA cleanup.

Not included through Phase 2B1: public routes/calculators, offers, price-book activation, inspections, payouts, shipping integrations, trade-in credit implementation, WooCommerce order integration, request linkage, or legacy import.

## Versions

- Plugin version: `0.6.0`
- Core schema version: `1.1.0`

The installed schema version is stored in the `appleklinika_buyback_schema_version` WordPress option. Failed migrations are recorded in `appleklinika_buyback_migration_error`; the installed version is advanced only after a successful migration.

## Tables

The WordPress table prefix is applied at runtime:

- `{prefix}ak_buyback_requests`
- `{prefix}ak_buyback_snapshots`
- `{prefix}ak_buyback_events`
- `{prefix}ak_buyback_price_books`
- `{prefix}ak_buyback_price_rules`

The Phase 1A schema does not add foreign keys so WordPress `dbDelta()` remains portable across supported local and hosting environments. Relationship columns are indexed explicitly.

## Diagnostics

Administrators and shop managers receive the custom `ak_buyback_view_diagnostics` capability while the plugin is active.

They also receive `ak_buyback_manage_price_books` for the draft pricing admin. Customers and subscribers receive neither capability.

Open:

```text
WooCommerce > Buyback diagnostics
```

Direct admin URL:

```text
/wp-admin/admin.php?page=appleklinika-buyback
```

The screen reports plugin/schema versions, table health, row counts, missing columns or indexes, and a sanitized legacy-record summary. It has no write controls and does not import legacy data.

The separate draft price-book admin is available at:

```text
/wp-admin/admin.php?page=appleklinika-buyback-price-books
```

It can create/update draft price books and create/update/toggle/delete draft rules. A draft edit page also provides a transient **Kalkulációs előnézet** for all four service modes. Active and retired records are read-only. There is no activate, retire, clone, request-link, persistent offer, or public action. See [Phase 2A architecture](../../../../docs/architecture/appleklinika-buyback-phase-2a.md) and [Phase 2B1 architecture](../../../../docs/architecture/appleklinika-buyback-phase-2b1.md).

## Verification

Run the real pure-domain test suite:

```bash
make test-buyback-domain
```

This command executes the plugin-local deterministic PHP runner without loading WordPress. It covers all supported value-object codes, all 31 allowed transition edges, all 410 unsupported status pairs, actor restrictions, service-mode restrictions, conditional guards, aggregate version/event behavior, and application-port signatures.

Run the real Docker-backed smoke test:

```bash
make test-buyback
```

The smoke test uses WordPress activation/deactivation APIs, runs the migration twice, checks schema health and authorization, proves that the known legacy demo record is detected but not imported, and compares a raw legacy user-meta hash before and after the full run.

Run the real WordPress/MariaDB persistence suite:

```bash
make test-buyback-persistence
```

It verifies insert/reconstitution, generated identities, request-number collision handling, duplicate rejection, typed queries, deterministic pagination, optimistic locking, missing-versus-stale failures, UTC timestamps, atomic request/event commits, rollback, event idempotency, and complete QA-fixture cleanup. Run it twice consecutively before accepting persistence changes.

Run the read-only legacy dry-run suite:

```bash
make test-buyback-legacy
```

The suite covers field parsers, deterministic references, every classification, CLI registration/JSON output, strict exit behavior, PII redaction, repeatability, the known demo record, table counts, option integrity, and a raw legacy-meta hash. It creates no user or buyback fixture and must pass twice consecutively.

Run the real draft pricing/admin suite twice:

```bash
make test-buyback-pricing-admin
make test-buyback-pricing-admin
```

It verifies the `1.0.0` to `1.1.0` migration, exact pricing schema, draft-only domain and repository behavior, optimistic locking, capability/nonce checks, admin commands, read-only catalog access, absence of live calculation/public routes, and complete cleanup with unchanged Phase 1 counts and legacy hash.

Run the real deterministic pricing-engine suite twice:

```bash
make test-buyback-pricing-engine
make test-buyback-pricing-engine
```

It verifies canonical input normalization, every comparison operator, exact base-price resolution, hard-reject/manual-review precedence, ordered deductions and basis-point multipliers, all four service modes, minimum policy, half-up rounding, deterministic shuffled-rule results, no mutation, preview authorization, repository-backed preview, and complete cleanup. The preview reads stored drafts and rules but creates no request, snapshot, event, offer, option, user meta, or WooCommerce record.

When a real WP-CLI runtime is available, generate a report with:

```bash
wp ak-buyback legacy-report --format=table
wp ak-buyback legacy-report --format=json --user-id=2
wp ak-buyback legacy-report --format=json --strict
```

Supported formats are `table` and `json`. Normal mode returns zero when reporting succeeds. Strict mode returns non-zero when any record is invalid or needs manual mapping. The command intentionally has no import, update, repair, delete, or migration option.

The repository-wide `make test` and `make quality` commands remain placeholders and are reported as such. The plugin-local domain and Phase 1A smoke commands are real test suites.

## Deactivation and Rollback

Deactivate the plugin from the standard WordPress Plugins screen. Deactivation removes the diagnostics and price-book-management capabilities and stops the plugin runtime hooks, but deliberately keeps all Phase 1 and Phase 2A tables, schema options, and stored data intact. Phase 2B1 adds no schema. Reactivating the plugin runs the same version check and safe migration path again. The plugin has no destructive uninstall routine; any future data removal requires a separately reviewed migration or uninstall design.

## Data Ownership

Phase 2B1 does not replace the theme-owned `Beszámítás` account endpoint and does not modify products, orders, users, checkout, cart, account output, stock, inventory catalog, or legacy buyback records. Stored draft pricing is consumed only by an authorized transient admin preview. Future activation, public calculation, request linkage, offer persistence, or legacy import requires a separately reviewed phase.
