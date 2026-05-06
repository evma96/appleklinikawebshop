# System Design

## Purpose

The system is a WooCommerce webshop for selling used smartphones. Each physical phone is represented as one unique product.

## Core Services

- WordPress provides content management and the admin interface.
- WooCommerce provides product, cart, checkout, and order management.
- MariaDB stores WordPress and WooCommerce data.
- phpMyAdmin is available for local database inspection.

## Product Model

Each used phone is a unique stock item. The project intentionally avoids WooCommerce variations for individual devices because condition and device identity differ per unit.

Current custom admin fields:

- Apple model from the internal device catalog.
- Battery health percentage.
- Storage capacity as a fixed admin select list: 64 GB, 128 GB, 256 GB, 512 GB, 1 TB, 2 TB.
- Color from the internal device catalog.
- Warranty duration as a fixed admin select list: 3 months, 6 months, 12 months, 24 months, 36 months.
- Accessories.
- Short device description.
- Internal IMEI for admins only.
- Body grade.
- Camera island grade.
- Display grade.
- Manually selected overall grade.

Product photos use the standard WooCommerce product image and product gallery.

Recommended photo flow:

- Main product image: front/display.
- Gallery image: back housing.
- Gallery image: sides/camera island.
- Gallery image: visible wear or accessories.

## Grade Model

The physical condition is split into separate components:

- Body.
- Camera island.
- Display.

The overall grade is selected manually by the admin. The component grades support internal inspection, but they do not automatically decide the final sales grade.

Detailed public grade explanations are deferred to a future frontend content feature.

## Device Catalog

The system stores Apple device model and color options in an internal WordPress option managed by the custom plugin.

Initial scope:

- iPhone models from 2018 onward.
- Hungarian color labels with Apple English names in parentheses.
- Admin-managed additions for future models.
- Admin-managed edits for existing catalog rows.
- Admin-managed deletion for catalog rows.

Future scope:

- iPad.
- Mac.
- Apple Watch.
- AirPods.
- Accessories.
- Optional archive workflow if deletion becomes too risky for production operations.

## Frontend Product Display

Public product pages can render safe Appleklinika product facts:

- Apple model.
- Storage capacity.
- Color.
- Battery health.
- Warranty duration.
- Overall grade.

Internal identifier / IMEI is excluded from frontend rendering.

## Deployment Direction

The local stack uses Docker and persistent volumes. The structure is intended to remain compatible with a future cloud VPS deployment.
