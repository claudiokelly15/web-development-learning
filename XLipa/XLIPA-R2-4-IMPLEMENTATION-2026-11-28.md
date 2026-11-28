# XLipa R2.4 — Products, Checkout, Mock Confirmation and Ledger Integration

28 November 2026 · Africa/Nairobi

**R2.4 STATUS: IMPLEMENTED AND VERIFIED within the fictional, disposable integration scope. The repository-wide suite is NOT GREEN: two inherited schema-test failures and one process-startup timeout are disclosed below. Operational activation, Selcom and deployment remain NO-GO. STOP FOR OWNER REVIEW.**

The ordinary HTTP journey now connects fixed TZS payment links, guest checkout, authenticated mock confirmation, exactly one sealed pending-accounting journal and persisted merchant transaction visibility. Focused, protected-contract, HTTP and responsive-browser checks passed. This is a scoped integration result with explicit repository-wide regression exceptions, not an all-green release or production approval.

## Continuation and scope

Continued the interrupted implementation without resetting, reverting or discarding its files. At resumption the working tree was clean: the existing `ffe15b131ef54ab733a0ae5d6854f89dcbf5d4f2` commit already contained the interrupted payment integration alongside earlier R2 work. No commit, push, deployment or dependency change was performed in this continuation. R2.5 was not started.

Recovered the previous `r24-existing-services-baseline.log`: 171 tests / 7,799 assertions PASS. That is historical evidence, not a new acceptance result. Pre-amendment payment and staging sources remain in `.recovery/r24-before/`. Subsequent failed assertions, diagnostic logs and retired generation stores were retained.

The implementation uses fictional accounts, fixed positive integer TZS amounts, the existing payment/link/provider/ledger services and the accepted composed SQLite schema. It adds ordinary Laravel HTTP admission inside the disposable loopback staging program. It does not activate operational merchants, settle funds, enable Selcom, issue developer API credentials, deliver webhooks or allow withdrawals.

## Five approved payment-service guard amendments

Only these five existing payment-service files differ from the preserved pre-R2.4 payment sources. Other existing payment services and the accepted financial schema are preserved. The continuation also formatted these amendments using Pint; their before/after SHA-256 values are recorded in `.recovery/r24-approved-guard-hashes.json`.

| Service | Additional fictional staging path | Restrictions retained |
| --- | --- | --- |
| `FictionalMockProvider` | Allows construction under the current signed payment program; an optional private runtime/store-bound registry preserves accepted mock requests between HTTP requests | Existing isolated-test path, exact 32-byte fictional key, authenticated event format, exact amount/reference/correlation, sequence, timestamp and terminal-state checks; no network provider |
| `PaymentEngine` | Guest checkout may execute under `r24.checkout.initiate` | Guest session hash, current owner/account/email/membership/application/evidence versions, fixed link amount, nonce, idempotency, reserved dispatch state and existing financial authorization |
| `PaymentLinkService` | Existing fixed-amount link/checkout methods may execute under the signed payment action set | Per-operation admission, tenant ownership, current authority, link state/expiry, guest nonce/session, transaction guard and historical status semantics |
| `MockPaymentLedger` | Allows only admitted post, reconcile or transaction-read actions | Independent ledger operation admission, eligible immutable confirmation source, current authority, double-entry balance, sealed journal uniqueness, no settlement or withdrawable credit |
| `MerchantDeveloperTools` | Narrow temporary widget scope for QR/button creation and resolution; generates the loopback fictional checkout URL | Scope restored in `finally`; non-widget developer operations retain their isolated-test-only guard. No operational API issuance, authentication or webhook capability is opened |

The staged additions are not global operational bindings. `StagingFinancialServices` constructs the financial store and services only after current runtime and request admission. `PaymentStore` and the mock provider are not installed as operational container defaults.

## Ordinary HTTP implementation

Merchant routes live under `/merchants/{merchant}/payment-products`: fixed-amount link creation/history/disable, private payment status and query, scoped mock confirmation, separately admitted accounting post/reconcile, widget creation/display and transaction visibility. Guest routes live under `/fictional-checkout/{token}`. They are registered only by the signed payment staging provider.

`financial-control.php` is a private CLI custodian, never a web endpoint. It issues exact actor, merchant, action, resource, runtime, generation, release and store grants. Grants bind current authentication/email/membership versions and the approved application; they expire within ten minutes and never after runtime expiry. Application approval alone cannot issue or exercise financial authority. Chief and Staff roles cannot borrow merchant ownership.

Financial writes require ordinary authentication where applicable, Laravel CSRF, the exact loopback Origin and a separately issued one-use command. Command claims and outcomes are durable. Unresolved or unknown outcomes remain quarantined; replacing an unresolved command is denied. Reads, queries and accounting reconciliation require their own scopes. Source-bound generations are retired before signed-source corrections; their stores and evidence are retained.

