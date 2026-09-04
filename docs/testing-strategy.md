# Testing Strategy

## Goals

- Protect business rules around unique used-phone products.
- Keep WordPress and WooCommerce integration behavior predictable.
- Avoid fragile tests that depend on live external services.

## Test Types

- Unit tests for domain and application logic.
- Integration tests for WordPress and WooCommerce adapters.
- Contract tests for REST APIs when APIs are introduced.

## External Calls

Tests must not make live calls to:

- Payment providers.
- External APIs.
- Online scraping targets.

Mocks, stubs, and local fixtures are required.

## Current Status

### Checkout partial-update runtime contract

`tests/checkout-blocks-state-contract.js` in the theme compares native keyboard input with Playwright fill input on isolated local guest sessions. It checks company, e-mail, both phone values and billing mode in the UI/Woo stores, matches Store API replies to their originating request payloads, and rejects HTTP errors including inner batch failures. Three country-driven React region-control recreations are followed by Step 2 → 3 → 4; country-dependent postcodes are explicitly re-entered after returning to Hungary. This proves updates during region-control recreation, not forced remounting of every contact input or order placement.

The address-identity adapter treats omitted name keys as unchanged only during `/wc/store/*/checkout` PUT/PATCH updates. Explicit blanks remain invalid, and final POST placement still validates all required recipient names. The regression first reproduced `rest_invalid_param` with missing-recipient errors on additional-fields-only PUT requests on `ab27b41e9f38a6a08cf60b2f5893e49b91fb9d5b`; both keyboard and fill paths failed before the production correction. `make test-order-company-contract` also checks omitted versus explicitly empty names and final-POST protection.

Run the browser script with Node and Playwright available (`NODE_PATH` may point to the installed Playwright packages). Its report records the uniquely named test run's exact draft IDs for application-level cleanup and empties each isolated cart before closing it. Never delete drafts by a broad email/name pattern. No real payment or final order is submitted by this regression. Test-server full-order acceptance remains a separate gate.

The test commands exist in the Makefile, but actual test tooling is still deferred.

The focused local order-finalization contract is covered by `make test-order-finalization` and its flow, presentation, e-mail, and lifecycle aliases. It creates isolated logged-in and guest BACS fixtures, renders the production order and e-mail templates without dispatching mail, verifies stock reduction/restoration and immutable HPOS snapshots, and removes every fixture before it completes.

The first behavior that should receive unit coverage is grade validation:

- Invalid grade values should be rejected.
- Allowed grade values should be accepted.
- Product condition save behavior should preserve the manually selected overall grade.

Device catalog behavior should later receive coverage for:

- Default iPhone, iPad, MacBook, and Apple Watch model seed data.
- Hungarian color labels.
- Admin-added custom models.
- Product save behavior preserving selected model and color values.
- iPad and Apple Watch connectivity restrictions.
- Apple Watch model-specific case size, case material, and color pairings.

Product selector behavior should receive browser-level regression coverage for:

- The local iPhone 13 Pro selector matrix creates real WooCommerce products by SKU without duplication.
- Color, storage, grade, and battery clicks keep the selected border on the clicked option.
- Matching selector combinations update the visible product data and add-to-cart product ID without a full page reload.
- Battery extra clicks update the displayed price and cart extra field without changing the selected WooCommerce product.
- Product gallery thumbnail and main image behavior still works after a smooth product switch.

Shop/listing behavior should receive browser-level regression coverage for:

- Footer and header information links open real WordPress pages and never use empty or `#` hrefs.
- Product cards link to real WooCommerce product pages.
- Shop filters narrow the WooCommerce product query by category-specific model, storage, condition, price, color, connection, and hardware metadata.
- iPhone SIM filtering uses the `_appleklinika_sim_config` product meta field instead of frontend-only placeholder data.
- iPad, MacBook, and Apple Watch category filters only show data-backed filters relevant to that product family.
- Product cards keep equal visual height and fixed image ratio across desktop, tablet, and mobile layouts.

After every frontend or UI implementation, `docs/ui-qa-checklist.md` must be updated with manual browser QA results. Items must stay `NOT TESTED` unless they were actually verified in the browser.
