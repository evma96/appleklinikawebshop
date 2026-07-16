# Deficiencies

## Open Decisions

- Confirm the exact public wording and visual examples for grade explanations.
- Confirm whether accessories should become checkboxes later.
- Confirm the exact official Hungarian marketing names for all Apple colors.
- Confirm when to expand the catalog beyond iPhone, iPad, MacBook, and Apple Watch to AirPods and accessories.
- Confirm the preferred production VPS provider and deployment style later.
- Define how separate products should be grouped in production so color/storage/grade cards switch only between the intended inventory items.
- Confirm whether the current same-model grouping is enough for production, or whether a dedicated model group / SKU group meta field is needed.

## Known Limitations

- Buyback Phase 2B1 now provides internal draft price-book administration plus a deterministic, transient admin calculation preview. Price-book activation, live/public calculation, request or offer persistence, inspection, payout, courier, trade-in credit, WooCommerce integration, and legacy import remain deferred.
- The Buyback Phase 1A migration smoke test is a real Docker-backed WordPress integration check (`make test-buyback`), while the repository-wide `make test` and `make quality` commands still remain placeholders.
- WooCommerce is not installed or configured by this bootstrap alone.
- Custom product fields are editable in admin and selected values render on the product page; selector cards can switch matching same-model products without a full page reload when a complete color/storage/grade combination exists.
- Product selector controls currently group by Apple model meta, but richer grouping rules still need real inventory examples.
- The product page currently embeds the same-model selector matrix in the page for smooth local switching; this should become a narrower query or endpoint before large production inventory.
- The single product FAQ section is intentionally not rendered until there is an editable FAQ/content source; fake product FAQs should not be added directly to the frontend.
- Single product trust block copy is centralized in `ProductFrontendDisplay::productTrustBlocks()` for this pass, but should move to an admin-backed editable source before production if policy wording changes often.
- Sparse WooCommerce products may render fewer single product information panels because description, attribute, review, and related-product sections are intentionally backed by real available product data only.
- Official manufacturer specification import now covers the current published demo catalog through model-level `appleklinika_official_specs_by_model` entries and per-product `_ak_official_specs_model_key` references. Refresh tooling, source review workflow, and an admin editing UI still need separate approval before production use.
- Battery replacement extra prices are development defaults until final business pricing rules exist.
- Selector demo products are local development fixtures and must not be treated as production inventory.
- The local selector demo matrix is intentionally broad and can create many WooCommerce products for one model; production inventory needs stricter grouping and stock ownership rules.
- The custom theme homepage now has a premium shell with centralized trust tile copy in theme render functions, admin-configurable featured product IDs/count, a dynamic homepage block that avoids shortcode paragraph wrapping, and homepage product sections reuse the approved shared shop product-card renderer; production may still need an admin-editable content control for the non-product homepage text blocks.
- Category, cart, checkout, and account pages have shared compact styling, and the checkout company tax number field now masks numeric input as `12345678-1-23` while still relying on server-side validation.
- The WooCommerce My Account page now uses a custom Apple Klinika shell with Rejoy-style sidebar rhythm, final account navigation, real WooCommerce order-card rendering, CTA-only buyback, warranty/return empty states backed by real order/refund data when available, grouped account settings, saved shipping/billing address user meta, and company billing meta reuse from checkout; browser QA confirmed the logged-in shell and endpoints, while actual account-detail save submission, checkout save-to-profile behavior, and real-order/warranty visuals still need production-like data checks.
- The shop listing has a Rejoy-style filter panel, and SIM filtering now uses product meta; production products still need consistent SIM values during admin upload.
- The shop `Állapot` filter uses `_appleklinika_overall_grade`; production listings depend on admins consistently setting this grade for every used-device product.
- The shop sale-first ordering uses WooCommerce's product lookup `onsale` flag; after bulk imports or manual database changes, WooCommerce lookup tables may need regeneration for accurate sale ordering.
- The local `free_shipping#1` instance title is localized in the WooCommerce shipping-zone setting; production shipping zones and newly created method instances still need their own customer-facing title review.
- The Apple category navigation links into category-specific shop views for iPhone, iPad, MacBook, and Apple Watch. Production still needs final taxonomy rules for AirPods and accessories.
- iPad, MacBook, and Apple Watch local demo products currently reuse existing local demo image assets; production needs real category-specific product photos.
- Header links for account/cart pages should be aligned with the final WooCommerce page slugs before production.
- Information pages are created with editable demo content and need final business/legal copy before production.
- Product listing, cart, checkout, and account pages still need the functional UX gate audit.
- Desktop-width visual QA is still limited by the current narrow in-app browser viewport; the header/grid CSS is implemented, but full desktop screenshots should be checked on a wider browser window.
- The 4-photo workflow is guidance-only and does not enforce a minimum image count yet.
- Test, lint, formatting, and static analysis commands are placeholders.
- No CI pipeline exists yet.
- Local runtime verification still requires Docker Desktop to be installed and running on the user's machine.
- WooCommerce admin verification is in progress after fixing early translation loading.
- Device catalog color names are seeded as practical Hungarian labels with Apple English names in parentheses and still need final business review.
- Browser-level storefront checks can be blocked by coming-soon mode when the tester is not in a logged-in WordPress session.
- The global design system cleanup is a first pass; header-specific and product-selector-specific visual fixes were intentionally left for separate focused iterations.
- The cart page now renders through a custom dynamic theme layout; nonce-protected item removal and the recalculated filled/empty cart transition were browser-tested, while quantity updates, coupon submission, and checkout navigation still need a focused interaction pass.
- The Kedvencek wishlist is intentionally logged-in-only while the storefront is behind Coming Soon for logged-out users; guest wishlist storage is not implemented.

