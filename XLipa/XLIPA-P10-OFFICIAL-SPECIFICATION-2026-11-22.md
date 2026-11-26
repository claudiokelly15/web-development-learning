# XLIPA — OFFICIAL P10 IMPLEMENTATION SPECIFICATION
## Notifications, Reports & Analytics
**Owner-approved scope originally issued in ChatGPT: 22 November 2026**  
**Status:** Implementation authorization for P10 only; NOT live-service or deployment authorization.  
**Source:** ChatGPT XLipa planning conversation. This specification was supplied in chat; a repository file path was **not** previously confirmed.

> **Read together with** `XLIPA-APPROVED-REQUIREMENTS-RECONCILIATION-2026-11-22.md`. That subsequent owner-approved addendum explicitly preserves D13-1 (on-demand withdrawal request advice), payment email preferences, free notifications, and XLSX exports. Where older text is ambiguous, apply the subsequent clarification. Do not interpret either file as authorization for real money, live messaging, NIN capture, production migration, or deployment.

## 1. Purpose, starting conditions, and preservation

Implement **P10 — Notifications, Reports & Analytics** as actual, production-intended Laravel code, verified through isolated tests and the established Safe Browser Demo. Do **not** create demo-only functionality. The long-term aim is a fully integrated real XLipa web application; the separate R1–R7 Real Application Completion program must **not** be started during P10.

Before editing:
1. Read applicable `AGENTS.md`, Git status, relevant P0–P9 reports, and existing Laravel code/tests.
2. Determine what notification, report, document, and analytics functionality already exists. Reuse it; do not repeat completed work.
3. Preserve uncommitted changes, all approved premium UI, tenant/role boundaries, immutable ledger rules, security controls, frozen source manifests, and working SQLite database and sidecars.
4. Chief Admin visual refinements have been reported completed and owner-approved; do not restart their redesign. Cosmetic grouping of unimplemented navigation features is **non-blocking**.
5. A more recent repository report showing **partial P10** supersedes this original document's historical statement that P10 had not started. Verify actual progress and continue without repeating work.

## 2. P10-A — Secure notification foundation

Build a durable notification architecture suited to Laravel and the existing system:
- Event identifiers and tenant/merchant-scoped notification records.
- Channel selection (email/SMS as permitted by event type), secure templates, correct recipient resolution.
- Delivery intents and audited state transitions, such as pending, queued, delivered, failed, retry-scheduled, permanently-failed.
- Unique idempotency controls to prevent duplicate sends from repeated financial callbacks, retries, and workers.
- Retry policy, failure evidence, and tenant-safe delivery history.
- Separation of **financial event occurred**, **message queued**, **provider accepted**, and **message delivered**. Never equate queued with delivered.
- No real external email/SMS transmission; use isolated fake transports and fictional fixtures.

## 3. P10-B — Payment notifications (merchant)

**Approved policy: EMAIL ONLY for normal payment notifications. Never per-payment SMS.**

Support merchant preferences:
- Individual confirmed-payment email.
- Daily digest.
- Weekly digest.
- Off for optional payment emails.

These optional preferences must not suppress legally or operationally mandatory notices. Notification records must use an eligible, authorized payment-confirmation event; attempts and pending payments are not confirmed payments. Confirmed payment value must not be confused with settled funds.

Include accurate merchant recipient, payment reference, TZS amount, payment status, EAT timestamp, safe template, tenant isolation, audit, delivery/retry status, and idempotency. No live sending.

## 4. P10-C — Withdrawal notifications and documents

**Approved notification channels:** Email **and** SMS, respecting mandatory-financial-notice policy. Notifications are **free to merchants**: no SMS/email charge or hidden wallet deduction.

### 4.1 Withdrawal request/advice PDF — **ON DEMAND** (D13-1)

The current approved D13-1 policy supersedes any older assumption of automatic advice creation/delivery. Provide appropriately authorized, private **on-demand** generation or retrieval for a recorded request. A request-advice is **not proof of payout**.

Show supported merchant identity, unique request reference, requested amount/date/status and masked destination. Access must be tenant-scoped and auditable. Specific secure delivery of advice beyond on-demand retrieval, and legal/contract exceptions, remain subject to separate review.

### 4.2 Confirmed payout receipt PDF — after authoritative proof

