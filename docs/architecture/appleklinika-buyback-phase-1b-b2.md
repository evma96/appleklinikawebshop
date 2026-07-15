# Apple Klinika Buyback Phase 1B-B2

Status: accepted and completed

Date: 2026-07-15

## Scope

Phase 1B-B2 adds the final read-only compatibility layer for the legacy `appleklinika_buyback_records` user meta. It reads and validates source records, checks deterministic references against the new request repository, and exposes a CLI-only dry-run report. It never imports, updates, deletes, repairs, or normalizes stored source data.

Plugin version is `0.4.0`. Code and installed schema versions remain `1.0.0`; no migration or table change belongs to this phase.

## Shared legacy source

`WordPressLegacyBuybackRecordSource` enumerates owning user IDs from the existing user-meta key and reads values with `get_user_meta()`, allowing WordPress to perform its normal safe unserialization. It returns immutable records and explicit source issue codes for malformed containers or non-scalar fields. The owning WordPress user ID is authoritative; the duplicated legacy customer email is compared privately and is never included in report output.

The Phase 1A `LegacyBuybackDetector` now reuses this record source. There is no parallel SQL detector and no legacy write API.

## Parsing and classifications

The pure application parser validates bounded safe IDs, plain-text device/condition/marker fields, `0–100%` battery values, integer HUF display amounts, UTC `YYYY-MM-DD` dates, and explicitly known statuses. The currently supported status mapping is:

- `Bevizsgálás alatt` → `inspecting`.

Unknown statuses and unresolved catalog model keys are never guessed. Every row receives exactly one classification:

- `importable`: structurally complete and model-resolved;
- `needs_manual_mapping`: structurally valid but requires explicit model/status/customer review;
- `invalid`: required data is missing, malformed, unsafe, or duplicated within the source;
- `already_present`: a valid deterministic reference already exists in the new request repository.

The production B2 configuration intentionally uses `NullLegacyModelResolver`, so display text cannot become a permanent model key without a future approved catalog adapter.

## Deterministic reference and privacy

References use:

```text
user-meta:{owning_user_id}:{legacy_record_id}
```

The record ID is bounded to 120 safe ASCII identifier characters, keeping the complete reference within the existing 191-byte schema column. The owning user ID prevents collisions when two users have the same source record ID. References contain no email, phone, address, IBAN, or other customer data.

Public report rows expose only the owner user ID, sanitized legacy ID/marker/device label, parsed battery and HUF amounts, mapped status, deterministic reference, classification, safe issue codes, and the read-only collision result. Raw serialized payloads and embedded customer data never leave the adapter.

## WP-CLI dry run

The command is registered only when `WP_CLI` is available:

```bash
wp ak-buyback legacy-report
wp ak-buyback legacy-report --format=json
wp ak-buyback legacy-report --format=json --user-id=2
wp ak-buyback legacy-report --format=json --strict
```

Supported options:

- `--format=table|json`, default `table`;
- `--user-id=<positive integer>`;
- `--strict`.

Normal mode exits zero when report generation succeeds, even when manual mapping is needed. Strict mode exits non-zero when any row is `invalid` or `needs_manual_mapping`. JSON has stable keys and record ordering and deliberately has no generated timestamp. There is no write/import flag.

## Known demo record

The local record `ak-buyback-account-test-profile-v1` with marker `account-test-profile-v1` is detected. It is expected to require manual model mapping until a separately approved catalog resolver exists. Running the report creates no new request and leaves the source meta byte-equivalent.

## Verification

Run twice:

```bash
make test-buyback-legacy
make test-buyback-legacy
```

The 50-assertion Docker-backed suite covers parser boundaries, classifications, deterministic references, CLI registration and JSON output, strict behavior, PII redaction, repeatability, the known demo record, filtered reports, plugin/schema state, all three table counts, relevant plugin options, and a raw source-meta hash. Its WP-CLI-compatible harness is necessary because the current local WordPress Apache image does not bundle the `wp` executable; it invokes the same registered command object and arguments.

Regression gates remain:

```bash
make test-buyback-persistence
make test-buyback-domain
make test-buyback
```

The repository-wide `make test` and `make quality` targets are placeholders and are not represented as real coverage.

## No-import guarantee and future scope

B2 contains no `update_user_meta()`, `delete_user_meta()`, request insert/update, event append, snapshot write, migration, REST/AJAX/public route, admin mutation, WooCommerce integration, pricing, offer, inspection, payout, or courier behavior. A future controlled migration is a separate phase requiring an explicit model resolver, import command, idempotent write policy, audit evidence, dry-run approval, and rollback plan.
