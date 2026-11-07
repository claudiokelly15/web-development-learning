# XLipa — B3.2-C Isolated Activation Package and Rehearsal

**CAR-1.0 · 7 November 2026 · Africa/Nairobi · SYNTHETIC REHEARSAL ONLY**

## 1. Executive summary — Muhtasari

**IMPLEMENTED / TESTED:** a consolidated, explicitly selected activation artifact outside the default migration directory; a frozen final-schema SQL contract and hashed selection manifest; independently populated foundation fixtures; actual Laravel migrator rehearsal, rollback/failure tests and restart quarantine. The artifact creates the final seven accepted tables directly, including the five R06 fields, with no security-table backfill or runtime activation.

**Final targeted package tests:** 18/18 passed, 121 assertions. **Complete C rehearsal:** 69/69 passed, 1,293 assertions. **Full combined suite: 232/232 passed, 3,142 assertions, zero failures/errors.** C includes all 51 existing B feature methods inherited unchanged and run against the consolidated schema. The full run additionally reran the original B suite and existing regressions; earlier B totals are not relabeled as new executions.

Kwa Kiswahili: package imetengenezwa na kujaribiwa kwenye synthetic SQLite pekee. Historical fixture rows, IDs, audit values na constraints zimehifadhiwa. Crash baada ya DDL commit lakini kabla ya ledger log inatambuliwa na restart hukataa ku-adopt schema kimyakimya. Working database haijaunganishwa wala kubadilishwa.

**Important gate:** this delivered artifact deliberately permits **explicit isolated rehearsal only** and refuses working targets before database access. It is not a permission switch for working activation. A separately reviewed working-target authorization guard/release package, fresh preflight and approved backup/recovery remain necessary before working execution. No accepted synthetic guard was weakened or removed.

**Recommendation:** GO for owner/technical review of this package and its isolated evidence. NO-GO to working backup, working migration, runtime activation or production under this task. XLipa remains a website, supports TZS only and receives no financial functionality.

Labels: **VERIFIED** means source/file evidence or newly executed assertions in the stated synthetic environment; **REPORTED** means previous reports, not a new working observation; **NOT TESTED** identifies missing operational evidence; **REQUIRES OWNER APPROVAL** identifies later scope/execution gates.

## 2. Authorization, source evidence and exact file delta

The current instruction authorizes new activation artifacts, isolated synthetic fixtures/tests and one report. Reviewed [RTP-1.0 preflight](XLIPA-B3-2-C-READ-ONLY-TARGET-PREFLIGHT-2026-11-07.md), [CAP-1.0 plan](XLIPA-B3-2-C-PREFLIGHT-AND-ACTIVATION-PLAN-2026-11-07.md), [FOD-1.0 accepted-B decision](XLIPA-B3-2-B-FINAL-OWNER-ACCEPTANCE-DECISION-2026-11-07.md), [OAI-1.0](XLIPA-R06-OPTION-A-SYNTHETIC-IMPLEMENTATION-REPORT-2026-11-07.md), related security/owner/technical contracts and current foundation/synthetic migrations, helpers, framework migrator and tests. Earlier working preflight facts are used as metadata, not as authorization to reconnect or copy records.

Backend AGENTS.md was inspected; `.ai/rules` absent at checked workspace/project/backend locations. No testing-best-practices skill was available in the session catalog; none was installed. No sub-agent/dependency/service was introduced. Existing PHPUnit conventions and isolation bootstrap were retained. Direct artifact creation avoided an ordinary Artisan generator boot against working dotenv; Artisan migration execution occurred only after the established synthetic bootstrap/target checks.

**No pre-existing file changed.** Paths below are relative to `XLipa/`:

| New file | Purpose |
|---|---|
| `backend/database/activation/b32c/2026_11_07_200000_activate_b32_security_contract.php` | Consolidated selected migration, pre-access guard, immediate transaction, audit reconstruction, final empty schema, preservation checks and refusing down. |
| `backend/database/activation/b32c/security-contract.json` | Frozen SQL for seven final tables, 25 supporting indexes and 29 new/accepted integrity triggers, plus union audit definition. No fixture data or secrets. |
| `backend/database/activation/b32c/selection-manifest.json` | Artifact/contract/source hashes, original ledger/schema fingerprints, selection/exclusion policy and explicit working-disabled status. |
| `backend/tests/Support/B32CRehearsal.php` | Memory-only foundation construction, explicit migrator selection and recorded-schema quarantine checks. |
| `backend/tests/Support/b32c-crash-worker.php` | Guarded temp-file subprocess stopping at the post-DDL/pre-ledger barrier. |
| `backend/tests/Feature/B32CActivationRehearsalTest.php` | Package-specific preservation/order/event/failure/drift/crash tests and independent schema comparison. |
| `backend/tests/Feature/B32CContractCompatibilityTest.php` | Reuses existing B assertions unchanged against C schema, replacing only test fixture setup. |
| `XLIPA-B3-2-C-ISOLATED-ACTIVATION-REHEARSAL-2026-11-07.md` | This report. |

