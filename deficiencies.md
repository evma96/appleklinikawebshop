# Deficiencies

## Open Decisions

- Confirm the exact public wording and visual examples for grade explanations.
- Confirm whether accessories should become checkboxes later.
- Confirm the exact official Hungarian marketing names for all Apple colors.
- Confirm when to expand the catalog beyond iPhone to iPad, Mac, Apple Watch, AirPods, and accessories.
- Confirm the preferred production VPS provider and deployment style later.
- Define how separate products should be grouped in production so color/storage/grade cards switch only between the intended inventory items.
- Confirm whether the current same-model grouping is enough for production, or whether a dedicated model group / SKU group meta field is needed.

## Known Limitations

- WooCommerce is not installed or configured by this bootstrap alone.
- Custom product fields are editable in admin and selected values render on the product page; selector cards can switch matching same-model products without a full page reload when a complete color/storage/grade combination exists.
- Product selector controls currently group by Apple model meta, but richer grouping rules still need real inventory examples.
- The product page currently embeds the same-model selector matrix in the page for smooth local switching; this should become a narrower query or endpoint before large production inventory.
- Battery replacement extra prices are development defaults until final business pricing rules exist.
- Selector demo products are local development fixtures and must not be treated as production inventory.
- The local selector demo matrix is intentionally broad and can create many WooCommerce products for one model; production inventory needs stricter grouping and stock ownership rules.
- The custom theme homepage is implemented first; category, cart, checkout, and account pages have shared compact styling but still need dedicated UX passes.
- The shop listing has a Rejoy-style filter panel, and SIM filtering now uses product meta; production products still need consistent SIM values during admin upload.
- The Apple category navigation currently links into the shop structure, but MacBook, iPad, and Apple Watch catalog/filter behavior still needs real product data and final taxonomy rules.
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
- The cart page now renders through a custom dynamic theme layout, but quantity updates, remove links, coupon submission, and checkout navigation still need a hands-on browser interaction pass.

## Deferred Improvements

- Add PHP dependency management with Composer.
- Add PHPUnit or Pest test setup.
- Add PHP_CodeSniffer rules for WordPress coding standards.
- Add PHPStan or Psalm static analysis.
- Add WP-CLI service or command workflow.
- Add production deployment documentation.
- Polish frontend product attribute display for the final theme.
- Add public grade explanation page with images.
- Add fixed option list for accessories if needed.
- Add iPad, Mac, Apple Watch, AirPods, and accessory catalog seed data.
- Decide whether the 4-photo workflow should be enforced before publishing a product.
- Decide whether selector grouping should use only Apple model or a stricter dedicated inventory group.
- Decide how smooth selector switching should behave when a requested color/storage/grade combination is unavailable in real stock.
- Decide the final pricing logic for new aftermarket and new factory battery options.
- Decide whether local demo seed tools should be removed, hidden behind an environment flag, or kept for staging only before production launch.

## Risks

- WordPress image versions must be reviewed before production deployment.
- Placeholder quality commands do not yet enforce real code quality.
- Internal IMEI handling must be designed carefully to avoid frontend exposure.
- WooCommerce data modeling must avoid product variation complexity for unique phones.
- Manual overall grade selection depends on consistent admin judgment.
- Model-dependent color filtering relies on admin JavaScript and should receive browser-level regression testing later.
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

## Next Iteration Questions

- Which fields should be visible on the frontend product page?
- What validation rules are required for battery health, warranty duration, and IMEI?
- Should grade explanations be managed as editable WordPress content or hardcoded design content?
- Should the next step be real PHP test tooling or better admin input fields?
- Which Apple product family should be added after iPhone?
- Should production catalog deletion become archive-only after real products depend on catalog values?
- Who owns the final legal text for ÁSZF, privacy, warranty, shipping, and returns pages?
