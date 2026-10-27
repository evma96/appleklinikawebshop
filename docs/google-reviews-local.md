# Google Reviews — actual LOCAL storefront candidate

Date: 2026-10-19. Branch: `feature/google-reviews-trust`.
Base HEAD: `e46e0fbd3eab8c3d0884026b02d67728b5a03439`.
This records the accepted LOCAL candidate and supersedes the previous link-only / separate structural-specimen result. The owner subsequently authorized one checkpoint, normal integration and TEST deployment. Main and production remain excluded. The release receipt records the resulting commit/deployment identities.

## Visible result

The actual homepage at `http://localhost:8082/` now contains a substantial section with a summary, stars, count, avatars, names, dates, review text, attribution and navigation. Six cards are available, three visible at desktop widths. Category and product pages have a compact summary and two cards. Mobile shows one complete card plus the next-card hint, with native horizontal scrolling. Long text is preserved in full and expandable using native `details`; there is no rewriting or substring truncation. Carousel controls add no external library, request, cookie, storage or autoplay. The original approved provider strip remains below the reviews, unchanged.

**All currently visible ratings, counts, identities, dates and comments are LOCAL QA examples, not real Google data.** Each stored name begins with `QA`, and each stored comment still begins with `QA-minta:` as required by the unchanged fixture reader. The current presentation uses one small `LOCAL · QA review data` badge with an explanatory title and keeps the synthetic names. It omits only the internal comment prefix in QA rendering; genuine Google comments remain untouched. The ignored LOCAL fixture uses neutral editorial text about choosing a device, not invented customer experiences or repeated technical disclaimers. Avatars remain generic local SVG silhouettes. The write-review action is omitted until Google supplies a legitimate destination; the all-reviews link retains the approved business URL.

The latest owner request explicitly authorizes this LOCAL visual fixture. It is deliberately on the actual storefront, not an isolated specimen. Old `*-current.png` link-only and `*-structure-only.png` artifacts are not evidence for this candidate.

## Verified identity and source availability

Approved business: **Apple Klinika Szeged - iPhone Szerviz és Kereskedés**, **6722 Szeged, Jósika utca 4.** Existing Contact identity: `0x474489111d25d185:0xf8dc8aa6ed1595e7`; CID `17932360265829291495`.
Profile URL: `https://www.google.com/maps?cid=17932360265829291495`.
The existing profile link was browser-verified previously; no Maps rating, count or review content was scraped or imported. Contact and its map are unchanged.

Read-only inspection of accessible LOCAL environment/constants, WordPress option names and existing integration source found no usable OAuth connection, Places API key or configured Places Place ID. The existing Google Maps embed is not an API credential. No unseen owner-managed Cloud account is assumed absent; its state cannot be inferred from LOCAL files.

Preferred official adapter: **Google Business Profile API**. OAuth token refresh is followed by Business Information `locations.get` identity verification against the approved CID, then `accounts.locations.reviews.list`, `pageSize=6`, `orderBy=updateTime desc`. No score filtering. API aggregate values are not recalculated from the displayed subset. Genuine avatar URLs must be HTTPS Googleusercontent hosts. The write-review CTA requires Google's `metadata.newReviewUri`.

- Official GBP capacity: up to **50 reviews per request**, with continuation tokens for further pages. This implementation intentionally fetches **up to six** for the carousel; compact uses two. Actual usable returned count depends on the authorized source.
- Official Places (New) alternative: **at most five reviews**, relevance ordered. No existing key or Place ID was found. It requires its own enabled/billed API configuration, so it is not silently enabled and no billed request is made.
- **Live data available now: no. Genuine reviews retrieved: 0.** This does not describe the business's actual review count.

### One owner setup action

**Authorize Apple Klinika's own Google Business Profile API connection using the account that owns/manages the verified listing.** Use an existing Google-approved project if available; otherwise the owner must request Basic API Access first. The supported OAuth authorization needs `business.manage`, and the developer must privately provision client ID/secret, refresh token and the account/location IDs. A Maps embed or an API key alone does not grant this access. No password/key belongs in chat or Git.

