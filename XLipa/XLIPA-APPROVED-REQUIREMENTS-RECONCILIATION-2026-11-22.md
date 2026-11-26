# XLIPA — APPROVED REQUIREMENTS RECONCILIATION
## P10 Scope Addendum and Core Payment Gateway Backlog Control
**Owner-approved direction supplied in ChatGPT: 22 November 2026**  
**Purpose:** Prevent forgotten approved requirements while completing P10 and planning subsequent R1–R7. This is a scope/control brief, **not** a claim that the current repository has implemented every item.

> **Pair with** `XLIPA-P10-OFFICIAL-SPECIFICATION-2026-11-22.md`. This addendum clarifies P10 requirements and records future core work. Neither file authorizes live financial or identity operations, working-DB activation, external messaging, commit/push or deployment.

## 1. Owner's overall goal

Finish the actual XLipa **Tanzania-focused, TZS-only payment gateway website**, reusing P0–P9 and approved Premium UI/UX, before official Selcom integration as far as provider-independent implementation reasonably allows. The ordinary authenticated Laravel app must work; isolated PASS and Safe Browser Demo screenshots do not alone prove real-app integration.

No restart, redundant rewrites, unapproved feature expansion or unauthorized operational activation. Preserve approved security, tenant/role, immutable ledger and working DB safeguards.

## 2. Source and evidence reconciliation

Review applicable `AGENTS.md`, the real current repository, prior master roadmaps and owner decisions, D13-1, P0–P9 and premium UI reports, and P10 reports. More recent Code/repo evidence takes precedence for **implementation status**, while later explicit owner policy takes precedence over contradictory earlier product assumptions.

For each capability give one of these statuses:
- Implemented and verified in ordinary authenticated Laravel application routes.
- Production-intended code implemented, isolated-tested only.
- Safe Browser Demo only.
- Partially implemented.
- Approved but not implemented.
- Approved concept only / post-launch.
- Requires an explicit owner or legal/provider/operations decision.
- Blocked by an external dependency.

Always cite exact code paths, routes, test evidence, dated report; do not promote a historical isolated PASS to a current real-app PASS.

## 3. Mandatory P10 scope clarifications

### A. Payment notifications

- **Email only** for ordinary merchant payment notifications; no per-payment SMS.
- Merchant preferences: **individual**, **daily**, **weekly**, **off** for optional payment email.
- Mandatory notices, where actually required, cannot be suppressed by optional preferences.
- Verified, eligible payment-confirmation evidence only; no attempted/pending payment success claims.
- Tenant-scoped, durable, idempotent, audited queue/delivery records; failed and retry statuses accurate.
- No live external messaging.

### B. Withdrawal notices

- **SMS + email**, subject to approved mandatory-notice policy.
- **No notification fees charged to merchants** and no balance deduction for delivery.
- Use isolated fake transports; keep delivery separate from financial confirmation.

### C. D13-1 document policy — precedence over older language

- **Withdrawal request/advice PDF: ON DEMAND**, not assumed automatically emailed on each request. A request is not a payout.
- **Confirmed payout receipt PDF: automatic only after authoritative payout proof AND approved reconciliation**; private, immutable, idempotent, authorized and auditable. Pending/failed withdrawal has no falsely paid receipt.
- Successful customer payment receipt where applicable: authorized on demand; no routine PDF for pending/failed payments.
- Settlement PDF and daily/weekly/monthly reports: on demand unless verified legal/contract rules override.
- Advice delivery vs retrieval, legal retention and other exceptions may require separate policy/legal clarification; do not silently fabricate an answer. Defer live delivery while implementing safe on-demand private access.

### D. Financial report formats

Approved exports: **CSV, PDF, XLSX**. Implement or verify all three with tenant-safe authorization, accurate TZS financial evidence and EAT date periods, clear financial categories, safe document/PDF output and spreadsheet formula-injection protection.

### E. Analytics

Reuse existing isolated-tested Merchant Analytics. Verify source × method breakdowns, confirmed volume, daily trends, percentages, Unknown classification and truthful attribution. No invented ad spend, ROAS, unique customers or phone-prefix network guessing.

## 4. Approved core product inventory — *outside P10 unless already reused*

