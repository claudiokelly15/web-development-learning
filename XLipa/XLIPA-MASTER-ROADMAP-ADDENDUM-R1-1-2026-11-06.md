# XLipa — Master Roadmap Addendum R1.1

**Merchant QR, Pay Buttons, Reports, Pricing, Settlements and Chief Admin**  
**Version R1.1 — 6 November 2026, Africa/Nairobi. DOCUMENTATION ONLY.**

## 1. Executive summary

**OWNER-APPROVED:** future merchant QR payments, embeddable Pay Buttons, physical-goods order integration, PDF/XLSX/CSV financial reports, flexible percentage/fixed/hybrid pricing and Starter/Business/Enterprise commercial tiers; Chief Auto-Settlement Settings, Refund Management and a Real-Time Transaction Feed.

**PROPOSED:** these capabilities reuse R1.0 checkout, order/payment facts, provider adapters, ledger, reconciliation and reporting contracts. Channels do not create parallel wallets or alternative payment truth. QR/button generation is not payment confirmation; a schedule is not settlement; a refund approval is not returned funds. This document specifies dependencies, security, acceptance and bounded review gates, not feature implementation.

Labels: **VERIFIED** = current read-only inspection evidence; **OWNER-APPROVED** = product requirement in the owner's R1.1 instruction, not execution permission; **PROPOSED** = technical design pending review; **NOT VERIFIED** = missing implementation/official contract/legal evidence; **REQUIRES APPROVAL** = separate concrete implementation, operational or financial gate. Unless marked otherwise, future technical behavior in sections 3–15 is PROPOSED.

## 2. Relationship to R1.0 and existing Phase 3

This targeted addendum **supplements**, and does not overwrite or replace, [R1.0 Payments & Analytics](XLIPA-MASTER-ROADMAP-ADDENDUM-PAYMENTS-ANALYTICS-2026-11-06.md). Read both together. All R1.0 requirements remain, with explicit R1.1 extensions identified below. It does not supersede the approved **B3.1–B3.5** milestone structure or the Stage A architecture/detailed B3 plan.

Reviewed inputs: [B3.2-A v2.0](XLIPA-PHASE-3-B3-2-A-FINAL-SPECIFICATION-2026-11-06.md), [Secure Architecture Review](XLIPA-B3-2-SECURE-ARCHITECTURE-REVIEW-2026-11-06.md), R1.0, [Stage A architecture](PHASE-3-STAGE-A-ARCHITECTURE-2026-11-06.md) and [detailed B3 plan](PHASE-3-B3-ONBOARDING-AUTHORIZATION-PLAN-2026-11-06.md). Historical broad-profile submission assumptions remain amended by v2.0, not revived by financial features.

**VERIFIED this task:** canonical project `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`; Laravel `backend/`; canonical public frontend `frontend/`; Git root parent `WEB-DEVELOPMENT`. Branch main, HEAD `7c3e4743384e0ca0acd7b029c944569c84304541`. Initial status has four pre-existing untracked documentation files: B3.2 planning, v2.0 specification, Secure Architecture Review and R1.0; no tracked modifications were reported. These are pre-existing work, not R1.1 additions. No runtime or test execution was needed. Historical 108 tests / 847 assertions and 31 frontend checks are not fresh results here.

B3.1 local scoped read-only merchant status is the completed foundation. B3.2 secure identity/contact infrastructure and B3.3 submission/B3.4 review/B3.5 acceptance remain separately gated. TZ minimum application data remain business name, NIDA, phone and email, with exact contact proofs. Optional profile/documents/payment methods remain generally optional at initial submission; missing mandatory secure identity/OTP infrastructure fails closed. Chief internal approval never grants financial capability. R1.1 introduces no NIDA collection, privileged account or live financial permission.

The future production domain, Selcom API/rail codes, rates, custody model and deployment infrastructure remain **NOT VERIFIED/UNDECIDED**. Local `http://xlipa.test` is unchanged. R1.1's hosted URLs are conceptual configured URLs, not a chosen production hostname.

### Explicit R1.1 extensions

| R1.0 area | R1.1 addition; preserved responsibility |
|---|---|
| Payment Links/Checkout/API | Add QR and Pay Button entry channels; use the same authoritative checkout/payment engine |
| Products and Orders | Add physical-goods delivery integration; merchant normally retains logistics/tax/stock responsibility |
| Reports and Exports | Explicit PDF, XLSX and CSV, including QR/button/channel breakdown and reconciliation exceptions |
| Fees/platform revenue | Dedicated versioned Pricing & Commission Tiers; actual rates undecided |
| Settlements | Explicit Auto-Settlement Settings; no inference of custody or transfer from scheduling |
| Refunds and Disputes | Detailed Refund Management operational workflow; provider evidence required |
| Platform transactions/incidents | Dedicated near-real-time feed with recoverable authoritative event cursor |

## 3. Merchant QR Payments architecture

**OWNER-APPROVED:** a dedicated **XLipa QR Payments** capability with reusable static and request-specific dynamic modes. QR is an entry mechanism to approved hosted checkout, not a separate payment rail or proof that money moved.

### Static QR

