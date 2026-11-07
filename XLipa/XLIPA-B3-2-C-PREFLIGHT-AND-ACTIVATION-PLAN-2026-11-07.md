# XLipa — B3.2-C Preflight and Activation Plan

**CAP-1.0 · 7 November 2026 · Africa/Nairobi · PREPARATION ONLY**

## 1. Executive summary — Muhtasari

**OWNER-APPROVED:** B3.2-B is accepted for its bounded synthetic security-contract scope. This task prepares a proposed C activation package; it does not execute it. The recommended C outcome is **seven empty, inert security tables plus compatible existing audit schema**, preserving all existing rows/IDs and enabling no new collection, verification, delivery or payment operation.

**PROPOSED:** obtain a separately authorized read-only target inventory; build one explicitly selected, target-bound activation migration outside the default synthetic migration scan; rehearse it against independently populated target-shaped synthetic data; establish a verified consistent backup/recovery point under write quiescence; then seek approval of the exact working execution package. No executable artifact is created here.

Kwa Kiswahili: B imekubaliwa. Hatua hii inaeleza namna salama ya kuandaa C, lakini working SQLite haijaunganishwa wala kubadilishwa. Kabla ya activation, target halisi, migration ledger, backup na restore rehearsal lazima zithibitishwe kwa ruhusa tofauti. Tables mpya zitabaki tupu; uwepo wake hautawasha OTP, NIN, SMS au malipo.

**Current recommendation:** GO for owner review of this plan and a narrowly scoped read-only preflight authorization. NO-GO for executable artifact generation under this task, working backup/migration, runtime activation or production. XLipa remains a website, supports TZS only and gains no financial functionality.

Labels: **VERIFIED** = inspected source/file fact in this task; **REPORTED** = prior execution evidence; **UNKNOWN** = target/runtime fact not queried; **PROPOSED** = reviewable future method; **REQUIRES OWNER APPROVAL** = separate scope/execution gate. No runtime, tests, working SQL, backup, checkpoint, restore or migration was run.

## 2. Current architecture and evidence

Reviewed [FOD-1.0 acceptance decision](XLIPA-B3-2-B-FINAL-OWNER-ACCEPTANCE-DECISION-2026-11-07.md), [OAI-1.0 implementation report](XLIPA-R06-OPTION-A-SYNTHETIC-IMPLEMENTATION-REPORT-2026-11-07.md), [OA-1.0 design](XLIPA-R06-OPTION-A-SECURITY-DESIGN-2026-11-07.md), security/technical/owner decisions and actual source. Current owner acceptance supersedes historical pending-B labels without rewriting those documents.

**VERIFIED:** Laravel backend under `XLipa/backend`, public frontend under `XLipa/frontend`; session/email-verified/scoped merchant architecture remains. Composer requests PHP `^8.3` and framework `^13.17`; installed framework source inspected includes its migration transaction/ledger behavior. Earlier inspected installed manifest identifies Laravel 13.35.0. **REPORTED:** PHP 8.5.12, SQLite 3.53.4, Sodium present, latest 163 tests/1,849 assertions including B 55/1,002. These are not new runtime checks or target-schema evidence.

Source inspected: all original users/cache/jobs and six foundation migrations; three synthetic migrations; Merchant/User/Membership/MerchantApplication/AuditLog/AuditEvent and their parent-key contracts; `config/database.php`, Composer scripts, synthetic coordinator/reconciler/verification/crypto boundary/bootstrap and relevant tests; backend AGENTS.md. No applicable `.ai/rules` directory was found. No sub-agent was used.

The accepted B artifacts are:

- `2026_11_06_100000_create_synthetic_verification_contract.php`: seven tables, composite RESTRICT FKs, current/active uniqueness, evidence/transition/chronology guards.
- `2026_11_06_100001_extend_synthetic_audit_contract.php`: guarded audit event-CHECK reconstruction and history-sensitive down refusal.
- `2026_11_06_100002_add_synthetic_pending_audit_obligations.php`: five pending fields, lineage, immutability/exact-discharge and raw audit append-only guards.

