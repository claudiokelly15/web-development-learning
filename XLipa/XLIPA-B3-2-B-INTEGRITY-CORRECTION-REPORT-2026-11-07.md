# XLipa — B3.2-B Integrity Correction Report

**7 November 2026 · Africa/Nairobi · ICR-1.0 · PARTIAL ACCEPTANCE / R06 BLOCKED**

## 1. Executive summary — Muhtasari

**Implemented and tested:** R01 proof-history integrity; R02 chronology predicates derived from existing approved flows; R03 precise rejection assertions and independent constraint cases; R04 idempotent revocation auditing; R07 pre-access migration guards. Exactly seven persistent business tables remain. Parent IDs, binary tenant identity, AAD-only uppercase normalization, OTP/proof limits and TZS-only scope are preserved.

**New actual execution:** targeted tests **11/11 passed, 230 assertions**; complete B3.2-B suite **37/37 passed, 585 assertions**; full combined regression **145/145 passed, 1,432 assertions, zero failures/errors**. The combined run includes the existing 108 regressions/847 assertions. These are current executions, not copied prior 135/1,177 results.

**R06 remains unresolved.** Two explicitly labeled diagnostic tests reproduce the audit-outage accounting rollback. Their passing means the defect is reproducible and its observed state is checked, **not that the durability requirement is satisfied**. Final acceptance and B3.2-C remain NO-GO. No new audit/accounting policy or architecture was silently implemented.

Kwa Kiswahili: marekebisho ya history, chronology, test quality, revocation na guards yamepita. Lakini audit ya exhaustion ikishindwa, failure ya tano inarollback hadi 4, na ya kumi hadi aggregate 9. Hili linahitaji uamuzi wa architecture/policy. Nimesimamisha marekebisho ya sehemu hiyo; nimekamilisha tu formatting, regression zilizoidhinishwa na ripoti. Hakuna working activation.

## 2. Authorization, inspection and baseline

Current task authorizes R01/R02/R03/R04/R06 investigation/R07 corrections and isolated synthetic tests. It explicitly requires stopping for a material new policy/architecture decision. It does not authorize B3.2-C, production, working SQLite, existing applied migrations, .env/secrets, frontend, real identity/SMS/Selcom or new financial/currency features.

Reviewed current source and the complete [FAR-1.0 final review](XLIPA-B3-2-B-FINAL-ACCEPTANCE-REVIEW-2026-11-07.md), [SBR-1.1 security contract](XLIPA-B3-2-SECURITY-BLOCKERS-RESOLUTION-2026-11-06.md), [technical decisions](XLIPA-B3-2-TECHNICAL-DECISIONS-AND-SCHEMA-APPROVAL-2026-11-06.md), [previous implementation report](XLIPA-B3-2-B-SYNTHETIC-IMPLEMENTATION-REPORT-2026-11-06.md), backend AGENTS.md, draft migrations, synthetic service/crypto/coordinator/fixture/bootstrap/test contracts. No `.ai/rules` directory at the workspace/project/backend locations checked. No testing-best-practices skill was available in the supplied catalog; no skill/dependency was installed.

Git root `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT`, XLipa project only. HEAD before/after `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`. Existing four tracked modifications and untracked B3.2 work were retained. Pre-write in-memory SHA-256 inventory: **202 unique existing files**, including explicit environment/database files. No separate baseline artifact written.

Status vocabulary: **CORRECTED/TESTED** means source change with current passing assertions; **DIAGNOSTIC VERIFIED** means observed unresolved behavior reproduced; **PROPOSED / REQUIRES OWNER APPROVAL** means no implementation permission inferred; previous run results remain historical. No production readiness is claimed.

## 3. Exact files changed and created

Paths relative to `XLipa/`. Only four existing draft/support/test files changed:

| File | Exact change |
|---|---|
| backend/database/migrations/2026_11_06_100000_create_synthetic_verification_contract.php | Freeze both consumption fields on every transition into revoked, including NULL history; named OTP/proof chronology CHECKs; validate configured synthetic target/URL/environment before invoking DB facade, then retain actual connection/FK checks. |
| backend/database/migrations/2026_11_06_100001_extend_synthetic_audit_contract.php | Extract configuration-only guard; invoke it before down's first audit query and before rebuild DB access; retain actual memory/SQLite/dependency/rebuild checks. |
| backend/tests/Support/SyntheticVerification.php | Sum changed proof/challenge row counts during revoke; emit mutation audit only when at least one row changed. No verify/OTP accounting/audit policy change. |
| backend/tests/Feature/B32SyntheticContractTest.php | Replace any-exception acceptance with expected class/message and SQLSTATE/SQLite category assertions; rewrite raw constraint tests; ten new integrity/diagnostic methods; final Pint formatting. |

**One new file:** this report, `XLIPA-B3-2-B-INTEGRITY-CORRECTION-REPORT-2026-11-07.md`.

The two `100000`/`100001` files are the existing B3.2-B synthetic drafts, not the original applied foundation migrations. No original `0001_*`/`2026_11_06_00000*` migration changed. Existing specifications/reports, crypto/key/AAD code, routes, frontend, .env, existing regressions and production services are unchanged in this task. No new column/table/event type added.

## 4. Findings corrected

### R01 — Proof-history integrity: CORRECTED / TESTED

The proof UPDATE trigger now compares `NEW.consumed_at` and `NEW.consumed_application_id` to OLD whenever entering revoked, even when OLD values are NULL. Existing non-NULL consumption history remains immutable. Only available→consumed initializes the pair.

Raw-SQL test supplies valid same-merchant application FK, paired timestamps, correct next state_version and otherwise-valid revoked shape; fabricated history is rejected specifically by `Immutable data or invalid transition`. It verifies the row is unchanged. A valid consume control then succeeds; changing consumption during consumed→revoked is rejected, and legitimate revocation preserves the exact original consumption time/application.

Unconsumed revocation also succeeds with both consumption fields remaining NULL. Neither a stale version nor missing application fixture is used as the reason for the R01 rejection.

### R02 — State chronology: bounded existing-flow rules CORRECTED / TESTED

The new predicates encode existing event order and expiry checks, without changing any lifetime:

| State/event | Enforced chronology |
|---|---|
| Existing digest metadata | `created_at <= digest_created_at < expires_at` remains unchanged. |
| Any terminal event when digest time exists | `terminal_at >= digest_created_at`; existing `terminal_at >= created_at` remains. |
| OTP consumed/exhausted | `terminal_at < expires_at`; existing row shape requires digest_created_at present, so it is also at/after digest creation. |
| OTP expired | `terminal_at >= expires_at`. |
| Revoked proof with prior consumption | `revoked_at >= consumed_at`, in addition to existing verified_at/expiry/consumption checks. |
| OTP/proof intervals | Exactly `expires_at=created_at+300000` and `expires_at=verified_at+1800000`, unchanged. |

Equality at issuance/consumption/revocation times is allowed: this preserves the already implemented same-millisecond test flows and nondecreasing event ordering. No extra grace period, strictly-positive processing delay or future-dated expiry extension introduced. Correct-code/exhaustion evaluation before expiry and existing replacement logic at expiry supply these boundaries; no new business timing policy was selected.

Tests use correct next versions and otherwise-valid row shapes to isolate named CHECK errors `otp_terminal_chronology` / `proof_revocation_chronology`. They cover pre-issuance terminal times, exact/after OTP expiry, premature expired state, backdated revocation, and valid equal-time/expiry controls. Existing five-guess exhaustion tests supply a valid exhaustion control. No new rule was invented for the deferred provider invocation/late-delivery lifecycle (R05); that remains outside this correction.

### R03 — Constraint test quality: CORRECTED / TESTED within existing suite

`rejected()` now requires an expected exception class and message. SQLite constraint cases assert SQLSTATE `23000` and primary SQLite code `19`; application policy/crypto/model/guard exceptions require their known class/message. A random fixture/SQL/programming exception no longer satisfies the assertion.

