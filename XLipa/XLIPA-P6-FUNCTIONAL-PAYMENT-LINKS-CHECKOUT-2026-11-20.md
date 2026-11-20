# XLIPA P6 functional payment links and checkout

2026-11-20. **PASS for isolated P6 functional payment links and checkout.**

## Recovered state

Branch `main`, HEAD `ec4ae19ca2e574ef6314863f1930e226ec6cb249` (`p6`), clean working tree and empty index. P6 was already implemented in that commit; it was resumed, not recreated. Read backend `AGENTS.md`, the November 19 P5 report, actual P1-P5 journey helpers, P4 engine/provider/store, P5 ledger and existing P6 implementation/tests. No `.ai/rules` directory or available testing-best-practices skill. Installed metadata: Laravel 13.35.0, PHPUnit 12.5.38, Pint 1.32.1. No dependency installation.

Recovered P6 files, all retained: `backend/app/Actions/Payments/{PaymentLinkAdmission,PaymentLinkService}.php`; the checkout extension in `PaymentEngine.php`; `backend/tests/Support/{FakePaymentLinkAdmission,PaymentLinkHttpHarness,PaymentLinkStoreFixture}.php`; `backend/tests/Feature/FunctionalPaymentLinkCheckoutTest.php`; `backend/resources/views/merchants/payment-links.blade.php`; `backend/resources/views/payments/checkout.blade.php`. Checkout CSS/JS existed only in ignored `backend/public/assets`, without tracked frontend source.

## Exact changes in this resumption

| Path relative to XLipa | Change |
|---|---|
| `backend/app/Actions/Payments/PaymentLinkService.php` | Scoped SQL string style correction; behavior unchanged |
| `backend/tests/Support/PaymentLinkHttpHarness.php` | Rotate the merchant form creation key after acknowledged success, retaining duplicate-request idempotency |
| `backend/tests/Feature/FunctionalPaymentLinkCheckoutTest.php` | Refresh completed merchant fixture; add repeated form use, checkout reservation uncertainty and accounting reconciliation coverage |
| `frontend/assets/xlipa-checkout.css` | Preserve recovered responsive/accessibility styles as reproducible source |
| `frontend/assets/xlipa-checkout.js` | Preserve recovered submit-progress/double-submit feedback as reproducible source |
| This report | New requested completion record |

## Functionality and authority

Merchant-owned links use 256-bit random public tokens, positive integer whole TZS amounts up to P4's existing limit, bounded UTF-8 descriptions, creation idempotency keys, active/disabled state and optional expiry. Tenant-authorized management includes history and disable. Descriptions and public business names use escaped Blade rendering. Creation keys rotate only after success; a duplicate old request still returns its original link without clearing a newer form key. Existing `Sync-Frontend.ps1` can copy the added frontend assets; no broad sync or template overwrite was performed.

Checkout uses guarded test-only HTTP routes with guest sessions, CSRF, a per-link nonce, exact input allowlists and private/no-store responses. Attempt access binds link identity and hashed customer session; guests cannot supply merchant, amount, currency, terminal status or provider result. Merchant sessions cannot become checkout customers implicitly. Pending, failed, confirmed, unknown and unresolved messages distinguish evidence from settlement. Historical customer status remains available after disable/expiry; new attempts are denied.

The recovered P4 delegation never logs in the owner or installs owner authentication/session state. It is restricted to CLI synthetic testing and separate explicit customer admission. It derives immutable amount/currency/merchant/owner/application/membership from the stored link and reserved attempt, verifies the customer's session hash, and checks current P4 owner/account/email/application/submission evidence and separate payment admission. Association triggers bind the intent to the exact link amount, tenant, authority and attempt idempotency key. Ordinary P4 owner authorization remains in place. No frozen contract change or operational guest capability is introduced.

## Real P1-P6 demonstration and accounting

The same fictional merchant registers, verifies email through the actual signed link, completes its business name, submits its application and obtains Chief approval through existing P1-P3 HTTP helpers. Separate fictional payment admission then permits link creation. A separate guest opens checkout and initiates the actual P4 engine/provider payment. Trusted fictional provider events use actual authenticity verification; customer success claims cannot confirm payments. The merchant subsequently invokes the real P5 consumer through the protected HTTP boundary.

The 12,500 TZS acceptance journey verifies one intent, its exact checkout association, authenticated confirmation, one sealed journal and two postings of 12,500 TZS each. Duplicate consume/reconcile returns the same receipt. Authorized merchant history shows the payment status and journal; financial reads show pending obligation 12,500 TZS, withdrawable TZS 0 and settlement unproven. Merchant operational attributes remain unchanged. Ledger consumption remains an explicit authorized merchant operation, never a guest accounting grant.

