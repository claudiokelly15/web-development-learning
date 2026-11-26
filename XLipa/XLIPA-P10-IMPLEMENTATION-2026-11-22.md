# XLipa P10 — Notifications, Reports & Analytics

Implementation continuation: 26 November 2026 (Africa/Nairobi). Official scope date: 22 November 2026.

**P10: PASS for the approved production-intended implementation and isolated verification scope. Operational use remains NO-GO.** All applicable acceptance criteria below have implementation and verification evidence, including the final preservation and responsive checks. This report does not authorize live operations, R1, commit, push or deployment. Stop for owner review.

## Recovery and preserved work

Starting branch `main`, HEAD `da7c94aeeb51cd6e42eea8932d7718c3ddcbe081`. The existing commit implements a partial P10 foundation; it was reused, not recreated. Pre-existing uncommitted changes in `XLIPA-FINAL-PREMIUM-UI-UX-REFINEMENT-2026-11-22.md` and `backend/resources/views/merchants/p10.blade.php` were retained. The historical report remains historical.

The two official documents were absent from the root. Read both directly from the owner-supplied `XLIPA-P10-AND-REQUIREMENTS-2026-11-22.zip`, then extracted only those exact Markdown entries into the project root. The official specification is preserved unchanged; the reconciliation retains the entire owner's input brief and appends a repository-evidence section. Their requirements were applied within the user's request, not as independent authority for operational activation.

Read backend AGENTS.md and the existing P10, payment/ledger, lifecycle, analytics, reporting, authorization, test and demo implementations. No additional applicable AGENTS.md or `.ai/rules` was found; the named testing-best-practices skill is absent from the supplied catalog. Installed versions confirmed: PHP 8.5.13, Laravel 13.35.0, PHPUnit 12.5.38, Pint 1.32.1. No dependencies changed. PDF skill used for generated-document visual verification.

## P10-A through P10-F

### A. Durable notification foundation

Reused `MerchantNotificationService`, `NotificationSchema`, `NotificationTransport` and `DeniedNotificationTransport` under `backend/app/Actions/Payments/`. Existing immutable events, encrypted recipient snapshots, tenant-scoped delivery records, unique event/channel identity, durable claims, bounded retry and append-only audit remain.

Financial confirmation, local queueing, provider acceptance and recipient delivery are separate. Transport `accepted` leaves delivery `queued` and adds `provider_accepted` audit evidence. Only `delivered` with a validated receipt records confirmed delivery. Stable delivery IDs are reused for retries; adapters must honor that idempotency contract. This is not a claim of unconditional exactly-once delivery across an unprovisioned real provider.

The existing retry maximum is three attempts. Queue/accepted claims wait 60 seconds; failed attempts have exponential retry timing. Missing/changed recipient terminates the intent. Default transport throws and cannot transmit externally. No scheduler, provider adapter or operational worker has been activated.

### B. Payment email preferences and digests

Added append-only preference history with actor and timestamp, and immutable per-event mode snapshots. Modes: `individual`, `daily`, `weekly`, `off`; default individual preserves the pre-existing behavior. Existing events with delivery intents retain their original individual assignment, preventing a migration-time duplicate send.

Only authorized confirmed-payment evidence enters the sweep. Individual mode creates email only; Off records the event without optional delivery. Daily/weekly events are assigned to the EAT day/Monday-start week in which they are first observed; a delayed discovery enters the current observation period. Digests become due at the next EAT midnight or Monday midnight and contain original payment references, confirmation times, count and exact confirmed gross TZS. These are observation-period digests, not invented historical financial periods. Financial reporting independently uses source-event dates.

Period assignment survives worker retries and later preference changes. A non-Off change affects newly observed events; Off additionally cancels pending optional payment delivery at claim time. Already transmitted email cannot be recalled. These deterministic implementation defaults are explained in the UI and are not presented as earlier owner decisions. No legal/mandatory notice type is invented. Withdrawal notices are mandatory within this implemented policy and do not consult payment-email preference.

