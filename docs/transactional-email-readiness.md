# Transactional email presentation and transport readiness

Date: 2026-10-01. Branch: `feature/local-fulfillment-backoffice-integration`.
Base checkpoint: `79be7f7e3de1f855929ff143ab8453f6c77072f9` (preserved).

## Current TEST SMTP acceptance — 2026-10-01

This section supersedes the earlier transport-not-configured/credential-pending
observations below; those remain as historical audit evidence.

**Final acceptance: PASS for this TEST SMTP and customer-email delivery run.**
Martin completed customer-side verification on 2026-10-01 without granting access
to his Gmail account. All three messages arrived in the normal Gmail Inbox, not
Spam. He confirmed correct mobile layout, typography, buttons, spacing and
Hungarian text, and that the actual received PDF attachment opens correctly.

| Verification | Result | Evidence |
| --- | --- | --- |
| Authenticated SMTP transport | PASS | Three messages accepted by Hetzner over authenticated STARTTLS. |
| Actual Gmail Inbox delivery | PASS | Martin confirmed all three in Inbox, none in Spam. |
| SPF | PASS | Gmail Authentication-Results on all three supplied message sources. |
| DKIM | PASS | Gmail Authentication-Results on all three; aligned `.hu` signing domain. |
| DMARC | PASS | Gmail Authentication-Results on all three; aligned sender identity. |
| Mobile rendering | PASS | Martin's visual check of layout, typography, buttons, spacing and Hungarian text. |
| Received PDF | PASS | Martin opened the actual attachment successfully. |

No further email-design changes are required. Accepted templates remain unchanged
at checkpoint `172366d22f83a4a5e73a533c257861e15adcf518`. This acceptance covers the
approved `.hu` QA sender and controlled Gmail delivery; the future production
sender and invoice-dependent order/provider E2E remain separate workstreams.

- Installed official WordPress.org **FluentSMTP 2.4.1** on TEST only. Archive SHA-256:
  `4932248e0fae299842ca0bd6b8dc71de7efef9b0302ffd7f29df39028673553f`.
- Hetzner `mail.your-server.de:587`, STARTTLS (`tls`), authenticated mailbox
  `info@appleklinika.hu`; forced From name `Apple Klinika`, From and Reply-To
  `info@appleklinika.hu`, matching return path. Martin approved this .hu identity
  for QA; the preferred future .com order sender is not yet established.
- Martin entered the existing mailbox password directly into the plugin's masked
  HTTPS field. It was not read out, copied into a plaintext file, logged or added
  to Git. Plugin-supported database encryption with WordPress SALT keys is enabled.
  No mailbox password reset, mailbox creation or DNS modification was performed.
- Three and only three controlled QA messages were submitted: basic WordPress
  plain email; accepted paid-order HTML/plain email with a clearly labelled
  fictional PDF attachment; accepted handoff HTML/plain email with two fictional
  tracking links. All three were accepted through authenticated TLS SMTP.
  Martin confirmed that all three arrived. This is owner-confirmed inbox receipt,
  not a model-inspected Gmail session. He explicitly elected to inspect Gmail
  himself; no access to his Gmail account is needed or used.
- **Received authentication PASS on all three:** Martin supplied Gmail's original
  source/headers for the basic, paid-order and shipping QA messages. Each
  `Authentication-Results: mx.google.com` reports `spf=pass`, `dkim=pass` and
  `dmarc=pass`. All three Message-IDs match the recorded outbound QA messages.
  Envelope sender/Return-Path, visible From and DKIM signing domain align with
  `appleklinika.hu`; the observed DKIM selector is `default2607`. DMARC `p=none`
  is a monitoring policy, not an authentication failure. No DNS correction is
  indicated for this tested identity, and no DNS change was made.
- Received paid/shipping sources contain their correct Hungarian subjects,
  personalized accents, HTML and plain-text alternatives, logo URL, account link
  and 0/2 tracking links, with no localhost URLs. This verifies received content,
  not visual rendering inside a native mail client.
- **Customer-side verification complete:** Martin confirmed normal Gmail Inbox
  placement for all three, correct mobile presentation and successful opening of
  the received PDF. The earlier pasted source omitted its base64 payload; the
  successful manual opening resolves that evidence limitation, with no attachment
  defect found. The recorded outbound MIME also contains a valid 1-page,
  1936-byte QA PDF. Raw headers, recipient addresses and signatures were not added
  to Git.
- Sent MIME verification passed: From/Reply-To, Hungarian subjects/accents,
  HTML plus useful plain fallback, 1/0 PDF attachments, 0/2 tracking links and no
  localhost URLs. Nine copied presentation/source files match checkpoint
  `172366d22f83a4a5e73a533c257861e15adcf518` byte-for-byte. They ran only as a
  private CLI fixture outside the active plugins, with unsaved Woo objects.
  The QA account link goes to the existing TEST My Account page because no
  persisted order exists; tracking numbers and PDF are fixtures, not provider proof.
