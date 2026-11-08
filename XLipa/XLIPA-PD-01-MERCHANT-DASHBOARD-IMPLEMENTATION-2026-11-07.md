# XLipa — PD-01 Merchant Dashboard v1 Implementation

**PD-01 · Implementation and verification: 8 November 2026 · Africa/Nairobi**

**Requested report filename retains 2026-11-07. Production-intended application source; synthetic fixtures only for testing.**

## 1. Outcome

**IMPLEMENTED / VERIFIED:** a real Laravel/Blade Merchant Dashboard at `GET /merchants/{merchant}/dashboard`, named `merchants.dashboard`. It uses the existing authentication/readiness chain, fresh member-scoped merchant resolution, merchant/application policies and `MerchantOnboardingSummary`. The `/account` selector now offers an **Open dashboard** link for each authorized merchant, while preserving the existing onboarding link and login/selection behavior.

Final focused dashboard suite: **30 tests / 273 assertions**, zero failures/errors. Final explicitly vetted regression selection: **122 tests / 1,040 assertions**, zero failures/errors. Existing canonical frontend checks: **10 pages / 31 interactions / zero DOM script errors**. Offline browser checks passed at **1440, 820 and 390 px**, with no page overflow, hidden navigation, page exceptions or HTTP requests. The entire repository suite was **not** run; section 5 explains the safety exclusion and automatic approval rejection.

The feature is application code, not a separate demonstration site: there is no fixture flag, hard-coded merchant/account or synthetic helper in its runtime path. Tests generate records independently. No working SQLite, real dotenv, operational OTP, NIDA data, provider, message or payment was accessed or activated in this package. **Working B3.2-C activation remains NO-GO.**

Labels: **VERIFIED** = inspected source or newly executed checks within the stated scope; **REPORTED** = prior report evidence, not a fresh result; **NOT TESTED** = no executed evidence here; **PROPOSED** = future work.

## 2. Reuse and implemented behavior

Reviewed backend `AGENTS.md`, B2/B3.1 and ORG-1.0, current routes/controllers, merchant/membership models and policies, onboarding view model, canonical frontend/shared styles, sync script and isolated bootstrap/tests. `.ai/rules` was absent at the checked workspace/project/backend paths. No testing-best-practices skill was available; none was installed. Composer's local `show laravel/framework` with plugins/scripts disabled confirmed **v13.35.0**. Existing PHP reports **8.5.12** and Node **26.13.0**. No dependency changed.

Used the Sites skill's local-only repository workflow with the user-requested Laravel implementation; no Site registration, hosted project, source synchronization, publication or new frontend framework was used. Files were created directly to avoid a normal Artisan generator boot against working configuration.

### Dashboard contents

- Merchant name, current authenticated account name/email and fresh active membership role.
- Actual current merchant operational/verification status and current application status through the accepted safe summary.
- Account access facts from the enforced active/verified authentication chain. No phone-verified claim, readiness percentage or payment eligibility inference.
- Status-aware readiness copy for draft, awaiting review, information requested, approved, rejected, missing current application and restricted merchant states.
- Owner/manager-only attempt/timestamp/public-feedback detail, following the existing application policy; staff retain the safe summary only.
- Available actions: existing onboarding status, account/merchant selector, public developer guide, public contact/support and existing CSRF-protected sign-out.
- Explicitly disabled navigation for Payments, Payment Links, Checkout, QR Payments, Wallet, Settlements, Reports, Developers/API tools and Settings. Support links to the existing contact page; the public developer guide is clearly distinguished from unavailable API tools.

No balance, payment count, transaction total, recent-payment row or invented success appears. Even an enabled/verified merchant with an approved application sees financial services as unavailable. No new editing, submission, proof, review or approval workflow exists.

### Visual implementation

A scoped black-and-white workspace uses the existing XLipa mark, shared site stylesheet, font stack and asset delivery. Public/auth pages and their styles remain untouched. A dark sidebar becomes a horizontally scrollable navigation strip on tablet/mobile; cards and status/account panels stack on phones. Native links/buttons, visible keyboard focus, skip link, named navigation, a single page heading, disabled semantics, responsive viewport metadata and reduced-motion support require no runtime JavaScript or third-party resource.

The new CSS is canonical in `frontend/assets/`; its generated public copy is byte-identical. The existing sync script will copy it in the normal frontend workflow; that script did not need modification or a full sync run.

## 3. Exact file delta

Paths are relative to `XLipa/`.

