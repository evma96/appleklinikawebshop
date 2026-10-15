# Production catalog boundary

Owner decision, 2026-10-14: production intentionally starts with **zero products**.
The current TEST/demo catalog is disposable functional-test data, not a production
inventory source. Martin will add each real physical device manually with fresh,
correct photographs and real device data after launch preparation.

## Required production preparation gate

This is a procedure for a separately authorized production launch, not authority
to access or modify production now.

1. Prepare the production WordPress/WooCommerce database independently. Do not
   clone/import the TEST database or its product exports as production data.
   Configuration/page/media transfers must be explicitly allowlisted and must
   exclude TEST products and product variations, their relationships, product
   stock and TEST catalog assignments. A full TEST disaster-recovery backup is
   never a production launch seed.
2. Do not run development/demo product provisioners, fixture seeders or catalog
   imports on production. Installing application source is not authorization to
   populate the catalog. Ensure no scheduled/import job can repopulate demo data.
3. Before opening customer traffic, use supported WordPress/WooCommerce CLI/API
   reads to count `product` and `product_variation` records across **all statuses**,
   including drafts, private records, trash and any custom status. Both counts
   must be zero. Stop the launch if any demo/test product is found; do not silently
   delete an unexpected real device or customer record.
4. Verify the storefront/search/category/featured sections contain no demo listing
   or stale product link and handle the empty catalog cleanly. Refresh relevant
   caches and search indexes through supported workflows if necessary. Record
   the empty-catalog acceptance before Martin begins real manual stock entry.
5. For real stock, use only the actual device's data and new correct photos. Do
   not reuse TEST product IDs or assign a demo photograph to a real device.

No TEST products, photos or stock are changed by this decision. The previous
catalog report (46 confirmed image mismatches, including 43 in stock; 31 model and
15 color mismatches; 76 additional review candidates) remains historical evidence,
not an outstanding curation project or launch-blocking defect. Preserve TEST
fixtures while they remain useful for functional checks.

## Detailed dry-run procedure (2026-10-15)

Use the [migration matrix](production-data-separation.md) to distinguish source,
configuration, TEST records, environment secrets and legal content. Application
logic is moved as code; records are selectively recreated, not copied wholesale.

1. Inventory the source read-only. On a **known LOCAL or TEST** WordPress CLI
   runtime with Woo loaded, run the repository's `scripts/operations/catalog-launch-plan.php`
   using `wp eval-file`. Optional single argument: a private JSON array of reviewed
   attachment IDs needed for the site (for example logo, hero, category and Contact
   photos). No argument means no media has been approved for export. Example:

   ```sh
   wp eval-file /reviewed-source/scripts/operations/catalog-launch-plan.php /private/site-media-allowlist.json
   ```

   Keep the report privately (0700 directory / 0600 file); it contains object IDs,
   not customer names, emails, passwords, option values or order bodies. The tool
   has no mutation/apply mode and refuses production, even if the caller supplies
   a production URL. A future production verification must be separately authorized;
   do not remove this guard to repurpose the TEST planner.
2. The plan explicitly excludes all inventoried product/variation/order/refund/user
   IDs, all carts/sessions/transients and TEST provider transaction/document state
   from the future import. It lists reviewed keep-media IDs, shared approved media
   and unapproved media needing review. Product thumbnail/gallery references are
   factual; **exclusive use is not inferred**. Check page blocks, settings, menus,
   CSS/backgrounds, logo/favicon and email assets before approving a site image.
   Media with uncertain use stays on TEST and is not exported by default.
3. Recreate reviewed categories and attributes without relationships to demo products.
   Recreate approved general Woo settings, page mappings, homepage/contact settings,
   navigation and site media with new target IDs. No entire options table, existing
   serialized credential blob, sessions, action queue or TEST users is imported.
   Fresh target setup excludes QA orders and provider artifacts by construction.
4. Install exact approved code without running `tools/dev/*`, seed/demo importers or
   fixture scripts. Keep external mail/providers and cron held during setup. Recreate
   real employees separately. Configure secrets/environment through the approved
   private workflow; review callback URLs and licence bindings. BACS stays OFF.
5. With separately authorized production read access later, count products and
   variations through supported WordPress APIs using **every registered post status**,
   including drafts/trash; use Woo order APIs including refunds and checkout-draft
   for the empty-order gate. Count employee accounts against the approved named list.
   Stop on any unexpected record; never blanket-delete a target that may have real data.
6. Check empty storefront, category/search/featured sections and no demo purchase or
   orphaned featured-product links. Verify homepage, Contact, checkout shell, My
   Account and Back Office routes without creating a real order. Confirm no TEST
   URLs, PDFs, tracking numbers or mail recipient overrides in target configuration.
   Refresh only supported caches/indexes if needed. Record empty-state acceptance.
7. Only after the empty-catalog gate may Martin add devices manually using actual
   serial/device details and fresh correct photos. No demo product export is a source.

The tool's offline fixture verifies source guards, exact excluded IDs, preservation
of shared site media and absence of mutations. It has **not** been run against a
production database, and this preparation does not claim a completed launch rehearsal.
A full encrypted TEST recovery is a separate disaster-recovery procedure, never
this empty-production preparation.


## Isolated rehearsal evidence

The 2026-10-15 [engineering run](qa/preproduction-engineering-20261015.md) completed
the LOCAL zero-product -> one admin-created physical-device fixture -> zero-product
round trip. A fresh database was used; no TEST SQL dump or customer/provider state
was copied. Use HPOS (`woocommerce_custom_orders_table_enabled=yes`) as on approved
TEST; legacy CPT is not the supported launch configuration for Back Office meta queries.
Review Woo coming-soon state explicitly; fresh installation defaults may differ.
No actual production deployment or destructive cleanup was exercised.
