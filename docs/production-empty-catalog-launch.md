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
