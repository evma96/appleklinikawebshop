# Canonical store-address consistency — 2026-10-14

Owner-confirmed physical store / personal pickup: **6722 Szeged, Jósika utca 4.** This is not an instruction to alter the registered company seat or legal documents.

## Complete occurrence inventory and action

| Occurrence found | Action/result |
| --- | --- |
| LOCAL effective Contact address used old 6720 / Jósika utca 2–4 because its saved option was empty | Save explicit canonical Contact values, preserving all other effective fields |
| Older LOCAL Contact source defaults: postcode and street | Correct exactly those two defaults; update two existing address expectations in its Contact test |
| Homepage hero_items.0.alt, LOCAL and TEST | Replace old street range with full canonical store address |
| First hero attachment alt: LOCAL 2444 / TEST 719 | Same correction in media-library metadata |
| Banner 1: address badge and street plaque | Correct address artwork |
| Banner 2: address badge and street plaque | Correct address artwork |
| Banner 4: lower-left address caption and street plaque | Correct address artwork |
| TEST Contact saved values and source defaults | Already canonical; unchanged |
| Personal-pickup address / customer email presentation | Reads Contact settings; render-only confirmation now canonical on both environments |
| Google directions textual destination | Canonical; LOCAL follows corrected Contact setting; TEST unchanged |
| Google Maps embed | Same Apple Klinika place 0x474489111d25d185:0xf8dc8aa6ed1595e7; no independent postal string in embed URL; unchanged |
| Apple Maps/Waze | Same verified coordinates 46.2544892,20.1426876; labels name the provider rather than another postal address; unchanged |
| Home-service artwork | Already says Szeged, Jósika utca 4.; unchanged |
| Published page/post/reusable-block/template/navigation/attachment text scan | No other Jósika occurrence found outside the listed options and attachment alt |
| Dormant legacy Contact panel source | Contains “6720 Szeged, minta üzletcím”, not the old Jósika range; no page uses its shortcode and the active Contact block does not render it; not activated or refactored |
| Dated 2026-09-11 approved-content snapshot, media archive, historical deficiencies and 2026-10-08 cookie evidence | Retain original historical facts/assets. They are not active storefront configuration; restoration must reapply the confirmed current address/assets |

Banner IDs/order/editability are preserved. Replaced attachment file references: LOCAL 2444/2445/2447 and TEST 719/720/722. New versioned filenames are `apple-klinika-home-slide-{1,2,4}-store-address-20261014.png`, with eight WordPress-generated responsive sizes each. Previous files remain available for rollback but are no longer active hero sources. The other five slides and category images are untouched. Three artwork corrections preserve the existing composition; no claim of pixel-identical non-text regions from image generation.

## Focused verification

- LOCAL Contact renderer: 44 existing assertions pass; PHP syntax and diff checks pass.
- Actual LOCAL and TEST homepage/Contact inspected in browser at desktop/mobile widths; corrected assets loaded, eight slides retained, no horizontal overflow at 390 px and no browser JS errors.
- All three original corrected assets return HTTP 200 on each environment and match their media-file SHA256.
- Render-only unsaved order object: accepted pickup email HTML/plain address correct on LOCAL; both acknowledgement and accepted-order HTML/plain address correct on TEST. No call to send/deliver or order save, zero mail and provider HTTP calls.
- All original 162 LOCAL / 73 TEST orders, users, stocks and protected provider/payment/shipping settings unchanged. Ten legal pages per environment unchanged. No QA user/order/cart created.
- Google embed and exact navigation coordinates unchanged. Company seat untouched.

A LOCAL content helper initially encountered an empty-string Contact option instead of an array, after successful media import. Only the missing setting step was resumed using effective sanitized content; no duplicate upload or production action. A hash comparison using a different Woo object-loading path was corrected to the original loading path and then matched all orders. These are resolved verification-tool issues, not application defects.

## Protected checkpoint and source relationship

The durable LOCAL runtime correction is committed as `a5b4c099299940ecc94119b923df4381ec26d7ec` on `feature/canonical-store-address-checkpoint`: five intended source/test/documentation files, no runtime/QA/media/secret artifacts, clean worktree. `make test` and `make quality` ran before commit; quality currently reports placeholder lint/static targets rather than a real static-analysis suite.

The current develop source already has the same canonical defaults and test expectations in `44f65cfbf81a4554d727730316d2c596ef041972`; only a comment and assertion description differ from the older LOCAL backport. Integration must retain the newer source and all previously approved security/lifecycle work. Current content/media is independently verified; no legal page is changed.

## Closure

Address LOW closed. **C0 / H0 / M2 / L1**. Remaining MEDIUM: independent backup/key custody; historical public-image metadata remediation. Remaining LOW: known catalogue content mismatch. Cash #753 owner Inbox/mobile confirmation remains evidence-only and was not resent. No payment/order lifecycle, main, production or legal-document change.
