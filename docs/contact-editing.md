# Contact page — LOCAL storefront polish

## Current review state

- Active worktree: `/private/tmp/appleklinika-homepage-body-redesign`.
- Branch: `feature/homepage-carousel-polish`.
- Previous homepage checkpoint: `6bb00605a5d12308a6275726e809e925066fea8d` (verified before and after the immediate-map change).
- LOCAL: `http://localhost:8082/?pagename=kapcsolat`.
- Martin approved the current Contact/map/directions state on 2026-09-11. The
  approved code and public settings are preserved in the feature-branch checkpoint;
  see [Approved LOCAL storefront](checkpoints/approved-storefront-2026-09-11/README.md).
  No push, merge, TEST access, deployment, production or main changes are included.
- The approved homepage, shared header, product-card renderer, checkout and Buyback
  remain unchanged by this Contact pass. Integrated order acceptance is not reopened.

## Editing without code

Open WordPress **Settings → Apple Klinika kapcsolat** (Hungarian:
**Beállítások → Apple Klinika kapcsolat**). Administrators can edit the page title,
intro, store name, city, postcode, street address, public phone, public email,
opening hours, navigation coordinates and map location. Save with
**Kapcsolati adatok mentése**.

The `appleklinika_contact_content` option stores these values through the native
WordPress Settings API with capability checks, a nonce and server-side sanitization.
An empty phone, email or hours field hides that public item. Empty required store
fields receive safe defaults. Unknown business details must not be invented.

The page with slug `kapcsolat` uses `templates/page-kapcsolat.html` and the dynamic
`appleklinika/contact` block. Its old sample post body remains stored but does not
render. A notice in that page's editor points to the settings screen. Public page
content is separate from the technical form recipient, which remains WordPress's
existing **Settings → General → Administration Email Address**.

The default address comes from Martin's request. The phone comes from the existing
owner-supplied `assets/images/home-service.jpg` artwork; it was also confirmed on
the public Apple Klinika store map. No public email or opening hours were assumed.

## Interactive map

At Martin's request, the map now uses Google's standard **Share → Embed a map**
iframe (`https://www.google.com/maps/embed?pb=...`) for the verified Apple Klinika
Szeged place, rather than Leaflet/OpenStreetMap. It is an interactive place map,
not a screenshot. This is the Google Maps website's copied share/embed solution,
not the separate Google Maps Platform Embed API (`/maps/embed/v1/...`). No API key,
Cloud project, billing setup, custom Google Maps API code or new plugin is needed.
The Leaflet JavaScript/CSS loader, unpkg dependency and OSM tile layer are removed.

For a changed map location, find the business in Google Maps, open **Share → Embed
a map → Copy HTML**, and paste the snippet into **Google Maps beágyazás** in the
existing Contact settings. Only a valid allowlisted HTTPS Google embed URL is
retained; arbitrary pasted HTML is never rendered. A previously stored OSM URL
falls back to the verified Google place without overwriting other Contact fields.
Update the street address, postcode and navigation coordinates too when moving the store.

Martin's latest instruction replaces the earlier two-click requirement. The server
renders the existing Google embed URL directly in a titled responsive iframe with
`loading="eager"`, `referrerpolicy="strict-origin-when-cross-origin"` and fullscreen
support. Loading starts with the Contact page, including below the mobile fold,
without a visitor click or JavaScript insertion. The existing desktop/mobile map
area and the rest of the Contact layout remain unchanged.

The manual-load placeholder, load/close buttons, consent copy, loading/retry states,
15-second timeout, focus transfer and their dedicated JavaScript/CSS are removed.
The Contact script now only enhances the existing mobile directions chooser.
No consent framework, API key, SDK or plugin is added.

Browser privacy or network restrictions may still block the external Google frame;
the presence of an iframe or a load event alone does not prove successful visual
map rendering. The always-available **Útvonaltervezés** links remain usable
independently of the embed and do not request location access on this page.

