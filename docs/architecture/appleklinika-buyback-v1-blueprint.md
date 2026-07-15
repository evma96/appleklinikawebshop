# Apple Klinika Buyback V1 Blueprint

Status: architecture proposal only
Date: 2026-07-14
Scope: iPhone-first hybrid buyback and trade-in system
Implementation status: not implemented

## 1. Executive summary

Apple Klinika should build Buyback V1 as a new standalone `appleklinika-buyback` plugin. The current account-page implementation is a theme-owned presentation backed by one serialized user-meta array. It is suitable for a demo record, but not for pricing, inspections, offers, payouts, trade-in credits, audit history, or staff operations.

V1 should support iPhone only while keeping category and device-model ports extensible. The customer can calculate without an account, but authentication is required before submission. A submitted request stores immutable assessment and price-book snapshots. Physical inspection creates a separate inspection snapshot and a separately versioned final offer. The preliminary amount is always described as an `Előzetes ajánlat`, never a guaranteed purchase price.

The recommended persistence is a small set of dedicated custom tables with repository adapters. The central request row contains current state and searchable operational fields; immutable JSON snapshots preserve assessment and pricing inputs; append-only events preserve the audit trail. Price books and rules are versioned separately. Sensitive payout details are minimized and encrypted or tokenized where appropriate.

Trade-in credit becomes usable only after physical inspection and final-offer acceptance. It is represented by a single-use, customer-bound credit ledger record, not a reusable WooCommerce coupon. A WooCommerce adapter may apply the reserved credit as a clearly identified negative order fee/adjustment, subject to accountant and invoicing approval. The buyback request, credit, and Woo order must reference each other and be updated idempotently.

The first implementation phase should create only the plugin skeleton, domain model, database migrations, repositories, transition policy, and automated tests. It should not yet replace the current account page or expose a calculator.

## 2. Existing codebase audit

### 2.1 Existing account buyback page

| Concern | Current implementation |
|---|---|
| Primary endpoint | `beszamitasaim` |
| Legacy endpoint | `eladasaim`, redirected to `beszamitasaim` |
| Endpoint registration | `appleklinika_register_account_endpoints()` in `wordpress/wp-content/themes/appleklinika-theme/functions.php` |
| Woo endpoint hook | `woocommerce_account_beszamitasaim_endpoint` at `functions.php:74` |
| Renderer | `appleklinika_render_sell_account_endpoint()` in `functions.php` |
| Data reader | `appleklinika_account_sell_records(int $userId)` in `functions.php` |
| Navigation | `wordpress/wp-content/themes/appleklinika-theme/woocommerce/myaccount/navigation.php` |
| Menu label | `Beszámítás`, added by `appleklinika_add_wishlist_account_menu_item()` |
| Legacy redirect | `appleklinika_redirect_account_downloads_endpoint()` |
| Account title helpers | `appleklinika_account_page_title()` and `appleklinika_account_breadcrumb_label()` |

Current presentation selectors are in `wordpress/wp-content/themes/appleklinika-theme/assets/css/frontend.css`:

- `body.woocommerce-account .ak-account-sell`
- `body.woocommerce-account .ak-account-steps`
- `body.woocommerce-account .ak-account-record-list`
- `body.woocommerce-account .ak-account-record-card`
- `body.woocommerce-account .ak-account-record-card__thumb`
- `body.woocommerce-account .ak-account-record-card__body`
- `body.woocommerce-account .ak-account-primary-action`
- `body.woocommerce-account .ak-account-secondary-action`

The page supports two presentational states:

1. No records: three explanatory cards and an `Értékbecslés kérése` contact link.
2. Records exist: a flat list with status, device, condition, battery, preliminary/final text amounts, creation date, and a contact link.

It does not currently expose a request detail view, customer actions, timeline, inspection findings, offer expiry, logistics data, or payout/trade-in data.

### 2.2 Current demo buyback record

The current record was verified read-only from the local WordPress database.

| Field | Value |
|---|---|
| Customer user ID | `2` |
| Customer email | `tesztvasarlo@appleklinika.local` |
| Storage type | Serialized WordPress user meta array |
| User-meta key | `appleklinika_buyback_records` |
| Record ID | `ak-buyback-account-test-profile-v1` |
| Device | `iPhone 13 Pro 128 GB Grafit` |
| Condition | `Jó állapot` |
| Battery | `87%` |
| Estimated offer | `145 000 Ft` |
| Final offer | `138 000 Ft` |
| Status | `Bevizsgálás alatt` |
| Created date | `2026-07-01` |
| Customer field | `tesztvasarlo@appleklinika.local` |
| Demo marker | `account-test-profile-v1` |

Exact record keys:

```text
id
device
condition
battery
estimated_offer
final_offer
status
created_date
customer
marker
```

The link to the customer is the user-meta owner (`user_id = 2`). The duplicate `customer` email inside the record is display/demo data, not a referential constraint.

### 2.3 Existing buyback capabilities and gaps

Present:

- WooCommerce account endpoint and navigation item.
- Legacy endpoint redirect.
- Empty/populated account presentation.
- One demo-backed user-meta reader.
- Conditional intro copy based on record count.
- Shared account record-card CSS.

Not present:

- Buyback aggregate or domain services.
- Customer calculator or questionnaire.
- Draft/submission workflow.
- Admin request list/detail screen.
- Price book, pricing rules, or calculation snapshots.
- Controlled status transitions.
- Inspection workflow or discrepancy records.
- Preliminary/final offer entities and acceptance.
- Payout records or trade-in credit ledger.
- Courier integration or handover scheduling.
- Transactional notifications.
- Capabilities specific to buyback staff.
- Audit/event history.
- REST/admin endpoints for buyback operations.
- Repository abstraction for the existing record source.

### 2.4 Current plugin/theme architecture

`appleklinika-inventory` already follows a lightweight DDD/CQRS layout:

```text
src/Domain
src/Application
src/Infrastructure
src/Interfaces
```

Useful patterns to reuse:

- command object plus handler, for example `SaveProductConditionCommand` and `SaveProductConditionHandler`;
- WordPress repository adapters;
- thin admin interfaces that delegate to application handlers;
- a device catalog in `DeviceCatalogRepository`;
- Woo product-condition data through `WooProductConditionRepository`.

