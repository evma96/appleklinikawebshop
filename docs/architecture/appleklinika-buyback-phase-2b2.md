# Apple Klinika Buyback Phase 2B2

## Status and scope

Phase 2B2 adds governed price-book activation to the existing draft administration and deterministic preview. Plugin version is `0.7.0`; code and installed schema remain `1.1.0`, so this phase runs no database migration.

The phase is internal-only. It adds no public calculator, offer persistence, buyback-request linkage, customer endpoint, REST/AJAX route, shortcode, checkout/order integration, inspection, payout, courier integration, or legacy import.

## Lifecycle and immutability

The only supported transitions are:

```text
draft -> active -> retired
```

- A draft may be edited and its rules managed.
- A ready draft may be activated by the activation handler.
- Activating a new HUF book retires the previous current HUF book inside the same transaction and at the same UTC timestamp.
- Active and retired price books and their rules are read-only.
- There is no generic status setter, standalone retirement, reactivation, cloning, or whole-book delete action.
- Each lifecycle transition increments the optimistic aggregate version exactly once and records the responsible actor and timestamp.

## Activation readiness

`PriceBookActivationReadinessEvaluator` is a pure domain service. `PriceBookActivationReadinessService` supplies the read-only device catalog and returns a report with the book identity/version, validation time, supported configurations, enabled base/adjustment counts, blockers, and warnings.

Activation blockers include:

- unpersisted, non-draft, non-HUF, invalid rounding, or invalid minimum policy;
- rule ownership or rule-shape errors;
- unknown condition keys or unsupported service modes;
- unsupported categories, unknown catalog model keys, or invalid storage values;
- missing or duplicate enabled base prices;
- duplicate enabled mode adjustments;
- unavailable catalog data.

Warnings do not block activation. They identify missing optional per-mode adjustments and absent hard-reject, manual-review, fixed-deduction, or condition-multiplier rule families.

Disabled rules are not part of the live configuration and do not block readiness. The admin renders all blocker and warning codes as explicit Hungarian messages. The activation form appears only when the report is ready.

## Serialized atomic activation

`ActivateDraftPriceBookHandler` requires:

- the price-book management capability at the interface boundary;
- a valid WordPress nonce;
- the expected aggregate version;
- the exact confirmation text `AKTIVÁLOM`;
- a ready draft readiness report.

The application handler then:

1. acquires a bounded MySQL advisory lock scoped to HUF activation;
2. starts one database transaction;
3. reads the target draft and current active HUF rows with `FOR UPDATE`;
4. rechecks optimistic version and readiness;
5. retires the previous current HUF book, if present;
6. activates the target with the same UTC transition timestamp;
7. verifies that exactly one current HUF book exists;
8. commits and releases the advisory lock.

Any failure rolls back the transaction. Lock contention raises a typed busy error, stale writes raise the existing optimistic-lock error, and a write failure cannot leave a half-retired/half-activated state. The advisory lock is released from a `finally` path.

## Current-active resolution

`RepositoryActivePriceBookResolver` resolves the current book by currency and UTC time using:

```text
status = active
effective_from <= now
effective_to IS NULL OR now < effective_to
```

It returns a typed `ResolvedActivePriceBook` with the aggregate, enabled rules, supported configurations, and resolution timestamp. Zero matches raise `NoActivePriceBookException`; multiple matches raise `MultipleActivePriceBooksException`. The resolver never silently chooses one corrupt candidate.

## Admin and diagnostics

The WooCommerce price-book page shows lifecycle status and effective dates. Draft detail pages show activation readiness and, when ready, the protected activation form. POST handling follows Post/Redirect/Get. Active and retired records expose no mutation controls.

The read-only diagnostics page reports one of:

- no current active HUF book;
- the resolved active book identity/version/effective dates/rule and configuration counts;
- a corrupt multiple-active state requiring operator intervention.

No activation path is exposed outside the capability-gated admin page.

## Verification and cleanup

Run the real WordPress/MariaDB suite twice consecutively:

```bash
make test-buyback-pricebook-activation
make test-buyback-pricebook-activation
```

The suite covers lifecycle invariants, readiness, authorization, nonces, confirmation, optimistic locking, advisory-lock contention, first and replacement activation, shared timestamps, resolver time boundaries and zero/one/multiple states, immutable active/retired records, rollback on forced persistence failure, admin output, diagnostics, and lock release.

All generated labels use `QA-ACTIVATION-*`. Cleanup removes only those exact books and rules and verifies unchanged request, snapshot, event, legacy-meta, and pre-existing price-book state. The known pre-existing draft remains untouched.

Regression gates remain:

```bash
make test-buyback-pricing-engine
make test-buyback-pricing-admin
make test-buyback-legacy
make test-buyback-persistence
make test-buyback-domain
make test-buyback
```

Repository-wide `make test` and `make quality` remain placeholders and must be reported as such.

## Next-phase prerequisites

Before any public calculator or customer offer is introduced, a separately reviewed phase must define immutable linkage from every request/offer to the resolved price-book ID and version, snapshot the applied inputs and breakdown, define expiry/recalculation policy, and preserve the no-silent-fallback behavior for missing or corrupt active pricing.
