# Small LOCAL storefront polish

Date: 2026-09-11. Branch: `feature/homepage-carousel-polish`.
Approved-state checkpoint: `e51f111af75506e3ef379efce28cb30caccb2e69`.
The checkpoint includes the approved theme changes and the public homepage/Contact
settings and media in `checkpoints/approved-storefront-2026-09-11/`.

## Changes after the checkpoint

- Changed the LOCAL WooCommerce shop page (ID 7) title from `Shop` to `Termékek`
  through the native WordPress post API. Browser tabs now use Hungarian. Its slug,
  content and publication status are unchanged. This is a LOCAL database edit;
  it remains editable under Pages and is not a theme or migration change.
- Aligned registered legal-page titles with their existing 760px body column.
  A dedicated body class scopes the two CSS declarations to published documents
  in the existing legal-page registry. Typography, content and mobile width stay
  unchanged. At 1440px, the checked title and body now both start at x=340 with
  width 760px; previously the title started at x=236.

Martin approved these polish changes for commit and integration into
`develop/post-deploy`, followed by deployment and acceptance on TEST only.
The shop title is runtime content: apply `Termékek` to the target environment's
configured WooCommerce shop page without assuming LOCAL page ID 7 exists there.

## Verification

- Visually inspected LOCAL at 1440×1000 and 390×844: homepage, iPhone/MacBook
  listings, a representative iPhone product, empty cart, guest login/registration,
  Contact, footer and a legal information page. Also inspected mobile password
  recovery without submitting any form. No horizontal overflow was found on the
  sampled pages, and checked storefront images loaded.
- Confirmed the Hungarian shop browser title and the corrected desktop legal
  heading alignment. The legal page still wraps within the mobile viewport.
- Confirmed the embedded Google map renders and the mobile directions chooser
  opens and closes with Google Maps, Apple Maps and Waze links retained.
- Confirmed the checkpoint homepage options and resolved Contact content are
  unchanged (Contact currently uses the theme defaults rather than a saved option).
  The approved
  homepage/carousel/category content, header, product-card component, Contact
  implementation, checkout, Buyback page 1999 and integration E2E are unmodified.
- PHP syntax check and `git diff --check` pass for the small changes. Before the
  checkpoint, `make test` and `make quality` passed but reported placeholder
  suites; real homepage presentation (112), carousel (107), Contact (44) and
  stubbed Contact form success/failure/nonce checks also passed.

## Intentionally unchanged

- Legal documents retain their visible `TESZT / MINTASZÖVEG` content. Final
  approved legal text must be supplied separately; sample warnings were not hidden.
- LOCAL catalogue demo images/data remain, including the checked graphite iPhone
  product displaying a light-colored gallery photo. Product data, card design and
  existing E2E fixtures were not changed during this presentation-only pass.
- This was a short guest-facing visual inspection, not another signed-in account,
  checkout, payment, order or integration audit, nor a physical-device test.

LOCAL has Martin's final visual approval. The separately authorized TEST
deployment and audit must verify the exact integrated SHA and report the known
legal content blocker separately from application defects.