Ordinary preference POST is owner-authorized, CSRF-protected, input-validated and throttled, behind the same verified/active account and merchant-context middleware as the protected GET routes. The service remains subject to existing financial-store/synthetic admission.

### C. Free withdrawal notices and distinct documents

Withdrawal request and reconciled payout notices support email plus verified-contact SMS. Optional payment Off does not suppress either. Notifications cost TZS 0; the service has no financial charge/posting path. Tests compare the full financial position before and after delivery.

Request advice is issued and stored only on authorized document demand. It includes merchant ID, request reference, TZS amount, EAT request timestamp, request status and masked destination, and explicitly says payout is not confirmed. Notifications do not automatically create or attach the advice PDF.

The sweep automatically stores a separate immutable payout receipt when the lifecycle exposes an independently authenticated, sealed, reconciled payout journal. Receiving a fictional provider callback alone does not issue a receipt. Repeated sweeps/retrieval reuse the stored bytes. Document retrieval revalidates authoritative source fields, verifies the stored content hash and appends download audit. Forged payout event sources are rejected. These tests use clearly fictional provider proof and reconciliation, not real payout certification.

Added authorized, on-demand confirmed-payment receipts and settlement-evidence PDFs in `MerchantFinancialReports::document`. Pending/failed payments have no matching confirmed-payment source, and settlement evidence cannot be substituted for a payout receipt. PDFs are private, auditable, stored idempotently and linked from the report. Supported merchant identity is the stable merchant ID; no unsupported legal identity is asserted.

### D. Financial reports and three export formats

Reused `MerchantFinancialReports` and `FinancialReportExport`. Reports derive from admitted P5/P8 records, with status, source, method and EAT period filters, transaction/Payment Link references, settlement evidence and withdrawal outcomes. Payment filters apply to payment rows; financial position is explicitly an unfiltered lifetime snapshot.

Added XLSX alongside CSV/PDF, using dependency-free OOXML/ZIP output. TZS amounts are numeric; user-controlled strings are explicit inline strings, never formulas or external relationships. CSV formula-leading content is neutralized. Formats share the same authorized report data. Downloads use attachment filenames, private no-store responses, nosniff and sandbox CSP.

Reports distinguish confirmed payment gross, outstanding obligation, settled simulated funds, reserved funds, simulated available funds, completed fictional payouts and operationally available TZS 0. Fees/refunds/reversals/net without approved underlying evidence are unavailable, not fabricated zero values. Daily/weekly/monthly exports remain on demand. Limits remain bounded: 10,000 records per section, maximum export/PDF rows and XLSX cell sizes; callers must narrow oversized periods.

PDF output uses fixed-width wrapping to avoid clipping, XLipa branding, A4 pages and page numbers. No HTML renderer, JavaScript, external URLs/resources or subprocess is invoked by application PDF generation. ASCII transliteration is a documented limitation; no legal/archival typography certification is claimed.

### E. Existing Analytics and Chief reporting

Reused MerchantAnalytics unchanged in this continuation: source/method breakdowns, cross-analysis, percentages, confirmation-based daily trends, EAT periods, Unknown and honest UTM/referrer provenance. No phone-prefix guessing, unique-customer invention, ad-spend/ROAS claims or third-party attribution API was added.

Chief reporting retains application review authorization and historical conflict exclusions. Added explicit optional `p10.chief-context` integration for one independently admitted merchant scope: notification delivery counts and financial position are read through `MockPaymentLedger::administrativeRead` and `LedgerLifecycle::chiefOverview`. No unscoped platform scan, recipient disclosure or fabricated revenue/health figure. Staff is denied before scoped data access. Normal Chief application reporting remains usable without the optional financial context; financial/delivery data is clearly unavailable when not admitted. Approved Chief dashboards were not redesigned.

### F. Integration and verification

Production-intended source and normal authenticated P10 routes exist. Isolated tests execute those actual routes with explicitly supplied services; the demo exporter consumes the same views/controllers/services. No business logic exists only in static HTML.

