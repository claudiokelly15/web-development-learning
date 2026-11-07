# XLipa — B3.2-C Read-Only Target Preflight

**RTP-1.0 · 7 November 2026 · Africa/Nairobi · READ-ONLY OBSERVATION**

## 1. Executive summary — Muhtasari

**GO — next isolated activation-package preparation stage only, subject to its separate owner authorization.** The confirmed working SQLite target has the expected foundation ledger and compatible application/audit parent structure. No partial B installation, unexpected audit dependency, invalid parent ULID shape or integrity/FK error was found in the inspected snapshot.

**Working activation remains NO-GO.** The three pending synthetic migrations are intentionally guarded against working use; an explicitly selected activation artifact/deployment manifest, target-shaped rehearsal and approved backup/recovery/execution package remain necessary. The database audit CHECK still accepts only the original eight events while PHP's enum includes sixteen. This is a verified preactivation compatibility gap, not unexplained corruption; new-event writers must remain disabled until an approved schema change.

Kwa Kiswahili: working database imekaguliwa kwa read-only. Schema ya msingi na ledger vinaendana na hatua iliyotarajiwa; tables saba za B bado hazijawekwa. Hii inaruhusu kupendekeza maandalizi ya isolated package, si kuendesha migration. Hakuna data ya mtu, credentials au records kamili zilizoonyeshwa.

**VERIFIED** = newly observed source/configuration/file/SQL metadata or aggregate in this task. **REPORTED** = earlier execution evidence. **NOT VERIFIED / NOT TESTED** = no current evidence for that claim. **REQUIRES OWNER APPROVAL** = future action. B's accepted status is preserved; it is not reopened by C deployment prerequisites.

SQL inventory captured at **2026-11-06 22:42:42 UTC / 7 November 2026 01:42:42 Africa/Nairobi**. This is a snapshot, not a perpetual target certification.

## 2. Confirmed target identity and read-only controls

Approved and resolved existing file:

`C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite`

