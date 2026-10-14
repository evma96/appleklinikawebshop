# Targeted checkout configuration closure — 2026-10-14

Baseline: `b7769e996b7d4926ca05dc4b5371b3bc2ce277da`.

## Scope

- Native Woo `free_shipping:1`, Hungary zone 1: disable using the Woo shipping-zone REST controller. Do not delete the method or change its stored fee settings. Woo shipping cache invalidation must also cover existing sessions. Personal pickup remains enabled at zero cost.
- Preserve GLS home 1990 HUF, GLS point/locker 1490 HUF, manual MPL home 2090 HUF and MPL postal/partner/terminal 1040 HUF. No provider or shipment call.
- Checkout summary reads the configured store address for canonical `local_pickup` IDs; the final review uses a personal-pickup heading and method edit action. Billing remains the customer address. Courier fields, payment and submission logic stay unchanged.
- Contact defaults and TEST public content: 6722 Szeged, Jósika utca 4., matching the previously verified Google place `0x474489111d25d185:0xf8dc8aa6ed1595e7`. Existing coordinates `46.2544892,20.1426876` and embed are unchanged. Contact, Google directions, checkout pickup and existing pickup-email address consumption share the same public setting.

## Bank transfer — intentionally removed by owner decision

On 2026-10-14 the owner decided not to offer bank transfer. The supported WordPress option API changed only `woocommerce_bacs_settings.enabled` to `no`, `description` to an empty string and `instructions` to an empty string on TEST. The existing empty account list is unchanged. No bank information is required. Native gateway code and historical order handling are preserved.

Actual TEST desktop (1280×900) and mobile (390×844) checks confirm no BACS, Barion for delivery/pickup, cash only for personal pickup, all GLS/MPL prices unchanged and no generic free courier. Personal pickup proceeds to the final review at 0 HUF with the configured store address. No order was submitted, email sent or provider action invoked. QA customer 595, checkout draft 757 and session/cart were deleted through supported APIs. All 73 original orders, 16 original users, stock and protected provider/mail/address/homepage settings match the pre-check baseline. Browser error log and relevant PHP runtime window contain no errors.

This follow-up changes TEST configuration only. Feature/develop/deployed TEST application SHA remains `44f65cfbf81a4554d727730316d2c596ef041972`. No commit, push or redeployment is needed for the setting; this documentation follow-up is included in the final readiness record. Previous source-regression proofs below remain applicable.

## Current TEST address matrix — owner-confirmed, followed by content correction

| Surface | Current configuration |
| --- | --- |
| Contact page and saved Contact settings | 6722 Szeged, Jósika utca 4. |
| Checkout personal pickup and final review | Apple Klinika, 6722 Szeged, Jósika utca 4.; consumes Contact settings |
| Google directions | Apple Klinika, 6722 Szeged, Jósika utca 4. |
| Google embedded destination | Apple Klinika Szeged business place `0x474489111d25d185:0xf8dc8aa6ed1595e7`; URL has no independent postal-address text |
| Apple Maps / Waze | Coordinates `46.2544892,20.1426876` |
| Homepage first hero image alt (saved and rendered) | “Szegedi üzlet: 6722 Szeged, Jósika utca 4.” |
| Dedicated homepage address field | None in the homepage settings schema |
| Woo general store address/city/postcode | Empty; active pickup consumes the Contact setting instead |

The BACS task left addresses unchanged. The owner then confirmed 6722 Szeged, Jósika utca 4. for the physical store/pickup. The subsequent address-only task corrected the first banner alt/media metadata and address artwork in slides 1, 2 and 4 on LOCAL and TEST, plus the stale LOCAL address settings/defaults. Coordinates, TEST Contact values, registered seat and legal documents are unchanged. See `store-address-consistency.md`.

## Verification

Before release: required offline `make test` (483 assertions), `make quality` (placeholder lint/static targets), Contact 44 assertions, checkout final UX 32 assertions, focused pickup renderer/sidebar 11 assertions, PHP/JS syntax and diff review. A supported LOCAL BACS fixture confirms on-hold/unpaid/no paid date, configured transfer instructions/account rendering, no invoice and zero provider calls; the fixture is deleted without sending mail. LOCAL browser uses a temporary loopback-only candidate container with existing provider/mail guard; temporary config changes are restored and QA customer/cart/draft removed. LOCAL database predates TEST MPL/COD configuration; the final rate/payment matrix is verified on actual TEST.

TEST acceptance must confirm exact deployed source, unchanged provider/mail hashes, the intended shipping options (including the postal point subtypes), no generic zero-fee courier, correct pickup summary and canonical Contact address at desktop/mobile widths. Browser navigation must not place an order or invoke payment/invoice/shipment. Existing #753 technical email evidence remains valid; owner Inbox/mobile confirmation is still pending. QA cleanup uses Woo/WordPress APIs, not raw SQL.

## Remaining closure status

C0 / H0 / M2 / L1. Missing BACS bank data is resolved by intentional removal. MEDIUM: independent backup target/off-Mac key custody; historical public-image private metadata. LOW: known demo catalogue image/content mismatch. The store-address LOW is closed after owner confirmation and targeted LOCAL/TEST verification. NON-LEGAL CONDITIONAL GO for tested checkout, subject to these remaining production-readiness actions; it is not production approval. Cash #753 still awaits only owner Inbox/mobile confirmation, with no resend. Existing security, monitoring, provider and email delivery proofs are retained. Legal documents are outside this task.