Important limitation: the current application handler depends directly on infrastructure repository classes rather than domain repository interfaces. Buyback should improve this by defining repository ports in the Domain/Application layer and injecting WordPress implementations.

Theme ownership should remain limited to generic presentation composition. The theme may host a temporary adapter during migration, but endpoint orchestration, permissions, business rules, status transitions, pricing, persistence, and notifications belong in the buyback plugin.

### 2.5 Admin and audit state

No current admin screen manages buyback records. Staff cannot safely change status, preliminary/final amounts, inspection findings, payout state, or linked orders. No audit trail exists. Editing the serialized user-meta value would be the only direct mutation path and is not an acceptable production workflow.

### 2.6 Recommended migration from the demo record

The demo record must not be silently discarded. Phase 1 should provide a read-only legacy adapter and an explicit, idempotent migration command:

1. Read each user's `appleklinika_buyback_records` array.
2. Validate the marker and required fields.
3. Create a migrated request with `source = legacy_user_meta` and a legacy ID reference.
4. Add a `legacy_imported` event containing the sanitized source snapshot.
5. Preserve the original user meta until migration QA is accepted.
6. Record a migration checksum so reruns cannot duplicate records.
7. Remove or archive legacy meta only in a later, separately approved migration.

## 3. Benchmark comparison

### 3.1 Sources and inspection limits

Public sources reviewed on 2026-07-14:

- Rejoy selling page: <https://rejoy.hu/eladas/>
- ShowMe buyback landing page: <https://showme.hu/buyback/>
- ShowMe flow entry: <https://showme.hu/buyback/flow>
- NorbiPhone buyback entry: <https://norbiphone.hu/felvasarlas#kalkulator>
- Previously supplied Rejoy account and selling-flow screenshots/recording.

The ShowMe flow page was only partly indexable and its dynamic flow could not be opened reliably. The NorbiPhone calculator was not safely accessible through the retrieval tool. Per the timebox, no repeated browser-automation loop was attempted. NorbiPhone observations below therefore use the project owner's supplied interpretation and public indexed context, and must be revalidated before implementation.

### 3.2 Comparison

| Dimension | Rejoy | ShowMe | NorbiPhone | Apple Klinika implication |
|---|---|---|---|---|
| Entry | Category/model online journey | Short device questionnaire | Local calculator/contact plus personal handover | One iPhone calculator entry with clear store/online paths |
| Initial result | Multiple preliminary offer options | Automatic preliminary offer | Indicative value followed by in-person check | Show all eligible modes from one versioned calculation |
| Handover | Courier scheduling and shipping | Free courier after acceptance | Local personal handover | Personal Szeged handover and courier as first-class options |
| Inspection | Technical/cosmetic inspection | Inspection before payout | On-site inspection | Structured checklist and evidence snapshot |
| Final offer | Explicit final offer after inspection | Confirmed amount after inspection | Immediate on-site final amount | Always separate preliminary and final offers |
| Payout | Faster and slower/higher modes | Bank transfer target | Immediate local payout | Four configurable service modes; no binding timing copy in code |
| Tracking | Account status and shipment context | Simpler guided flow | Staff-led/local | Account timeline plus admin operations |
| Trust | Documents, tracking, detailed profile status | Simplicity, free courier, fast payout | Personal expertise and immediate interaction | Receipt/proof, traceability, exact discrepancy explanation |
| Main risk observed | Confusion if tracking or deductions are unclear | Overpromising automatic/fast outcome | Manual process consistency | Audit trail, explicit disclaimers, configurable timing, staff checklist |

### 3.3 Extracted design principles

Adopt:

- A short, progressive questionnaire with saved progress.
- A visible distinction between self-assessment and physical inspection.
- An itemized preliminary calculation and final-offer difference explanation.
- Proof of handover and trackable status.
- Account-based accept/reject actions for the final offer.
- Clear return path when the customer rejects the final offer.
- Configurable service modes rather than copy-bound business promises.

Do not copy:

- competitor wording, legal terms, layouts, assets, colors, trademarks, or source code;
- competitor price amounts;
- claims that Apple Klinika cannot evidence operationally.

## 4. Recommended module location and boundaries

### 4.1 Decision

Create a standalone plugin:

```text
wordpress/wp-content/plugins/appleklinika-buyback
```

Why not the theme:

- buyback is operational business logic and data ownership, not presentation;
- theme changes must not endanger requests, offers, payouts, or audit records;
- staff/admin workflows and migrations need independent lifecycle management.

Why not fold into inventory:

- inventory describes products Apple Klinika sells;
- buyback describes customer-owned devices, assessments, offers, logistics, inspection, payouts, and credit liability;
- the two domains interact through stable ports but should not share persistence ownership.

### 4.2 Proposed structure

```text
appleklinika-buyback/
  appleklinika-buyback.php
  src/
    Domain/
      Buyback/
      Pricing/
      Inspection/
      TradeIn/
      Shared/
    Application/
      Command/
      Query/
      Handler/
      DTO/
      Port/
    Infrastructure/
      Persistence/WordPress/
      WooCommerce/
      Notification/
      Security/
      Clock/
    Interfaces/
      Account/
      Admin/
      Http/
      Cli/
  assets/
  migrations/
  tests/
```

### 4.3 Integration ports

The buyback plugin should depend on interfaces such as:

- `DeviceCatalogReader`: reads normalized iPhone model/configuration choices from inventory.
- `BuybackRequestRepository`: persists aggregates and snapshots.
- `PriceBookRepository`: retrieves active versioned price rules.
- `WooOrderGateway`: reserves/applies/releases trade-in credit.
- `NotificationGateway`: email/account notification delivery.
- `SensitiveValueProtector`: encrypts/decrypts restricted values.
- `Clock`: makes expiry and transition tests deterministic.
- `TransactionManager`: wraps request/credit/order linkage changes.

Inventory and WooCommerce are adapters, not domain dependencies.

## 5. Apple Klinika V1 customer flow

### 5.1 End-to-end journey