A reusable opaque token resolves server-side to a merchant-approved collection configuration for a shop/counter/location/receipt. Default fixed-price catalog or payment-link rules apply. Customer-entered amounts are allowed **only** when the merchant has an explicitly approved variable-amount capability/configuration. Server validates bounded integer minor-unit amount, supported currency/precision, minimum/maximum transaction and cumulative limits, merchant status/capability, fraud/rate controls and reference rules. Client currency, merchant ID or destination is never authority. Missing amount capability/configuration fails closed.

Each scan starts or resumes a separately identified checkout/order; scanning the same static QR twice does not necessarily mean duplicate payment. An approved customer/session idempotency key deduplicates retries of the same intent without incorrectly preventing legitimate repeat purchases. Reference-free counter payments need an approved merchant reconciliation policy, not guessed order matching.

### Dynamic QR

An authenticated merchant action/backend creates a versioned payment request bound to server-owned merchant, product/service/order/invoice reference, amount, currency, expiry and usage policy. QR contains only an opaque public reference/approved hosted URL; amount and current status are retrieved and validated server-side. No secret API key, NIDA, phone, customer name, bank details or unrestricted return URL in the payload. Invoice references remain private server metadata unless deliberately approved for display.

A single-request QR cannot authorize a changed amount or another tenant. Check current request/merchant capability at checkout creation and payment initiation. Concurrent scans and competing attempts require order/request serialization and a unique successful allocation policy. A second actual capture is recorded/reconciled as an overpayment/duplicate-success exception under approved refund policy, never erased to make a one-payment UI appear correct.

### Management, tokens and lifecycle

Merchant tools: generate; preview authorized merchant/request identity; download printable QR; activate/deactivate reusable code; configure approved expiry/usage limits; associate orders; inspect payment history and QR-attributed volume. Printable files contain only public checkout information, generated safely with resource limits and escaped captions. Download/management authorization is scoped; there is no public list of merchant QR configurations.

Use high-entropy unguessable tokens, no sequential database keys as access grants; protect stored token lookup where appropriate. Distinguish reusable public locator from privileged management credentials. Rate-limit resolution and checkout creation. Token rotation/revocation disables future initiation, while immutable existing order/payment references remain traceable. Logs avoid full token URLs. Hosted pages prominently show legitimate merchant and amount; operational guidance should address physical sticker substitution, since an opaque token alone cannot prevent replacement of a printed QR.

Expiry/deactivation closes new payment initiation and reservations. It cannot invalidate money already confirmed by a provider. Late confirmations enter the existing order/payment exception/reconciliation protocol; reserve inventory and define grace/overpayment/refund handling before release. Exactly-once provider payment is not guaranteed by QR generation. Dynamic request state, payment attempt state and settlement state remain separate.

Attribution records `entry_channel=qr`, QR/request/location reference and evidence provenance; a QR scan is not automatically a Facebook/direct/physical-origin fact. Revenue counts only confirmed eligible payment records, once. Reusable-code usage limits need explicit semantics: initiated, reserved or successfully paid counts, with atomic enforcement and asynchronous late-success handling.

**Minimum acceptance:** tenant and token tampering; revoked/expired code; variable amount disabled/out-of-bounds/precision/currency failures; simultaneous scans/final-use race; duplicate initiation/callback; late capture and duplicate actual capture; printable output with no secrets; QR totals matching common payment/ledger/report facts. Physical-scan/mobile browser QA and anti-abuse monitoring are prerequisites to release. No QR renderer/package is installed now.

## 4. XLipa Pay Buttons architecture

**OWNER-APPROVED:** merchant-facing Pay Buttons for external shops and landing pages, including shoes, clothing and electronics. Displaying “Pay with XLipa” does not mean the merchant or rail is live.

**No-code mode:** create a predefined fixed offer/payment link and generate a safe HTML link/button snippet containing only an approved public hosted-checkout URL/identifier and escaped accessible label. No API secret, arbitrary script, editable financial authority or sensitive customer metadata. Prefer a simple hosted redirect link initially; custom scripts/iframes and their CSP/postMessage contracts need separate approval. A public identifier cannot authorize new prices or privileged API calls. Merchant can configure permitted catalog/request association, public label, active status and approved return destination; revocation blocks new checkout initiation without corrupting prior payments.

**Dynamic checkout mode:** merchant trusted backend/serverless service validates cart, stock, tax/delivery policy and approved price versions, then calls the scoped authenticated XLipa API with order/product references and idempotency key. Server validates amount/currency and merchant capability according to the approved integration contract. Browser receives only an approved checkout URL/reference. If a merchant has no trusted backend, use hosted fixed catalog/links; never move a secret into frontend JavaScript to enable a dynamic cart.

Treat browser-submitted item IDs/quantities as requests, not authoritative price, merchant, paid flag or settlement destination. Order snapshots bind approved prices, taxes/shipping components and currency; shipping charges are not invented by XLipa. Redirect URLs use reviewed exact origin/path allowlists with no secret query parameters/open redirects. Pending/success/failure/cancelled displays reflect authorized backend state. Success redirect alone grants neither goods nor access; signed webhook plus authorized server-side status verification drives fulfillment. API idempotency, webhook deduplication/replay protection and key rotation/revocation follow R1.0.

