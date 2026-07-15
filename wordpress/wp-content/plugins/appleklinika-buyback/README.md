# Apple Klinika Buyback

Phase 1A provides the schema and read-only diagnostics foundation for the future Apple Klinika buyback system. Phase 1B-A adds the pure domain model. Phase 1B-B1 connects that domain to the existing WordPress tables with optimistic locking and transactional event persistence. The plugin intentionally does not expose a public buyback workflow.

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

Not included through Phase 1B-B1: public routes, calculators, forms, offers, pricing, inspections, payouts, shipping integrations, trade-in credit implementation, WooCommerce order integration, operational admin UI, or legacy import.

## Versions

- Plugin version: `0.3.0`
- Core schema version: `1.0.0`

The installed schema version is stored in the `appleklinika_buyback_schema_version` WordPress option. Failed migrations are recorded in `appleklinika_buyback_migration_error`; the installed version is advanced only after a successful migration.

## Tables

The WordPress table prefix is applied at runtime:

- `{prefix}ak_buyback_requests`
- `{prefix}ak_buyback_snapshots`
- `{prefix}ak_buyback_events`

The Phase 1A schema does not add foreign keys so WordPress `dbDelta()` remains portable across supported local and hosting environments. Relationship columns are indexed explicitly.

## Diagnostics

Administrators and shop managers receive the custom `ak_buyback_view_diagnostics` capability while the plugin is active.

Open:

```text
WooCommerce > Buyback diagnostics
```

Direct admin URL:

```text
/wp-admin/admin.php?page=appleklinika-buyback
```

The screen reports plugin/schema versions, table health, row counts, missing columns or indexes, and a sanitized legacy-record summary. It has no write controls and does not import legacy data.

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

The repository-wide `make test` and `make quality` commands remain placeholders and are reported as such. The plugin-local domain and Phase 1A smoke commands are real test suites.

## Deactivation and Rollback

Deactivate the plugin from the standard WordPress Plugins screen. Deactivation removes the diagnostics capability and stops the plugin runtime hooks, but deliberately keeps the Phase 1A tables, schema options, and stored data intact. Reactivating the plugin runs the same version check and safe migration path again. Phase 1A has no destructive uninstall routine; any future data removal requires a separately reviewed migration or uninstall design.

## Data Ownership

Phase 1A does not replace the theme-owned `Beszámítás` account endpoint and does not modify products, orders, users, checkout, cart, account output, pricing, stock, or legacy buyback records.