1. **Choose category**: V1 exposes iPhone only.
2. **Choose model**: catalog-backed base model.
3. **Choose configuration**: storage, color where relevant, SIM/network variant.
4. **Functional assessment**: power, display, touch, cameras, biometric authentication, buttons, audio, charging, liquid/motherboard issues, repair/parts history, battery health.
5. **Cosmetic assessment**: display, frame, back glass, camera lenses, bends/dents with explicit severity definitions.
6. **Eligibility declarations**: ownership/authorization, not lost/stolen/blacklisted, data removal, Find My/iCloud removal, truthful answers.
7. **Calculate**: application service creates an assessment snapshot and eligible preliminary offers.
8. **Compare modes**: in-store instant, fast online, higher/slower, trade-in. Timing and labels come from active configuration.
9. **Choose handover**: Szeged store or courier where enabled.
10. **Authenticate**: guest may calculate; login/account is required to submit.
11. **Enter contact/payout data**: only fields required by selected mode.
12. **Accept terms and submit**: record exact terms/privacy versions and timestamps.
13. **Handover/receipt**: staff or courier records chain-of-custody evidence.
14. **Inspection**: staff verifies identity, functionality, cosmetics, locks, parts, and discrepancies.
15. **Final offer**: itemized amount, difference explanation, issue/expiry date.
16. **Customer decision**: accept or reject in account, unless an approved in-store signed flow applies.
17. **Settlement**: payout, trade-in application, or device return.
18. **Close**: retain required audit and financial records under policy.

### 5.2 Customer fields

| Field group | Field | Rule |
|---|---|---|
| Draft | guest/session draft token | Optional; opaque, short-lived, no sensitive payout data |
| Customer | authenticated user ID | Required at submission |
| Customer | full name, email, phone | Required at submission; account defaults may be reused |
| Device | category, model key, display name | Required |
| Device | storage | Required where model has variants |
| Device | color | Optional for pricing unless configured otherwise |
| Device | SIM/network variant | Conditional |
| Device | IMEI/serial | Conditional; preferably after login or handover, never public |
| Function | power, display, touch, camera, biometric, buttons, audio, charging | Required answers, including `unknown` where manual review is allowed |
| Function | battery health | Required for eligible iPhone models; validated `0..100` |
| Function | liquid damage, motherboard issue, repair history, replacement parts | Required declaration |
| Cosmetic | screen, frame, back, camera lens, bends/dents | Required using versioned answer choices |
| Ownership | ownership/authorization declaration | Required |
| Ownership | not stolen/lost/blacklisted declaration | Required |
| Privacy | data removal and Find My/iCloud acknowledgement | Required |
| Service | selected mode | Required |
| Handover | store/courier choice | Required from enabled choices |
| Address | pickup/return address | Conditional for courier/return |
| Payout | payout method | Conditional after/at submission based on policy |
| Payout | IBAN reference | Conditional for transfer; protected and never echoed in full |
| Trade-in | target product/cart/order | Conditional; product may be selected later |
| Legal | buyback terms version and acceptance time | Required |
| Legal | privacy version and acknowledgement time | Required |
| Marketing | separate consent | Optional; never implied by transactional acceptance |

Customer answers remain editable only while the request is `draft`. After submission, corrections require an explicit staff/customer amendment event and a new assessment snapshot; history is never overwritten.

## 6. Four service modes

Mode labels and timing are configuration, not constants in legal/business logic.

| Code | Working Hungarian label | Handover | Offer/payout behavior | V1 notes |
|---|---|---|---|---|
| `in_store_instant` | Azonnali személyes felvásárlás | Szeged store | Optional online estimate; staff inspection; final offer and policy-approved payout | Cash vs transfer is an open business/legal decision |
| `fast_online` | Gyors felvásárlás | Courier or store | Preliminary offer; inspection; final offer; configurable target, initially 1–3 business days after acceptance | Timing must be expressed as target until operations/legal approve wording |
| `higher_offer` | Magasabb ajánlat | Courier or store | Higher preliminary amount with longer configurable settlement, initially 10–20 days | Business must decide whether this is direct purchase or consignment-like |
| `trade_in` | Azonnali beszámítás | Store initially; courier later if approved | Inspection and accepted final value become single-use credit linked to one Woo order | No credit before inspection and acceptance |

Each mode configuration needs:

- active flag;
- customer label and description;
- eligibility rules;
- payout adjustment rule;
- allowed handover methods;
- target timing copy;
- final-offer acceptance requirement;
- legal-copy version/reference.

## 7. Domain and storage model

### 7.1 Core aggregate

`BuybackRequest` is the consistency boundary for identity, selected mode, current status, current offer references, and linked settlement references. Assessment, pricing, inspection, final offer, and events are immutable/versioned records associated with it.

Suggested domain objects:

- `BuybackRequestId`, `RequestNumber`
- `CustomerId`, `GuestDraftToken`
- `DeviceIdentity`, `DeviceConfiguration`
- `AssessmentSnapshot`, `AssessmentVersion`
- `Money`, `Currency`
- `ServiceMode`, `HandoverMethod`
- `PreliminaryOfferSnapshot`
- `InspectionReport`, `InspectionFinding`
- `FinalOffer`, `OfferExpiry`
- `BuybackStatus`, `StatusTransitionPolicy`
- `PayoutInstruction`, `PayoutStatus`
- `TradeInCredit`, `TradeInCreditStatus`
- `TermsAcceptance`
- `AuditEvent`

### 7.2 Recommended custom tables

Use dedicated tables rather than a CPT or serialized user meta. Buyback requires transactional transitions, operational filtering, immutable snapshots, unique constraints, and auditable cross-record references.

#### `wp_ak_buyback_requests`

Current searchable state:

- `id` bigint/UUID strategy defined in migration
- `request_number` unique human-safe number
- `customer_id` nullable for draft, required for submitted request
- `guest_draft_token_hash` nullable
- `category`, `model_key`, `device_display_name`
- `service_mode`, `handover_method`
- `status`
- `current_assessment_id`
- `selected_preliminary_offer_id`
- `current_final_offer_id`
- `payout_status`
- `trade_in_credit_id`, `woo_order_id`
- `created_at`, `updated_at`, `submitted_at`, `closed_at`
- `version` for optimistic locking
- `source`, `legacy_reference`, `demo_marker`

Indexes: unique request number, customer/status, status/updated date, model/status, Woo order, legacy reference.

#### `wp_ak_buyback_snapshots`

Immutable payloads:

