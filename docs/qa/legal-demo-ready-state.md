# Visible legal demo state

Verified on 2026-09-06 against develop/deployed source
`de224e591630303d2bcddc5c14ec19433e53b940`.
Feature: `feat/legal-demo-ready-state`. No application deployment or production-code
change is required: this is explicit WordPress demo content/configuration provisioning.

## Persistent demo pages

Every title below ends with ` – TESZT`. Every page begins its content with
**TESZT / MINTASZÖVEG – NEM VÉGLEGES JOGI DOKUMENTUM**, followed by a warning that
the document only tests layout, links and consent flows and must be replaced before
launch. No legal deadlines, fees, rights or business commitments are invented.

| Document title, before the TESZT suffix | Slug | LOCAL ID | TEST ID |
| --- | --- | ---: | ---: |
| Általános Szerződési Feltételek | teszt-jogi-aszf | 2224 | 660 |
| Adatkezelési tájékoztató | teszt-jogi-adatkezeles | 2225 | 661 |
| Cookie-tájékoztató | teszt-jogi-cookie | 2226 | 662 |
| Elállás és visszaküldés | teszt-jogi-elallas | 2227 | 663 |
| Jótállás és szavatosság | teszt-jogi-jotallas | 2228 | 664 |
| Szállítás és fizetés | teszt-jogi-szallitas-fizetes | 2229 | 665 |
| Marketing-hozzájárulási tájékoztató | teszt-jogi-marketing | 2230 | 666 |
| Felvásárlási feltételek | teszt-jogi-felvasarlas | 2231 | 667 |

Open pages using `http://localhost:8080/?page_id=ID` or
`https://teszt.appleklinika.com/?page_id=ID`. Current permalinks use page IDs; the
stored slugs above are not hardcoded into templates.

The existing central resolver supplies all links. Native options
`woocommerce_terms_page_id` and `wp_page_for_privacy_policy` select the first two
pages. Other keys use existing `appleklinika_legal_page_{key}` settings:
`cookies`, `withdrawal`, `warranty`, `shipping_payment`, `marketing`, `buyback_terms`.
The original privacy page #3 remains a draft, with its content untouched in both
environments; only its former privacy-option mapping was replaced after inspection.

## Explicit provisioning

The CLI-only helper is `tools/dev/provision-legal-demo.php`. Copy the three PHP
files in that directory together to a non-public directory inside the appropriate
WordPress container. Run there, with WordPress at `/var/www/html/wp-load.php`:

```sh
# Read-only plan; LOCAL currently has a legacy production environment label.
php /tmp/ak-legal-demo/provision-legal-demo.php --allow-local-production-label

# LOCAL, explicit apply. The privacy replacement flag is only for the reviewed
# original draft mapping, not permission to overwrite that page's content.
php /tmp/ak-legal-demo/provision-legal-demo.php --apply --allow-local-production-label --replace-unpublished-privacy=3

# TEST SERVER uses environment=staging; never use a production target.
php /tmp/ak-legal-demo/provision-legal-demo.php --apply --replace-unpublished-privacy=3

php /tmp/ak-legal-demo/test-legal-demo.php
php /tmp/ak-legal-demo/verify-legal-demo-state.php
```

`AK_WORDPRESS_LOAD` can point to the existing WordPress bootstrap when needed.
No new runtime configuration key is required by the application. The helper is not
included by a theme/plugin, not part of deployment, and never seeds on page load.

Safety properties:

- Exact allowed hosts only: LOCAL localhost:8080 or staging teszt.appleklinika.com.
  The LOCAL label exception never permits a production hostname or production-labelled
  TEST SERVER.
- Without `--apply`, no content/settings are written.
- Stable `_ak_legal_demo_key` ownership, exact title/slug and stored content hashes
  prevent silently overwriting manually edited/final pages. Conflicting mappings,
  non-demo slug collisions and duplicate ownership stop the operation.
- `_ak_legal_demo_baseline_v1` preserves original mapping/registration/checkout
  configuration; `_ak_legal_demo_original_custom_css` preserves original Additional CSS.
- Repeated apply leaves page IDs, content, modified timestamps, checkout content,
  CSS and mappings unchanged. Verified using before/after snapshots of all pages
  and custom-CSS posts on both environments. WordPress-sanitized HTML is compared,
  avoiding unnecessary rewrites when KSES strips unsupported CSS properties.

Only supported WordPress APIs are used. No raw SQL or direct database-row update.

## Demo configuration, not new application architecture

- Checkout page #9 uses the existing native Woo Terms block with `checkbox: true`.
- A normal page-content notice above the Woo checkout explains the optional marketing
  field and links to its centrally resolved information page. It is marked with
  `ak-legal-demo-reference:start/end`; no React-owned field is moved or recreated.
- Account registration is enabled through the existing Woo option so the already
  implemented privacy/marketing UI can be seen. No new customer system is introduced.
- Normal WordPress Additional CSS corrects the marketing checkbox's inherited tall
  input dimensions and keeps the Buyback marketing link in a readable text flow on
  mobile. The tightly scoped `ak-legal-demo:start/end` block is demo configuration.
  Custom-CSS post IDs: LOCAL #2243, TEST #679. Existing unrelated CSS is preserved.
- No checkout store/Store API/order/pricing/consent implementation changed. No external
  mailing-list subscription was made.

## Browser acceptance

Native Playwright fill/click interactions in fresh, isolated browser contexts;
LOCAL and TEST SERVER at **1440px** and **390px**. Screenshots were actually opened
and visually reviewed, not only DOM-asserted.

