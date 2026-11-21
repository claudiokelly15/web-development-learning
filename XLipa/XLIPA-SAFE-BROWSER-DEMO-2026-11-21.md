# XLipa safe browser demo — 2026-11-21

Verified running URL: **http://127.0.0.1:18761/**

This is a **read-only visual demo**, with real XLipa Blade responses rendered from disposable fictional fixtures. It is not an interactive authenticated application. Navigation works; forms and financial writes are disabled.

## Start, stop and access

From `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File XLipa/scripts/start-demo.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File XLipa/scripts/stop-demo.ps1
```

The demo is already running at handoff. Do not start another copy. Startup refuses an existing state file or occupied port. Optional `-PhpPath`, `-NodePath`, and `-Port` parameters support other installed runtime locations/ports.

No password is required or accepted. Use the demo navigation to select the fictional Merchant, Chief Admin or Staff snapshots:

| Role | Entry |
|---|---|
| Merchant | http://127.0.0.1:18761/merchant-dashboard |
| Chief Admin | http://127.0.0.1:18761/chief-dashboard |
| Staff Admin | http://127.0.0.1:18761/staff-dashboard |

Role selection is public snapshot navigation, not login or a permission grant. Actual role authorization is exercised during export; Staff requests for Chief dashboard and application review return 403.

## Feature inventory

| Feature | Browser availability |
|---|---|
| Merchant dashboard | Visual-only; real account/application projection |
| Business profile / onboarding | Visual-only existing onboarding status view; no separate profile editor invented |
| Merchant verification | Visual-only; no live OTP/NIN |
| Merchant applications | Visual-only actual application history |
| Payment links | Visual-only actual service-created fictional link; local checkout navigation |
| Customer checkout | Visual-only existing checkout form, disabled |
| Wallet | Visual-only actual ledger projection: TZS 12,500 fictional pending obligation, TZS 0 withdrawable, settlement unproven |
| Transaction history and detail | Visual-only actual payment/link/attempt/journal evidence and confirmed mock status |
| Merchant settings | Existing account view only; unsupported settings remain unavailable |
| Chief dashboard and access scope | Visual-only existing workspace views |
| Application review and decision history | Visual-only existing redacted list/detail and audit timeline after isolated approval |
| Merchant management | Dedicated management screen unavailable: no corresponding existing route; review submitted merchants through applications |
| Staff dashboard and access scope | Visual-only existing authorized views; Chief/application review access denied in tests |
| Other platform/staff administration | Existing unavailable labels retained; no new capabilities invented |
| Browser writes | Unavailable; all forms disabled, scripts removed, POST rejected with 405 |

Startup executes existing real P1–P7 services inside isolated CLI tests: fictional registration/verification, business-name completion, submission, Chief approval, payment-link creation, mock checkout, authenticated mock confirmation and ledger posting. Those results supply the snapshots. Clicking a browser button never claims to execute those services.

## Isolation and lifecycle

Initial repository: clean `main`, HEAD `aa6325dd90df8c61d2dcd320cb99a0df4c953cce` (`P7`). Read backend `AGENTS.md`; no `.ai/rules` directory or available testing-best-practices skill. Installed Laravel 13.35.0, PHPUnit 12.5.38, Pint 1.32.1, PHP 8.5.13. No dependencies installed.

The retired PREVIEW-01 is referenced in prior reports; its server/scripts/fixtures were not found in the inspected project locations. No separately named P8 implementation or uncommitted work was found. All pre-existing tracked files remain unchanged, with an empty index. No P8/P9 implementation was started.

Existing harnesses explicitly require CLI, testing, synthetic-only mode and owned in-memory SQLite connections. Browser access would fail those guards. The permitted visual fallback preserves them: the exporter uses the pinned isolated bootstrap, an absent synthetic environment file and in-memory stores. Node serves an explicit in-memory allowlist of rendered HTML and approved public CSS/image assets; it never boots Laravel or opens a database. Ordinary-site URLs are rewritten to demo destinations. Unexported links explain unavailability.