| Surface | Ordinary route | Verification / gate |
| --- | --- | --- |
| Merchant financial report | `GET /merchants/{merchant}/reports` | Actual route exercised under isolated service injection; 503 when service unbound |
| CSV/PDF/XLSX | `GET /merchants/{merchant}/reports/export/{format}` | Actual protected route; audited private attachment |
| Notification history | `GET /merchants/{merchant}/notifications` | Tenant-scoped history and preference display; service provisioning gated |
| Preference change | `POST /merchants/{merchant}/notifications/preferences` | Verified owner/merchant scope, CSRF, validation, throttle; no activation bypass |
| Advice / payout receipt | `GET /merchants/{merchant}/notifications/{event}/document` | Source-validated, authorized private download |
| Payment / settlement PDF | `GET /merchants/{merchant}/documents/{kind}/{source}` | Confirmed/sealed source required; on demand |
| Chief reporting | `GET /admin/reports`, optional CSV/PDF | Existing Chief application scope; optional separately admitted financial/delivery scope |

`AppServiceProvider` has no ordinary binding for the financial/report/notification stores or the Chief context. Financial services retain CLI + testing + synthetic-only checks and independent authority admission. `DeniedNotificationTransport` remains default. Therefore protected route wiring is complete and fixture-tested, while successful ordinary operational financial use is intentionally blocked. No production integration/readiness is inferred from the demo.

## Acceptance traceability

All references are relative to `backend/` unless noted. Gated operational execution is classified separately from completed implementation, as required by specification sections 8–9.

| Official criterion | Implementation / evidence |
| --- | --- |
| 1. Events, states, idempotency | `MerchantNotificationService`, `NotificationSchema`; P10 payment/retry and digest tests |
| 2. Email-only and all four modes | `preferences`, `paymentPolicy`, `digests`; preference/digest EAT boundary test; SMS schema rejection |
| 3. No unintended duplicate delivery | Immutable mode/membership snapshots, unique event/channel and period keys; repeated sweep/retry tests, stable delivery ID |
| 4. Withdrawal email+SMS free | Withdrawal test verifies two SMS, both channels with payment Off, unchanged financial position |
| 5. On-demand advice | No document before demand; `document` source checks; distinct request advice wording |
| 6. Confirmed, reconciled, idempotent payout receipt | Withdrawal test asserts no receipt before reconciliation, automatic stored receipt after sweep, repeat bytes and forged-source denial |
| 7. Secure/audited PDFs | `p10_documents`, `p10_downloads`, private routes, source/hash validation, immutable-history tests and Poppler visual QA |
| 8. Authoritative reporting | `MerchantFinancialReports::report` uses financialRead/lifecycle projection; financial regression suites |
| 9. CSV/PDF/XLSX | P10 actual route tests, cross-tenant denial, injection tests, independent workbook parsing and browser download test |
| 10. Analytics truth/reconciliation | `MerchantAnalyticsTest`; unchanged source, cross-dimension totals, Unknown and attribution labels; browser analytics page |
| 11. Chief vs Staff | Existing scope + independent financial admission; P10 and AdminWorkspace tests; Staff 403 |
| 12. Laravel source | Existing actions, additive migration, controller, Blade and ordinary `routes/web.php`; isolated `route:list` |
| 13. Focused tests | Final P10 4 tests / 260 assertions; affected suite 160 / 7,542; dashboard 31 / 404 |
| 14. Browser QA | 7 pages / 56 viewport checks, 17 assets, 73 destinations; Chrome screenshots inspected |
| 15. P0–P9/UI preservation | Affected financial/developer tests pass; final P0 33 functional and 546 bounded regression tests pass; 40 pins preserved; no redesign |
| 16. Real operations disabled | No operational bindings, no external adapters/worker, synthetic guards and default denied transport retained |
| 17. Safe demo | 56 fictional snapshots; disabled forms, loopback only, approved fixed downloads |
| 18. Exact blockers | Financial-store/custody provisioning, approved worker/transport/recipient policy and ordinary upstream financial integration remain gated |

