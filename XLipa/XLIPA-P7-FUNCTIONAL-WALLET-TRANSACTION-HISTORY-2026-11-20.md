# XLIPA P7 functional wallet and transaction history

2026-11-20. **PASS for isolated P7 functional merchant wallet and transaction history.**

## A. Starting repository state

Branch `main`, HEAD `ec4ae19ca2e574ef6314863f1930e226ec6cb249`, empty index. Preserved the three modified P6 paths (`PaymentLinkService.php`, `FunctionalPaymentLinkCheckoutTest.php`, `PaymentLinkHttpHarness.php`) and three untracked P6 paths (completion report and checkout CSS/JS). No P7 implementation existed. Read backend `AGENTS.md`, the P6 report, dashboard/controller/policy/styles, P4 records/engine/provider/store, P5 financial reads and P6 associations/harnesses. No `.ai/rules` directory or available testing-best-practices skill. Installed metadata: Laravel 13.35.0, PHPUnit 12.5.38, Pint 1.32.1. No dependencies installed.

## B. Exact P7 files changed

Paths are relative to XLipa. Existing P6 changes are not P7 changes.

| Path | Work |
|---|---|
| `backend/app/Actions/Payments/MockPaymentLedger.php` | Extend the existing P5 read model with a same-snapshot financial read and bounded evidence summary; extract the existing source validator without changing posting/reconciliation or the legacy position response |
| `backend/app/Actions/Payments/MerchantWallet.php` | New read-only wallet/history/detail projection over the P4/P5/P6 store |
| `backend/app/ViewModels/MerchantFinancialPresentation.php` | New exact integer TZS formatting, explicit EAT timestamps and truthful labels |
| `backend/resources/views/merchants/dashboard.blade.php` | Owner navigation to isolated financial views only when test routes are registered |
| `backend/resources/views/merchants/financial-layout.blade.php` | New shared financial shell using existing dashboard branding/navigation |
| `backend/resources/views/merchants/wallet.blade.php` | New actual financial summary and payment request states |
| `backend/resources/views/merchants/transactions.blade.php` | New filter form, bounded history and pagination |
| `backend/resources/views/merchants/transaction-detail.blade.php` | New authorized detail with payment/accounting/settlement distinctions |
| `backend/tests/Support/MerchantWalletHttpHarness.php` | New explicitly registered guarded GET-only HTTP boundary; private responses including access denials; test-memory failure diagnostics |
| `backend/tests/Feature/FunctionalMerchantWalletTest.php` | New actual P1-P7, financial, pagination, security, retention and uncertainty coverage |
| `frontend/assets/xlipa-wallet.css` | New scoped responsive styles using existing dashboard variables/components |
| `backend/public/assets/xlipa-wallet.css` | Matching ignored public copy of the authoritative wallet stylesheet |
| This report | New requested completion record |

Classes/tests were scaffolded through Artisan with an explicit synthetic bootstrap and absent test environment file. Only the new wallet CSS was copied into ignored public assets; no broad frontend sync was run.

## C. Wallet functionality

The actual P5 boundary validates authoritative P4 confirmations, retained body hashes/canonical facts where present, durable fingerprints, sealed journals and their two directed postings. It scans sources in keyset batches of 100 and checks source/journal counts so missing evidence cannot silently disappear. P7 reads share the exact reserved store snapshot and current P4/P5 authorization; there is no second ledger, wallet database or mutable balance counter.

Wallet displays confirmed fictional payment value, posted pending merchant obligation, simulated clearing, confirmed-but-unaccounted value, payment request states and unresolved reservations. Unaccounted value is confirmed minus posted, checked nonnegative; clearing and obligation are two sides of the same journals and are never added into available funds. Checked P5 integer arithmetic and SQLite integer aggregates reject overflow instead of returning fractional/rounded money. TZS formatting uses decimal strings, including an exact `PHP_INT_MAX` case. Withdrawable TZS is always 0 and settlement explicitly unproven.

## D. History and detail