## Deferred Improvements

- Keep every price book draft-only until Phase 2B2 separately defines activation governance, immutable request linkage, and audit requirements; the Phase 2B1 admin preview is transient, and legacy `appleklinika_buyback_records` data remains read-only until a separately approved import design exists.
- Add PHP dependency management with Composer.
- Add PHPUnit or Pest test setup.
- Add PHP_CodeSniffer rules for WordPress coding standards.
- Add PHPStan or Psalm static analysis.
- Add WP-CLI service or command workflow.
- Add production deployment documentation.
- Polish frontend product attribute display for the final theme.
- Add public grade explanation page with images.
- Add fixed option list for accessories if needed.
- Add AirPods and accessory catalog seed data.
- Replace local iPad, MacBook, and Apple Watch demo image fixtures with category-specific demo assets when available.
- Decide whether the 4-photo workflow should be enforced before publishing a product.
- Decide whether selector grouping should use only Apple model or a stricter dedicated inventory group.
- Decide how smooth selector switching should behave when a requested color/storage/grade combination is unavailable in real stock.
- Decide the final pricing logic for new aftermarket and new factory battery options.
- Decide whether local demo seed tools should be removed, hidden behind an environment flag, or kept for staging only before production launch.
- Decide later whether production should support guest wishlist storage, login-required notices, or account import after login/registration.

## Risks

- WordPress image versions must be reviewed before production deployment.
- Placeholder quality commands do not yet enforce real code quality.
- Internal IMEI handling must be designed carefully to avoid frontend exposure.
- WooCommerce data modeling must avoid product variation complexity for unique phones.
- Manual overall grade selection depends on consistent admin judgment.
- Device-type field visibility and model-dependent option filtering rely on admin JavaScript; the current local browser pass verified iPhone, iPad, MacBook, Apple Watch visibility, iPad/Watch connectivity restrictions, and Apple Watch model-specific case size/material/color pairing, but this should be included in regression testing before launch.
- Photo quality and angle consistency still depend on the admin uploader.
- The current local logo asset came from the provided Facebook image and should be replaced with final production brand artwork if a higher-resolution source exists.
- Product page trust text is first-pass business copy and should be reviewed before production.
- The product page currently styles around the active default WordPress theme; final theme work may move these styles into a dedicated theme layer.
- Automatically creating missing information pages from the theme is useful during bootstrap, but production content ownership should be reviewed before launch.
- The product page now uses a WooCommerce template override to prevent duplicate default output, but the final production theme should still receive a full WooCommerce template audit before launch.
- Footer information pages now exist with realistic placeholder content, but ÁSZF, privacy, shipping, warranty, contact, and returns copy still requires final business/legal approval before launch.
- Footer information page trust-block copy is intentionally simple bootstrap text and should be reviewed against final shipping, warranty, and returns policies before production.
- Contact page phone number, address, and map are placeholders; final business contact data and mail delivery settings must be confirmed before production.
- The custom cart layout depends on WooCommerce cart hooks and form handling staying compatible with future WooCommerce updates, so it should be regression-tested after WooCommerce upgrades.
- The company purchase checkout fields now include required-state handling and Hungarian tax number format validation, but they are still saved as WooCommerce order/user meta only; final invoice plugin integration and any official invoice field mapping still need a separate audit after the chosen invoicing workflow is installed.
- Checkout address detail fields for house number, floor, staircase, and door are saved as WooCommerce Blocks address meta and appended to formatted order addresses, but final invoice/shipping label field mapping still needs a separate plugin-specific audit.
- The checkout multi-step shell deliberately hides non-active WooCommerce Blocks form sections without moving or unmounting them, while keeping the live order summary visible; it still needs a full regression pass after GLS shipping and Barion payment plugins are installed because those plugins may add their own fields inside the shipping and payment sections.
- The single product zoom modal now supports thumbnail switching and previous/next controls, but the current local catalog did not expose a published product with multiple gallery images during this pass; multi-image behavior should be verified as soon as a real multi-photo product is available.
- The local image normalization pipeline has only been approved for the first iPhone phone-portrait output. MacBook, iPad, and Apple Watch profiles exist in the script but must not be batch-run until each family has a reviewed source/preview pass.
- Only the tested iPhone 13 Pro product currently uses `_ak_single_product_gallery_image_id`; future products need an admin workflow or import step so portrait/detail images are assigned separately from archive featured images.

## Next Iteration Questions

- Which fields should be visible on the frontend product page?
- What validation rules are required for battery health, warranty duration, and IMEI?
- Should grade explanations be managed as editable WordPress content or hardcoded design content?
- Should the next step be real PHP test tooling or better admin input fields?
- Which Apple product family should be added after iPad, MacBook, and Apple Watch: AirPods, accessories, or another device line?
- Should production catalog deletion become archive-only after real products depend on catalog values?
- Who owns the final legal text for ÁSZF, privacy, warranty, shipping, and returns pages?