| File | Change / purpose |
|---|---|
| `backend/routes/web.php` | **Modified:** add one authenticated, verified, member-scoped GET/HEAD dashboard route with the existing ULID constraint. |
| `backend/resources/views/account/show.blade.php` | **Modified:** add dashboard entry beside each existing onboarding link and replace the stray question-mark separator. Selection remains explicit; no automatic first-merchant redirect. |
| `backend/app/Http/Controllers/MerchantDashboardController.php` | **Added:** authorize the existing summary ability, reload active role, select only safe current-application columns and build the dashboard view model. |
| `backend/app/ViewModels/MerchantDashboardOverview.php` | **Added:** readonly presentation model wrapping the existing safe summary and current-account fields; factual readiness copy only. |
| `backend/resources/views/merchants/dashboard.blade.php` | **Added:** server-rendered responsive workspace; Blade-escaped content and existing logout action. |
| `frontend/assets/xlipa-dashboard.css` | **Added:** scoped workspace styles/breakpoints; no shared marketing-style edits. |
| `backend/tests/Feature/MerchantDashboardTest.php` | **Added:** 30 focused cases including role/state providers and optional synthetic response export for offline QA. |
| `scripts/Test-Merchant-Dashboard.cjs` | **Added:** dependency-free offline Chrome/Edge QA against an explicitly marked PHPUnit-exported fixture; no Laravel HTTP server. |
| `XLIPA-PD-01-MERCHANT-DASHBOARD-IMPLEMENTATION-2026-11-07.md` | **Added:** this report. |
| `backend/public/assets/xlipa-dashboard.css` | **Generated, ignored addition:** exact canonical CSS copy for existing Laravel asset serving. |

Thus there are **two modified pre-existing source files, six new source/test/QA files and this one report**, plus the ignored generated stylesheet. Existing untracked ORG/OSD/SOB work was preserved as pre-existing work. No existing controller, policy, model, middleware, migration, test, manifest or security helper changed.

## 4. Security verification

| Requirement | Executed evidence / result |
|---|---|
| Authentication | Guests redirect to login; unverified users to the existing verification notice. Suspended/closed accounts, stale auth versions and absent auth versions lose access through the existing fresh-user middleware. |
| Tenant context | Member-scoped route resolution rejects foreign/nonmember merchants with 404; forged query context cannot replace the route merchant. Account selector lists no foreign dashboard links. |
| Fresh membership and role | Revocation denies the next request despite preloaded relationships. Owner→staff downgrade immediately removes feedback/attempt detail. A fresh active-role query adds no creator/platform bypass. |
| Owner/manager/staff | All can read the allowed summary; only existing policy-authorized owner/manager details render. The source summary/policies are reused unchanged. |
| Platform roles | `chief_admin` and `admin` alone receive no tenant access. When such an actor separately holds staff membership, that membership still grants only staff detail. No Chief Admin authorization was modified. |
| Suspended/closed merchant | Preserves B3.1's accepted read-only status access, with a restriction notice and support direction. No state reset, payment activation or additional write permission occurs. |
| Missing/history data | No current application gives an honest unavailable state; historical feedback is not substituted, and no draft/repair record is created. |
| Redaction | No address, merchant contact email, internal notes, encrypted snapshot, identity/OTP field or raw model serialization is output. Runtime queries do not need the seven C tables. Current user's own account identity is intentionally displayed. |
| Output safety | Malicious merchant/account/feedback strings are escaped; DOM assertions find no script, inline SVG injection or event-handler attributes. No user-provided URL or raw HTML output is introduced. |
| Read-only domain behavior | Domain fingerprints remain unchanged after authorized, denied, restricted, missing-data and write-method checks. Dashboard POST/PUT/PATCH/DELETE return 405. Only the existing logout POST form is present, with CSRF. |
| Headers/errors | Existing no-store/private, no-referrer, nosniff and DENY headers apply, including errors/method denials. Unexpected controller exceptions are generic and use the existing redacted merchant log category. |
| No fabricated finances | Navigation DOM has exactly nine disabled sections; live anchor destinations are explicitly allowlisted. No currency amount, percentage, total-revenue card or available-balance value is rendered. |

The focused fixtures use the accepted test-only foundation builder exclusively in testing/memory mode: **14 foundation tables and nine migration records**, with no C activation required. Test helpers are never imported into the application controller/view models. Notifications are fake and stray Laravel HTTP requests are blocked. Framework session/CSRF handling remains existing behavior; “read-only” here describes new merchant domain behavior, not a claim that sessions can never write in a future deployment.

## 5. Test commands, results and safety limit

Commands used the existing PHP binary from `XLipa/backend`; no working Artisan, migrate, seed, setup or configuration command was run. The PHPUnit bootstrap sets `DB_DATABASE=:memory:`, empty DB URL, public fixture key, inert transports and no real dotenv/cache path. The existing base TestCase independently enforces that setup.

