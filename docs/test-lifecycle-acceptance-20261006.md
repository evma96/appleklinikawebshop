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
