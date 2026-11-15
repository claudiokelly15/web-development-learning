# XLIPA P4 provider-neutral mock payment engine

2026-11-15. Isolated implementation. Final regression and preservation checks are in progress.

## A. Starting repository state

Recovered shared Git root `C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT`, branch `main`, HEAD `b670fdb12aa65e64e977bb2b677c392028fc0bf2`. The existing P3 service/test/helper modifications and untracked P3 files/report were preserved. The index was empty. Read backend AGENTS.md, P3 completion evidence, payment-related source, schema/profile checks, authorization, transaction helpers and P0 tooling. No applicable `.ai/rules` directory or equivalent Laravel payment/wallet implementation was found. The referenced testing-best-practices skill remains unavailable.

## B. Exact files created or modified

New files relative to `backend` follow.

- `app/Actions/Payments/PaymentProvider.php`
- `app/Actions/Payments/FictionalMockProvider.php`
- `app/Actions/Payments/PaymentAdmission.php`
- `app/Actions/Payments/PaymentEngine.php`
- `app/Actions/Payments/PaymentStore.php`
- `app/Actions/Payments/PaymentOutcomeUnknown.php`
- `tests/Support/PaymentStoreFixture.php`
- `tests/Support/FakePaymentAdmission.php`
- `tests/Support/PaymentHttpHarness.php`
- `tests/Support/MerchantDecisionJourney.php`
- `tests/Feature/FunctionalMockPaymentTest.php`

The sole existing-file modification is `backend/tests/Feature/FunctionalChiefAdminDecisionTest.php`, extracting its existing journey helpers into `MerchantDecisionJourney` and updating imports. Existing P3 test bodies remain present. This report is the only new document. Existing business services, routes/controllers, provider bindings, operational configuration, SQL, migrations and manifests retain their bytes.

## C. Provider-neutral contract

`PaymentProvider` supports provider identity/capabilities, create, query and authenticated event normalization. The engine consumes normalized internal outcomes and generic references. Refund/payout operations are absent and reported unsupported by the mock. No Selcom class, format, credentials or endpoint is introduced.

Amounts are exact whole TZS integers in the technical fixture range **1–1,000,000,000,000 TZS**. Currency is exactly TZS. Floats, numeric strings, booleans, null, nonpositive amounts and overflow are rejected. The engine performs no floating-point monetary arithmetic. Order references are optional bounded ASCII identifiers.

## D. Functional fictional provider

`FictionalMockProvider` performs no network I/O and requires CLI, synthetic-only flag and testing mode. It deterministically accepts pending requests, simulates accepted requests with lost acknowledgements, produces deliberately malformed responses for normalization testing, answers status queries and generates signed fixture events. The engine itself is never mocked.

The fictional event protocol uses `X-Fictional-Signature`, lowercase HMAC-SHA256 over the exact canonical JSON body with a public synthetic 32-byte fixture key. Ordered fields are event_id/reference/correlation/amount_tzs/currency/result/sequence/occurred_at. Bodies are bounded to 4,096 bytes. Accepted time skew is five minutes old through 30 seconds future, with events prohibited from predating their payment. Duplicate JSON keys, altered canonical bytes and invalid signatures are denied. This is exclusively the mock protocol.

## E. Payment lifecycle

Intent creation persists pending state before provider dispatch. Dispatch is durably claimed once and marked unknown before the provider call. A valid create acknowledgement returns pending; timeout or malformed response leaves unknown. Repeating the same scoped idempotency key returns the existing payment without another provider create. Changed payload under that key conflicts.

Authenticated events can advance pending/unknown to confirmed or failed. Older/equal sequence events are recorded as out of order. Repeated outcomes create no second confirmation effect. Contradictory terminal outcomes are recorded as conflicts, and terminal-to-pending/unknown notifications remain out of order. Terminal state is preserved. Provider queries return normalized observations alongside the stored state and never manufacture confirmation evidence.

## F. Security and idempotency evidence

Application approval alone is insufficient. A separate default-denied `PaymentAdmission` must grant the actor/merchant/operation. The real engine reserves the existing authority-store writer, verifies the strict BNC profile, current active verified owner/session, approved application and exact immutable approval audit, and checks submission-bound authentication/email/membership/profile epochs. Revoked or stale authority and rejected merchants are denied before provider dispatch.

Tests cover cross-tenant lookup at both HTTP and engine boundaries, bad CSRF/client claims, invalid currency/amounts, signature failures, replayed event IDs with changed bodies, correlation/amount mismatch, stale event timestamps, duplicate requests/events and terminal conflicts. Database uniqueness covers merchant+provider+idempotency, provider correlation and provider event identity. Direct SQL duplicate attempts verify those constraints independently of application checks.

Ordinary application tests find no mock routes and no payment/event grant. Existing operational routes and provider bindings remain unchanged.

## G. End-to-end merchant journey

`FunctionalMockPaymentTest::test_same_p3_approved_merchant_creates_and_confirms_a_payment_without_wallet_credit()` uses the shared real P1/P2/P3 HTTP journey. The same registered/email-verified merchant completes its name, submits through the actual submission service, receives actual Chief approval, obtains separate fictional payment eligibility, creates a persistent intent through the real engine/mock provider, receives a valid signed confirmation and retrieves its confirmed state through the authorized read route.

