# XLipa — B3.2-C FRR-2.0 Read-Only Revalidation

**FRR-2.0 · 7 November 2026 · Africa/Nairobi · COMPLETED READ-ONLY SNAPSHOT**

## 1. Executive summary

**VERIFIED: read-only revalidation completed successfully.** The existing target matches RTP-1.0's foundation schema-object fingerprints and original nine-entry migration ledger. All seven B security tables remain absent. Integrity check returned **ok**; foreign-key check found **zero violations**. No unexpected schema or ledger drift was observed.

The corrected authorizer remained installed throughout inspection: **235 allowed callback events, zero denied events**, no SQLITE_AUTH error and no retry. The Windows read-only/no-write-delete sharing lease remained held until SQLite closed and preservation checks completed. Database SHA-256 and file ID were unchanged; WAL/SHM/journal sidecars were absent before and after.

**GO for owner review of this completed snapshot and separately scoped next-stage preparation. Working activation remains NO-GO.** This observation authorizes no backup, restore, checkpoint, migration, runtime activation or deployment.

Kwa Kiswahili: FRR-2.0 imepita; schema na ledger zinaendana na baseline, na working database haijabadilishwa kwenye muda wa ukaguzi. Tables saba bado hazijawekwa. Hakuna hatua inayofuata imeanzishwa.

SQL snapshot timestamp: **2026-11-07 00:21:19 UTC / 7 November 2026 03:21:19 Africa/Nairobi**. This is a bounded observation, not a perpetual target or production certification. XLipa remains a WEBSITE supporting TZS only.

Labels: **VERIFIED** = newly observed file/configuration/SQL evidence in this task; **REPORTED** = prior execution evidence; **NOT VERIFIED** = no evidence obtained here; **REQUIRES OWNER APPROVAL** = separate future action.

## 2. Authorization and reviewed inputs

The owner's explicit authorization covers only section 5 of [FRP-1.0 readiness review](XLIPA-B3-2-C-FRR-2-0-READINESS-REVIEW-2026-11-07.md), including read-only configuration, identity, header/hash checks and one new report. The historical FRR-1.0 report remains unchanged and accurately records its earlier incomplete attempt.

Readiness scope, actual policy source, database configuration and baseline manifest were checked before the single invocation. Relevant RTP/FRR/OAD/WSR/OSG evidence remains as reviewed in FRP-1.0. This task uses their baseline metadata, not their conclusions as a substitute for current SQL observations.

Pinned policy SHA-256:

`85a67f85b4b7cde737faf1d821cbe0ca72ef35f2d637422cfd444a0f1094660f`

Pinned existing selection-manifest SHA-256:

`20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873`

Both matched before database opening. The manifest was read solely as baseline metadata; no activation artifact, migrator, synthetic guard/bootstrap or fixture builder was executed. No security guard or source code was changed.

## 3. Target identity and pre-access safety

**Confirmed existing canonical target:**

`C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite`

| Observation | VERIFIED result |
|---|---|
| File existence / size | Existing file, **204,800 bytes**; no create-on-open path |
| File identity | `0x0000000000000000005d0000000213e8`, matching RTP/FRR; same before/after inspection |
| Hardlinks | Exactly one enumerated link, approved path |
| Ancestors / target | No ReparsePoint, Offline or RecallOnDataAccess flag observed |
| OneDrive process check | No process named OneDrive observed; no process/service/sync setting changed |
| File lease | FileMode.Open / FileAccess.Read / FileShare.Read acquired and held across PHP and final checks |
| Header | SQLite format 3 signature; read/write format bytes **1/1** |
| Sidecars | `-wal`, `-shm`, `-journal` absent before open and after close |

The lease provides the reviewed bounded write/delete-sharing restriction without ACL changes. Same file ID, stable hashes and canonical connection identity support continuity during this attempt. This is not an attestation against privileged filter drivers or every directory-replacement attack, nor proof of long-term OneDrive/storage durability. No filesystem relocation, sync pause, service shutdown, journal recovery or immutable-mode shortcut was used.

## 4. Effective configuration and connection controls