### A. Merchant onboarding and identity

- Tanzania market; individuals (including unregistered businesses subject to eligibility) and companies represented by an authorized person.
- Minimal signup allows blank business name; verified email plus valid authorized membership for appropriate dashboard access.
- Business Name Completion must follow approved concurrency/one-time guards; ordinary HTTP activation is separately gated.
- Initial merchant application fields: business name, phone, email, owner's/authorized representative's NIDA/NIN.
- Phone proof for submission, **not** unconditional initial dashboard access.
- OTP policy: six digits, 5-minute expiry, 5 attempts per challenge, >=60 seconds between resend, 10 aggregate failures/30 minutes under approved actor+merchant+phone scope.
- NIN: approved authenticated encryption, masking, independently managed keys separate from `APP_KEY`; real collection blocked pending actual security/legal/operational authorization.
- Chief final merchant application decision is distinct from financial-service activation and provider/legal approval.
- Preserve existing version/proof/anti-replay and fail-closed restrictions.

### B. Chief Admin Pricing & Fees — future Real App stage

Core requirement: percent, fixed and hybrid transaction fees; withdrawal fees; min/max; Starter/Business/Enterprise tiers; merchant overrides; effective dates, versioning, audit; exact TZS calculations, immutable fee snapshots and separate merchant gross/provider fee/XLipa revenue. **Rates not approved; do not invent commercial defaults.** Do not silently add this to P10.

### C. Core financial operations and merchant products

Track real-app integration/readiness for existing payment mock engine, checkout, payment links, QR, pay buttons, merchant API/API credentials, signed webhooks, ledger, wallet, transactions, withdrawals, settlements, reconciliations and financial reporting. Also assess clearly specified refund/reversal/dispute controls, accurate fee accounting, error handling and immutable financial evidence. Do not misstate any mock or isolated implementation as live functionality.

### D. Universal Attribution Engine — later R4 stage

Owner-approved *merchant freedom first*: direct website links, landing pages, third-party shorteners, biolinks, custom domains and redirects; ideally **one link and one landing page** for several social networks, without requiring multiple pages or an XLipa shortener. Build from first-party UTM/referrer/campaign/click/checkout/payment evidence; optional providers such as 66biolinks only with documented authentic integration capabilities. Mark unavailable source **Unknown**. Do not imply platform-origin proof from tags or promise perfect cross-redirect attribution. Full third-party integrations are **not P10**.

### E. Merchant account settings, roles and security

Inventory business/profile, verified contact details, notification preferences, language/localization, teams and granular permissions, account security, sensitive-action step-up, merchant MFA and mandatory privileged/Chief MFA before live access. Distinguish requirements **approved** from unapproved future role additions. Do not silently replace existing owner/manager/staff permissions with Accountant/Developer/Support/Custom roles.

### F. Chief/Staff workspace functionality

Merchant applications/KYC, lawful decisions, merchants, payments, withdrawals/settlements, financial oversight, fees/revenue, providers, risk/fraud, disputes/refunds, API governance, notification/delivery records, support, staff permissions, settings, audit logs. Preserve separate Chief vs Staff authorization. Existing premium Chief UI visual refinements were reported completed/owner-approved; **no new redesign prerequisite to P10**.

### G. Domain and production preparation

Config-driven future move from local `xlipa.test` to eventual `xlipa.com.tz` (domain not yet necessarily purchased); avoid hard-coded host business logic; audit auth/session cookies, trusted hosts, callbacks and webhook URLs. Do not deploy or change live settings without authority.

### H. Security, recovery and provider abstraction

Tenant isolation, admin scopes, MFA, key custody/rotation/revocation, anti-replay, webhook signing, financial idempotency, NIN encryption, auditability, encrypted backup/restore rehearsal, incident/recovery, approved release gating and Selcom-ready **provider-agnostic interfaces**. No invented Selcom endpoints, supported rails, credentials or evidence of readiness.

## 5. Explicitly excluded: XLipa Store

**FINAL OWNER DECISION:** XLipa Store is **POST-LAUNCH PHASE 2 ONLY**, after the **entire core XLipa Payment Gateway is completed and officially launched**.

