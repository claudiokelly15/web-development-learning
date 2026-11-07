# XLipa — B3.2-C FRR-2.0 Readiness Review

**FRP-1.0 · 7 November 2026 · Africa/Nairobi · REVIEW / PREPARATION ONLY**

## 1. Recommendation

**GO to request separate owner authorization for one bounded FRR-2.0 read-only attempt using the corrected policy and the execution scope below.** The reproduced callback defect has a concrete correction, and inspected tests cover required query categories with the authorizer enabled. No additional corrective implementation is required by this review before requesting that permission.

This is readiness for an inspection attempt, conditional on its fresh pre-access checks. It is **not** a finding that the current working target is safe, unchanged or accessible. No working database, sidecar or real `.env`/secret was accessed, inspected or hashed here. The complete standalone working inspector is not installed as a runtime command; the reviewed component is a test-only callable policy, to be used in an explicitly reviewed standalone read-only invocation, never through the synthetic bootstrap.

Kwa Kiswahili: correction na tests zinaunga mkono kuomba ruhusa ya FRR-2.0. Ukaguzi huo haujaanza. Path, configuration, file lease na sidecars lazima vikaguliwe upya baada ya ruhusa; sharti likishindwa, simama.

**Working activation remains NO-GO.** XLipa remains a WEBSITE supporting TZS only.

Labels: **VERIFIED** = source/record evidence inspected in this review; **REPORTED** = historical execution/target observation, not a fresh run; **PROPOSED** = unexecuted plan; **REQUIRES OWNER APPROVAL** = separate permission. No tests or runtime commands were run in this review.

## 2. Verified root cause and correction

Reviewed complete OAD-1.0, FRR-1.0, RTP-1.0, WSR-1.0 and OSG-1.0 reports against `backend/tests/Support/OfflineInspectorAuthorizer.php`, `backend/tests/Unit/OfflineInspectorAuthorizerTest.php`, isolated bootstrap and database configuration. Backend AGENTS guidance was retained; `.ai/rules` was absent at the checked workspace/project/backend locations. No new skill, dependency or sub-agent was used.

| Question | Concrete inspected evidence / assessment |
|---|---|
| Incorrect constant used? | The preserved legacy callbacks in `test_original_callback_reproduces_auth_and_instrumentation_identifies_constant_error` and `test_uninstrumented_original_callback_fails_at_begin` reference global `SQLITE3_SELECT` and other global authorizer names. |
| Correct replacement? | Current policy matches `SQLite3::SELECT`, `READ`, `FUNCTION`, `TRANSACTION`, `PRAGMA` and returns `SQLite3::OK` / `DENY`. Global `SQLITE3_OPEN_READONLY` remains the correct separate open flag. |
| Original failure reproduced? | Legacy tests assert exception code 23 at BEGIN; the instrumented control asserts action 22, first argument BEGIN, other arguments NULL and the missing-constant PHP error. A class-constant-only control permits BEGIN/read/ROLLBACK without adding an action permission. |
| Required queries permitted? | The inventory test exercises database_list, version, stored schema SQL, ledger names/batches, table/index/FK metadata, COUNT, settings, integrity_check and foreign_key_check under the installed policy and read-only connection. |
| Prohibited SQL denied? | Nineteen provider cases cover DML, DDL, ATTACH/DETACH, unsafe PRAGMAs, extension loading and VACUUM. Assertions require a denied policy event, not merely any exception. Callback-error and unknown-action controls fail closed. |

The regression fixture's namespaced missing-constant error differs only in PHP namespace qualification. The historical FRR target had no captured action trace; this review verifies the reconstructed source/test evidence rather than inventing a historical trace. No target corruption or recovery requirement follows from that callback error.

The minimal correction is constant qualification. The policy also narrows functions to sqlite_version/count/typeof/like and transactions to BEGIN/ROLLBACK. Its COUNT empty-column READ case and LIKE function support are intentional compatibility details of this narrower profile. The authorizer **does not need to be disabled** for the inspected required query set.

