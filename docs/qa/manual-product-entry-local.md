# Manual product entry — LOCAL diagnosis and repair (2026-10-26)

## Scope

LOCAL storefront only, branch `feature/local-product-entry-repair`. No commit,
push, TEST access or deployment. Existing uncommitted selector/presentation work
was preserved. No payment, order, invoice, fulfillment or protection logic changed.

## Identified product and comparison

Newest manually created device: **2513**, `iphone-13-pro-128gb`, published.
Working reference: **288**, `selector-teszt-iphone-13-pro-128-gb-grafit-a`.

| Field | New device before repair | Working reference |
| --- | --- | --- |
| Catalog visibility | hidden | visible |
| Visibility terms | exclude-from-catalog, exclude-from-search | none |
| Stock | instock; quantity tracking off; quantity null | instock; managed; quantity 1 |
| Regular / current price | HUF 139990 / 119990 | HUF 268990 / 226990 |
| Product category | Egyéb | iPhone |
| Device type / model | iphone / iphone_13_pro | same |
| Storage | 128_gb | same |
| Color / grade | sierra_blue / b | graphite / a_plus |
| Battery / warranty | 100%; 12_months | 84%; 24_months |
| SIM | physical_esim | dual_esim |
| Accessory classification | case, compatible type iphone | none |
| Featured image | not assigned | 2255 |
| Gallery IDs | 2517, 2516, 2514, 2515 | 2256, 2257 |
| Custom single-product image override | absent | absent |
| Media format | HEIC, no dimensions/subsizes | JPEG, valid dimensions/subsizes |

Other new-device inventory fields (connectivity, screen/chip/RAM, watch fields,
accessories, short device description) were empty. An internal identifier was
present; its value is deliberately omitted. Existing uploaded media belonged to
2513 and the files physically existed. No missing upload/attachment relation.

## Exact causes

1. The normal admin field **Extra védelem csoport** was saved as **Tok (case)**,
   with iPhone accessory compatibility selected. `ProductProtectionFields::save`
   intentionally calls `set_catalog_visibility('hidden')` for a nonempty valid
   accessory group. Woo consequently adds both exclusion terms. The normal
   catalog query excludes `exclude-from-catalog`; search excludes
   `exclude-from-search`. Direct URLs still work for published hidden products.
   This also prevented the device from receiving its own optional extras.
   The default for a new product is correctly **Nem kiegészítő / visible**;
   no automatic default-to-case bug was reproduced. This is conflicting normal
   product-entry configuration, not a missing hidden inventory key or index.
2. No normal Woo **Termékkép** was selected, so cards had no main photo. The
   custom detail gallery falls back to gallery IDs, but those four files were
   HEICs that the original LOCAL libheif **1.15.1** could not decode. Imagick
   advertised HEIC support but actually returned `Failed to read the file`.
   Native WordPress conversion therefore produced only filesize metadata.
   This was a LOCAL media-runtime compatibility defect, not a missing image.
3. During restoration, a real gallery deduplication defect appeared: persisted
   Woo main-image ID was a string, gallery IDs were integers; strict comparison
   showed the same photo twice if also assigned to the gallery. Normalize the
   main ID to integer before the existing strict deduplication.

## Repair

- Product 2513 only: clear the incorrect accessory group/types/models; set
  catalog visibility to visible; assign the existing iPhone category and
  existing first gallery attachment **2517** as featured image. Preserve gallery
  IDs/order, model/color/storage/condition, prices and stock mode/quantity.
- Add opt-in `docker-compose.local-media.yml` and
  `docker/wordpress-local-media/Dockerfile`. The LOCAL image extends the same
  WordPress 6.5.5 / PHP 8.2 Docker base with Debian Bookworm backports libheif
  **1.19.7-1~bpo12+1** and its decoder dependencies only. The running WordPress
  core volume remains version **7.0**; no core/PHP/plugin update performed.
  The ignored LOCAL `.env` appends the overlay to its existing COMPOSE_FILE.
  Back Office runtime, database volumes and LOCAL network/mail guards unchanged.
- All four original HEICs decoded successfully in a network-disabled isolated
  image trial. Run native `wp_generate_attachment_metadata` only for existing
  attachments 2514–2517. WordPress generated upright 1920×2560 JPEG web masters
  and 9 standard sizes each. Original HEIC binaries retained unchanged; same
  attachment IDs, no re-upload or duplicate attachment creation. Native WordPress
  retains the original attachment MIME while attached-file URLs point to JPEG.
- One integer cast in `ProductFrontendDisplay::productImages`; two regression
  checks added to `tests/product-gallery-render.php` for persisted mixed ID types.

Debian package source: https://packages.debian.org/bookworm-backports/libheif1

## Future normal entry workflow

For a physical device, leave **Extra védelem csoport = Nem kiegészítő**; those
fields describe a separately sold accessory, not extras offered with a phone.
Use normal Woo catalog visibility (shop and search), the matching category,
Apple model/storage/color/grade, price and intentional stock settings. Select
**Termékkép** for the card/main photo and **Termékgaléria** for additional photos.
No custom metadata editing, reindexing or cache-flushing step is required.
On this corrected LOCAL runtime, ordinary supported HEIC uploads use WordPress's
native automatic JPEG/subsize workflow. This does not establish HEIC support on
TEST/production, which were not accessed or changed.

Quantity tracking on 2513 remains disabled, exactly as entered. No physical
quantity was inferred. Eight preexisting HEIC files were observed (four assigned originals plus four
*-2 files). The unassigned *-2 copies were left untouched; no new upload was made.

## Verification

- 28 gallery-render assertions, including persisted string/int ID deduplication.
- 45 existing catalog/search discoverability assertions.
- 49 existing model-selector assertions.
- 53 targeted read-only checks: real main archive/category/search queries include
  2513; correct values/media, normal new-product/admin defaults, six legitimate
  extras and unavailable unpriced adapter, unchanged reference/accessory data.
  Global taxonomy counts correctly change from 50 to 51 iPhones and 7 to 6 hidden
  products; those counts are not changes to the reference product itself.
- Browser: normal iPhone listing page 2 contains both 2513 and reference 288;
  real product card loads the new JPEG; search/direct page work; four distinct
  gallery buttons and full-size zoom work; selected B / Sierra Blue / 128 GB,
  native price/stock and extras correct. Graphite then Sierra Blue returns 2513.
- No QA product, order, account, session or cart created by the checks.
- Desktop 1440/1280 and mobile 390/320: photos loaded, no horizontal overflow.
  Existing reference 288 still has three working gallery images. Browser console
  and Apache PHP-error checks were clean. Total focused result: **175 PASS**.
- Evidence: `local-product-entry-repair-20261026` in the task output directory;
  no secret or private configuration in this document.

Two verification-harness assumptions were corrected before the final passing run:
there are six real accessories (not seven), and taxonomy term totals must be
excluded from per-product equality. The CLI admin-default check also explicitly
loads Woo's admin form helper. None required an application change.