Guest admission requires an independently issued checkout bundle and an exclusive customer session binding. A session nonce binds the checkout attempt; repeated initiation returns the original attempt and payment without another dispatch. Guests cannot supply the amount, currency, owner, confirmation or accounting authority. Merchant payment routes are throttled at 60 requests/minute; guest checkout is throttled at 20 requests/minute using the private generation's cache.

The existing services persist payment intent, dispatch attempt, authenticated mock event and eligible confirmation. Posting uses the existing ledger consumer: one immutable sealed journal with a debit to simulated clearing receivable and a credit to merchant pending obligation. Reconciliation reads the original accounting outcome. Mock confirmation does not prove settlement; operational withdrawable funds remain TZS 0.

Merchant transaction visibility uses the existing `MerchantWallet` projection, including persisted references, payment states and accounting status. It does not substitute dashboard fixtures for financial records. Existing ordinary operational financial routes remain denied.

## Corrections found during verification

- Added explicit staging payment/checkout throttles, which were absent at the interruption point.
- Corrected the read-only custody status helper to count the approved `funds_withdrawals` table. The interrupted helper used the nonexistent `withdrawal_requests` name. No schema or financial-service contract was changed for this correction.
- Corrected the new acceptance test's signature-denial expectation to the existing HTTP 401 contract. The provider had correctly rejected the forged signature.
- Increased the local onboarding HTTP test's bounded request timeout from 15 to 60 seconds after a timed-out generation was retired and retained. No uncertain financial request was blindly redispatched.
- The installed agent-output package suppressed PHPUnit progress even with `--debug`. Diagnostic attempts were retained; the final comprehensive run disables that output package only in its process environment and uses the same isolated test bootstrap.
- The original throttle probe reached an expired customer grant. Its completed payment/ledger/security evidence was retained, and `Test-Payments.cjs --finish` resumed only review/widget/throttle operations under a fresh customer grant. It did not dispatch a payment or post a journal. The harness now understands PowerShell UTF-16 logs and uses an unexpired customer grant for future throttle probes.

## Verification evidence

Counts below describe separate runs and must not be added together as unique tests.

| Verification | Result / evidence |
| --- | --- |
| Recovered pre-interruption payment baseline | 171 tests / 7,799 assertions PASS; `.recovery/r24-existing-services-baseline.log` |
| Fresh focused staging/payment/checkout/ledger run | 190 tests / 5,427 assertions PASS; `.recovery/r24-focused-resume.log` |
| Established protected verifier | PASS: 29 frozen pins, 3 manifest digests, 4 INT-05 pins, 4 isolation guards, 282 PHP syntax checks, 15 non-mutating formatting checks, 33 functional tests / 971 assertions and 546 bounded regressions / 4,910 assertions; source preservation; `.recovery/r24-protected-verifier.log` |
| Final comprehensive regression | Completed: **1,489 cases: 1,485 passed, 3 failed, 1 intentionally skipped; 0 errors; exit 1**. `.recovery/r24-comprehensive-final.log` and `r24-comprehensive-summary.json`; see exceptions below |
| Final ordinary onboarding HTTP | 57 checks PASS; `.recovery/r24-onboarding-accepted-final.log` and `r24-onboarding-acceptance.json` |
| Final ordinary payment HTTP | 42 recorded checks PASS across the retained core run and its non-dispatching continuation; `.recovery/r24-payments-core-accepted.log`, `r24-payments-accepted-final.log` and `r24-payment-acceptance.json` |
| Isolated responsive browser acceptance | 4 pages / 12 viewport checks PASS at 390×844, 768×1024 and 1440×1000; `.recovery/r24-browser-1795852197239/qa.json` and 12 screenshots; representative mobile and desktop images visually reviewed |

Installed versions were checked locally: PHP 8.5.13, Laravel 13.35.0, PHPUnit 12.5.38 and Pint 1.32.1. No dependency was installed or updated.

The established regressions exercise persistence constraints, immutable records, exact amount/currency/reference binding, cross-tenant denial, revoked/stale authority, nonce/idempotency, link expiry/disable, signature and terminal conflicts, uncertain dispatch/commit outcomes and default operational denial. Process-failure and concurrent-revocation coverage includes service-level regressions; those cases are not all newly fault-injected through ordinary HTTP or a browser.

Ordinary HTTP acceptance verified persisted pending, confirmed, failed and uncertain outcomes; forged provider signature denial (401); duplicate signed-event idempotency; exactly one sealed journal; independent reconciliation; actual transaction visibility; link disable and retained customer history; CSRF and nonce denial; expired/revoked grants; tenant, Chief and Staff denial; operational route denial; and guest throttling. Browser checks cover product management, transaction visibility (including confirmed/posted, failed and unresolved records), QR/button display and fresh checkout. They check keyboard reachability, labels, assets, staging notices and horizontal overflow. Terminal guest checkout history was verified through HTTP, rather than a separate terminal-state browser screenshot. No broad screen-reader audit is claimed.

