# XLIPA P5 functional financial ledger

2026-11-19. **PASS for isolated P5 functional double-entry mock-payment accounting.**

## A. Starting repository state

Branch `main`, HEAD `cd0f1cd8970c39b1b178211a31a632d9e2927396`, empty index. Preserved three existing modified paths: the November 15 P4 report, `backend/app/Actions/Payments/PaymentEngine.php` and `backend/tests/Feature/FunctionalMockPaymentTest.php`; also preserved the untracked November 19 P4 report. No P5 work existed. Read backend AGENTS.md, latest P4 report, payment implementation/provider/store/confirmation schema, shared merchant journey, authorization, frozen contracts/manifests and P0 tooling. No `.ai/rules` directory exists; the referenced testing-best-practices skill is unavailable. Installed metadata: Laravel 13.35.0, PHPUnit 12.5.38, Pint 1.32.1; execution uses existing PHP 8.5.12. No dependency installation.

Before ledger edits, ran the two previously affected P2 test groups sequentially twice using the established guarded bootstrap: both passed **12 tests / 154 assertions / exit 0**, in **3,494 ms** and **4,573 ms**. The prior failures did not reproduce within this bounded check; their cause remains unestablished. No P2 restriction was changed.

## B. Exact files changed

Paths are relative to `backend`; this report is the only new document.

| File | Work |
|---|---|
| `app/Actions/Payments/LedgerAdmission.php` | New independent default-denied accounting admission and unavailable operational clock |
| `app/Actions/Payments/MockPaymentLedger.php` | New actual confirmation consumer, double-entry writer, reconciliation and financial reads |
| `app/Actions/Payments/PaymentEngine.php` | Add internal same-store accounting transaction under current P4 authority, with final authority recheck; retain recovered ownership fix |
| `tests/Support/LedgerStoreFixture.php` | New guarded additive financial fixture schema and database invariants |
| `tests/Support/FakeLedgerAdmission.php` | New scoped fictional grants and deterministic clock |
| `tests/Support/LedgerHttpHarness.php` | New explicitly registered, guarded test-only accounting/read boundary |
| `tests/Support/MockPaymentJourney.php` | Extract the existing P4 journey/helpers without replacing business operations |
| `tests/Feature/FunctionalMockPaymentTest.php` | Reuse extracted journey; retain all recovered P4 tests and six ownership cases |
| `tests/Feature/FunctionalFinancialLedgerTest.php` | New full-journey, generated accounting, authorization, constraint, rollback, contention and uncertainty tests |

## C. Accounts and journal structure

Four additive tables exist only in the explicitly owned P4 fixture store: `ledger_accounts`, `ledger_journals`, `ledger_postings`, `ledger_seals`. Each merchant has a TZS simulated clearing receivable (asset, debit) and merchant pending obligation (liability, credit). A confirmed amount produces one journal with two equal positive integer postings. This deliberately narrow accounting model provides no settlement, fee, payout or reversal operation. Corrections would require separately admitted new evidence; editing history is prohibited. No universal nonnegative-balance rule is asserted for other accounting types.

Amounts reuse P4's exact whole-TZS range 1–1,000,000,000,000. Application validation rejects floats, numeric strings, booleans, null, nonpositive amounts, overflow and non-TZS currency. Checked integer addition prevents cumulative totals becoming floats.

Database constraints bind each posting to its journal's merchant/currency and its account's merchant/currency/kind. A seal trigger requires exactly two correctly directed postings with debit total = credit total = source amount. A deferred journal-to-seal foreign key prevents incomplete journals committing. Update/delete/replacement triggers preserve accounts, journals, postings and seals; no posting can be appended to a sealed journal.

## D. P4 confirmation integration

The actual shared P1–P4 HTTP journey registers and verifies a merchant, completes its name, submits its application, obtains Chief approval, grants separate fictional payment eligibility, creates an intent and authenticates the mock provider's confirmation. That same merchant/payment/confirmation then reaches the real P5 writer and authorized financial read. Successful business operations are not mocked.