Do **not** implement, schedule as part of P10/R1–R7, count as a core pre-Selcom milestone, treat as a release blocker, or include it in the core completion percentage:
- Multi-product XLipa Store storefront.
- XLipa Store single-product landing page.
- Store-specific catalog/commerce management.

Keep it documented solely as a separately scoped future concept. Merchants may still use their **own** external landing pages and existing payment products. The previous list of 15 additional feature ideas was never blanket implementation authorization.

## 6. Implementation order and authorization

**NOW:**
1. Read current partial-P10 report/source and recover precise status.
2. Complete the authorized P10 requirements (including the mandatory clarifications in §3) safely, reusing already completed code.
3. Verify focus tests, bounded regressions, source protection and isolated browser output.
4. Produce a **requirements-to-code traceability matrix** and current P10 implementation report.
5. Stop for owner review before beginning the separate real-app completion program.

**AFTER P10 / WITH OWNER APPROVAL:**
- **R1:** Real Application integration audit with factual route/data gap matrix.
- **R2:** Core production-intended feature integration on ordinary authenticated Laravel routes in isolated/staging-safe conditions.
- **R3:** Remaining approved provider-independent core business functions, including Pricing & Fees and Chief operations.
- **R4:** Advanced provider-agnostic attribution.
- **R5:** Security, performance, backup/recovery and P11-aligned readiness.
- **R6:** Pre-Selcom end-to-end functional acceptance (P12-aligned).
- **R7:** Final pre-Selcom readiness review.
- **Then:** Real official Selcom integration only when documentation, keys and permission exist; separate legal/provider/operational go-live gates.
- **Finally, after core launch:** XLipa Store Phase 2, new scope/approval required.

## 7. Restrictions

Preserve all P0–P9, P10 code, uncommitted changes, approved UI, isolated-test infrastructure, protected manifests, immutable financial rules and role/tenant boundaries. No working SQLite/WAL/SHM access or migrations, secret reads, release binding overrides, real money, live NIN/OTP, external messaging, provider integrations, unauthorized Git actions or deployment. Use disposable isolated fixtures. Separate product approval from authorization to activate services.

## 8. Required reconciliation deliverable

Create or update **`XLIPA-APPROVED-REQUIREMENTS-RECONCILIATION-2026-11-22.md`** **in the project repository** (do not overwrite an existing user-created report without checking content) containing:

A. Inventory of approved core requirements, with approval-vs-concept distinction.  
B. Requirements completed by P0–P9, with evidence and ordinary-route integration status.  
C. Requirements completed by P10, with evidence and remaining issues.  
D. Approved core requirements not yet implemented.  
E. Real Laravel route/service/persistence integration gaps.  
F. Separate future features and explicit Store post-launch exclusion.  
G. Requirements awaiting owner/security/legal/provider decisions.  
H. External dependencies and NO-GO gates.  
I. Recommended safe next milestone and specific work.  
J. Overall project development estimate with prior provisional **~55% baseline**, new evidence-based estimate, and percentage-point delta. Separate real-app integration from isolated tests/demo and production go-live readiness. Exclude XLipa Store.

This current file is the **owner's input brief**; the Codex-generated reconciliation deliverable must add actual **current repository evidence** and must not simply copy this brief as if it were a verified audit.

## 9. Completion discipline

After P10: deliver an accurate PASS/PARTIAL/FAIL report and the evidence-backed traceability matrix; **STOP for owner review**. Do not begin R1–R7, use working operational data or claim P10 PASS on documentation alone.

---

## 10. Repository evidence reconciliation — 26 November 2026

Sections 1–9 above are preserved owner input. This section is the implementation evidence deliverable. The detailed stage/18-criterion matrix, exact test commands, files and remaining gates are in `XLIPA-P10-IMPLEMENTATION-2026-11-22.md`. It supersedes earlier recovery reports saying the official documents were missing or P10 had not started. Starting HEAD was `da7c94a`; existing uncommitted premium recovery report and P10 responsive-view changes were preserved. No new commit was made.

### A–C. Approved requirements and implemented evidence

Paths below are relative to `backend/`. “Ordinary route” means actual `routes/web.php` wiring; successful synthetic tests do not certify an operational environment. There is no demo-only business feature in the P10 implementation.