All currently live in the normal `database/migrations` directory. Their guards intentionally refuse a working activation target. They are reference contracts, **not production-appropriate executable activation artifacts unchanged**. No test helper is registered as a runtime service. No production crypto/key/capture/provider service exists in this inspected module.

## 3. Target inventory and unknowns

### 3.1 Intended target identity

**Intended candidate, requiring owner confirmation:**

`C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite`

This is the default source-config path and historically protected working file. `config/database.php` permits overrides via DB_CONNECTION, DB_DATABASE and DB_URL. Effective process variables/config cache may differ. **The intended path is not proof of the effective connection.** .env contents were not inspected or changed. This plan treats file contents as restricted even if some existing records may be synthetic.

| Source-defined assumption | What is known / still UNKNOWN |
|---|---|
| Default SQLite; migration repository `migrations` | VERIFIED source default; actual target/ledger/table existence and batches UNKNOWN. |
| SQLite FK setting defaults true; transaction mode DEFERRED | VERIFIED configuration. Actual per-connection FK enforcement, busy timeout, journal/synchronous mode and locks UNKNOWN. No global setting change is implied. |
| users integer IDs; merchants and applications ULIDs; memberships merchant/user pair | VERIFIED migration/model compatibility. Actual stored types, casing, collation, duplicates/orphans and indexes UNKNOWN. Preserve every stored parent ID byte. |
| applications UNIQUE `(id,merchant_id)`, five existing states, immutable evidence model rules | VERIFIED parent contract needed by proof FK. Actual target definitions/drift UNKNOWN. Encrypted snapshots/notes require preserving existing APP_KEY/custody. |
| audit columns id/type/actor/target/merchant/application/request/metadata/occurred_at | VERIFIED original migration; original eight event CHECK plus three indexes and composite application FK. Actual event set, raw row bytes, triggers/views/inbound references UNKNOWN. App enum already has the approved sixteen values; do not infer DB parity. |
| Seven B table names absent from original migrations | VERIFIED original design only; actual absence, partial installation, staging/name collisions and any existing pending obligations UNKNOWN. |
| Path lies under a OneDrive-named directory | VERIFIED path naming; actual sync/reparse/local filesystem behavior UNKNOWN. Require local locking/storage suitability and controlled sync before write work; any relocation is separately authorized. |

### 3.2 Separately authorized read-only preflight specification

Prepare a minimal no-side-effect inspector only after authorization; do not use generic application boot, `migrate:status`, Tinker or full config dumps as a shortcut. First validate explicit canonical path, existence (no create-on-open), file identity/reparse resolution and redacted effective connection settings. Resolve cached config/environment safely without printing credentials or loading providers that may write/send.