- `id`, `request_id`
- `snapshot_type`: `assessment`, `pricing`, `inspection_device`
- `schema_version`
- `payload_json`
- `created_by_type`, `created_by_id`
- `created_at`
- `checksum`

JSON is acceptable here because snapshots are immutable evidence; frequently queried operational fields remain normalized on the request/offer tables.

#### `wp_ak_buyback_offers`

- `id`, `request_id`
- `offer_type`: `preliminary` or `final`
- `service_mode`
- `amount_minor`, `currency`
- `price_book_version_id`
- `calculation_snapshot_id`
- `difference_amount_minor`, `difference_reason_summary`
- `status`: draft/issued/accepted/rejected/expired/superseded
- `issued_at`, `expires_at`, `decided_at`
- `accepted_by_type`, `accepted_by_id`, `acceptance_method`
- unique/idempotency keys

#### `wp_ak_buyback_events`

Append-only audit timeline:

- `id`, `request_id`
- `event_type`
- `from_status`, `to_status`
- `actor_type`, `actor_id`
- `public_summary`, `private_payload_json`
- `correlation_id`, `idempotency_key`
- `created_at`

No update/delete through normal application services. Corrections are new events.

#### `wp_ak_buyback_price_books`

- version identity, label, status, effective dates, currency, created/activated actor/time.

#### `wp_ak_buyback_price_rules`

- price book ID;
- model/config match;
- rule kind (`base`, fixed, percentage, multiplier, minimum, reject, manual review, mode adjustment);
- condition key/operator/value;
- amount/percentage/multiplier;
- priority and active flag;
- display/admin explanation.

#### `wp_ak_buyback_inspections`

One or more versioned inspections:

- request/inspector/time;
- verified model/config/IMEI reference;
- findings snapshot ID;
- result and completion state;
- evidence attachment IDs (future-compatible);
- notes with separate customer-visible and internal fields.

#### `wp_ak_buyback_settlements`

- request ID, settlement type (`bank`, `cash`, `trade_in`);
- protected payout-method reference;
- amount, due/completed date, status;
- external transaction reference;
- Woo order/credit reference;
- idempotency key.

### 7.3 Why not CPT/user meta

A CPT is acceptable for editorial content, but less suitable here because:

- status is a domain workflow, not WordPress post status;
- offer/payout/credit uniqueness needs database constraints;
- operational staff queries require predictable indexes;
- snapshots and event history must be append-only;
- a serialized meta array cannot support safe concurrent updates or auditability.

### 7.4 Migration and activation behavior

- Use `dbDelta` only through versioned plugin migrations with a stored schema version.
- Activation creates tables and roles/capabilities; it must not mutate legacy records automatically.
- A dry-run CLI command reports legacy records and validation errors.
- A second explicit CLI/admin action performs idempotent import.
- Deactivation leaves data intact.
- Uninstall deletes data only through a separate explicit confirmation and documented retention gate.

## 8. Status model

Only application commands may transition status. The repository rejects version conflicts, and every accepted transition appends an event in the same database transaction.

Legend: `C` customer, `S` staff/admin, `Y` system.

| Status | Allowed previous | Who enters | Customer label | Customer next action | Staff action | Notification | Customer answers editable? |
|---|---|---|---|---|---|---|---|
| `draft` | none | C/Y | Piszkozat | Continue or delete | None | Optional reminder later | Yes |
| `submitted` | draft | C | Beküldve | Review confirmation | Validate request | Customer + admin | No |
| `awaiting_handover` | submitted, courier_requested | S/Y | Átadásra vár | Bring to store or follow instructions | Confirm handover plan | Customer | No |
| `courier_requested` | submitted, awaiting_handover | C/S | Futár igényelve | Confirm pickup details | Book courier | Admin | No |
| `courier_booked` | courier_requested | S/Y | Futár lefoglalva | Prepare and hand over device | Monitor pickup | Customer | No |
| `received` | awaiting_handover, courier_booked | S | Készülék beérkezett | Wait for inspection | Record chain of custody | Customer + admin | No |
| `inspection_pending` | received | S/Y | Bevizsgálásra vár | Wait | Assign inspector | Optional customer, admin | No |
| `inspecting` | inspection_pending | S | Bevizsgálás alatt | Wait | Complete inspection | Customer | No |
| `preliminary_mismatch` | inspecting | S/Y | Eltérés az előzetes adatokhoz képest | Review once final offer is issued | Document discrepancies | Admin; customer when appropriate | No |
| `final_offer_ready` | inspecting, preliminary_mismatch | S/Y | Végleges ajánlat készül | Wait | Review and issue | Admin | No |
| `final_offer_sent` | final_offer_ready | S | Végleges ajánlat érkezett | Accept or reject before expiry | Resend/withdraw/supersede | Customer + admin | No |
| `final_offer_accepted` | final_offer_sent | C/S* | Végleges ajánlat elfogadva | Follow payout/trade-in status | Initiate settlement | Customer + admin | No |
| `final_offer_rejected` | final_offer_sent | C/S* | Végleges ajánlat elutasítva | Choose/confirm return | Start return | Customer + admin | No |
| `return_requested` | final_offer_rejected, cancelled | C/S | Visszaküldés kérése rögzítve | Confirm address if needed | Arrange return | Customer + admin | No |
| `returning_device` | return_requested | S/Y | Készülék visszaküldés alatt | Track/receive | Record shipment | Customer | No |
| `payout_pending` | final_offer_accepted | S/Y | Kifizetés folyamatban | Wait/check details | Process payout | Customer + finance | No |
| `paid` | payout_pending | S/Y | Kifizetve | View settlement | Reconcile | Customer + admin | No |
| `trade_in_pending` | final_offer_accepted | S/Y | Beszámítás előkészítve | Select/complete linked purchase | Reserve/apply credit | Customer + admin | No |
| `trade_in_applied` | trade_in_pending | S/Y | Beszámítás felhasználva | View linked order | Reconcile order/credit | Customer + admin | No |
| `cancelled` | draft, submitted, awaiting_handover, courier_requested, courier_booked | C/S | Megszakítva | Start new valuation; return if already held | Record reason/return need | Customer + admin | No |
| `closed` | paid, trade_in_applied, returning_device, cancelled | S/Y | Lezárva | View history | Archive after checks | Optional customer | No |