P5 requires independent ledger admission plus current P4 owner/session/account/membership/application authority. It joins immutable confirmation, intent and applied confirmed-event evidence; verifies merchant, amount, currency, provider, event lineage and timestamps; checks retained raw-body hash/canonical facts while present; and binds a durable source fingerprint. Immutable accepted P4 event facts remain authoritative after permitted raw-body expiry. Client claims must match stored evidence and cannot create confirmation authority.

## E. Double-entry evidence

The complete 12,500 TZS journey asserts an exact 12,500 debit to simulated clearing and 12,500 credit to merchant pending obligation. Seven real P4 confirmation scenarios cover amounts **1, 2, 19, 12,500, 999,999, 1,000,000,000,000 and 500,000,000,001**; each journal independently satisfies exact integer debit = credit = source. Totals are **1,500,001,012,522 TZS** per side. Dedicated arithmetic-boundary assertions accept `PHP_INT_MAX` as an exact aggregate result and reject adding beyond it; a single payment remains bounded by P4's smaller limit.

Direct SQL attempts reject missing credit, missing seal, imbalance, zero/negative/fractional/overflow amounts, non-TZS currency, invalid account relationships/types/directions, foreign journal ownership, wrong source and duplicate source. Adversarial generated-store cases reject changed actor/auth/membership/authority/application/provider/merchant bindings and altered confirmation amounts/event hashes. Pending, failed, unknown and unauthenticated payments produce no eligible accounting source.

## F. Idempotency, concurrency and uncertainty

The journal is the durable consumption receipt, unique by fixed consumer + confirmation and by payment reference. Repeated identical consumption returns the original journal; conflicting payment/amount claims are rejected. No mutable processed flag or dashboard counter controls accounting.

Consumption, source validation, account creation, both postings and seal share P4's exact `PaymentStore`/PDO `BEGIN IMMEDIATE` transaction. No second ledger database or distributed transaction is introduced. Authority remains in its existing distinct reserved store; authority commit acknowledgement loss can return unresolved after the ledger has committed. Tests explicitly reconcile that actual ledger outcome rather than assuming rollback across stores.

Failures before journal, during credit, before seal and after seal roll back all ledger effects. Final grant loss and membership revocation also roll back. Pre/post-payment-store commit acknowledgement loss returns unresolved and quarantines that store; repeated requests cause no additional commit attempt. A trusted reopened fixture explicitly reconciles posted/not-recorded, then an explicit request returns the original receipt or posts once. Reconciliation itself never posts. Two owned connections contend on a disposable generated fixture, then reuse one durable journal; conflicting claims remain denied. These bounded tests make no production multi-process/load or crash-durability claim.

## G. Financial read model

Authorized reads derive confirmed fictional payment value from verified P4 confirmations and pending obligations/simulated clearing from actual sealed ledger postings. They distinguish confirmed-but-unconsumed value from posted obligations and retain historical journal identities/timestamps after raw event-body expiry. Tenant isolation and current authorization apply to posting, reconciliation and reads. Responses omit raw bodies, signatures and source fingerprints and use private/no-store caching.

Withdrawable TZS is **0** and settlement is **unproven**. Merchant operational attributes and the authority catalogue remain unchanged. No real cash, fees, fee revenue, settlement, withdrawable funds, financial activation or withdrawal functionality is created. Fee calculation remains a separate unprovisioned capability.

## H. Actual verification results

All PHP tests explicitly use the isolated configuration/bootstrap, generated fixtures and cleared prepend/append hooks; URL access and provider socket/cURL functions are disabled. Commands run from `XLipa/backend` with the existing Herd PHP CLI, requiring sandbox escalation for runtime access.

| Final run | Tests | Assertions | Duration ms | Exit |
|---|---:|---:|---:|---:|
| P5 `FunctionalFinancialLedgerTest.php` | 50 | 2,451 | 135,680 | 0 |
| P4 `FunctionalMockPaymentTest.php` | 37 | 1,705 | 112,544 | 0 |
| Preserved P1/P2/P3/service/authorization selection | 176 | 4,506 | 272,708 | 0 |
| P0 onboarding selection | 33 | 964 | 61,535 | 0 |
| P0 bounded regression | 546 | 4,824 | 154,630 | 0 |

