# Apple Klinika Back Office MVP

## Purpose and access

The Back Office is a private order-fulfilment workspace at `/backoffice/`. Activate the `Appleklinika Back Office` plugin in WordPress once to register the route and administrator capability. It is then available only to authenticated users with `manage_appleklinika_backoffice`; WordPress administrators have access through `manage_options` and receive the dedicated capability when the plugin is activated.

All reads and mutations are protected server-side. Order actions use WordPress nonces, validate the WooCommerce order through `wc_get_order()`, and use WooCommerce CRUD APIs. No Back Office REST route is public.

## Source of truth

WooCommerce remains the source of truth for orders, payment, shipping and stock. The physical device is the ordered unique WooCommerce product. Its device metadata uses the existing `Appleklinika Inventory` fields, including `_appleklinika_storage_capacity`, `_appleklinika_color`, `_appleklinika_overall_grade`, `_appleklinika_sim_config`, `_appleklinika_battery_health`, and `_appleklinika_internal_identifier`.

The plugin deliberately does not change product stock, product availability, or WooCommerce order status. WooCommerce's existing stock reduction/restoration continues to own reservation and release behaviour.

## Queue and search scalability

The order queue is a bounded WooCommerce query: 25 orders per page with `paginate => true`, validated `queue_page` values (1–10,000), total result count, and previous/next plus nearby numbered navigation. It is sorted by creation date and then immutable order ID, both descending, so timestamp ties remain on exactly one page. It never loads an arbitrary historical order list into PHP. Queue-only metadata filters are passed through without an unnecessary outer group. Alternative values of the same fulfilment-state key use one equivalent HPOS-native `IN` condition; queue plus exact-device filtering uses an explicit outer `AND` while preserving the queue group. Summary cards request paginated IDs with a one-row limit and read WooCommerce's total metadata instead of hydrating a page of orders for each count. Queue rows use order-time primary-item and shipping snapshots, so they do not resolve products or shipping items one-by-one.

The default `Nyitott rendelések` view is intentionally restricted to WooCommerce's submitted operational statuses: `pending`, `on-hold`, and `processing`. It excludes `checkout-draft`, cancelled, failed, refunded, trash, and already-completed WooCommerce orders. The default list keeps the submitted but unpaid `pending`/`on-hold` orders visible with a payment block; it does not allow a fulfilment transition until WooCommerce confirms payment. State filters are secondary select controls and use query-level order metadata filtering. The `Új` queue also includes submitted legacy orders that do not yet have Back Office state metadata.

## Worklist context

Opening an order preserves the current validated `queue`, `s`, `search_type`, and `queue_page` values. The order detail header provides `← Vissza a rendelésekhez`, which reconstructs the same worklist URL. State actions, rejected actions, and internal-note Post/Redirect/Get flows retain that context on the detail page.

The return URL is always rebuilt from these four allowlisted values. Queue and search type use the queue/search validation rules, the search text is sanitized, and the page is bounded to 1–10,000. No request-supplied `return_url` or other arbitrary redirect target is read.

Search is selected explicitly or detected automatically and always remains inside the paged WooCommerce query:

- order number: exact WooCommerce order ID;
- e-mail: exact `billing_email` query;
- customer name and telephone: HPOS `field_query` against billing fields;
- IMEI/internal identifier: exact order metadata query, wrapped in a valid HPOS `meta_query` group even when it is the only metadata condition.

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

The Back Office-only `backoffice/?view=activity` view shows the current day's workflow actions, including time, employee, order, action, and state change. Each workflow history record now includes the WordPress user ID, display name, order ID, action, prior/new state, and timestamp. A short-lived WordPress transient holds only the current day's activity index for this operational view; no analytics database or historical reporting system is introduced.

## GLS and printing

The existing `gls-shipping-for-woocommerce` plugin remains responsible for label creation, secure label storage, tracking numbers and parcel IDs. The Back Office calls its existing single-order label method only when the employee explicitly selects the valid next GLS action. A successful label action keeps the order at `Szállításra előkészítve`; only the separate physical handover action moves it to `Átadva a GLS-nek`, and the server rejects handover without a real label. If the local plugin or its credentials are unavailable, the Back Office states `GLS kapcsolat nincs konfigurálva ebben a környezetben.` and does not offer a fake success path.

The shipping plugin's existing metadata is displayed only for GLS orders. `Belső megjegyzések` shows only manually entered employee text; workflow events remain in `Műveleti előzmények`. The printer-friendly browser page at `/backoffice/?order=<id>&print=1` is named `Rendelési lap`, is an internal order summary rather than a shipping label, and uses pickup-neutral wording for personal pickup.

## Verification

The pure workflow regression script is available as `make test-backoffice-workflow` and does not make external calls. It verifies the normal GLS and pickup paths, problem/resume paths, invalid transitions, repeated completion protection, all three HPOS metadata-query composition cases, exact device search, bounded page size, deterministic date-and-ID pagination ordering, customer progress mapping, problem-state privacy, employee identity history, daily grouping, manual-note filtering, GLS handover protection, and safe worklist-context normalization. It requires the project WordPress container because the host environment does not provide PHP.
