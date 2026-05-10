# Workflow

## Branching

- Work must happen on `feature/<scope-name>` branches.
- `main` is not used for direct development.

## Quality Gate

Before commit or push:

```bash
make test
make quality
```

## Functional UX Gate

Every storefront section must be checked with this question:

```text
Is this just visual, or does it actually work?
```

If a section should be interactive, it must use real WordPress/WooCommerce behavior instead of static markup. Acceptable examples:

- Product cards link to real WooCommerce product pages.
- Product prices, stock, and images come from WooCommerce.
- Gallery thumbnails, arrows, and lightbox navigation change real product images.
- Add-to-cart uses WooCommerce cart behavior and updates cart state.
- Header cart count reflects the real WooCommerce cart.
- Search submits to real WooCommerce product search.

Temporary placeholders must be explicitly documented in `deficiencies.md`.

## Manual UI QA Gate

After every frontend or UI change, run a browser-based manual UI QA pass and update:

```text
docs/ui-qa-checklist.md
```

Use only these statuses:

- `PASS` for items actually verified in the browser.
- `FAIL` for items verified in the browser and found broken.
- `NOT TESTED` for anything not checked during that implementation round.

`make check` only covers code-level workflow checks. Manual UI QA covers real webshop usability, including navigation, product pages, shop filters, layout proportions, cart, and checkout.

Fix `FAIL` items before starting unrelated new features.

## DOM/CSS Targeting Gate

When a visual CSS change does not appear in the browser, or when the project owner asks for the "red border test", verify the real DOM target before continuing.

Temporary test rule:

```css
body.post-type-archive-product .ak-product-card__inner,
body.woocommerce-shop .ak-product-card__inner {
  outline: 6px solid #D6001C !important;
  outline-offset: 4px !important;
}
```

Expected result:

- If the red border appears, the selector reaches the visible product card and styling can continue.
- If the red border does not appear, stop styling and inspect the rendered DOM/classes before editing more CSS.

After verification, remove the temporary rule and bump the CSS asset version.

## Local Development

Start the stack:

```bash
make up
```

Stop the stack:

```bash
make down
```

For the complete click-through verification workflow, see:

```text
docs/local-verification.md
```

## Environment

- Copy `.env.example` to `.env`.
- Keep keys consistent between both files.
- Store secrets only in `.env`.