Reviewed SHA-256 pins:

| Source | SHA-256 |
|---|---|
| `backend/tests/Support/OfflineInspectorAuthorizer.php` | `85a67f85b4b7cde737faf1d821cbe0ca72ef35f2d637422cfd444a0f1094660f` |
| `backend/tests/Unit/OfflineInspectorAuthorizerTest.php` | `0fefa104a48b232ac3e9b3fa19efc236d242184e4ae1f9a715e670a5dde73ed5` |

These identify reviewed code, not owner permission or a production trust root. Do not modify the file or broaden the allowlist during working inspection to make an unexpected query pass.

## 3. Test evidence assessment

**REPORTED historical execution, corroborated by prior tool output retained in this session:** OAD's final full command returned:

```json
{"tool":"phpunit","result":"passed","tests":311,"passed":311,"assertions":3463,"duration_ms":56087}
```

The process exited 0. This output supports 311 passed, 3,463 assertions and no reported failures/errors. OAD records zero failures/errors explicitly. It is prior execution evidence, **not an independently rerun result in this review**; no separate durable log artifact was created or discovered as part of this task. The targeted prior output similarly reports 25/25 and 149 assertions, 497 ms.

The inspected source contains the corresponding reproduction/negative/control assertions. Fixture setup creates independently populated temporary files, closes the builder, reopens with OPEN_READONLY, sets query-only/memory temp/FKs and installs the policy. Teardown asserts unchanged fixture hash and only the expected file in its directory. Existing OSG signed-permit denial/replay coverage belongs to the unchanged full suite; this policy itself supplies no owner authorization.

No extra test run was necessary: the relevant prior output is available, inspected source has not changed and this review introduces no implementation. The 311 total is not a forecast, a new execution or fresh working-target evidence.

## 4. Inspector security assessment and remaining risks

| Capability | Readiness / required boundary |
|---|---|
| Canonical identity | Policy has no filesystem opener. Future standalone wrapper must establish existing-file identity, reparse/hardlink policy and a stable no-write/no-delete lease before SQLite open. Historical RTP/FRR identity is a comparison baseline only. |
| Effective configuration | Source permits connection/path/URL overrides. Future approved inspection must privately resolve process/Dotenv/cache provenance without Laravel/providers. Unresolved cache or override ambiguity stops. No real configuration is inspected here. |
| No-create / read-only | Fixture tests exercise OPEN_READONLY and missing-file refusal. Future opener must use that flag alone, never READWRITE/CREATE, a URI mode fallback or immutable-mode shortcut. |
| Query-only / initialization | Fixed connection-local query_only=ON, temp_store=MEMORY, foreign_keys=ON are initialized before policy installation; then read back under policy before BEGIN/inventory. Do not route arbitrary user SQL through initialization. |
| Metadata and ledger | Fixed query categories pass in synthetic evidence. Policy allows broad reads; **it is not a personal-data access filter**. Privacy requires the wrapper's fixed metadata/aggregate query list, not arbitrary SELECT. |
| FK / integrity | Both checks are exercised. Only aggregate violation counts and integrity status may be emitted. Diagnostics may contain row identifiers; never print raw FK-check results or arbitrary integrity error text. |
| Baseline comparison | Compare stored schema-object hashes and exact ledger against the frozen foundation metadata in the existing selection manifest/RTP. This does not invoke that activation artifact or authorize a migration. |

**Remaining execution gates:** separate explicit owner permission; freshly verified target/configuration; stable Windows file sharing/identity; no sidecar/recovery requirement; matching inspected source/runtime profile. They cannot be verified in this review because target access is prohibited. They are checks for the authorized attempt, not reasons to repeat offline correction without new evidence.

Windows FileShare.Read is the reviewed bounded no-write/delete lease, not a guarantee against a privileged filter driver or directory replacement. If final handle/path identity continuity or local filesystem behavior cannot be established, stop. No OneDrive process is not proof that synchronization cannot interfere. Do not pause sync, stop services, alter ACLs or relocate files under read-only permission.

