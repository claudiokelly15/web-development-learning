# XLipa — Master Roadmap Addendum: Payments, Integrations and Analytics

**Version R1.0; 6 November 2026, Africa/Nairobi. DOCUMENTATION ONLY.** This is a separate proposed addendum to `PHASE-3-STAGE-A-ARCHITECTURE-2026-11-06.md` and the detailed `PHASE-3-B3-ONBOARDING-AUTHORIZATION-PLAN-2026-11-06.md`. Neither authoritative document is replaced or edited. There is no standalone master-roadmap file in the inspected inventory. No completion dates are invented.

Labels: **VERIFIED** = inspected project evidence; **OWNER-APPROVED** = requested product direction, not execution permission; **PROPOSED** = architecture for review; **NOT VERIFIED** = missing evidence/contract/implementation; **REQUIRES APPROVAL** = separate concrete execution gate. All future modules below are PROPOSED implementations of OWNER-APPROVED capabilities.

## 1. Current position and unchanged Phase 3 structure

**VERIFIED:** canonical project is `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`; Laravel is `backend`, authoritative public frontend is `frontend`, Git root is the parent directory. Branch `main`, HEAD `7c3e4743384e0ca0acd7b029c944569c84304541`. B3.1 scoped merchant status/context/policies exist. B2 native session authentication, signed email verification, account/auth-version gates and trusted-host controls exist. Production authentication remains intentionally gated. Last historical suite result is 108 tests / 847 assertions and 31 frontend checks; these were not rerun for this documentation task.

Initial tree contained the two untracked B3.2 planning/specification documents. They remain historical inputs. READONLY SQLite checks found integrity `ok`, zero foreign-key violations, nine migration records and user/merchant/membership/application/audit counts 1/1/1/1/1; platform role assignments zero. No OTP, secure NIDA store, upload service, payment API, ledger or financial dashboard was found.

**OWNER-APPROVED baseline:** B3.2-A v2.0; TZ only; individuals, sole proprietors, registered businesses/companies and unregistered individuals may apply. Minimum submission information is business name, NIDA, phone and email; exact contact ownership must be verified. Optional broad profile/documents/payment methods are not universal initial-submission prerequisites. Chief Admin makes the final internal application decision. Internal approval does not grant financial capability or establish legal/provider eligibility.

| Existing milestone | Preserved meaning and next dependencies |
|---|---|
| B3.1 | Completed local read-only context/policies/status foundation; no full dashboard claim |
| B3.2 | Profile foundation expanded by v2.0 minimum identity/contact design; architecture review, then separately approved B schema preparation, C working activation, D capture/verification implementation |
| B3.3 | Submission/history only after secure minimum data, exact proofs, immutable evidence bindings and fail-closed readiness work |
| B3.4 | Restricted Chief review, independent reviewer/conflict/reauth safeguards; no administrator provisioning side effect |
| B3.5 | Onboarding acceptance/hardening; scoped tests elsewhere do not complete this milestone |

The companion `XLIPA-B3-2-SECURE-ARCHITECTURE-REVIEW-2026-11-06.md` supplies the concrete identity/contact/OTP schema proposal. This addendum adds future gates after or alongside their genuine dependencies; it does not rename B3.1–B3.5. Domain remains unknown for production; local `http://xlipa.test` is preserved.

## 2. Gaps requiring explicit design

The inspected plans provide onboarding foundations, not a complete payment contract. Missing detailed designs include provider eligibility/contracts and settlement model; payment/order state machines; authenticated callbacks and durable inbox/outbox; ledger accounting/reconciliation; merchant credentials and hosted checkout; links/catalog limits; attribution provenance; method-code adapters; reporting definitions and data completeness; fulfillment retries; dispute/refund operations; privileged access; privacy/retention; incident response, recovery and deployment acceptance. These are gaps in specified/implemented capability, not evidence of a current exposed vulnerability.

Official Selcom methods, bank lists, request fields, callbacks, idempotency guarantees, currencies, charges, settlement timing and merchant eligibility are **NOT VERIFIED**. No API contract or provider integration is fabricated here. Financial eligibility, custody, legal obligations and tax/accounting treatment require qualified review and signed provider terms.

## 3. Recommended future gate order

These labels are addendum review gates, not replacements for Phase 3 milestone numbers. Every gate requires an explicit approved scope/diff before execution. Sandbox success never authorizes live money.

