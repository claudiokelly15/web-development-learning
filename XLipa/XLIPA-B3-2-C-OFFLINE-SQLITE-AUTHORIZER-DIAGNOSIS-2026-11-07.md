# XLipa — B3.2-C Offline SQLite Authorizer Diagnosis

**OAD-1.0 · 7 November 2026 · Africa/Nairobi · OFFLINE / SYNTHETIC ONLY**

## 1. Executive summary

**VERIFIED root cause:** the reconstructed FRR callback references nonexistent global authorizer constants, beginning with `SQLITE3_SELECT`. On the same installed PHP **8.5.12 / SQLite 3.53.4**, `BEGIN` invokes action **22 (`SQLite3::TRANSACTION`)**. Evaluating the callback's allowlist throws `Error: Undefined constant "SQLITE3_SELECT"`; the uninstrumented caller receives **SQLITE_AUTH (23)**. The denial occurs during statement preparation, before any schema/ledger inspection.

**Correction:** use `SQLite3::SELECT`, `SQLite3::READ`, `SQLite3::FUNCTION`, `SQLite3::TRANSACTION`, `SQLite3::PRAGMA`, `SQLite3::OK` and `SQLite3::DENY` for authorizer actions/results. Keep global `SQLITE3_OPEN_READONLY` for the genuinely global file-open flag. No new write action needs permission, and no database repair is indicated by this defect.

Implemented a **test-only policy and synthetic diagnosis suite**. Approved metadata/ledger/count/integrity/FK queries pass; dangerous statements remain denied; each fixture remains byte-identical after read-only tests with no sidecar/unintended file. Working SQLite and real `.env`/secrets were neither opened nor hashed. No working inspection retry occurred.

Kwa Kiswahili: tatizo lilikuwa majina ya constants kwenye callback, si ushahidi wa database corruption. Correction imejaribiwa kwenye synthetic fixtures pekee. Working activation bado **NO-GO**.

**GO for owner review of this offline correction and evidence**, followed only by a separately authorized bounded read-only revalidation if accepted. This report does not complete FRR-1.0 or authorize backup/migration. XLipa remains a WEBSITE supporting TZS only.

## 2. Reviewed evidence and exact files

Reviewed [FRR-1.0](XLIPA-B3-2-C-FRESH-READ-ONLY-REVALIDATION-2026-11-07.md), [RTP-1.0](XLIPA-B3-2-C-READ-ONLY-TARGET-PREFLIGHT-2026-11-07.md), [WSR-1.0](XLIPA-B3-2-C-WORKING-TARGET-SAFETY-AND-RECOVERY-PACKAGE-2026-11-07.md), [OSG-1.0](XLIPA-B3-2-C-OFFLINE-SECURITY-GUARD-IMPLEMENTATION-REPORT-2026-11-07.md), the FRR standalone callback retained in session tool history, existing isolated bootstrap/PHPUnit conventions and backend AGENTS.md. The historical inspector was supplied through standard input, not an existing source file to patch. No historical report is rewritten.

No `.ai/rules` directory was present at the checked workspace/project/backend locations. No testing-best-practices skill was available in the supplied catalog; none was installed. No dependency, sub-agent, production registration or runtime service was added.

**New files only**, relative to `XLipa/`:

| File | Purpose |
|---|---|
| `backend/tests/Support/OfflineInspectorAuthorizer.php` | Callable read-only metadata policy with class constants and safe action/category diagnostics; no database opener or execution authority. |
| `backend/tests/Unit/OfflineInspectorAuthorizerTest.php` | Legacy reproduction, captured cause, minimal correction control, inventory and 19 dangerous-statement cases, missing-file and callback-failure tests. |
| `XLIPA-B3-2-C-OFFLINE-SQLITE-AUTHORIZER-DIAGNOSIS-2026-11-07.md` | This report. |

All approved B/C migrations, activation artifact, frozen SQL, selection manifest, existing tests/guards, frontend and previous reports remain unchanged. No corrected working launcher is installed.

