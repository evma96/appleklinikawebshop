# UI QA Checklist

Manual UI QA verifies real webshop usability in the browser. Do not mark an item as `PASS` unless it was actually checked in the browser during the current implementation round.

Status values:

- `PASS`: verified in browser and working.
- `FAIL`: verified in browser and broken.
- `NOT TESTED`: not verified in browser during this round.

Last run: 2026-05-05
Scope: Cart visual restoration to the provided approved screenshot.

## 1. Navigation Check

| Item | Status | Notes |
| --- | --- | --- |
| Header links work | NOT TESTED | Header was not changed in this round. |
| Footer links work | NOT TESTED | Footer link targets were not changed in this round. |
| Product cards open real product pages | NOT TESTED | Product cards were not changed in this round. |
| No empty href | NOT TESTED | Footer links were not changed in this round. |
| No `#` links | NOT TESTED | Footer links were not changed in this round. |
| No broken URLs | NOT TESTED | Footer links were not changed in this round. |

## 2. Product Page Check

| Item | Status | Notes |
| --- | --- | --- |
| No duplicated product layout | NOT TESTED | Not part of this UI change. |
| Gallery works | NOT TESTED | Not part of this UI change. |
| Thumbnails work | NOT TESTED | Not part of this UI change. |
| Selectors work | NOT TESTED | Not part of this UI change. |
| Selected state stays on clicked option | NOT TESTED | Not part of this UI change. |
| Check icon does not overlap text | PASS | Browser QA showed the selected color check icon still top-right while color names wrap without ellipsis. |
| Add to cart works | NOT TESTED | Not part of this UI change. |

## 3. Shop / Listing Page Check

| Item | Status | Notes |
| --- | --- | --- |
| Product cards are equal size | NOT TESTED | Product cards were not changed in this round. |
| Images have consistent ratio | NOT TESTED | Product images were not changed in this round. |
| Filters look modern | NOT TESTED | Filter radius/spacing was slightly aligned; verify no visual regression. |
| Filters do not look like default browser UI | NOT TESTED | Filter functionality was not changed. |
| Sorting works if available | NOT TESTED | WooCommerce sorting was not changed in this round. |

## 4. Layout Check

| Item | Status | Notes |
| --- | --- | --- |
| No oversized sections | NOT TESTED | Final visual cleanup changed shared spacing only; verify at 1440px desktop. |
| No huge empty spacing | NOT TESTED | Narrow browser QA shows compact stacked cart cards; 1440px desktop spacing still needs visual verification in the user's browser. |
| Compact ecommerce proportions | NOT TESTED | Cart item cards now have a subtle hover lift/movement like storefront product cards. Verify at 1440px desktop. |
| Mobile layout is usable | PASS | Narrow browser QA shows the logo/search on the left and Fiókom/Kosár stacked in the right column. |
| Desktop layout is balanced | NOT TESTED | Desktop cart summary fixed positioning was reverted; verify at 1440px that the two columns no longer overlap and scroll together. |

## 5. Cart / Checkout Check

| Item | Status | Notes |
| --- | --- | --- |
| Cart page opens | NOT TESTED | A direct `.ak-cart-*` desktop restoration layer was added; verify the full cart page visually against the reference image at desktop width. |
| Quantity update works | NOT TESTED | Cart item/update area visuals changed only; quantity control functionality was not retested in this pass. |
| Remove item works | NOT TESTED | Separate remove links were intentionally removed from the custom cart UI; removal now depends on setting quantity to zero and updating the cart. |
| Checkout page opens | NOT TESTED | Checkout sidebar product rows were changed with CSS only; browser verification is still needed at desktop width. |
| WooCommerce fields are not broken | NOT TESTED | Checkout form fields, shipping, payment, notices, terms, and order submit were not changed. |