| Gate/order | Purpose and dependencies | Exit evidence / owner approval |
|---|---|---|
| F0 — financial/provider specification | After onboarding policy is settled: legal operating model, official provider contract, currencies, service eligibility, threat model, privacy and target production DB | Approved state/accounting diagrams, capability matrix, callback contract, fee/refund/settlement rules; no live integration permission |
| F1 — payment consistency foundation | F0; immutable ledger design, order/payment states, durable callback inbox, outbox, reconciliation and sandbox adapter | Isolated duplicate/out-of-order/concurrency/failure tests, balanced postings and recovery drill; separate schema preparation/activation gates |
| F2 — merchant integration foundation | F1; server-authoritative catalog/orders, checkout, scoped credentials, sandbox documentation | Tenant isolation, price/currency checks, credential lifecycle, idempotency and hosted checkout acceptance |
| F3 — payment links and integrations | F2; links, merchant API, outbound webhooks, safe return URLs, fulfillment and attribution capture | End-to-end sandbox checkout → confirmed payment → one posting/event → one entitlement; forged redirects and replay fail |
| F4 — reporting/dashboard reads | F1–F3 reliable data; approved KPI/source/method policies, privileged read permissions | Cards/charts/exports reconcile with underlying records, tenant isolation and responsive/accessibility review |
| F5 — financial operations | F0/F1 and actual provider support; settlement/refund/dispute/withdrawal permissions and operational controls | Partial/reversed/failed flows, reconciliation, dual control where approved, incident/recovery tests; no assumed withdrawal support |
| Launch gate | All relevant gates plus B3.5, production auth/HTTPS/MFA/proxy/host config, monitoring, backups/key recovery, support and legal/provider sign-off | Explicit service-by-service live capability and deployment authorization, sandbox evidence and rollback/incident runbooks |

F5 requirements needed to operate a payment service safely are prerequisites to that service's launch, not deferred behind an unsafe live pilot. Work can be reviewed in smaller slices; no blanket B–D, financial schema, provider or launch approval follows from this document.

## 4. Hosted payment links and landing pages

**OWNER-APPROVED future capability:** no-code payment links for videos, e-books, audio/downloads, physical goods and services. **PROPOSED data:** opaque public token, server-owned merchant ID, product/service reference/name, versioned price in integer minor units, explicit supported currency, merchant reference, active/paused/expired state, optional expiry/usage limits, approved campaign/source tags, checkout/order association and history. A public token identifies a permitted purchase offer; it grants neither merchant administration nor arbitrary amount selection.

Server resolves merchant, approved capability, catalog version, amount and currency. Browser parameters cannot override price, merchant, settlement destination or product. Define whether a usage limit counts reserved checkouts or successful purchases; enforce atomic capacity reservations with expiry and explicitly handle late asynchronous success after reservation expiry. Never silently oversell or discard a paid order. Stock/delivery/tax/shipping responsibilities need separate product policy. Paused/expired links block new checkouts without falsifying existing pending payment outcomes.

Browser-only static websites use a predefined hosted link/catalog checkout or a restricted publishable identifier. The identifier is public and cannot create arbitrary privileged operations. Dynamic cart prices require a merchant-controlled backend/serverless endpoint with protected credentials, or a strictly server-owned hosted catalog. CORS and hidden JavaScript are not secret protection. No API secret appears in links, query strings, page source, frontend bundles or customer-visible URLs.

**Acceptance:** tampered amount/currency/tenant/product rejected; expiry and simultaneous final-use requests tested; existing pending outcomes remain reconcilable; copied links preserve offer integrity without proving traffic origin. Responsive branded checkout and clear failure/pending outcomes require browser QA before release.

## 5. Merchant API, checkout and credential lifecycle

**PROPOSED XLipa contract, not invented Selcom fields:** merchant-scoped server-to-server credentials with environment and granular scopes (create checkout, read payment, manage approved resources). High-entropy opaque secrets shown once, stored as nonrecoverable authentication digests, compared safely; bounded overlap rotation, immediate revocation and audited issuance. Separate sandbox/production accounts, keys, data and webhook secrets. Sensitive actions require fresh permission/reauth; key lists reveal masked identifiers only. No broad Chief bypass or secret support exports.

Authenticated checkout creation binds merchant order reference, server-validated product/amount/currency and approved return destination. An idempotency key is scoped to merchant + operation + environment, bound to a canonical request digest; same key/same request returns the original result, different payload conflicts. Retention and retry window must be documented. Concurrent creation has a database uniqueness guard. A key is not a tenant authorization substitute.

Checkout/order/payment are separate records: one order may have multiple failed/pending attempts, but fulfillment depends on the approved successful payment policy. Define terminal/nonterminal transitions, delayed success, overpayment/duplicate successful attempts and refund handling before implementation. Minimize payer data; merchant NIDA onboarding requirements do not automatically apply to paying customers.