Use a proven SQLite read-only open against the confirmed existing file, with write-denying filesystem permissions and query-only defense. WAL read-only opens can depend on existing sidecars or directory access; if opening would create sidecars, recover a hot journal or require writes, **stop**. Do not set immutable mode on a changing database to bypass this. SQLite documents the read-only WAL conditions in its [WAL documentation](https://www.sqlite.org/wal.html#read_only_databases).

Allowed inventory, once separately approved: connection `database_list` and library version; read-only FK/journal/synchronous/busy/page-size/compile-option queries; migration names/batches; sqlite_schema objects and relevant table/index/FK metadata; counts, integrity_check and foreign_key_check; aggregate compatibility checks without displaying personal values. No journal-mode setter, checkpoint, VACUUM, schema migration or ATTACH. Establish a consistent read snapshot; record capture time/data version and known writer activity. A snapshot does not authorize later writes or guarantee no drift afterward.

Produce a private schema/ledger manifest, hashes of reviewed files and a redacted report. Stop on URL/connection mismatch, unrecognized object/ledger state, missing foundation, orphan/constraint errors, audit dependencies not covered by the intended rebuild, inconsistent IDs, unavailable read-only access, hot recovery requirement or partial B objects. Never guess that a ledger entry means its schema matches.

### 3.3 Runtime/capability prerequisites

For the future artifact, pin/rehearse the exact PHP/Laravel/SQLite binaries and filesystem used for activation. Require PDO SQLite, exceptions, FK enforcement, transactional DDL, partial UNIQUE indexes, composite FKs with exact parent UNIQUE tuples, triggers/RAISE, CHECK/typeof, integer-ms arithmetic and the strftime/printf conversion used by pending occurrence validation. Run capability probes on disposable SQLite, not working storage. No JSON1 dependency is needed by the accepted schema.

If reusing PHP implementation patterns, note `array_find_key` in the synthetic rebuild is newer than Composer's PHP 8.3 lower bound; target PHP 8.5 or implement/rehearse an explicitly compatible alternative. Do not copy a helper and assume Composer's lower bound suffices. Sodium is needed for future sensitive services and isolated crypto validation; real keys/Sodium provisioning are not required merely to create empty TEXT columns. Backup API tooling is a separate capability: PDO availability alone does not establish a backup API wrapper/SQLite CLI.

## 4. Proposed migration plan

### 4.1 Artifact selection and ledger safety

**PROPOSED, not generated:** one consolidated migration named, subject to final manifest approval,

`backend/database/activation/b32c/2026_11_07_200000_activate_b32_security_contract.php`

It would create the **final accepted** seven-table schema including OA fields directly, avoiding a gratuitous rebuild of a newly created empty limit table. Audit reconstruction remains necessary only if actual preflight confirms the old CHECK. No original applied or synthetic migration is edited. The future artifact must have a semantic comparison against accepted B constraints/triggers; direct final-form creation is not permission to omit them.

Deployment selection must exclude the three testing-only `100000`/`100001`/`100002` files from the working release's normal migration scan, using a reviewed release manifest/staging process that preserves their source files. They remain available in the development test tree. Do not mark them applied, insert fake ledger rows or loosen their guards. A future release must not accidentally rediscover them through ordinary migration discovery. If this packaging cannot be enforced, stop and approve a different explicit migration-location strategy before implementation.

The exact selected activation file is executed through an explicit connection/path command, never an unscoped `artisan migrate`. A **non-executable template**, to be replaced and validated against installed CLI help in rehearsal, is:

```text
<approved-php> <reviewed-release>/artisan migrate --database=<approved-connection> --path=<approved-absolute-activation-file> --realpath --no-interaction --force
```

There is no runnable approved command yet: file, release root, connection and fingerprints are not finalized. `--force` is not approval or a safety control. Composer `setup` currently includes key generation/migration/install commands; do not run it, generic post-install scaffolding, migrate:fresh/refresh/reset, seeders or key:generate as part of this activation.

### 4.2 Ordered activation algorithm

One reviewed migration/DDL transaction on one confirmed SQLite connection; no network, backfill or runtime service calls:

1. **Before DB access:** require dedicated CLI activation capability, approved canonical target/connection, release/artifact hashes and an unexpired operator-approved run manifest. Reject fixture flag/testing environment, memory/temp targets, URL ambiguity or any target mismatch. These are new activation-specific protections, not removal of synthetic guards. Require externally established quiescence and verified backup/rehearsal records.
2. **Acquire writer before security-dependent schema reads** using an explicitly owned immediate transaction mechanism, rehearsed with Laravel transaction state. Never nest a manual BEGIN inside Laravel's automatic migration transaction. If standard migration wrapping is retained, use a deliberately scoped connection transaction strategy; do not change the application's global DEFERRED setting casually. Bounded acquisition failure aborts, with no partial success.
3. Recheck actual schema/ledger fingerprint, FK=1, dependencies, namespace collisions and absence of all seven B tables under the lock. The approved first-activation baseline must match exactly. Unexpected partially/existing-installed tables refuse; no IF NOT EXISTS adoption or data overwrite. If preflight later shows a valid existing installation, prepare a separately reviewed state-specific plan.
4. Reconstruct **audit first** with the union of original eight and approved eight events, unchanged columns/row values/FKs/indexes/compatible triggers. Compare raw typed content and schema objects, not just counts. Reject unsupported inbound FKs/views/triggers instead of rewriting them heuristically. FK enforcement stays on. Verify old audit data before committing. If the existing CHECK already matches, only an explicitly rehearsed fingerprint branch may preserve it without rebuild.
5. Create `merchant_identity_records`, then `merchant_contact_versions`, then `merchant_onboarding_contexts` with their parent keys/current-pointer checks.
6. Create `phone_otp_challenges` including the OA `(id,merchant_id,actor_user_id)` UNIQUE and all original lineage/active-slot/state/chronology constraints.
7. Create `otp_delivery_intents`, then `contact_verification_proofs` with exact contact/challenge/provenance/application FKs and corrected history/chronology rules.
8. Create final `verification_limit_state` with ten ordered slots, clock/version identity rules and five nullable pending fields, their tuple/FK/unique/scan constraints. Install pending creation/immutability/exact-discharge guards only once their referenced audit/challenge/intent tables exist.
9. Install raw audit UPDATE/DELETE append-only guards after the audit reconstruction; preserve model/enum compatibility. No ordinary audit-history rewrite. All seven tables must still have **zero rows**; no context/proof/identity backfill, synthetic keys or code enters working storage.
10. Validate expected schema/index/trigger manifest, FK/integrity, unchanged existing typed row digests and parent identities, no unexpected DDL, and complete rollback on any failure. Commit only that verified state. Do not enable any new service afterward.

Laravel `Migrator::runUp()` in inspected installed source calls `runMigration()` and then `repository->log()` (lines 253–258); its migration transaction wrapping is in `runMigration()` (432–451). **Do not claim schema and migration-ledger commit are automatically atomic together.** Rehearse a crash after DDL commit/before ledger logging. On restart, a completed schema with missing ledger is a quarantine/NO-GO state for automatic rerun. Preserve the DB and seek reviewed forward ledger reconciliation or approved restore; no manual insert/mark-applied without evidence and approval. Likewise refuse ledger-present/schema-missing state. If later implementation elects a custom atomic DDL+ledger coordinator, its exact code/transaction ownership needs separate review and tests.

### 4.3 Required rehearsal and postflight

On a fresh isolated target-shaped file, reproduce the inventoried schema/ledger using synthetic old users/merchants/memberships/apps, nullable cases, encrypted fixture snapshots and all old audit types. Match binary casing/types/indexes/triggers; use public fixture keys only. No working-data copy by default. Rehearse the actual selected migrator/file/batch, not only direct up/down methods.

Compare final constraints to accepted B, run B raw constraint/crypto/state/accounting cases through an isolated compatibility harness, and existing regressions. Inject failures before/after audit copy/drop/rename/index recreation, during final table/trigger creation and at the ledger gap. Assert full rollback or quarantined committed-state recognition as appropriate. Verify empty final tables, unchanged old row bytes and preserved enum compatibility. Record actual results/fingerprints; none are forecast or claimed here.

Before future working execution, repeat preflight and backup against the current quiescent state; stale rehearsal manifests do not authorize a changed target. Postflight checks must compare existing data, expected ledger delta and empty tables before reopening old application writes. Database file hash is expected to change after successful DDL: preservation then means unchanged old logical/raw typed contents, not an impossible unchanged whole-file hash.

### 4.4 Rollback/recovery boundary

Prefer transaction rollback before commit. After committed inert activation, do not automatically run broad rollback/down. Proposed working down should refuse with a clear forward-fix/recovery instruction; original synthetic down remains disposable-fixture behavior. Even empty-table down/audit-CHECK reversal needs an exact reviewed reverse artifact and must refuse if any new row/obligation/event exists. Never delete/coerce new audit history, clear debt, disable FKs or use writable_schema.

Keep maintenance until postflight succeeds. With writers still stopped and no later legitimate commits, a separately approved restore of the verified preactivation point can recover a failed release. Once writes resume, restoring the old snapshot may erase legitimate commits: preserve incident state and obtain forward-fix/recovery approval. Code rollback alone cannot imply DB/CHECK rollback. Existing APP_KEY must remain available for unchanged encrypted history.

## 5. Backup and restore-to-new-file procedure

**PROPOSED; no working backup/restore performed.** Owner must name operator/custodian, private storage destination, access policy, retention, RPO/RTO and recovery approver. Treat DB, journal, manifests and recovery copies as restricted; backups are not automatically encrypted by SQLite. Select approved encryption/storage protection independently of APP_KEY, with keys recoverable under separate custody. Do not place them in Git, webroot, shared temp or an unreviewed sync folder.

1. Schedule a bounded maintenance window; stop incoming mutations, workers/schedulers/CLI writers and establish that existing sessions/processes cannot reopen writes. Maintenance UI alone is insufficient. Control sync/backup agents and verify local filesystem locking. Record release/target identity, time, free space for copy/rebuild/recovery and storage health. No spontaneous journal mode change.
2. Use a vetted SQLite **Online Backup API** wrapper/tool with exact binary/path version recorded, creating a new unique destination and refusing overwrite. This API produces a consistent snapshot; check completion/error codes and close handles. Primary recommendation is this API, not copying only `database.sqlite` while it is open. [SQLite Backup API](https://www.sqlite.org/backup.html).
3. Determine current WAL/rollback-journal state under the separately authorized procedure. Never delete sidecars or copy main and WAL at different moments. The API copies logical committed database state through SQLite. A hot-journal recovery requirement, checkpoint BUSY/error or unexplained sidecar state stops the run. A checkpoint changes storage and requires the backup/write-window permission; it is not part of read-only preflight. SQLite notes the WAL is part of persistent database state and must accompany an unsafe raw separation; use the engine-aware snapshot instead. [SQLite WAL file](https://www.sqlite.org/wal.html#the_wal_file).
4. Do not substitute a live OS/OneDrive copy. A cold-file alternative requires all connections closed, verified clean checkpoint/recovery and an exact approved file-set capture/restore plan; it is not the default. `VACUUM INTO` is a possible separately reviewed alternative, not an implicit fallback: it creates a logically equivalent compacted copy and can change physical layout/implicit rowids, so byte-identical file comparison is inappropriate. [SQLite VACUUM INTO](https://www.sqlite.org/lang_vacuum.html#vacuum_with_an_into_clause).
5. Validate the backup as a separate file: integrity_check=`ok`, foreign_key_check empty, original schema/ledger/index/trigger and typed row manifests equal at the quiescent point. Both checks are needed; integrity_check does not substitute for FK checking. Hash the closed completed artifact, encrypt/protect it as selected and verify retained artifact/hash after protected transfer. Record safe counts/hashes, never raw identity/profile data in the report. [SQLite integrity checks](https://www.sqlite.org/pragma.html#pragma_integrity_check).
6. Restore/copy the verified backup to a **new unique isolated path**, never over working SQLite. Deny network and disable mail/jobs/providers before any application boot. Keep restore readiness false independently of DB flags. Check integrity/FKs/manifests again. If this contains real existing data, owner permission must explicitly cover that processing and protected target; do not import it into ordinary public-key fixtures.
7. Rehearse the concrete activation on a disposable child of that restored file only when separately authorized. Keep an untouched verified backup. Existing encrypted evidence preservation can be checked as ciphertext; any authorized decryption uses existing real-key custody privately and never injects fixture APP_KEY or prints plaintext. Do not require generating new NIN keys merely to rehearse empty schema.
8. Write a recovery decision record: verified restore path, artifact hashes, consistent point, independent post-backup evidence requirements, responsible people and recovery command proposal. Verify protected backup availability before any migration gate is released.

**Recovery stops:** missing custody/key/backup, mismatched hash/content, integrity/FK errors, exhausted space, unquiesced writer, unknown checkpoint/journal behavior, wrong path, inability to isolate restore or incomplete restore validation. A snapshot predating revocation/obligation/owner change cannot recover those missing commits. Hold restored authorizations, assess independent post-backup evidence/data loss and obtain recovery disposition; never infer freshness from an empty pending queue or elapsed OTP window. A preactivation backup is a valid rollback point only within the explicitly controlled no-later-writes boundary.

## 6. Synthetic-to-runtime gap analysis

| Component | C treatment / future runtime requirement |
|---|---|
| SyntheticKeys, bootstrap APP_KEY, fixed `000123` OTP, fake delivery and process worker | Remain test-only; never copy into runtime or real-data rehearsal configuration. Release autoload/runtime routes must not register them. |
| SyntheticCipher/SensitiveFixtureValue | Reference/test contract only. Later authorized runtime crypto boundary needs maintained Sodium availability, authorized scoped binding/decrypt/masking, protected resolver/custody, rotation/recovery and serialization/error redaction. No ordinary full-NIN accessor. |
| SyntheticCoordinator | Do not rename/promote. Later runtime transaction coordinator needs same-connection ownership, writer-before-read, pre-evaluation retries only, unknown-COMMIT handling and trusted clock/storage readiness integrated with application authorization. |
| SyntheticVerification/consume/revoke | Fixture exerciser only. Future runtime services require current session/auth_version/owner/context checks, exact normalization, safe random OTP generation, lifecycle invalidation and correct success audit. C creates no actions/forms/routes. |
| SyntheticAuditReconciler | Reference for count+obligation then audit+discharge atomicity and exact matching. Later runtime internal permissions, scans/fair retry/alerts/hold recovery are separately implemented; no callable merchant reconciliation endpoint. No broadening delayed audit beyond exhaustion. |
| Seven new Eloquent models | Not currently present. Future models must use explicit ms timestamps/nonstandard version fields, guarded evidence and correct new-ID case; existing HasUlids defaults cannot be blindly applied. Models are not necessary to keep empty tables inert. |
| Provider/send state, R05 fencing | Runtime implementation needs fresh claim/time/context fences, invocation boundary, unknown outcomes and callback authentication. Fake acceptance never proves live possession. |

Preserve original parent bytes/binary identity; uppercase validated ULIDs only in AAD. Preserve domains independent of APP_KEY, six digits/five-minute OTP/five guesses/60-second resend/ten strict rolling failures, proof 30 minutes/no grace/single use, pending no-TTL/no-force-clear and immutable history. Onboarding proof remains neither MFA nor financial authorization. No existing optional profile field becomes a new blanket gate. TZS only; no payment tables.

## 7. Security and preservation controls

The future package must contain exact reviewed SQL/source hashes, deployment migration allowlist, target/config/schema/ledger fingerprints, backup/recovery manifest, rehearsal results, maintenance boundary, named operator and explicit stop/approval checkpoints. Any unexpected schema/data/runtime change invalidates the execution manifest. No secrets in commands/logs or report; no bulk plaintext export or URL credentials.

Read-only preflight cannot guarantee absolute DB-file immutability if other processes keep writing. Record that limitation; do not assert an unchanged live hash as a consistent backup. Actual migration comparisons occur under established write quiescence. Existing application/email paths may already support real delivery; this C plan neither enables them nor authorizes sending smoke-test mail. Rehearsals disable egress and workers before boot.

**This task's preservation:** in-memory SHA-256 baseline covers **208 unique pre-existing files**, Git tracked/nonignored-untracked workspace inventory plus explicit environment variants and working SQLite files. No extra manifest file was written. Working SQLite was hashed as a file only, never connected/copied/restored; hash remains `214AE83A31F53E80D3068D1AEB1366008BB5ACCB2DF212DD2C534AE4121B6D8F`. Ignored vendor/cache/recovery trees are not exhaustively certified. **VERIFIED post-write:** all 208 baseline files unchanged, zero changed/missing; the sole new inventory entry is this report. Existing uncommitted changes remain as found; HEAD `b8bec62ee52fd8fa610c5289fd99692b88b75f6d` unchanged; `git diff --check` passed. This final report-only edit records those results.

## 8. Required owner decisions

No repeat approval of accepted B security policies is requested. Future decisions are:

1. Confirm exact intended working path/host/filesystem, whether sync must pause and whether any relocation is separately planned. Identify read-only inspection operator and permitted redacted outputs.
2. Authorize a specifically bounded read-only preflight, including how read-only WAL/hot-journal refusal is handled; no implicit write recovery or config/provider boot.
3. Approve consolidated activation artifact/deployment allowlist/connection and its guard/transaction/ledger-gap strategy for future implementation. Approve exact target-shaped synthetic harness and results before working use.
4. Select private backup/restore storage and tool/API, encryption/access/custodians, maintenance window, space/budget, retention and RPO/RTO. Explicitly authorize working-data backup/restore rehearsal separately from synthetic testing.
5. Approve exact artifact fingerprints, reviewed command/target, recovery stop criteria and execution window only after evidence exists. Approve reopening existing operations only after postflight. No new security feature is activated by that reopening.
6. Separately scope runtime services, real identity/contact processing, live messaging and payment work under their appropriate stages. Provider/legal/key-custody prerequisites apply before corresponding real processing, not as invented prerequisites to inert schema planning.

## 9. Explicit GO / NO-GO gates

| Gate | Current status | Evidence/authorization needed to proceed |
|---|---|---|
| B accepted synthetic contract | **OWNER-ACCEPTED** | Current owner decision; not reopened by C unknowns. |
| Source-only C planning/report | **GO / performed** | This authorization; one Markdown artifact only. |
| Target read-only preflight | **NO-GO to execute now; GO to request bounded authorization** | Confirm path, no-create read-only inspector and redaction/sidecar refusal scope. |
| Executable activation package + isolated target-shaped synthetic rehearsal | **NO-GO under planning-only task** | Approve artifact/harness/deployment-selection implementation; use preflight manifest, no copied real records. |
| Working backup + protected restore-to-new-file rehearsal | **NO-GO** | Named operator/tool/destination/custody, quiescence and explicit real-data handling permission. |
| Working database migration | **NO-GO** | Passing concrete rehearsal, fresh matching target, verified backup/recovery, reviewed exact command/artifact and explicit execution authorization. |
| Runtime identity/OTP/proof/reconciliation activation | **NO-GO** | Separately implemented/reviewed runtime services, clock/storage/auth/custody/outage/readiness tests and activation approval. |
| Real identity/contact collection | **NO-GO** | Lawful purpose/notices/issuer/normalization evidence, real key custody/recovery and safe authorized capture flows. |
| Live SMS/email in the new module | **NO-GO** | Provider/recipient/security/privacy readiness, delivery fencing, quotas/spend and explicit send authorization. Existing mail is not exercised here. |
| Selcom/payment functionality / production deployment | **NO-GO** | Separate financial/provider/security/legal/operational stages and permission. No additional currency. |

No gate opens automatically when another is approved. Planning a backup, generating a future migration or accepting B is not working execution authorization.

## 10. Recommended next authorized action — Pendekezo

**PROPOSED next task:** authorize a read-only target preflight only, against the confirmed existing SQLite path, with no application/provider boot, no writes/checkpoint/backup and a redacted schema/ledger/capability/dependency report. If safe read-only opening is impossible, report the concrete reason and request a separate controlled maintenance/recovery action; do not escalate silently.

Use that evidence to finalize and separately authorize executable activation-package preparation and synthetic target-shaped rehearsal. Backup/working migration remain later, reviewable approval steps. Current target unknowns are gates to C execution, not reasons to revoke accepted B.

**STOP for owner review.** Hakuna executable migration, working backup/restore, activation, runtime service, test run, frontend/env/secret edit, commit/push/deploy iliyofanywa. XLipa remains a website supporting TZS only.
