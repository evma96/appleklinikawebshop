# Focused LOCAL pre-launch presentation polish

Base: `bd171ebda56627975baf30d69fbe63856c886a1c`.
Branch: `fix/prelaunch-visible-polish`. No remote environment work.

## Ownership and changes

- Header active state reads the current physical product's device-type metadata on
  PDPs. Archive navigation continues to use its existing category/query mapping.
  Unknown product types and unrelated pages do not inherit a false iPhone state.
- The existing filter form gains one mobile toggle. At <=639px it is initially
  collapsed, with `aria-expanded` reflecting native button clicks. Its current
  fields, GET submission and desktop column are unchanged. Without JavaScript,
  all filters remain usable. No new wrapper, observer, polling or CSS framework.
- No checkout state, Store API, pricing, inventory, gallery, Buyback or legal changes.
- Existing customer-facing `Grade` copy becomes `Állapot` / `Állapotbesorolás`
  in card chips and explanatory text; underlying grade values are untouched.

## Findings resolved in data or already inapplicable locally

122 published LOCAL QA products had `Demo - ` / `Selector teszt - ` title prefixes
and boilerplate implementation/test descriptions. Their existing SKU prefixes
confirmed fixture provenance. Woo product setters removed only those prefixes and
cleared the test-only long/short descriptions; no new product facts were invented.
All other Woo product data was compared unchanged, including images, prices,
stock, attributes and IDs. SKUs and URLs remain stable.

Restoration backup (not committed):
`/Users/apple/Desktop/appleklinikawebshop/.local/product-gallery-test/prelaunch-product-copy-before.json`.
These local changes do not propagate by deploying this branch.

LOCAL already uses `hu_HU` and installed official Woo PHP/JS language catalogues.
Native checkout showed `Szállítási opciók`, `Fizetési módok`, `Megrendelés`, Hungarian
stock/consent labels. No additional translation mapping, DOM replacement or
language-package modification was necessary. Remote language-pack configuration
was not inspected or changed in this LOCAL-only task.

All 24 distinct currently assigned LOCAL images were visually inspected: 12 real
iPhone photos and 12 other device demo images. All are upright. The old portrait
assets #521/#522 are not assigned to these published products. No source image,
derivative, attachment or image assignment needed modification.

## Evidence and checks

Evidence directory: `/private/tmp/prelaunch-visible-gmHTfc/`.
Before screenshots: `before-*.png`; final viewport screenshots: `after/*.png`.
The final screenshots and `image-orientation-{0,12}.png` were opened and personally
reviewed, not judged solely from DOM measurements.

- `tests/prelaunch-presentation.php`: 18 read-only navigation/localization checks.
- `tests/prelaunch-presentation.browser.cjs`: 115 checks at 1440/390px. Four category
  listings and PDPs, compact/expandable filters, native filter submission, resize back to desktop, no overflow,
  cleaned copy, actual assigned three-photo gallery #288, thumbnails, previous/next,
  native lightbox, zoom, close/reopen. No route-substituted gallery fixture.
- One-off native checkout smoke: 18 checks, Step 2→3→4 at both widths, valid company,
  tax and contact data retained, Hungarian rendered labels, marketing unchecked.
  Waited for shipping-rate rendering before final screenshots. No order submitted;
  both isolated QA carts emptied in `finally`.
- Product frontend 3; storefront 18; catalogue/search 45; cart/checkout 56;
  company 40; order finalization 40; information presentation 49; gallery renderer
  26; purchase renderer 68; purchase lifecycle browser 90.
- PHP and JS syntax, `git diff --check`, `make test`, `make quality`.
  The last two project targets currently report that no generic suites/tools are
  configured; they are not counted as additional regression checks.

The pre-existing LOCAL `/favicon.ico` 404 is explicitly logged separately by the
browser gate. There were no new console errors or JavaScript runtime exceptions.
Intentional legal-demo warnings and safe LOCAL payment labels are unchanged.

## Run the targeted gates

Execute `tests/prelaunch-presentation.php` in the local WordPress PHP environment.
Run the browser gate with Node/Playwright and `BASE_URL=http://localhost:8080`.
Optional paths: `PLAYWRIGHT_MODULE`, `CHROME_EXECUTABLE`, `EVIDENCE_DIR`.
The browser gate refuses non-local hosts and uses fresh isolated contexts.
