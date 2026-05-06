# Local Verification

This guide explains how to run and verify the local WooCommerce development environment.

## 1. Install Docker Desktop

Docker Desktop is required to run WordPress, MariaDB, and phpMyAdmin locally.

Official links:

- Docker Desktop for Mac: https://docs.docker.com/installation/mac/
- Docker Desktop overview: https://docs.docker.com/desktop/
- Docker Compose installation note: https://docs.docker.com/compose/install/

Use the Mac installer that matches the machine:

- Apple Silicon: M1, M2, M3, M4.
- Intel: older Intel-based Mac.

After installation, open Docker Desktop and wait until it says Docker is running.

## 2. Start The Local Stack

From the project folder:

```bash
make install
make up
```

Expected local URLs:

- WordPress: http://localhost:8080
- phpMyAdmin: http://localhost:8081

## 3. Complete WordPress Installation

Open WordPress:

```text
http://localhost:8080
```

Choose the site language, create the first admin user, and log in.

## 4. Install WooCommerce

Official links:

- WordPress plugin management: https://wordpress.org/documentation/article/manage-plugins/
- WooCommerce setup wizard: https://woocommerce.com/document/woocommerce-setup-wizard/

In the WordPress admin:

1. Go to `Plugins > Add New`.
2. Search for `WooCommerce`.
3. Install and activate WooCommerce.
4. Follow the WooCommerce setup wizard.

Recommended starting choices:

- Store country: Hungary.
- Currency: Hungarian forint.
- Product type: physical products.
- Avoid optional paid extensions unless explicitly approved.

## 5. Activate The Custom Plugin

In the WordPress admin:

1. Go to `Plugins > Installed Plugins`.
2. Find `Appleklinika Inventory`.
3. Click `Activate`.

## 6. Verify Product Fields

In the WordPress admin:

1. Go to `Products > Add New`.
2. Create a test product.
3. In the product data area, verify these custom fields:
   - Battery health.
   - Storage capacity.
   - Color.
   - Warranty duration.
   - Accessories.
   - Short device description.
   - Internal identifier / IMEI.
   - Body grade.
   - Camera island grade.
   - Display grade.
   - Overall grade.
4. Save the product.
5. Reopen the product and confirm the values remained saved.

## 7. Verification Notes

- The custom fields are admin-side only for now.
- Internal identifier / IMEI must not be displayed on the frontend.
- Product photos should use the standard WooCommerce product image and gallery.
- No live payment setup is required for this local verification.