`S*` may record an in-store customer decision only with approved evidence/acceptance method. The event must identify the staff member and method; staff cannot silently impersonate an online customer action.

Additional transition rules:

- Final offer acceptance is rejected after expiry unless staff issues a new offer.
- `paid` requires a settlement reference and completion timestamp.
- `trade_in_applied` requires a uniquely linked credit and Woo order.
- `closed` requires no unresolved device custody, payout, return, or credit.
- Cancellation after device receipt cannot skip return/settlement resolution.
- An expired final offer remains historical; a new one supersedes it.

## 9. Price engine

### 9.1 Inputs and versioning

Inputs:

- normalized model/configuration;
- versioned customer assessment;
- active price-book version at calculation time;
- mode-specific adjustment;
- optional staff inspection snapshot for final calculation.

Every calculation stores:

- price-book ID/version;
- normalized inputs;
- matched rules in execution order;
- base amount;
- each fixed/percentage/multiplier adjustment;
- minimum/rejection/manual-review decision;
- final calculated amount;
- customer-safe explanation and internal detail;
- calculator version.

Existing requests never recalculate silently when the active price book changes.

### 9.2 Rule order

1. Resolve exact base-price row by model, storage, and eligible variant.
2. Evaluate hard disqualifiers.
3. Evaluate manual-review triggers.
4. Apply fixed deductions to base amount.
5. Apply percentage deductions/multipliers in deterministic priority order.
6. Apply service-mode adjustment.
7. Clamp to zero and configured minimum/maximum policy.
8. Round to configured currency increment.
9. Return offer or manual-review/rejected result with breakdown.

### 9.3 Formula

```text
fixed_adjusted = max(0, base_price - sum(fixed_deductions))
condition_adjusted = fixed_adjusted * product(condition_multipliers)
mode_adjusted = condition_adjusted * mode_multiplier + mode_fixed_adjustment
raw_offer = max(0, mode_adjusted)

if hard_disqualifier:
    result = rejected
else if manual_review_rule:
    result = manual_review
else if raw_offer < configured_minimum_offer:
    result = rejected or manual_review (price-book policy)
else:
    result = round_to_increment(raw_offer)
```

Percent rules are stored as multipliers (for example `0.90`) to avoid ambiguity. Fixed amounts use integer minor units; floating-point money is forbidden.

### 9.4 Pseudocode

```text
function calculate(input, mode, priceBookVersion): CalculationResult
    normalized = normalizeAndValidate(input)
    baseRule = priceBook.findBase(normalized.model, normalized.storage, normalized.variant)
    if baseRule missing: return ManualReview("missing_base_price")

    matched = priceBook.matchRules(normalized, mode).sortByPriority()
    if matched.any(isHardReject): return Rejected(matched.reasons)
    if matched.any(requiresManualReview): return ManualReview(matched.reasons)

    amount = Money(baseRule.amount)
    breakdown = [Base(baseRule)]

    for rule in matched.fixedRules:
        amount = amount.minus(rule.amount).notBelowZero()
        breakdown.append(rule.effect)

    for rule in matched.multiplierRules:
        amount = amount.multiply(rule.multiplier)
        breakdown.append(rule.effect)

    amount = amount.multiply(mode.multiplier).plus(mode.fixedAdjustment).notBelowZero()

    if amount < priceBook.minimumOffer:
        return priceBook.minimumPolicyResult(breakdown)

    return Offered(amount.round(priceBook.roundingIncrement), breakdown, priceBook.version)
```

### 9.5 Admin safeguards

- Draft/active/retired price-book states.
- Only one active version per currency/effective instant.
- Four-eyes approval is recommended before production activation.
- Preview calculation and affected-model count before activation.
- Import validates duplicates, missing bases, invalid ranges, and negative outcomes.
- Existing snapshots keep the retired version reference.
- No competitor scraping or automatic external repricing in V1.

## 10. Admin workflow

### 10.1 Capabilities

Define dedicated capabilities rather than broad `manage_options` access:

- `ak_buyback_view_requests`
- `ak_buyback_manage_handover`
- `ak_buyback_inspect_devices`
- `ak_buyback_issue_offers`
- `ak_buyback_manage_payouts`
- `ak_buyback_manage_trade_in`
- `ak_buyback_manage_price_books`
- `ak_buyback_view_sensitive_data`
- `ak_buyback_export_data`

Roles may combine these for a small team, but audit events record the actual user.

### 10.2 Request list

Columns:

- request number;
- customer;
- device/configuration;
- selected mode and handover;
- preliminary amount;
- final amount;
- current status;
- request age;
- last update;
- assigned staff member.

Filters: status, mode, model, date/age, assigned staff, handover method, payout/trade-in state. No raw sensitive value appears in list views.

### 10.3 Request detail

Sections:

- customer and access-controlled contact data;
- device and self-assessment snapshot;
- preliminary calculation breakdown;
- handover/courier chain of custody;
- inspection checklist and evidence;
- discrepancies between submitted and verified state;
- final-offer composer with itemized reasons and expiry;
- customer decision;
- payout/trade-in settlement;
- linked Woo order;
- append-only activity timeline.

Staff actions invoke commands and transition policy. There is no editable raw status dropdown.

### 10.4 Price-book admin

- Manage model/storage base prices.
- Manage condition and failure modifiers.
- Manage four mode adjustments and availability.
- Define effective dates, minimum amount, rounding, and manual-review policy.
- Clone an active book into a draft.
- Preview and validate before activation.
- CSV import/export may be Phase 1.1 after schema stability.

### 10.5 Required operational actions

- validate submission;
- confirm handover/courier booking and receipt;
- assign/complete inspection;
- record discrepancies and deductions;
- issue/supersede final offer;
- resend transactional notification;
- record payout initiation/completion;
- reserve/apply/release trade-in credit;
- arrange rejected-device return;
- cancel/close with reason.

## 11. Customer account workflow

### 11.1 List view

Replace the user-meta reader with a plugin query. Each card shows only real stored data:

- device image or neutral category fallback;
- model and configuration;
- selected mode;
- `Előzetes ajánlat`;
- `Végleges ajánlat` when issued;
- Hungarian status label;
- last update;
- `Részletek` action.

