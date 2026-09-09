# Order fields at provider boundaries

The Woo order snapshot is unchanged. Checkout stores `appleklinika_tax_number`
and `_wc_billing/appleklinika/*` / `_wc_shipping/appleklinika/*` address components.
Provider adapters must read that snapshot, not the current customer profile.

`OrderProviderMapping` in the customer-address-book infrastructure registers:

- `wc_szamlazz_xml_adoszam`: use the stored tax number; retain the vendor fallback
  for orders without an Apple Klinika tax number.
- `wc_szamlazz_xml`: compose the invoice billing street, house number, address line
  2 and any stored staircase/floor/door components into `vevo/cim`.
- `gls_shipping_for_woocommerce_api_get_delivery_address`: compose the same
  components from SHIPPING only, leaving every other delivery field unchanged.

GLS 1.4.1 has a pickup-address filter but no equivalent recipient filter. The
vendored file has one added `apply_filters` return at `get_delivery_address()`;
single and bulk label paths already use that method. Preserve this extension on
vendor updates. No global Woo getter filter, request interception, database
migration or order/customer write is used.

Run `make test-order-provider-mapping` in LOCAL. It creates and removes one exact
private QA product/order, blocks WordPress HTTP requests, and only generates the
Számlázz.hu XML preview and GLS address data. The unmodified baseline fails the
three required mappings; the fixed version passes all ten checks. Legacy fields,
contact data, repeated mapping and unchanged stored order data are also covered.
Actual TEST invoice/parcel issuance is a separate, explicitly authorized E2E gate.
