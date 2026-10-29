# Measured LOCAL storefront query fixes — 2026-10-28

Status: **READY FOR INTEGRATION**, not committed, pushed or deployed.
Branch: `feature/performance-query-fixes`; unchanged HEAD `f3c61e5f64fd687c2ef275e3a7ca5bfe358302ff`.
Previously accepted uncommitted product/model-specification work was retained.
No TEST, production, main, provider, legal or activation change.

## Method

Same six LOCAL routes and methodology as the completed performance audit:
four sequential unauthenticated HTTP requests per route, first as warm-up,
median of the last three. No concurrent benchmark load. SQL counts/durations
are from one separate request with the same temporary request-gated profiling
instrumentation as the earlier audit; they are not TTFB medians. OPcache is warm.
These timings are LOCAL evidence, not an unmeasured production latency promise.

| Page | Median TTFB before / after (ms) | SQL count before / after | SQL duration before / after (ms) |
|---|---:|---:|---:|
| listing | 2364.1 / 106.4 | 156 / 156 | 2277.5 / 28.1 |
| search | 2415.1 / 91.6 | 148 / 148 | 2296.1 / 17.1 |
| iphone | 2611.2 / 154.9 | 234 / 181 | 2405.7 / 20.7 |
| macbook | 10496.2 / 64.1 | 172 / 157 | 11412.3 / 14.4 |
| ipad | 7318.1 / 65.8 | 168 / 157 | 7276.9 / 14.7 |
| apple_watch | 3866.2 / 67.8 | 167 / 157 | 3560.0 / 16.9 |

No final sampled request exceeded 2 seconds. The read-only recommendation
measurement uses the same real current product and same-model pool before/after:

| Family | Recommendation before / after (ms) | Final IDs, same order |
|---|---:|---|
| iphone | 0.4 / 0.3 | 2513, 298, 300, 302, 304, 306 |
| macbook | 10665.7 / 5.8 | 451, 450, 449, 448, 447, 446 |
| ipad | 7160.8 / 5.7 | 383, 472, 471, 470, 469, 468 |
| apple_watch | 3633.1 / 5.6 | 480, 481, 482, 496, 495, 494 |

## Exact changes

All source paths below are under `wordpress/wp-content/plugins/appleklinika-inventory/`.

- `src/Interfaces/DeviceProductHooks.php` delegates marked public queries to
  `src/Infrastructure/WordPress/WooDeviceQueries.php`. Equivalent correlated
  `EXISTS` predicates replace the availability constraint's wide postmeta joins.
  Woo's original visibility, status, category/search, stock and sort clauses remain.
  There is no materialized available-ID list or new persistent cache.
- `src/Infrastructure/WordPress/WooSimilarDevicesQuery.php` passes an explicit
  `appleklinika_recommendation_scope` rather than a raw `meta_query` discarded by
  the installed Woo data store. `WooDeviceQueries::productQuery()` applies that
  scope at the supported CPT filter as an AND group around the family/model OR.
  The six-item limit, native eligibility checks and existing model-first order stay.
- `src/Infrastructure/WordPress/WooProductMetadata.php` batches raw metadata for
  already selected Woo products through Woo's native cache API. Woo still filters
  internal keys, applies read filters, unserializes values and invalidates on save.
  `ProductFrontendDisplay.php` primes the render pool once.
- `WooProtectionCatalog.php` / `Interfaces/Frontend/ProductProtection.php` reuse
  accessory candidates only for display during this request and use the existing
  device object. Cart validation still calls the fresh `forDevice()` path.
  On the dense iPhone page, 55 individual Woo metadata reads became two batch
  reads. The 50 purchase-form renders remain (49 device variants plus the initial
  form), preserving instant selector switching; protection render time was
  73.5 -> 23.3 ms in the instrumented sample.
- `src/Infrastructure/WordPress/ProductDescriptionImages.php` resolves old HEIC
  description references to existing WordPress JPEG derivatives. It matches the
  attachment's original filename, preserves alt/link semantics and responsive
  sizing, and uses the browser-safe full attachment for linked zoom. Unknown
  HEIC without a valid derivative is not requested. No media binaries or stored
  product descriptions were rewritten. `ProductFrontendDisplay.php` invokes it
  only when HEIC markup occurs.
