# XLIPA P2 functional merchant application submission

2026-11-14. Isolated implementation and verification. **PASS for the complete isolated P2 journey.**

## Exact changes

Recovery started on branch `main` at HEAD `576ad6f20552b9af835c2c1b69540e2d58efda02`. The existing INT-07A test changes and untracked INT-07A, P0 and P2-status reports/tooling were preserved. Read `backend/AGENTS.md`, the P2 status report, existing testing guards, service/fixture contracts and current implementation. Applicable `.ai/rules` directories were absent. The referenced testing-best-practices skill remains unavailable in the session catalog.

| File relative to XLipa | Change |
|---|---|
| `backend/tests/Feature/FunctionalMerchantApplicationSubmissionTest.php` | New P2 suite with 22 cases |
| `backend/tests/Support/ApplicationSubmissionHttpHarness.php` | New explicitly registered, protected test-only submission boundary and fictional fixture preparation |
| `backend/tests/Support/MerchantOnboardingJourney.php` | Existing INT-07A setup and HTTP journey helpers extracted into a shared trait |
| `backend/tests/Feature/FunctionalMerchantOnboardingTest.php` | Uses the shared trait, retaining the existing tests and earlier uncommitted corrections |
| `backend/tests/Support/MerchantOnboardingHttpHarness.php` | Existing memory/PDO guard made public for reuse by the P2 harness, with identical guard conditions |
| This report | New implementation and acceptance evidence |

The shared trait avoids recreating registration, email verification, business-name completion requests or fixture database installation. Existing business services, public controllers/routes, application UI, provider bindings, SQL, migrations, manifests and operational configuration retain their bytes. Files were created directly inside the authorized test scope to avoid an ordinary Artisan environment bootstrap.

## Full journey demonstrated

`FunctionalMerchantApplicationSubmissionTest::test_same_registered_merchant_completes_the_entire_http_submission_and_chief_queue_journey()` performs the following sequence.

1. Register a fictional merchant owner through HTTP without a business name.
2. Log in through HTTP and follow the existing signed verification URL from a faked notification. Check the actual email-binding version advances to 2.
3. Use the existing INT-07A HTTP harness and actual `BusinessNameCompletionService` to save `Kijani Studio`. Check the same membership and application IDs survive and the draft profile version advances to 2.
4. Read the existing applications page through HTTP and verify the draft and public submission unavailable state.
5. Prepare the same merchant's identity declaration, contact version, onboarding context, challenge/delivery/proof lineage and private expected submission tuple through the established fictional fixture mechanisms.
6. POST the server-custodied context reference to `/merchants/{merchant}/__isolated-submission`. The protected test-only boundary invokes the real `ApplicationSubmissionService::submit()` and returns the safe JSON status `submitted`.
7. Authenticate an independent fictional Chief Admin. GET the existing filtered review queue and assert its only row has the exact submitted application ID. GET the existing detail page and assert matching recorded evidence and submission history.

Application preparation follows the current service's actual requirements. The evidence contains the verified account contact, business name, identity declaration and contact-possession lineage with exact application, attempt, profile, membership, email/authentication, identity/contact and context versions. No additional business-information requirement or new runtime preparation interface was invented.

The fictional issuer uses the existing `FakeSubmissionAdmission`, `FakeSubmissionVerificationEnvironment` and `FakeSubmissionEvidenceEnvironment`, with the same lifecycle transitions as `ApplicationSubmissionServiceTest::fixture()`. Rows use the contract-required live-shaped provenance entirely inside the guarded fixture. Transport remains inherited-denied. No OTP, SMS or identity provider is called. Simulated-mode substitution is separately denied. The real submission service performs its existing trust, encryption, authority and schema checks.

The P2 test clock is fixed at 2026-11-14 00:00 UTC, with fictional submission time 100 ms later. This keeps the actual HTTP email-verification timestamp within the evidence contract's permitted ordering. Runtime clocks and contracts were untouched.

## Actual service and database effects

The successful request commits a real submission transaction to the owned memory database. Assertions require the original owner, merchant, membership and application identities, attempt 1, email-binding version 2 and profile version 2. They verify `pending_review`, the original submitting owner, canonical submitted time, a consumed proof bound to that application and one immutable encrypted evidence record with its matching submission audit.

The real `SubmissionEvidenceCipher` decrypts the generated fixture capsule during assertions, confirming the registered email, completed business name, Tanzania market and TZS currency. Foreign-key checking returns no violations. The original business-name completion record remains present. Duplicate HTTP dispatch cannot call the service again or alter the stored evidence.

Chief review shows the same committed application. Sensitive fixture identity/contact/email/request values are withheld. POST, PUT, PATCH and DELETE against the detail route return 405, and the application remains pending review with identical evidence bytes. Ordinary merchant and foreign-user review access is denied.

## Security tests

The suite rejects guests, unverified users, managers, staff, foreign users, Chief users without merchant ownership, revoked memberships, stale sessions and suspended accounts. It tests cross-tenant and cross-session context reuse, client-supplied expected versions, actual CSRF rejection, expired/revoked proof, stale authority/context versions, unavailable issuer/admission/retention and simulated verification mode.

