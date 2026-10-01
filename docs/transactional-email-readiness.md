# Transactional email presentation and transport readiness

Date: 2026-10-01. Branch: `feature/local-fulfillment-backoffice-integration`.
Base checkpoint: `79be7f7e3de1f855929ff143ab8453f6c77072f9` (preserved).

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

1. **First choice to evaluate:** the existing Tárhely.com mailbox SMTP service,
   provided the owner confirms a suitable account, supported TLS SMTP settings,
   transactional sending permission, volume limits, DKIM signing/alignment and
   an acceptable operational support/delivery policy. This avoids choosing a new
   paid service prematurely. DNS alone does not prove these capabilities.
2. **Alternative:** a dedicated transactional SMTP service such as Postmark after
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
