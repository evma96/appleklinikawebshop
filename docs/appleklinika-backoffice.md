# Apple Klinika Back Office

## Purpose and access

The Back Office is a private order-fulfilment workspace at `/backoffice/`. Activate the `Appleklinika Back Office` plugin in WordPress once to register the route and administrator capability. It is then available only to authenticated users with `manage_appleklinika_backoffice`; WordPress administrators have access through `manage_options` and receive the dedicated capability when the plugin is activated.

All reads and mutations are protected server-side. Order actions use WordPress nonces, validate the WooCommerce order through `wc_get_order()`, and use WooCommerce CRUD APIs. No Back Office REST route is public.

## Source of truth

WooCommerce remains the source of truth for orders, payment, shipping and stock. The physical device is the ordered unique WooCommerce product. Its device metadata uses the existing `Appleklinika Inventory` fields, including `_appleklinika_storage_capacity`, `_appleklinika_color`, `_appleklinika_overall_grade`, `_appleklinika_sim_config`, `_appleklinika_battery_health`, and `_appleklinika_internal_identifier`.

The plugin deliberately does not change product stock, product availability, or WooCommerce order status. WooCommerce's existing stock reduction/restoration continues to own reservation and release behaviour.

## Queue and search scalability

The order queue is a bounded WooCommerce query: 25 orders per page with `paginate => true`, validated `queue_page` values (1–10,000), total result count, and previous/next plus nearby numbered navigation. It is sorted by creation date and then immutable order ID, both descending, so timestamp ties remain on exactly one page. Changing the state selector submits the existing GET form immediately, preserving any entered search and search type while resetting to the first page. It never loads an arbitrary historical order list into PHP. Queue-only metadata filters are passed through without an unnecessary outer group. Alternative values of the same fulfilment-state key use one equivalent HPOS-native `IN` condition; queue plus exact-device filtering uses an explicit outer `AND` while preserving the queue group. Summary cards request paginated IDs with a one-row limit and read WooCommerce's total metadata instead of hydrating a page of orders for each count. Queue rows use order-time primary-item and shipping snapshots, so they do not resolve products or shipping items one-by-one.

The default `Összes nyitott` view is intentionally restricted to WooCommerce's submitted operational statuses: `pending`, `on-hold`, and `processing`, excluding raw Back Office `handed_to_gls`, `picked_up`, and legacy `completed`. It excludes `checkout-draft`, cancelled, failed, refunded, trash, and WooCommerce-completed orders. The five linked summary cards partition this open set; their counts are global, not search-specific. Missing, empty and unrecognised Back Office metadata all map to `Új`, matching the domain state fallback. Status and summary labels share the same domain allowlist.

`Fizetésre vár` and `Fizetés ellenőrzendő` are open-order subsets for `pending` and `on-hold`. `Átadva / átvéve` shows physical handover states across submitted and completed WooCommerce orders. `Lezárt rendelések` independently shows WooCommerce `completed`, including orders with no Back Office metadata. This distinction does not change WooCommerce status and does not require a historical scan or custom SQL. These two completed/handed-over views may overlap because they represent different facts.

Submitted but unpaid orders remain visible with a payment block. Existing workflow eligibility still uses WooCommerce's `is_paid()` semantics; that API treats processing COD orders as processable without proving cash collection. Accordingly COD is explicitly labeled `Utánvét`, not `Fizetés rendben`; no new payment-confirmation operation is implied.

## Daily processing interface

The worklist combines order number/date, customer/delivery, ordered item/amount, fulfilment/payment, and the next action or blocking reason. Selecting a summary opens its real work queue and clears search; the filter form preserves the current search when changing queue and resets pagination. The detail page puts the order title, safe return action and current state first, then order-time device data, customer/payment, internal notes, and a prominent next-action panel. On narrow screens the next-action panel precedes the order data, while documents and history follow it. Completed orders never display a fresh-processing button just because their Back Office metadata is missing.

WooCommerce formatted addresses pass through a line-break-only HTML allowlist on both detail and packing sheet. Already-escaped text is not escaped a second time. Device names always come from order line items. Existing item-level identifier snapshots take priority, with order-level fallback only for a single line item and current product metadata as the last fallback. The displayed identifier states whether it is order-time or current product data; no historical backfill is performed.

## Worklist context

Opening an order preserves the current validated `queue`, `s`, `search_type`, and `queue_page` values. The order detail header provides `← Vissza a rendelésekhez`, which reconstructs the same worklist URL. State actions, rejected actions, and internal-note Post/Redirect/Get flows retain that context on the detail page.

The return URL is always rebuilt from these four allowlisted values. Queue and search type use the queue/search validation rules, the search text is sanitized, and the page is bounded to 1–10,000. No request-supplied `return_url` or other arbitrary redirect target is read.

Search is selected explicitly or detected automatically and always remains inside the paged WooCommerce query:

- order number: exact WooCommerce order ID;
- e-mail: exact `billing_email` query;
- customer name and telephone: HPOS `field_query` against billing fields;
- IMEI/internal identifier: exact order metadata query, wrapped in a valid HPOS `meta_query` group even when it is the only metadata condition.

Automatic search recognizes a leading-`#` order number and a short numeric WooCommerce order ID as an exact order lookup. Longer all-numeric inputs retain the existing device-identifier handling, while explicit search-type selection always takes precedence.

