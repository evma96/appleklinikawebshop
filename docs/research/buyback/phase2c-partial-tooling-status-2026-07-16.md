# Phase 2C Partial Benchmark Tooling Status

Status: **INCOMPLETE WIP - DO NOT USE TO IMPORT A PRICE BOOK**

This checkpoint preserves the stopped Phase 2C research and implementation work for later forensic review. The work was intentionally stopped before full validation because the available benchmark evidence does not meet the configured two-independent-source threshold across the Apple Klinika iPhone catalog.

## Safety State

- No benchmark price book draft was created.
- No price book was activated, modified, or retired.
- Protected price book ID 31 remains `noj`, `draft`, aggregate version `0`, with zero rules.
- The database currently contains zero active HUF price books.
- No importer or seed command was executed.
- No external source request was made during this preservation task.

## Preserved Research

### ShowMe

- 36 public iPhone models.
- 111 advertised model/storage combinations.
- Public question and option structure recovered from rendered and embedded public data.
- Two direct iPhone 13 Pro 128 GB preliminary observations retained.
- Snapshot SHA-256: `8273bdc6067a535ea8b8c80d3feddabd4d85ef291986ec195033bf1c4ce16a1e`.

### NorbiPhone

- 27 public iPhone models.
- 76 advertised model/storage combinations.
- Ten combinations priced before the public endpoint returned HTTP 429 at iPhone 17 / 512 GB.
- Question, answer, payout-mode, and public API semantics retained.
- 22 raw observations retained, including payout-mode variants and direct reference observations.
- Snapshot SHA-256: `94a671acc7dbe149e0b537d4f32b7c54df3eac032bdc3abf7e44c4ae34b5e5d8`.

### Rejoy

- 39 public iPhone models.
- 121 advertised model/storage combinations.
- Seven public question steps retained.
- 39 public maximum teaser observations retained as non-comparable evidence.
- Standardized model/storage offers remain unavailable because the result is behind an email gate.
- The per-model `maximum_teaser_amount_minor` summary values use an inconsistent unit scale, while `raw_reference_observations[].amount_minor` contains the intended HUF values. The snapshot is preserved unchanged and must be normalized only in a separate reviewed task.
- Snapshot SHA-256: `b04e93e4716666fa627b94a6bdc6d91b563a130553aa5b2e00080914671146b2`.

## Validation Performed

- All three research files are valid JSON.
- Required source, URL, capture timestamp, model coverage, storage coverage, question-tree, observation, and limitation fields are present.
- Observations contain explicit source and evidence information.
- No cookies, session tokens, authorization headers, customer email addresses, customer phone numbers, customer addresses, or account identifiers were detected.
- All 24 new or modified Buyback PHP files pass `php -l` under the project WordPress PHP 8.2 container.
- The benchmark Make target and `ak-buyback pricebook-seed` CLI registration exist.
- Namespace-to-file paths match the plugin's PSR-4-like autoloader convention.

## Incomplete and Unverified Tooling

- The canonical Apple Klinika model/storage matrix was not generated.
- No normalized proposal or importable benchmark manifest was completed.
- Evidence observation IDs are not fully cross-checked against source snapshot records.
- Exact manifest setting enforcement, unknown-field rejection, duplicate mode-rule rejection, and deep evidence comparability checks are incomplete.
- Safe runtime access to research files from the Docker-mounted WordPress plugin is unresolved.
- Actual WP-CLI command availability and importer execution were not verified.
- The benchmark test file exists but was not executed.
- No admin preview was run.
- Plugin bootstrap still contains the existing migration, plugin-version option, and capability-grant write paths. Merely registering the plugin is therefore not a guaranteed read-only operation.

## Temporary Recovery Scripts

The following temporary scripts are intentionally preserved on the isolated WIP branch for forensic and recovery purposes only:

- `.tmp-build-iphone-matrix.sh`
- `.tmp-build-norbi-snapshot.sh`
- `.tmp-norbi-harvest.sh`
- `.tmp-normalize-rejoy-snapshot.sh`

They are not production tooling and must not be run without a separate review.

## Required Next Step

Any continuation must start from a separate reviewed task. The smallest safe continuation is a static Phase 2C Lite pass that validates the preserved snapshots, completes the canonical model/storage matrix, hardens manifest and evidence validation, and runs isolated tests without crawling competitors or creating a price book.