Checkout/buttons need mobile responsiveness, keyboard use, meaningful accessible labels/focus, visible pending/error states and safe retry guidance. Disable accidental duplicate UI submission, but enforce idempotency server-side regardless of UI state. Generated snippet configuration/version and merchant domain association are auditable; domain association alone proves neither price nor payment.

**Minimum acceptance:** no secrets in snippets/public URLs; forged cart/price/currency/merchant/return destination denied; button/configuration revocation; parallel retries one checkout effect; valid signed event and matching server status before fulfillment; accessible desktop/mobile flow. Attribution stores `entry_channel=pay_button` and button/configuration ID independently of API transport or traffic source.

## 5. Physical goods and delivery-order integration

**OWNER-APPROVED:** Products & Orders can support physical goods sold through merchant websites. Merchant normally manages stock, shipping, delivery charges, taxes, returns and customer service unless a separately approved XLipa service assumes a defined responsibility. XLipa is not automatically a logistics company or merchant of record.

Keep independent dimensions: payment state (initiated/pending/confirmed/failed/refunded etc.), commercial order state and fulfillment/shipment state. Proposed customer-facing composite order labels include **Awaiting payment, Paid, Processing, Shipped, Delivered, Cancelled, Returned**. These are future business-order labels, not existing Laravel merchant-application states. “Paid” is derived from the approved payment allocation rule; it does not itself assert shipment. Multiple shipments/partial returns may need item-level fulfillment records rather than one misleading global status.

Merchant integration binds product/SKU, quantity, immutable order/checkout/payment references and an authorized fulfillment event reference/version. Minimize shipping/address/customer data inside XLipa; retain it only where needed and approved. A merchant-supplied shipment/delivery update has merchant provenance and is not independently verified delivery proof. Signed merchant updates, scopes, idempotency and sequence/version checks prevent cross-order writes. No delivery tracking API or carrier contract is invented.

**REQUIRES APPROVAL:** whether partial payments are supported; what constitutes fully paid; inventory reservation expiry and late captures; cancellation before/after capture/shipment; split shipments and item-level returns; refund/tax/shipping-fee allocation; overpayments; service booking differences. Until specified, partial payment cannot silently unlock full fulfillment. Cancellation does not mean refund completed; confirmed payment does not mean delivered; return does not automatically reverse a ledger posting. Preserve events and use approved compensating flows.

**Minimum acceptance:** payment confirmation never marks shipped/delivered; foreign order updates fail; retry-safe fulfillment updates; cancellation/late-payment race and partial refund/return cases preserve accounting; merchant logistics failure cannot falsify provider payment facts.

## 6. Merchant financial reports — PDF, XLSX and CSV

**OWNER-APPROVED formats:** PDF statements, Microsoft Excel XLSX and CSV. Extend R1.0 Reports & Exports without changing its KPI definitions. Reports cover transaction statements; gross payment volume; known fees; net merchant proceeds; successful/pending/failed payments; refunds/reversals; actual method/network; traffic source/campaign; QR; payment links; Pay Buttons; checkout/API; settlements; supported withdrawals; reconciliation exceptions; order/payment references.

Use one report definition/query contract with common metric version, ledger book, currency, timezone, effective-date basis and reproducible `as_of` cutoff. Capture ingestion/reconciliation completeness and approved late-event revisions. Gross, fees, refunds and net retain R1.0 definitions; net proceeds are not profit, unknown fees are not zero. Separate event-period and order-cohort reporting where refunds cross periods. Financial figures reconcile to underlying payment/journal facts under identical filters, not merely displayed page rows.

Filters: merchant and authorized account, date range, status, method/network, source, campaign, product, currency, provider, payment link, QR/button, settlement status and reconciliation state. For merchant users merchant context is server-fixed; changing a browser filter cannot expand access. “Account” means a defined authorized merchant/book/account resource, not any valid user ID or bank number. Chief cross-merchant reports require explicit platform export scope and bounded tenant selection. Multi-merchant reports never combine currencies into one financial total.

Security: fresh tenant/role export authorization on request, background execution and download; private generated files, short-lived nontransferable or appropriately user-bound download authorization, no public storage URLs; immediate revocation enforcement even for previously queued jobs/links. Audit requester, scope/filter digest, metric version, cutoff, row count and download access, without raw report contents. Rate/row/range/CPU/memory/file-size/concurrent-job limits; approved queue timeout/cancellation/retry and retention cleanup. Export failures cannot leak temporary files.

CSV encodes untrusted text so spreadsheet applications cannot execute formula-like prefixes; XLSX writes such values as explicit strings, disables formulas/macros/external links from user data and uses a reviewed safe writer. Do not damage legitimate numeric amount cells or strip meaningful account/reference zeros. Monetary precision must survive Excel numeric limits: where exact values exceed safe spreadsheet precision use exact text plus documented unit/currency, not silent rounding. PDF escapes data, prohibits external resource fetch and bounds renderer resources. No dependency is selected/installed by this plan.

No raw NIDA, API/signing secrets, OTP, sensitive KYC or unnecessary payer information. Customer names/contact require purpose-specific visibility and masking. Correct TZS labels and approved precision; integer minor-unit arithmetic and currency separation remain authoritative. Include report legend, period/timezone/cutoff/completeness and filter summary. Downloads remain accessible on desktop/mobile with an alternative tabular view.