At order creation, the Back Office snapshots the primary item name and shipping method for the lightweight queue, plus an existing product's internal identifier into the order item and order metadata under `_appleklinika_backoffice_device_identifier`. This is search-only historical evidence, not inventory or reservation state. A future `_appleklinika_serial_number` product field is captured by the same mechanism when it exists.

The project currently has no equivalent snapshot for orders created before this change. Their current queue page uses the already-loaded order's first line item and shipping method as a bounded fallback, so the employee sees a useful name without a historical scan. Identifier-only search is guaranteed for new orders from this version onward. No full-history product/order scan or custom index is introduced merely to backfill them.

## Fulfilment workflow

The lightweight Back Office state is stored in WooCommerce order metadata under `_appleklinika_backoffice_state`. Every state change records an internal WooCommerce order note and a small metadata history containing the action, prior and new state, user, and timestamp.

Delivery mode is derived from the WooCommerce order shipping item's canonical `method_id`, not its customer-facing title. The standard `local_pickup` method uses personal pickup. The existing GLS plugin method IDs use GLS delivery. An unrecognised or mixed method set is shown as an operational review block; the workflow does not assume GLS.

GLS: `Új → Előkészítés alatt → Csomagolás alatt → Szállításra előkészítve → Átadva a GLS-nek`

Personal pickup: `Új → Előkészítés alatt → Átvételre előkészítve → Átvéve`

`Problémás` is an internal exception state and returns to `Előkészítés alatt` on the same delivery-mode path. GLS labels remain separate from physical GLS handover. For pickup, the primary actions are start processing, prepare for pickup, then confirm customer pickup; no GLS panel, label action, tracking data, or carrier instruction is rendered. Existing raw states are read through a deterministic compatibility mapping: `STARTED`/`DEVICE_CHECKED` become preparation, `PACKED`/`DOCUMENTS_READY`/`LABEL_CREATED` become shipping-ready, and legacy `COMPLETED` becomes handed to GLS.

## Customer progress and today's activity

On the authenticated My Account `view-order` screen, the order owner sees a compact Apple Klinika progress section sourced from the same Back Office state. GLS stages are order received, preparation, packing, shipping-ready, and handed to the carrier. Pickup stages are order received, preparation, ready for pickup, and picked up. `PROBLEM` remains at the previous safe public stage. The section does not render activity entries, employee information, internal notes, payment/debugging details, or internal error text.

The Back Office-only `backoffice/?view=activity` view shows the current day's workflow actions, including time, employee, order, action, and state change. Each workflow history record includes the WordPress user ID, display name, order ID, action, prior/new state, and timestamp. Manual internal notes also record a `note` action with unchanged state; their content stays exclusively in the internal WooCommerce note and never enters the activity payload. A short-lived WordPress transient holds only the current day's activity index for this operational view; no analytics database or historical reporting system is introduced.

## GLS and printing

The read-only `OrderDocuments` adapter resolves existing invoice PDFs through the installed Számlázz.hu plugin's `generate_download_link($order, 'invoice', true)` method and GLS filename metadata beneath `GLS_LABELS_DIR`. Both integrations must be active. Each PDF must be a readable local file inside its provider's canonical directory; remote paths, traversal and symlink escapes are rejected. No client-supplied file path is accepted. The Back Office download endpoints check employee capability and an order-specific nonce before reading the PDF. Missing/inactive documents have explanatory empty states instead of download buttons. Legacy GLS URL metadata is not fetched or migrated.

Tracking links use actual numeric parcel codes and the active GLS account's allowed country, matching the provider's public tracking URL. No tracking state is invented. GLS generation reuses the installed operation, validating workflow, payment, delivery mode, existing label, account and sender before any provider call. Local/test environments require sandbox mode; this includes localhost even if WordPress reports its default `production` environment type. Normal browsing does not load the provider's admin-only operation classes.

Invoice generation is deliberately unavailable from this version. The installed method performs a direct service request and potentially irreversible invoice operations; no verified invoice test account was available. Neither integration was activated or called during this work.

The existing `gls-shipping-for-woocommerce` plugin remains responsible for label creation, secure label storage, tracking numbers and parcel IDs. The Back Office calls its existing single-order label method only when the employee explicitly selects the valid next GLS action. A successful label action keeps the order at `Szállításra előkészítve`; only the separate physical handover action moves it to `Átadva a GLS-nek`, and the server rejects handover without a real label. If the local plugin or its credentials are unavailable, the Back Office states `GLS kapcsolat nincs konfigurálva ebben a környezetben.` and does not offer a fake success path.

The shipping plugin's existing metadata is displayed only for GLS orders. `Belső megjegyzések` shows only manually entered employee text; workflow events remain in `Műveleti előzmények`. The printer-friendly browser page at `/backoffice/?order=<id>&print=1` is named `Rendelési lap`, is an internal order summary rather than a shipping label, and uses pickup-neutral wording for personal pickup.

## Verification

`make test-backoffice-workflow` runs the standalone workflow, queue-query, provider-document, repository-operation, and rendered-view suites. They do not bootstrap live integrations or make external calls; document tests clean isolated temporary PDF fixtures. Rendered address tests use the installed WordPress sanitizer without bootstrapping WordPress. `make test` includes all five suites. See [runtime QA and remaining integration limits](backoffice-daily-processing-qa.md).

All five regression entry points require PHP CLI. Direct HTTP requests return 404 before loading stubs or creating temporary test documents, so a deployed plugin cannot expose an executable browser-based test harness.