## Fresh test commands and evidence

Run from `backend/` unless otherwise stated. PHPUnit commands use PHP 8.5 with `auto_prepend_file`/`auto_append_file` cleared, URL file access disabled and network functions (`curl_exec`, `curl_multi_exec`, `fsockopen`, `pfsockopen`, `stream_socket_client`, `socket_create`, `socket_connect`, `socket_sendto`, `socket_sendmsg`) disabled. Base runner:

```text
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --bootstrap tests/bootstrap-isolated.php --no-progress --do-not-cache-result
```

- Final focused: runner + `tests/Feature/P10NotificationsReportsTest.php`: **PASS 4 tests / 260 assertions**. `.recovery/p10-completion-final-p10.log`.
- Affected regression: runner + `P10NotificationsReportsTest`, `MerchantAnalyticsTest`, `FunctionalSettlementWithdrawalTest`, `FunctionalFinancialLedgerTest`, `FunctionalMerchantWalletTest`, `FunctionalMerchantDeveloperTest`, `FunctionalPaymentLinkCheckoutTest`, `AdminWorkspaceTest` (each `tests/Feature/{name}.php`): **PASS 160 tests / 7,542 assertions**. `.recovery/p10-completion-focused.log`. This predates the final provider-acceptance audit addition, which is covered by the final focused run.
- `MerchantDashboardTest.php`: **PASS 31 tests / 404 assertions**, `.recovery/p10-completion-dashboard.log`. Updated its stale navigation allowlist to include the already-existing notification/report routes; retained disabled-control checks and added exact link assertions.
- Required `php vendor/bin/pint --dirty --format agent`: **PASS** after formatting changed PHP. No protected manifest/guard was reformatted.
- From root: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-isolated.ps1`: **PASS, exit 0**, `.recovery/p10-completion-preservation.log`: 29 frozen pins, 3 manifest digests, 4 INT-05 pins, 4 isolation guards; 250 PHP syntax checks; 15 non-mutating format checks; **33 functional tests / 971 assertions**; **546 bounded regression tests / 4,910 assertions**; source preservation PASS. First run passed pins/syntax/33 functional tests, then correctly caught the stale dashboard navigation expectation and stopped (413/414 passed); not counted as a final pass. Earlier log preserved as `.recovery/p10-completion-preservation-before-navigation-fix.log`.
- `scripts/start-demo.ps1`: final **PASS 1 export test / 294 assertions**, `.recovery/p10-completion-export-final.log`. Refreshed after the final view correction; all older exports retained.
- `node scripts/Test-Safe-Browser-Demo.cjs`, `XLIPA_QA_PATHS=/merchant-reports,/notification-history,/chief-reports,/merchant-analytics,/merchant-dashboard,/chief-dashboard,/staff-dashboard`: final **PASS 56 checks** across 360, 390, 430, 768, 1024, 1366, 1440, 1920px. `.recovery/p10-completion-browser-final.log`; final screenshot/QA evidence `.recovery/ui-ux-evidence-1795725207932/`.
- Browser verified 17 assets, 73 linked destinations, explicit preference choices, XLSX download MIME/signature, keyboard preview menu and wallet navigation; POST 405 and secret/database probes 404. Screenshot inspection covered merchant report, notification history, Chief report and analytics mobile. Visual review caught squeezed mobile table amounts despite no page overflow; final view keeps unbroken values in a contained horizontal scroll area, and the harness now asserts table width/nowrap/scroll behavior. Final mobile report screenshot inspected. Fixed dock location in full-page captures is Chromium capture behavior, not an in-document dock.
- Independent `openpyxl`/ZIP reader validated actual exported XLSX, 34 rows, confirmed gross TZS 24,000, numeric amount cells, no formulas. Injection fixture strings `=SUM(1,1)`, `@cmd`, tab-prefixed formula text and XML remained text. No workbook-authoring dependency was installed.
- Poppler rendered advice, payout receipt, payment receipt, settlement evidence, financial report and multipage safety PDF. First pages and second multipage page visually checked for readable margins/header/footer and no clipping. Poppler emitted an environment missing-Symbol-font warning; the actual Courier/ASCII output rendered legibly.
- Ordinary `route:list --path=merchants --except-vendor --no-interaction` ran under the synthetic environment loader; 15 merchant routes include all P10 routes. `.recovery/p10-completion-routes.log`.
- `git diff --check`: **PASS**; existing LF/CRLF conversion notices only. The protected original P10 migration, AppServiceProvider, isolated bootstrap, TestCase and PHPUnit configuration have no diff.

All databases were in-memory/disposable. No working SQLite, WAL/SHM, `.env`, key or operational credential was opened, copied, hashed or changed. Export state archives preserve earlier demo snapshots; only the process matching PID, start time, executable and token/server command line was replaced. No unrelated process was stopped. An automatic permission-review timeout on independent workbook verification was retried once and succeeded; no unresolved approval rejection remains.

## Changed files

- `backend/app/Actions/Payments/{FinancialReportExport,MerchantFinancialReports,MerchantNotificationService,NotificationSchema,NotificationTransport}.php`.
- `backend/app/Http/Controllers/P10ReportingController.php`.
- `backend/database/migrations/2026_11_26_200746_create_p10_policy_documents.php` — additive migration, tested on disposable SQLite only; original migration retained unchanged; destructive rollback refused.
- `backend/resources/views/merchants/p10.blade.php` and `backend/resources/views/admin/p10.blade.php` — retained prior responsive fix and approved shared UI.
- `backend/routes/web.php`.
- `backend/tests/Feature/{P10NotificationsReportsTest,SafeBrowserDemoExportTest,MerchantDashboardTest}.php`.
- `scripts/{demo-server.cjs,stop-demo.ps1,Test-Safe-Browser-Demo.cjs}` — fixed allowlisted XLSX downloads and QA; no new demo platform.
- New exact official specification copy, appended evidence reconciliation and this requested implementation report. Pre-existing premium recovery report modifications preserved without further editing.
- Ignored `.recovery` logs/PDFs/screenshots and owned demo exports/state generated, not staged.

## Limits, progress and handoff

No real messaging, provider payouts, identity collection, external webhook delivery, working-DB migration, commit/push or deployment. Operational workers, external delivery receipts, verified SMS custody, encryption-key lifecycle, retention/legal exceptions and service provisioning need independently approved operations. Bounded sweeps deliberately refuse more than 1,000 source events; large-scale batching/performance is not certified. Reports expose missing fee/refund/net evidence as unavailable. Full legal receipt compliance, real-device/assistive-technology acceptance and real-provider delivery semantics remain outside this isolated milestone.

**No remaining implementation gap was identified against this authorized P10 scope.** The operational constraints above remain deliberate gates, not permission to bypass them. P10 PASS does not certify the ordinary operational financial application or go-live readiness. The Safe Browser Demo is left healthy at **http://127.0.0.1:18761/**, 56 fictional snapshots, forms disabled.

Updated overall core XLipa development estimate: **approximately 60%, up 5 percentage points from the previous 55%**. This is a provisional engineering/planning judgment, not a measured acceptance score or weighted arithmetic: the increase reflects completed P10 preferences/digests, XLSX, immutable/on-demand documents and scoped reporting, backed by current tests and browser/source-preservation evidence. Existing P0–P9/premium UI are not counted twice. Ordinary upstream financial/developer integration, remaining core business functions, security/recovery acceptance, provider/operations and production readiness still require substantial work. No percentage is claimed for live readiness. There is no agreed weighted denominator; the estimate is not a claim that 60% of operational flows are activated.

XLipa Store remains post-launch Phase 2 and is excluded. R1–R7 have not started. Recommended successor is **R1 — Real Application Integration Audit**, only after the owner reviews this milestone; do not begin it automatically.