**Minimum acceptance:** PDF/XLSX/CSV totals equal dashboard/ledger for same definition/cutoff; currency/time boundary/late event and unknown-fee tests; malicious formula/text/Unicode/leading-zero values; no secret fields; cross-tenant/account filter denial; revoked permission denies queued/download access; large-job resource/cleanup tests. QR/API/button dimensions reconcile without double counting.

## 7. Flexible Hybrid Pricing Engine and Commission Tiers

**OWNER-APPROVED:** percentage-only, fixed-only and hybrid percentage + fixed fee; configurable **Starter, Business, Enterprise** tiers. Actual rates are **UNDECIDED**. A tier is a commercial assignment, not a payment permission, credit facility or merchant approval. Official provider charges, tax treatment and final XLipa commission are **NOT VERIFIED**.

### Versioned rule contract and deterministic selection

Proposed rule metadata: immutable rule/version ID, status (draft/approved/scheduled/retired), scope/environment, merchant or tier, service/product, supported method, currency, approved special-agreement reference, effective-from/to UTC instants, scaled integer percentage numerator/denominator, fixed minor-unit fee, optional min/max, rounding policy, fee-bearer/base definition, approver/requester and reason. Use `[from,to)` intervals; no ambiguous overlap or retroactive posted repricing. Nonmatching currency/method cannot inherit a guessed rate. Missing applicable approved rule blocks a fee-bearing operation until configured, not silently free or maximally charged.

Proposed precedence: **approved merchant special agreement → merchant-specific → tier-specific → approved platform default**. Within one level, require explicit unique priority and exact approved scope matching; reject overlapping equal-priority rules during approval. Do not invent a hidden “most specific” tie-breaker for conflicting product/method scopes. Selection must yield exactly one rule; otherwise fail safely and alert. Tier effective dates/version are part of matching. Owner must ratify this precedence and whether any fee components may stack; default is one selected XLipa rule, no implicit cumulative commissions.

Formula proposal: calculate percentage using integer/rational arithmetic on an approved base, add fixed component, apply min/max according to an approved order, then round once using the rule's explicit currency rounding convention. The suggested order and tie rounding (for example half-up) require finance approval and test vectors; nothing defaults to binary float. Clearly specify whether taxes/provider charges are outside the clamp/base and when the quote expires. No numerical rates or provider charges are assumed.

### Transparency, posting and change control

Keep provider cost, XLipa commission, tax, payer surcharge (only if explicitly permitted) and merchant net proceeds distinct. Provider costs may be provisional/unknown until reconciliation. Present an authorized fee quote/breakdown before confirmation when contractually appropriate. Freeze rule/version, base, components, rounding and quoted/actual status with the payment/order under approved price-lock timing. If policy changes before an uncommitted checkout completes, honor the valid quote or require explicit refreshed consent; do not silently change an agreed fee. Provider actual-fee adjustments append reconciled entries, not altered original journals.

Sensitive commercial changes require draft→review→approval, separation of duties/reauth, audit and prospective effective dates; emergency suspension has its own audited policy. Merchant-visible tier/rate disclosure must match applied rules without revealing another merchant's agreement. Historical investigation can reproduce the original calculation after rule retirement. Rollback stops/supersedes future versions; it never mutates posted fees. Remediation uses approved linked compensating entries and transparent reasons.

**REQUIRES APPROVAL:** fee base; who bears fees; precedence; min/max and rounding order; partial/full refund fee refunds; provider nonrefundable fees; chargeback costs; tax review; quote lifetime; rule-change approval powers; commercial dispute/retention policy. Refund/reversal rules freeze their applicable original version; cumulative allocations cannot exceed approved refundable components.

**Minimum acceptance:** percentage/fixed/hybrid synthetic vectors, min/max/rounding/large bounds, exact interval boundary, overlap denial, ambiguous/no-rule failure, tier/merchant special agreement selection, stable historic replay, concurrent price changes, quote expiry, partial-refund fee allocation and ledger/platform-revenue reconciliation. Test values are synthetic, never advertised final rates.

## 8. Chief Auto-Settlement Settings

**OWNER-APPROVED future capability:** manual or scheduled settlement, including daily/weekly only where supported. Policy fields may include merchant/currency/provider scope, schedule timezone/cutoff, minimum amount, eligibility delay, holds/reserves, approved verified destination version, restrictions, retry/reconciliation policy and effective version. A configuration is not an instruction to move money without current capability/contract authorization.

Separate six facts: **eligibility → scheduling → initiation → provider acknowledgement → actual confirmed settlement → reconciliation completion**. Reports show each honestly. Scheduling and an HTTP/provider acknowledgement cannot mark settled. Confirmed-settled reporting requires verified settlement evidence and the approved reconciliation check, with exceptions visible rather than suppressed.

Financial operating model determines behavior: if provider owns the settlement schedule/directly settles merchants, XLipa can observe/configure only what its contract permits. Do not invent an XLipa payout API or assume custody. Unsupported scheduling remains unavailable, even if a Chief toggles a UI setting.