**VERIFIED inspection CLI configuration:** source database settings plus `.env` parsed privately through isolated Dotenv, with process-variable precedence, without Laravel boot. No process override was found for APP_ENV, APP_CONFIG_CACHE, DB_CONNECTION, DB_DATABASE or DB_URL. Config cache/override was absent; effective driver SQLite; DB_URL empty; configured canonical database matched the target; configured FK setting true. Testing/synthetic substitution was absent.

No credential, APP_KEY, personal value or full configuration dump was emitted. `.env` preservation digest matched before/after privately; its digest is not published here. A running web server's private process configuration remains **NOT VERIFIED**.

Standalone SQLite3 used **SQLITE3_OPEN_READONLY only**, without CREATE/READWRITE. Fixed connection-local protections were initialized, the pinned callable authorizer was installed successfully, then protection values were read back before the snapshot. No arbitrary SQL initialization hook or provider ran.

| Runtime / inspector setting | VERIFIED value |
|---|---|
| PHP / SQLite3 library | **8.5.12 / 3.53.4**, matching the reviewed profile |
| query_only / temp_store / foreign_keys | **1 / 2 (MEMORY) / 1** |
| journal_mode / synchronous | **delete / 2 (FULL)**; read, not changed |
| page_size / encoding | **4096 / UTF-8** |
| schema_version / user_version | **46 / 0** |
| busy_timeout | **0** on this standalone connection |
| data_version | **1 before / 1 after** |
| Compile options | 41 observed; no OMIT_FOREIGN_KEY/OMIT_TRIGGER entry |

`database_list` showed exactly the approved file-backed main, no other database. The invocation would allow only an empty-filename temp entry in addition; no attached database was present. These settings do not certify Laravel PDO settings, power-loss behavior or deployed operational infrastructure.

One BEGIN read snapshot covered metadata/ledger/count/integrity queries; statements were finalized, ROLLBACK ended the read transaction and SQLite closed before final file checks. Authorizer diagnostics recorded **235 allowed events / zero denials**. No authorizer disablement, allowlist expansion, callback replacement or second connection attempt was required.

## 5. Schema and ledger comparison

### 5.1 Complete foundation object comparison

All **42 non-internal schema objects** matched the existing baseline map exactly by SHA-256 of their stored SQL: **14 tables, 22 explicit indexes, six triggers, zero views**. PRAGMA metadata additionally enumerated **30 indexes including eight automatic primary-key indexes**. No unexpected/transient object or partial activation was found.

| Table | Aggregate rows | RTP comparison |
|---|---:|---|
| users | 1 | Matches |
| merchants | 1 | Matches |
| merchant_memberships | 1 | Matches |
| merchant_applications | 1 | Matches |
| audit_logs | 1 | Matches |
| migrations | 9 | Matches |
| platform_role_assignments | 0 | Matches |
| password_reset_tokens | 0 | Matches |
| sessions | 0 | Matches |
| cache | 0 | Matches |
| cache_locks | 0 | Matches |
| jobs | 0 | Matches |
| job_batches | 0 | Matches |
| failed_jobs | 0 | Matches |

All seven names remain **ABSENT**: merchant_identity_records, merchant_contact_versions, merchant_onboarding_contexts, phone_otp_challenges, otp_delivery_intents, contact_verification_proofs, verification_limit_state. Absence is a schema observation, not proof of complete historical security-event knowledge.

### 5.2 Ledger

The exact nine migration names/batches matched the baseline, with no synthetic B or C activation entry:

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

Only migration names/batches were retrieved from ledger rows. No successful migration record was fabricated, adopted or removed.

### 5.3 Application, audit, indexes and FKs

Current stored CREATE-SQL fingerprints match RTP:

- audit_logs: `7aafd23afa9cb08ca36a2f8298d1849acd1a7ef632084f854cd43b8afca9166e`.
- merchant_applications: `3175141adaa030285ffce72428edc65c223bfec69c795d7e8bb9685ebb27f025`.

The audit CHECK therefore retains exactly the original eight-event definition, not the later sixteen-event union. This is the **expected unactivated compatibility gap**, not unexplained drift. Its original nine columns, three explicit indexes and application/merchant/user FKs remain. No inbound audit FK, audit trigger or view was found in the complete current object/FK inventory.

