# XLipa P8 and Merchant Analytics — implementation, 21 November 2026

**Analytics: PASS. P8: PASS. Both statuses apply to fictional, isolated implementation and verification only.** Final regression/preservation/browser evidence is recorded below. No operational activation or P9 work.

## Recovered state and preservation

Recovered `main` at `aa6325d` (P7), with 25 modified tracked UI/test/public files and the existing untracked navigation partials, product stylesheet, demo scripts/exporter and UI completion report. Preserved these changes. Read `backend/AGENTS.md`; no `.ai/rules` directory or listed testing-best-practices skill was available. Reused the existing isolated bootstrap, P1–P7 service journeys, payment store, P5 ledger, wallet read models, design system and ownership-checked demo lifecycle. Existing P8 `FundsAdmission`, `FictionalFundsEvidence`, payout interface/provider and empty lifecycle/withdrawal/test scaffolds were recovered; filled those scaffolds rather than creating competing services. No dependencies installed.

The prior platform-wide UI report remains historical evidence. This assignment changes only analytics, the P8 financial extension, their UI integration and verification. No unrelated redesign.

## Analytics implementation

`MerchantAnalytics` records the first payment touch while its payment is pending or unresolved, before confirmation, within a bounded capture window. The immutable snapshot includes normalized source, provenance, permitted sanitized UTM source/campaign or referral-host claims, capture time, source hash and actual checkout Payment Link association. Repeated identical capture is idempotent; conflicting capture and retrospective capture after confirmation are rejected. No full referrer URL, IP, phone prefix, raw session identity, cross-site tracker or payer identifier is collected. Explicit first-party Direct differs from absent Unknown. UTM and referral claims are labelled unverified, including spoofable platform names; an arbitrary source is classified Referral / Other, not verified platform origin. Referral-host matching is exact.

Payment-method evidence uses an independent fictional HMAC domain and immutable, canonical, bounded envelopes. Accepted evidence must match an existing confirmed fictional payment, merchant, confirmation, exact integer amount, currency and timestamp. Synthetic identifiers are explicitly `synthetic_mpesa`, `synthetic_mixx`, `synthetic_airtel`, `synthetic_halo`, `synthetic_bank` and `synthetic_other`. All UI labels say synthetic. Missing evidence remains Unknown. No Selcom network codes or response fields are invented. No methods are inferred from phone numbers.

Reports run under the existing current P4/P5 owner/session/account/membership/application authority. Confirmation facts are revalidated by P5. Reports include successful counts, integer TZS values, source and method comparisons, daily source trends, cross-dimensional totals and scoped transaction links. Pending/failed payments never contribute to confirmed totals. Unique paying customers remain `null` / Unavailable: a checkout session is not a verified customer. Each dimension includes Unknown and reconciles to all confirmed value; `attributed_value_tzs` separately excludes Unknown. Integer basis-point allocation with deterministic largest remainders makes nonempty percentages sum to exactly 100.00%. This denominator is explained in the UI.

Ranges: Today, Yesterday, This Week (Monday start), This Month and inclusive Custom dates, using confirmation timestamps and Africa/Nairobi/EAT calendar boundaries. Invalid dates, reversed/overlong ranges, unknown filters and nonstring input are rejected. Reports use indexed merchant/evidence joins, batches of 100, maximum 10,000 returned confirmation records and 366 days; each source/method/cross bucket includes at most five transaction links. No hard-coded dashboard totals or per-customer assumptions.

## P8 settlement and ledger extension

P8 uses the existing owned `PaymentStore` and P5 `lifecycleTransaction`, with independent default-denied `FundsAdmission`. It does not replace or weaken P5's original source journal/posting/seal contracts. New `ledger_lifecycle_journals`, postings and seals extend that same logical ledger; settlement journals reference original sealed P5 journal identities. The new fixture schema is installed only into the distinct owned in-memory P4/P5 store. No working schema or frozen SQL/migration is modified.

Independent settlement evidence uses the recovered fictional settlement protocol and a key/domain separate from payment confirmation and payout. Merchant, payment, confirmation, exact amount/currency, source journal and timestamps must match. Each source journal and provider settlement transaction can settle only once; an exact event retry returns the original result, while conflicting evidence is rejected. Settlement cannot be created merely by a successful payment confirmation.

Balanced lifecycle entries:

| Event | Debit | Credit |
|---|---|---|
| Independent simulated settlement | Simulated settled cash; merchant pending obligation | Simulated clearing receivable; merchant available obligation |
| Withdrawal reservation | Merchant available obligation | Merchant reserved obligation |
| Reconciled failed payout | Merchant reserved obligation | Merchant available obligation |
| Reconciled confirmed fictional payout | Merchant reserved obligation | Simulated settled cash |