Security cases cover tampered fixed claims, wrong nonce/CSRF, foreign merchants/customers, revoked membership, wrong authenticity proof, mismatched provider correlation, duplicate callbacks and terminal conflicts. Pending/failed/unknown payments cannot post. SQL attempts cannot rewrite link identity or checkout association history.

Added before/after-commit acknowledgement-loss cases verify quarantined stores reject repeated work. Reservation uncertainty never dispatches while quarantined; a durable reservation without an intent remains unresolved after trusted reopening and never automatically retries payment creation. If no reservation committed, an explicit later request can create once. Accounting uncertainty reconciles the actual posted/not-recorded outcome without posting; an explicit consume then reuses or creates exactly one journal and two postings. Tests use real business operations; only fictional admission, failure injection and notification transport are controlled.

## Executed verification

All PHP suites use the existing Herd PHP CLI with cleared prepend/append hooks, URL access disabled and provider socket/cURL functions disabled. PHPUnit uses `--configuration phpunit.xml --bootstrap tests/bootstrap-isolated.php --no-progress --do-not-cache-result`. Only generated test-owned databases are used. Final suites run sequentially.

| Final check | Tests | Assertions | Duration ms | Exit |
|---|---:|---:|---:|---:|
| P6 `FunctionalPaymentLinkCheckoutTest` | 18 | 1,031 | 57,572 | 0 |
| P5 ledger | 50 | 2,451 | 168,938 | 0 |
| P4 payment | 37 | 1,705 | 109,972 | 0 |
| Preserved P1-P3/service/authorization selection | 176 | 4,506 | 219,239 | 0 |
| P0 onboarding selection | 33 | 964 | 61,093 | 0 |
| P0 bounded regression | 546 | 4,824 | 187,349 | 0 |

Counts overlap and must not be summed. Preserved selection: `FunctionalChiefAdminDecisionTest`, `FunctionalMerchantApplicationSubmissionTest`, `FunctionalMerchantOnboardingTest`, `ApplicationSubmissionServiceTest`, `AdminApplicationReviewTest`, `MerchantAccessTest`. P0 entry point: unchanged `scripts/verify-isolated.ps1 -PhpPath C:/Users/hp/.config/herd/bin/php85/php.exe`.

P0 finished `PASS isolated verification and source preservation`, exit 0. Its syntax check passed all **214 PHP files**, and its non-mutating Pint passed **15 files**. Final scoped P6 `vendor/bin/pint --test --no-config --format agent --cache-file <temporary-cache>` passed **seven PHP files**, exit 0. Pint stdin was used only to inspect formatted text; final style checks did not mutate source. JavaScript syntax and final Git whitespace checks passed, exit 0.

Development runs: sandbox runtime lookup failed before escalation. Two early overlapping P6 runs each passed 17/18 cases, exposing a stale pre-completion merchant-model assertion and a separate view 503; the latter's cause is unestablished. Refreshed the fixture and reran sequentially: the final 18/18 result above passed. Initial scoped Pint reported style issues; formatting was inspected through stdin and scoped edits applied. No passing result is inferred from the early runs. Checkout source/public asset hashes match. `node --check frontend/assets/xlipa-checkout.js` passed with exit 0 using the existing runtime after sandbox escalation; no browser-rendering claim is made.

## Preservation and remaining scope

P0 verified **29 frozen pins, three manifest digests, four INT-05 pins and four isolation guards**, and its final source-preservation comparison passed. Against the clean recovered HEAD, the only tracked modifications are the three scoped PHP files listed above; the three new files are the two frontend assets and this report. The index remains empty. No real environment file, operational APP_KEY, credentials, private keys, working SQLite database or sidecars were opened/copied/hashed/queried/migrated. No protected migration/manifest, frozen contract, operational route/binding/configuration, dependency or P0 verifier was changed. No reset, revert, deletion of repository work, commit, push or deployment. Existing ignored public assets were preserved byte-for-byte.

Operational link/payment admission, deployment, real provider integration, settlement, fees and withdrawals remain outside this isolated milestone. A committed reservation with no intent intentionally requires review; no automatic redispatch is supplied. No production recovery, concurrency/load or live settlement claim follows from fixture tests.

**Final classification: PASS for isolated P6 functionality.** No isolated acceptance blocker remains. All ten requested definition-of-done conditions have executed passing coverage, including actual HTTP initiation, real P4 confirmation, real P5 accounting, authorized merchant reads, duplicate protection and disabled operational checkout. Production provisioning is not supplied by this milestone.

B3.2-C and SE-1.0 remain **NO-GO**. Operational Business Name Completion, public merchant application submission, Chief Admin decision writes, Selcom, live OTP/NIN and live payments remain **DISABLED**. No working database migration or operational financial activation. Withdrawable TZS remains **0**. No P7 work.

**STOP FOR OWNER REVIEW.**
