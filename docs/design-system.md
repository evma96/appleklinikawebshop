# Design System

## Direction

The storefront should feel like a compact, trustworthy used-phone ecommerce site: white and light gray surfaces, clean cards, strong red price hierarchy, green trust and action elements, and restrained blue selection states.

## Typography Scale

- 12px: badges, helper text, compact metadata.
- 13px: secondary labels and small card metadata.
- 14px: navigation and compact product card titles.
- 15px: body copy and form text.
- 19px: compact section headings.
- 28-36px: product and homepage hero headings.

Hero-scale text should be used sparingly. Product cards, filters, checkout, cart, and footer areas should stay compact.

## Spacing Scale

- 4px: smallest helper gaps.
- 8px: compact control gaps.
- 12px: card internal spacing.
- 16px: standard content spacing.
- 20px: large component spacing.
- 28px: section-to-section spacing.

Avoid large empty gaps. Most storefront sections should fit within the 24-32px rhythm.

## Buttons

- Standard buttons: 40px tall.
- Primary ecommerce CTA: 44-48px tall.
- Border radius: 8px.
- Primary CTA color: green.
- Secondary buttons: white or light gray with a subtle border.

Buttons should feel tappable but not oversized.

## Cards

- Border: 1px solid light gray.
- Radius: 8px by default, 10px only for larger containers.
- Shadow: very subtle only, never heavy.
- Product cards should prioritize image, title, price, and stock/status metadata.
- Trust cards should be compact, readable, and aligned.

## Price Style

- Current prices use the shared red price color.
- Old prices are muted and crossed out.
- Savings/trust values use green.
- Prices should be displayed without decimals, with space-separated thousands and the currency at the end, for example `379 990 Ft`.
- Product-list prices stay compact around 15px.
- Product-page buying-panel prices can be larger, but should remain inside the compact ecommerce rhythm.

## Colors

- Background: white and near-white gray.
- Text: dark ink.
- Muted text: gray.
- Price/sale and cart checkout accents: Apple Klinika brand red (`#c9152d`).
- Trust/primary action: green.
- Selected state: blue.
- Borders: light gray.

## Layout Rules

- Use consistent max-width containers around 1060px.
- Desktop layouts should use balanced grids, not oversized panels.
- Mobile layouts should collapse to one clean column.
- Header, homepage, product page, cart, checkout, account, and footer should all use the same spacing, radius, and typography scale.
- The storefront header uses a two-row ecommerce structure: logo, centered search, account/cart actions, then Apple-focused category navigation.
- Shop filters should appear as a compact collapsible panel with custom checkbox/range controls, not default browser dropdowns.
- Product cards should keep title text to two readable lines, meta text compact, and the primary card button aligned at the bottom.
- Product option cards should reserve top-right space for the selected check icon and keep price differences on a readable single line where possible.
- Footer information pages use the reusable `ak-info-page` layout: centered max-width content, large page title, styled introductory paragraph, clean list/card blocks, subtle dividers, restrained highlight/quote boxes for important notes, and compact trust cards at the bottom.
- The Contact page extends the info-page layout with compact contact cards, a two-column form/map area on desktop, and a single-column layout on mobile.
- Cart pages use a custom Appleklinika two-card layout powered by real WooCommerce cart data: left product review card, right sticky order summary card, compact floating item cards, rounded white/glass-like surfaces, and a strong Apple Klinika brand-red checkout CTA.