History joins actual P4 intents, P6 links/attempts and P5 confirmation/journal evidence. Reserved checkout requests without intents appear as unresolved records with no invented payment reference/provider. Direct P4 payments show no invented link, customer or method. Safe descriptions, requested whole TZS, recorded provider identifier, created/confirmed/posted times, payment/accounting states and journal references are displayed. `unknown` remains the stored domain status and is labelled unresolved. Mock provider identity is explicitly fictional.

Filters support an inclusive EAT calendar date range, payment status and payment-reference prefix. Only scalar allowlisted inputs are accepted. Pages contain 10, 20 or 50 rows; page is bounded to 1-10,000. Ordering uses recorded time plus a unique record identity. SQL parameters bind tenant and filters. Evidence result batches and history pages are bounded, with no per-row lookups: the real 101-confirmation test checks five payment-store read queries for a 10-row history page. Full-source validation remains linear in the merchant's history; this is not a production load/scalability claim.

Details distinguish initiation/reservation, dispatch, pending/confirmed/failed/unresolved payment, posted/not-yet-posted/not-eligible accounting and unproven settlement. Responses omit raw payloads, HMAC signatures, source hashes, customer-session hashes, nonce hashes and authority metadata. Blade escapes descriptions and names. Existing dashboard CSS and the narrow new stylesheet provide desktop/tablet/mobile layouts, labelled filters, keyboard focus, semantic tables and horizontal scrolling on narrow screens. No browser-rendering claim is made.

## E. Actual P4/P5/P6 integration

The acceptance test uses the same fictional registered merchant through signed email verification, business-name completion, actual application submission and Chief approval. Separate payment/link/ledger admissions then permit P6 link creation, separate guest checkout, real P4 mock-provider processing and authenticated confirmation. Duplicate confirmation and consume requests yield one payment and one journal. Actual P5 consumption/reconciliation precedes the P7 wallet/history/detail reads.

The 12,500 TZS journey first shows confirmed-but-unaccounted 12,500 and obligation 0; after posting it shows confirmed 12,500, obligation 12,500, clearing 12,500, unaccounted 0, withdrawable 0 and settlement unproven. Link, checkout, payment and journal identifiers remain associated. Read fingerprints verify no financial or merchant-attribute changes. Pending, failed and unknown payments remain distinct; raw-event body expiry leaves authoritative history unchanged. Read-commit acknowledgement loss returns unresolved without fabricated balances, quarantines repeated access and recovers actual records on a trusted reopened store without reposting. A committed reservation without an intent remains visible and is never dispatched by reading.

## F. Authorization and security evidence

All financial reads require authenticated, verified current owner authority, active account/session/membership, current approved application/submission evidence, separate P4 read admission and current P5 financial-read admission. Existing owner-only permissions remain; manager/staff grants cannot bypass them. Tests cover guest/unverified/inactive accounts, stale session, revoked membership, membership epoch ABA, admission denial and late admission revocation. No operational denied admission control was changed.

Two genuinely approved merchants share the same fixture payment store, have real distinct payment records and demonstrate isolated wallet/history/detail reads even for an owner with access to both. Forged filters/record identities and cross-tenant references are denied. Tampered source/body/seal/fingerprint/posting observations return denial without financial data. Successful reads and denial responses are private/no-store. The test-only HTTP adapter retains unexpected exceptions in test memory, never in response payloads.

## G. Actual verification

PHP uses existing Herd PHP 8.5.12 with cleared prepend/append hooks, URL access disabled and provider socket/cURL functions disabled. Suites use `vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --bootstrap tests/bootstrap-isolated.php --no-progress --do-not-cache-result`. Only test-owned generated databases are used. Final suites run sequentially; overlapping counts are not summed.

| Final run | Tests | Assertions | Duration ms | Exit |
|---|---:|---:|---:|---:|
| P7 focused | 29 | 1,962 | 130,957 | 0 |
| P6 checkout | 18 | 1,031 | 48,043 | 0 |
| P5 ledger | 50 | 2,451 | 125,753 | 0 |
| P4 mock payment | 37 | 1,705 | 73,359 | 0 |
| Preserved onboarding/service/authorization selection | 176 | 4,506 | 198,936 | 0 |
| P0 onboarding | 33 | 964 | 38,115 | 0 |
| P0 bounded regression | 546 | 4,824 | 162,474 | 0 |