Assertions verify merchant identity, exact integer amount, stable reference and one confirmation record. The merchant row remains byte-identical. No wallet, balance or ledger table is introduced. Mock confirmation records tested provider lifecycle evidence only. No withdrawable funds, settlement, fee revenue, API keys or payment activation are created.

## H. Database, transactions and P5 boundary

The frozen authority database retains its exact catalogue and BNC profile. Payment DDL is installed only into a **distinct, fresh, test-owned memory PDO**, with no ATTACH or default-connection migration. This deliberate separate-store choice preserves frozen catalogue compatibility. A controlled contention test exports only that generated payment fixture into a newly owned temporary file, opens two connections, exercises a writer conflict and verifies one idempotent intent. Its owned file/sidecars are removed in `finally`.

The additive store contains payment_intents, payment_events and payment_confirmations. Identity/history constraints and append-only evidence triggers protect records. Event processing commits event receipt/outcome, payment state and confirmation evidence in one `BEGIN IMMEDIATE` transaction. Authority checks use the existing authority writer reservation; this supplies no distributed-transaction or production recovery guarantee across stores.

Raw receipt is captured in memory, authenticated before durable storage, then deduplicated and normalized. The durable inbox retains bounded authenticated raw bodies for a fictional 24-hour interval. Authorized expiry clears only raw bodies, retaining immutable hashes, timestamps, event facts and confirmation evidence. Read endpoints expose no raw bodies or signatures. No queue or exactly-once delivery assumption is used.

P5's integration boundary is the immutable confirmation ID, unique per payment, linked to its event and exact merchant/amount/currency. A future ledger consumer must enforce its own unique consumer+confirmation identity in its ledger transaction. P4 contains no consumer, balance update or financial ledger.

Known pre-commit failures roll back all payment/event/confirmation changes. Uncertain store commits quarantine that store and return unresolved, including both potentially persisted and absent outcomes. No create/event retry occurs automatically. Provider acknowledgement loss leaves the claimed request unknown until authenticated event evidence arrives. Fixture PDO inspection distinguishes actual persisted outcomes without presenting them as operational recovery certification.

## I. Actual test results

The existing PHP 8.5.12 CLI ran with approved sandbox escalation and auto-prepend disabled. Installed framework/test tooling remains Laravel 13.35.0, PHPUnit 12.5.38 and Pint 1.32.1. No dependencies were installed.

Commands ran from `XLipa/backend`.

```powershell
$php = 'C:/Users/hp/.config/herd/bin/php85/php.exe'
& $php -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/FunctionalMockPaymentTest.php
& $php -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/FunctionalChiefAdminDecisionTest.php tests/Feature/FunctionalMerchantApplicationSubmissionTest.php tests/Feature/FunctionalMerchantOnboardingTest.php tests/Feature/ApplicationSubmissionServiceTest.php tests/Feature/AdminApplicationReviewTest.php tests/Feature/MerchantAccessTest.php
```

**P4 focused — 30 passed / 1,376 assertions / 25,799 ms, exit 0.** Preserved-functionality result pending capture.

All twelve task PHP files passed scoped syntax checks. Required scoped dirty/explicit formatting and final non-mutating `vendor/bin/pint --test --no-config --format agent` checks passed. The P0 entry point supplies final whole-scope syntax verification after the last edits.

From the shared workspace root, the established verifier command is as follows.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File XLipa/scripts/verify-isolated.ps1 -PhpPath C:/Users/hp/.config/herd/bin/php85/php.exe
```

P0/bounded results pending capture. Overlapping run totals must not be added together. Initial development runs exposed a PHPUnit helper-name collision, object-identity comparison in the catalogue assertion, authorization exception status mapping and Windows temporary-file/statement-handle cleanup. These were corrected within the new test/tooling scope. Intermediate results do not substitute for final evidence.

## J. Preservation evidence

Final inventory and protected-pin verification pending capture. Recovery captured 336 existing source/report/configuration/metadata/template paths, excluding operational environment/credential/key files, databases/sidecars, storage and dependencies. The intended sole existing change is the P3 test's shared-helper extraction. Prior P3 uncommitted work and all existing reports/tooling remain preserved.

No working database, sidecars, real `.env`, operational APP_KEY, private keys or provider credentials were opened, copied, hashed, queried or migrated. No repository reset, commit, push or deployment occurred.

## K. Remaining blockers

Final local verification is in progress. Operational payment admission, real providers, production webhook authentication, real retention policy, authoritative restore and durable provider-command recovery remain unprovisioned. The mock accepts only isolated testing. The two-connection test establishes controlled fixture contention behavior, with no production multi-process/load or crash durability claim. P5 ledger work remains separate.

## L. Final assessment

Final assessment pending verification capture.

**STOP FOR OWNER REVIEW.** B3.2-C and SE-1.0 operational activation remain NO-GO. Operational Business Name Completion, public submission, operational Chief decisions, live OTP/NIN, Selcom and live payments remain DISABLED. Working database migration remains unauthorized. No P5 work was started.