Generate a **distinct, immutable, idempotent** confirmed-payout receipt **only** following independently authoritative payout confirmation **and approved reconciliation**. Include verified payout reference, amount, confirmation timestamp, merchant identity and masked destination.

Do **not** generate a paid receipt from a withdrawal request, approval, pending/failed payment, assumed provider response, or mere message delivery. In isolated tests, clearly label synthetic confirmation and reconciliation as fictional; never imply real money movement. Receipt is private, downloadable under appropriate authorization, and prepared for the approved secure email/delivery and retention policy when live systems are authorized.

### 4.3 Other document defaults

- Successful customer payment receipt/PDF: on authorized demand, where applicable; no routine receipt for pending/failed payments.
- Settlement PDF: on demand unless verified legal/contract requirements establish a different policy.
- Protect private data and prevent duplicate issuance or financial misrepresentation.

## 5. P10-D — Merchant financial reporting and exports

Use authoritative existing ledger/payment/settlement/withdrawal services, not fictional totals embedded in a live dashboard. Support, as supported by actual data:
1. Confirmed, pending and failed payments and transaction history/status.
2. Payment methods and recorded traffic sources.
3. Payment Links and relevant checkout references.
4. Withdrawals, settlement evidence and financial summaries.
5. Date range, status, payment method, traffic source and reporting period filters.
6. Precise TZS values and consistent Tanzania/EAT date boundaries.

**Approved export formats: CSV, PDF and XLSX.**
- Protect against CSV/XLSX spreadsheet formula injection; escape fields and safeguard downloads.
- Enforce merchant-scoped access, no cross-tenant data leakage, secure PDF generation and suitable XLipa branding.
- Separate confirmed gross, refunds/reversals, fees, net, held, available, withdrawable, settled and paid-out figures correctly.
- Never invent fee rates, platform revenue, provider settlements, or unsupported historical data.
- Daily/weekly/monthly report exports are **on demand** by default, subject only to independently verified legal/contract exceptions.

## 6. P10-E — Merchant Analytics refinement (reuse existing code)

Existing Merchant Analytics had already been reported as isolated-tested; **do not rebuild** it. Preserve and verify:
- Traffic-source attribution and payment-method analytics.
- Source × Payment Method cross-analysis and reconciling totals.
- Confirmed payment counts, value, percentages and daily trends.
- Date filters, responsive charts, premium compact cards and licensed/appropriate icons.
- **Unknown** source/method where evidence is insufficient.

Recognizable source categories may include Facebook, Instagram, WhatsApp, TikTok, Google, YouTube, Direct, Referral, Other and Unknown. Payment methods/networks (M-Pesa, Mixx by Yas, Airtel Money, HaloPesa, banks, Unknown) may appear only when supported by actual authoritative provider evidence or clearly fictional test data. Do **not** infer network from phone prefix or invent unique customer counts, advertising spend or ROAS.

UTMs/referrers are attribution signals rather than independently verified social-network proof. Preserve captured evidence and uncertainty. No double-counting.

### Advanced attribution boundary

The owner's future Universal Attribution Engine must support direct links, third-party shorteners, Biolinks (including optional 66biolinks integration), custom domains and redirects, preferably with one landing page and one link; merchants must not be forced into an XLipa shortener. Full external shortener APIs and cross-domain attribution integrations **are not automatically in P10**. Maintain extension points, but do not invent external APIs or guaranteed source identification. That work belongs in separately approved R4.

## 7. Chief Admin reporting

Add or complete reporting strictly supported by authorized existing data: merchant application counts/reviews/decisions, audited activity, operational notification delivery status and appropriate platform financial oversight where underlying evidence exists. Enforce separate Chief vs Staff permissions. Do not display invented platform revenue, merchant volumes, provider health or regulatory status.

Do **not** start a new Chief Admin visual redesign. Preserve the owner-approved Premium UI/UX.

## 8. Real Laravel implementation and integration

Use existing Laravel services, controllers, models, policies, jobs/events/queues/adapters, migrations, Blade templates and tests as appropriate. **No demo-only business logic.**

Where safe within current authorized constraints, expose protected read-only reporting and notification history through ordinary authenticated application routes. For operational writes blocked by established release gates, implement production-intended code and verify on disposable isolated fixtures; document the exact gate rather than overriding it. The R1–R7 program later handles the broader real-application integration audit and completion.

## 9. Data, security and activation restrictions

