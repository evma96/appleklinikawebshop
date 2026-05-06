# Appleklinika Webshop

Production-oriented WooCommerce webshop foundation for selling used smartphones.

## Business Model

- Each phone is a unique WooCommerce product.
- Products do not use variations for individual devices.
- Each product will later store device-specific attributes such as battery health, storage capacity, color, cosmetic condition, warranty duration, accessories, and internal IMEI.
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

The `Appleklinika Inventory` plugin adds used-phone product fields in the WooCommerce product editor:

- Apple model from the internal device catalog.
- Battery health percentage.
- Battery option for standard, new aftermarket, or new factory battery selection.
- Storage capacity as a fixed admin select list.
- Color from the internal device catalog.
- SIM configuration as a fixed admin select list.
- Warranty duration as a fixed admin select list.
- Accessories.
- Short device description.
- Internal identifier / IMEI for admins only.
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

The first catalog seed focuses on iPhone models from 2018 onward. Color labels use Hungarian names with Apple English names in parentheses. The catalog is designed so iPad, Mac, Apple Watch, AirPods, and accessories can be added later.

Existing catalog rows can be edited or deleted directly from the admin catalog table.

The frontend currently includes:

- A custom Appleklinika storefront theme with the first homepage implementation.
- A homepage hero, real WooCommerce featured product grid, real WooCommerce product category grid, trust section, and structured footer.
- A modern ecommerce product page structure powered by the actual WooCommerce product object.
- A three-column desktop product page with Rejoy-style Appleklinika configuration selectors, WooCommerce image gallery, and a right-side purchase panel.
- A right-side purchase panel with WooCommerce stock status, product title, product price, sale savings, Appleklinika meta-based option cards, real WooCommerce add-to-cart form, stock note, and trust cards.
- Product gallery thumbnails, previous/next controls, and lightbox navigation use real WooCommerce product images.
- The product page uses a theme-level WooCommerce single product template override so the custom Appleklinika layout is the only product layout rendered.
- The block theme product template uses the `appleklinika_single_product` shortcode to avoid fallback rendering through default WooCommerce product content.
- The header includes real product search and a WooCommerce cart count.
- Product add-to-cart updates the real WooCommerce cart count and displays success feedback.
- The WooCommerce shop/listing page has a compact product grid with equal-height cards, product images, key meta, prices, and real product detail links.
- Storefront prices are formatted without unnecessary decimals, using space-separated thousands such as `379 990 Ft`.
- The shop page includes a left-side collapsible filter panel for type/model, price, storage, color, and SIM options, using WooCommerce product meta.
- The header uses a simplified two-row storefront layout with logo, centered search, account/cart actions, and Apple-focused category navigation.
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
- WooCommerce product tabs are rendered once inside the styled product information section to avoid duplicate product content.
- A `Termékek` navigation item is injected into the active WordPress navigation block and links to the WooCommerce shop page.
- Color/storage/grade selection is modeled as separate unique WooCommerce products instead of WooCommerce variations.

Internal identifier / IMEI remains admin-only and is not rendered on the frontend.

## Local Selector Demo Products

For local verification, an admin-only development seeder can create a full iPhone 13 Pro selector matrix as real WooCommerce products.

The current local matrix includes:

- 5 iPhone 13 Pro colors.
- 4 storage options.
- 4 grade options.
- SIM configuration values are assigned across the local matrix.
- Battery replacement options are tested as add-on extras, not separate products.
- One featured image per generated product, rotated from local demo assets.

Open this while logged in as an admin:

```text
http://localhost:8080/wp-admin/admin.php?appleklinika_seed_selector_demo=confirm
```

The seeder is idempotent by SKU, so rerunning it updates the same local selector demo products instead of creating endless duplicates.
