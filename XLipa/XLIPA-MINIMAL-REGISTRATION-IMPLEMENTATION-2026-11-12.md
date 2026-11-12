# XLipa — Minimal Registration Implementation

12 November 2026 · Africa/Nairobi · Existing workflow only; isolated fictional verification

## Outcome

Implemented optional business name at signup. Omitted, null, empty and whitespace-only names persist as exactly `business_name=''`; no company name is invented. Registration still creates the existing complete account/merchant/owner/draft/audit bundle. Verified-email owners can use the existing unnamed-merchant dashboard without phone possession proof, NIN or full KYC. Unnamed merchants remain unable to submit.

No name-completion action, new service/product/dashboard, schema amendment or activation was added. **STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**

## Inspection and preserved starting state

Read backend `AGENTS.md` and the PD-05A Final Decision Contract Specification. Inspected registration request/action/controller/form, CreateMerchant, foundation schema and draft creation, account selector/onboarding/dashboard, authentication/dashboard tests and isolated bootstrap, C/SE name guards/readiness, lifecycle and internal submission boundaries. `.ai/rules` was absent at workspace, XLipa and backend paths; no testing-best-practices skill was available in the session catalog. Installed metadata: Laravel v13.35.0, PHPUnit 12.5.38; existing PHP 8.5.12 used. No dependency or sub-agent added.

HEAD remains `014460f1b8070e6f823fff3bd80ede294d4e8341`. Starting tree had six tracked PD-05A modifications and six untracked prior deliverables. Those are baseline work, not changes attributed to this task. Staging was empty. Previous Chief review implementation and all three PD-05A reports were preserved.

## Exact files changed by this task

Paths relative to `XLipa/`:

| File | Purpose |
|---|---|
| `backend/app/Http/Requests/Auth/RegisterRequest.php` | Normalize absent/string businessName; optional nullable bounded string validation; other rules unchanged |
| `backend/app/Actions/Auth/RegisterMerchantOwner.php` | Handle missing/null/empty name safely; explicitly request unnamed creation |
| `backend/app/Actions/Merchants/CreateMerchant.php` | Nullable name means initial unnamed merchant; persist empty string; preserve supplied-name validation and atomic graph |
| `backend/app/Models/Merchant.php` | Add display-only `businessNameLabel()`; no accessor/storage mutation |
| `backend/app/ViewModels/MerchantOnboardingSummary.php` | Use display label in existing onboarding/dashboard summary |
| `backend/resources/views/account/show.blade.php` | Nonempty accessible merchant label in account selector |
| `backend/resources/views/merchants/applications.blade.php` | Display unnamed label above existing submission blockers |
| `backend/resources/views/merchants/verification.blade.php` | Display unnamed label on existing verification page |
| `frontend/xlipacreateaccount.html` | Label business name optional and remove only its HTML required attribute |
| `backend/resources/views/xlipa/xlipacreateaccount.blade.php` | Matching generated/ignored runtime copy of that canonical form |
| `backend/tests/Feature/AuthenticationTest.php` | Append input normalization/type/security/graph/access/rollback/form cases |
| `backend/tests/Feature/MerchantDashboardTest.php` | Append unnamed-page labels/read-only fingerprint and optional fictional HTML exports |
| `backend/tests/Feature/SubmissionLifecycleTest.php` | Append SE readiness/epoch/verification/unnamed-dashboard/public-denial case |
| `backend/tests/Feature/ApplicationSubmissionServiceTest.php` | Append unnamed internal-service and direct frozen-schema denial case; retain earlier PD-05A test |
| `scripts/Test-Minimal-Registration.cjs` | New offline fixture-only responsive checker, based on existing QA approach |
| `XLIPA-MINIMAL-REGISTRATION-IMPLEMENTATION-2026-11-12.md` | This report and current roadmap status |

No controller, route, authorization policy, frozen schema/readiness source, submission service, public guard or shared stylesheet changed. The existing dashboard/onboarding Blade templates obtain their label through the summary; no new dashboard or styling was needed.

## Registration before and after