References: [Google Maps sharing and embedding](https://support.google.com/maps/answer/11471036?hl=en),
[cross-platform Maps URLs](https://developers.google.com/maps/documentation/urls/get-started).

## Mobile directions

At widths up to 800px, **Útvonaltervezés** opens a compact native disclosure with
Google Maps, Apple Maps and Waze links. On desktop the existing single Google Maps
directions button remains. No provider preference or user location is stored.
Native links and disclosure work without JavaScript; Escape, outside click, link
selection and switching to desktop dismiss the enhanced chooser.

Destination: **Apple Klinika, 6720 Szeged, Jósika utca 2–4.** The postcode follows
Martin's supplied address. Google Maps receives that full name/address. Apple Maps
and Waze receive the already-verified store pin **46.2544892,20.1426876**: Apple's
address-only lookup interpreted `2-4.` as `24.`, so it is deliberately not used for
that provider. The precise pin is editable in **Navigációs célpont koordinátái**;
copy latitude/longitude from the store position in Google Maps when changing it.
Providers can label the same point differently (for example, Waze names the adjacent
Hajnóczy street); their labels do not overwrite the configured business address.

The links use HTTPS universal/app links, never app-only custom URL schemes or
timeout-based app detection: Google `/maps/dir/?api=1&destination=...`, Apple
`/directions?destination=latitude,longitude`, and Waze `/ul?ll=...&navigate=yes`.
The provider/OS can open an installed app; otherwise the link remains a working
web destination. No app install, geolocation permission, API key or new SDK is
required by this page. Apple Maps and Waze web directions were opened with the
coordinate destination retained; Waze resolves the link to its live-map route UI.
Actual installed-app handoff on physical iOS/Android devices is not emulated.

Previous chooser verification: 40 PHP presentation/settings assertions and PHP/JS
syntax checks passed. LOCAL was visually inspected at 1440px and 390px: desktop
retains the direct Google button, mobile shows the three-provider disclosure, and
neither has horizontal overflow. Escape restores focus to the disclosure button;
outside click and selecting a provider close it. Selecting Google opened directions
to the actual Apple Klinika store. Google identifies the
same store as Jósika u. 4, 6722; the public address remains Martin's supplied
6720 Szeged, Jósika utca 2–4.

References: [Apple unified Maps URLs](https://developer.apple.com/documentation/mapkit/unified-map-urls),
[Waze deep links](https://developers.google.com/waze/deeplinks?hl=en).

## LOCAL verification — immediate map loading

2026-09-11, `http://localhost:8082/?pagename=kapcsolat`:

- **PASS: 44 Contact presentation/settings assertions.** The initial server HTML
  contains exactly one eager iframe with the unchanged configured Google embed
  URL, accessible title, referrer policy and fullscreen support. There is no hidden
  map ancestor, manual-load control or consent placeholder. An in-memory edit to
  the map source is reflected in the iframe. Exact navigation destinations and
  the native three-provider chooser remain covered. No fixtures are persisted,
  external HTTP is blocked, and mail is stubbed.
- **PASS: PHP syntax** for the modified Contact renderer and presentation test;
  whitespace checks cover the changes, including the untracked Contact files.
- **PASS: LOCAL desktop, 1440 × 1000.** The actual Google map, Apple Klinika place
  card and store pin rendered in the Codex in-app browser without a load click.
  The iframe fills the existing approximately 680 × 442px map area. Desktop keeps
  the direct Google directions link; document width is 1440px without overflow.
- **PASS: LOCAL mobile, 390 × 844.** The Google map and store pin also rendered
  without a load click. The map is 352 × 360px; document width is 390px without
  overflow. The existing chooser displays Google Maps, Apple Maps and Waze with
  the correct address/coordinate destinations. Escape closes it and restores
  summary focus; clicking outside closes it. The chooser JavaScript is preserved
  byte-for-byte. No provider link was opened and no installed-app handoff is claimed
  in this pass.
- **Browser limitation:** the blank external iframe reported during the earlier
  Google replacement did **not** recur in this pass. Actual Google imagery and
  the store pin were visually verified in both sizes. Martin's Safari or personal
  Chrome was not accessed. This is LOCAL browser evidence only.

The pre-existing uncommitted Contact work is preserved. Changes in this pass are
limited to `inc/contact-page.php`, `assets/css/contact.css`, `assets/js/contact.js`,
`tests/contact-presentation.php`, this document, `README.md` and `deficiencies.md`.
The existing `functions.php` changes and `templates/page-kapcsolat.html` are
unchanged. No branch switch, commit, push, merge, deployment or TEST access occurred.
The approved homepage and completed integrated order E2E were not modified or rerun.

## Previous Contact verification

The following results belong to the earlier Contact work and were not rerun for
this focused map correction. Previously, the Google iframe was blank in the Codex
preview while Martin confirmed correct rendering in his normal browser. The
original map-control checks are superseded by immediate loading above.

The earlier short storefront pass covered homepage desktop/mobile, the unchanged six-card
desktop row, iPhone listing and product page, empty cart, and logged-out account
entry. No customer sign-in, new order or payment/invoice/GLS retest was performed.
Existing LOCAL demo-product photography/data and legal draft content remain as-is;
they are not replaced with invented launch content. No broad storefront restyling
was warranted. That earlier pass also added truthful form mail-failure feedback.

The focused PHP test uses in-memory settings and mail stubs, blocks external HTTP,
and persists no test fixtures:

Original pass: 27 Contact presentation/settings assertions; stubbed mail success,
mail failure and invalid nonce checks; 112 homepage presentation assertions;
107 homepage carousel assertions; PHP/JavaScript syntax and `git diff --check` pass.
The required `make test` and `make quality` commands also ran, but currently report
that their broad suites/linters are not configured; the focused checks above are
the meaningful automated evidence for this pass.

```sh
docker exec ak-homepage-preview php /var/www/html/wp-content/themes/appleklinika-theme/tests/contact-presentation.php
docker exec ak-homepage-preview php /var/www/html/wp-content/themes/appleklinika-theme/tests/contact-presentation.php mail-success
docker exec ak-homepage-preview php /var/www/html/wp-content/themes/appleklinika-theme/tests/contact-presentation.php mail-failure
docker exec ak-homepage-preview php /var/www/html/wp-content/themes/appleklinika-theme/tests/contact-presentation.php invalid-nonce
```

Real SMTP delivery and publication of final public email/hours remain owner-approved
follow-ups, not prerequisites silently fabricated by this visual pass.