| Approved requirement | Current code and evidence | Classification and ordinary-route status |
| --- | --- | --- |
| P0 repository/security baseline | `scripts/verify-isolated.ps1`, `tests/bootstrap-isolated.php`, `tests/TestCase.php`, frozen activation manifests; `XLIPA-P0-REPOSITORY-CI-BASELINE-2026-11-14.md` | Implemented isolated verification infrastructure. Final current run PASS: 33 functional / 971 assertions, 546 bounded regression / 4,910 assertions, 40 pins and source preservation. This is not all-suite or operational certification. |
| P1 account/authentication and minimal merchant signup | `Actions/Auth/RegisterMerchantOwner.php`, auth controllers, `routes/web.php`, `AuthenticationTest` and `MerchantAccessTest` | Production-intended code and ordinary auth/dashboard routes; successful authentication subject to readiness/active-account gates. Existing tests run with synthetic configuration. |
| Business name completion and onboarding | `Actions/Merchants/` completion services, `MerchantOnboardingController`, `FunctionalMerchantOnboardingTest` | Ordinary onboarding GET exists. Successful completion is isolated-tested; `AppServiceProvider` retains `DeniedBusinessNameCompletionAdmission`. No bypass. |
| Phone/identity verification and P2 submission | `MerchantVerificationController`, verification cipher/store/readiness, submission services; `MerchantVerificationTest`, `MerchantApplicationSubmissionTest`, functional submission tests | Ordinary verification/application GET/POST routes exist. Real identity/OTP collection and successful ordinary submission remain gated; `DeniedSubmissionAdmission` retained. Existing isolated security foundations preserved. |
| P3 Chief/Staff workspaces and lawful decisions | `AdminWorkspaceController`, `AdminApplicationController`, `Actions/Merchants/ApplicationDecisionService.php`; AdminWorkspace/review/functional decision tests | Ordinary authenticated Chief/Staff/read-review routes exist. Chief decision mutation execution remains isolated, not an operational financial approval. Role/conflict controls preserved. |
| P4 payment engine | `Actions/Payments/PaymentEngine.php`, explicit `PaymentStore`, provider/admission interfaces, functional mock payment tests | Production-intended source, fictional isolated execution; no ordinary provider financial binding or live payments. |
| P5 immutable ledger | `Actions/Payments/MockPaymentLedger.php`, explicit admission; `FunctionalFinancialLedgerTest` | Production-intended source, isolated-tested. Authoritative fictional confirmations revalidated; no live custody or financial release. Current affected-regression pass includes this suite. |
| P6 Payment Links and checkout | `Actions/Payments/PaymentLinkService.php`, `FunctionalPaymentLinkCheckoutTest`, isolated HTTP harness | Isolated-tested functional implementation with static demo previews. Successful financial endpoints registered by explicit test harness, not ordinary operational routes. |
| P7 wallet/transactions | `Actions/Payments/MerchantWallet.php`, `FunctionalMerchantWalletTest` | Isolated-tested service/history/detail behavior, demo snapshots; no ordinary financial-store binding. |
| P8 settlement/withdrawal/reconciliation | `LedgerLifecycle.php`, `MerchantWithdrawals.php`, provider/admission interfaces; `FunctionalSettlementWithdrawalTest`; P8 report | Production-intended isolated-tested source; lifecycle requires CLI/testing/synthetic flag plus independent admission. Real payouts/custody disabled; operational withdrawable TZS 0. |
| Source/method/cross-analysis analytics | `MerchantAnalytics.php`, `AnalyticsEvidence.php`, `MerchantAnalyticsTest`; P8 report | Existing implementation reused, current affected regression and responsive analytics checks pass. Source tags/referrers are not platform proof; missing evidence Unknown; no payer/ROAS invention. Ordinary financial integration remains gated. |
| P9 API, credentials, QR, buttons, signed webhooks | `MerchantDeveloperTools.php`, `CheckoutQr.php`, `MerchantWebhooks.php`; `FunctionalMerchantDeveloperTest`; `XLIPA-P9-IMPLEMENTATION-2026-11-21.md` | Production-intended isolated-tested services; successful endpoints remain test-harness registrations. Static demo previews do not activate public API credentials, egress or webhooks. |
| P10 durable notifications and delivery audit | `MerchantNotificationService.php`, `NotificationSchema.php`, `NotificationTransport.php`; P10 payment/retry/digest tests | Production-intended code, isolated-tested. Pending/queued/provider-accepted/delivered are distinct; stable retry ID; immutable audit. Default denied transport. |
| Payment email individual/daily/weekly/off | `preferences`, `paymentPolicy`, `digests`; append-only preference/mode/batch records; P10 EAT cutoff/dedup tests | Implemented. Actual authenticated preference POST and notification GET exercised in isolated Laravel. Service binding and synthetic financial admission still block ordinary operational use. Email only; no payment SMS. |
| Free mandatory withdrawal email+SMS | `sweep`, `enqueue`, `deliver`; P10 withdrawal test compares position before/after and keeps both channels with payment Off | Implemented, isolated-tested; no wallet debit/fee path, TZS 0. Verified SMS contact resolver and external sending remain unprovisioned. |
| D13-1 request advice on demand | `MerchantNotificationService::document`, `p10_documents`, private normal document route; P10 withdrawal tests | Implemented and isolated-route tested. Advice is created only on demand, not automatically attached to request notifications, and is not payout proof. |
| Automatic reconciled payout receipt | Sweep stores immutable payout PDF only after lifecycle sealed reconciliation; document retrieval revalidates source/hash; forged-source/repeat tests | Implemented in the explicitly admitted sweep. Worker activation is blocked; a raw callback/request cannot issue a paid receipt. Synthetic proof is labelled fictional. |
| On-demand confirmed-payment/settlement PDFs | `MerchantFinancialReports::document`, ordinary `/merchants/{merchant}/documents/{kind}/{source}`, report links | Implemented, isolated-route tested, private and audited. Missing/unconfirmed source rejected. No new public token or customer-identity inference; merchant-authorized retrieval only. |
| Merchant report filters and financial categories | `MerchantFinancialReports::report`, `resources/views/merchants/p10.blade.php` | Implemented and isolated-tested on actual GET. EAT dates; source/method/status/payment filters; separate unfiltered position snapshot. Unsupported fee/refund/net evidence marked unavailable. |
| CSV/PDF/XLSX on demand | `FinancialReportExport.php`, `MerchantFinancialReports::export`, normal export route/controller | Implemented and isolated-route tested; tenant denial, private headers, CSV injection protection, literal XLSX cells. Independent parser validates full XLSX gross TZS 24,000, 34 rows, no formulas. PDF render inspected. |
| Chief reports and authorization | `P10ReportingController::chief`, `MerchantNotificationService::chiefHistory`, existing independent ledger/lifecycle Chief admission, `admin/p10.blade.php` | Ordinary Chief application counts/activity route; financial/delivery scope only with explicitly supplied `p10.chief-context` and separate admission. Staff 403 and tenant confidentiality tested. No invented platform revenue/health. |
| Premium UI/UX | Existing shared Blade/CSS and role navigation, owner-approved 22 November report | Preserved; no new dashboard redesign. Current seven-page browser pass includes merchant/Chief/Staff dashboard, analytics and P10 screens. New preference controls inherit the existing UI and demo-disabled behavior. |

