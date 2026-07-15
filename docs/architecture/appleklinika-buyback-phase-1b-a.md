# Apple Klinika Buyback Phase 1B-A

Status: accepted and completed

Date: 2026-07-15

## Scope

Phase 1B-A adds the pure PHP domain foundation for buyback requests. It does not add public, account, checkout, admin-operation, pricing, inspection, courier, payout, or WooCommerce integration behavior.

Plugin version is `0.2.0`. The code and installed database schema remain `1.0.0`; no migration or table change belongs to this phase.

## Domain model

The `AppleKlinika\Buyback\Domain\Buyback` namespace contains:

- `BuybackRequestId`, `RequestNumber`, `CustomerId`, and `LegacyReference` identities;
- `ServiceMode`, `HandoverMethod`, and `HandoverMethodPolicy`;
- `BuybackStatus`, `TransitionContext`, and `StatusTransitionPolicy`;
- the `BuybackRequest` aggregate;
- `Event\BuybackStatusChanged`.

The `Domain\Shared` namespace contains `Money`, `ActorType`, `AggregateVersion`, and the `DomainEvent` contract. Money is stored as a non-negative integer and an uppercase three-letter currency code. Cross-currency operations and negative offer results fail explicitly.

The exception hierarchy is rooted at `BuybackDomainException` and includes value-object validation, transition, currency, aggregate-operation, and stale-version failures. Messages contain operational identifiers only, not customer data or SQL.

## Transition policy

`StatusTransitionPolicy` is a pure domain service. Its matrix has 31 allowed status edges across 21 status codes. It validates:

- customer, staff, and system actor permissions;
- courier compatibility of the selected service mode;
- payout versus trade-in settlement paths;
- final-offer expiry;
- evidence for a staff-recorded customer decision;
- settlement reference before `paid`;
- credit and linked Woo order references before `trade_in_applied`;
- same-status, post-custody cancellation, and terminal-state restrictions.

The transition context carries only safe decision flags, current/expiry time, actor type, and an optional correlation ID. It never carries IBAN, IMEI, addresses, WordPress users, Woo objects, or database handles.

## Aggregate behavior

`BuybackRequest` can be created as a valid draft or reconstituted by a future repository. Draft-only mutations attach a customer and select compatible service/handover choices. Status changes are available only through `transitionTo()` with the domain policy; there is no arbitrary status setter.

Every accepted mutation increments `AggregateVersion`. Every accepted status transition records one immutable `BuybackStatusChanged` event. The application layer can release and clear pending events after persistence. Invalid transitions leave state, version, and pending events unchanged.

## Application ports

Phase 1B-A defines interfaces only:

- `BuybackRequestRepository` with typed identity/status/customer queries, pagination DTOs, expected-version save, and duplicate checks;
- `RequestNumberGenerator`;
- `Clock`;
- `TransactionManager`;
- `DomainEventPublisher`.

There is no WordPress persistence, clock, transaction, or event-store adapter in this phase.

## Verification

Run the deterministic domain suite:

```bash
make test-buyback-domain
```

It runs inside the repository PHP container but does not bootstrap WordPress or access the database. Current coverage includes:

- 639 assertions;
- 31 allowed transition cases;
- 410 rejected unsupported status pairs;
- 93 actor-transition cases;
- 4 explicit service-mode restriction cases;
- 7 conditional-guard cases;
- aggregate creation, mutation versioning, event recording/release, and port signatures.

Run the Phase 1A regression suite separately:

```bash
make test-buyback
```

The global `make test` and `make quality` targets remain placeholders; they must not be represented as real coverage.

## Explicit non-goals

Phase 1B-A does not add repository implementations, database writes, new schema, legacy import, customer forms, public routes, staff screens, price books, preliminary/final offer payloads, inspections, payout instructions, courier bookings, trade-in credit records, notifications, or WooCommerce cart/order behavior.

## Next phase: 1B-B

Phase 1B-B may add application commands/handlers and WordPress repository adapters behind these ports, with optimistic locking and transactional event persistence. That work requires a separately reviewed schema compatibility and integration-test plan. It must not silently activate public functionality or migrate legacy records.