Provide versioned API documentation, sandbox examples with synthetic data, scope/error reference, pagination, rate limits, correlation IDs, retry guidance and deprecation policy. Errors must not expose credentials, provider bodies, payer data or foreign orders. Payment-status reads require merchant-scoped authorization and return current server facts. TLS, input validation, endpoint authorization and bounded requests follow primary [OWASP REST security guidance](https://cheatsheetseries.owasp.org/cheatsheets/REST_Security_Cheat_Sheet.html); concrete XLipa limits require approval.

**Acceptance:** revoked/rotated/wrong-environment credentials fail; all cross-tenant IDs denied; parallel same-key requests produce one effect; client totals cannot override catalog; stale/pending status does not become successful through redirect input.

## 6. Provider callbacks and merchant webhooks

### Inbound provider events

Use only the official provider authentication/signature contract once obtained. Preserve necessary raw bytes for verification under an approved short-retention policy; validate environment, provider account, merchant/order association, amount, currency and trusted reference. Never trust an unsigned event ID/header merely because it looks unique. Durable inbox deduplicates an authenticated stable provider event ID scoped to provider account/environment; if the provider supplies no usable ID, approve an alternative deduplication/reconciliation design against its actual guarantees.

Expect duplicates, delays, missing events and order changes. State transitions must resist regression; contradictions create an exception/reconciliation case. Acknowledgement follows durable acceptance according to provider protocol. Provider acceptance of a request is not payment capture. Browser return parameters never confirm a payment. Public [Stripe webhook documentation](https://docs.stripe.com/webhooks) illustrates signature, duplicate and event-order concerns; it is a reference pattern only and supplies no Selcom contract or integration authorization.

Commit validated payment transition, unique financial posting and outbound event intent atomically; retrying a callback cannot credit twice. Reconciliation independently detects lost callbacks and discrepancies. Network calls stay outside held database transactions.

### Outbound merchant notifications

Persist a versioned immutable event in a transactional outbox. Stable event ID identifies the business event across retries; delivery attempts have separate IDs/times. Proposed signing contract: HMAC-SHA256 over documented timestamp + delivery ID + exact raw body, explicit key version, constant-time validation and a proposed five-minute replay window. Exact encoding/header names are future XLipa design decisions. Receiver also deduplicates durable event IDs; a valid fresh signature does not make a duplicate event new. Rotating signing secrets requires protected recoverable storage, unlike hashed incoming API authentication keys.

Bounded exponential backoff/jitter, delivery timeouts, attempt/age ceilings, dead-letter status and audited manual replay; no infinite retry or guaranteed exactly-once network delivery. Merchant receiver durably queues before success response. Status verification API is an authorized recovery path.

Webhook endpoint registration is an SSRF boundary: HTTPS, no userinfo, no arbitrary redirects, bounded payload/time, restricted egress, and rejection of loopback/private/link-local/metadata targets including IPv6. Validate resolution and the actual connection destination against DNS rebinding; review IP changes rather than relying on string checks. Secrets and payload PII stay out of logs. Only permitted public endpoints are reachable.

**Acceptance:** wrong signature/body/key/environment/replayed deliveries denied; duplicates produce one merchant effect; outage/recovery/manual replay preserves event identity; internal-network/DNS-rebinding tests fail closed; retries never create duplicate financial postings.

## 7. Revenue source attribution

**OWNER-APPROVED categories:** Facebook, Instagram, WhatsApp, TikTok, Google, YouTube, direct website, other identifiable source, unknown/unattributed. These are analytics categories, not financial authorities.

**PROPOSED capture:** allowlisted bounded UTM source/medium/campaign/content/term; server campaign ID, source-specific payment-link ID, merchant landing-page reference, sanitized referrer origin when available, observation timestamp and checkout/order IDs. Preserve selected attribution through redirects by opaque server-side checkout/session references, never by trusting returned arbitrary UTM text. Record event provenance (`merchant_declared`, `browser_observed`, or future contract-verified partner evidence), evidence type and policy/mapping version. Signing merchant labels protects integrity; it does not prove that Facebook or any other platform sent the visitor.

First-touch and last-touch are separate proposed models. Initially limit to the same authorized checkout/session without cross-session identity tracking. A future cross-session window (for example 30 days) requires explicit privacy/consent/storage approval. Define touch eligibility and tie-breaking, freeze the chosen order attribution before payment, and attach confirmed payment facts through immutable IDs. Corrections append a version with provenance; they never rewrite the ledger. Display model and confidence/evidence, including low-confidence declared labels. Never add first-touch and last-touch totals together as extra revenue.

Revenue-by-source joins confirmed financial events with the selected attribution version; each event contributes once per model/currency. Refund attribution follows the original paid order under an approved reporting policy. Unknown/unattributed remains visible and contributes to totals. No phone prefix, mobile-money network, merchant label alone or missing referrer proves a platform source. Direct website requires suitable evidence; absent evidence stays unknown rather than defaulting every unknown visit to direct.

Messaging apps may suppress referrers; copied links retain old campaign tags, cross-device journeys break continuity, privacy settings/ad blockers remove evidence and browser metadata is spoofable. Report association, not perfect causal measurement or advertising-platform billing equivalence. Do not fingerprint customers or collect raw URL queries/click IDs by default. Strip sensitive parameters, bound metadata and approve tracking retention, notices/consent and recipient access. General minimization/security principles are informed by [PDPC's published privacy notice](https://pdpc.go.tz/privacy-notice/); this is not confirmation of XLipa's legal basis.

**Acceptance:** copied/forged labels never change amount, fees, beneficiary or financial status; duplicate callbacks do not duplicate attributed revenue; unknown share is measurable; selected-model totals reconcile to confirmed payments; no unapproved cross-device or personal tracking.

## 8. Actual payment-method analytics

Vodacom M-Pesa, Airtel Money, Mixx by Yas, HaloPesa, supported banks and other officially supported methods are **OWNER-APPROVED candidate reporting categories**, subject to actual provider support, which is **NOT VERIFIED**. Existing logos/marketing are not support evidence.

A reviewed provider adapter maps official raw codes to a versioned internal method/network taxonomy, preserving restricted source reference, mapping version and provenance. Distinguish provider (processor), payment network (rail) and merchant settlement method. Missing/unknown method remains unreported/unknown; never infer it solely from a telephone prefix. No Selcom codes, bank list or unsupported rail is invented.

Proposed facts: merchant/order/payment IDs, authenticated provider reference, provider-reported method/network, normalized status, integer amount and currency, explicit known/unknown fees, effective and received timestamps, independent refund state and settlement state. Provider acceptance, customer debit, capture, refund and settlement can have different timestamps/statuses. Redact payer/account identifiers in analytics. Preserve the original fact plus mapping correction versions instead of silently changing history.

**Acceptance:** unknown-code and missing-fee fixtures remain honest; remapping is reproducible; method totals reconcile per currency; partial refunds and settlement status do not erase captured-payment history.

## 9. Ledger, wallet and reconciliation

**PROPOSED:** merchant-specific accounts in a double-entry ledger with immutable journals/entries and explicit currency/book identity. Every posted journal balances debits and credits per currency; money uses bounded integer minor units and an approved currency precision catalog, never binary floats. TZS presentation/precision follows the approved provider/currency contract, not an assumption that every currency has two decimals.

First approve the operating model. If providers settle directly to merchants, wallet may be an operational/memorandum reporting ledger: it must not pretend XLipa holds those assets. If a later authorized model creates platform-controlled funds/liabilities, its legal authority, safeguarding, account ownership and bookkeeping need explicit expert/provider approval. A reporting book is not automatically XLipa's statutory general ledger. Never mix books or treat gross sales as bank cash.

| Distinct fact | Meaning |
|---|---|
| Customer payment initiated | Request/attempt exists; no earned or available funds implied |
| Provider payment confirmed | Authenticated evidence meets approved capture/completion contract |
| Reporting revenue recognized | Approved versioned platform metric recognizes the confirmed event; not a legal tax/GAAP assertion |
| Available for settlement | Ledger/policy says matured eligible funds less fees, holds, reserves, refunds and committed withdrawals; not simply total sales |
| Actually settled | Provider/bank evidence and reconciled settlement entries confirm movement under the operating model |

Unique business-event + book + currency keys prevent duplicate postings. Payment transition, balanced journal and outbox intent commit together, with tenant/amount/currency checks and target-engine concurrency tests. Corrections use linked reversal/adjustment journals; never edit posted history. Pending, available, reserved, settled, fees, refunds and dispute/chargeback obligations are distinct accounts/projections. Cumulative refunds cannot exceed eligible captured value under policy; fee refunds and partial settlements are explicit. Prevent withdrawal races using reservations and serializable/CAS semantics; `lockForUpdate` on SQLite alone is insufficient.

Available balance must be reproducible from posted entries and approved holds/reservations. Show deficits/holds truthfully and block unsupported withdrawals rather than silently replacing negatives with zero. Currency balances remain separate; FX requires a separately approved quote/rate/rounding/posting design. Financial operations require fresh capability permission, reauth and approved dual control/risk limits, not broad profile-edit rights.

Reconcile internal payments/ledger against provider transactions, fees and settlement statements per currency/cutoff. Record breaks (missing, duplicate, wrong amount/currency, fee mismatch, unmatched payout), ownership, evidence, resolution and adjustment approval. Do not edit financial records to make reports match. Statement ingest needs checksum/idempotency, restricted storage and parser validation. Pending settlement is not provider cash confirmation. Reconciliation coverage/as-of timestamps appear in reports.

**Acceptance:** balanced-journal enforcement, callback replay, partial refund/chargeback/settlement, audit failure rollback, over-withdrawal race, reconciliation discrepancy and restore/replay tests. Financial tables/postings are not created in this task.

## 10. Secure digital-product fulfillment

Merchant websites may host videos, books, audio and files. XLipa does not automatically host or license merchant content. Bind merchant product, order, checkout and confirmed payment references. Signed webhook triggers the merchant backend to verify current payment server-side and compare merchant/order/amount/currency/product before granting entitlement. Browser success URLs are navigation only, never proof.

Use a stable fulfillment reference and unique order-item entitlement key; retry/delivery deduplication prevents repeated grants. Track queued/fulfilled/failed/revoked events separately from payment state. Merchant defines access duration, download limits, authentication and content delivery; signed short-lived merchant access URLs must not expose XLipa API secrets. Safe return URLs are approved exact origins/paths, not arbitrary redirect input; no secret or sensitive data in return queries.

Refund/reversal events suspend/revoke future access under an approved merchant policy and preserve evidence. Already downloaded content cannot be guaranteed recoverable; do not promise revocation erases files. Partial refunds require item-level policy. Physical/service fulfillment needs its own inventory/shipping/booking rules, not automatic digital entitlement reuse.

**Acceptance:** forged success redirect grants nothing; mismatched order/payment fails; duplicate/reordered webhook yields one entitlement; receiver outage recovers; refund/partial refund and expired access follow documented rules.

## 11. Merchant Dashboard module register

All modules preserve the existing XLipa brand and require later responsive visual/accessibility acceptance. Common security baseline **S**: fresh active/verified identity and auth_version; server-scoped merchant; explicit per-action role/capability; escaped response allowlist; privacy headers/redacted logs; CSRF for session writes; audit/concurrency for mutations. Owner/manager/staff rights are specified per module before building; current staff status access does not grant financial/profile access. Every row requires its named gate's explicit owner scope approval. Data below are future records, not new tables now.

| Module / purpose and data | Dependencies / order gate | Additional security | Minimum acceptance |
|---|---|---|---|
| 1 Overview — approved KPI snapshots and readiness | F4 after reliable F1 facts | S; no sensitive KYC/raw account data | Cards reconcile, missing data honest, no fake balances |
| 2 Transactions — scoped payment/attempt/refund history | F1→F2→F4 | S; masked payer/provider details | Pagination/filter totals and lifecycle distinctions correct |
| 3 Payment Links — offer/version/token/status management | F3 | S; owner-approved write scope, atomic limits | Tampering/expiry/concurrent usage tests |
| 4 Checkout — hosted sessions/order outcomes | F2/F3 | Public purchase token restricted; no secret/browser price authority | Pending/success/failure secure end-to-end flow |
| 5 API Keys — scoped credential lifecycle | F2 | Reauth, show-once secret, hash/revoke/rotate | Revoked/wrong-environment/scoped-key denials |
| 6 API Documentation — versioned contract and sandbox | F2 then F3 | Synthetic examples; no customer/live secrets | Working sandbox examples, idempotency/errors documented |
| 7 Webhooks — endpoints/delivery history/replay | F3 | SSRF egress controls, encrypted signing keys, reauth | Signature/retry/dead-letter/replay tests |
| 8 Products and Orders — catalog/versioned order items | F2/F3 | Server price/currency, inventory CAS | Snapshot stability, capacity and duplicate-order tests |
| 9 Revenue Analytics — approved financial aggregates | F4 | S; currency/book separation | KPI/chart totals match posted confirmed facts |
| 10 Traffic Sources — evidence-tagged source reports | F3 capture→F4 | Privacy minimization, no authority from labels | Unknown visible; selected-model revenue reconciles |
| 11 Campaign Performance — campaign/link/model comparison | F3→F4 | Sanitized labels, provenance; no causal guarantee | No first/last-touch double counting |
| 12 Payment Method Analytics — actual reported rail | F1 adapter→F4 | Restricted raw codes, mapping version | Unknown codes/fees and refunds reported correctly |
| 13 Wallet and Ledger — book-specific entries/balances | F1→F4 | Explicit read permission, immutable postings | Per-currency balanced entries and reproducible availability |
| 14 Settlements — batches/allocations/provider evidence | F5 | Restricted reads/operations, reconcile before settled | Partial/missing/reversed payout handling |
| 15 Withdrawals — supported requests/reservations | F5 only if contract permits | Reauth, limits, dual control where approved | Concurrent requests cannot overspend; failed requests release safely |
| 16 Refunds and Disputes — case/evidence/reversal tracking | F5 | Separate initiate/approve powers; private evidence | Partial/refund caps, deadlines and duplicate reversal tests |
| 17 Reports and Exports — scoped CSV/PDF jobs | F4/F5 as data permits | Reauthorize download/job, TTL, safe cells | Same cutoff/filters/currency totals as dashboard |
| 18 Business Profile — optional declared draft details | B3.2-D | Current-draft policy/CAS; staff denied | Optional absence not submit blocker; no privileged assignment |
| 19 KYC Verification — masked identity/proof/evidence status | B3.2 secure modules→B3.3/B3.4 | Owner sensitive capture; category permissions/private scans | No raw NIN leakage; exact proof and tenant bindings |
| 20 Payment Methods — optional bank/mobile-money versions | Separate method gate then F5 | Owner-only default, encrypted/masked data, reauth | Changes invalidate method proof; OTP not account-holder proof |
| 21 Notifications — preferences/redacted delivery status | B3.3 onward; F3 outbox | Opt-in/purpose rules, no secrets/PII in preview | After-commit delivery, deduplication and outage handling |
| 22 Support — scoped tickets and safe attachments | Separate support gate alongside F4 | Explicit support access, no impersonation or key reveal | Tenant isolation, redaction and audited access |
| 23 Security and Account Settings — sessions/reauth/controls | Existing B2 then separately approved hardening | Preserve auth/CSRF/signatures; verified contact-change protocol | Revocation/auth_version, recovery and MFA readiness tests |

Payment Methods means merchant settlement/account metadata, whereas Payment Method Analytics describes the customer's actual payment rail. These must not share a transferable verified flag.

## 12. KPI definitions and reporting contract

**PROPOSED definitions require owner/finance approval and policy versioning.** Aggregate only authorized merchant records and one selected currency/book. Display effective period, timezone, cutoff/as-of and completeness. Distinguish event flows during a period from current status stocks; late callbacks/reconciliation can revise historical effective-date totals with traceable versions.

| KPI | Proposed deterministic definition / limitation |
|---|---|
| Gross revenue | Sum eligible distinct confirmed captures under approved recognition policy; excludes initiated/pending/failed attempts; refunds displayed separately |
| Net revenue after known fees | Gross minus applicable refunds minus known fees (plus approved fee reversals); disclose exact policy. Missing fees mean incomplete/estimated net, never assumed zero. Not business profit |
| Successful transactions | Count distinct eligible successful payment IDs, not callback/attempt counts; later refund does not erase capture history |
| Pending / failed transactions | Separate documented payment/attempt population and status-as-of cutoff; never combine attempt failures with successful-payment denominator silently |
| Refunded amounts | Sum confirmed refund events in selected currency/effective period, including partial refunds once |
| Available balance | Ledger-derived approved available projection less active reservations/holds at cutoff; not revenue total |
| Pending settlement balance | Eligible posted amounts awaiting confirmed settlement under model; disclose holds/provider reconciliation coverage |
| Total settled | Confirmed reconciled settlement allocations, net of explicit settlement reversals |
| Payment success rate | Successful eligible outcomes / (successful + failed terminal eligible outcomes); pending excluded, cancelled handling explicitly approved; zero denominator = N/A |
| Average transaction value | Eligible gross / successful transaction count; zero count = N/A |
| Revenue growth | (Current comparable metric − prior metric) / prior metric; prior zero = N/A; show absolute change; compare like currency/coverage |
| Best traffic source | Highest confirmed attributed gross under named first- or last-touch model; show unknown and evidence, not proven causation |
| Best payment method | Highest confirmed volume or count under explicitly selected metric; no inferred speed/reliability claim |

Fees/taxes/discounts/shipping, chargebacks, revenue-recognition timing and fee refund treatment require explicit contracts. Gross sales and platform fee revenue are separate measures. Partial refunds crossing reporting periods need both event-period refund reports and clearly labelled order-cohort net views; do not silently mix them.

### Required performance line chart

**OWNER-APPROVED future requirement:** responsive time-series revenue chart with daily/weekly/monthly buckets, custom date ranges, gross versus net, transaction-count trend and comparable previous period. Use separate panels/axes with clear units rather than treating TZS and transaction count as the same quantity. Default proposed merchant reporting timezone is `Africa/Dar_es_Salaam`; store event timestamps in UTC, use an approved IANA timezone, and query local calendar boundaries as `[start, end-exclusive)`. Future timezone/DST changes require boundary tests. Define week start and equal-elapsed-period comparison explicitly.

Use approved currency precision and locale formatting consistently for TZS; never add other currencies into a TZS total. Fill genuine zero-activity buckets only when ingestion coverage is complete. Missing coverage, unknown fees or unavailable net are gaps/incomplete states, not invented zero values. Show as-of/reconciliation status and late-event revisions. Empty/loading/error states must be distinct; no sample values posing as live financial results. Mobile layout, keyboard-accessible legend, contrast and an equivalent data table need acceptance tests.

Additional proposed charts: source revenue with unknown category; actual payment-network revenue; transaction status counts; top products; campaign performance by selected attribution model; settlement trends. Each uses the same documented metric/filter/currency/cutoff contract as cards and exports.

### Filters and secure exports

Date range, transaction status, source, campaign, product, payment method, provider, payment link and settlement status use server allowlists/scoping and bounded ranges. Define whether date is capture, refund or settlement date. Common query definitions and immutable report cutoff/snapshot make cards, rows, chart and export totals reproducible. Avoid pagination sums masquerading as whole-report totals.

CSV/PDF generation requires explicit export permission; job execution and download both recheck membership/account state. Short-lived private download references, audited access, retention/row/resource limits, no public object URLs and no raw KYC/secrets. Prevent spreadsheet formula injection in untrusted string cells while preserving typed financial numeric values. PDF templates escape content and disable external fetch. Queued reports cannot remain downloadable after access revocation. Acceptance compares totals/rounding/time boundaries and tests cross-tenant/download-replay/large-export failures.

## 13. Chief Admin operations register

No Chief account, endpoint or dashboard is implemented/authorized now. Common baseline **P**: separate platform policy and active assignment, least privilege, fresh account/auth_version, recent reauth, privileged MFA/HTTPS readiness, audited purpose, field-level response allowlists and conflict-of-interest checks. Chief title is not unrestricted NIN/document/credential access or a `Gate::before` bypass. Merchant owner/manager/staff never receives platform-wide information. Each row requires explicit scope approval at the indicated gate.

| Module / purpose and required data | Dependencies / gate order | Additional security | Acceptance |
|---|---|---|---|
| Merchant applications — queue/state/evidence versions | B3.3→B3.4 | P; no self-review; CAS | One competing final decision wins, audit atomic |
| KYC review — masked identity/findings/private clean evidence | Secure KYC→B3.4 | P; category-specific decrypt/stream permission; no general full-NIN reveal | Cross-case/tenant denial, masked output and access audit |
| Approvals/rejections/information requests — bounded reasons | B3.4 | P; Chief final internal decision only | No financial auto-activation or suspension bypass |
| Provider configuration — references/capability versions | F0→F1 | P; secret manager, approved change/dual control | No plaintext credential display; config rollback/fail-closed tests |
| Platform transactions — authorized cross-merchant operational view | F1→F4 | P; masked payer details and scoped staff delegation | Totals reconcile without exposing unrelated sensitive profile data |
| Payment incidents — callback/reconciliation/delivery cases | F1/F3 | P; bounded redacted diagnostics | Traceable replay/runbook, no double credit |
| Settlements — batches/breaks/approvals | F5 | P; separation of duties and evidence | No unaudited payout or false settled status |
| Fees/platform revenue — contract/rate versions and journals | F0/F1→F4 | P; prospective approved rate changes, no posted-history edits | Fees reconcile and differ from merchant gross |
| Fraud/risk review — purpose-limited cases/holds | F0 then F5 | P; bounded evidence access, appeal/review policy | Holds don't rewrite payments; no opaque blanket KYC demands |
| Refunds/disputes — deadlines/evidence/adjustments | F5 | P; capability-specific approvals | Refund caps, duplicate prevention, dispute audit |
| Support — tickets/authorized merchant context | Support gate→F4 | P; temporary purpose-bound access; no impersonation/secrets | Access expires/revokes and is audited |
| API governance — scopes/version/rate/endpoint incidents | F2/F3 | P; no merchant secret reveal | Key revocation and contract deprecation tests |
| Staff permissions — role assignments/delegation | B3.4 privileged-access gate | P; no self-escalation, reviewed grants/revocations | Revocation effective next request; final-decision delegation separately approved |
| Audit logs — bounded operational trails | B3.4→F1/F5 | P; restricted reads/exports, integrity monitoring | No raw NIN/OTP/secrets; model guard not claimed tamper-proof |
| System settings — policy versions/feature capabilities | F0→launch | P; change control, staged rollout/rollback | Financial capability denied by default and configuration drift detected |

## 14. Data and security foundation across modules

Future conceptual entities include catalog/product versions; merchant orders/items and checkout sessions; payment attempts/events; provider inbox; idempotency records; merchant API credential metadata; webhook endpoints/outbox/deliveries; attribution observations/bindings; method mappings; ledger books/accounts/journals/entries/holds; settlement allocations; withdrawal/refund/dispute cases; fulfillment references; report snapshots/jobs; restricted platform operations. This is not approval to create those tables. Concrete columns, tenant composite FKs, uniqueness, indexes, retention and migration grouping require reviewed schemas at each gate.

Keep merchant/application ULIDs and existing integer user identities. Composite tenant relationships prevent foreign child attachment. Every financial event/version has provenance and immutable reference; actor IDs, amounts, currency, status and verification metadata are server-owned. Preserve application states and separate payment/OTP/evidence/settlement states rather than overloading onboarding enums. Use target-engine transaction/concurrency tests; SQLite alone does not establish production financial locking behavior.

Protect key types separately: application encryption/key recovery, identity envelopes, OTP HMAC/quota fingerprints, API authentication digests and recoverable webhook signing keys have different access/rotation needs. Key loss is not fixed by changing APP_KEY. Independent encrypted backup and isolated restore/replay/decrypt rehearsals, retention/holds and authorized operator/processor review precede real sensitive use.

Deployment gate includes explicit production domain, DNS/TLS/public root, exact hosts/proxies/origins, secure cookies, trusted callback endpoints, secret provisioning, approved production auth enablement, queue/scheduler supervision, webhook/ledger/reconciliation monitoring, alerts and spend limits. No wildcard host shortcut, disabled signatures/CSRF, public secrets, real-money synthetic tests or permissive fallback adapters. Incident runbooks cover compromised credentials, callback outage, reconciliation breaks, privacy breach, restore and provider downtime. Rollback must reconcile subsequent financial writes; restoring a stale DB blindly is unsafe.

## 15. Approval and acceptance register

**REQUIRES APPROVAL:** ratify this addendum without altering the existing roadmap; approve bounded B3.2-B architecture/schema subset from the companion review; separately approve working activation and UX implementation. Resolve exact NIN syntax/privacy/key plan and phone-proof policy before real collection. Optional documents and payment methods remain independent gates.

Before payment work: obtain official provider/eligibility/rail/currency/callback/fee/settlement contracts and qualified legal operating-model review; approve ledger book/state/recognition/reconciliation semantics and target DB; approve API/webhook/credential contracts; attribution privacy/evidence/model policy; KPI/timezone/fee/currency/export definitions; fulfillment/refund responsibilities; Chief/staff capabilities and operational dual control; recovery/monitoring and deployment runbooks. No completion date, legal certification, provider support or financial activation is implied.

Future acceptance must include cross-tenant and stale-role tests; signed-callback/duplicate/out-of-order/idempotency failures; journal balance and concurrent spend; ledger-to-report and provider reconciliation; immutable evidence; OTP/key/provider unavailable fail-closed paths; private upload/SSRF security where enabled; source/method unknowns; server-side fulfillment; responsive dashboard charts and accessibility; authorized exports; backup/restore and incident exercises. All fixtures are synthetic and isolated; no real working records or live money are testing shortcuts.

**GO:** roadmap addendum is ready for owner review. **CONDITIONAL GO recommendation only** for a separately authorized, bounded B3.2-B schema-preparation stage after technical/privacy decisions are approved; no generation/execution authorized here. **NO-GO:** automatic payment/API/ledger/dashboard/Chief implementation, working migrations, live integration, collection or deployment.

Only the two requested documentation artifacts are added. Existing code/frontend/schema/data/configuration/historical plans and Git history are preserved; no tests, dependency installation, account creation, SMS/email/provider operations, commit, push or deployment performed. **STOP — await explicit approval for the next concrete stage.**