SQLite read-only WAL access can depend on sidecars; FRR-2.0 should conservatively refuse any WAL/SHM/journal or non-1/1 header rather than investigate recovery live. This profile intentionally does not promise to inspect every valid SQLite deployment. Unexpected objects/identifier formats, additional function requirements, BUSY, authorization errors or incompatible initialization stop the attempt. Do not replace the current allowlist with the legacy broad function policy.

No signed production permit infrastructure, operational run register, backup service or working migration launcher is installed by OSG/OAD. Do not use disposable signatures as owner approval or run the memory-only OSG harness against disk. Those deployment capabilities remain separate; FRR-2.0 is the manually authorized standalone read-only inspection already contemplated by RTP/OSG.

## 5. Exact proposed FRR-2.0 execution scope — NOT EXECUTED

### 5.1 Authorization and source preparation

1. Obtain a new explicit approval for **one FRR-2.0 read-only revalidation attempt** and one new report, permitting necessary read-only configuration resolution and file identity/header/hash inspection. Approval must name the candidate below and exclude backup/restore/checkpoint/migration/activation. Earlier approvals are not automatically reused.
2. Pin the policy hash above, source database configuration and existing baseline manifest. Use the known standalone PHP85 binary without Artisan, application/provider boot, testing bootstrap or environment substitution. Require the expected SQLite3 API/constants; any runtime/profile change stops for isolated validation.
3. Keep the standalone invocation fixed and reviewable before target access: structured code through standard input, no generated executable migration, shell-supplied SQL or arbitrary query endpoint. Require only the exact policy source directly; do not register it as a runtime service or import fixture keys/builders. The policy file contains no top-level DB access.

Historical candidate only:

`C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite`

### 5.2 Pre-access checks after authorization

4. Record source-file preservation baseline without displaying secrets. Resolve the existing target, ancestor/reparse attributes, file ID/hardlinks and effective CLI configuration privately. Process variables take precedence over isolated Dotenv parsing; reject non-SQLite driver, nonempty DB_URL, mismatched resolved DB_DATABASE, testing/synthetic substitution, unavailable FK configuration or unresolved cached configuration. Do not boot Laravel to resolve them.
5. Require an already existing, local, readable target. Acquire and hold FileMode.Open / FileAccess.Read / FileShare.Read through the entire PHP child. Refuse sharing violations, unstable identity, unhydrated/ambiguous storage, or sync/replacement risk not excluded by the actual controls. Make no ACL, service or sync change. Verify identity continuity before/after connection and at finalization.
6. Under that lease, inspect the header and sidecar presence. Require SQLite3 signature and read/write versions 1/1; refuse existing `-wal`, `-shm`, `-journal` or any recovery requirement. Capture initial target/config preservation hashes privately. Do not copy the target or create a new database. Failure ends the attempt before SQLite open.

### 5.3 Fixed SQLite sequence

