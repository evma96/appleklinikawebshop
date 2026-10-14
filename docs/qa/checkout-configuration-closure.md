# Targeted checkout configuration closure — 2026-10-14

Baseline: `b7769e996b7d4926ca05dc4b5371b3bc2ce277da`.

## Scope

- Native Woo `free_shipping:1`, Hungary zone 1: disable using the Woo shipping-zone REST controller. Do not delete the method or change its stored fee settings. Woo shipping cache invalidation must also cover existing sessions. Personal pickup remains enabled at zero cost.
- Preserve GLS home 1990 HUF, GLS point/locker 1490 HUF, manual MPL home 2090 HUF and MPL postal/partner/terminal 1040 HUF. No provider or shipment call.
- Checkout summary reads the configured store address for canonical `local_pickup` IDs; the final review uses a personal-pickup heading and method edit action. Billing remains the customer address. Courier fields, payment and submission logic stay unchanged.
- Contact defaults and TEST public content: 6722 Szeged, Jósika utca 4., matching the previously verified Google place `0x474489111d25d185:0xf8dc8aa6ed1595e7`. Existing coordinates `46.2544892,20.1426876` and embed are unchanged. Contact, Google directions, checkout pickup and existing pickup-email address consumption share the same public setting.

## Bank transfer — owner data required

TEST BACS is enabled; native `woocommerce_bacs_accounts` is empty. No bank data exists in the inspected TEST Szamlazz bank overrides. LOCAL invoice overrides are incomplete and the account number matches a placeholder pattern, so they are not a legitimate payment source. No account number is invented or copied from QA. BACS remains enabled and unchanged pending owner data.

Required: beneficiary's full legal company name, bank name, HUF account number; IBAN/BIC only if international transfers are accepted. Ask the owner for payment deadline and any reference requirement beyond the order number. Never request banking login credentials. Existing BACS lifecycle renders configured native transfer instructions in acknowledgement, remains unpaid/on-hold until recorded payment, and cannot invoice on mere submission. Do not repeat live payment/provider operations to prove this unchanged path.

## Verification

Before release: required offline `make test` (483 assertions), `make quality` (placeholder lint/static targets), Contact 44 assertions, checkout final UX 32 assertions, focused pickup renderer/sidebar 11 assertions, PHP/JS syntax and diff review. A supported LOCAL BACS fixture confirms on-hold/unpaid/no paid date, configured transfer instructions/account rendering, no invoice and zero provider calls; the fixture is deleted without sending mail. LOCAL browser uses a temporary loopback-only candidate container with existing provider/mail guard; temporary config changes are restored and QA customer/cart/draft removed. LOCAL database predates TEST MPL/COD configuration; the final rate/payment matrix is verified on actual TEST.

TEST acceptance must confirm exact deployed source, unchanged provider/mail hashes, the intended shipping options (including the postal point subtypes), no generic zero-fee courier, correct pickup summary and canonical Contact address at desktop/mobile widths. Browser navigation must not place an order or invoke payment/invoice/shipment. Existing #753 technical email evidence remains valid; owner Inbox/mobile confirmation is still pending. QA cleanup uses Woo/WordPress APIs, not raw SQL.

## Remaining closure status

After successful targeted verification: C0 / H1 / M2 / L1. Remaining HIGH: incomplete BACS business data. MEDIUM: independent backup target/off-Mac key custody; historical public-image private metadata. LOW: known demo catalogue image/content mismatch. Existing closed security, monitoring, provider and mail delivery systems are not reopened. Legal/business wording remains outside this change. Final status stays NON-LEGAL NO-GO while BACS is offered without usable transfer details.