Startup verifies isolation guard hashes, absent synthetic environment/cache sentinels, runtime and port, then checks the server's ownership token. Server binds only `127.0.0.1`, validates Host/peer, rejects writes, and uses no-store/CSP/noindex headers. Shutdown checks PID, process start time and command identity, then removes only validated owned snapshot files. Successful stop/cleanup and fresh starts were exercised. Disposable state/exports are narrowly ignored under scripts; QA screenshots are retained separately in system temp.

HTTP loopback was selected without provisioning certificates or accessing private keys. `https://xlipa.test` and Herd TLS/configuration were not modified. No working database, real `.env`, operational APP_KEY, credentials or private keys were read. No frozen schema, contract or manifest changed. No live payments, settlement, withdrawals, operational submissions or Chief decision writes enabled. B3.2-C and SE-1.0 remain **NO-GO**. No commit, push or deployment.

## Verification

- Final export: **1 test, 67 assertions passed**.
- Existing P6/P7/AdminWorkspace/AdminApplicationReview suites: **88 tests, 3,629 assertions passed**.
- HTTP: **18 HTML endpoints and 10 local assets returned 200**; no ordinary-site URLs or scripts remained. POST returned 405; `.env` and working-database URL probes returned 404 without filesystem access. Running-server ownership health check passed.
- Headless Chromium: **34 desktop/mobile page checks** across home plus 16 feature pages, at 1440px and 390px. No document overflow, broken images, missing stylesheets or enabled form fields. Desktop/mobile screenshots inspected; demo banner styling corrected without changing branded assets.
- Browser and native computer-use connectors failed because the native pipe was unavailable. Used the repository's established headless Chromium/CDP approach instead; no browser installed.
- Final screenshot evidence: `C:\Users\hp\AppData\Local\Temp\xlipa-demo-qa-Lue9Xc`.
- Pint passed for the final new PHP exporter. Existing tracked source and index diffs are empty.
- P0 final source-frozen rerun: **PASS, exit 0**, including 29 frozen pins, three manifest digests, four INT-05 pins, four isolation guards, syntax for 226 PHP files, non-mutating formatting, **33 functional tests / 964 assertions**, **546 regression tests / 4,824 assertions**, and final source preservation. The first run correctly failed preservation because the new exporter was edited while verification was running; the complete rerun resolved that verification race.

Scope limits: this demo does not exercise browser authentication, form submission, keyboard/screen-reader behavior comprehensively, or every financial state/filter interactively. Those capabilities retain their existing isolated functional coverage. No available funds or production readiness is inferred from fictional accounting.

## Platform-wide UI/UX resumption � 21 November 2026

This section supersedes the historical inventory, counts and clean-source statements above. The continuation began with 24 modified tracked files and existing untracked UI components, CSS, tests and demo infrastructure. None was reset, cleaned or discarded. The earlier 44-page / 132-check browser evidence in `.recovery/ui-ux-evidence-1795248430208` was recovered and retained.

### Recovered implementation and remaining task

| Pass | State recovered | Continuation result |
|---|---|---|
| A � shared design | Product stylesheet, grouped role navigation, responsive workspace layouts, cards, tables and form styling already implemented | Preserved; fresh desktop/tablet/mobile verification |
| B � merchant and finance | Dashboard KPIs, ledger position, TZS 0 withdrawable, 30-day trend, date windows, latest payments, links/checkout, wallet, history/detail and onboarding already implemented | Added missing, separate Traffic Source Attribution and Payment Method Analytics availability sections; no backend or fabricated figures |
| C � administration | Chief workload cards, application review/history, shared administrative navigation and Staff access scope already implemented | Preserved; existing authorization tests and browser role previews verified below |
| D � remaining website | All 10 public pages, authentication/recovery/verification layout, account/security/notification availability and developer/support descriptions already upgraded | Preserved; 10-page frontend interaction checks passed |
| E � demo and verification | Exporter already expanded to 42 fixture snapshots; existing scripts and 44-page browser harness present | Refreshed using owned lifecycle; final evidence recorded below |