- Do not access, copy, migrate, hash or modify working operational SQLite, WAL/SHM sidecars; use disposable databases for tests and schema validation.
- Do not modify `.env`, `APP_KEY`, operational secrets, or protected frozen source manifests without separately approved controlled procedures.
- Preserve authentication, role/tenant authorization, immutable ledger, idempotent financial transitions, signing/webhook/anti-replay protections, audit logs, secure PDF access, input validation and sensitive-data masking.
- No live NIN/identity collection, OTP provider, real email/SMS, external webhook delivery, provider payments, payout activation or production deployment.
- No invented Selcom APIs/credentials or claims of provider approval.
- Do not commit, push, deploy or run destructive operations without separate permission.

## 10. Safe Browser Demo checks

The previously established loopback-only fictional demo was `http://127.0.0.1:18761/`. Reuse the existing exporter and isolated headless Chrome automation; do not build a new demo platform. Verify affected merchant notification/report/document/analytics pages and authorized Chief reports on desktop, tablet and mobile. The demo must not represent fictional figures as real money or prove operational integration by itself.

If the demo is unavailable, diagnose safely and report separately; avoid scope drift.

## 11. Implementation order

- **P10-A** — Notification foundation and delivery states/idempotency.
- **P10-B** — Payment merchant email notifications and individual/daily/weekly/off preferences.
- **P10-C** — Withdrawal email+SMS; on-demand request advice vs reconciled confirmed payout receipt.
- **P10-D** — Merchant financial reports with CSV/PDF/XLSX exports.
- **P10-E** — Existing Analytics refinements; authorized Chief Admin reporting.
- **P10-F** — Laravel integration verification, isolated end-to-end tests, responsive browser QA and preservation verification.

Resume from actual partial P10 implementation. Do not duplicate stages already proven complete. If a stage is blocked, document the gate and proceed with independent authorized work where safe.

## 12. P10 acceptance criteria

Classify P10 **PASS** only when evidence confirms all applicable authorized criteria:
1. Notification event/state/idempotency architecture implemented and tested.
2. Payment merchant notifications are email-only, correctly triggered, with individual/daily/weekly/off preference handling.
3. Duplicate events do not create unintended duplicate delivery.
4. Withdrawal notices support email+SMS under mandatory policy at no merchant charge.
5. Request advice is private, authorized and **on demand**, distinct from paid receipt.
6. Confirmed payout receipt requires independently authoritative confirmation **plus reconciliation**, and is idempotent.
7. PDF generation/download is securely authorized and auditable.
8. Merchant reports derive from authoritative financial records.
9. CSV, PDF **and XLSX** exports are tenant-safe, correct and tested.
10. Source/method analytics reconcile, respect Unknown, and avoid unsupported claims.
11. Chief vs Staff authorization remains enforced.
12. Implementation is production-intended Laravel code, not just HTML fixtures.
13. Focused isolated DB/functional tests pass.
14. Relevant browser/responsive QA passes.
15. P0–P9 and premium UI remain preserved.
16. Real services and financial operations remain disabled.
17. Demo displays fictional P10 UI only where safely supported.
18. Remaining normal-route/activation blockers are documented.

Report **PARTIAL** when criteria are not satisfied; no architecture-only or demo-only PASS.

## 13. Required milestone report

Create or update **`XLIPA-P10-IMPLEMENTATION-2026-11-22.md`** (avoid overwriting any existing report; preserve its history) with:
- Starting Git/source state and existing P10 functionality reused.
- New production-intended code, exact changed files and migrations.
- P10-A through P10-F implementation evidence.
- Notifications/delivery states and policy adherence.
- Request advice, payout receipt and secure document tests.
- CSV/PDF/XLSX reporting and Analytics results.
- Chief reporting authorization and UI verification.
- Ordinary authenticated Laravel integration vs isolated-only vs demo-only status.
- Exact test commands, results, browser QA and source-preservation findings.
- Unresolved P10, operational and external dependencies.
- PASS/PARTIAL/FAIL with justification.
- **Overall XLipa progress:** previous provisional planning estimate **~55%**; new evidence-based estimate, percentage-point delta, work left, next milestone. Distinguish development progress, ordinary-app integration and production/go-live readiness. XLipa Store is excluded from this percentage.

**Stop for owner review after this milestone. Do not start R1–R7 automatically.**