Raw contact tests separate:

- Immutable-field changes with correct row_version increment, asserting the immutable trigger.
- A stale-version-only update, asserting the version guard separately.
- DELETE prohibition and current-contact uniqueness with their actual messages.
- Valid retirement control, then valid current contact INSERT template without unrelated UPDATE immutability checks. Individual invalid version/type/purpose/fingerprint/key/row-version/retirement/envelope fields are rejected by CHECK; missing actor by FK; duplicate scoped version by UNIQUE.
- Valid final INSERT control using the same otherwise-valid template. Failed inserts leave no row.
- Six partial-null context tuple permutations with correct context_version; a distinct mismatched-actor FK case; valid all-present and all-null controls.

Original related tests now specify exact failure categories (foreign tenant/case-altered application FK, policy authority, crypto context, audit metadata, unknown event, injected audit failure, downgrade refusal, reconstruction collision and temp guard). Terminal resurrection test now supplies the correct next version so a stale CAS no longer masks the illegal state edge.

No constraints/triggers were removed or weakened to make tests pass. This is meaningful independent negative/control coverage, not a claim of exhaustive mutation testing of every possible constraint. No schema mutation test or eighth business table was introduced.

### R04 — Revocation idempotency: CORRECTED / TESTED

revoke() performs proof/challenge updates in its existing transaction, tracks their affected rows and emits contact.proof_changed only when a genuine mutation occurred. A second identical revoke or wrong/nonexistent-contact no-op adds no false mutation audit and does not overwrite original revocation/consumption evidence.

Tests check unconsumed and consumed proofs, genuine active-challenge-only revocation and repeated calls. Genuine first revocation remains audited; no new attempted-action event or audit contract was added. This remains a test-only helper, not an operator authorization endpoint.

### R07 — Migration guard ordering: CORRECTED / TESTED

Audit down calls the configuration-only synthetic guard before referencing audit_logs. rebuild also guards before its DB calls and still verifies actual SQLite/memory connection. Core up/down first inspect configured driver/path/URL/environment without DB facade access, then retain actual target/FK verification after validation. A configured DB_URL override is rejected rather than trusted.

The test sets only the in-memory Laravel **configuration string** to the canonical working path and replaces the DB facade with a strict mock accepting no calls. Both draft migrations' up/down methods must produce their specific guard LogicException before touching that mock. A connection/query attempt would fail the expected assertion. The real working connection/database is never opened. Configuration/facade are restored in finally.

Core guarded temporary-file support and audit memory-only support remain as before. No guard removed or relaxed, no “pretend working is testing” activation path added.

## 5. R06 — Audit-outage investigation: DIAGNOSTIC VERIFIED / REQUIRES OWNER APPROVAL

The wrong-guess algorithm is intentionally **unchanged**. New diagnostics inject a SQLite BEFORE INSERT trigger that rejects only the exhaustion-event write after the legitimate setup failures have already committed. They assert the exact injected exception, no proof and resulting persisted state.

| Diagnostic | Observed after injected failure |
|---|---|
| Challenge has four failures; fifth wrong guess evaluates, then exhaustion audit fails | Entire transaction rolls back: challenge remains at **4**, limit row byte-for-byte unchanged. Repeating the attempt reproduces the same rollback; no proof created. |
| First challenge contributes five failures; second contributes four; tenth aggregate wrong guess evaluates, then audit fails | Entire transaction rolls back: second challenge remains at **4**, aggregate retains **9**, no tenth slot, unchanged limit row, no proof created. |

These tests are explicitly named `test_integrity_audit_outage_reproduces_*_rollback_without_proof` and documented as diagnostics of an unresolved policy boundary. Their pass does not satisfy durable accounting or demonstrate fail-closed denial **before further code evaluation**. Existing tests still cover rollback of successful proof creation when its audit fails.