## 3. Reproduction and exact denied stage

A standalone initial probe used a newly generated memory database. The reconstructed callback failed at `BEGIN`, code 23. Logging placed after allowlist evaluation produced no event because evaluation itself threw. A second synthetic probe recorded entry before evaluation and caught the callback error, returning DENY explicitly.

Captured safe diagnostic tuple:

| Field | Observed value |
|---|---|
| Query category / stage | Read-transaction acquisition / prepare `BEGIN` |
| Action code / name | **22 / TRANSACTION** |
| First argument | `BEGIN` |
| Second argument | NULL |
| Database / trigger-or-view source | NULL / NULL |
| PHP callback error | `Undefined constant "SQLITE3_SELECT"` |
| SQLite caller outcome | SQLite3Exception, **23 / SQLITE_AUTH** |

`defined('SQLITE3_SELECT')` returned false; `SQLite3::TRANSACTION` equaled 22. Permanent tests preserve both the uninstrumented failure and instrumented diagnostic. Inside the namespaced PHPUnit regression control PHP reports `Tests\Unit\SQLITE3_SELECT`; that is namespace qualification of the same missing global identifier. A separate class-constant-only control permits BEGIN/read/ROLLBACK without admitting a new action code.

This identifies **callback handling/constant resolution**, not PRAGMA mutation, an internal write, a function evaluation or unexpected SQL, as the first failure in the reconstructed FRR sequence. The historical target's denied-action trace was never captured; this report does not invent one. The source/runtime reproduction supplies deterministic evidence of the callback defect.