Database constraints/triggers enforce source identity, amounts, posting shape, balanced seals, one settlement per source, one terminal accounting effect per withdrawal, no overdraw and immutable update/delete/replacement behavior. The projection validates source hashes, settlement signatures, payout evidence, exact posting lines and reconciliation identities on reads. Available/reserved funds derive from immutable ledger entries, not dashboard counters:

`simulated available = independently settled − reservations + reconciled releases`

`reserved = reservations − reconciled releases − reconciled payouts`

`available + reserved = independently settled − reconciled payouts`

All amounts are whole integer TZS with bounded inputs and checked accumulation. Operational available balance remains zero regardless of fictional settlement.

## Withdrawals, payout and reconciliation

Withdrawal requests require current merchant owner authority, separate P8 admission, an approved fictional destination reference, an exact positive amount, sufficient simulated funds and an idempotency key. Only the granted masked destination description is retained/displayed. Request references and final payout journal/receipt references are distinct. Reservation and request creation commit atomically under SQLite `BEGIN IMMEDIATE`; an independent database trigger also prevents overdraw. Two-connection contention, duplicate keys and overspend are tested.

A durable dispatch claim commits before provider invocation. Current financial authority is rechecked before the fictional command. A previously claimed request is never automatically dispatched again, including after lost acknowledgment. Ambiguous outcomes retain their reservations and are displayed unresolved. The original provider-neutral `PayoutProvider` contract remains intact; the additive `ReconciledPayoutProvider` interface supplies the recovery capability. The only implementation remains the CLI/testing-only fictional provider; no payment rail or network client is introduced.

Signed callbacks validate merchant, withdrawal, destination, amount, currency, correlation, dispatch lineage and time. They append immutable observations and do not release/pay funds. Reconciliation additionally queries the fictional provider's accepted command state and verifies a fresh authenticated response. Conflicting terminal observations or a nonmatching query remain unresolved with funds held. Only a matching terminal outcome produces a unique payout or release journal. Exact callback and reconciliation retries cannot double-account. A confirmed fictional payout receipt is issued only after this reconciliation.

## Merchant and Chief interfaces

Existing dashboard analytics now shows calculated tables, horizontal comparison bars, cross-dimension rows, source trends, range controls and transaction drilldowns. Merchant wallet adds the net P8 position and settlement/withdrawal history instead of presenting gross pre-P8 obligations as net available funds. The dedicated read-only settlement/withdrawals page shows pending obligations, independently settled simulated funds, reservations, simulated availability, completed fictional payouts, failed/unresolved history and distinct request/receipt references. The original P7 wallet presentation remains available when P8 is not explicitly provisioned.

Chief oversight is a separate independently granted read boundary. It checks current active verified account/session, current Chief policy, an explicit per-merchant `admin_read` grant and conflict exclusions for owned/member/submitted merchants, before and after the financial snapshot. It validates P5 source evidence and P8 ledger projection and exposes only redacted settlement/withdrawal/reconciliation/exception data. It grants no merchant writes or payout controls. Staff and merchants cannot use the Chief endpoint. Ordinary routes/bindings were not changed: the new read routes are registered only by the existing guarded test harness.

## Safe Browser Demo

Reused `scripts/start-demo.ps1` and `stop-demo.ps1`; refreshed only the owned process/export. The existing server is still static, localhost-only and read-only; no browser financial controls are activated. Forms are disabled, scripts removed, writes rejected and assets allowlisted. Role selection is snapshot navigation, not login or an authorization grant.

Fixtures execute real isolated onboarding/approval/payment/link/confirmation/accounting services. Four confirmed payments are attributed to Facebook (12,500), TikTok (6,000), Instagram (3,500) and WhatsApp (2,000), each with authenticated synthetic method evidence. Total confirmed value is calculated as TZS 24,000; failed/pending/unresolved requests are additional records, not confirmed income. Independent fictional settlement establishes TZS 12,500 of simulated funds. A reconciled fictional payout of 2,500, a reconciled failed request of 1,000, an unresolved reservation of 1,500 and a pending reservation of 500 yield TZS 8,000 simulated available and TZS 2,000 reserved. Operational available remains TZS 0. The exporter asserts these service-derived values.

Final entries:

- http://127.0.0.1:18761/merchant-dashboard
- http://127.0.0.1:18761/merchant-analytics
- http://127.0.0.1:18761/wallet
- http://127.0.0.1:18761/settlement-withdrawals
- http://127.0.0.1:18761/chief-financial-oversight

Final isolated export: **1 test / 154 assertions passed**, 45 fixture snapshots (plus demo home/features), log `.recovery/analytics-p8-demo-final.log`.

## Exact implementation files changed in this assignment

Paths relative to XLipa; existing UI modifications outside this list were preserved.