The specification simultaneously requires counted failures to remain accounted and mutation auditing to share its transaction. If the audit INSERT itself fails, the current single transaction cannot both commit the counted failure and commit the failed audit event. Swallowing the error/committing counters without an audit changes policy; an independent durable pending record or outage gate changes architecture. Neither was authorized as an implicit fix.

**STOP on R06 implementation:** no extra failure-state field, outbox, new table, new reason/event, independent counter commit, silent audit loss or quota reinterpretation was implemented. After diagnostic confirmation only the already authorized formatting/validation/reporting was completed. The complete suite remains green because the two diagnostics accurately characterize the defect; final acceptance remains blocked.

### Options for owner review — not implemented

| Option | Concrete direction | Approval / trade-off |
|---|---|---|
| **A — Recommended for a separately reviewed design** | Keep seven tables, but propose bounded durable pending-audit metadata within existing scope state; atomically persist counted failure plus pending obligation, gate further code evaluation while pending, reconcile into existing audit_logs idempotently. Successful verification/consumption retains its atomic success audit. | **REQUIRES OWNER APPROVAL** for added columns/tenant references, schema scope refinement, audit timing/recovery policy, failure/flush protocol and tests. This is a proposal, not already configured outbox/custody. Preserve append-only final audit and original occurrence time. No eighth table. |
| B — Fail-closed availability gate before evaluation | Design a durable cross-process audit/accounting health gate and recovery protocol; deny verification before inspecting code while unhealthy. | **REQUIRES OWNER APPROVAL** for health-state custody, detection/recovery and proof that unexpected mid-operation failures cannot reopen a guess oracle. A simple preflight SELECT or memory flag does not guarantee durability; may still need a pending/reservation protocol. No complete implementation is claimed. |
| C — Independent counters without guaranteed mutation audit | Commit failures despite audit failure and return temporary unavailability. | Changes the approved accounting/audit atomicity contract and loses audit evidence unless separately reconciled. **Not recommended as a silent shortcut.** Explicit owner audit-policy revision would be required. |

**Requested next decision:** authorize preparing the exact Option A specification for review (columns, pending-state transition truth table, idempotency, outage/restore behavior and acceptance tests) before any architecture-changing implementation, or choose an alternative. This does not request renewed approval of OTP/proof values and does not include B3.2-C.

## 6. Tests added and current execution evidence

Ten methods added to the existing feature test file:

1. `test_integrity_revocation_cannot_create_or_rewrite_consumption_history`
2. `test_integrity_unconsumed_revocation_preserves_null_history_and_is_idempotent`
3. `test_integrity_consumed_revocation_keeps_history_and_active_only_revocation_is_audited_once`
4. `test_integrity_consumption_chronology_has_inclusive_issue_and_exclusive_expiry_bounds`
5. `test_integrity_exhaustion_expiry_and_revocation_chronology`
6. `test_integrity_proof_revocation_cannot_predate_consumption`
7. `test_integrity_context_tuple_checks_are_independent_of_version_or_fixture_failure`
8. `test_integrity_migration_guards_refuse_working_config_without_database_facade_access`
9. `test_integrity_audit_outage_reproduces_fifth_guess_rollback_without_proof` — diagnostic only.
10. `test_integrity_audit_outage_reproduces_tenth_aggregate_rollback_without_proof` — diagnostic only.

Existing raw-SQL test and related rejection cases rewritten as described. No old regression/test removed. Complete B3.2 suite is now 33 feature cases plus four crypto unit cases.

| Actual run | Tests / assertions | Failures / errors | Result |
|---|---|---|---|
| Initial targeted corrections/diagnostics | 11 / 230 | 0 / 0 | PASS; 2,588 ms. |
| Pint `--dirty --format agent` | Formatting command, not tests | Exit 0 | Formatted the feature test; prior draft files checked. |
| Final targeted after formatting/final assertions | **11 / 230** | **0 / 0** | **PASS**; 2,285 ms. |
| Complete B3.2-B synthetic filter | **37 / 585** | **0 / 0** | **PASS**; 12,326 ms. Includes two unresolved-behavior diagnostics. |
| Full combined test suite, including existing regressions | **145 / 1,432** | **0 / 0** | **PASS**; 26,970 ms. |