- No Barion/GLS/Szamlazz API requests, real invoices/parcels, Woo orders or stock
  effects. The current application repository remains at
  `067d63cbbdd38dc52a95d14fb3bd6ec309088d2d`; the new invoice-dependent lifecycle
  was not deployed. Temporary server-side template fixtures were removed after
  private QA evidence was captured outside Git.
- **18 transport safety assertions passed after a normal TEST WordPress container
  restart:** ordinary sends held; explicit approved QA permitted; other/multiple
  recipients and CC/BCC held; missing SMTP and fallback configuration held;
  approved sender/Reply-To and encrypted storage intact. Failed/refund/new-account/
  password-reset Woo classes retain their standard `wp_mail` route (no customer
  mail sent). Homepage, WP REST and Back Office entry returned HTTP 200 afterward.
- The dedicated temporary TEST account had `read` plus only FluentSMTP-specific
  access, never Administrator, order management or general `manage_options`.
  Its account and sessions were deleted using WordPress/Woo APIs; the temporary
  capability helper was removed. No real account was changed.

### TEST-only transport safety and persistence

FluentSMTP is installed in the existing TEST plugins bind mount; its encrypted
settings live in the persistent TEST WordPress database. The mail safety MU file
lives in the persistent TEST WordPress content volume at
`wp-content/mu-plugins/appleklinika-test-mail-safety.php`. It intentionally holds
ordinary requests and cron mail. A controlled CLI verification must explicitly
opt in and use the sole owner-approved recipient, with no CC/BCC. The private
recipient is configuration, not repository documentation. No unauthenticated PHP
mail fallback is permitted if FluentSMTP or its authenticated configuration is
missing. SMTP protocol debug and email-body logs are disabled; blocked/failed
attempts emit only sanitized diagnostic categories.

The configuration and guard survived container restart. Keep the plugin and
persistent guard/settings when maintaining TEST. Do not relax the recipient hold
or enable production sends as part of a normal application deploy. Owner
visual/placement/PDF verification is now complete. The later invoice/provider
lifecycle run remains a separate gate.

## Provider correction and transport preflight — 2026-10-01

Martin confirmed that Apple Klinika's existing mailboxes are hosted on **Hetzner
Webhosting**. Use that mailbox infrastructure. The earlier Tárhely.com inference
below was based on public DNS and did not establish the actual mailbox provider;
it is superseded for SMTP selection. Do not use or request access to the
mHosting/Websupport control panel. The observed DNS records remain dated facts,
not proof of outbound sender authorization or message authentication results.