Counts overlap and are not summed. The two pre-P5 anomaly runs are recorded in section A. An initial P5 development run passed 32/34 tests; two test setup/expectation issues were corrected (invalid-session follow-up is unauthenticated; ordinary-app bootstrap must follow fixture assertions). The complete final P5 run above passed without weakening implementation or isolation guards. Focused database/contention/authority-uncertainty and corrupted-source/late-revocation/arithmetic runs also passed before the final full suite.

All nine scoped PHP files passed syntax, exit 0. Final scoped `vendor/bin/pint --test --no-config --format agent --cache-file <unique-temp-cache>` passed, exit 0. P0 independently passed syntax for **208 PHP files** and non-mutating Pint for its **15 files**. Style text was obtained through Pint stdin and applied as scoped patches; the final checks did not modify sources.

Test runner: `vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --bootstrap tests/bootstrap-isolated.php --no-progress --do-not-cache-result <test files>`. Preserved selection: `FunctionalChiefAdminDecisionTest`, `FunctionalMerchantApplicationSubmissionTest`, `FunctionalMerchantOnboardingTest`, `ApplicationSubmissionServiceTest`, `AdminApplicationReviewTest`, `MerchantAccessTest`. P0 entry point: `powershell.exe -NoProfile -ExecutionPolicy Bypass -File XLipa/scripts/verify-isolated.ps1 -PhpPath C:/Users/hp/.config/herd/bin/php85/php.exe` from the shared root.

The unchanged P0 verifier returned **exit 0**, ending `PASS isolated verification and source preservation`. Final P5, P4, preserved-selection and P0 test processes ran sequentially. No final run failed.

## I. Preservation evidence

Recovery inventory: **364** Git-selected existing source/report/configuration/metadata/template paths, excluding environment/credential/key files, databases/sidecars, storage and dependencies. Final comparison: **362 unchanged, two intentionally changed, zero missing**; changes are exactly the existing payment engine extension and P4 test extraction. Seven new PHP files plus this report are the only P5 additions (**372 paths total**). Both recovered P4 reports and the pre-existing ownership correction remain preserved. P0 verified **29 frozen source/artifact pins, three historical manifest digests, four INT-05 hashes and four isolation-guard hashes**, and independently passed source preservation. Git whitespace checks passed, exit 0; the index remains empty.

No working `database.sqlite` or sidecars, real `.env`, operational APP_KEY, credentials or private keys were opened, copied, hashed, queried or migrated. No frozen SQL/migration/manifest, operational route, binding, configuration, dependency or P0 verifier was changed. No reset, revert, deletion of repository work, commit, push or deployment occurred.

## J. Remaining blockers

No isolated P5 acceptance blocker remains. Operational accounting admission, production financial schema/recovery/restore governance, actual settlement evidence and pricing remain unprovisioned. The additive schema installer is test-only; no operational ledger activation is supplied. No live provider or production recovery claim follows from fixture tests. The historic P2 anomaly remains unexplained, but did not reproduce in the bounded precheck or final preserved selection.

## K. Final classification

**PASS for isolated P5 functionality.** Actual P4 confirmations produce balanced, immutable, source-idempotent journals through the real ledger implementation. Atomic rollback, conservative uncertainty, explicit reconciliation, bounded competing consumers, tenant authorization and historical financial reads have passing coverage. Required P5/P4/preserved-functionality/bounded/P0/syntax/non-mutating Pint/frozen-preservation checks passed. This is fictional pending accounting, with no real funds or operational activation.

B3.2-C and SE-1.0 remain **NO-GO**. Operational Business Name Completion, public application submission, operational Chief Admin decisions, live OTP/NIN, Selcom and live payments remain **DISABLED**. Working database migration is **NOT AUTHORIZED**. No operational financial postings, real merchant credit, live settlement or withdrawals. No P6 work was started.

**STOP FOR OWNER REVIEW.**