The PHP manual specifies authorizer constants on `SQLite3` and preparation-time callbacks. [PHP SQLite3::setAuthorizer](https://www.php.net/manual/en/sqlite3.setauthorizer.php). Its separate global open flags must not be confused with those action constants.

## 4. Correction and retained protections

The minimal causal fix is constant qualification. The new isolated policy additionally restricts the original broad function/transaction handling to the exercised inspector profile:

- SELECT and bounded read operations; BEGIN and ROLLBACK only.
- Functions `sqlite_version`, `count`, `typeof` and `like`, needed by the fixed metadata/aggregate queries; arbitrary functions, including extension loading, denied.
- Enumerated read-only PRAGMAs only when there is no assignment argument; identifier-bearing table/index/FK metadata PRAGMAs accept bounded identifier arguments. Unsafe/unknown PRAGMAs remain denied.
- All unrecognized action codes return DENY. Callback errors fail closed. No IGNORE fallback, authorizer removal or permissive retry exists in the policy.

During validation, LIKE was observed as FUNCTION action 31. COUNT(*) produced READ action 20 with an empty column and missing database name in this build; the corrected profile explicitly accommodates that read-only callback shape. These were refinements of the new narrower profile, **not alternative explanations of FRR's first BEGIN failure**. PHP documents empty-column READ authorization for COUNT(*). No permission to attach another database is added.

Each synthetic file is independently populated, closed, then opened with **OPEN_READONLY**, followed by connection-local query_only=ON, temp_store=MEMORY and foreign_keys=ON. The policy remains installed for the approved query and negative-statement tests. Missing-file OPEN_READONLY fails without creating a file. Fixture creation uses read/write/create only to construct that new synthetic input, never for the inspection connection.

The policy has no path opener, Windows lease implementation, approval signer or working permit validator. Future authorized reuse still requires the existing canonical-path/configuration/identity lease, no-create and sidecar/journal refusal controls; none is weakened by these tests. Invalid signed authorization and zero-access behavior continue to be tested by the unchanged OSG suite, rather than replacing its permit checks with a test boolean.

Diagnostics retain category/action/name/allow-or-deny only. The captured legacy arguments above are fixed synthetic transaction metadata. No arbitrary SQL text, filenames, row values, personal data or secrets are logged by the policy.

## 5. Actual tests and outcomes

Commands from `XLipa/backend`, existing Herd PHP85 executable:

```text
<php85> vendor/bin/pint --dirty --format agent
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter OfflineInspectorAuthorizerTest
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result
```

| Final execution | Actual results |
|---|---|
| Targeted diagnosis after Pint | **25 tests / 149 assertions; zero failures/errors; 497 ms** |
| Full combined regression | **311 tests / 3,463 assertions; zero failures/errors; 56,087 ms** |

The full run re-executed all previous 286 cases and the 25 new cases, including accepted B/C compatibility and OSG invalid-signature/authorization/replay zero-access tests. These are current executions, not copied historical passes.

The 25 cases cover uninstrumented/instrumented legacy reproduction; class-constant-only correction; approved schema, ledger, identity, table/index/FK metadata, settings, counts, integrity=`ok` and FK-check empty; INSERT/UPDATE/DELETE; CREATE/DROP/ALTER; ATTACH/DETACH; unsafe PRAGMAs and extension loading; VACUUM; missing-file refusal; unknown-action denial and callback-error fail-closed behavior.

Every case verifies the closed synthetic database hash is unchanged and its private directory contains **only fixture.sqlite**, then removes the fixture. This catches WAL/SHM/journal or unintended file creation in those tests. It does not certify filesystem power-loss behavior or permit opening a real WAL/hot-journal target. Existing signed-permit denial tests are included in the full regression run.

Development failures are retained: initial 23-case run had 20 passes, one assertion failure and two errors (namespaced error text, missing LIKE function permission, and constructor exception type). Next run had 22 passes/one error; an instrumented single-case run localized the COUNT empty-column READ denial. Corrections preserved the negative tests; a 23-case/140-assertion run then passed. Two final regression controls brought the final targeted count to 25/149. These development totals are not substituted for final results.

Pint formatted the new test. Runtime/test/formatter execution was approved through sandbox escalation; no automatic approval rejection occurred. No Artisan boot, dependency installation or real environment setup ran. Full tests use the existing isolated bootstrap: public fixture keys, no working dotenv, memory SQLite and guarded disposable synthetic files for existing concurrency tests, blocked stray Laravel HTTP requests and inert mail/queue configuration.

## 6. Preservation evidence

In-memory source baseline: **225 pre-existing tracked/nonignored-untracked files**, excluding all SQLite paths/sidecars and `.env` variants before hashing. No preservation artifact was written. Working SQLite, its sidecars and real environment/secret files were not opened, hashed, copied or inspected in this task. Previous working hashes remain historical and are not reverified here.

**VERIFIED final post-report comparison:** all **225 baseline files unchanged**, zero changed/missing. Exactly three additions were found: the two new PHP files and this report. No `xlipa-authorizer-*` temporary directory remained. `git diff --check` passed; HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`. This final report-only edit records those results. Ignored dependency/cache/recovery trees are outside exhaustive preservation coverage. No approved migration, existing guard/test, historical report, frontend, branding, source configuration or Git history was modified. No staging/commit/push/deploy occurred.

## 7. Limitations and next approval

**VERIFIED:** the offline callback defect is reproduced and corrected on the installed PHP/SQLite build, with dangerous operations denied and synthetic files preserved. This is evidence for the inspector policy, not fresh working schema/ledger/integrity evidence. FRR-1.0 remains incomplete until separately authorized revalidation succeeds.

**NOT TESTED / NOT AUTHORIZED:** a working-target retry, live WAL/hot-journal handling, real OneDrive/file-ID continuity, production approval custody or physical storage durability. No backup, restore, working migration, runtime activation, NIN/OTP collection, live SMS/email, Selcom/payment service, commit/push/deploy occurred.

**GO for owner review of OAD-1.0.** If accepted, the next explicit approval should authorize only a fresh standalone read-only revalidation with this reviewed constant correction/profile, retaining RTP/FRR filesystem/configuration safeguards and stopping if any write/recovery is required. This does not authorize a migration or promote the offline harness into a working opener.

**Working activation remains NO-GO. STOP for owner review.** XLipa is a WEBSITE supporting TZS only.