| Behavior | Before | After |
|---|---|---|
| Missing/null/empty/whitespace businessName | Registration validation rejected it | Canonical empty string persisted; same successful guest redirect |
| Supplied valid name | Required string, max 200 | Optional string, trimmed, max 200 |
| Malformed/oversize name | Invalid | Arrays/objects represented as request structures, booleans, numeric values and 201-character name rejected |
| Full name/email/phone/password/confirmation/terms | Required | Required, existing normalization/security rules retained |
| Privilege claims and duplicate email | Denied claims; generic duplicate handling | Unchanged |
| Initial graph and version | User/terms/merchant/owner/draft/audit at version 1 | Same, including for unnamed merchants |

Request normalization changes only null/string businessName input; malformed types are not coerced. The registration action handles omitted keys and passes explicit null for an unnamed creation. CreateMerchant maps that null to `''`; non-null empty supplied names still fail its internal creation contract. No NULL is written to the NOT NULL field, and no account name/email/ID/display label becomes a business name.

The existing registration transaction retains accepted terms, declared normalized phone, active owner membership, initial current draft attempt 1/profile version 1, normal onboarding/unverified flags and existing merchant.created audit. Audit failure rolls back all graph records. Existing duplicate-email handling remains generic, sends no duplicate verification notice and creates no extra records. Registration leaves the registrant a guest.

## Dashboard and security behavior

Verified-email active owners can access account selector, existing dashboard and onboarding with an empty persisted name and no phone/identity evidence. Email verification, fresh session/auth version and tenant membership remain the access controls. Phone is still collected at signup as declared `business_phone`; it does not manufacture verified application contact evidence. Login, privilege, CSRF, rate-limit, suspension, stale-session and cross-tenant protections remain covered by the retained regression selection.

`businessNameLabel()` returns “Business name not provided” only for display. Named text remains Blade-escaped. The account link, dashboard workspace and onboarding headings are nonempty; existing application/verification pages use the same label. Read-only page fingerprints and raw stored-name checks establish that rendering does not save the label or change profile versions. Premium light assets and responsive rules are unchanged.

## Submission and operational restrictions

The existing availability guard still reports a required business name. Public POST remains unconditionally unavailable. The internal PD-04B.3 service is unchanged and rejects an empty-name merchant even with otherwise complete encrypted fictional lineage and fictional admission. Direct evidence INSERT is also rejected by the unchanged SE name invariant, without consuming proof or creating evidence. Existing fully named service success/replay cases remain in regression.

SE-1.0 exact readiness remains valid on the generated unnamed registration fixture. Email-binding and membership authority epochs initialize at 1; versioned email verification works. No schema additions, name-completion endpoint, request bypass, synthetic runtime configuration or real trust adapter is added. Capture/delivery admission, SE account/password lifecycle hold, public submission, Chief writes, Selcom and payment activation remain unchanged.

## Actual verification

All PHP tests use the inspected environment-file-free bootstrap, generated in-memory SQLite, fictional keys/lineage where required, array mail/session/cache and null logging/queue. Existing selected contention/unknown-outcome cases use only their own generated temporary databases and cleanup. No working application was browsed or started.

Commands from `XLipa/backend`, prefixed with `C:/Users/hp/.config/herd/bin/php85/php.exe -d 'auto_prepend_file='`:

```text
vendor/bin/pint --dirty --format agent
vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'AuthenticationTest|MerchantDashboardTest|test_minimal_signup'
vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'AdminApplicationReviewTest|ApplicationSubmissionServiceTest|SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest|SubmissionLifecycleTest|SubmissionEvidenceCipherTest'
```

| Final check | Actual result |
|---|---|
| Focused registration/dashboard plus minimal-signup cases | **83 passed / 956 assertions / 39,550 ms**, exit 0 |
| Final fictional export case after stylesheet-inlining correction | **1 passed / 46 assertions / 3,305 ms**, exit 0 |
| Vetted combined regression | **400 passed / 3,503 assertions / 168,554 ms**, exit 0; zero failures/errors |
| Required final Pint | Passed without changes, exit 0 |
| Tracked whitespace and staging checks | git diff --check passed; staged diff empty |