Final persisted counts are 2 links, 1 checkout attempt, 3 payment intents, 3 applied event records, 1 confirmation, 1 journal, 2 postings and 1 seal. Credits and debits are each TZS 12,500. API credentials, webhook deliveries and withdrawals are all zero. `.recovery/r24-final-financial-status.json` records these results; `r24-final-transport-safety.json` records denied provider/webhook/OTP transports, array-only mail and unbound operational financial/identity services.

### Repository-wide regression exceptions

The comprehensive run is broader than the focused and established accepted verifier; it must not be described as an all-green suite. Its reported exceptions are retained for owner review:

- `B32CActivationRehearsalTest::test_final_schema_equals_independently_run_accepted_migrations` compares the earlier selected activation schema with unrestricted current default migration discovery.
- `B32SyntheticContractTest::test_seven_tables_and_all_foreign_keys_are_enforced` expects 21 tables where default current migrations produce 30.
- One `SobBoundaryTest` process-termination dataset missed its 25-second worker `READY` barrier under concurrent verification load. Its separate unchanged-case rerun passed: 1 test / 4 assertions, `.recovery/r24-custody-timeout-rerun.log`. This does not erase its failure in the comprehensive run.

The two schema failures reproduce with the five preserved pre-amendment payment-service definitions: 3 selected tests / 31 assertions, 2 failures, `.recovery/r24-pre-amendment-legacy-reproduction.log`. Their test files and migrations are unchanged from `12dddbe`. Existing contracts and tests were not relaxed to manufacture a green result. Repairing those older fixture expectations is left as an explicit review item; no operational migration or financial guard was changed for them.

The one skip is `SafeBrowserDemoExportTest::test_export_safe_browser_demo`, which requires an explicitly owned static-demo export directory. No export directory was supplied in the comprehensive run. R2.4's actual ordinary-web browser verification ran separately and passed; the skipped exporter is not counted as a pass.

## Protected state and operational limits

The resumption preservation inventory captures the operational `backend/database/database.sqlite`, environment files, activation sources/manifests and existing payment services without exposing credential values. Of 42 captured paths, 37 remain hash-identical; the only five differences are the approved payment amendments and their formatting. Frozen manifests and contracts passed independent verification; the accepted financial schema hash equals the pre-R2.4 snapshot. The final preservation check is recorded in `.recovery/r24-preservation-check.json`.

All application writes for acceptance target generation-owned workflow, financial or admission stores beneath `scripts/.staging/`, or isolated test databases. Staging uses private session/cache/view storage, an absent environment-file location, array-only mail and a null queue. External HTTP middleware and PHP transport restrictions deny real provider, webhook and SMS communication. Only reserved fictional contact/identity input is admitted by the existing onboarding boundary.

Browser verification uses an owned headless Chromium process with a fresh temporary profile and ephemeral loopback CDP. Exact-origin interception permits GET/HEAD and login/logout POST only. It does not attach to the owner's browser, use their profile or submit financial writes. Private handoff files remain inside the ACL-protected disposable generation; credentials and session cookies are not included in this report or browser QA output.

The accepted runtime was `run-f02c81bc41e87af521aa406ba21161f3`. It was stopped and retired after acceptance, with stores and evidence retained; see `.recovery/r24-final-retirement.log`. Earlier diagnostic generations were also retained. No generation was destructively reset, re-signed after retirement or reopened as an operational store.

Operational security readiness, Selcom readiness and production go-live remain **NO-GO**. This milestone supplies provider-independent fictional integration evidence only. XLipa Store remains excluded, post-launch Phase 2. R2.5, settlement, withdrawals, operational API/webhook activation and live providers require separate owner authorization.

## Review and reproduction

Use `scripts/staging.ps1 -Operation Start -Profile payments` for a new owned generation, then run `Test-Onboarding.cjs`, `Test-Payments.cjs` and `Test-Payments-Browser.cjs` with the installed Node runtime. Use `financial-control.php <runtime> grant <fictional-owner-email> <action> [resource-id]` for exact review grants; use the existing onboarding custodian for its separately admitted operations. Browser registration remains disabled; fictional account creation is the disclosed existing-service CLI provisioning alternative.

Never edit a signed manifest, re-sign a retired store, overwrite an unresolved command or change signed source beneath a live generation. Use the owned Stop operation to retire it, retaining evidence, and Start for a fresh generation. Test counts and disposable data are not an operational activation approval.

**Project progress: approximately 68%, from the accepted 65% baseline (+3 percentage points).** This provisional estimate credits the verified fictional product/checkout/confirmation/accounting/transaction integration. It does not credit test or document counts, imply resolution of the inherited full-suite exceptions, or represent a weighted readiness denominator. Operational security, Selcom and production readiness remain NO-GO without invented readiness percentages.

The remaining owner-review items are the disclosed repository-wide regression exceptions and authorization of any next milestone. R2.5 has not started. No commit, push or deployment was performed.

**STOP FOR OWNER REVIEW.**