Frozen evidence rejects mutation. The public submission endpoint still returns 503 after a successful isolated submission. A separately bootstrapped ordinary application has no fixture submission route and retains denied admission. Fictional admission, evidence and verification bindings are scoped to fixture preparation or dispatch and restored in `finally`.

Both pre-commit and post-commit acknowledgement loss are injected through the existing `SubmissionTransaction` seam. HTTP returns 202 unresolved without leaking the private error. Reads through the captured owned PDO distinguish draft/no evidence from committed pending-review/evidence. Repeated HTTP dispatch remains blocked and the commit-attempt counter stays at one. This establishes conservative uncertainty handling. Operational recovery and durable custody remain held.

The P2 boundary reuses the existing guard requiring CLI, synthetic-only flag, testing mode, memory SQLite without a connection URL and the exact owned PDO. The established bootstrap supplies fictional keys and absent environment/cache paths. Notifications are faked and stray Laravel HTTP is blocked. Successful new P2 writes stay in memory. Existing service regression uses only its established disposable fixture databases where persistence is required.

## Actual commands and results

Commands below ran from `XLipa/backend`, except the P0 entry point shown from the shared workspace root. The existing Herd executable required approved sandbox escalation. Installed metadata identifies PHP 8.5.12, Laravel 13.35.0, PHPUnit 12.5.38 and Pint 1.32.1. Dependencies were unchanged.

```powershell
$php = 'C:/Users/hp/.config/herd/bin/php85/php.exe'
& $php -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/FunctionalMerchantApplicationSubmissionTest.php
```

**P2 focused result — 22 passed, 970 assertions, 16,997 ms, exit 0.**

```powershell
& $php -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/FunctionalMerchantApplicationSubmissionTest.php tests/Feature/ApplicationSubmissionServiceTest.php tests/Feature/FunctionalMerchantOnboardingTest.php tests/Feature/AdminApplicationReviewTest.php
```

**Combined P2/service/INT-07A/Chief result — 112 passed, 2,515 assertions, 49,664 ms, exit 0.** This run followed final executable edits and non-mutating formatting checks.

Each of the five task PHP files passed `-l`, exit 0. With `$files` containing those five PHP paths from the change table, the final style check was as follows.

```powershell
foreach ($file in $files) { & $php -d 'auto_prepend_file=' -l $file }
& $php -d 'auto_prepend_file=' vendor/bin/pint --test --no-config --format agent @files
```

**Non-mutating Pint passed, exit 0.** Required scoped `--dirty --format agent` and explicit-path formatting were applied beforehand. Formatter changes were limited to task-file imports, indentation and style.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File XLipa/scripts/verify-isolated.ps1 -PhpPath C:/Users/hp/.config/herd/bin/php85/php.exe
```

**P0 entry point passed, exit 0.** PHP syntax passed for all 184 first-party files, and non-mutating Pint passed for its 15 selected files. INT-07A passed 33 tests / 964 assertions in 16,134 ms. The established bounded regression passed 546 tests / 4,824 assertions in 144,583 ms. Source preservation passed. Its unchanged selection runs separately from the P2/service combined command. Counts from overlapping selections must not be added together.

Initial development checks exposed a synthetic timestamp earlier than email verification, an incorrect 404 expectation for an existing 403 admin denial, and a suspended-account mutation prohibited by the frozen lifecycle hold. Test fixtures and expectations were corrected. Suspended-user coverage now uses a factory-created suspended actor. The frozen hold was preserved. Intermediate failures supply no final acceptance result.

## Preservation evidence

Final comparison of 325 recovery-inventoried source, report, configuration, asset and metadata files found 323 unchanged, two intentionally changed and zero missing. The only changed existing paths are the onboarding test and its HTTP harness from the change table. The three new PHP files and this report are the only additions. The existing INT-07A/P0/status reports and P0 tooling retain their bytes. Git whitespace checking passed and the index remains empty.

Read-only comparisons matched all 29 frozen foundation/C/SE/BNC/INT-01/INT-02 source/artifact pins, all three historical BNC manifest digests and four INT-05 protected source/test hashes, with zero mismatches. The P0 preflight independently matched those pins and all four isolation guards. No hash definition was regenerated.

No working database, sidecar, real environment, operational credential or private key was opened, copied, hashed, queried or migrated. All application/schema/provider source bytes are preserved. No commit, push, deployment or operational activation occurred. The older P2 status report remains a dated pre-implementation audit.

## Remaining blockers and final assessment

**PASS for the complete isolated P2 journey.** The same HTTP-registered and email-verified merchant completes its business name, prepares contract-valid fictional evidence, submits through the actual internal service and appears in the Chief read-only queue and detail page. Focused, combined and bounded regression runs, syntax, non-mutating style and preservation checks passed. No isolated acceptance blocker remains.

The test-only helpers grant no operational admission. Durable custody, real trusted issuance, authoritative runtime recovery and all existing deployment/activation dependencies remain subject to owner-reviewed holds. Browser automation and real provider delivery are outside this Laravel HTTP feature-test evidence.

**STOP FOR OWNER REVIEW.** Operational Business Name Completion, public submission, Chief Admin decision writes, live OTP/NIN, Selcom and live payments remain DISABLED. B3.2-C and SE-1.0 operational activation remain NO-GO. No working database migration, P3 work or new architecture contract was started.