- Footer: eight unique configured links, all eight pages HTTP 200, no missing document.
- Legal pages: clear test warning, readable long-page headings/paragraphs/lists/links,
  consistent header/footer and no horizontal overflow.
- Checkout: COMPANY OFF hidden fields not required; ON company/tax required. Native
  entry, three postcode/Woo updates and Step 2 → 3 → 4 preserve company, tax, email,
  both phones and both addresses. No duplicate controls or browser console/page errors.
- Terms unchecked initially. Clicking the order action while unchecked shows Woo's
  blocking validation and does not submit an order. Checking clears that validation
  and enables normal continuation; no accepted order action was clicked.
- Marketing is a separate unchecked, non-required field. Check/uncheck updates Woo's
  additional fields correctly; declining does not block Step 2 → 3 → 4. Existing
  order adapter tested with an **unsaved** WC_Order: accepted => metadata `1`, refused
  => absent metadata, persisted order ID remains zero.
- Buyback: three configured legal links, required consent blocks unchecked submission;
  checking produces a valid normal submission form, but no request was submitted.
  Marketing remains optional and does not alter calculated offers. Server-side required
  consent and normal submission are covered by the isolated public-submission tests.
- Registration: privacy and marketing links resolve; native email entry leaves the
  form valid with marketing unchecked, checked and unchecked again. No registration
  was submitted or customer created.

The first browser harness incorrectly classified Woo's
`__experimental_calc_totals=true` draft/totals request as order placement and aborted
it. The harness was corrected to distinguish calculation from actual submission;
the identical acceptance then passed both environments/widths. This was a test guard
issue, not a storefront failure. TEST SERVER's existing English Woo core labels were
recorded, not silently claimed to be translated by this demo task.

## Screenshot evidence

Local evidence files use `/private/tmp/legal-{local|test}-{1440|390}-` followed by:

- `footer.png`
- `long.png`
- `register.png`
- `buyback.png` (final corrected mobile text flow)
- `checkout-marketing-final.png` (final 20px checkbox)
- `checkout-terms-final.png` (visible native checkbox and links)

Examples: `/private/tmp/legal-test-390-buyback.png`,
`/private/tmp/legal-test-1440-long.png`,
`/private/tmp/legal-local-1440-checkout-terms-final.png`.
These are local QA artifacts, not deployed media or committed private session files.
Earlier full-page checkout screenshots can show the sticky header at the capture's
scroll position; final legal-area screenshots use viewport/element capture instead.

## Results and cleanup

All passed on LOCAL and TEST SERVER:

| Suite | Assertions per environment |
| --- | ---: |
| Legal infrastructure | 9 |
| Cart/checkout | 56 |
| Company contract | 40 |
| Order finalization | 40 |
| Buyback public submission | 25 |
| Buyback pricing engine | 150 |
| Buyback public active pricebook | 66 |
| Demo provisioning unit checks | 38 |
| Configured demo-state checks | 51 |
| Total | 575 |

PHP lint, unchanged frontend JavaScript syntax and `git diff --check` pass.
`make test` and `make quality` run too, but currently only print placeholder messages;
the actual assertion evidence is the explicit suites above, not those placeholders.

No checkout order, registration or browser Buyback request was submitted. Exactly the
own transient QA checkout drafts LOCAL #2242 and TEST #678 were deleted with Woo CRUD.
Their carts were emptied through Woo Store API after exact product/quantity/email
verification; their exact guest sessions were removed with WC_Session_Handler.
Both QA email queries return zero orders/drafts; both sessions are absent; product
#476 stock remains 2. No unrelated orders, users, sessions or pricebooks were cleaned.
The eight demo pages, configured mappings, registration visibility, native terms
checkbox, demo notice and CSS **remain intentionally available for manual testing**.

TEST pricebook #1999 remains active, name `Felvásárlási árkönyv – v21`, version 21,
926 rules. Before/after book SHA256:
`ceeccb6ce7c5012afe3e36f05b3adf3a0c405b2bc9dd903999d0ac1e80f35de4`.
Rule SHA256:
`1cd90c4eadf47aa47ba024f5d85675170bb89b64bd8a6c03c3631b6b3f4abadc`.
The iPhone 13 / 128 GB reference stays 32,000 / 33,000 / 35,000 / 37,000 Ft.

## Replacing demo text before launch

1. Obtain approved real legal/business documents; this helper supplies none.
2. Through the normal WordPress page editor, replace the existing demo page content
   and title (remove TESZT) with the approved final version. Keeping IDs preserves
   central references. Alternatively create final pages and explicitly select them
   under Settings → Jogi dokumentumok; verify native Woo/WP mappings as well.
3. Edited demo pages are deliberately protected from later helper overwrites. Do not
   reset their ownership/content hashes to bypass this protection.
4. Remove the marked demo notice from checkout page #9 using the page editor. Keep
   native required Terms acceptance enabled and configure actual final documents.
5. Review the marked Additional CSS in the normal WordPress CSS editor. Remove demo
   styling if no longer required; retain unrelated CSS. Do not blindly restore an
   old baseline over later approved settings. Any production styling port requires
   its own reviewed source change, not copying the test database.
6. Never import these dummy pages/settings into production or run the demo provisioner
   there. Do not treat this UI PASS as legal approval or full-order E2E acceptance.

No merge, main access, production access, application deployment or business-data
change was performed by this task.
