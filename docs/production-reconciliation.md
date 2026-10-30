# Authorized payment, invoice and email reconciliation

## Scope and gates

Production recovery requires the existing signed production configuration, environment-specific provider identity pins, mandatory safety module and validated provider activation. Public ordering additionally requires signed public-opening evidence, `public_open: true` and Coming Soon off. Recovery callbacks can therefore continue while public ordering is closed; activation OFF remains fail-closed. No live account was activated by the 2026-10-30 remediation.

Only an authenticated operator with `manage_appleklinika_backoffice` or `manage_options` can use order review. Existing UI nonce, expected-state checks, per-order lock and history remain mandatory. Use Woo as the authoritative order; never fabricate paid status, transaction ID, stock or provider documents.

## Payment interruption

Use Back Office's existing Barion recheck. It independently verifies the installed gateway's provider response: payment ID, Woo reference, amount, currency, success and matching transaction. A stale closed-callback marker is not payment proof. Completion goes through the existing native gateway and stock-admission lock, including late captured payments after cancellation. A reassigned device raises an actionable order exception; do not take another customer's stock or issue an invoice. Resolve with the provider/operator rather than pretending the order is fulfillable.

A provider-read exception returns a retryable callback HTTP 503. Repeated successful callbacks/rechecks cannot duplicate payment, stock, invoice or mail. Ineligible/custom-callback orders remain manual review cases. Operator, time and result are recorded on the order and existing Back Office history.

## Invoice interruption

The existing Action Scheduler reconciliation scan runs every five minutes, 25 orders per page. A known safe rejection may retry at most three times with delay. A missing outcome, partial document, interrupted request or uncertain issuance is held for reconciliation; it is never blindly issued again. Completed historical documents are not retroactively rewritten merely because they have no worker journal.

If a complete invoice/PDF was already saved but its completion event was interrupted, repair the lifecycle projection and queue its confirmation without another invoice request.

If the provider has the invoice but local number/PDF persistence was lost, an authorized operator must first inspect the provider portal and confirm the invoice number, Woo order reference, buyer and gross total. Then use the private supported PDF-read workflow:

```sh
wp --user=<authorized-staff-id> appleklinika recover-existing-invoice \
  --order=<woo-order-id> --invoice=<existing-invoice-number> \
  --confirmed-provider-order=<verified-woo-order-number> \
  --reason='<describe the provider portal evidence checked>'
```

This queries the installed licensed plugin's `action-szamla_agent_pdf` endpoint. It does not issue an invoice, mark an unpaid order paid or override a different invoice/receipt/void. The selected Agent key must match the approved environment pin; no credentials belong in command arguments or logs. A valid PDF is stored through the vendor's existing file writer and checked through the shared document resolver. Operator evidence and existing history record recovery. A repeated call resumes interrupted local finalization without a second document query or duplicate history. This is an explicit operator attestation of provider/order linkage, not an automatic ownership claim inferred from PDF bytes.

If the provider has no invoice but the local attempt is uncertain, stop for provider-supported investigation. Do not erase the attempt marker to force issuance. Known pre-request configuration failures can be corrected and retried through existing safe operations.

## Email dependency and limits

Submission acknowledgement precedes accepted-order/invoice confirmation; shipping requires accepted confirmation plus actual recorded carrier handoff. A failed prerequisite holds later messages while real fulfillment may continue. Successful retry wakes the next eligible message. Accepted events are never resent. Known transport rejection has a bounded three-attempt retry; uncertain/sending outcomes require mail-provider evidence before any manual resend.

The application proves ordered SMTP acceptance, not ordering inside Gmail or another recipient's Inbox: network delivery and Inbox sorting are outside application control. Do not infer delivered-to-Inbox from `wp_mail()` alone. Persistent failures remain visible on the order; the separate business-queue alerting gap M2 remains open.

## Launch sequence

1. Prepare a fresh closed environment and production-specific provider/backup configuration; no sandbox credentials or imported QA products/orders/customers.
2. Record signed initial empty-provisioning evidence. Preserve it as immutable initial-state evidence.
3. Staff may enter and review real devices while public checkout remains closed. Keep unique identities, stock 0/1, no backorders and approved tax mappings. List reviewed real product IDs in private configuration.
4. Run public-opening readiness with real inventory present; resolve QA artifacts/configuration failures without deleting real stock. Record its signed evidence.
5. Only separately approved production activation/public opening may set the relevant switches and turn Coming Soon off. Real LIVE provider/site-license verification H5, independent key custody M3 and production backup/monitor proof remain explicit manual requirements.