The next genuinely unfinished implementation task was the missing traffic-source dimension. The dashboard now distinguishes where customers came from from how they paid. It names Facebook, TikTok, Instagram, WhatsApp, Google, YouTube, Direct, Referral/Other and Unknown separately from M-Pesa, Mixx by Yas, Airtel Money, HaloPesa and supported banking/payment methods. Both sections explain absent evidence or unimplemented reporting. No counts, financial values or provider identities were inferred.

### Changes made during this continuation

- `backend/resources/views/merchants/overview-finances.blade.php`: two distinct analytics availability sections.
- `backend/tests/Feature/SafeBrowserDemoExportTest.php`: assertions preserve both dimensions, planned categories and unavailable-data wording.
- `scripts/Test-Safe-Browser-Demo.cjs`: verifies both analytics headings and captures each section at 1440, 768 and 390 pixels.
- This existing report: recovery state and fresh evidence; no duplicate report introduced.
- Generated localhost snapshots and ignored QA evidence refreshed; all earlier source changes retained. No service, schema, migration, guard, manifest or dependency changes.

### Fresh verification and demo

- Required `vendor/bin/pint --dirty --format agent`: passed.
- `Test-Frontend.cjs`: 10 pages, 31 interaction checks, zero DOM script errors; passed.
- Final isolated demo export: 1 test / 124 assertions; passed. 42 fixture snapshots plus demo home and feature guide produce 44 browser endpoints.
- First fresh `Test-Safe-Browser-Demo.cjs`: 44 HTTP pages, 16 assets, 56 navigation destinations, 132 browser checks; passed. Keyboard preview menu, visible focus, actual wallet navigation and mobile menu fit passed. No document overflow, broken images, missing stylesheets or enabled form fields. POST returned 405; secret and database URL probes returned 404.
- Evidence from that run: `.recovery/ui-ux-evidence-1795266385403/qa.json`; Merchant desktop, Chief tablet, Staff mobile and merchant analytics mobile screenshots inspected.
- The first export attempt overlapped the analytics label edit and failed an obsolete `Payment networks` assertion. The assertion was updated and the final fresh export passed; this failed attempt is not counted as a pass.
- An initial preservation invocation stopped at the local PowerShell policy; the corrected invocation uses the established ExecutionPolicy Bypass entry point. An intermediate preservation run was interrupted before edits to avoid a source-preservation race. Only the final stabilized run is authoritative.
- Browser approval review timed out once; the permitted retry succeeded. Installed runtimes required sandbox escalation. No dependencies or browsers installed.

The demo remains localhost-only, static and read-only at **http://127.0.0.1:18761/**. It uses fictional fixtures and never boots operational Laravel in the server. Browser forms and scripts remain disabled. Mock confirmations establish no settlement; available-to-withdraw remains TZS 0. No working database, real environment, credentials or keys accessed. No activation, operational writes, P8/P9 work, commit, push or deployment. B3.2-C and SE-1.0 remain NO-GO.

Existing future capabilities (live analytics attribution, withdrawals, settlements, merchant management, notification/preferences editors and production developer tools) remain explicitly unavailable; they are not unfinished implementations within this UI-only scope. Browser authentication, live submissions, exhaustive assistive-technology behavior and every interactive filter are outside the static demo's verification coverage.

### Final verification follow-up

