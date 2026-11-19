# XLIPA P4 provider-neutral mock payment engine

2026-11-15. **PASS for the isolated functional mock payment engine.**

## A. Starting repository state

Resumed shared Git root `C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT`, branch `main`, HEAD `cd0f1cd8970c39b1b178211a31a632d9e2927396`, with a clean working tree, empty index and no untracked work. P4 had already been implemented and committed before this continuation. Its engine, fictional provider, fixture schema, HTTP harness, journey extraction and tests existed; the report still had unfinished verification fields. Continued those files rather than recreating them. The earlier report's `b670fdb...` starting state describes the original P4 development, not this recovery baseline.

Read backend AGENTS.md, the P3 completion report, current P4 draft, payment implementation, schema/profile checks, authorization, transaction helpers, isolation guards and P0 tooling. No additional applicable AGENTS.md or `.ai/rules` was found. The referenced testing-best-practices skill remains unavailable. A fresh 362-file source/report/configuration/template inventory and frozen-pin comparison preceded edits.

## B. Exact files created or modified

The recovered P4 implementation already contained these files, relative to `backend`; all remain present:

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

The P3 test's extraction into `MerchantDecisionJourney` was also already committed. This continuation changed exactly:

| File relative to XLipa | Resumed work |
|---|---|
| `backend/app/Actions/Payments/PaymentEngine.php` | Bind reads, queries and idempotent request reuse to the exact current payment authority |
| `backend/tests/Feature/FunctionalMockPaymentTest.php` | Add six authority-binding regressions |
| `XLIPA-P4-FUNCTIONAL-MOCK-PAYMENT-ENGINE-2026-11-15.md` | Complete this report with fresh evidence |

No new file was needed. P1/P2/P3 services and helpers, operational routes/controllers, provider bindings, admission controls, configuration, SQL, migrations and manifests retain their recovery-baseline bytes.

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

Recovery identified and reproduced one defect: a same-merchant payment with a mismatched actor returned HTTP 200 instead of 404. The engine now checks merchant, provider, actor, auth epoch, membership, authority epoch and approved application before exposing a row, querying it or reusing its idempotency key. Six generated-store cases substitute those bindings independently and verify denial, unchanged payment rows and no extra provider dispatch or confirmation. Ordinary payload conflicts still return 409.

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

Fresh execution used the existing PHP 8.5.12 CLI with approved sandbox escalation. Installed tooling remains Laravel 13.35.0, PHPUnit 12.5.38 and Pint 1.32.1. No dependency was installed. All tests used the established isolated bootstrap and generated SQLite fixtures, with no operational environment or real provider delivery.

Commands ran from `XLipa/backend`.

```powershell
$php = 'C:/Users/hp/.config/herd/bin/php85/php.exe'
$options = @('-d','auto_prepend_file=','-d','auto_append_file=','-d','allow_url_fopen=0','-d','allow_url_include=0','-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,socket_sendto,socket_sendmsg')
& $php @options vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --bootstrap tests/bootstrap-isolated.php --no-progress --do-not-cache-result tests/Feature/FunctionalMockPaymentTest.php
& $php @options vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --bootstrap tests/bootstrap-isolated.php --no-progress --do-not-cache-result tests/Feature/FunctionalChiefAdminDecisionTest.php tests/Feature/FunctionalMerchantApplicationSubmissionTest.php tests/Feature/FunctionalMerchantOnboardingTest.php tests/Feature/ApplicationSubmissionServiceTest.php tests/Feature/AdminApplicationReviewTest.php tests/Feature/MerchantAccessTest.php
```

| Fresh run | Tests passed | Assertions | Runner duration | Exit |
|---|---:|---:|---:|---:|
| P4 actual engine/journey/security suite | 37 | 1,705 | 36,085 ms | 0 |
| P1/P2/P3 functional journeys and submission/Chief-read/merchant-security selection | 176 | 4,506 | 108,460 ms | 0 |
| P0 functional onboarding selection | 33 | 964 | 23,112 ms | 0 |
| P0 established bounded regression | 546 | 4,824 | 94,767 ms | 0 |

All final runs reported zero failures/errors. Counts overlap and are not summed. The new ownership regression first failed with 200 versus expected 404 (one case, 40 assertions), then passed in the full corrected P4 run. Earlier draft counts are historical and superseded by these executions.

All twelve recovered P4 PHP files passed scoped `php -l`. Explicitly non-mutating `vendor/bin/pint --test --no-config --format agent --cache-file <unique-temp-cache> <twelve-task-files>` passed, exit 0. No formatter modified source. P0 independently passed syntax for **201 PHP files** and non-mutating Pint for its **15 files**.

From the shared workspace root, the established verifier command is as follows.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File XLipa/scripts/verify-isolated.ps1 -PhpPath C:/Users/hp/.config/herd/bin/php85/php.exe
```

The unchanged P0 entry point returned **exit 0**, ending with `PASS isolated verification and source preservation`. It verified 29 frozen pins, three historical manifest digests, four INT-05 protected hashes and four isolation-guard hashes before running its syntax, Pint, functional and bounded checks.

## J. Preservation evidence

The fresh continuation inventory covered **362** Git-selected source/report/configuration/metadata/template paths, excluding environment/credential/key files, databases/sidecars, storage and dependencies. Final comparison found **359 unchanged, three intentionally changed and zero missing**; the changed paths are exactly the two PHP files and report in section B. The earlier draft's 336-file inventory was not used as a new preservation result. All recovered P3 and other prior work remains present.

Protected sources, all three manifests, migration identities, existing triggers and the P0 verifier were unchanged. No manifest was regenerated. P0 independently verified its source-preservation inventory. Git whitespace checks passed; index remains empty; no untracked files were added.

Final executable SHA-256 values:

| File under backend | SHA-256 |
|---|---|
| `app/Actions/Payments/PaymentEngine.php` | `c729a0cdbf958414b1d69d3cfd78e19225a8a1dd8856d308dea4e05fca28866a` |
| `tests/Feature/FunctionalMockPaymentTest.php` | `bc5bd70604129b2b48acb2434183f47dc340fec4949b68442948cfd25489dfb3` |

No working database, sidecars, real `.env`, operational APP_KEY, private keys or provider credentials were opened, copied, hashed, queried or migrated. No repository reset, commit, push or deployment occurred.

## K. Remaining blockers

No isolated P4 acceptance blocker remains. Operational payment admission, real providers, production webhook authentication, real retention policy, authoritative restore and durable provider-command recovery remain unprovisioned. The mock accepts only isolated testing. The two-connection and acknowledgement-loss tests establish bounded fixture behavior, with no production multi-process/load, distributed-transaction or crash-durability claim. P5 ledger work remains separate.

## L. Final assessment

**PASS for P4 isolated functional implementation.** The actual engine and fictional provider complete the existing merchant-registration → email-verification → name-completion → submission → Chief-approval journey, require separate mock eligibility, persist an intent, process authenticated provider evidence and return authorized payment status. Pending, failed and unknown outcomes, request/event idempotency, exact ownership, replay/conflict handling, concurrency and ambiguous commits have passing functional coverage. Confirmation creates no wallet credit, withdrawable funds, settlement or fee revenue. No P5 work was implemented.

**STOP FOR OWNER REVIEW.** B3.2-C and SE-1.0 operational activation remain NO-GO. Operational Business Name Completion, public submission, operational Chief decisions, live OTP/NIN, Selcom and live payments remain DISABLED. Working database migration remains unauthorized. No P5 work was started.