- `backend/app/Actions/Payments/AnalyticsEvidence.php` — new fictional method evidence verifier.
- `backend/app/Actions/Payments/MerchantAnalytics.php` — new capture, classification and report service.
- `backend/app/Actions/Payments/LedgerLifecycle.php` — completed recovered scaffold; settlement, immutable accounting and financial projections.
- `backend/app/Actions/Payments/MerchantWithdrawals.php` — completed recovered scaffold; requests, reservations, dispatch, observations and reconciliation.
- `backend/app/Actions/Payments/FictionalPayoutProvider.php` — independent reconciliation query capability on existing fictional provider.
- `backend/app/Actions/Payments/ReconciledPayoutProvider.php` — additive provider-neutral recovery interface.
- `backend/app/Actions/Payments/MockPaymentLedger.php` — separate explicitly granted Chief read boundary; existing merchant and source accounting behavior preserved.
- `backend/tests/Support/AnalyticsStoreFixture.php`, `LifecycleStoreFixture.php`, `FakeFundsAdmission.php` — guarded additive fixtures and explicit fictional grants.
- `backend/tests/Support/MerchantWalletHttpHarness.php` — optional analytics/P8 merchant and Chief read endpoints; ordinary P7 harness unchanged when omitted.
- `backend/tests/Feature/MerchantAnalyticsTest.php` — new analytics service/HTTP/security coverage.
- `backend/tests/Feature/FunctionalSettlementWithdrawalTest.php` — replaced recovered example test with actual P8 journey/security/accounting/contention tests.
- `backend/tests/Feature/SafeBrowserDemoExportTest.php` — generated analytics, P8 and authorized Chief snapshots with calculated-total assertions and Staff denials.
- `backend/resources/views/merchants/analytics.blade.php`, `analytics-tables.blade.php`, `funds.blade.php`, `funds-tables.blade.php` — focused new presentation partials/pages.
- `backend/resources/views/merchants/overview-finances.blade.php`, `wallet.blade.php`, `financial-layout.blade.php`, `navigation.blade.php` — integration into current financial experience.
- `backend/resources/views/admin/funds.blade.php`, `navigation.blade.php` — Chief redacted read-only oversight integration.
- `frontend/assets/xlipa-product.css` — focused report controls/layout styling; copied to the existing ignored `backend/public/assets` mirror.
- `scripts/demo-server.cjs` — new snapshot destinations and truthful capability inventory.
- `scripts/Test-Safe-Browser-Demo.cjs` — instant scroll reset so dashboard screenshots capture their intended position.
- This requested report. Disposable exports/logs/screenshots remain in existing ignored locations.

## Focused verification and resolved development failures

- Final focused acceptance: **15 tests / 733 assertions / exit 0** (`.recovery/analytics-p8-acceptance-final.log`). Analytics includes capture/classification, Direct/Unknown, methods/signatures, missing evidence, pending/failed exclusion, customer unavailability, integer totals, exact percentages, ranges, cross totals, duplicate/conflicting events, scoped HTTP/filter/drilldown and authorization tests. P8 includes independent settlement, forged/amount/duplicate rejection, balanced postings, reservations/overspend/idempotency, two-connection contention, lost acknowledgment, failed/unknown/conflicting payouts, callbacks/reconciliation, immutable history and merchant/Chief/Staff boundaries.
- Focused contention rerun: **1 test / 48 assertions / exit 0** (`.recovery/p8-contention.log`).
- `pint --dirty --format agent`: completed; final non-mutating result is recorded below.
- Initial analytics tests exposed an incorrect fictional failure identifier and a revocation fixture missing SE-1.0 epoch/revocation fields; fixed test setup without weakening guards.
- First expanded demo exposed unresolved (not-yet-confirmed) checkout attribution capture; the first-touch rule now includes pending and unknown intents, while still rejecting retrospective capture after confirmation.
- Initial two-connection run passed its contention assertions but failed Windows temp cleanup because the caught PDO exception retained a connection through its stack trace. Released those references and collected cycles before removing only the test-owned temp fixture; focused rerun passed.

## Security and provider-dependent limits

No working SQLite file or sidecar, real environment, operational APP_KEY, credential or private key was accessed. No frozen migrations/SQL/manifests or existing isolation guards were changed. No live Selcom, payouts, public submissions, operational decisions, OTP/NIN, financial writes or notifications were enabled. No working-database migration, commit, push or deployment. B3.2-C and SE-1.0 remain NO-GO. No P9 started.

