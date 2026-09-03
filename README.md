# Appleklinika Webshop

Production-oriented WooCommerce webshop foundation for selling used Apple devices.

Checkout partial-update validation and its local browser regression are documented in
[`docs/testing-strategy.md`](docs/testing-strategy.md#checkout-partial-update-runtime-contract).

## Business Model

- Each used device is a unique WooCommerce product.
- Products do not use variations for individual devices.
- Each product stores device-specific attributes such as battery health, storage capacity, color, cosmetic condition, warranty duration, accessories, and internal IMEI.
- iPhone, iPad, MacBook, and Apple Watch listings reuse the same WooCommerce product-card system, but category-specific chips and filters come from their own product meta.
- Internal IMEI is admin-only data and must never be exposed on the frontend.

## Architecture Direction

The project uses a custom plugin for WooCommerce business data and a custom theme for storefront presentation.

The custom storefront theme lives in:

```text
wordpress/wp-content/themes/appleklinika-theme
```

The project is prepared for a custom plugin using DDD and CQRS:

- `src/Domain`: entities, value objects, and business rules.
- `src/Application`: commands, queries, handlers, and DTOs.
- `src/Infrastructure`: WordPress and WooCommerce adapters, repository implementations.
- `src/Interfaces`: REST endpoints, WordPress hooks, and admin entry points.

Business logic must not live inside controllers, hook callbacks, or templates.

## Local Setup

1. Copy the local environment file:

   ```bash
   cp .env.example .env
   ```

2. Start the stack:

   ```bash
   make up
   ```

3. Open WordPress:

   ```text
   http://localhost:8080
   ```

4. Open phpMyAdmin:

   ```text
   http://localhost:8081
   ```

For the full local verification checklist, see:

```text
docs/local-verification.md
```

The compact storefront design rules are documented in:

```text
docs/design-system.md
```

The storefront functional UX rule is documented in:

```text
docs/workflow.md
```

Frontend and UI changes must also update the manual browser QA checklist:

```text
docs/ui-qa-checklist.md
```

## Docker Services

- `wordpress`: WordPress with Apache and PHP.
- `mariadb`: persistent MariaDB database.
- `phpmyadmin`: local database debugging UI.

Persistent data is stored in Docker volumes:

- `wordpress_data`
- `mariadb_data`

## Development Rules

- Do not work directly on `main`.
- Use feature branches named `feature/<scope-name>`.
- Do not install unnecessary plugins.
- Ask before adding paid plugins.
- Prefer custom plugin logic for maintainable business behavior.
- Keep `.env.example` in sync with `.env` keys.
- Never commit secrets.
- Before commit or push, run:

  ```bash
  make test
  make quality
  ```

- After every frontend or UI change, run a manual browser QA pass and update `docs/ui-qa-checklist.md` with `PASS`, `FAIL`, or `NOT TESTED`.

## Current Status

This is the project bootstrap with the first admin-side WooCommerce customization.

The `Appleklinika Inventory` plugin adds used-device product fields in the WooCommerce product editor:

- Device type for category-specific storefront chips and filters.
- Category-specific admin field visibility: after selecting iPhone, iPad, MacBook, or Apple Watch, the product editor only shows the matching option fields for that device family.
- Apple model from the internal device catalog.
- Apple model choices are filtered by the selected device type.
- Battery health percentage.
- Battery option for standard, new aftermarket, or new factory battery selection.
- Storage capacity as a fixed admin select list.
- Color from the internal device catalog.
- SIM configuration as a fixed admin select list.
- iPad connectivity is limited to Wi-Fi and Wi-Fi + Cellular.
- MacBook display size, Apple Silicon chip family, RAM, storage, and color.
- Apple Watch connectivity is limited to GPS and GPS + Cellular; case size, case material, and color options are filtered by the selected Watch model, including 42 mm and 46 mm sizes where applicable.
- Warranty duration as a fixed admin select list.
- Accessories.
- Short device description.
- Internal identifier / IMEI for admins only.

The custom theme also adds a functional `Kedvelt termékek` wishlist flow:

- Logged-in users save favorite product IDs in WordPress user meta.
- Logged-out users are sent to the WooCommerce account/login page instead of saving guest favorites.
- The WooCommerce account area includes a `Kedvelt termékek` section that lists saved products from real WooCommerce data.
- The WooCommerce My Account area uses a scoped Apple Klinika shell with a Rejoy-inspired dark sidebar, dashboard quick links, real-data pages for `Vásárlásaim`, `Beszámítás`, `Garanciáim`, `Visszaküldéseim`, grouped account settings, saved shipping and billing address fields, company billing mode with Hungarian tax-number validation, polished empty states, and hidden standalone Downloads/Addresses navigation items.
- Body grade.
- Camera island grade.
- Display grade.
- Manually selected overall grade.

The final storefront design is still in progress. The product page now uses WooCommerce product data, featured images, gallery images, stock status, pricing, and the real add-to-cart flow.

Recommended photo upload flow:

- Main product image: front/display.
- Gallery image: back housing.
- Gallery image: sides/camera island.
- Gallery image: visible wear or accessories.

The plugin also adds an admin catalog page:

```text
Appleklinika > Device Catalog
```

The catalog seed includes iPhone models from 2018 onward, iPad models from 2019 onward, Apple Silicon MacBook models, and Apple Watch models from SE 2 / Series 8 onward. Color labels use Hungarian names with Apple English names in parentheses. Apple Watch admin options also include model-specific case size, case material, connectivity, and color pairings. The catalog is still designed so AirPods and accessories can be added later.

Existing catalog rows can be edited or deleted directly from the admin catalog table.

The frontend currently includes:

- A custom Appleklinika storefront theme with a premium homepage implementation.
- A homepage hero, centrally managed trust/info tiles, an admin-configurable `Kiemelt Apple ajánlatok` product showcase, real WooCommerce fallback product queries, and structured footer.
- Homepage featured products are controlled in `Settings > Apple Klinika homepage` with an ordered comma-separated WooCommerce product ID list and a 1-12 product count limit.
- A Leonardo-inspired single product page structure powered by the actual WooCommerce product object.
- A desktop product page with a large real WooCommerce gallery, sticky right-side buy panel, separate Apple Klinika trust row, real configuration selectors, and product information panels.
- A right-side purchase panel with WooCommerce stock status, product title, short description, product price, sale savings, real WooCommerce add-to-cart form, add-to-cart feedback, and stock note.
- Product gallery thumbnails, previous/next controls, and lightbox navigation use real WooCommerce product images.
- Single product description, specs, reviews, and related products render from real WooCommerce product data, product attributes/meta, WooCommerce reviews, and WooCommerce related/same-model products.
- Single product `Termékadatok` keeps a short Apple Klinika condition view by default and can show imported official manufacturer specification rows through a `Mutass többet` / `Mutass kevesebbet` toggle when stored model-level specs exist for the product model key. The current model-level cache lives in the `appleklinika_official_specs_by_model` WordPress option, published products reference it with `_ak_official_specs_model_key`, and the current demo catalog has model-level specs coverage for all published products; legacy `_ak_official_specs` product meta is kept as fallback during verification.
- The product page uses a theme-level WooCommerce single product template override so the custom Appleklinika layout is the only product layout rendered.
- The block theme product template uses the `appleklinika_single_product` shortcode to avoid fallback rendering through default WooCommerce product content.
- The header includes real product search, account/cart action buttons with functional icons, and a WooCommerce cart count.
- Product add-to-cart updates the real WooCommerce cart count and displays success feedback.
- Filled cart rows expose WooCommerce's nonce-protected item removal action with a visible `Eltávolítás` label, and the existing coupon form uses the descriptive `Kupon alkalmazása` submit label without changing coupon calculation logic.
- The WooCommerce shop/listing page has a compact product grid with equal-height cards, product images, key meta, prices, and real product detail links.
- Storefront prices are formatted without unnecessary decimals, using space-separated thousands such as `379 990 Ft`.
- WooCommerce sale-price accessibility text is localized as `Eredeti ár` and `Jelenlegi ár`, the local free-shipping method is customer-facing as `Ingyenes szállítás`, and the shared sale-first catalog label is category-neutral.
- The shop page includes a left-side collapsible filter panel using WooCommerce product meta.
- iPhone keeps the approved filters for type/model, price, storage, condition, color, and SIM.
- iPad uses model, price, storage, color, connectivity, and condition filters.
- MacBook uses model, price, screen size, chip, RAM, storage, color, and condition filters.
- Apple Watch uses model, price, case size, case material/color, connectivity, strap, and condition filters.
- The WooCommerce Blocks checkout includes a real company purchase option (`Cégként vásárolok`) with conditional company name and Hungarian tax number fields, frontend `12345678-1-23` input masking, server-side format validation, order meta persistence, and logged-in user meta reuse when available. The standard Woo billing-company projection is committed only after the React-owned company input finishes its change event, so live typing cannot invalidate the canonical additional-field state during a Blocks update.
- The WooCommerce Blocks checkout uses a Phase 1 multi-step shell that keeps the original Blocks DOM mounted, shows only the current logical checkout step, keeps the live order summary visible on each step, links the completed cart step back to the real cart page, and leaves the real place-order button as the only order-submitting control.
- Order-facing screens use the immutable HPOS snapshot: standard WooCommerce billing/shipping fields remain authoritative, Hungarian house-number details appear once in the formatted address, and company tax numbers are shown once on order details, order confirmation, and rendered e-mails. The checkout summary remains gateway- and shipping-method-agnostic for later Barion, GLS, and invoicing integrations.

The standalone `appleklinika-buyback` plugin now includes the Phase 1 persistence/domain foundation, read-only legacy reporting, draft pricing administration, deterministic draft preview, and Phase 2B2 readiness-gated atomic price-book activation. Drafts may also define an explicit automatic-offer minimum for one canonical iPhone model; at or below that threshold the existing personal-inspection path is used before service-mode adjustments, while the price-book global minimum remains the fallback. Each draft may optionally add one explicit offer-mode adjustment for a canonical model and offer mode; it replaces that price-book-wide mode default without stacking, while models without a stored override continue to inherit the default. Draft duplication preserves its generated copy/version suffix and shortens only the source-name portion when that suffix would exceed the existing 120-byte domain limit. The active HUF price book is resolved through a typed service, active/retired configuration is immutable, and no public calculator, customer offer, request linkage, or legacy import is exposed. See [Buyback Phase 1A architecture](docs/architecture/appleklinika-buyback-phase-1a.md) and [Buyback Phase 2B2 architecture](docs/architecture/appleklinika-buyback-phase-2b2.md).

The local-only `/eladas/` demo keeps its approved four-offer calculator and now asks display functionality directly after screen condition, then records parts/service-history answers and affected parts as temporary questionnaire state. It does not write requests, customer data, orders, or price-book data.

Run its real integration smoke test with:

```bash
make test-buyback
make test-buyback-pricebook-activation
```

Product cards intentionally stay compact: non-iPhone archive cards only show storage, grade, an optional real battery-health chip, and a Cellular chip only when an iPad or Apple Watch product has cellular connectivity.
- The header uses a simplified two-row storefront layout with logo, centered search, account/cart actions, and Apple-focused category navigation on storefront/shop views.
- Color, storage, condition, battery health, and warranty values come from product meta fields when available.
- SIM configuration comes from product meta and can appear in shop card meta, product info cards, and shop filtering.
- Color, storage, and condition selector cards are built from matching WooCommerce products with the same Apple model meta when available.
- Selector price differences are calculated from real WooCommerce product prices when a matching product exists.
- On the product page, selector clicks can switch to another matching unique WooCommerce product without a full page reload when the selected color, storage, and grade combination exists.
- Battery replacement is modeled as an optional paid extra, not as a separate WooCommerce product variant.
- Editable WordPress footer information pages are automatically prepared for ÁSZF, Adatvédelem, Szállítás, Kapcsolat, Garancia, and Visszaküldés.
- Footer information links point to those real WordPress pages instead of dead static URLs.
- Footer information pages use a shared `ak-info-page` layout with centered readable content, intro styling, clean list blocks, and a compact trust block.
- The Contact page adds a dedicated contact panel with phone, email, address, a simple WordPress-handled form, and a map placeholder.
- Custom single product information panels render real WooCommerce descriptions, product attributes/meta, reviews, and related products while default WooCommerce tabs/related output are removed to avoid duplicate product content.
- A `Termékek` navigation item is injected into the active WordPress navigation block and links to the WooCommerce shop page.
- Color/storage/grade selection is modeled as separate unique WooCommerce products instead of WooCommerce variations.
- Single product galleries use a custom portrait-friendly image stage with hover zoom and a single-frame zoom modal that supports stepped inspection zoom, keyboard navigation, thumbnails when multiple gallery images exist, and overlay/ESC/close dismissal.
- Local product image normalization now starts with `tools/ak-normalize-product-images.py`, which creates category/profile-based display PNGs from local sources without external API calls. The first approved run is iPhone-only and writes reviewed assets to `wordpress/wp-content/uploads/ak-normalized-output/iphone/`.
- Single product pages can use a dedicated `_ak_single_product_gallery_image_id` attachment for the main gallery image, so archive/shop product cards keep using the normal WooCommerce featured image while the product detail view can use a portrait-optimized display asset.

Internal identifier / IMEI remains admin-only and is not rendered on the frontend.

## Local Selector Demo Products

For local verification, an admin-only development seeder can create a full iPhone 13 Pro selector matrix and a small iPad/MacBook/Apple Watch test set as real WooCommerce products.

The current local matrix includes:

- 5 iPhone 13 Pro colors.
- 4 storage options.
- 4 grade options.
- SIM configuration values are assigned across the local matrix.
- Battery replacement options are tested as add-on extras, not separate products.
- 3 iPad demo products covering storage, color, Wi-Fi / Cellular, grade, battery, and sale/non-sale pricing.
- 3 MacBook demo products covering screen size, chip, RAM, storage, color, grade, cycle count, and sale/non-sale pricing.
- 3 Apple Watch demo products covering case size, material, GPS / Cellular, strap, grade, battery, and sale/non-sale pricing.
- One featured image per generated product, rotated from local demo assets.

Open this while logged in as an admin:

```text
http://localhost:8080/wp-admin/admin.php?appleklinika_seed_selector_demo=confirm
```

The seeder is idempotent by SKU, so rerunning it updates the same local selector demo products instead of creating endless duplicates.
