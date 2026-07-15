# Apple Klinika Buyback Phase 1A

## Purpose

Phase 1A establishes a standalone plugin boundary for the future buyback domain. It creates versioned persistence, migration infrastructure, read-only diagnostics, and regression checks without exposing a public workflow or importing legacy records.

The implementation follows the approved [Buyback V1 blueprint](appleklinika-buyback-v1-blueprint.md). The plugin namespace is `AppleKlinika\\Buyback` and the code is separated into Domain, Application, Infrastructure, and Interfaces layers.

## Component Boundaries

- `src/Domain`: schema version value object.
- `src/Application`: migration contract, diagnostics query/handler/report, and read ports.
- `src/Infrastructure`: WordPress schema migration, schema inspection, activation/deactivation, capabilities, environment reader, and legacy detector.
- `src/Interfaces`: the read-only WooCommerce admin diagnostics screen.
- `migrations`: deterministic ordered migration registry.
- `tests`: Docker-backed WordPress smoke test.

WordPress hooks are limited to plugin boot, activation/deactivation, admin menu registration, and an admin error notice. Domain decisions and diagnostics assembly stay outside hook callbacks and admin rendering.

## Versioning and Migration

- Plugin version: `0.1.0`
- Code schema version: `1.0.0`
- Installed schema option: `appleklinika_buyback_schema_version`

The migration runner:

1. Acquires an atomic option-based migration lock.
2. Reads the installed semantic schema version.
3. Loads the deterministic migration registry.
4. Runs only migrations newer than the installed version.
5. Verifies all required tables, columns, and indexes.
6. Advances the installed version only after success.
7. Stores a sanitized failure message without dropping or rewriting existing data.
8. Always releases the lock.

Running the same migration repeatedly is safe. Table creation and repair use WordPress `dbDelta()`, while business rows are never seeded by activation or migration.

## Core Tables

Runtime table names use the current WordPress prefix.

### `ak_buyback_requests`

Stores the current request projection and external references. Important indexes cover request number, legacy reference, customer/status, status/update time, model/status, and WooCommerce order ID.

### `ak_buyback_snapshots`

Stores immutable JSON snapshots associated with requests. Indexes support request history, request/type lookup, and checksum lookup.

### `ak_buyback_events`

Stores append-only domain events and audit context. Indexes support request chronology, event-type lookup, and idempotency keys.

No foreign keys are introduced in Phase 1A. Relationship columns remain indexed, avoiding portability issues with WordPress table-prefix and `dbDelta()` conventions.

## Diagnostics and Authorization

The plugin grants `ak_buyback_view_diagnostics` to administrator and shop manager roles on activation and revokes it on deactivation. The read-only page is registered under:

```text
WooCommerce > Buyback diagnostics
```

It reports:

- plugin, code schema, and installed schema versions;
- migration state and last recorded migration error;
- table existence, row counts, missing columns, and missing indexes;
- the active theme and whether WooCommerce is active;
- sanitized legacy user/record counts and record identifiers/markers.

It contains no form, import, repair, or mutation action.

## Legacy Safety

The legacy detector reads only the existing `appleklinika_buyback_records` user meta. It does not deserialize data into the new schema, import records, alter user meta, or expose full record payloads in diagnostics.

The smoke test hashes raw user-meta rows before activation and confirms byte/value-equivalent rows after migration, diagnostics, deactivation, and reactivation. The known local demo ID `ak-buyback-account-test-profile-v1` must be detected and must not appear in the new request table.

## Verification

The real Phase 1A integration check is:

```bash
make test-buyback
```

It bootstraps the local WordPress runtime and verifies activation, migration, second-run idempotency, schema health, diagnostics authorization, legacy read-only behavior, deactivation persistence, and final reactivation. The generic `make test` and `make quality` commands are still placeholders and are not treated as equivalent test coverage.

## Explicitly Deferred

- Public routes or forms.
- Quote calculators and pricing rules.
- Offers, inspections, payouts, or courier flows.
- Trade-in credit and WooCommerce order integration.
- Legacy migration/import.
- Admin write controls.
- Public/account UI replacement.