- Existing merchant/dashboard/admin/payment-link/checkout/wallet selection: **119 tests / 4,010 assertions passed** (`.recovery/ui-resume-functional.log`).
- Final browser harness, including explicit analytics headings and six section screenshots: **44 HTTP pages / 16 assets / 56 navigation destinations / 132 viewport checks passed**; keyboard and actual wallet navigation passed. Evidence: `.recovery/ui-ux-evidence-1795266448029/qa.json`. Inspected both analytics sections on mobile, public home desktop, checkout mobile, wallet tablet and sign-in mobile in addition to the role-dashboard screenshots from the prior fresh run.
- P0's first completed bounded regression correctly caught an older onboarding test counting every disabled button in the page (the shared navigation now adds disabled feature buttons). Updated `backend/tests/Feature/MerchantOnboardingPreparationTest.php` to count the disabled save button only inside `[data-completion-preview]`. The enabled/disabled requirements, no-form/no-script/no-hidden-input checks, database fingerprint and notification assertions remain intact. Focused rerun: **16 tests / 132 assertions passed**. Pint passed again. Full P0 rerun is recorded below when complete.

### Visual defect fixed during final inspection

Fresh mobile screenshot inspection of password recovery exposed a public-site `nav { position:absolute }` rule leaking into the shared account header. It covered the recovery heading. The generic site footer background also made the account card's footer text difficult to read. Added narrowly scoped `.legal-header > .legal-nav` static positioning/background rules and a transparent `.legal-content > .product-footer` background in the existing product stylesheet; synchronized that one asset to the existing public mirror. Public navigation and branding remain unchanged. The browser harness now checks heading clearance, navigation flow and footer background for password recovery/reset, email verification and account pages at all three widths. Refreshed the owned demo again: isolated export **1 test / 124 assertions passed**. Final post-fix browser evidence follows below.

### Acceptance � PASS (existing platform UI/UX scope)

All five UI/UX passes are complete for the platform's existing interfaces. No required UI implementation or verification task remains open in this continuation. Future business services remain labelled unavailable rather than implemented by this UI task.

- Final P0 command: `powershell -NoProfile -ExecutionPolicy Bypass -File XLipa/scripts/verify-isolated.ps1`, **exit 0**. Log: `.recovery/ui-resume-preservation-complete.log`.
- **29 frozen pins, 3 manifest digests, 4 INT-05 pins and 4 isolation guards passed**.
- **226 PHP syntax checks** and **15 non-mutating formatting checks passed**.
- **33 functional tests / 964 assertions passed**.
- **546 bounded regression tests / 4,879 assertions passed**.
- **Final source preservation passed**. No frozen source, manifest or guard changes.
- Final post-CSS browser command: `Test-Safe-Browser-Demo.cjs`, **exit 0**. **44 HTTP pages, 16 assets, 56 navigation destinations and 132 viewport checks passed**, including analytics and account-layout assertions, keyboard menu and wallet navigation. **103 screenshots** retained at `.recovery/ui-ux-evidence-1795266723048`; machine results in its `qa.json`, execution output in `.recovery/ui-resume-browser-accepted.log`. Recovery mobile screenshot inspected and the covered-heading/low-contrast-footer defects are resolved.
- Final isolated export: **1 test / 124 assertions passed** (`.recovery/ui-resume-demo-final.log`).
- `git diff --check`: exit 0. Current demo ownership health passed. Demo left running at **http://127.0.0.1:18761/** for owner review.

This is acceptance of the existing interface upgrade and isolated verification, not operational activation. All static-demo coverage limits above remain. The established P0 regression is a bounded suite, not a claim that every repository test or every assistive-technology workflow ran. No financial data fabricated; no real funds, settlement or production readiness inferred.

## Subsequent development — P8 and functional analytics

The UI-only acceptance above is historical. The subsequent owner-authorized development assignment implemented functional isolated analytics and P8 settlement/withdrawal/reconciliation. Current capabilities, demo evidence, exact changes and separate acceptance statuses are in `XLIPA-P8-AND-MERCHANT-ANALYTICS-IMPLEMENTATION-2026-11-21.md`. Its final demo has 45 fixture snapshots and 47 browser pages, including service-calculated analytics and independently granted Chief financial oversight. Operational financial access remains disabled; P9 has not started.
