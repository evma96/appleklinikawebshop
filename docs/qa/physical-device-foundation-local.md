# Physical-device product foundation — LOCAL verification

Date: 2026-10-26. Branch: `feature/physical-device-foundation`.
Scope: LOCAL only. No commit, push, merge, TEST access, deployment, provider request or production/main change.

## Native data model and entry contract

One physical used device is one simple Woo product. New entries default to an editable draft, managed stock of one, sold individually, no backorders, normal catalog visibility and the existing family category. Publication requires a manual SKU, IMEI or serial, a positive regular price, at least one readable own photo, a valid model/color/condition, and storage except for Watch. Stock accepts only zero or one. Existing demo/legacy records are not silently bulk-migrated.

`Application/DeviceProduct/DeviceProductService::save(SaveDeviceProductCommand)` is the shared entry point. Product ID zero creates; a positive ID updates only the provided fields. The command does not accept a source-product/copy ID. The normal Woo editor uses the same validated plan. The future Back Office entry screen must authorize its caller and use this service; no new Back Office screen or inventory table was introduced.

Supported input keys include `device_model`, optional derived `device_type`, `storage_capacity`, `color`, `overall_grade`, `regular_price`, optional `sale_price`, `sku`, `imei`, `serial_number`, `stock`, ordered `photos`, `custom_name` and `title`, plus the existing family/condition fields. Categories, visibility, native stock settings and selector relationships are derived. Example composition:

```php
$store = new WooDeviceProductStore();
$service = new DeviceProductService(
    $store,
    new SaveProductConditionHandler(
        new WooProductConditionRepository(),
        new DeviceCatalogRepository()
    )
);
$id = $service->save(new SaveDeviceProductCommand(0, $input));
```

The native Woo SKU is the only shop item number. The editor label is **Cikkszám / Boltvezető azonosító**; the native 100-character lookup limit and uniqueness are checked. No random SKU is generated. Native Woo admin SKU search works. A connection-owned advisory lock serializes the application service and normal editor writes, including identity uniqueness validation; there is no separate inventory database.

IMEI and serial are separate private `_appleklinika_imei` / `_appleklinika_serial_number` fields. Either or both are accepted without incorrect family restrictions. The old mixed internal identifier is retained as legacy data, not guessed to be an IMEI. New order items snapshot SKU/IMEI/serial once. Later product edits cannot change the sold line's identity. Authorized Woo/Back Office views show these snapshots; public product/order/email item metadata omits the private identity fields.

Automatic names use the catalog model display label and storage. Watch internal storage is omitted. **Egyedi terméknév** defaults off; when enabled, ordinary subsequent saves preserve the manual title. WordPress/Woo use the final title for the slug. An invalid entry remains an editable draft with a clear error. A stale Woo stock form cannot silently restore a sold device.

## Native photos and privacy

The existing opt-in LOCAL media image contains the compatible Debian libheif decoder and ExifTool. `AK_PHOTO_METADATA_TOOL=/usr/bin/exiftool` is passed through the LOCAL Compose overlay. No external image conversion service or new WordPress media plugin is used.

WordPress accepts direct HEIC/HEIF and produces browser-safe JPEGs and native responsive sizes. JPEG/PNG/WebP remain native inputs. Product attachment processing strips GPS, capture timestamps, maker/device identifiers and private EXIF; original image data, orientation and ICC are preserved. Public `image_meta` is cleared. The processed original and all current/backup derivatives are covered. File hashes prevent unnecessary repeated metadata rewriting and invalidate the cache when file bytes change. Processing failure blocks device publication instead of silently skipping privacy processing.

`photos` is an ordered list of native attachment IDs: the first becomes the featured image; the remainder become the native gallery. Duplicate IDs are removed without reordering. Photos already owned by another physical product are rejected. The future upload UI should first create a draft and attach uploads to that draft, then pass the ordered media collection to the service; the operator never needs to type IDs. Native Woo image/gallery controls remain compatible.