**P10: PASS for authorized implementation and isolated verification; operational use remains NO-GO.** P10 test evidence: final focused **4 tests / 260 assertions**; affected P5–P10/admin/analytics suite **160 / 7,542**, with final acceptance-audit change separately covered by focused rerun; dashboard regression **31 / 404**. Final export **1 / 294**. Final browser **7 pages / 56 viewport checks**, 17 assets and 73 linked destinations; includes readable mobile table width/nowrap and contained scrolling. Final clean P0 run PASS as listed above; first pass correctly rejected the stale dashboard navigation test, which was updated to allow only the actual P10 routes. Full commands, logs, intermediate failures and coverage limits are in the P10 report. Final browser evidence: `.recovery/ui-ux-evidence-1795725207932/qa.json`; preservation log `.recovery/p10-completion-preservation.log`.

### D–E. Approved core backlog and real-integration gaps

These remain approved work to assess/implement in the later R1–R7 program, not additional P10 delivery or new work started here:

- Ordinary successful completion/submission/Chief decision and P4–P9 financial/developer service integration: existing source and isolated journeys, but test harnesses/admission gates are not operational route activation. `routes/web.php`, `AppServiceProvider`, explicit PaymentStore and admission interfaces are the present integration evidence.
- Pricing & Fees: approved core requirement; no complete percent/fixed/hybrid/versioned/merchant-override calculation and immutable commercial fee snapshot implementation established by this P10 pass. Rates remain unapproved. Reports must not invent revenue/net/fees. R3 owns this work after audit.
- Refund/reversal/dispute workflows, full Chief merchant/payment/risk/provider/support/staff/settings operations: approved backlog where specified; no complete ordinary implementation established here. Existing dashboard unavailable controls are not implementations.
- Account/profile, existing owner/member authorization and account security are retained. Expanded localization/team permissions, sensitive-action step-up and merchant/privileged MFA require later integration/security acceptance; no new Accountant/Developer/Support/Custom roles were added.
- Runtime worker scheduling, notification recipient/key custody, live delivery/receipt callbacks and provider adapters remain explicitly disabled. No operational email/SMS or provider contract is inferred from fake transports.
- Domain migration to a future purchased/approved `xlipa.com.tz`, auth/cookie/trusted-host/callback review and deployment are later work. Existing config-driven URL root remains; no live settings changed.