- Focused tests: `tests/performance-queries-local.php` (new) and
  `tests/product-recommendations.php` (supported scope contract).

## Result equivalence and search tie discovery

Listing first-page IDs and order are identical. All four selector pools are
identical (49 / 1 / 2 / 5 products); all four six-item recommendation lists are
identical, in the same order. Selector JSON fields are unchanged except the
intended four-image description source repair; generated quantity IDs are
normalized for comparison. Existing deltas and valid disabled choices remain.

**Search qualification:** the old search ordered by relevance and creation date
without a final unique tie-breaker. Many demo devices share both values. Even a
repeat of the original join query returned a different order inside that tie;
a changed SQL execution plan also moved those tied rows between pages. We did
not pretend the raw first-page ordering was identical. `searchOrder()` retains
all existing sort expressions and appends product ID DESC only to resolve ties
on the marked main product search. With that same deterministic ordering, the
legacy and optimized queries return exactly the same IDs/counts on all four
pages, with no duplicates. No relevance/price/date priority was changed.

## Image and browser evidence

The four old references (attachments 2514–2517) now render their existing JPEGs.
Browser resource evidence: zero HEIC requests; about 76 KB of requested JPEG
encoded content (including a responsive candidate), replacing the previous
2.09 MB of unusable HEIC requests. All four decoded successfully. Original
product gallery, thumbnails and zoom work; a color switch updates the real
product ID, description, price and the associated protection form.

Desktop homepage hero was intentionally left unchanged: its 1184-pixel source
is displayed at 1184 pixels in the measured desktop layout. Selecting a smaller
variant would reduce quality; its approximately 1.97 MB encoded size remains an
asset-weight observation outside this narrowly authorized fix. No broad image
compression, layout or Google Reviews change.

Browser checks covered iPhone/MacBook/iPad/Watch at 1440, 1280, 390 and 320 pixels,
plus listing/search and the manually created iPhone description. No horizontal
overflow or JavaScript console errors. Watch storage remains hidden. Native
cart behavior is additionally covered by guarded in-memory Woo cart integration
tests, not by modifying the owner's existing two-item browser cart.

## Validation and cleanup

**1,887 passing assertions**: 1,499 focused inventory/catalog/selector/media/
protection checks plus 388 offline lifecycle/security/legal-regression checks
from `make test`. The latter only reads the existing legal implementation; it
makes no legal/document changes. 16 family/viewport browser cases are additional
manual/DOM checks, not counted as assertions. Inventory PHP syntax checks passed.
`make quality` ran but explicitly reports no configured linter/static analyzer;
this is not claimed as static-analysis coverage.

Focused breakdown: performance queries/images 121; recommendations 23; selector
options 69; presentation 48; purchase rendering 116; gallery 28; catalog/storage
185; specification catalog 435; specification integration 71; physical-device
foundation 204; protection 102; protection association 52; catalog/search 45.

Native stock deduction/restock and metadata writes were exercised on owned QA
products. Recommendation family filtering includes new uncategorized native
Woo products. Accessory validation sees changed stock after an earlier display
snapshot; zero-price accessories remain unavailable. Parent-device association,
removal/restore/session cleanup, native order persistence and offline invoice
mapping passed through the existing focused suite. No external provider or
customer-email attempt was made.

All owned QA products/media/orders were removed by the tests using supported
Woo/WordPress workflows. A redundant manual cleanup call found the already
removed manifests; this was a tooling invocation error, not an application
failure, and required no further deletion. Final owned-QA scan is empty; product
status counts (131 published, 2 draft, 38 trash, 42 auto-draft) and 6 users remain
as before. Temporary profiling code is removed before handoff. No runtime
artifacts or credentials are part of the source changes.

## Remaining measured limits

No multi-second server-side bottleneck remains in these six representative
LOCAL pages. The dense model pool still generates all selector fragments by
design; this is now about 155 ms total TTFB, so no architecture change is justified.
The large homepage image remains as described above. TEST/deployment performance
has not been retested because this task is LOCAL only.

Re-run the focused regression in the isolated LOCAL container with
`AK_LOCAL_VERIFICATION=1` and `tests/performance-queries-local.php`. It creates and
cleans its own fixtures and blocks provider HTTP/mail. Retain the existing local
network guards when running foundation/protection tests.
