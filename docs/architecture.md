# Architecture

## Strategy

Custom business behavior will live in a dedicated WordPress plugin under:

```text
wordpress/wp-content/plugins/appleklinika-inventory
```

The plugin will follow DDD and CQRS principles adapted to WordPress and WooCommerce.

Storefront presentation lives in a dedicated theme under:

```text
wordpress/wp-content/themes/appleklinika-theme
```

The theme owns layout, typography, homepage sections, header, and footer presentation. The plugin owns product-specific business fields and WooCommerce integration.

## Layers

```text
src/Domain
src/Application
src/Infrastructure
src/Interfaces
```

## Responsibilities

- `Domain`: business concepts and rules independent from WordPress.
- `Application`: use cases, commands, queries, handlers, and DTOs.
- `Infrastructure`: WordPress, WooCommerce, database, and external adapter implementations.
- `Interfaces`: REST endpoints, hooks, admin screens, and CLI entry points.

## Rule

Controllers, hook callbacks, and admin screens may coordinate work, but they must not contain core business rules.

## Current Plugin Flow

The first admin customization is implemented through:

- `Domain/DeviceCatalog/DeviceType.php`: allowed Apple product families.
- `Infrastructure/WordPress/DeviceCatalogRepository.php`: internal device catalog storage and default iPhone, iPad, MacBook, and Apple Watch seed data, including Apple Watch model-specific case size, case material, connectivity, and color pairings.
- `Interfaces/Admin/DeviceCatalogPage.php`: admin page for viewing, adding, editing, and deleting catalog entries.
- `Domain/ProductCondition/Grade.php`: allowed grade values.
- `Application/ProductCondition/SaveProductConditionCommand.php`: input command for saving product condition data.
- `Application/ProductCondition/SaveProductConditionHandler.php`: sanitization and use case orchestration.
- `Infrastructure/WordPress/WooProductConditionRepository.php`: WordPress post meta persistence.
- `Infrastructure/WordPress/SelectorDemoProductsSeeder.php`: admin-only local development seeder for a full iPhone 13 Pro WooCommerce selector test matrix plus small iPad, MacBook, and Apple Watch category fixtures.
- `Interfaces/Admin/ProductConditionFields.php`: WooCommerce admin field rendering and save hooks, including device-type field visibility and model-dependent option filtering for catalog-backed selects.
- `Interfaces/Admin/ProductPhotoGuidance.php`: admin-side product photo checklist for the 4-photo upload workflow.
- `Interfaces/Frontend/ProductFrontendDisplay.php`: WooCommerce-hooked product purchase layout using the actual product object, product images, real add-to-cart template, and Appleklinika product meta fields.
- The product layout removes default WooCommerce single-product summary/tabs/related hooks and renders the WooCommerce product tabs once inside the custom styled product information section.
- The frontend configuration panel builds color, storage, and condition cards from published WooCommerce products with the same Apple model meta when available.
- Selector cards are backed by matching unique WooCommerce products. When a full color/storage/grade combination exists, the frontend updates the visible product title, price, gallery, stock status, URL, and add-to-cart product ID without a full page reload.
- Battery replacement is handled as an optional cart add-on extra that adjusts price and order/cart metadata, not as a separate unique product.
- Product image gallery controls and add-to-cart feedback are implemented on top of real WooCommerce product images and cart behavior.

The overall grade is intentionally selected manually in the admin workflow.

## Current Theme Flow

- `templates/front-page.html`: renders the homepage through the `appleklinika_homepage` shortcode.
- `templates/single-product.html`: renders WooCommerce product pages through the `appleklinika_single_product` shortcode to avoid block theme fallback to post content/default WooCommerce output.
- `parts/header.html`: renders the storefront header with product, account, and cart navigation.
- `parts/footer.html`: renders a dynamic footer shortcode with shop, account, cart, legal, shipping, warranty, return, and contact links.
- `woocommerce/single-product.php`: overrides the WooCommerce single product template so the default `content-single-product.php` output does not duplicate the custom Appleklinika product layout.
- `functions.php`: registers WooCommerce support, creates missing canonical editable footer information pages, migrates legacy footer page slugs, and renders homepage/footer sections from real WordPress and WooCommerce data without overwriting later admin edits.
- `functions.php`: marks canonical footer information pages with the `ak-info-page` body class, marks the Contact page with `ak-contact-page`, renders the `appleklinika_info_trust_block` shortcode, and renders the Contact page panel/form through `appleklinika_contact_panel` so these page-specific layouts stay isolated from WooCommerce product, cart, and checkout pages.
- `functions.php`: replaces the rendered WooCommerce cart page content with the custom Appleklinika cart layout, using the live WooCommerce cart object for item rows, quantity fields, remove links, coupons, shipping, discounts, totals, and checkout URL while leaving WooCommerce cart processing intact.
- `functions.php`: also adds the shop filter bar, loop product meta, and compact product-card call-to-action behavior on WooCommerce listing pages.
- `assets/css/frontend.css`: contains the first shared storefront styling layer.

## Design System

The shared storefront sizing, spacing, color, button, and card rules are documented in:

```text
docs/design-system.md
```

The functional UX gate is documented in:

```text
docs/workflow.md
```
