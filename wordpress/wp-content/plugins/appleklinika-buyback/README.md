# Apple Klinika Buyback

Phase 1A provides the persistence and read-only diagnostics foundation for the future Apple Klinika buyback system. It intentionally does not expose a public buyback workflow.

## Scope

- Versioned, idempotent schema migrations.
- Three core tables for requests, immutable snapshots, and append-only events.
- A read-only WooCommerce diagnostics screen.
- A read-only detector for the legacy `appleklinika_buyback_records` user meta.
- Activation, deactivation, migration, capability, and legacy-integrity smoke tests.

Not included in Phase 1A: public routes, calculators, forms, offers, pricing, inspections, payouts, shipping, trade-in credit, WooCommerce order integration, or legacy import.

## Versions

- Plugin version: `0.1.0`
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

Run the real Docker-backed smoke test:

```bash
make test-buyback
```

The smoke test uses WordPress activation/deactivation APIs, runs the migration twice, checks schema health and authorization, proves that the known legacy demo record is detected but not imported, and compares a raw legacy user-meta hash before and after the full run.

The repository-wide `make test` and `make quality` commands remain placeholders and are reported as such.

## Deactivation and Rollback

Deactivate the plugin from the standard WordPress Plugins screen. Deactivation removes the diagnostics capability and stops the plugin runtime hooks, but deliberately keeps the Phase 1A tables, schema options, and stored data intact. Reactivating the plugin runs the same version check and safe migration path again. Phase 1A has no destructive uninstall routine; any future data removal requires a separately reviewed migration or uninstall design.

## Data Ownership

Phase 1A does not replace the theme-owned `Beszámítás` account endpoint and does not modify products, orders, users, checkout, cart, account output, pricing, stock, or legacy buyback records.
