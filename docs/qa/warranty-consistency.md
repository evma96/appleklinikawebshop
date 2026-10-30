# Warranty consistency

Root cause: the legacy selector demo seeder assigned 24 months to A+ iPhones and several other demonstration devices. The frontend correctly read the stored warranty; the homepage trust strip already said 12 months.

Correct the demo source to 12 months. Default missing warranty on supported device entry to 12 months, while retaining explicit alternative values and non-device terms. Update only the identified LOCAL device warranty metadata using Woo APIs. Preserve original terms for existing referencing orders in `_appleklinika_warranty_order_history` product metadata, read by the account warranty view; do not rewrite historical orders.

Check all four families: trust card, technical warranty row, selector navigation, desktop/mobile; verify homepage 12 months and unchanged order hashes. No production or legal-document change.

## Focused release procedure

Run `php wp-content/plugins/appleklinika-inventory/tests/warranty-consistency.php` inside the named LOCAL/TEST WordPress container, plus the existing product-information presentation checks. Run `make test` and `make quality` before commit/push.

Before deployment, inspect TEST using the old seeder definitions: only exact known legacy demo SKUs with matching cosmetic grade and the old 24-month value qualify. Record product IDs, old/new warranty, referencing order IDs and order/item hashes privately outside Git. Do not run the seeder, rewrite orders, normalize unrelated explicit terms or change legal files.

After deploying the exact SHA, re-check each planned SKU and old value before applying via Woo product metadata APIs. Save the previous terms for every referencing order in `_appleklinika_warranty_order_history` on the product, then change the current product warranty to `12_months`. This preserves the account warranty display without editing any order. Repeated execution is a no-op. Verify all original order/item hashes and product data except these two warranty metadata keys.

Known TEST plan (2026-10-30): 20 legacy iPhone demo defaults; all 24 iPad, 24 MacBook and 24 Watch records already 12 months. No unrelated explicit warranty terms discovered. Never infer current eligibility solely from this historical count; inspect again.