The owner-specified endpoint `mail.your-server.de:587` was checked from TEST via
`appleklinika-test`: STARTTLS, certificate verification and TLS 1.3 passed; SMTP
AUTH LOGIN/PLAIN was advertised. No authentication, sender probe or message send
was attempted. This proves network/TLS readiness only, not SMTP credentials or
inbox delivery. Use a full mailbox address as the SMTP username, as documented in
[Hetzner's official configuration guide](https://docs.hetzner.com/managed/email/set-up-email-account/setting-up-an-email-account/).

Authenticated konsoleH inspection subsequently found one hosting account with
**appleklinika.hu**, not appleklinika.com. Its existing mailboxes are `info`,
`szamlazas` and `webmaster`; the only listed forward is `postmaster` to `webmaster`.
The `info@appleklinika.hu` mailbox is active without automatic expiry. No
`rendeles`, `webshop` or `felvasarlas` mailbox/alias was found in this account.

Martin explicitly approved **info@appleklinika.hu** as both From and Reply-To for
this QA transport test, with From name `Apple Klinika`. This does not establish or
change the future production .com sender. No mailbox was created, modified or
reset. The existing mailbox password is not retrievable from konsoleH and was
not found in the inspected TEST/LOCAL private mail configuration. Credential
entry by Martin is the current manual step; no SMTP authentication or send has
occurred yet.

Public appleklinika.hu DNS observed during this account check: MX priority 10
`www749.your-server.de`; SPF `v=spf1 +a +mx ?all`; DMARC
`v=DMARC1;p=none;sp=none;pct=50;adkim=r;aspf=r;`. These records do not substitute for
received-message authentication results. No DNS changes were made.

The three controlled delivery messages must go only to the Martin-controlled QA
recipient supplied in this conversation. Keep the private recipient and mailbox
password out of Git and reports. Native receipt and SPF/DKIM/DMARC header checks
are pending. Do not deploy the invoice-dependent lifecycle for transport testing.

## Scope and current state

LOCAL presentation only for `paid_invoice` and `carrier_handoff`, plus read-only
TEST mail inspection and public DNS queries. No deployment, provider request,
actual mail, SMTP plugin installation, signup, DNS mutation or license change.
Existing payment/invoice/shipment workflows and their guards are unchanged.
Failed/refund/account/password and other Woo emails are unchanged.

### A. Application generation

The two existing `WC_Email` subclasses retain Woo sender configuration, settings,
localization, CSS inlining, `wp_mail` transport and the existing dispatch paths.
Their default is now multipart (HTML plus a useful text alternative); an explicit
saved Woo format still takes precedence. Only these two emails use the new local
HTML/plain templates. There is no global Woo header/footer/template override.

A read-only presentation adapter takes items (including variation metadata),
quantities, prices, taxes/discount totals, payment/shipping names and addresses
from the Woo order snapshot. It does not persist state, call providers or send.
The account URL uses Woo routing; logo and links use the running site URL, with
no hardcoded LOCAL or TEST hostname in customer templates. Local previews naturally
contain localhost URLs; their index clearly identifies fictional, unsaved data.

| Email | Contents and unchanged eligibility |
| --- | --- |
| Köszönjük, megkaptuk a rendelésed! | First-name/fallback greeting, order number/date, successful payment, items/quantities/totals, delivery or pickup instructions, invoice number/PDF attachment explanation, billing snapshot and account link. Dispatch still requires the paid managed order and readable resolved invoice PDF; the existing attachment code is unchanged. |
| Úton van a rendelésed! | Explicit actual GLS handoff wording, order number, all current validated/deduplicated tracking numbers and links, shipping destination and account link. The existing audited handoff gate remains required. Label generation, packing and label-ready states do not trigger this message. |

Pickup wording does not claim the order is ready now or promise a new pickup
email. Parcel-point wording refers to the selected point in order details and
does not present the billing/home address as the parcel destination. Shipping
copy does not promise an unverified arrival date. No marketing content is added.

The visual system uses the existing Apple Klinika logo, white/light tables,
restrained red buttons/accents, inline styling, a 600px maximum column, a narrow
screen rule and an Outlook conditional-width fallback. There are no scripts,
forms, external fonts or additional email frameworks. Image alt text remains
useful if the client blocks remote images.

### B. Actual transport audit

Read-only inspection of the running TEST WordPress/Woo environment through the
private SSH alias found:

- No active SMTP mail plugin; no configured SMTP constants or active SMTP hook.
- The isolated Buyback SMTP adapter exists in source, but its required TEST
  environment configuration is absent. It is not an authenticated transport for
  these Woo emails and was not changed.
- Effective Woo From name: `appleklinika.com`; effective From address uses
  `gmail.com`. The full mailbox/configuration is intentionally not copied here.
- PHP points to `/usr/sbin/sendmail -t -i`, but that executable is absent; no
  alternative sendmail/msmtp binary was found. The localhost:25 PHP ini defaults
  are not authenticated SMTP configuration.
- Woo header image is unset and its base color is the ordinary gray. The new
  scoped templates supply branding without changing those global settings.

**Authenticated transport is not ready. Actual inbox delivery is not proven.**
No test message was sent. Rendering success and the previous application
send-acceptance evidence do not demonstrate transport acceptance or inbox delivery.

### C. Public DNS audit

Observed on 2026-10-01 using public DNS only:

| Record | Observed result | Meaning / limitation |
| --- | --- | --- |
| MX | Priority 0 `mail.appleklinika.com`; priority 20 `mx2.postmaster.hu` | Hosting mailbox service with backup MX; not proof of an outbound SMTP subscription. |
| NS | `ns1.tarhely.com`, `ns2.tarhely.com` | Tárhely.com hosting/mail is the likely existing provider (inference). |
| SPF | One record: `v=spf1 +a +mx +ip4:185.111.89.243 include:mail.s58.tarhely.com ~all` | Authorizes the existing hosting paths; softfail policy. It does not authorize arbitrary future senders or authenticate a Gmail From identity. |
| DKIM | `default._domainkey.appleklinika.com` publishes an RSA 2048-bit public key | DNS key exists. No received signed message was inspected, so active signing, selector use and alignment remain unverified. Other selectors were not exhaustively enumerated. |
| DMARC | `v=DMARC1; p=none;` | Exists, but requests no enforcement and has no aggregate-report recipient. Not a missing-record finding. |
| TEST subdomain | No own MX/SPF or `_dmarc.teszt` TXT response | Not an approved TEST sending identity. Parent DMARC policy applies as appropriate. |

Inbound MX and an existing DKIM record do not establish authenticated outbound
transport. Gmail From on an unrelated web host is an identity/alignment risk.
Use an owned, verified domain sender rather than assume that the domain SPF
legitimizes the currently configured Gmail address. See Woo's
[email authentication guidance](https://woocommerce.com/document/email-authentication/)
and [email troubleshooting](https://woocommerce.com/document/email-faq/).

## Smallest sensible implementation plan — not applied

1. **Current choice:** the owner-confirmed existing Hetzner Webhosting SMTP service,
   provided the owner confirms a suitable account, supported TLS SMTP settings,
   transactional sending permission, volume limits, DKIM signing/alignment and
   an acceptable operational support/delivery policy. This avoids choosing a new
   paid service prematurely. DNS alone does not prove these capabilities.
2. **Deferred alternative only if Hetzner proves unsuitable:** a dedicated transactional SMTP service such as Postmark after
   Martin selects/authorizes the account and cost. Use a transactional stream,
   not a marketing stream. Woo can keep its current email generation while a
   maintained SMTP connector handles authentication; no need for a custom SMTP
   implementation. See [Woo transport options](https://woocommerce.com/document/email-smtp-providers/)
   and [Postmark's supported SMTP workflow](https://postmarkapp.com/manual).
3. Confirm an owned From mailbox (for example `rendeles@appleklinika.com`, only a
   proposal, not a claimed existing mailbox), From name `Apple Klinika`, and a
   monitored Reply-To. Obtain SMTP host, port, TLS mode and credentials through
   private configuration/secure entry, never repository files or chat logs.
4. Before enabling mail on TEST, select a TEST mail sink or a strict approved
   recipient allowlist. Keep real customer addresses out of TEST mail delivery.
   Do not silently enable all scheduled Woo mail when activating transport.
5. Add provider-issued DNS authentication only after explicit authorization.
   Preserve inbound MX and legitimate existing senders; maintain one SPF record,
   configure the chosen DKIM selector and aligned return path where required.
   Start DMARC reporting with an owner-approved recipient and validate legitimate
   senders before considering stronger enforcement. Do not blindly replace the
   current SPF or jump to a reject policy.
6. With transport authorized, send controlled QA messages to owner-approved test
   inboxes and verify SPF/DKIM/DMARC alignment in received headers, PDF attachment,
   native Gmail/Outlook/Apple Mail rendering, link access, bounce handling and
   delivery logs. Record application generation, SMTP acceptance and actual inbox
   arrival separately. This task does not claim any of those inbox checks passed.

Martin needs to confirm the sender/Reply-To and either existing hosting-mail
access/limits or approve a chosen transactional service. Credentials should be
entered privately. Any subsequent DNS changes need separate explicit approval;
no purchase or DNS edits are implied by this recommendation. Woo PRO activation
and the focused provider lifecycle acceptance remain a separate workstream.

## LOCAL evidence and reproducible previews

Prerequisite: the durable LOCAL integration runtime with its existing network/
mail guard enabled. Commands:

```sh
make test-email-presentation
make preview-order-emails
```

The second command copies render artifacts to ignored `.local-runtime/email-previews`
and serves only `127.0.0.1:18789`; stop with Ctrl-C. Five scenarios:
`paid-delivery`, `paid-pickup`, `paid-locker`, `shipped-single`, `shipped-multiple`.
Each has HTML and text. Orders/items are memory-only Woo objects, never saved;
there is no `send()`, payment event or provider invoice/parcel generation. The
synthetic invoice reference is presentation data, not proof of an issued PDF.

- **93 presentation assertions PASS:** actual Woo rendering/inlining, multipart
  alternative, saved-format compatibility, accents/escaping/fallback greeting,
  multiple/long items and metadata, quantities, exact item/subtotal/grand totals,
  invoice area, pickup/locker wording, account routing and deduplicated tracking.
- **0 orders saved, 0 mail attempts, 0 provider/network attempts** by the renderer.
- Browser checks: desktop (1280px), mobile (390px) and narrow (320px), both email
  types; no horizontal overflow. Totals, PDF area and links inspected. GLS link
  targets were checked without calling the external provider. Fictional account
  links are intentionally not backed by a stored order.
- `make test`: **301 focused offline assertions PASS**, plus PHP syntax checks.
  `make quality`: PASS, but the root lint/format/static targets remain placeholders;
  this is not a claim of comprehensive static analysis. The root unit/integration
  placeholders likewise do not add coverage beyond the named focused suites.
- No native email-client or actual recipient-inbox testing yet; browser previews
  alone cannot certify Outlook/Gmail/Apple Mail rendering or email delivery.

Prior defects and provider acceptance evidence remain in
[the lifecycle record](order-email-lifecycle.md) and
[LOCAL reconciliation](local-reconciliation.md); they were not rerun or erased.