Native product duplication retains model-level fields only. It clears SKU, global unique ID, IMEI, serial, private unit notes, condition, price, featured/gallery photos, custom naming and other unit-specific Apple Klinika metadata. A second save cannot resurrect the original gallery through Woo's recursive array merge. The duplicate remains a draft until new physical-unit data is entered.

The currently running LOCAL WordPress core is 7.0 (the persistent core volume is newer than the Docker base tag). Native HEIC conversion therefore also requires a capable WordPress core, not only image libraries. Before any later TEST integration, verify native HEIC support and the same compatible libheif + ExifTool runtime there. This task neither checks nor changes TEST. This is a new-entry foundation, not a retrospective metadata rewrite of the disposable catalog.

## Visibility and classification

Stock zero excludes a physical device from normal catalog/search queries, homepage product collections and available selector products. The product/order/history records remain. Native Woo restock restores availability at one. This does not change the global Woo out-of-stock setting or maintain a second availability database.

Device metadata and an Extra védelem accessory group cannot be published together. The normal editor explains the conflict. Legitimate hidden Woo accessories remain unchanged. Already-invalid legacy products are excluded from accessory options as an additional guard.

Observed existing LOCAL exception: product **#2513**, `iphone-13-pro-128gb`, currently contains both `iphone_13_pro` and accessory group `case`, with hidden catalog visibility. These pre-existing product values were preserved. Its editor now displays the conflict and it is not offered as a case accessory. Correct its classification through the normal editor with its legitimate required device data; this task does not invent its SKU/identity or bulk-repair legacy data.

## Offline invoice mapping

The supported `wc_szamlazz_invoice_line_item` hook uses the immutable sold-item snapshot:

```text
IMEI: <value>
Sorozatszám: <value>
```

Only supplied identity lines appear, without duplication on replay. Other hidden metadata is excluded. The installed vendor's product-line mapping was executed offline: names, quantities and amounts remained native and unchanged.

The supported `wc_szamlazz_xml` invoice hook sets the used-device invoice note to exactly:

```text
Használt cikk - különbözet szerinti szabályozás
```

No VAT/tax calculation, provider transport, license check, order/email lifecycle or legal document was changed.

## COD safety gate

**DEFER COD UNTIL AFTER LAUNCH.**

The installed GLS adapter natively maps `cod` payment to the order's `CODAmount` and `CODReference`; this mapping was checked offline. That alone is insufficient: current lifecycle enrollment is Barion-based, payment/invoice handling requires a paid order, and fulfilment/packing-to-label also requires verified payment. COD would need separate acceptance, unpaid fulfilment, invoice timing and collection-confirmation decisions. Enabling a checkout gateway alone would produce an incomplete flow. No COD option, payment configuration, provider behavior or lifecycle guard was changed.

## Rehearsal and cleanup

`make test-local-device-foundation` runs disposable LOCAL-only Woo fixtures, with network and mail blocked, and deletes its orders/products/media in `finally`. It requires the existing LOCAL HEIC source photograph; the source itself is not altered or committed. `--keep-for-browser` temporarily retains explicitly marked fixtures for browser inspection; `--cleanup` removes that manifest's fixtures using Woo/WordPress APIs.

The four-family rehearsal covers automatic/custom naming, stock defaults, native category/visibility/SKU search, identity uniqueness and snapshots, invalid classification, duplicate-product isolation, ordered photos, HEIC/HEIF/JPEG/PNG/WebP processing, private-metadata removal, pixel/orientation/ICC preservation, selector membership, vendor invoice mapping, native stock reduction/retry/restock, Woo editor errors/retries/stale stock and the COD gate. Original non-QA product data is fingerprinted before/after.

Browser evidence: iPhone/iPad/Mac/Watch at desktop 1440 and mobile 390; additional iPhone/Watch 320. No broken images, private identity text, horizontal overflow or console errors. Gallery switching and full-image links work. Browser search proved available → absent at zero → present after restock. Existing page/selector/Extra védelem/review styles were preserved. The non-iPhone blank-color photos are deliberate generated QA fixtures, not catalog imagery intended for sale.