Original applied migrations, three accepted synthetic migrations, existing tests/helpers/specifications/reports, frontend, .env and production source remain unchanged from this task's baseline. Prior tracked/untracked work was preserved, not reverted or staged.

## 3. Implementation and transaction protocol

### Frozen contract and parity

Final SQL was generated solely from accepted migrations on guarded in-memory SQLite, then frozen in the JSON asset. It is not generated from working rows, and no runtime call imports test migrations or fixture services. The independent parity test separately executes the original migrations and compares the complete SQLite schema objects against activation output. Trigger installation order is also compared; no approved predicate, FK, state edge, lifetime or audit meaning was omitted.

Seven final tables are identity records, contact versions, onboarding contexts, OTP challenges, delivery intents, contact proofs and verification limit state. Limit state includes the five nullable pending-audit fields and exact challenge/merchant/actor lineage directly at creation. There is no new limit-state rebuild, eighth business table, submission/payment table, currency field, model/service/provider or key provisioning.

### Pre-access and under-lock checks

The artifact requires canonical `b32c.selected` equal to its own explicit path, testing environment/synthetic flag and memory or strictly named disposable temp SQLite with no URL override. It validates its source/SQL hashes and all original migration source hashes. Working target/config strings are rejected with a strict DB facade mock proving refusal before DB calls. This is an intentionally working-disabled boundary; it must not be bypassed by setting the real environment to testing.

It verifies the actual SQLite connection/FK state and idle transaction ownership, then owns `BEGIN IMMEDIATE`/COMMIT/ROLLBACK through PDO. `withinTransaction=false` prevents nesting a manual BEGIN inside Laravel's automatic migration transaction. There is no global DEFERRED-setting change or post-error closure retry.

Under the writer lock, it requires the exact fourteen-table foundation set, nine genuine ledger entries/batches and schema-object fingerprints captured by the earlier authorized preflight. Unexpected/partially activated schemas, changed ledger/source, views or unsupported audit dependencies refuse. FK/integrity checks and old table rows are captured privately for preservation comparisons; no row contents are logged.

### Ordered DDL and preservation

1. Rebuild audit into a transient stage table using the approved sixteen-event CHECK and original nine columns/FKs. Copy all rows directly without casts, JSON normalization, ID changes or time conversion. Checkpoints permit synthetic fault injection after copy/drop/rename/index recreation.
2. Restore the original audit indexes verbatim. Existing non-audit objects and old row values are checked unchanged; the approved audit table definition is the intended schema delta.
3. Create final identity → contact → context → challenge → intent → proof → limit tables, then supporting indexes and triggers in accepted installation order. Audit append-only UPDATE/DELETE guards are installed with the final integrity guards.
4. Require zero rows in all seven new tables; compare every old table's fixture rows including encrypted snapshot/notes bytes and raw whitespace-preserving audit metadata. Recheck old schema objects, FK/integrity and commit. No historical backfill, contact grant or pending obligation is invented.
5. Laravel's real migration repository logs the actual activation as batch **3** after successful `up()`. The previous nine records stay in batches 1/2. There is no handcrafted successful ledger INSERT. Repeat selection with a valid completed schema is a migrator no-op; the selector validates recorded schema before allowing that skip.

`down()` always refuses and deletes nothing. Transaction rollback is the tested precommit recovery. Committed working reversal remains a separate forward-fix/restore decision, not reuse of disposable synthetic down.

## 4. Migration selection and deployment strategy

The artifact is outside `database/migrations`; default discovery does not find it. Tests verify that fact and that the three original synthetic migrations remain present/protected. The rehearsal uses the actual migrator with explicit `--database=sqlite`, the single absolute `--path`, `--realpath` and no-interaction; there is no unfiltered activation scan.

`selection-manifest.json` records these deployment rules:

- Only this named activation artifact and its matching contract are candidates; mismatched hashes refuse.
- The three `2026_11_06_100000`/`100001`/`100002` testing-only migration files must be excluded from a future working release scan through a reviewed release allowlist; their source files/guards remain untouched in development.
- Never mark synthetic migrations applied, copy fixture keys, run Composer setup/key generation, seed, refresh/reset or weaken guards to make an unfiltered migrate work.
- Working execution is **DISABLED** in this revision. No staging/deployer is installed or release deployed. The exclusion manifest is a reviewable build requirement, not evidence that a production release has already been assembled.

Review fingerprints (SHA-256):

| Artifact | SHA-256 |
|---|---|
| Activation PHP | `be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97` |
| Frozen SQL contract | `09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0` |
| Selection manifest | `20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873` |

Hashes identify this revision, not a signature or protection against a privileged actor replacing both files and manifest. Future approval must pin reviewed hashes independently. Any working-mode guard implementation or artifact edit requires new hashes, isolated validation and explicit review; there is no dormant enable-working environment flag here.

## 5. Rehearsal fixtures and crash handling

Foundation fixtures are built by running the actual nine original migrations in two batches against fresh memory SQLite. They independently create two synthetic users/merchants/memberships/applications and original audit history covering all eight old identifiers. Existing application snapshots/notes use public fixture APP_KEY encryption. Raw metadata has deliberate whitespace and timestamps include milliseconds so byte preservation is meaningful. No working record, key or database copy is used.

The compatibility subclass reuses all 51 original B feature methods without editing them. It activates C on a fresh foundation, asserts all seven tables empty, then initializes only synthetic B test bindings. Reflection in this test adapter populates private fixture context; no runtime visibility/permission is changed. Those assertions cover tenant FKs, ULID/AAD policy, OTP/proof lifecycle, R01–R04/R07, rolling failures, pending debt, reconciliation, races and restart behavior on the new schema.

For the ledger gap, a uniquely named guarded temp file is populated only from the test's own synthetic foundation. A separate PHP worker, using isolated bootstrap/no real dotenv, runs the actual selected migrator. The parent kills it after DDL COMMIT and before migration repository logging. On reopen, **21 tables / nine ledger entries** exist, with no activation ledger entry. FK/integrity remain valid. Rerun refuses with quarantine and leaves the ledger untouched. No automatic adoption, fake mark-applied or destructive reset occurs.

A separate negative fixture retains a genuinely logged activation but deliberately drops a new table; selector preflight refuses before Laravel can silently skip it. These are explicit synthetic corruption/crash injections, not working ledger edits. No automated recovery is claimed; the operator must preserve incident state and approve a forward reconciliation or validated restore separately.

## 6. Actual tests and development failures

All commands use existing `C:\Users\hp\.config\herd\bin\php85\php.exe` from `XLipa/backend`, with `vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result`. Test bootstrap forces memory SQLite, empty DB_URL, no working dotenv, fixture keys, array mail/null queues and blocked Laravel HTTP requests. Subprocess files match guarded random temp names and contain only synthetic rows.

| Final execution | Tests / assertions | Failures / errors | Duration |
|---|---|---|---|
| `--filter B32CActivationRehearsal` after final formatting | **18 / 121** | **0 / 0** | 6,001 ms |
| `--filter B32C` complete rehearsal + inherited B contracts | **69 / 1,293** | **0 / 0** | 36,388 ms |
| Full combined suite | **232 / 3,142** | **0 / 0** | 88,042 ms |

The full run comprises the prior 163-case suite (including 55 B/crypto cases and 108 existing regressions) plus the new 69 C cases. Existing regressions therefore ran inside this full command, not as a fictitious separate execution. Every final total above is a new actual execution.

Package tests cover exact schema and trigger order parity; independently populated historical bytes/IDs; one real migration log/batch; final empty tables; all sixteen events and unknown-event rejection; raw append-only guards; default selection refusal; nine phase-specific transaction rollbacks; an actual late DDL collision; schema/ledger drift; refusing down; missing recorded schema and real subprocess ledger-gap restart quarantine. Inherited B tests supply unchanged tenant/security/state/rate/proof/audit assertions.

Development failures are not hidden:

- Initial manifest-generation PHP input failed to parse before creating its manifest; the first smoke test then errored because the manifest was absent. A safe structured/hash-based manifest construction replaced that failed input path. A later oversized Windows command was rejected before execution; the SQL-asset reorder was applied through apply_patch instead. Neither failure touched working storage.
- First complete C run: **68 tests, 67 passed, one failure, 1,287 assertions**. Alphabetical trigger installation changed which rejection fired first for an existing B case containing both a stale version and foreign lineage. The frozen contract was corrected to preserve accepted reference installation order. No B test, constraint or expected rejection was weakened. Targeted parity/tenant rerun passed **2 tests/19 assertions**; subsequent final runs are above. This preserves observed reference behavior but does not promise cross-version SQLite trigger ordering without rehearsal.

Pint `--dirty --format agent` completed successfully; the activation artifact outside default formatter discovery was also explicitly formatted/checked and passed. Final formatter pass made no further change. PHP execution required sandbox escalation and was allowed; no automatic approval rejection. No dependency install or generic application setup ran.

## 7. Preservation evidence

Before writes, an in-memory SHA-256 inventory covered **210 unique pre-existing files**: tracked/nonignored-untracked workspace files plus explicit environment/working SQLite files. Existing four tracked modifications and untracked accepted work were retained as baseline. No preservation inventory file was created. Ignored dependency/cache/recovery trees are not exhaustively certified.

Pre-report comparison: **all 210 unchanged, zero changed/missing**. Only the seven new package/test files and this report are intended additions. Both original and synthetic migrations, old tests/helpers, all previous reports, frontend, .env and unrelated inventoried files are byte-preserved. No .env.testing or real key was created/modified; no secret value printed.

Working database hash remains:

`214AE83A31F53E80D3068D1AEB1366008BB5ACCB2DF212DD2C534AE4121B6D8F`

The working DB was read as a file for hashing only, never connected as SQLite, used as a template, backed up or restored. Earlier preflight metadata supplied only schema/ledger fingerprints; fixture rows were generated independently. Random synthetic process files are removed in finally blocks; final inspection found no matching leftover files and no working WAL/SHM/journal sidecar. This is not an OS-level egress or ignored-cache completeness claim.

**VERIFIED final post-report comparison:** all **210 baseline files unchanged**, zero changed and zero missing. Exactly the eight additions listed in section 2 were found. HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`; `git diff --check` passed. No staging, commit/push/deploy or other project change. This final report-only edit records those results.

## 8. Remaining risks and next approval gate

| Area | Status / required boundary |
|---|---|
| B contract compatibility and inert consolidated schema | Verified in synthetic parity/behavior tests; B acceptance preserved. No automatic production certification. |
| Working execution guard | Intentionally **not enabled**. Owner review must approve a separately implemented target-bound working guard and exact command/release manifest; never reuse testing flags on working SQLite. That later revision must be rehearsed and re-fingerprinted. |
| Release packaging | Explicit path/default-discovery tests pass; deployment exclusion policy is recorded. Actual working release staging/allowlist enforcement and operator tooling are not deployed. |
| Fresh target / consistent backup | No current working SQL, write-quiescence check, working backup or restore rehearsal. Prior read-only snapshot is not a backup or perpetual schema guarantee. Separate authorization/custody/destination/recovery evidence remain necessary. |
| Ledger crash gap | Real subprocess gap is reproduced and quarantined, not automatically repaired. Forward ledger recovery or restore requires evidence and approval; no silent recovery shortcut. |
| Failure/durability scope | Logical transaction/DDL rollback and process termination tested. Physical power loss, disk-full/I/O faults, filesystem/sync durability and every commit-ambiguity state are **NOT TESTED** operational scenarios. |
| Runtime services | No production crypto resolver, OTP generator/verifier, delivery adapter, pending scanner/alerts, readiness authority or submission/review/payment action registered. Test helpers are not promoted. |
| Future real processing | Key custody, lawful collection/normalization, R05 delivery fencing/provider evidence, wider quotas/spend and operational recovery remain separately gated. No new currency or financial table. |

**GO for owner/technical review of this isolated implementation and rehearsal package.** A suitable next authorization is to finalize the working-target guard/release-selection and backup/recovery execution package, with fresh preflight and isolated validation before any working write. Approval of this report alone is not permission to back up, restore or migrate working SQLite.

**NO-GO:** working backup/restore, working migration, runtime activation, real identity/contact collection, live SMS/email, Selcom/financial services, commit/push/deployment under this task. Current artifact cannot be used unchanged as an approved working command.

**STOP for owner review.** Utekelezaji na rehearsal zimebaki synthetic; hakuna working activation. XLipa remains a website supporting TZS only.