All new evidence is fictional and isolated. Operational schema provisioning, durable real-provider query/reconciliation and settlement mapping, payout-destination custody/admission, provider signing/key management, retention governance, operational recovery and live load verification remain separate work. The fictional provider's accepted-command state is process-local; after a provider-fixture restart, a missing independent reconciliation response leaves the durable dispatch unresolved and never authorizes automatic resend. This does not certify live-provider readiness. Static demo forms cannot exercise date submission or money movement; those service/HTTP behaviors are verified by isolated tests. Unique customer analytics and operational analytics exports remain unavailable by design.

## Final integration corrections

Visual inspection caught and corrected the inherited dashboard wording/position: its quick action now links to the verified settlement/withdrawal view, its pending obligation is net of authenticated simulated settlement (TZS 11,500 in the fixture), and it separately shows settled simulated funds, reservations and simulated availability. The operational withdrawable value remains zero. Added exporter assertions for the net pending/available values. Scoped the Chief card typography so document heading rules do not enlarge metric labels. Instant scroll reset in the browser harness ensures overview screenshots show the overview, rather than an in-progress smooth-scroll position.

The earlier P0 invocation passed pins, syntax, formatting and 33 functional tests but was deliberately interrupted before completion for these screenshot-driven corrections. Only `.recovery/analytics-p8-p0-final.log` is the final preservation evidence. Service code and its 15-test acceptance remained unchanged by the visual corrections.

Accepted demo export: **1 test / 157 assertions / exit 0**, 45 fixture snapshots; `.recovery/analytics-p8-demo-accepted.log`. Final scoped non-mutating Pint: **14 files / exit 0**, `.recovery/analytics-p8-format-final.log`.

## Final acceptance

| Objective | Status | Scope |
|---|---|---|
| A — Merchant Analytics | **PASS** | Functional isolated capture, authenticated synthetic methods, calculated reports/ranges/cross-dimensions, current merchant authorization and integrated UI |
| B — P8 | **PASS** | Independent fictional settlement, balanced immutable extension of the P5 ledger, reservations, payout uncertainty/idempotency, reconciliation and authorized Merchant/Chief read models |

These are isolated implementation acceptance statuses. They do not authorize operational financial use or certify live-provider readiness. No required isolated implementation task remains open in this assignment. The provider-dependent limitations above remain explicit. Stop for owner review before P9.

### Final recorded results

| Verification | Result | Evidence |
|---|---|---|
| Analytics + P8 focused acceptance | 15 tests / 733 assertions / exit 0 | `.recovery/analytics-p8-acceptance-final.log` |
| P4–P7 + Chief/Admin regression | 175 tests / 7,791 assertions / exit 0 | `.recovery/analytics-p8-regression.log` |
| Accepted demo export | 1 test / 157 assertions / exit 0 | `.recovery/analytics-p8-demo-accepted.log` |
| Non-mutating scoped Pint | 14 changed PHP files / exit 0 | `.recovery/analytics-p8-format-final.log` |
| P0 guards and frozen integrity | 29 source pins, 3 manifest digests, 4 INT-05 pins, 4 isolation guards passed | `.recovery/analytics-p8-p0-final.log` |
| P0 PHP syntax / formatting | 233 PHP syntax checks, 15 non-mutating formatting checks passed | Same final P0 log |
| P0 functional suite | 33 tests / 964 assertions passed | Same final P0 log |
| P0 bounded regression | 546 tests / 4,879 assertions passed | Same final P0 log |
| P0 final source preservation | PASS / complete command exit 0 | Same final P0 log |
| Final browser harness | 47 pages / 16 assets / 59 navigation targets / 141 viewport checks / exit 0 | `.recovery/analytics-p8-browser-evidence.log` |
| Browser security/accessibility checks | Disabled forms, no scripts, no overflow/broken images/missing CSS; keyboard menu/focus and actual wallet navigation passed; POST 405 and sensitive URL probes 404 | Same browser evidence |
| P8 displayed balances | Simulated available TZS 8,000; reserved TZS 2,000; operational available TZS 0, checked across Merchant wallet/P8 and Chief views at all three widths | Same browser evidence |
| Final Git whitespace check | exit 0 | `git diff --check` |
| Running demo ownership | PASS; left running at http://127.0.0.1:18761/ | Owned state/health token comparison |

Final screenshot evidence: **127 PNGs** and `qa.json` in `.recovery/ui-ux-evidence-1795270163834`. Inspected the final Merchant dashboard desktop, source/method mobile tables/charts, wallet tablet and mobile financial position, settlement/withdrawal history desktop, and Chief mobile oversight. Earlier screenshot sets were retained, not overwritten. The added browser checks verify the displayed P8 metric values rather than relying only on layout assertions. Test selections overlap; their counts must not be added as a unique overall test count.

No commits, pushes, deployments, working database access, frozen source changes, operational admission or real financial/identity operations. B3.2-C and SE-1.0 remain NO-GO. **P9 not started.**