`AK_GOOGLE_REVIEWS_CONFIG_FILE` points to a supported private JSON outside the WordPress webroot with keys `client_id`, `client_secret`, `refresh_token`, `account_id`, `location_id`. The path remains empty here. No real credential is created or changed. Google-only outbound access and supported secret mounting can be configured after owner authorization; the existing LOCAL provider/mail/network isolation stays enabled.

Official references:
- [GBP reviews.list and page-size limit](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews/list)
- [GBP prerequisites and Basic API Access](https://developers.google.com/my-business/content/prereqs)
- [Places review limit](https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places)
- [Places fields and required configuration](https://developers.google.com/maps/documentation/places/web-service/place-details)
- [Business Profile caching policy](https://developers.google.com/my-business/content/policies)

## Live behavior and failure handling

The Inventory plugin owns the domain projection, refresh service, transport/cache adapter and Woo review restrictions; the theme owns HTML/CSS and presentation-only carousel behavior. Rendering reads only cached public data; it never waits for Google. Configured sites refresh twice daily. The cache is scoped to the configured location and expires after 24 hours, even after failures. A lock and 15-minute backoff limit retries. HTTP has fixed official destinations, TLS verification, no redirects, an eight-second timeout and a response-size bound. No upstream bodies or secrets are logged. An unconfigured site has no scheduled Google refresh.

Without valid real data and outside the explicit LOCAL preview, the review section is hidden; the provider strip remains. There is no empty promotional panel or link-only substitute. There are no invented live scores, reviewer identities, dates or text.

Title, subtitle and visibility remain editable under **Settings → Apple Klinika vélemények** with `manage_options` and native Settings API checks. Review text, scores and credentials are not editable there.

## LOCAL QA isolation

The private fixture is `.local-runtime/google-reviews-qa.json`, ignored by Git and outside the webroot. The optional `docker-compose.reviews-qa.yml` is selected only in ignored LOCAL `.env`, and mounts that file read-only at `/run/appleklinika/reviews-qa.json`. `AK_GOOGLE_REVIEWS_QA_HOST_FILE` is blank in `.env.example`; a missing fixture fails closed. No fixture enters uploads, a WordPress option, product/customer record or Google cache.

`LocalReviewPreview` independently requires all of:
1. WordPress environment exactly `local`;
2. the established `AK_LOCAL_VERIFICATION=1` isolation guard;
3. both configured site URL and incoming request host exactly loopback;
4. a readable, bounded private file outside `ABSPATH`;
5. the explicit fixture marker and QA-prefixed identity/comment in every record.

The renderer independently rejects QA-tagged data when the environment gate fails. The live projection rejects QA/fixture-tagged snapshots. Production, staging, test, development-without-local-designation, non-loopback site/request, missing isolation guard, malformed/unmarked fixture and public-webroot fixture denial are tested.

QA responses have `Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private` and `X-Robots-Tag: noindex, nofollow`. No public QA endpoint or runtime toggle is added. The LOCAL WordPress container alone was recreated to add the mount, keeping the same source, data/uploads volumes, network, loopback ports and provider/mail blocking. Database and Back Office containers were not recreated. Remove the QA overlay from LOCAL `COMPOSE_FILE` to disable the preview; no database cleanup is required. Do not use this overlay or fixture for TEST/production.

## Scope preservation

Woo product review tabs, forms, rating/count blocks and product-review structured data remain disabled. The obsolete custom review panel is removed; description/specifications/selectors/pricing are retained. Existing stored comments are not deleted and unrelated WordPress comments remain unaffected. Transactional surfaces (cart, checkout, account/login/register), Back Office, wp-admin, feeds and 404 are excluded.

Barion / GLS / MPL / Számlázz.hu assets are unchanged byte copies from the existing approved implementation. No payment/shipping/invoice setting, customer/order data, legal text, header, checkout or provider logic is modified. The older LOCAL database's shipping/payment configuration is outside this task and not reconciled with TEST.

## Verification

**302 focused assertions PASS:** domain/service/presentation/LOCAL guards 94; mocked transport/cache 27; actual LOCAL Woo hooks 20; homepage 112; product information 49. No real API, mail, payment or provider call. The homepage harness reports its existing duplicate `WP_HTTP_BLOCK_EXTERNAL` definition warning; inspected runtime pages show no corresponding new failure.

Actual in-app browser verification:
- Homepage 1440 and 1280: three equal-height cards visible, six total, navigation reaches `4–6 / 6` and disables the next button; previous navigation works.
- Homepage 390 and 320: one full card with next-card hint, native horizontal keyboard scrolling verified, no document horizontal overflow. Touch swipe uses that native scroll container; no physical-phone gesture test is claimed.
- Long second review expanded/collapsed: full original text and paragraph preserved; no overflow.
- Product/category 1440 and 390: two compact cards, summary and original provider strip; no document overflow. Product Woo rating/tab/form absent.
- Empty-cart checkout navigation redirects to cart, with neither component present; isolated checkout context tests also deny the section. No checkout/order E2E was run.
- Account and Back Office login show neither reviews nor provider strip. Back Office route and admin exclusions also covered by focused context tests. No admin login or QA account was needed.
- No console errors on the inspected LOCAL pages. Local HTTP returns 200, six marked cards and expected no-cache/noindex headers. No old link-only markup.
- PHP syntax and whitespace checks pass. Source was uncommitted during the initial visual acceptance; it is now approved for checkpointing.

Screenshots of **actual LOCAL pages**, not a specimen, are outside Git in:
`/Users/apple/.codex/visualizations/2026/09/11/01a08fcb-a363-7761-acb7-46d99e2bde2c/google-reviews-on-page/`

| Screenshot | Evidence |
| --- | --- |
| `homepage-1440.png` | Summary, three visible cards, navigation and four logos |
| `homepage-1280.png` | Laptop-width balance |
| `homepage-390.png`, `homepage-320.png` | Narrow viewports and native card track |
| `homepage-expanded-1440.png` | Entire long text available |
| `category-1440.png`, `category-390.png` | Actual category compact block |
| `product-1440.png`, `product-390.png` | Actual product compact block |
| `checkout-empty-cart-excluded-390.png` | Checkout-to-empty-cart exclusion |
| `backoffice-excluded-390.png` | Back Office login exclusion |

LOCAL UI implementation is ready for visual review. Live Google data integration/real-avatar acceptance remains pending owner authorization; it is not claimed as a live-data PASS.

## Changed files

All 25 Git-visible files in the approved Google Reviews candidate (including the preceding iteration):

- `.env.example`
- `Makefile`
- `README.md`
- `deficiencies.md`
- `docker-compose.yml`
- `wordpress/wp-content/plugins/appleklinika-inventory/appleklinika-inventory.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Frontend/ProductFrontendDisplay.php`
- `wordpress/wp-content/themes/appleklinika-theme/functions.php`
- `docker-compose.reviews-qa.yml`
- `docs/google-reviews-local.md`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Application/GoogleReviews/ReviewGateway.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Application/GoogleReviews/ReviewService.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Domain/GoogleReviews/PublicReviews.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/GoogleBusinessReviews.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/LocalReviewPreview.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Frontend/StoreReviews.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/tests/google-reviews-transport.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/tests/google-reviews.php`
- `wordpress/wp-content/themes/appleklinika-theme/assets/css/google-reviews.css`
- `wordpress/wp-content/themes/appleklinika-theme/assets/images/services/barion.svg`
- `wordpress/wp-content/themes/appleklinika-theme/assets/images/services/gls.svg`
- `wordpress/wp-content/themes/appleklinika-theme/assets/images/services/mpl.jpg`
- `wordpress/wp-content/themes/appleklinika-theme/assets/images/services/szamlazzhu.svg`
- `wordpress/wp-content/themes/appleklinika-theme/assets/js/google-reviews.js`
- `wordpress/wp-content/themes/appleklinika-theme/inc/google-reviews.php`

Intentionally ignored LOCAL-only changes: `.env` (opt-in overlay/path), `.local-runtime/google-reviews-qa.json` (explicit neutral QA dataset). No credentials or customer records in either new QA configuration or fixture.

## Integration acceptance safeguards

The initial 302-check baseline is retained above as historical evidence. Before checkpointing, 60 additional assertions cover staging/production with loopback and remote configured/request hosts, an available private fixture and enabled LOCAL flag. These independently prove both the reader and full/compact renderer deny QA, and missing live configuration cannot activate it. The two test entry points are CLI-only and return HTTP 404 otherwise. Tests carry synthetic sentinels solely for automated assertions; the actual LOCAL visual fixture remains ignored and is not exported/deployed.

The deployment must use only the exact approved Git delta onto the current `develop/post-deploy`, preserving later Back Office/GLS/security code and private runtime overlays. Do not deploy the LOCAL Compose overlay, ignored fixture, `.env`, screenshots or private artifacts. TEST has no approved live Google connection: expected frontend output is no review section, no QA marker/rating/name/text, no link-only fallback and the normal four-logo strip on eligible public surfaces. No provider transaction is needed for smoke verification.

Checkpoint preparation result: **362 focused assertions PASS** (154 + 27 + 20 + 112 + 49), plus `make test` **388 offline assertions** and PHP syntax checks. `make quality` ran successfully but its lint/static targets remain placeholders. LOCAL carousel, expansion and mobile native scrolling were rechecked in the browser without console errors. The 25-file publication scan found no credentials or private artifacts; the original public MPL logo metadata contains only public 2008 Photoshop/export/dimension metadata, no GPS/device/customer identifiers.

Publication note: the original Barion SVG uses CRLF and the original GLS SVG has five trailing spaces in style lines. They are retained byte-for-byte; staged whitespace validation excludes these original third-party assets and passes for all application/configuration/documentation files. This is not an application or rendering defect.

## 2026-10-26 — LOCAL visual composition revision

Branch: `feature/local-reviews-visual-composition`; baseline `0841b1ff7eab5d0f81ac17cdddd84d1708fe0fe6`. Presentation-only, uncommitted and awaiting Martin's visual approval. No TEST access, push, deployment, legal/content registry edit or provider action.

- One left-hand summary and right-hand card track replace the fragmented intro/summary layout. Product/category use two equal-height cards within a 1120 px compact grid; the product variant consumes part of the existing empty bottom gutter without changing the product template. Homepage retains its editable title/subtitle, a larger summary, three cards at 1440 and the six-card carousel.
- A small corner badge replaces the yellow QA banner. Repeated fixture attribution and the inactive preview CTA are removed. Fixture markers, host/environment gates, no-cache behavior, live-data fallback and Google adapter are unchanged. The ignored private fixture's neutral text was refreshed for visual review; no fixture file is staged or distributed.
- Mobile stacks summary above a native horizontal track, with one full card and a next-card hint. Long reviews still expand/collapse without rewriting genuine content. Existing JavaScript is unchanged.
- Original provider logo assets and labels are unchanged. Only spacing separates the reviews, provider strip and footer.

Verification: **185 focused assertions PASS** (158 presentation/domain/LOCAL protection + 27 mocked transport/cache). PHP syntax and diff whitespace checks pass. No real external API, customer email, order, cart or provider transaction.

Personally inspected the actual LOCAL pages and saved viewport plus full-page screenshots: product 1440/1280/390/320, homepage 1440/390, category 1440/390. Each has document width equal to viewport width. Product desktop cards share top/bottom alignment; compact review width follows the 1120 px product-information grid. The homepage carousel advances `1–3 / 6` to `4–6 / 6`, disables next at the end and returns correctly. Native mobile horizontal scrolling, normal page scrolling and full-text expand/collapse work. No captured browser console warnings/errors on the inspected pages. Physical-device touch gestures were not separately tested.

Evidence (outside Git):
`/Users/apple/.codex/visualizations/2026/09/11/01a08fcb-a363-7761-acb7-46d99e2bde2c/local-reviews-visual-composition-20261026/`

This is visual acceptance evidence using explicitly labeled LOCAL data; it does not establish a live Google connection.