Execution proposal: scheduler selects eligible matured funds under the current policy and provider availability; fresh default-deny financial permission/policy checks; parent account serialization and atomic reservation; unique settlement-run/item/business key; approved destination-version binding; audited initiation intent/outbox; provider call outside locks. Competing manual/scheduled/withdrawal/refund jobs share reservations, preventing double payout. Rechecking before external submission must respect already-committed obligations and current restrictions. Destination changes require reauth, ownership verification and approved cooling/dual control; no new destination copied from browser payload.

On ambiguous timeout, keep funds reserved/pending and reconcile through the provider's actual status/idempotency contract; never blindly retry with a new payout reference. Failed confirmed-no-transfer operations release/reallocate reservations through audited entries. Partial/late/reversed settlements create explicit allocations and exceptions. Scheduler restart/catch-up, holidays, cutoff boundaries, duplicate jobs and unavailable provider are operational cases, not excuses to mark paid. Schedule timezone and daylight-saving semantics use approved IANA policy even though initial TZ reporting is straightforward.

Chief configuration and payment execution permissions remain separate, with recent reauth, least privilege, role separation and dual control where approved. Emergency pause, limits, alerting and audited manual recovery precede live automation. **Actual delays, schedules, reserves and minimums remain undecided**, subject to official contracts/legal requirements.

**Minimum acceptance:** duplicate scheduler/manual run one payout intent; concurrent withdrawals/refunds cannot overspend; held/ineligible/unverified-destination funds blocked; timeout/partial/failed/late/reversed evidence handled; no settled label before evidence/reconciliation; unauthorized or stale configuration denied; pause/restart/cutoff tests.

## 9. Chief Refund Management

**OWNER-APPROVED:** refund request, eligibility, partial/full amount, approval/rejection, supported provider submission, pending/confirmed/failed outcomes, contract-supported reversal, dispute evidence and audit history. Separate request/approval state from provider financial state. A Chief approval is permission to attempt an authorized refund, not proof the customer received money.

Proposed protocol: authorized requester identifies original capture and currency; server validates merchant/capture association, applicable contract window and component-level refundable amount; reserve eligible refund amount atomically across approved/in-flight requests; separately authorized approver performs reauth/conflict/dual-control checks as required. Unique request/idempotency keys and captured-version/CAS prevent concurrent over-refunds. Provider submission through approved adapter/outbox outside locks, then authenticated evidence and approved reconciliation establish confirmed completion. Pending/ambiguous timeout keeps reservation pending until evidence resolves it. Reject/confirmed failure releases only the appropriate reservation; duplicate callbacks cannot release or refund twice.

Cumulative confirmed plus reserved refunds must not exceed the approved eligible captured amount, accounting for prior reversals/disputes/chargebacks under explicit policy. Failed attempts do not automatically permit a second external refund if outcome is unknown. A contract-supported refund reversal is a new linked event/posting; do not delete the original refund or pretend a UI reversal retrieves money. Distinguish provider refund support from merchant customer-service/return policy and dispute deadlines.

Record bounded public reasons, encrypted restricted dispute evidence/internal notes and financial audit references. Category/role controls apply to evidence; no raw KYC or provider secret in refund screens. Refund permissions are separate from general merchant/Chief administration and settlement configuration. Fee/tax/shipping/item allocations follow original approved pricing/refund policies; customer notice and digital-access/physical-return actions follow their own after-commit workflow, not automatic payment-state corruption.

**Minimum acceptance:** partial/full caps and parallel approval race; duplicate request/callback; wrong tenant/currency/capture; permission revocation; ambiguous timeout/failed/late confirmed refund; fee allocation; exactly one linked ledger reversal; no confirmed refund from Chief decision alone; dispute/refund interaction reconciles.

## 10. Chief Real-Time Transaction Feed

**OWNER-APPROVED:** dedicated authorized platform-wide operational feed. “Real-time” means a bounded near-real-time view of authoritative backend records, with visible freshness/delay, not a guaranteed instantaneous provider outcome.

Proposed field allowlist: transaction ID, merchant/business name, legitimately collected customer name only with explicit purpose/permission, justified masked contact, amount/currency, actual provider-reported method/network, provider, payment status, creation/provider-confirmation times, order/payment reference, known/unknown fees, refund indicator, settlement status and source/channel attribution/evidence. Unknown names/methods/details remain absent or explicitly unknown; never fabricated. No raw NIDA/account details/API secrets. A narrow operations role need not see customer identity just because it sees transactions.

Represent payment, refund and settlement as distinct state dimensions, not one status that erases history. Initiated/pending/failed come from persisted transitions; confirmed requires approved provider evidence; refunded and settled retain independent provenance/reconciliation. Every feed item carries opaque event ID, entity ID/version, effective/received times, safe event type and authoritative status snapshot/reference. Browser redirects cannot create successful events.

| Delivery option | Benefits / limits | R1.1 recommendation |
|---|---|---|
| Cursor-based bounded polling | Reuses authenticated HTTP, simple recovery/load bounds; updates wait for next poll | Initial design: configurable approximately 5–15 second polling only while view visible, capped batches/rate, jitter/backoff; intervals require capacity approval |
| SSE | One-way push suits feed; reconnect/cursor support, but long-lived connection/auth/proxy capacity and revocation handling needed | Later upgrade after measured need; same durable cursor API/data permissions |
| WebSockets | Bidirectional features; more connection/channel authorization and operational complexity | Defer unless requirements justify it; not implemented now |