### 11.2 Detail view

- status timeline;
- submitted self-assessment summary;
- handover/courier details and proof/track link where available;
- inspection findings and explicitly explained differences;
- preliminary and final offer side by side;
- offer issue/expiry timestamps;
- authenticated accept/reject action with nonce and re-authentication policy if required;
- payout or return state;
- trade-in credit and linked Woo order.

### 11.3 Empty state

- Explain the service without implying an existing record.
- CTA: `Értékbecslés indítása`.
- No demo record or fake amount.

### 11.4 Endpoint migration

Keep `beszamitasaim` as the account entry endpoint for continuity. Add request detail as a controlled sub-route/query resolved by the plugin. The legacy `eladasaim` redirect may remain until analytics/logs show it can be retired. The theme should delegate account content to a plugin interface and keep only shell styling.

## 12. Trade-in and WooCommerce integration

### 12.1 Credit validity

Trade-in credit is not valid from the calculator. It becomes eligible only when:

1. device is physically received and inspected;
2. a final offer is issued and accepted;
3. request is in `trade_in_pending`;
4. credit record is bound to the authenticated customer;
5. credit has not expired, been released, or been consumed.

### 12.2 Recommended representation

Use a dedicated `TradeInCredit` ledger record as the security source of truth. Do not use a normal coupon.

Recommended Woo adapter behavior:

- reserve credit against one cart/session and one customer;
- on checkout, add a named negative fee/adjustment such as `Beszámítási jóváírás` backed by the reserved ledger ID;
- persist request ID and credit ID on the Woo order and order adjustment;
- atomically mark the credit consumed when the order is created/confirmed according to the chosen payment policy;
- release reservation after cart expiry, checkout failure, or approved order cancellation;
- never allow consumed credit in another order.

The negative-fee representation is Woo-compatible but requires accountant/invoice-export approval. If the invoicing provider cannot represent it correctly, implement the ledger as a custom partial payment/tender adapter instead. The domain ledger remains the same either way.

### 12.3 Product reservation and difference payment

- V1 should not reserve a target product during preliminary calculation.
- Reservation may begin only when final trade-in credit is accepted and a target in-stock product is selected.
- Existing Woo stock-hold behavior should be used where possible; buyback must not invent parallel stock ownership.
- The order total equals normal Woo totals minus valid trade-in credit, never below zero.
- If credit exceeds the order amount, V1 should reject the combination or require staff resolution; it should not create a wallet balance unless later approved.
- The remaining difference is paid through normal enabled Woo payment gateways.

### 12.4 Cancellation and refund rules

- Before order creation: release reserved credit.
- Failed/cancelled unpaid order: release if the device settlement has not otherwise completed.
- Paid order cancellation/refund: staff workflow decides whether to restore credit, pay it out, or offset return; never automatic without state checks.
- Product return must not silently recreate a reusable credit.
- Every reserve/apply/release/reinstate action is idempotent and audited.
- Request and Woo order references are reciprocal and immutable after successful linkage except through a correction event.

## 13. Notifications

Transactional messages do not imply marketing consent.

| Event | Customer email | Account notice/timeline | Admin/staff | SMS later? |
|---|---|---|---|---|
| Request submitted | Required | Required | Required | Optional |
| Handover/courier instructions | Required | Required | Operations | Optional |
| Device received | Required | Required | Operations | Optional |
| Inspection started | Recommended | Required | Assigned inspector | No |
| Final offer issued | Required | Required with action | Required | Optional |
| Final offer accepted | Required | Required | Finance/operations | Optional |
| Final offer rejected | Required | Required | Returns staff | Optional |
| Payout initiated | Required | Required | Finance | Optional |
| Payout completed | Required | Required | Finance | Optional |
| Trade-in applied | Required | Required with order link | Sales/finance | Optional |
| Device return initiated | Required | Required with tracking | Returns staff | Optional |
| Request cancelled | Required | Required | Relevant assignee | No |

Notifications render from event DTOs, use configurable Hungarian templates, and store delivery attempts/results. Retrying delivery must not repeat the business transition.

## 14. Security, privacy, and legal review points

This blueprint is not legal advice. Production copy and policies require Hungarian legal, accounting, tax, and operations review.

### 14.1 Sensitive data

| Data | Control |
|---|---|
| IMEI/serial | Collect only when operationally required; encrypt/restrict; mask in UI/logs; never expose publicly |
| IBAN | Prefer payment-provider token/reference; otherwise application-level encryption with key outside database; show masked value only |
| Identity/contact/address | Least-privilege access, standard Woo/WordPress erasure/export integration, retention policy |
| Ownership declarations | Versioned immutable acceptance record |
| Inspection evidence | Private media access; no public attachment URLs; retention and deletion rules |
| Payout references | Finance-only capability, masked logs, immutable audit events |

### 14.2 Application controls

- Capability checks on every admin command and sensitive query.
- Nonces/CSRF protection for WordPress forms; authenticated authorization beyond nonce.
- Strict DTO validation, allowlists, sanitization at interfaces, escaping at output.
- Prepared SQL in repositories.
- Optimistic locking and database transactions for state/credit changes.
- Idempotency keys for notifications, payouts, courier callbacks, and Woo credit operations.
- Rate limiting and signed short-lived draft tokens for guest calculator APIs.
- No sensitive values in URLs, analytics, browser storage, or generic logs.
- REST routes default private; object-level authorization is mandatory.
- Append-only event log with actor, correlation ID, timestamp, before/after status.
- Separate customer-visible and internal notes.
- Backups and restore drills include custom tables and encryption keys.

### 14.3 Retention/export/erasure

- Define retention by data class before launch.
- Financial/contract records may require retention even after account erasure; legal basis must be documented.
- WordPress privacy exporter provides customer-readable request history.
- Eraser anonymizes eligible data while preserving legally required financial/audit records.
- Drafts expire and purge automatically after a configurable period.
- Rejected/returned device evidence has a separate retention schedule.

### 14.4 Configurable copy requiring approval

- Preliminary-offer disclaimer.
- Final-offer formation and acceptance process.
- All payout timing/availability statements.
- Ownership and authorization declaration.
- Lost/stolen/blacklisted device handling.
- Find My/iCloud and customer data-removal responsibility.
- Inspection and deduction explanation.
- Return shipping, fees, and custody rules.
- Title transfer moment.
- Trade-in credit/order/cancellation/refund terms.

