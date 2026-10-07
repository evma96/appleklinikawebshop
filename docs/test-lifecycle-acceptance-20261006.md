# TEST lifecycle acceptance — 2026-10-06

## Scope and protected state

Round 2 checkpoint: `a3fb385cf8ed042d45a2a61e754913b8098ec43a`.
Approved integration deployment: `a8f912bd4d6d9141e40b34f208f026641aa2a25f`.
The protected fulfilment, email and security checkpoints remain ancestors. Main and production are outside scope.

## Dedicated staff-host defect

The actual TEST installation stores `home=https://teszt.appleklinika.com`, while `DedicatedHostUrls` correctly rewrites request-local URLs to `backoffice-teszt.appleklinika.com` for staff login/cookies. `LifecycleConfiguration` previously checked the rewritten `home_url()`, so it returned false on the dedicated host. Lifecycle hook registration, including handoff dispatch and Woo-admin correction, was disabled there. Native customer order links also inherited the staff origin.

The repair checks the canonical stored installation URL while retaining the explicit environment guard and existing host allowlist. Only the two lifecycle emails normalize their customer account/logo URLs back to stored storefront URLs. Staff navigation, authentication, cookie scope, provider behavior, templates and unrelated emails are unchanged.

## Verification

Focused regression includes dedicated-host lifecycle recognition, production/missing-canonical rejection, and actual Woo rendering under simulated staff URL filters. The latter uses memory-only fixture orders, blocks mail/network, and compares the whole customer email presentation snapshot with the canonical snapshot.

The genuine QA storefront order reached Barion sandbox success, one stock reduction, an automatic TEST/minta invoice with complete company/tax/house-number fields, and one accepted primary email. Martin confirmed receipt/PDF/mobile/authentication checks. Callback, native invoice-event and dispatch retries did not duplicate effects. Further fulfilment/GLS/shared-correction acceptance is pending this targeted host repair; do not interpret this document as a full lifecycle PASS.

Private provider references, recipient details, screenshots and operational evidence remain outside the public repository. No credentials are included here.

## GLS runtime PDF regression — 2026-10-07

The sandbox returned one parcel and tracking code, but PDF persistence silently failed because `WP_Filesystem()` selected `ftpsockets` against immutable code ownership. The writable label directory remained protected (0750, www-data). No second PrintLabels request was made.

The repair uses WordPress's native direct-filesystem adapter only for runtime label storage, writes 0640 PDFs, reports failure, and saves provider IDs before the PDF. Repeating a single-order request with any saved label/parcel/tracking reference stops before the provider. No global FS_METHOD, filesystem ownership, firewall, authentication, or protected document access is relaxed.

Offline regression uses the real WordPress filesystem adapter with a stub provider: successful storage and permissions, successful retry, partial write failure, retained provider IDs, invalid PDF, and four existing-reference guards. The existing parcel may be recovered using the official GLS [GetPrintData API](https://api.test.mygls.hu/docs/MyGLS_API.pdf); this retrieves already printed package data rather than creating another parcel. Private provider evidence remains outside Git.