Preserved selection: `FunctionalChiefAdminDecisionTest`, `FunctionalMerchantApplicationSubmissionTest`, `FunctionalMerchantOnboardingTest`, `ApplicationSubmissionServiceTest`, `AdminApplicationReviewTest`, `MerchantAccessTest`. P0 uses the unchanged `scripts/verify-isolated.ps1 -PhpPath C:/Users/hp/.config/herd/bin/php85/php.exe`. Final non-mutating scoped Pint passed five P7 PHP files, exit 0. Pint stdin supplied formatting text for the new test; Pint did not mutate repository source itself. P0 syntax passed 218 PHP files, and its non-mutating Pint passed 15 files, both exit 0. The unchanged P0 verifier finished `PASS isolated verification and source preservation`, exit 0. New wallet CSS and preserved P6 CSS/JS match their public copies; Git whitespace checks passed, exit 0.

Development evidence: initial P7 run: 24 tests, 17 passed, 1,552 assertions, 45,304 ms, exit 1, with two failures and five fixture errors; the frozen SE schema correctly rejected unsupported user/epoch mutations. Fixtures now use supported membership transitions, inactive/unverified factory accounts and saved stale session cookies. A second run passed 23/24 (1,598 assertions, 64,502 ms, exit 1); corrected the stale-cookie test setup while retaining its 302 denial expectation. Focused security/two-tenant/corruption run passed 18 tests / 901 assertions / 35,637 ms / exit 0. These counts are not added to final runs.

The P6 historical HTTP 503 remains an unresolved observation. P7's `FunctionalMerchantWalletTest::test_ordinary_runtime_retains_unavailable_financial_navigation_and_no_p7_routes` separately reproduced HTTP 503 (1 test, 80 assertions, 5,115 ms, exit 1), with a `Service Unavailable` HTML response. A diagnostic run with exception handling disabled (1 test, 79 assertions, 2,868 ms, exit 1) captured `EnsureAuthenticationReady.php:18`: `Account access is temporarily unavailable. Please try again later.` The test had booted a second fresh Laravel application, lacking fixture schema, before issuing a request. HTTP assertions now precede that independent bootstrap. This establishes this P7 test's cause, not the historic P6 cause; no concurrency explanation is asserted and no readiness guard was weakened.

## H. Preservation verification

A pre-edit inventory hashed 257 selected existing source/config/test/frontend/script paths, including all six recovered P6 paths; no environment/credential/database files were included. Comparison: **255 unchanged, two intentionally changed, zero missing**. The changes are exactly the existing ledger read-model extension and conditional dashboard navigation; all six recovered P6 paths remain byte-identical to the starting state. P0 verified **29 frozen pins, three manifest digests, four INT-05 pins and four isolation guards**, and its final source-preservation comparison passed, exit 0. No frozen source or manifest was edited. The index remains empty. Ordinary route/configuration/provider/admission restrictions remain unchanged. No real `.env`, operational APP_KEY, credentials/private keys, working SQLite database or sidecars were opened/copied/hashed/queried/migrated. No reset, revert, clean, repository-work deletion, commit, push or deployment.

## I. Remaining blockers

No isolated P7 acceptance blocker remains. All required final suites and preservation checks passed. Operational financial admission, actual settlement evidence, live providers, fees, withdrawals, funds movement and production recovery/load governance remain unprovisioned. Reads do not resolve or redispatch P6's durable reservations without intents. No production activation follows from fictional fixture accounting.

## J. Final classification

**PASS for isolated P7 functionality.** The merchant wallet reads actual ledger-derived figures; history/detail reflect actual P4/P6 records and P5 evidence with passing filtering, pagination, current authorization, tenant isolation, duplicate, retention and uncertainty coverage. No available real funds or settlement is inferred. All eleven requested acceptance conditions have passing evidence. B3.2-C and SE-1.0 remain **NO-GO**. Operational Business Name Completion, public application submission, operational Chief Admin writes, live OTP/NIN, Selcom, live payments/webhooks and withdrawals remain **DISABLED**. No real merchant credit, working database migration or P8 work.

**STOP FOR OWNER REVIEW.**