Applications retain the expected five-state CHECK, nullable current slot and UNIQUE `(id,merchant_id)` parent key, plus attempt/current-slot uniqueness and original indexes. Six version triggers remain the users_auth_version, merchants_profile_version and applications_versions INSERT/UPDATE pairs. Their exact fingerprints match baseline. No COLLATE NOCASE substitution or parent-ID rewrite occurred.

PRAGMA inventory observed **13 FK constraints / 14 component rows**, including audit `(application_id,merchant_id)` → applications `(id,merchant_id)`, with original RESTRICT deletes and NO ACTION updates. Integer user IDs and text-affinity merchant/application/audit IDs remain compatible. Aggregate non-TEXT ID counts for merchants, applications and audits were all **zero**. No individual ID was retrieved or printed. ULID-shape value checks were not repeated because FRP limits this run to its supported query profile; their earlier evidence remains historical.

## 6. Integrity, drift and interpretation

**VERIFIED:** integrity_check returned exactly **ok**; foreign_key_check yielded **zero violations**. No raw diagnostic row IDs or personal records were emitted. No encrypted evidence was decrypted, audit payload selected or provider contacted.

| Comparison category | Disposition |
|---|---|
| Foundation objects / original ledger / counts | **Expected; matching** |
| Seven B tables absent | **Expected; no activation** |
| Old audit event CHECK | **Expected preactivation gap** |
| Unexpected schema, index, trigger or ledger drift | **None observed** |
| FK/integrity problems or authorizer error | **None observed in this attempt** |
| Web-process settings, real custody, backup/recovery, storage/sync durability | **Unable to verify / outside this inspection** |

FRR-1.0's authorizer blocker did not recur with the pinned correction. Its historical report remains accurate; this new report supplies the previously missing completed SQL observations. No data/schema discrepancy was repaired, and a clean inspection does not remove later-stage authorization gates.

## 7. Preservation evidence

Source-only in-memory baseline covered **229 pre-existing tracked/nonignored-untracked files**, excluding SQLite paths/sidecars and `.env` variants from that source inventory. Separate explicitly authorized target/configuration preservation checks were performed under the inspected read-only sequence. Ignored dependency/cache/recovery trees are not exhaustively certified. No baseline manifest, inspector script, data copy or extra artifact was written.

Database SHA-256 before and after the leased interval:

`214AE83A31F53E80D3068D1AEB1366008BB5ACCB2DF212DD2C534AE4121B6D8F`

Both matched RTP/FRR's recorded digest. File ID remained unchanged; sidecars remained absent; `.env` digest remained unchanged. Hash equality is preservation evidence for this interval, **not a backup, restore rehearsal or permanent freshness guarantee**.

The standalone PHP was supplied via standard input. Composer autoload supplied the isolated Dotenv parser; only the pinned policy source was directly required for authorization. No Laravel/Artisan/testing bootstrap, test suite, formatter, migration, seed, setup, backup, restore, checkpoint, journal recovery, service/sync/ACL change or runtime activation occurred. Runtime access required sandbox escalation and was allowed; no approval rejection or inspection retry occurred. Safe schema metadata/aggregate outputs contained no personal values or secrets.

**VERIFIED final post-report comparison:** all **229 source-baseline files unchanged**, zero changed/missing; this requested report is the sole new nonexcluded inventory entry. `git diff --check` passed. HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`; no staging, commit/push/deploy. This final report-only edit records those results.

## 8. GO / NO-GO and stop boundary

**GO — FRR-2.0 completed; accept this read-only snapshot for owner review and separately authorized preparation.** No further authorizer diagnosis or repeated read-only review is demanded by these results. Recheck freshness when a future approved action actually depends on it.

**Next proposed authorization:** finalize the still-disabled working-target execution/release-selection package and exact backup/recovery tooling, custody, destination and maintenance scope, using isolated synthetic validation only where implementation is needed. Existing CAR/OSG/OAD evidence should be reused; do not reopen accepted B security policies without new evidence. This is a proposed scope, not work begun here.

Before any working write, separate approvals and evidence remain necessary for working backup, protected restore-to-new-file rehearsal and the exact working migration package. Current rehearsal guards stay unchanged and working-disabled. Runtime activation, real identity/contact processing, live messaging and Selcom/payments remain independent later permissions. No approval is combined or inferred from successful SQL inspection.

**Working activation remains NO-GO. STOP for owner review.** XLipa is a WEBSITE supporting TZS only.
