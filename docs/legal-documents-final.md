# Final VirtualJog PDF installation — 2026-10-16

These are owner-supplied authoritative PDFs. Never rewrite, correct, extract/rebuild,
merge, optimize, recompress or strip metadata from these binaries. Public domain/seat
wording inside the originals is preserved exactly, irrespective of technical TEST URLs.
This task installs on LOCAL and TEST only; it does not publish production or constitute
an independent legal assessment.

| Document | Original local filename | Version / accepted / updated | SHA-256 |
| --- | --- | --- | --- |
| Terms | `mpdf (1).pdf` | v2 / 2026-09-20 / 2026-09-29 | `6a86d96c62b29cf3da4c8027fab62295567bb8f811ebf2c8cd20cc5fa17ff71b` |
| Privacy | `mpdf.pdf` | v2 / 2026-09-20 / 2026-10-07 | `21efbe66c36ddb9dd90d06f6b57116eeb6c7539ca7943f758ae4224336ca3e2c` |

Source files were located in the owner's Downloads directory. Their first-page titles,
v2 footer and stated dates were checked; PDFs contain 34 and 31 pages respectively.
The PDF creation timestamps are 2026-10-07 and are not confused with their printed
acceptance/update dates. First pages were rendered for identity verification.

## Customer locations

- Terms: `/aszf/`, resolved to the existing Woo terms page. LOCAL page #2224, TEST #660.
- Privacy: retain the native WordPress privacy URL: LOCAL `/?page_id=2225`,
  TEST `/?page_id=661`. No new canonical privacy URL is invented.
- Existing `/adatvedelem/` compatibility route resolves the selected privacy page.
- Original legacy pages #36 (`aszf`) and #37 (`adatvedelem`) also display the same final
  PDFs instead of the older startup/sample content. Old ID links stay usable.
- PDFs: theme `assets/legal/virtualjog-terms-v2-20260929.pdf` and
  `assets/legal/virtualjog-privacy-v2-20261007.pdf`, byte-identical to the originals.

Each page provides a same-origin native PDF viewer, a direct open link and a download
link. Version/date labels are taken from the originals; no legal body text is extracted
into HTML. Direct links remain available when an embedded PDF renderer is unsupported.
The Codex embedded browser's inline PDF pane was blank without a script error; this is
recorded as a preview limitation, not proof that inline reading was visually verified.
HTTP-downloaded PDFs and the browser-downloaded terms PDF match the source hashes.
Native PDF readability was independently checked by rendering the unchanged originals.

Footer, Woo terms/privacy, account registration and Contact/Buyback references continue
to use the existing central registry/native WordPress options. No consent checkbox,
payment, order, email or provider workflow changes. The marked demo-only checkout notice
is removed; native checkout blocks/consent remain byte-identical after removing that
single wrapper. The other six legal sample documents are outside this two-PDF scope
and remain explicitly marked as samples; they are not claimed as finalized by this task.

## Explicit LOCAL / TEST content installation

The CLI-only helper is `tools/dev/install-virtualjog.php`. It is not loaded by WordPress,
not publicly served, and refuses all hosts/environments except local localhost:8082
and staging teszt.appleklinika.com. Deploy the theme source/assets first, then copy the
helper into a non-public container temporary path and invoke:

```sh
php /tmp/ak-install-virtualjog.php          # read-only plan / both PDF hashes validated
php /tmp/ak-install-virtualjog.php --apply # explicitly authorized content update
```

It validates every affected page before writes, refuses unexpected non-sample content,
keeps native page mappings/slugs, saves the original pages in a non-autoloaded private
WordPress backup option `_appleklinika_virtualjog_v2_previous` and WordPress revisions,
and uses only supported WordPress APIs. A repeated apply changes no pages. Remove the
container helper afterward. Older files/history/revisions are not unnecessarily deleted.
LOCAL Apache receives only the two legal aliases alongside its existing explicit routes;
TEST already routes those paths to WordPress, so its Apache configuration is unchanged.

Rollback is deliberate, never automatic: restore the saved page records through supported
WordPress APIs/editor revisions and the exact preceding theme source. Do not reset other
settings/pages or restore an entire database over newer orders. Never run the old demo
provisioner over these final pages; its content ownership check must remain effective.

## Focused verification

`make test-legal-pdfs` covers 15 offline assertions: both original hashes, open/download/
viewer identity, printed dates, invalid key, terms and unchanged privacy canonicals,
plain-permalink route compatibility and unrelated route preservation. Existing legal
infrastructure and checkout/company/finalization regressions are run separately.

Verify both environments in this order: LOCAL first, then exact approved TEST release.
Fetch `/aszf/`, native privacy/old ID URLs and both PDF links; compare SHA-256, MIME type,
status and redirects. Inspect the pages/footer at desktop and mobile widths, and inspect
Woo-generated checkout/registration links without submitting an order or accepting terms.
Do not claim a live transaction/inbox/provider test; none is needed here.

Deployment acceptance records and screenshots are retained outside Git in the task's
`virtualjog-final-documents-20261016` evidence directory. Main and production stay unchanged.
