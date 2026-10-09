# MPL checkout and fulfilment — TEST candidate

## Source and dependency

Reviewed 2026-10-09 against the live Magyar Posta pages:
- https://www.posta.hu/webaruhazi_regisztracio : gross HUF 1,040 for pickup points/post offices/terminals; home delivery HUF 2,090 up to 10 kg, 3,140 above 10 through 20 kg, 6,300 above 20 through 40 kg. General webshop registration is required; these tariffs do not include collection at the merchant's premises.
- https://www.posta.hu/belfoldi_csomagmegoldasok : home 40 kg, post office 30 kg, partner pickup point 20 kg, terminal 20 kg. Normal domestic parcel maximum 120 × 60 × 60 cm; terminal 50 × 35 × 31 cm.
- https://www.posta.hu/ugyfelszolgalat/csomagautomata : terminal value ceiling HUF 500,000 and all three side limits apply, not merely volume.
- https://www.posta.hu/mplapi : label/tracking API registration and agreement required.

Free supported selector: **Hungarian Pickup Points & Shipping Labels for WooCommerce 4.2.8**, from WordPress.org, by Viszt Péter. `docker/mpl-selector.json` pins its release archive checksum. Install with `python3 scripts/install-mpl-selector.py <plugins-directory>`; the downloaded dependency is ignored in Git. No purchase, licence bypass, or custom static pickup-point list. The plugin refreshes the official postal point database; only its Posta data importer is enabled by our TEST adapter.

Its supported automatic label/manifest workflow needs a separate legitimate PRO/test licence, in addition to Posta Sandbox API key/password, customer/agreement codes and sender details. No such access was present on TEST. `appleklinika_mpl_api_enabled=no` blocks provider requests, including native plugin handlers. A production API hostname is denied in this TEST adapter. The official registration page is opened for Martin; secret fields must be entered privately after registration.

## Editable checkout configuration

`appleklinika_mpl_enabled=yes` enables the adapter only under the existing LOCAL/TEST lifecycle guard. In the Hungary shipping zone add `ak_mpl_home` and the vendor's `vp_pont` alongside the unchanged GLS and personal-pickup methods. Home rates are editable Woo shipping-instance fields. Pickup-point prices and provider choices are editable in WooCommerce → Settings → Shipping → Pickup points; only `postapont_posta`, `postapont_postapont`, `postapont_automata` are intended here. Prices are configured gross, with Woo shipping-tax extraction, not a new tax policy.

All 122 existing published TEST products lack weight (read-only inventory). No guessed catalog data was inserted. Woo weight and dimensions must represent the **packed product**; a combined parcel stacks those boxes conservatively. Unknown weight/dimensions, non-Hungarian destinations, excessive weight/size or terminal value fail closed. Therefore the current catalog remains a **CONFIG/DATA launch blocker for MPL availability** until real packaging data is supplied. Known-weight/size QA products prove checkout independently. Multi-parcel carton optimization and oversized service are not introduced.

## Shared lifecycle and documents

The existing `FulfilmentWorkflow`, `ChangeFulfilment`, Woo metadata/history, customer timeline and notification ledger are extended for MPL. Existing GLS action and persisted state names remain unchanged. MPL uses `handed_to_carrier` in the same state machine; it is not a separate order-status model.

Labels stay in `ready_for_shipping`. Actual staff handoff requires provider label + tracking evidence and a closed MPL manifest; only that logged transition schedules the existing shipping email once. MPL wording and official postal tracking links are used in HTML/plain-text email. Native redundant provider customer emails are suppressed for MPL orders. Completion remains a later explicit delivery closure. Existing invoice/Barion/cash behavior is preserved.

Read-only adapter consumes vendor `_vp_woo_pont_*` order metadata. Label PDFs are blocked at the public upload path and served through the existing capability + per-order nonce Back Office document route, with document-access history. Label integration cannot be called before supported license/configuration readiness; no provider PASS is claimed from fixtures.

## Verification status

Offline tariff boundary and carrier-transition checks plus a disposable real Woo database verify rates, missing-data exclusion, shared state/history, manifest prerequisite, one MPL handoff email despite retries, carrier-specific copy/URL and duplicate-vendor-email suppression. Provider and inbox results remain external tests, not implied by these assertions.

TEST browser checkout/point selection and cleanup evidence are recorded separately in the private prelaunch evidence directory. MPL provider proof is pending legitimate Posta Sandbox access and supported label licence. No legal documents were changed.

### Checkout Blocks compatibility follow-up

The first TEST browser check exposed a configuration/compatibility gap: a shipping rate alone does not mount the vendor's pickup selector. Enable Woo's native pickup feature (`woocommerce_pickup_location_settings.enabled=yes`) and mount the unmodified vendor picker inside its supported `woocommerce/checkout-pickup-options-block` parent at render time. Stored checkout content and legal sample wording remain unchanged. The three-step presentation includes Woo's shipping/pickup choice and pickup controls in step 3, and uses the selected pickup rate in the final review. The vendor activation routine must run before the first official point import (it creates its data directories). Official import produced 1,955 post offices, 647 terminals and 164 partner points on 2026-10-09.
The vendor's default CSS assumes it is the only legacy pickup method when no new-style locations are configured; a scoped compatibility override preserves both the existing `local_pickup` and MPL choice. It also preserves Woo's native price label instead of the vendor's point-only minimum on the combined pickup choice. The existing cash/pickup shipping identifier is unchanged.
A second browser check identified a duplicate point-price pseudo-label and missing selected-point address in the custom final review. The scoped override removes the duplicate, and final review reads the vendor widget's actual selected name/address. Native delivery choice labels are Hungarian; the editable native pickup title is `Átvételi pont vagy üzlet` so it does not misdescribe MPL as in-store collection.
TEST QA order 750 preserved the official point `Szeged 1 posta` / `880002`, gross shipping 1,040 and total 2,040 HUF, unpaid/on-hold via BACS. Postal point addresses must not retain customer-specific house/floor/door additions; the adapter clears only these shipping extras after the vendor maps the official point, preserving billing. Set vendor `custom_tracking_page` to boolean false (the vendor uses truthiness, so string `no` is not sufficient) to retain one shared Apple Klinika timeline.

MPL 4.2.8 map compatibility: the upstream markercluster bundle reads mutable global `L`, allowing another map widget to break initialization. A checksum-guarded, one-line Browserify module binding (`scripts/mpl-selector-compat.py`) uses the same bundled Leaflet instance. No provider, licensing, GLS or legal behavior is changed. Re-review this patch when updating the pinned dependency.
