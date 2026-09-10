# Homepage Editing

The homepage body is maintained under **Settings → Apple Klinika homepage** in WordPress. Editing requires an administrator with `manage_options` permission. The shared header, catalogue product cards, prices, stock and purchase behaviour are not configured here.

## Content and images

1. Open the homepage settings screen and expand the section or row to edit.
2. Change titles, supporting text and button labels. Text fields accept plain text, not HTML.
3. Choose images from the WordPress media library with **Kép választása**. **Kép eltávolítása** uses the section's fallback image when available; it does not delete the media-library file. Hero and category fallbacks can use existing WooCommerce product photos. If no suitable product photo exists, the section remains usable without a broken image.
4. Set link destinations to the appropriate shop, product category or information page. Use normal site links or full `https://` URLs, never executable links. In-page destinations such as `#ak-home-offers` and `#ak-home-process` are supported. Leave a button label blank to hide that button.
5. Click **Főoldal mentése**, then open the homepage in a separate tab to check the result on desktop and mobile.

Content follows this fixed order: hero, categories, featured products, trust information, process. The process section intentionally has no action button.

Use a landscape campaign image around 1200 × 800 px for the hero, a square/portrait
product image around 600 × 600 px for category tiles, and a service photo at least
800 px wide. These are recommendations, not fixed upload requirements. The bundled
service source remains unmodified; its default crop excludes the embedded ad copy.
A Media Library replacement is not given that source-specific crop. No AI reference
screenshot is embedded as a website section.

## Rows and hero slides

Use **Új elem hozzáadása**, **Elem eltávolítása**, **Feljebb** and **Lejjebb** to manage rows. The saved row order becomes its display order. Limits are six hero slides, eight hero benefits, twelve categories, twelve trust items and ten process items. At least one default hero is retained if all hero rows are removed. Other lists may be intentionally empty.

Choose an icon from the provided list rather than pasting emoji or SVG markup. Keep hero titles short and check that button text describes its destination. An intentionally blank optional paragraph remains blank after saving.

## Featured products

The existing **Kiemelt Apple ajánlatok termékek** field accepts comma-separated WooCommerce product IDs. Their order is preserved, and only published products are shown. **Megjelenített termékek száma** accepts 1–12 (default: 6).

When no valid product selection is provided, the existing product query chooses featured products first, fills from sale products, then from recent products. Cards always display actual WooCommerce product data using the same renderer as the catalogue; homepage content fields cannot override product prices, condition details or stock.

## Verification

Run the focused local regression suite with:

```sh
make test-homepage
```

WordPress, WooCommerce and at least one published local product must be available. Tests cover sanitization, row limits, safe links, media fallback, section order, absence of a process CTA, unchanged shared header output and parity with real catalogue product cards. Existing local images are used for attachment-ID checks when available. Settings are overridden only in memory and restored when the test ends: no product, option or media fixtures are created, and HTTP requests are blocked.

Before accepting an edit, confirm that images are not stretched, text remains readable on mobile, hero navigation works with keyboard controls, and the homepage has no horizontal overflow. Product cards and the header should look and behave exactly as they do outside the homepage.
