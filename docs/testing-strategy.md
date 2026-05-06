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

The test commands exist in the Makefile, but actual test tooling is still deferred.

The first behavior that should receive unit coverage is grade validation:

- Invalid grade values should be rejected.
- Allowed grade values should be accepted.
- Product condition save behavior should preserve the manually selected overall grade.

Device catalog behavior should later receive coverage for:

- Default iPhone model seed data.
- Hungarian color labels.
- Admin-added custom models.
- Product save behavior preserving selected model and color values.

Product selector behavior should receive browser-level regression coverage for:

- The local iPhone 13 Pro selector matrix creates real WooCommerce products by SKU without duplication.
- Color, storage, grade, and battery clicks keep the selected border on the clicked option.
- Matching selector combinations update the visible product data and add-to-cart product ID without a full page reload.
- Battery extra clicks update the displayed price and cart extra field without changing the selected WooCommerce product.
- Product gallery thumbnail and main image behavior still works after a smooth product switch.

Shop/listing behavior should receive browser-level regression coverage for:

- Footer and header information links open real WordPress pages and never use empty or `#` hrefs.
- Product cards link to real WooCommerce product pages.
- Shop filters narrow the WooCommerce product query by storage, condition, model, and price range.
- SIM filtering uses the `_appleklinika_sim_config` product meta field instead of frontend-only placeholder data.
- Product cards keep equal visual height and fixed image ratio across desktop, tablet, and mobile layouts.

After every frontend or UI implementation, `docs/ui-qa-checklist.md` must be updated with manual browser QA results. Items must stay `NOT TESTED` unless they were actually verified in the browser.