7. Open canonical existing target using **SQLITE3_OPEN_READONLY alone**, enable exceptions, then execute only connection-local `PRAGMA query_only=ON`, `PRAGMA temp_store=MEMORY`, `PRAGMA foreign_keys=ON`. Never set journal_mode/synchronous on working storage. These initialization statements are fixed in advance, not a bypass for arbitrary PRAGMAs.
8. Install the pinned callable policy and require setAuthorizer success. Read back query_only=1, temp_store=2 and foreign_keys=1 under it. On failure or any authorization error, stop without loosening the policy. Record safe action/category diagnostics; do not expose callback data values or raw exception text that could contain records.
9. Execute `BEGIN` for one read snapshot. `PRAGMA database_list` must show exactly one file-backed `main` resolving to the leased target. A connection-local `temp` entry with an **empty filename** is allowed; it is not an attached disk database. Reject every other entry. This avoids FRR's overly strict one-entry assumption when temp appears.
10. Read version and enumerated settings: query_only, temp_store, foreign_keys, data_version, journal_mode, synchronous, page_size, encoding, schema_version, user_version, busy_timeout, compile_options. Require the conservative DELETE profile. Do not infer Laravel PDO settings from this standalone connection.
11. Fixed schema query: `SELECT type,name,tbl_name,sql FROM sqlite_schema WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name`. Retain SQL privately for hashes/structural comparison; do not print arbitrary definitions or default values. Compare the full table/index/trigger/view object map to baseline metadata. Retrieve only `migration,batch` from migrations ordered by id. Compare original nine entries/batches and detect any genuine activation entry without adopting or altering it.
12. Enumerate table_info/index_list/index_info/foreign_key_list only for metadata identifiers validated against the policy's bounded identifier format and safely quoted. Refuse unsupported identifiers rather than rewriting them. Read aggregate table counts only; no application records, audit payloads, emails, tokens or ciphertext. Identify the seven security-table names by schema presence; do not read their record fields.
13. Execute integrity_check and foreign_key_check. Emit only status and violation counts, with no row IDs. Baseline ID-type aggregates may use COUNT/typeof only. Do not copy additional RTP ULID-shape SQL requiring unapproved functions; shape evidence remains historical unless an exact supported query is separately reviewed. No allowlist expansion during inspection.
14. Compare schema, original ledger, audit event CHECK, index/trigger/FK definitions, table presence/counts and integrity status against RTP/manifest. Classify differences as expected, unexpected drift, unsafe/ambiguous or unable to verify; any drift stops eligibility for later work. No repair, ledger insert, normalization or migration follows.

### 5.4 Finalization and report

15. Record ending data_version, finalize statements, `ROLLBACK` the read transaction and close SQLite. On any failure, bounded cleanup attempts only rollback/close; do not mask the original safe error category, reconnect or retry. Keep the Windows lease until target hash, identity and absent-sidecar checks complete. An unexpected file change or sidecar is an incident/failed preservation result, never permission to delete or repair it.
16. Compare protected source/config hashes, release handles in finally and create one new **FRR-2.0** Markdown report with redacted evidence. Preserve FRR-1.0 and every historical report. A possible distinct filename is `XLIPA-B3-2-C-FRR-2-0-READ-ONLY-REVALIDATION-2026-11-07.md`; it is not created now. Report success only for completed observations, otherwise the exact safe blocker. Stop immediately; no automatic backup, migration or runtime stage.

No ready-to-run migration command is part of this plan. Negative DML/DDL tests belong only to disposable fixtures; **never probe writes on the working target**, even to demonstrate rejection.

## 6. Preservation evidence

This review's in-memory SHA-256 baseline covers **228 pre-existing tracked/nonignored-untracked source files**, excluding all SQLite paths/sidecars and `.env` variants before hashing. No working file metadata or real secret was inspected. Historical database hashes in reviewed reports remain historical. Ignored dependency/cache/recovery trees are not exhaustively certified.

**VERIFIED post-report comparison:** all **228 baseline files unchanged**, zero changed/missing; this requested report is the sole new nonexcluded inventory entry. `git diff --check` passed; HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`. This final report-only edit records those results. No code, migration, test, security contract, frontend or previous report was edited. No PHP/test/formatter/runtime, working SQL, backup/restore/checkpoint, provider, commit/push/deploy or activation was executed.

## 7. Owner boundary

**GO — readiness to request a separately authorized FRR-2.0 attempt under section 5.** No authorizer disablement or additional business policy is needed for that fixed scope. Actual execution is **NOT AUTHORIZED by this review** and must stop if any fresh precondition fails.

Suggested separate approval:

> Naidhinisha FRR-2.0 read-only revalidation pekee kwa scope ya section 5 ya readiness review, pamoja na read-only configuration/identity/header/hash checks na ripoti mpya. Hakuna backup, restore, checkpoint, migration, service activation au kubadilisha guards. Sharti lolote likishindwa au SQLite ikihitaji writes, simama.

This wording is proposed, not assumed granted. **Working activation remains NO-GO. STOP for owner review.** XLipa is a WEBSITE supporting TZS only.