```text
<composer> --no-plugins --no-scripts show laravel/framework --no-interaction
<php85> vendor/bin/pint --dirty --format agent
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter MerchantDashboardTest
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest'
<node> XLipa/scripts/Test-Frontend.cjs
<node> XLipa/scripts/Test-Merchant-Dashboard.cjs <verified-synthetic-export-directory>
```

For the one offline export, the focused test process alone received `XLIPA_PD01_VISUAL=1`. This enables a fixture artifact export in the test, not a runtime feature flag. Subsequent regression processes did not enable it.

| Final executed check | Actual result |
|---|---|
| Pint after final PHP edits | Passed; no accepted PHP source reformatted. |
| Dashboard-only | **30 passed / 273 assertions / 5,253 ms; zero failures/errors**. |
| Explicit safe regression allowlist | **122 passed / 1,040 assertions / 19,267 ms; zero failures/errors**. Includes the 30 new cases and 92 existing account/merchant/foundation/trusted-host cases. |
| Existing frontend DOM suite | **10 pages / 31 interaction checks / zero DOM script errors**. External resource loading disabled. |
| Final offline browser suite | **Three viewports passed; zero HTTP requests, zero page errors**. Viewports/results in section 6. |

### Important scope correction: the whole suite is not safe by default

Source inspection found `tests/Feature/PhaseTwoFoundationTest.php::test_existing_sqlite_foundation_can_be_read_without_schema_changes` directly resolving `database_path('database.sqlite')`, hashing it and opening it through SQLite3. Setting Laravel's configured connection to memory does **not** redirect this direct filesystem access. That case was not executed in PD-01, and no legacy test was modified or weakened.

An attempted broader negative-filter command was **rejected by automatic approval review before execution**. Its stated concern was that other legacy tests reference/configure working SQLite paths, so the selection could access or modify working storage contrary to the user boundary. The safer alternative was the explicit five-class allowlist above, after inspecting their memory guards and disposable concurrency fixture handling. It was allowed and passed. No broader selection, unsafe probe or workaround was executed.

The B/C/SOB/recovery suites outside that allowlist were not rerun here. Their prior totals remain **REPORTED**, and earlier whole-suite totals must not be used as proof that every legacy case avoids working storage. This package supplies no new all-suite synthetic-isolation certification. A separately scoped future test-harness correction should replace that direct-file probe with an independently generated fixture before an unrestricted suite is considered safe; PD-01 leaves accepted tests unchanged.

Development results are retained: the first 28-case run passed 27 and failed the error-redaction test because a prior request had cached the route controller before mocking. Splitting header and injected-error checks into separate isolated cases fixed the test setup without changing application policy; the 30-case suite then passed. Initial browser screenshots exposed global marketing `nav`, `h1` and footer style collisions, including hidden mobile navigation. Scoped overrides and stronger visibility/theme checks corrected them. No production guard was weakened to obtain a pass.

## 6. Responsive and visual evidence

The offline export is an actual Blade response from the dashboard route using generated foundation data. Styles/favicon are embedded only in that ignored QA artifact. The production view continues to load normal same-origin canonical assets. Headless Chrome uses a newly created profile, blocks HTTP/HTTPS page requests and never contacts the Laravel site or a provider. Its profile is removed after the run.

| Layout | Viewport width | Captured page height | Result |
|---|---:|---:|---|
| Desktop | 1440 px | 1396 px | Sidebar, two-panel detail layout, visible navigation and heading, no horizontal overflow. |
| Tablet | 820 px | 1591 px | Scrollable navigation strip, responsive content/panels; no hidden current link or clipped panels. |
| Mobile | 390 px | 2534 px | Navigation strip and stacked cards/panels/actions; no page overflow. |

All widths verify keyboard-visible skip navigation, one current overview link, nine disabled feature sections and correct black/white heading/footer theme. Desktop/mobile and tablet screenshots were visually inspected. These checks are not a complete WCAG, assistive-technology, cross-browser or device certification.

Final ignored synthetic QA artifacts:

- [Desktop screenshot](backend/storage/framework/testing/pd01-visual-272bf4a128ffe67729be/desktop.png)
- [Tablet screenshot](backend/storage/framework/testing/pd01-visual-272bf4a128ffe67729be/tablet.png)
- [Mobile screenshot](backend/storage/framework/testing/pd01-visual-272bf4a128ffe67729be/mobile.png)
- `backend/storage/framework/testing/pd01-visual-272bf4a128ffe67729be/browser-results.json`