## 15. MVP versus later

### 15.1 V1 must have

- iPhone category, model, and configuration selection.
- Versioned functional/cosmetic questionnaire.
- Guest calculation with authenticated submission.
- Admin-editable, versioned price book.
- Preliminary offers for all eligible configured modes.
- Four service modes with configurable labels/timing.
- Store and courier handover records without mandatory courier API automation.
- Account request list/detail and status timeline.
- Admin request list/detail and controlled transitions.
- Physical inspection and discrepancy capture.
- Versioned final offer with expiry and accept/reject.
- Payout status and manually entered transaction reference.
- Trade-in credit ledger and Woo linkage design; actual application can be a late V1 phase after accounting approval.
- Transactional email/account notifications.
- Audit trail, permissions, migration, privacy export/erasure integration.

### 15.2 V1.1/later

- Courier API booking/tracking callbacks.
- SMS.
- Customer-uploaded photos.
- IMEI blacklist API.
- Automated competitor price monitoring.
- MacBook, iPad, Apple Watch.
- Store-credit wallet or surplus carryover.
- Electronic contract signing.
- Advanced analytics and operations dashboards.
- Automated bank payout provider.
- OCR/document verification.

## 16. Phased implementation plan

### Phase 1: plugin skeleton and data model

- **Scope:** standalone plugin, domain types, repository ports, migration runner, custom tables, transition policy, legacy read adapter.
- **Expected modules:** `Domain/Buyback`, `Application/Port`, `Infrastructure/Persistence/WordPress`, `Interfaces/Cli`.
- **Data changes:** create versioned tables only; no automatic legacy mutation.
- **Activation/migration:** idempotent schema migration; dry-run legacy report.
- **Frontend/admin:** none beyond health/admin diagnostic page if needed.
- **QA:** unit tests for value objects/transitions; repository integration tests with local DB; migration rerun/rollback rehearsal.
- **Rollback:** deactivate plugin; leave tables/data intact; current theme endpoint continues reading user meta.
- **Risk:** Medium, primarily schema and transaction semantics.
- **Recommended Codex reasoning:** high.

### Phase 2: price-book admin and calculator service

- **Scope:** price-book/rule domain, draft/activation workflow, calculation command/query, preview admin.
- **Expected modules:** `Domain/Pricing`, `Application/Command`, `Application/Query`, `Application/Handler`, `Infrastructure/Persistence/WordPress`, `Interfaces/Admin`.
- **Data changes:** price book and rule rows; seeded test fixture only after explicit approval.
- **Activation/migration:** add pricing tables through the versioned migration runner; do not create or activate a live price book automatically.
- **Frontend:** no public calculator yet.
- **Admin:** model/config bases, modifiers, modes, validation preview.
- **QA:** deterministic fixtures, rule ordering, no-negative/minimum/manual-review, snapshot immutability.
- **Rollback:** retire draft price book; prior versions remain.
- **Risk:** High because pricing errors create financial exposure.
- **Recommended Codex reasoning:** high.

### Phase 3: iPhone questionnaire and preliminary offers

- **Scope:** progressive form, guest draft token, catalog adapter, four-mode comparison.
- **Expected modules:** `Domain/Questionnaire`, `Application/Calculator`, `Application/Draft`, `Infrastructure/Catalog`, `Interfaces/Http`, and narrowly scoped frontend assets.
- **Data changes:** expiring drafts and immutable snapshots.
- **Activation/migration:** add draft/snapshot indexes with an idempotent migration; keep the public calculator behind a disabled feature flag until QA fixtures pass.
- **Frontend:** calculator only; no submission without authentication.
- **Admin:** optional draft diagnostics, no raw editing.
- **QA:** accessibility, mobile, answer validation, tamper resistance, price-book version display, expired drafts.
- **Rollback:** disable public route/feature flag; stored drafts can expire.
- **Risk:** Medium-high.
- **Recommended Codex reasoning:** high.

### Phase 4: authenticated submission and account tracking

- **Scope:** login handoff, request submission, terms capture, `beszamitasaim` list/detail, legacy display migration.
- **Expected modules:** `Domain/Request`, `Application/Submission`, `Application/CustomerQuery`, `Infrastructure/Identity`, `Infrastructure/Legacy`, `Interfaces/Account`.
- **Data changes:** customer-bound requests/events.
- **Activation/migration:** enable the new account query behind a feature flag; run legacy user-meta import in dry-run first and never delete the source record during V1 migration.
- **Frontend:** submit confirmation, timeline, real empty/list/detail states.
- **Admin:** basic request list/read-only detail.
- **QA:** ownership authorization, cross-user isolation, login redirect, duplicate-submit idempotency.
- **Rollback:** feature flag routes back to legacy endpoint; records remain readable in admin/CLI.
- **Risk:** High due to authorization/privacy.
- **Recommended Codex reasoning:** high.

### Phase 5: admin inspection and final-offer workflow

- **Scope:** custody receipt, inspection form, discrepancy calculation, final offer, customer accept/reject.
- **Expected modules:** `Domain/Inspection`, `Domain/Offer`, `Application/Inspection`, `Application/Offer`, `Interfaces/Admin`, `Interfaces/Account`.
- **Data changes:** inspection, offer, event records.
- **Activation/migration:** add inspection/offer schema through the migration runner; do not auto-transition any existing request when activating the phase.
- **Frontend:** inspection and final-offer detail/actions.
- **Admin:** controlled staff commands, no raw status dropdown.
- **QA:** transition matrix, offer expiry/supersede, evidence, notification idempotency.
- **Rollback:** disable mutation actions; keep records and read-only views.
- **Risk:** Very high because custody and binding offers begin here.
- **Recommended Codex reasoning:** high with human operations/legal review.

### Phase 6: payout handling and notifications