Feed reads derive from durable committed payment/outbox events and scoped current records, never a volatile browser-only stream. Cursor ordering is delivery order, not proof of provider business-time order. Entity version prevents stale events overwriting newer state; deduplicate event IDs. On connection loss resume after last acknowledged cursor; if retention gap occurs, resync from an authorized snapshot/cutoff then continue. Historical paginated lookup is separate, with bounded search/filter/date ranges. Search merchant/method/status/time/reference through authorized indexed fields, not unrestricted PII search.

Every poll/page/reconnect rechecks active platform assignment, account/auth_version and field permissions. No public shared channel/cache; privileged cache is private/no-store. Search results/alerts apply the same masking. Alerts are separate deduplicated rules with thresholds, cooldowns, routing/escalation and redacted content, not every received event becoming a notification. Approve feed retention and access/search audit volume, privacy purpose and staff delegation before release. Show ingestion/provider delay and outages distinctly from genuine zero activity.

**Minimum acceptance:** unauthorized/cross-scope field denial; revoked access stops refresh; duplicate/out-of-order/reconnect/resync; missing metadata shown honestly; provider lag/poll timeout/backoff/resource caps; historic pages reconcile; no browser-success confirmation; alert deduplication and sensitive-field leakage tests. No polling/SSE/WebSocket transport is installed or implemented here.

## 11. Updated Merchant Dashboard register and navigation

All 23 R1.0 modules remain. The following register preserves their responsibilities and adds two dedicated modules plus an explicit contextual physical-order workspace. “Reports & Statements” is the navigation label extending Reports & Exports, not its deletion. Physical Goods Orders is a focused view under Products and Orders, not a duplicate order/financial database. Implementation gates below are approval boundaries, not authorization granted now.

| Group | Modules retained/added | Responsibility / gate |
|---|---|---|
| Home | Overview (1) | KPI/readiness, F4 |
| Payments | Transactions (2); Payment Links (3); Checkout (4); **QR Payments (24)**; **Pay Buttons (25)** | Shared authoritative payments, F2/F3; history/analytics F4 |
| Commerce | Products and Orders (8); **Physical Goods Orders (26, contextual workspace)** | Catalog/orders and independent fulfillment, F2/F3 physical sub-gate |
| Developers | API Keys (5); API Documentation (6); Webhooks (7) | Scoped credentials/contracts/delivery, F2/F3 |
| Insights | Revenue Analytics (9); Traffic Sources (10); Campaign Performance (11); Payment Method Analytics (12); **Reports & Statements — PDF/XLSX/CSV (17)** | Same KPI/currency/cutoff, F4/report sub-gate |
| Finance | Wallet and Ledger (13); Settlements (14); Withdrawals (15); Refunds and Disputes (16) | Authorized operating model and ledger, F1/F5 |
| Business | Business Profile (18); KYC Verification (19); Payment Methods (20) | B3.2–B3.4 and separately approved optional method scope |
| Account/help | Notifications (21); Support (22); Security and Account Settings (23) | R1.0 gates/permissions unchanged |

Desktop: grouped sidebar with expandable sections and contextual commerce/developer navigation; avoid 26 equally prominent links. Mobile: compact menu with meaningful group labels, current-location/focus state and accessible disclosure controls; no required hover. Role/capability controls determine server-authorized data/actions; hiding navigation is not authorization. Unimplemented/unsupported services must not have deceptive live controls. Preserve premium XLipa brand/layout language; later browser tests cover small screens, tables/charts, keyboard/focus/contrast and accessible QR/button downloads. No frontend/wording/layout is changed now.

## 12. Updated Chief Admin module register

Preserve all R1.0 operational responsibilities. New dedicated screens extend existing fee, settlement, refund and transaction responsibilities without parallel accounting. Common prerequisite is explicit active platform permission, fresh auth/version, reauth for sensitive changes, privileged MFA/HTTPS readiness, purpose/field controls, audited actions and no blanket superuser/decrypt bypass.

| Group / retained module | R1.1 responsibility / order |
|---|---|
| Onboarding: Merchant Applications; KYC Review; Approvals/Rejections/Information Requests | B3.3/B3.4 unchanged; final internal decision, no financial activation |
| Payments: Platform Transactions; **Real-Time Transaction Feed**; Payment Incidents | Shared facts; F1 then F4 feed sub-gate; feed does not replace historical transaction lookup |
| Commercial: Fees/Platform Revenue; **Pricing & Commission Tiers** | Versioned rule approval/assignments and actual fee journals; F0 pricing specification→F1 engine→F4 reads |
| Finance: Settlements; **Auto-Settlement Settings** | Settings versus execution/confirmed reconciliation; F5 auto-settlement sub-gate |
| Finance: Refunds and Disputes; **Refund Management** | Request/approval/provider/refund states and evidence; F5 refund sub-gate; dispute handling retained |
| Risk: Fraud/Risk Review | Holds/cases/approved escalation; cannot falsify payments or replace KYC evidence |
| Integrations: Provider Configuration; API Governance | Approved adapters/secrets/capabilities; F0–F3; no merchant key reveal |
| Operations: Support; Staff Permissions; Audit Logs; System Settings | R1.0 approval gates retained, purpose-limited access and default-deny capabilities |

