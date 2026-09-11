# Homepage Editing

The homepage body is maintained under **Settings → Apple Klinika homepage** in WordPress. Editing requires an administrator with `manage_options` permission. The shared header, catalogue product cards, prices, stock and purchase behaviour are not configured here.

The artwork carousel and denser homepage product grid described below are LOCAL-only. Martin approved the current homepage, eight-slide set and category photos on 2026-09-11. No TEST or production deployment or migration is implied. Live media and settings remain environment-specific data; their approved recovery copy is tracked in [Approved LOCAL storefront](checkpoints/approved-storefront-2026-09-11/README.md).

## Content and images

1. Open the homepage settings screen and expand the section or row to edit.
2. Change titles, supporting text and button labels. Text fields accept plain text, not HTML.
3. Choose images from the WordPress media library with **Kép választása**. **Kép eltávolítása** does not delete the media-library file. Split-layout hero and category tiles use a fallback product photo when available. Artwork-mode slides need their own image and destination and are omitted when either is missing.
4. Set link destinations to the appropriate shop, product category or information page. Use normal site links or full `https://` URLs, never executable links. In-page destinations such as `#ak-home-offers` and `#ak-home-process` are supported. Leave a button label blank to hide that button.
5. Click **Főoldal mentése**, then open the homepage in a separate tab to check the result on desktop and mobile.

Content follows this fixed order: hero, categories, featured products, trust information, process. The process section intentionally has no action button.

For the split layout, use a landscape image around 1200 × 800 px; for image-only
artwork, upload the finished campaign image at its intended aspect ratio, with its
copy and visual buttons already inside the image. Use a square/portrait
product image around 600 × 600 px for category tiles, and a service photo at least
800 px wide. These are recommendations, not fixed upload requirements. The bundled
service source remains unmodified; its default crop excludes the embedded ad copy.
A Media Library replacement is not given that source-specific crop. No AI reference
screenshot is embedded as a website section.

## Rows and hero slides

Use **Új elem hozzáadása**, **Elem eltávolítása**, **Feljebb** and **Lejjebb** to manage rows. The saved row order becomes its display order. Limits are eight hero slides, eight hero benefits, twelve categories, twelve trust items and ten process items. The settings retain at least one default hero row if every row is removed; this does not force an incomplete artwork slide to appear. Other lists may be intentionally empty.

Choose an icon from the provided list rather than pasting emoji or SVG markup. Keep hero titles short and check that button text describes its destination. An intentionally blank optional paragraph remains blank after saving.

## Hero layout and carousel

Under **Nyitó szakasz megjelenése**, select **Külön szöveg és kép** to keep independently editable text, buttons and an image. Existing saved homepages remain in this mode unless it is explicitly changed; older rows are enabled by default. Switching modes does not erase the stored split copy or hero benefits.

For image-only artwork, choose **Kész képes banner**. Each enabled row supplies one media-library image, **Hivatkozás** and **Alternatív szöveg**. **Dia neve (belső cím)** helps identify the row in the editor. The entire image is the link. Separate titles, paragraphs, buttons and benefit tiles are not overlaid on the artwork. Alternative text should describe the campaign and destination, especially any meaningful text embedded in the image.

Set **Megjelenítés** to **Kikapcsolva** to keep a row saved but remove it from the carousel; **Bekapcsolva** enables it again. Artwork rows without an image or safe destination are also skipped. The remaining rows keep their configured order. If no artwork row qualifies, the hero is omitted; disabled campaigns are not replaced by fallback content. One visible artwork slide does not show unnecessary carousel controls.

With multiple slides, the carousel advances every 5.5 seconds. Visitors can select a position dot or use a horizontal swipe, and can pause or resume automatic rotation. Selecting a dot starts a fresh 5.5-second interval. Hovering or leaving focus on a dot does not stop the loop; focus on a linked slide and a hidden browser tab pause rotation. Reduced-motion preference starts the carousel without automatic rotation. Verify both layouts after editing; artwork must remain readable and uncropped on narrow screens.

Position dots at the bottom of the image are the only permanently visible carousel controls. There are no previous/next arrows or separate navigation bar below the banner. The pause/resume button is visually discreet and reveals itself when reached with the keyboard; it remains available to assistive technology. Leave visual breathing room near the bottom of the artwork so dots do not obscure important campaign content.

## Featured products

The existing **Kiemelt Apple ajánlatok termékek** field accepts comma-separated WooCommerce product IDs. Their order is preserved, and only published products are shown. **Megjelenített termékek száma** accepts 1–12 (default: 6).

When no valid product selection is provided, the existing product query chooses featured products first, fills from sale products, then from recent products. Cards always display actual WooCommerce product data using the same renderer as the catalogue; homepage content fields cannot override product prices, condition details or stock.