Two obsolete generated preview directories were removed nonrecursively after verifying exact resolved child paths, no reparse points/directories and expected artifact filenames. No pre-existing project or working-storage content was removed. Final screenshots/fixture evidence are private ignored test artifacts, not a hosted standalone demonstration.

## 7. Preservation evidence

Before implementation, **214 pre-existing source/document files** were SHA-256 inventoried in memory using a source-extension allowlist. Working SQLite/databases/sidecars, real dotenv/credential/key/secret and protected metadata paths were excluded before hashing. This inventory includes the pre-existing untracked reports/SOB package; ignored dependency/cache/recovery trees and binary assets are not exhaustively certified.

Comparison after verification found only the **two intended existing-file changes**: `backend/routes/web.php` and `backend/resources/views/account/show.blade.php`. The other **212 baseline files were unchanged**, with zero missing. Original marketing/auth frontend, styles, policies, models, B/C migrations, activation manifest, accepted tests/security contracts and prior reports are preserved. No preservation inventory file was created. Public generated dashboard CSS matches its canonical source exactly.

Final source SHA-256 pins, including the two modifications and generated stylesheet (this report has no self-referential hash):

| Path | SHA-256 |
|---|---|
| `backend/app/Http/Controllers/MerchantDashboardController.php` | `f834f942d564eae6a1ba3d256f019b1cade1b0b49ed3357f0a1326965bc55105` |
| `backend/app/ViewModels/MerchantDashboardOverview.php` | `092717cb5a0d98f54a4c8e2af0ad50868fca159da9c4d6e833aa4bc3d75c911c` |
| `backend/resources/views/merchants/dashboard.blade.php` | `ead44a5023034bdc17335c847effb70766f1e1ecde51fac4014131c9a267496f` |
| `backend/tests/Feature/MerchantDashboardTest.php` | `22e33b7924ea2c713b183a2968b41441c1f02619aa3c07571da82802fcae703b` |
| `frontend/assets/xlipa-dashboard.css` | `f03a146c8af569be3bc87e25402db989d3938aa79b4b368e4cbea72d1662bf86` |
| `scripts/Test-Merchant-Dashboard.cjs` | `b4ce0e77d416e08079425f59dbb2c82aee5d2af0618fea2cea4f013cb689921c` |
| `backend/routes/web.php` | `3c11c0ee8bd914b078e2d094725f84f145e117fbf1a3f93fdf87d811b7894e50` |
| `backend/resources/views/account/show.blade.php` | `77c43da3367af22b583a9e88f088df1954de5a77ccc19854d20986cbdf68b50f` |
| `backend/public/assets/xlipa-dashboard.css` | `f03a146c8af569be3bc87e25402db989d3938aa79b4b368e4cbea72d1662bf86` |

**VERIFIED final checks:** all nine feature/generated source pins above remained unchanged after the final runs. The 214-file baseline comparison again found exactly the two intended modifications, 212 unchanged and zero missing. Tracked diff names are exactly those two files; staged diff is empty. Whitespace checks passed for all ten feature/report/generated files and the tracked diff, and all three screenshot links resolve. Git's pre-existing LF/CRLF notice is not a whitespace failure. No Git configuration was changed.

HEAD remains `d78797cf2cceb8d4c41b4fe375cee0f8cdff9975`; no stage/commit/push/deploy or operational activation occurred.

## 8. Known limitations and next package

- **NOT TESTED:** current working data/runtime, real authenticated browser navigation, real mail/provider behavior, production hosting/configuration or full accessibility/cross-browser coverage. The working database and real dotenv were neither read nor hashed.
- Existing authentication deliberately retains its local/testing plus log/array readiness profile. The dashboard source is reusable and production-intended; this package is not production deployment approval and does not relax that gate.
- Financial, API-key, settings-edit, KYC/OTP/submission/review and Chief Admin features are unavailable. Public developer/support links do not imply functioning API keys, ticketing or payments.
- Suspended/closed merchants preserve accepted read-only summary access; revoked membership and inactive account restrictions remain enforced. No new business mutation endpoint exists.
- The legacy direct-working-file test must be isolated before a future unrestricted regression run; historical aggregate passes do not close that specific test-safety gap.

**PROPOSED next product package: PD-02**, a separately authorized Chief/Staff Admin workspace shell and explicit permission/read-model contract using synthetic fixtures. It must preserve separation from merchant membership and grant no real administrator provisioning, review action or financial authority. No PD-02 implementation occurred here. Operational OP-01/OP-02 decisions/preparation can progress independently under their own scopes.

**GO for owner review of the implemented PD-01 feature and bounded evidence. Working B3.2-C activation remains NO-GO. STOP FOR OWNER REVIEW.** XLipa is a WEBSITE supporting TZS only.