Desktop/mobile navigation groups show operational priority and explicit pending/exception counts only from permitted data. Search shortcuts never broaden access. No Chief account, endpoint, role expansion or dashboard implementation occurs in this task.

## 13. Cross-module security and financial consistency

One chain: **authorized channel configuration → server checkout/order → provider attempt/evidence → validated transition + unique balanced journal + outbox → common reporting/notifications/fulfillment**. QR, link, button and API differ in entry/transport metadata; identical approved payment facts produce identical accounting regardless of channel. Pricing snapshot is part of that chain, not a spreadsheet or second balance store.

Use orthogonal dimensions: entry channel (QR/button/link/direct hosted), creation transport (merchant API/no-code), traffic-source provenance (Facebook/unknown etc.) and actual provider method/network. A button can use a link and API; do not sum overlapping dimensions as separate revenue. Pick a documented mutually exclusive primary entry-channel classification for channel totals, while allowing secondary dimensions for filtering. Source attribution remains evidence-labelled and cannot set amount/fees/beneficiary. QR revenue is confirmed payment volume entered via QR, not scan count or proof of physical location.

Tenant/member policies and platform policies remain separate. Every root/nested order, QR, button, pricing assignment, report, settlement and refund reference requires correct composite merchant association and fresh permission; job execution/download/webhook work cannot rely on stale browser roles. Scoped public checkout identifiers grant only the approved purchase interaction. CSRF/session/signature/host protections remain; server-to-server APIs have their own scoped authentication. No merchant actor can assign Chief powers, fee rules or settlement destinations through general profile fields.

Double-entry postings are immutable, unique per approved business event/book/currency and atomic with state/outbox. Fees, refund adjustments, settlement reservations and allocations all reconcile to the same books; never create QR/button balances. Separate currency and legal operating-model books, integer arithmetic/approved rounding, bounded CAS/transactions and target-engine concurrency tests. Preserve application states and current-slot/version constraints; no QR/order/refund states are added to onboarding enums.

Operational prerequisites: provider status/idempotency/reconciliation contracts; approved financial permissions and settlement destination verification; privacy/retention and export controls; monitored queues/scheduler/cursors; alerts/spend/rate limits; independently recoverable encrypted backups/keys; incident/restore/replay runbooks. No provider call under held DB locks. Missing critical contract/config/key/permission fails closed, not via fake success or unknown fee assumed zero. Current SQLite is not proof of production financial concurrency. No financial schema is generated here.

## 14. Updated implementation gates and prerequisites

All F0–F5 and launch gates from R1.0 remain. These sub-gates are review slices only. **Every row requires explicit owner approval of concrete scope/diff/tests**; code/schema preparation, working activation, sandbox adapter activation and production financial permissions remain separate. “Contract required” means before provider-dependent execution; inert documentation/schema tests can be reviewed without claiming the contract exists.

| Capability / proposed order | Dependencies and operational prerequisites | Contract / production financial permission | Security and minimum exit evidence |
|---|---|---|---|
| B3.2-B bounded identity/contact schema preparation — next possible stage | Ratified secure review, exact subset/keys/NIN/contact/OTP policy, isolated synthetic migration plan | No live financial permission; no provider adapter or real collection from schema approval | Reviewed additive SQL/FKs/audit rebuild/up-down-failure/recovery evidence; working DB untouched |
| F0-P Pricing specification; F1-P engine foundation | F0 approved operating model/provider costs, finance rounding/tax/precedence/quote rules; target engine | Official fees/contracts before provider cost/live calculation; live permission separately required | Reproducible versioned fee vectors, overlap denial, historical stability, ledger consistency |
| F2-C Physical order contract | Server catalog/order/checkout and agreed merchant logistics ownership; inventory/event recovery | Payment contract for actual checkout; live collection separately required | Separate payment/fulfillment dimensions, tenant/idempotency/late-capture acceptance |
| F3-Q QR | F1/F2 checkout, capability/limits, opaque tokens, reservation/abuse/scan QA; variable amount policy approved | Official collection/currency/limit contract before live QR; explicit collection permission | Static/dynamic/tamper/expiry/replay/one-allocation/overpayment tests from section 3 |
| F3-B Pay Buttons | F2 scoped keys/orders; F3 signed webhook/fulfillment; safe snippet/domain/return policy and browser QA | Official collection support before live; explicit collection permission | No secret/browser authority; no-code/backend modes, idempotency and accessibility acceptance |
| F4-R PDF/XLSX/CSV | Approved KPI/cutoff/channel definitions; complete common facts, writer review, bounded private export jobs | Reporting synthetic fixtures needs no provider; live data requires approved processing/export permission, not payout permission | Same totals/rounding, formula injection/precision/privacy/revocation/resource tests |
| F4-T Chief feed | F1 durable events/read models, scoped platform fields, cursor retention/recovery/load/alert policy | Official provider mapping for claimed facts; operational read permission, no implicit payout authority | Poll-first bounded load, reconnect/resync/version/dedup/masking and revocation tests |
| F5-S Auto-settlement | Approved model, F1 ledger/reservations, verified destination, pricing/reconciliation, monitored scheduler and emergency pause | Official scheduling/payout/status/idempotency contract mandatory; explicit settlement execution permission | No double payout/overspend, ambiguous response/partial failure, evidence + reconciliation before settled |
| F5-R Refund Management | Eligible capture/component policies, ledger/reservations, independent approval roles, outbox/reconciliation/dispute runbooks | Official refund/reversal support mandatory for those operations; explicit refund execution permission | Concurrent caps/duplicates/timeout/partial fees/linked reversals; no UI-only completion |
| Launch service gate | Relevant B3.5/F gates, production auth/MFA/HTTPS/domain/proxy/hosts, incident/privacy/key recovery/provider/legal review | Explicit service-by-service launch/deployment approval | Isolated/sandbox evidence plus operations sign-off; no real-money fixture shortcuts |