Evidence directory: `/Users/apple/.codex/visualizations/2026/09/11/01a08fcb-a363-7761-acb7-46d99e2bde2c/physical-device-foundation-20261026/`.

## Final verification

**1,305 assertions passed** in the final runs:

| Suite | Assertions |
| --- | ---: |
| Physical-device entry, normal Woo editor, photos, order/invoice snapshots, stock, COD gate | 200 |
| Model/storage catalog | 185 |
| Model selector options | 49 |
| Gallery render | 28 |
| Purchase panel render | 68 |
| Product information presentation | 49 |
| Product frontend structure | 3 |
| Extra protection | 102 |
| Parent-device/accessory association | 52 |
| Trust/Watch | 36 |
| Catalog/search discoverability | 45 |
| Existing lifecycle/Back Office/security/legal-PDF checks from `make test` | 388 |
| **Total** | **1,305** |

`make test` and `make quality` exited successfully. The generic unit/integration and linter/static-analysis targets still report that they are unconfigured; this is not claimed as additional coverage. All 24 PHP files changed in this task passed PHP syntax checks. `git diff --check` passed. A targeted source secret-pattern check found no credential/private-key patterns.

Final supported-API cleanup inspection: zero own QA products, orders or attachments; zero own QA image files; no retained fixture manifest. No QA user was created. The foundation suite confirmed all original product-data fingerprints unchanged. External requests and outgoing mail during that suite: **0 / 0**. Browser console checks were clear. The original LOCAL product page was reopened after the disposable fixtures were removed.

The 13 pre-existing changed files outside this task remained byte-for-byte identical to their task-start hashes, including the ongoing visual CSS. The working tree intentionally remains uncommitted on `feature/physical-device-foundation`; HEAD is still `0841b1ff7eab5d0f81ac17cdddd84d1708fe0fe6`.

**READY FOR INTEGRATION** for the physical-device foundation, subject to including the documented media-runtime prerequisites when integration is separately authorized. This does not enable COD, repair legacy #2513 automatically, or certify a TEST/production deployment.

## Exact files changed by this task

The following 32 source/configuration/documentation paths differ from their task-start contents. Existing approved edits in shared files were retained. Paths are relative to `/Users/apple/Desktop/appleklinika-integration-active`.


- `.env.example`
- `Makefile`
- `README.md`
- `deficiencies.md`
- `docker-compose.local-media.yml`
- `docker/wordpress-local-media/Dockerfile`
- `docs/local-development.md`
- `docs/qa/physical-device-foundation-local.md`
- `wordpress/wp-content/plugins/appleklinika-backoffice/src/Infrastructure/WooOrderBackOfficeRepository.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/appleklinika-inventory.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Application/DeviceProduct/DeviceProductService.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Application/DeviceProduct/DeviceProductStore.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Application/DeviceProduct/SaveDeviceProductCommand.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Application/ProductCondition/SaveProductConditionHandler.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Domain/DeviceProduct/PhysicalDevice.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/DeviceAvailability.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/DeviceInvoiceMapping.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/DeviceOrderIdentity.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/ProductPhotoProcessor.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/WooDeviceProductSafety.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/WooDeviceProductStore.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Infrastructure/WordPress/WooProtectionCatalog.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Admin/DeviceProductEditor.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Admin/ProductConditionFields.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Admin/ProductProtectionFields.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/DeviceOrderHooks.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/DeviceProductHooks.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/src/Interfaces/Frontend/ProductFrontendDisplay.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/tests/device-product-editor-rehearsal.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/tests/device-product-foundation-local.php`
- `wordpress/wp-content/plugins/appleklinika-inventory/tests/protection-local.php`
- `wordpress/wp-content/themes/appleklinika-theme/tests/catalog-search-discoverability.php`

The ignored private LOCAL `.env` also received only the non-secret `AK_PHOTO_METADATA_TOOL=/usr/bin/exiftool` setting. The rebuilt LOCAL image and ignored runtime/evidence files are not Git source changes; no credentials or QA data were added to tracked files.