This is a bounded reconciliation of P10 dependencies and supplied approved inventory, not the separate R1 real-application audit. No claim that every later-stage requirement has been exhaustively audited or completed.

### F. Future features

Universal Attribution Engine/third-party shorteners/biolinks/custom-domain redirects, including any 66biolinks connection, remain later R4 work requiring authentic capabilities. Existing first-party capture and Unknown behavior remain the extension foundation. No proprietary API contract or guaranteed redirect attribution is invented.

**XLipa Store is post-launch Phase 2 only.** No catalog, multi-product storefront or single-product Store landing page was implemented, scheduled into R1–R7, made a core blocker or counted in core progress. The earlier collection of feature ideas remains concepts rather than blanket authorization.

### G–H. Decisions, dependencies and NO-GO gates

- Legal/contract-mandatory notices, special document delivery/retention exceptions, legal receipt wording and custody/retention policy require authoritative review; safe on-demand access was implemented without inventing those exceptions.
- Financial/provider binding, independent settlement/payout authority, release approvals, actual key custody/rotation and verified SMS contact resolver must be provisioned through separately approved gates. The additive P10 migration was tested on disposable SQLite only and not run operationally.
- Optional payment policy implementation defaults are explicit: first-observation EAT periods, Monday weeks, changes for new events, Off suppresses pending optional delivery. They are implementation choices, not claims of earlier owner policy. Withdrawal notices remain independent and free.
- Pricing rates/tier defaults, future privileged MFA/security enforcement, legal NIN collection and provider/legal eligibility decisions remain outside this milestone; no rates, NIN, OTP or live proofs were fabricated/activated.
- Official Selcom documentation/credentials and explicit provider/go-live authorization remain external dependencies. Test interfaces do not assert provider approval.
- Working SQLite/WAL/SHM, environment files, keys, protected manifests and operational settings were not accessed/copied/hashed/changed. No real messaging, payments, payouts, external webhooks, commit/push or deployment.

### I–J. Next milestone and progress

No remaining authorized P10 implementation gap was identified; the ordinary-app/operational gates in D–H remain. Current core development estimate: **approximately 60%, +5 percentage points from 55%**. Verified completion of the remaining P10 preferences/digests, XLSX, document issuance and scoped-reporting requirements supports this provisional planning increase. P0–P9/UI are not recounted, and Store is excluded. No measured/weighted denominator has been agreed: 60% is engineering judgment, not the proportion of ordinary operational flows activated. Ordinary application integration and production/go-live readiness remain separately incomplete and are not assigned unsupported percentages.

Recommended next milestone after owner review: **R1 — Real Application Integration Audit**, producing an actual ordinary-route/service/persistence gap matrix and ordered R2 work. R1 has not started; R2–R7 remain unchanged. Stop for owner review after the final P10 report.