Recommended sequence: finish approved onboarding architecture decisions → separately authorize bounded B3.2-B → C/D/B3.3/B3.4/B3.5 as approved; meanwhile only separately authorized F0 specifications. After F0/F1 consistency foundations, F2 orders/checkout, F3 QR/buttons, F4 reads/reports/feed and F5 operations can be staged according to dependencies. Safety-critical refund/settlement/reconciliation functions needed for a service are **pre-launch prerequisites**, not postponed until after money is collected. No implementation/completion dates are promised.

## 15. Consolidated acceptance and evidence requirements

All tests are **PROPOSED future tests, not run here**. Gate reports must give concrete files/schema/SQL, synthetic fixtures, actual results and remaining limitations. Required common coverage: active/revoked/stale roles and cross-tenant nested IDs; mass assignment; CSRF/signatures/host regressions; amount/currency/price tampering; duplicate/out-of-order authenticated callbacks; CAS/idempotency/atomic audit failure rollback; balanced immutable ledger and provider reconciliation; secrets/masked PII; failed/unknown provider outcomes; recoverable jobs/cursors and field permissions.

Channel acceptance compares equal approved orders through QR/button/link/API: same payment and pricing facts yield the same ledger and report totals. Attributed source and entry channel stay independent; unknown method/source/fee is honest. Exports, dashboard KPIs and feed snapshots explain as-of/currency/coverage differences rather than inventing contradictory balances. Inventory, logistics, fulfillment and actual settlement remain separate evidence domains.

Before production: target-engine concurrency/constraint tests, scheduler/outbox restart and callback outage/recovery, bounded load/resource tests, backup/restore/replay/decrypt rehearsal, approved privacy/retention/incident procedures, mobile/desktop accessibility and financial operation permission review. No tests may mutate the working SQLite or use real NIDA/customers as fixtures. No framework/package/provider implementation is selected merely by this document.

## 16. Outstanding owner, provider and professional decisions

**REQUIRES APPROVAL — owner/technical:** static QR variable-amount eligibility/limits; QR usage/reservation/late-payment policy; token rotation/print display; button snippets/return-domain policy; supported physical-order updates/partial payments/logistics/refund responsibilities; export roles/limits/retention/cutoff formats; exact mutually exclusive channel taxonomy; pricing precedence/base/rounding/min-max/quote lock/fee-bearer/tier assignment and approval workflow; settlement schedules/timezones/delays/thresholds/reserves/destination/retry controls; refund limits/roles/fee reversal; feed fields, polling interval, cursor retention, staff scopes and alerts. Actual pricing rates remain undecided.

**NOT VERIFIED — external/professional:** official Selcom/provider rails/currency/method codes/fees/limits, callback/status/idempotency guarantees, direct settlement versus platform obligations, permitted automatic payouts/refunds/reversals, tax/accounting/legal eligibility, customer surcharge rules, dispute/refund windows, privacy lawful processing/retention and applicable jurisdiction requirements. Obtain current official contracts and qualified review; this proposal makes no legal or pricing claim.

Existing B3.2 decisions remain: approved NIN syntax and subject/declaration/privacy wording, exact email/phone proof scope/lifetime/outage policy, encryption/key recovery/operator plan, minimum schema subset/audit enum and approval gates. QR/buttons do not fix missing secure onboarding infrastructure and cannot bypass eligibility. Production domain/infrastructure remains undecided.

## 17. GO / NO-GO, preservation and stop

**GO:** R1.1 targeted documentation is ready for review alongside unchanged R1.0. All additional owner product requirements are captured without removing existing modules or changing B3.1–B3.5.

**CONDITIONAL GO recommendation for B3.2-B only after** the owner approves the precise bounded schema-preparation subset and resolves the secure architecture decisions listed above. R1.1 is not that authorization and does not broaden B3.2-B to QR/pricing/payment/financial schemas. External payment contracts need not block inert identity/contact schema design, but no real collection or live verification can proceed without its own privacy/technical/operational prerequisites.

**NO-GO:** automatic implementation, working-schema activation, financial permissions, live QR/buttons/SMS/provider operations, accounts, migrations, code/frontend/env changes, dependencies, commit/push or deployment in this task. Only this new Markdown artifact is created. R1.0, secure review, specification, authoritative plans and historical reports remain unchanged; protected-file hashes and HEAD are checked before delivery. No tests/runtime or working database writes are performed.

**STOP — await explicit owner approval of the next concrete stage.**