Do not add overlapping focused/export/regression results. Twenty-four new minimal-signup cases / 404 assertions were appended across existing test classes; no existing case was removed. The combined result retains the previous 376 tests / 3,099 assertions plus those 24 cases / 404 assertions. The final regression is the authoritative combined result. Earlier focused runs also passed, but do not replace the final evidence above. The final exporter-only correction was retested and included in the subsequent combined regression.

Offline browser command from workspace root:

```text
"C:/Program Files/nodejs/node.exe" XLipa/scripts/Test-Minimal-Registration.cjs XLipa/backend/storage/framework/testing/minimal-visual-5a04b46cd7bf78e5514d
```

**18 page/viewport combinations passed, exit 0**: signup, account selector, dashboard, onboarding, application history and verification × desktop 1440, tablet 820 and mobile 390 px. Zero horizontal overflow/clipped checked containers, zero page errors, zero HTTP requests. Checks assert optional signup name, preserved other required form controls, unnamed labels and keyboard-visible existing skip navigation where present. Signup has no skip link; no new skip-link claim is made for it. Desktop/mobile signup and the mobile unnamed dashboard screenshots were visually inspected.

Ignored reproducible artifacts: `backend/storage/framework/testing/minimal-visual-5a04b46cd7bf78e5514d/`, with rendered/inlined fictional HTML, fixture marker, browser-results.json and 18 screenshots. No Vite build or broad frontend synchronization was necessary; only the canonical signup form and its matching runtime copy changed, with equality asserted by a test.

Development notes: initial PHP runs returned slowly; their completed results were inspected before acceptance. An initial browser export left absolute stylesheet URLs uninlined because the asset helper normalized a trailing slash; Chrome blocked those requests and the checker failed. Correcting only the test exporter and rerunning produced the zero-request final evidence. During edit review an overbroad HTML replacement briefly removed fullName's required attribute; it was restored before test execution, and the final form test asserts every other required field. No security constraint was weakened to obtain a pass.

## Preservation evidence

A bounded pre-edit inventory covered **211 existing source/asset/script/test/config/schema/report/roadmap files**: **198 unchanged, 13 intended changes, zero missing**. The changed list is precisely the existing tracked source/test/canonical-form files in the table above; the ignored runtime form copy and new QA script/report are separately listed. This inventory deliberately excluded working databases, sidecars, environment/secrets and dependency/storage trees; it is not a whole-workspace certificate.

Prior PD-05A controller/routes/bootstrap/views/test helper/reports/roadmap remained byte-identical except the explicitly appended test in the already modified service test class. Within that class the previous Chief integration test and all earlier cases remain. The previous roadmap file was not rewritten; this report records the current update.

Explicit preserved SHA-256 pins:

- SE schema source: `1fde336da8709ea7d052fe4fdb41262e47cea4aad5cd505e32d26c0ee769ee61`.
- Frozen C artifact: `be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97`.
- Frozen C contract: `09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0`.
- Frozen C manifest: `20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873`.

No working SQLite/sidecar was opened, read, copied, hashed or modified. No real dotenv, operational key or credential was accessed. No working migration, real identity capture, OTP/email delivery, successful public submission, Chief decision, Selcom call or payment occurred. No commit, push or deployment occurred.

## Roadmap and remaining approved-scope work

| Milestone | Current development status |
|---|---|
| PD-01/02/03 and UI-01 | Existing dashboard/admin/verification/premium light work preserved |
| PD-04B.3 and related submission contracts | Preserved; internal bounded protocol retained, public success and activation denied |
| PD-05A Chief review foundation | Prior read-only implementation preserved; no decision writes |
| Minimal registration | Optional name and unnamed-page compatibility implemented and verified in isolated fixtures; ready for owner review |
| Business-name completion | Explicitly not implemented in this task; an unnamed merchant cannot submit until a separately approved completion flow exists |
| Decision/successor amendment and integration | Remain subject to the final specification, security-policy decisions and separately approved exact schema/readiness work; no automatic continuation |
| Operational readiness | NO-GO; lawful identity policies, trusted issuer/admission, custody/recovery, retention/holds, privileged assurance/revocation, trusted time, authoritative restore/release and production fault evidence remain independent blockers |

Next step is owner review of this bounded diff and actual evidence. No unsupported completion percentage is assigned; development completion is separate from production readiness.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**