The LOCAL homepage grid uses six columns above 1100 px, three at 701–1100 px, two at 521–700 px and one at 520 px or below. This changes only homepage spacing and column layout, not the shared card design or catalogue grid. The product count control remains independent of the number of columns.

Homepage-only row alignment keeps the existing image, title, price and action sizes, allowing metadata space to absorb differences between cards. Equal grid rows also prevent small natural-height differences between vertically stacked mobile cards; no fixed outer card height is imposed. The current six-product LOCAL selection was measured as follows:

| Viewport | Columns | Each card |
| --- | --- | --- |
| 1440 px | 6 | About 210.66 × 520 px |
| 900 px | 3 | About 271 × 488 px |
| 390 px | 1 | 354 × 488 px |

The image area remained 220 px and the action 40 px. Price/action positions aligned without wishlist overlap; mobile preserved a 14 px wishlist-to-action gap with no horizontal overflow. The catalogue's existing hover lift is intentional and does not change a card's layout position. These measurements describe the current local selection, not a fixed size guarantee for every product or screen.

## LOCAL visual-content review — 2026-09-11

The existing four approved hero rows (media IDs 2444–2447) retain their complete
content, destinations, enabled state and order. Four supplied banners are appended
in the requested order, producing eight enabled, editable rows:

| Position | Supplied banner | LOCAL media ID | Destination |
| --- | --- | --- | --- |
| 5 | Hozd a régit, vidd az újat! — dark version | 2448 | `/eladas/` |
| 6 | Gyors és profi Apple szerviz | 2449 | `/?pagename=kapcsolat` |
| 7 | Hozd a régit, vidd az újat! — white version | 2450 | `/eladas/` |
| 8 | Prémium Apple készülékek | 2451 | `#ak-home-offers` |

The hero row limit is raised from six to eight in the shared settings schema and
editor copy. The renderer, carousel script, dots, 5.5-second interval and responsive
styles remain unchanged. The supplied 2048 × 819 banners are displayed as images
in the existing uncropped artwork frame; no new text or controls are overlaid.

Only `image_id` changes in these **Mit keresel?** rows:

| Category | Supplied source | LOCAL media ID |
| --- | --- | --- |
| MacBook | `IMG_0910.JPEG` | 2452 |
| iPad | `IMG_0823.JPEG` | 2453 |
| Apple Watch | `IMG_0892.JPEG` | 2454 |

The iPhone row and its existing catalogue fallback image remain unchanged. All
category titles, supporting copy, order, destinations and card behavior are
preserved. The seven supplied originals are retained byte-for-byte in the LOCAL
uploads folder `2026/09/ak-visual-review/`; standard WordPress derivatives provide
orientation-correct, appropriately sized images. Media and selections remain
editable in the existing homepage settings flow and are not Git-tracked content.
No generated replacement imagery was used.

Verification: 112 homepage presentation assertions and 107 carousel assertions
passed, along with PHP syntax and whitespace checks. The actual settings renderer
contains eight hero rows; an in-memory reversed order survives sanitization without
losing any of them. Browser checks at 1440 × 1000 and 390 × 844 verified all four
new banners through their dots, all three supplied category photos, unchanged
iPhone source and category links, and no horizontal overflow. Automatic rotation
continued beyond the eighth slide into the existing slides. The LOCAL homepage
is left for Martin's visual review.

Later visual polish may refine the small text embedded in the wide mobile banners
or the framing of the supplied labelled product photos, based on Martin's review.
This pass preserves the supplied visual content and current layout. Existing
uncommitted Contact work is unchanged. No TEST access, commit, merge, push or
deployment was performed; checkout, Buyback behavior and integrated E2E were not
modified or rerun.

## Verification

Run the focused local regression suite with:

```sh
make test-homepage
node wordpress/wp-content/themes/appleklinika-theme/tests/homepage-carousel.cjs
```

WordPress, WooCommerce and at least one published local product must be available. Tests cover sanitization, row limits, safe links, media fallback, section order, absence of a process CTA, unchanged shared header output and parity with real catalogue product cards. Existing local images are used for attachment-ID checks when available. Settings are overridden only in memory and restored when the test ends: no product, option or media fixtures are created, and HTTP requests are blocked.

Artwork checks additionally cover enabled-row filtering, image and URL requirements, order, safe alternative text, image-only links, absent overlays and legacy split compatibility. They require an existing local media-library image; no test image is uploaded. Focused stylesheet checks guard overlay hit-testing, focus-revealed controls and homepage-only card alignment; actual card geometry still requires browser measurement. The dependency-free Node harness runs the actual carousel script with simulated DOM and timers, covering rotation, manual navigation, pause, reduced motion, visibility and swipe/click suppression. It does not replace browser verification of visual layout and real input behaviour.

Before accepting an edit, confirm that images are not stretched, text remains readable on mobile, hero navigation works with keyboard controls, and the homepage has no horizontal overflow. Product cards and the header should look and behave exactly as they do outside the homepage.