**VERIFIED before SQLite open:** file exists, length **204,800 bytes**; canonical PHP realpath and configured path agree. File and every ancestor through `C:\` had no ReparsePoint attribute. Filesystem file ID was recorded as `0x0000000000000000005d0000000213e8`; hardlink enumeration returned only the approved path. No symlink/reparse/alternate hardlink ambiguity was detected. OneDrive sync activity and long-term filesystem suitability remain operationally unverified.

Effective **inspection CLI configuration** was resolved without Laravel boot: source `config/database.php` plus `.env` parsed in memory through Dotenv's isolated array parser and process-variable precedence. Only redacted matches/flags were emitted. There were no process overrides for APP_ENV, APP_CONFIG_CACHE, DB_CONNECTION, DB_DATABASE or DB_URL; no active config cache or environment config-cache override; driver SQLite; DB_URL empty/absent; database path matches the approved file. Effective configured FK setting resolves true. No credentials/APP_KEY values were printed. This does not introspect an already-running web server's private process environment.

Safety layers used:

1. Header read through an ordinary read-only file stream confirmed SQLite3 signature and header read/write format versions **1/1**. WAL, SHM and rollback-journal sidecars were absent before opening; unexpected sidecars/header state would have stopped the attempt.
2. A Windows FileStream opened with **FileMode.Open, FileAccess.Read, FileShare.Read** remained held across inspection. It required an existing file and denied competing write/delete handle access during that interval; no ACL or file attribute was changed. Failure to acquire it would have stopped before SQLite open.
3. Standalone PHP SQLite3 used explicit **SQLITE3_OPEN_READONLY**, without CREATE or READWRITE. This is the documented read-only open mode; SQLite's read-only flag refuses a missing file. [PHP SQLite3 open](https://www.php.net/manual/en/sqlite3.open.php), [SQLite open flags](https://www.sqlite.org/c3ref/open.html).
4. Connection-local **query_only=ON**, temp_store=MEMORY and foreign_keys=ON were set and read back. These are connection protections, not journal/schema/file mutations. An authorizer allowed only SELECT/READ/functions/read transactions and the enumerated metadata/read-only PRAGMAs; other actions were denied. No write was attempted merely to test the protection. Query-only is an additional layer, not a replacement for the read-only open. [SQLite query_only](https://www.sqlite.org/pragma.html#pragma_query_only).
5. Fixed metadata/aggregate statements ran inside one read transaction, ended without writes. No ATTACH, application/provider boot, checkpoint, journal recovery, backup, restore, DDL/DML, migration, seed or test suite ran. No temporary inspector script was written; PHP was supplied through standard input.

`database_list` reported the approved file as `main`; the only other entry was connection-local `temp` with an empty filename, not an attached database or disk sidecar. Data version was **1 before / 1 after**; database SHA-256 matched before/after; all three sidecars remained absent. The Windows guard was released only after SQLite closed.

## 3. SQLite/runtime capabilities and settings

| Observation | VERIFIED value / scope |
|---|---|
| PHP inspector | **8.5.12**, existing Herd PHP85 binary; no install/update |
| SQLite3 extension/library | Available; **3.53.4**, numeric version **3053004** |
| Sodium extension | Available; no secret/key operation performed |
| journal_mode | **delete**, read only; not changed |
| synchronous | **2 (FULL)** on inspector connection; not a power-loss certification |
| page_size / encoding | **4096 / UTF-8** |
| schema_version / user_version | **46 / 0**; observed only |
| query_only / temp_store | **1 / 2 (MEMORY)** after connection protection |
| busy_timeout | **0** on standalone inspector; not Laravel's effective runtime timeout |
| FK enforcement | Initial standalone SQLite3 connection **0**, explicitly enabled to **1** before checks |
| Compile capabilities | THREADSAFE=1, MUTEX_W32; no OMIT_FOREIGN_KEY/OMIT_TRIGGER compile option observed; MAX_VARIABLE_NUMBER=32766; MAX_COLUMN=2000; MAX_TRIGGER_DEPTH=1000 |

**Important distinction:** initial inspector FK=0 is SQLite3's new-connection state, not evidence that the existing Laravel application disables FKs. Source/effective configuration defaults/enables FKs; this inspection did not boot its PDO connection. Every future migration/runtime connection must independently verify FK=1.

Existing tables, composite FKs, indexes and triggers were read successfully. This task did not create a partial index, execute a trigger mutation, prove transactional DDL, probe failure rollback or exercise millisecond conversion through DDL. Those belong to isolated artifact rehearsal. The actual future PDO SQLite library/build and transaction coordinator must be pinned and checked; do not assume the standalone SQLite3 result alone certifies every API binding. No current library limitation blocking isolated preparation was identified.

## 4. Existing migration ledger

**VERIFIED:** nine rows, all corresponding to the nine original migration files; no synthetic B migration or C activation entry. Names/batches were the only ledger fields emitted.

| Migration | Batch |
|---|---:|
| `0001_01_01_000000_create_users_table` | 1 |
| `0001_01_01_000001_create_cache_table` | 1 |
| `0001_01_01_000002_create_jobs_table` | 1 |
| `2026_11_06_000001_add_account_controls_to_users` | 2 |
| `2026_11_06_000002_create_merchants` | 2 |
| `2026_11_06_000003_create_merchant_memberships` | 2 |
| `2026_11_06_000004_create_platform_role_assignments` | 2 |
| `2026_11_06_000005_create_merchant_applications` | 2 |
| `2026_11_06_000006_create_audit_logs` | 2 |

Three source files are absent from this ledger and remain in the default migration directory: `2026_11_06_100000_create_synthetic_verification_contract`, `2026_11_06_100001_extend_synthetic_audit_contract`, `2026_11_06_100002_add_synthetic_pending_audit_obligations`. They would be pending candidates in an unfiltered scan, but their guards refuse working activation. They must not be marked applied or have guards weakened. No migrate:status/application boot was used to establish this list.

The planned `database/activation/b32c/2026_11_07_200000_activate_b32_security_contract.php` remains a proposal, not an implemented/executed migration. Ledger plus observed objects show no partial B activation. A future DDL-commit/ledger-log crash remains a packaging/rehearsal concern, not an observed existing mismatch.

## 5. Schema and dependency inventory

### 5.1 Tables and aggregate counts

**14 user/application/infrastructure tables**, excluding SQLite internal objects; **22 explicit indexes / 30 indexes including automatic indexes**, **six triggers**, **zero views**. No table row values were emitted.

| Table | Rows |
|---|---:|
| users | 1 |
| merchants | 1 |
| merchant_memberships | 1 |
| merchant_applications | 1 |
| audit_logs | 1 |
| platform_role_assignments | 0 |
| migrations | 9 |
| password_reset_tokens | 0 |
| sessions | 0 |
| cache | 0 |
| cache_locks | 0 |
| jobs | 0 |
| job_batches | 0 |
| failed_jobs | 0 |

The seven B names are all **ABSENT**: merchant_identity_records, merchant_contact_versions, merchant_onboarding_contexts, phone_otp_challenges, otp_delivery_intents, contact_verification_proofs, verification_limit_state. No observed transient B rebuild table or r06 object-name collision. Absence means no persisted obligation in those nonexistent structures, not a claim of complete historic security-event knowledge.

### 5.2 Parent/application/audit contracts

**users:** INTEGER primary key with existing account/auth-version/terms columns. **merchants:** TEXT-affinity varchar primary key and existing profile/status fields. **memberships:** INTEGER primary key, UNIQUE merchant/user and expected user/merchant FKs. Aggregate checks of merchants, applications and audits found **zero non-TEXT IDs and zero invalid case-compatible ULID shapes**. No actual ID, case-transformed ID or user value was output.

**merchant_applications:** 15 columns; primary id; merchant_id; attempt/current-slot/status/profile version; encrypted-snapshot storage; submit/review actor/times; feedback/notes; creation/update times. Actual CHECK values remain draft/pending_review/information_requested/approved/rejected and nullable current_slot='1'. UNIQUE `(merchant_id,attempt_number)`, `(merchant_id,current_slot)` and **`(id,merchant_id)`** are present. That final tuple provides the required parent for the new consumed-proof application FK. Two expected application-version triggers enforce positive integer attempt/profile versions.

**audit_logs:** nine columns, unchanged expected nullability: required id/event_type/request_id/occurred_at; nullable actor/target/merchant/application/metadata. Primary id and three expected indexes are present. CHECK accepts exactly the **original eight** events: merchant.created, membership.revoked, application.submitted, application.reviewed, platform_role.granted, platform_role.revoked, account.status_changed, merchant.status_changed. No raw audit metadata, actor identifier, request value or timestamp was selected from records.

Audit/application CREATE statements contain no COLLATE NOCASE override. Original binary parent identity must remain authoritative; no case rewrite is justified. The planned uppercase-only AAD normalization does not alter this requirement.

### 5.3 Index inventory

Observed explicit index column groups below match the inspected foundation intent. Names are the existing Laravel-generated table/column/index or unique names.

| Table | Explicit indexes / UNIQUE groups |
|---|---|
| audit_logs | `(merchant_id,occurred_at)`, `(actor_user_id,occurred_at)`, `(event_type,occurred_at)` |
| merchant_applications | UNIQUE `(merchant_id,attempt_number)`, `(merchant_id,current_slot)`, `(id,merchant_id)`; indexes `(status,submitted_at)`, `(merchant_id,created_at)` |
| merchant_memberships | UNIQUE `(merchant_id,user_id)`; indexes `(user_id,status)`, `(merchant_id,role,status)` |
| merchants | `(operational_status,created_at)`, `(verification_status,updated_at)`, `(registration_country,registration_number)` |
| users | UNIQUE `(email)` — column name only, no email values inspected/output |
| sessions | `(user_id)`, `(last_activity)` |
| failed_jobs | UNIQUE `(uuid)`; `(connection,queue,failed_at)` |
| jobs | `(queue)` |
| cache / cache_locks | `(expiration)` each |

Eight automatic primary-key indexes complete the observed total of 30. INTEGER primary keys do not require a separate automatic index. Platform assignments, migrations and job_batches have no additional explicit indexes beyond their existing key arrangement.

### 5.4 Triggers and foreign keys

The six triggers are exactly INSERT/UPDATE pairs named `users_auth_version_*`, `merchants_profile_version_*`, `applications_versions_*`, with their expected positive-integer predicates. **No audit-table trigger** currently exists; SQL append-only guards are a planned B-contract addition, while existing AuditLog model hooks already prohibit model update/delete.

Observed **13 FK constraints** (14 component rows because one is composite):

- merchants.created_by_user_id → users.id.
- memberships merchant_id → merchants.id; user_id and created_by_user_id → users.id.
- platform assignments user_id and granted_by_user_id → users.id.
- applications merchant_id → merchants.id; submitted_by_user_id and reviewed_by_user_id → users.id.
- audits actor_user_id and target_user_id → users.id; merchant_id → merchants.id; **(application_id,merchant_id) → applications(id,merchant_id)**.

These use RESTRICT deletion and existing NO ACTION update behavior. No inbound FK to audit_logs, no view, and no inspected trigger referencing audit_logs was found. This removes the presently known dependency obstacle to designing its reconstruction; it does not prove a future rebuild will succeed without rehearsal. Existing sessions.user_id is indexed but has no declared FK, matching its original migration design rather than new drift.

Metadata fingerprints retained for the two principal compatibility targets (SHA-256 of stored CREATE SQL, not row contents):

- audit_logs: `7aafd23afa9cb08ca36a2f8298d1849acd1a7ef632084f854cd43b8afca9166e`
- merchant_applications: `3175141adaa030285ffce72428edc65c223bfec69c795d7e8bb9685ebb27f025`

These are snapshot identifiers, not authorization to adopt a future different schema automatically. Metadata inventory remained in memory; this report is the sole new on-disk artifact.

## 6. Integrity and foreign-key findings

**VERIFIED:** integrity_check returned one result, **`ok`**; foreign_key_check returned **zero violations**. No offending records were extracted. Aggregate parent-ID type/shape checks returned zero exceptions in all three inspected ULID tables. Data version and file hash remained unchanged across the protected read interval.

These results establish current structural consistency and declared-FK consistency in the snapshot. They do not verify legal identity, contact ownership, model-level metadata rules for every stored payload, current business authorization, key recoverability or storage behavior under power loss. No encrypted profile evidence was decrypted. No provider was contacted.

## 7. Compatibility assessment

| CAP-1.0 expectation | Actual finding / assessment |
|---|---|
| Foundation ledger and existing parent tables before C | **Confirmed:** original nine migrations/two batches, 14 expected tables. No unexplained additional table/view or partial B installation observed. |
| Integer user and preserved ULID parents | **Compatible:** expected storage/key definitions, zero invalid aggregate ULID shapes. Keep exact stored bytes; no normalization/backfill. |
| Application composite parent key | **Confirmed:** required UNIQUE id/merchant tuple present. Application statuses/version guards unchanged. |
| Existing audit CHECK requires union with B events | **Confirmed gap:** DB accepts eight; current PHP enum sixteen. New-event writes would not be compatible yet. Keep new paths inert; prepare reviewed compatible audit artifact. |
| Audit dependency/collision risk | **No current unexpected dependency found:** zero inbound audit FKs/views/audit triggers; existing three indexes must be preserved. No B rebuild object found. Recheck before any future execution. |
| Final seven tables plus R06 fields | **Not installed**, as expected. Prepare final-form empty tables; no legacy scope-table rebuild or debt migration is needed for this observed initial target. |
| Default migration discovery safety | **Conflict confirmed:** three unapplied synthetic-only files in normal scan. Exact deployment allowlist/path strategy must prevent their accidental working selection. |
| Backup/recovery readiness | **Not established:** unchanged file hash/read snapshot is not a backup or restore rehearsal. No backup was performed. |

**Actual unexplained schema drift:** none identified in the inventoried foundation definitions, keys, indexes, version triggers and application/audit checks compared with source. The known PHP-enum/DB-CHECK difference is deliberate unactivated B work. This does not claim exact generated-DDL equivalence for every historical runtime or exhaustive validation of all model-only invariants.

## 8. Activation blockers and remaining unknowns

The following block **working activation**, not the next isolated preparation task or already accepted B:

1. **No reviewed executable C artifact/manifest yet.** Existing synthetic migration guards must stay intact; no fake applied ledger entries or unfiltered migrate command.
2. **Audit enum/CHECK compatibility gap.** Preserve the one existing audit row's raw values and all original indexes/FKs through an approved union-CHECK reconstruction. Install append-only guards at the correct dependency point; test failures late in reconstruction.
3. **Concrete migration/release rehearsal missing.** Generate only after separate authorization; rehearse exact manifest, transaction ownership and actual migrator ledger/batch behavior. Laravel's post-DDL ledger logging gap remains relevant; do not treat observed healthy ledger as proof against a future crash.
4. **No authorized consistent backup/restore-to-new-file evidence or write-quiescence procedure executed.** Do not use this read handle/hash as a replacement. Name custody, storage, operator, recovery procedure and execution window before working writes.
5. **OneDrive/local-storage and process readiness not certified.** Path/reparse checks passed, but sync controls, filesystem locking under load, space, writers and long-term durability require their own operational checks. No sync setting/process/service was modified.
6. **Freshness and connection scope.** Future execution must recheck matching path/file/schema/ledger and FK=1 on its actual connection. This CLI inspection does not certify a running web process's configuration or future PDO/backup-tool capabilities.

No new SQLite limitation or target corruption was found that requires stopping isolated artifact preparation. Production crypto/key custody, runtime lifecycle/delivery fencing, scanner/recovery operations, real processing/legal/provider/spend controls remain later gates; they are not invented prerequisites to preparing inert DDL.

## 9. Preservation evidence and execution record

Before inspection, in-memory SHA-256 baseline covered **209 unique pre-existing files**: tracked/nonignored-untracked workspace files plus explicit environment variants and working SQLite files. Existing modified/untracked B work was retained as found. No baseline/inspector file, data copy or extra manifest was written. Ignored vendor/cache/recovery trees are not exhaustively certified.

Working database SHA-256 before and after the SQL interval:

`214AE83A31F53E80D3068D1AEB1366008BB5ACCB2DF212DD2C534AE4121B6D8F`

WAL/SHM/journal files were absent before and after; no new sidecar appeared. Configuration parsing read only what was needed in memory, printed no secrets and changed no .env/process configuration. PHP loaded Composer's autoloader for the isolated Dotenv parser only; the SQL inspector did not boot Laravel. FK/query-only/temp-store changes were connection-local defenses, not persisted configuration changes.

Commands comprised source/Git/file metadata/hash reads, Windows file-ID/hardlink enumeration, standalone PHP version/config/header checks and the restricted SQLite inventory. PHP runtime access required sandbox escalation and was allowed; no automatic approval rejection occurred. An initial non-escalated runtime-path metadata lookup was denied by the filesystem sandbox; no database open was attempted through that failed lookup. No tests, migrations, setup, seeders, formatter, backup, restore, checkpoint, journal recovery, provider, commit/push/deploy or runtime activation occurred.

**VERIFIED final post-report comparison:** all **209 protected baseline files unchanged**, zero changed and zero missing. The sole new nonignored inventory entry is this requested report. All three sidecars remain absent. HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`; `git diff --check` passed. Source/report/migration/frontend/secret files and working identity/data were not changed by this inspection. This final report-only edit records those results.

## 10. GO / NO-GO and next stage — Hatua inayofuata

**GO recommendation: authorize the next isolated C activation-package implementation and target-shaped synthetic rehearsal only.** Use the observed initial foundation schema/ledger, preserve original parent identity, create final seven-table form directly and explicitly select the artifact outside accidental synthetic discovery. Reproduce the audit CHECK/index/FK structure and recorded dependency conditions with independently populated synthetic rows; do not copy working records into normal fixtures.

This is a recommendation, **not permission to begin that implementation in this task**. No further working SQL, migration generation, test execution or backup follows this report automatically.

**Working backup/restore, migration/activation, runtime identity/OTP services, real collection, live SMS/email, Selcom/payments and deployment: NO-GO without their separate approved packages and execution authorization.**

**STOP for owner review.** Read-only inspection imekamilika; working SQLite na sidecars hazijabadilishwa. XLipa remains a website supporting TZS only.