Existing regression contribution is **108 tests/847 assertions**, re-executed inside the full run (145−37; 1,432−585), not reported as a separate newly run command. A green suite here does not certify R06 fixed or complete production security. Previously reported 135/1,177 values were not reused as present results.

Commands executed from `XLipa/backend`, with explicit existing runtime `C:\Users\hp\.config\herd\bin\php85\php.exe`:

```text
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'test_integrity|test_raw_sql'
<php85> vendor/bin/pint --dirty --format agent
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'test_integrity|test_raw_sql'
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter B32Synthetic
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result
```

PHP access/formatter/tests were allowed through sandbox escalation; no automatic review rejection. Other commands were source/inventory/hash/Git reads. No standalone working Artisan migration, install, live provider/network action or application endpoint was used.

## 7. Isolation and preservation evidence

All Laravel migration/setup operations use the existing isolated testing bootstrap: `:memory:`, empty DB_URL, no working dotenv load, public deterministic fixture keys, mail array, queue/log null and blocked stray Laravel HTTP requests. Existing multi-process tests use random guarded temporary `xlipa-b32-synthetic-<hex>.sqlite` files built only from synthetic fixture data, not working records. No matching leftover temporary files found at inspection.

New guard tests contain the working path only as a configuration value and exercise strict mock refusal; they do not connect to or copy it. Existing parent-ID/case/tenant/crypto tests ran unchanged as part of B3.2-B. No currency field or financial behavior added; **XLipa remains TZS only and a website, not a mobile app**.

Working SQLite SHA-256 before/after:

`214AE83A31F53E80D3068D1AEB1366008BB5ACCB2DF212DD2C534AE4121B6D8F`

Before report creation, all **202 baseline files** compared: exactly the four intentional source/test changes listed above, **198 unchanged**, **zero missing**. Those unchanged files include working SQLite, .env/.env.example, original applied migrations, frontend, crypto/key/AAD code, existing specifications/reports and unrelated tracked/nonignored workspace files. No .env.testing created, no real APP_KEY/credential disclosed or changed. Ignored dependencies/caches/recovery trees were not exhaustively hashed.

**VERIFIED final post-report comparison:** the same 202 baseline files show exactly four intentional changes, 198 unchanged and zero missing. The only new nonignored workspace entry is this requested report. `git diff --check` passed; HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`. This final report-only edit records those results. No unrelated project changes, stage/history changes, commit/push/deploy or working activation.

## 8. Remaining issues and GO / NO-GO

| Area | Status / recommendation |
|---|---|
| R01 history, bounded R02 chronology, R03 test quality, R04 idempotency, R07 guards | **CORRECTED AND TESTED** within the specified synthetic contract. Owner review still required; no production guarantee. |
| R06 audit-outage durability | **UNRESOLVED / OWNER DECISION REQUIRED.** Reproduced, not fixed. No policy silently changed. |
| Final unconditional B3.2-B acceptance | **NO-GO while R06 remains unresolved.** Passing characterization diagnostics are not durability acceptance. |
| R05 complete delivery fencing and R08 broader recovery/evidence | Remain deferred findings in FAR-1.0; no provider/working recovery implementation in this task. |
| B3.2-C / working database activation | **NO-GO**, outside authorization and still requires accepted B, reviewed activation artifacts/preflight/backup/restore plan and explicit execution permission. |
| Real NIN/contact, live SMS/email, Selcom/payment services, production | **NO-GO**, unimplemented/unauthorized. |

**Nimesimama kwa owner review ya R06.** Pendekezo ni kuidhinisha specification ya Option A kwa review kabla ya code mpya. Hakuna kuendelea B3.2-C, kuactivate working SQLite, kuongeza currency nyingine au kupanua tables saba kimyakimya.