- **Scope:** settlement records, masked payout data, manual finance workflow, email/account notifications.
- **Expected modules:** `Domain/Settlement`, `Application/Settlement`, `Application/Notification`, `Infrastructure/Encryption`, `Infrastructure/Notification`, `Interfaces/Admin`.
- **Data changes:** encrypted/tokenized payout references and delivery logs.
- **Activation/migration:** add settlement/delivery structures and verify encryption-key configuration before finance actions or payout-data collection can be enabled.
- **Frontend:** payout state only; no full bank data echo.
- **Admin:** finance capability, initiate/complete actions.
- **QA:** encryption keys, idempotency, permission matrix, failure/retry, privacy export.
- **Rollback:** manual payout operations continue outside system; freeze status actions safely.
- **Risk:** Very high.
- **Recommended Codex reasoning:** high plus security/accounting review.

### Phase 7: trade-in/Woo order integration

- **Scope:** credit ledger, reserve/apply/release, order metadata, cancellation/refund policy.
- **Expected modules:** `Domain/TradeIn`, `Application/Credit`, `Infrastructure/WooCommerce`, `Interfaces/Checkout`, `Interfaces/Admin`.
- **Data changes:** credit/settlement/order links and unique constraints.
- **Activation/migration:** create and verify unique credit-ledger constraints first; keep checkout application disabled until accounting and invoicing behavior is approved.
- **Frontend:** target product/order flow and difference payment.
- **Admin:** linked order, reconciliation and exception actions.
- **QA:** concurrency, replay, cart expiry, out-of-stock, checkout failure, cancellation/refund, tax/invoice export.
- **Rollback:** feature flag prevents new reservations; reconcile existing credits explicitly.
- **Risk:** Critical financial/inventory risk.
- **Recommended Codex reasoning:** high, with accountant and WooCommerce specialist review.

### Phase 8: release hardening

- **Scope:** security/privacy/legal copy, operations runbook, monitoring, complete UX polish.
- **Expected modules:** `Interfaces/Admin/Diagnostics`, retention jobs, privacy adapters, monitoring adapters, and deployment/runbook documentation.
- **Data changes:** approved retention jobs and production configuration.
- **Activation/migration:** enable production retention schedules and launch configuration only after staging rehearsal, legal approval, and a verified restore point.
- **Frontend:** final approved copy, accessibility polish, and complete error/empty states.
- **Admin:** diagnostics, operational warnings, queue health, and support runbook links.
- **QA:** end-to-end staging, role matrix, penetration review, backup/restore, accessibility, operational rehearsal.
- **Rollback:** launch feature flag and documented incident procedure.
- **Risk:** High if skipped; lowers launch risk when completed.
- **Recommended Codex reasoning:** high for audit, medium for isolated polish.

## 17. Open business decisions

- [ ] Final Hungarian names and descriptions of all four modes.
- [ ] Exact payout target windows and whether wording is guaranteed or best-effort.
- [ ] Allowed payout methods by mode.
- [ ] Whether in-store instant payout supports cash, transfer, or both.
- [ ] Whether courier pickup is free and under which conditions.
- [ ] Who pays return shipping after rejection, mismatch, or ineligible device.
- [ ] Preliminary-offer validity period.
- [ ] Final-offer validity period.
- [ ] Whether `Magasabb ajánlat` is direct purchase or consignment-like.
- [ ] Minimum accepted device value.
- [ ] Hard-rejected versus manually reviewed device conditions.
- [ ] Effect of charger/box/accessories on price.
- [ ] Proof-of-ownership requirements.
- [ ] When IMEI/serial is collected and checked.
- [ ] Whether guest drafts are stored server-side and for how long.
- [ ] Store/courier chain-of-custody document requirements.
- [ ] Whether a target product is reserved for trade-in, and at what moment.
- [ ] Trade-in accounting/invoice representation.
- [ ] Handling when trade-in credit exceeds order total.
- [ ] Cancellation/refund treatment of consumed trade-in credit.
- [ ] Who owns legal terms and who approves each version.
- [ ] Data retention periods by record type.
- [ ] Staff roles and approval limits for offers/payouts.

## 18. Main risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Preliminary amount presented as guaranteed | Legal/trust | Fixed terminology, versioned disclaimer, explicit final-offer workflow |
| Price-book mistake | Direct financial loss | Draft/preview/approval, fixtures, snapshots, rollback to prior version |
| Unexplained final reduction | Trust/support load | Itemized discrepancy and deduction record visible to customer |
| Lost custody visibility | High-value device dispute | Receipt, timestamps, courier reference, chain-of-custody events |
| Arbitrary status edits | Data inconsistency | Domain transition policy, command handlers, audit events |
| Duplicate payout/credit | Financial loss | Unique constraints, idempotency keys, transactions, reconciliation |
| Trade-in double spend | Critical | Customer/request/order-bound ledger, atomic reservation/consumption |
| Woo invoice/tax mismatch | Compliance | Accountant/provider review before Phase 7; adapter behind feature flag |
| Sensitive data exposure | Privacy/security | Minimize, encrypt/tokenize, mask, capabilities, private media |
| User-meta migration loss/duplication | Data integrity | Dry run, checksum, idempotent import, preserve legacy source |
| Theme/plugin coupling | Maintenance | Plugin-owned domain/interfaces; theme shell delegates to plugin |
| Courier/API outage | Operational | Manual handover/logistics fallback; API automation later |
| Concurrency between admin/customer actions | Incorrect settlement | Optimistic locking, transactions, stale-version rejection |
| Unapproved legal/payout copy | Legal/brand | Configurable content and explicit review gate |

## 19. Recommended next implementation prompt scope

The next task should be **Phase 1 only**:

> Create the `appleklinika-buyback` plugin skeleton and persistence foundation only. Implement domain value objects for request ID, money, service mode, status, and a tested status-transition policy; repository interfaces; versioned custom-table migrations; WordPress repository adapters; a read-only legacy user-meta adapter; and a dry-run CLI migration report. Do not create public calculator/account/admin mutation UI, do not migrate data automatically, do not change WooCommerce checkout/order behavior, and do not implement pricing, payouts, courier, or trade-in credit. Add unit/integration tests using local fixtures only and update required architecture/workflow documentation.

Acceptance gates for that phase:

- schema migration is idempotent;
- no existing user-meta record is changed;
- all listed status transitions have tests;
- cross-request/customer repository queries are covered;
- legacy dry-run reports record `ak-buyback-account-test-profile-v1` without importing it;
- plugin deactivation leaves existing storefront/account behavior intact;
- no public route or financial action exists yet.
