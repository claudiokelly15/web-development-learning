# XLipa — B3.2-C Working-Target Safety and Recovery Package

**WSR-1.0 · 7 November 2026 · Africa/Nairobi · PREPARATION / DESIGN ONLY**

## 1. Executive summary — Muhtasari

**Working activation remains NO-GO.** This document specifies a fail-closed working-target guard, release-selection contract and operator runbook. It enables no execution path and changes no approved migration, frozen SQL, manifest, test or runtime service. No working SQLite file, sidecar or secret file was opened, hashed, copied or inspected in this task.

Kwa Kiswahili: hii ni package ya maandalizi ya usalama na recovery. Guard inayopendekezwa itahitaji ruhusa ya action moja, target iliyothibitishwa na hashes za release zilizokubaliwa. Backup, restore na migration kila moja itahitaji ruhusa yake; hakuna iliyofanywa hapa. Synthetic/testing flags hazitatumika kama njia ya kuingia kwenye working database.

**GO for owner review of this preparation package.** Recommended next authorization: implement and validate the proposed guard/selector in an offline, synthetic-only harness with working execution still hard-disabled. After that, separately authorize fresh read-only target revalidation. No approval here is inferred for backup, restore, migration or runtime activation.

**VERIFIED** = source/document/artifact evidence inspected now. **REPORTED** = earlier execution/working-target observation, not repeated. **PROPOSED** = unimplemented protocol/runbook. **REQUIRES OWNER APPROVAL** = an outstanding action, custody or execution decision. The operational sequence below is actionable after its named inputs and authorizations exist; it is not an installed utility or an executable working command today.

XLipa remains a website supporting TZS only. Accepted seven-table business scope, binary tenant identity, AAD-only uppercase normalization, OTP/proof values, separate encryption domains and exhaustion-only delayed audit policy remain unchanged.

## 2. Current evidence and source limits

Reviewed [CAR-1.0 isolated rehearsal](XLIPA-B3-2-C-ISOLATED-ACTIVATION-REHEARSAL-2026-11-07.md), [RTP-1.0 read-only preflight](XLIPA-B3-2-C-READ-ONLY-TARGET-PREFLIGHT-2026-11-07.md), [CAP-1.0 activation plan](XLIPA-B3-2-C-PREFLIGHT-AND-ACTIVATION-PLAN-2026-11-07.md), [FOD-1.0 bounded B acceptance](XLIPA-B3-2-B-FINAL-OWNER-ACCEPTANCE-DECISION-2026-11-07.md), actual activation PHP/SQL/selection JSON, `B32CRehearsal`, package tests, database configuration, backend AGENTS.md and installed Laravel migrator. `.ai/rules` was absent at checked workspace/project/backend locations. No sub-agent or dependency/skill installation was used.

**VERIFIED current source:** the activation artifact resides outside normal migration discovery, requires explicit selection plus an isolated fixture environment/path, checks its manifest/source hashes, owns BEGIN IMMEDIATE with `withinTransaction=false`, and refuses destructive down. Its working mode is disabled. `B32CRehearsal` adds recorded-schema validation before Laravel can skip an already logged migration, but that selector is test support, not a deployed working launcher.

**VERIFIED Laravel behavior:** `Migrator::runUp()` runs the migration and then logs its repository row; `runMigration()` handles migration transaction wrapping. DDL commit and ledger logging are not automatically one atomic operation. Both gap directions must be checked outside the migration before skip/rerun decisions.

| Evidence | Status here |
|---|---|
| 232 tests / 3,142 assertions, zero failures/errors; C subset 69/1,293 | **REPORTED** CAR executions; no tests rerun in this design task. |
| Working target previously had 14 tables, original nine ledger rows, old eight-event audit CHECK, no B tables, integrity/FK checks clean | **REPORTED** RTP snapshot; not current working observations. |
| Actual source config permits DB_CONNECTION/DB_DATABASE/DB_URL overrides and defaults to SQLite, FK true, DEFERRED | **VERIFIED source**, not an effective environment inspection now. |
| OneDrive-named working path, previous file identity and journal state | **REPORTED only**; no filesystem identity/sidecar check repeated. |
| Approval of B synthetic result | Owner decision retained; deployment preparation does not revoke it. |

Newly verified SHA-256 fingerprints of the existing approved package:

| File under `backend/database/activation/b32c/` | SHA-256 |
|---|---|
| `2026_11_07_200000_activate_b32_security_contract.php` | `be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97` |
| `security-contract.json` | `09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0` |
| `selection-manifest.json` | `20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873` |

These files remain immutable in this task. Matching hashes identify a reviewed revision, not authorization to execute it or a signature against an attacker replacing both artifact and manifest.

## 3. Proposed working guard and authorization contract

### 3.1 Separate entry point; no fixture promotion

**PROPOSED:** a new, standalone CLI-only working selection/preflight wrapper and a separately reviewed working activation revision. Neither exists/enables working execution here. Do not modify the approved rehearsal migration or its guards. Do not import SyntheticKeys, SyntheticCoordinator, SyntheticVerification, fault-injection closures or fake delivery into working code. A working revision may reuse the reviewed frozen SQL only through pinned hashes and parity tests.

The wrapper must validate authorization **before application boot, database connection, data hashing or backup access**. It must not expose HTTP routes, worker triggers or a permissive environment switch. Missing inputs return a bounded safe error. The new working artifact must also require the validated one-run context so direct `migrate --path` cannot bypass the wrapper.

Working-local and future production are distinct target classes. Pin the actual approved environment name; reject testing, synthetic flag, fixture keys/bootstrap or memory/temp target substitution. An approved local working database does not require pretending APP_ENV=production; it does require an explicit non-testing target class and operator authorization.

### 3.2 Exact proposed per-action permit

Each permit authorizes **one** action: `read_only_revalidate`, `backup`, `restore_rehearsal`, `working_migrate`, `runtime_activate`, `real_identity_contact`, or a separately named provider/payment action. No wildcard or action list. Fields must include:

- Contract version, unique run UUID, permit UUID, owner approval reference, named operator OS principal, approved host/target class, action and UTC not-before/expiry/max-duration.
- Exact canonical source path plus approved volume/file identity and protected parent-directory identity; destination identity for backup/restore actions. The historical candidate is `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite`; current identity remains to be revalidated under separate permission.
- CLI/PHP/SQLite versions and hashes; exact connection name/driver; allowed configuration provenance; selected artifact basename/path; immutable release, artifact, contract, foundation and manifest hashes.
- Accepted preflight/schema/ledger fingerprint and capture/run IDs; expected initial/final state; quiescence record; backup/recovery IDs where required; explicit resume-existing-operations policy (default denied). No key bytes, credentials or personal data.

**PROPOSED trust mechanism / REQUIRES OWNER APPROVAL:** detached signature over the exact permit bytes, verified against a separately protected approved public key; record the permit digest in the owner's independent approval record. Define signer custody/provisioning/revocation before implementation. No signing key or approval service is created/claimed here; APP_KEY and fixture keys are forbidden for this role. A protected offline approval workflow may be selected instead only after documenting equally concrete authenticity/replay controls.

The operator cannot edit a permit into authority. A signature-valid permit still requires matching action/operator/host/target/hash/time and no revocation. Do not trust an approval boolean, CLI `--force`, a mutable repository JSON alone or possession of a database file.

### 3.3 Replay, clock and crash rules

Use an independently protected operational run register outside the application database, with atomic create-if-absent claim on permit/run UUID. No eighth business table is proposed. Register states: **issued → claimed → completed**, or **claimed → failed/unknown/quarantined**. No automatic return to issued. Claim occurs before an action's first authorized connection/write; an interrupted attempt consumes the permit and requires disposition/new authorization. Signed permits without replay state are insufficient.

Run-register availability, trusted UTC/monotonic elapsed clock or authorization freshness failure denies the action. Recheck expiry, identity and cancellation immediately before the destructive stage/transaction; bound retries to acquisition before DDL. No automatic re-execution after unknown commit, lost connection or expired approval. Protect register backups independently so restoring an application snapshot cannot reactivate old permits. Operational register custody and retention require approval; no deployed infrastructure is assumed.

### 3.4 Guard sequence and drift checks

| Phase | Required check / refusal |
|---|---|
| Before any target access | Validate permit, trust root, replay claim, operator, host, executable/release hashes and explicit action. Reject testing/synthetic substitution, unexpected paths/URLs/connection drivers, writable bootstrap hooks and unresolved config cache/provenance. No ordinary Laravel provider boot. |
| Authorized identity inspection | Open existing path with no create semantics; verify canonical path, resolved final handle path, volume/file ID, parent directories and reparse/hardlink policy against approval. Unexpected alias, replacement, missing file or inability to keep identity stable stops. No data/path normalization workaround. |
| Effective config | Resolve approved .env/process/cache precedence privately with redacted outputs. DB_DATABASE may be present only if exactly the approved resolved target; DB_URL override or unapproved connection/provenance rejects. Preserve secrets; do not dump config. |
| Actual connection | Only after the action allows it: assert SQLite driver and `database_list` main target, same OS identity and no attached database; FK=1 before transaction. Confirm journal/runtime settings against approved evidence. Read-only authorization uses true read-only + query-only and refuses recovery/sidecar writes. |
| Writer acquisition | For an explicitly migration-authorized action only, acquire one owned BEGIN IMMEDIATE, with no nested manual/framework transaction. Recheck schema/ledger/source/backup/quiescence under lock. Bounded lock failure aborts; no closure replay after DDL. |
| State classification | Compare exact approved table/index/FK/trigger/CHECK and ledger fingerprints, not counts alone. Inspect partial states before invoking migrator, even if it would skip an existing ledger row. Refuse ambiguous state or drift rather than widening the accepted fingerprint dynamically. |
| Postflight | Validate old typed row/ID/ciphertext preservation, approved audit changes, complete final schema/ledger, empty seven tables, FK/integrity and no unauthorized runtime activation. Record outcome; retain maintenance on failure/unknown. |

**TOCTOU requirement:** keep an OS identity lease/no-delete handle and appropriately protected directories throughout authorized operations; a migration's lease must allow its own writes, unlike a read-only no-write lease. Recheck final handle identity after connection and before commit. If pathname replacement/reparse races cannot be excluded on the actual filesystem, stop rather than treating two realpath calls as proof. Do not alter ACLs, move the DB or pause sync merely under read-only revalidation permission.

## 4. Proposed release-selection controls

Build a clean, immutable working release from an explicit allowlist; no runtime directory glob or repository-wide migration scan. Keep the approved rehearsal directory/files untouched. A future separately named/versioned working artifact must receive its own source review, hash and parity/recovery tests; current PHP cannot be made working-ready by setting fixture flags.

The working release manifest must pin:

1. One selected working activation artifact, exact path/name/hash, frozen SQL hash and expected migration ledger identity; exact nine original foundation hashes and approved new schema/ledger fingerprints.
2. The three synthetic-only `2026_11_06_100000`, `100001`, `100002` exclusions from working discovery; all `Tests\` code/bootstrap/fixture assets excluded from runtime registration/autoload. Approved development files remain intact.
3. A complete staged file inventory and expected discovered migration list. Build validation fails on missing/extra migration, duplicate basename, symlink escape, mismatched contract/hash or an artifact in default discovery. Verify both staging and execution inventories; manifest text alone is not enforcement.
4. A single selected command specification consumed by the wrapper with structured arguments, not a user-supplied shell string. Reject broad paths/directories, multiple migrations, unfiltered `migrate`, reset/refresh/fresh, seeders, Composer setup/key generation and rollback. Never invoke those as an error-recovery fallback.
5. Independent owner-pinned release/manifest digest or signature. The execution wrapper must validate its own reviewed binary/source and the immutable staged release, not only files in a mutable working checkout.

Laravel migrator may still perform genuine logging for one successful `up()`. No synthetic migration may be marked applied and no successful ledger row is fabricated. A deliberately selected future design that makes DDL+ledger atomic would be a separately reviewed architecture change, not assumed here. Until then preserve and handle the known gap explicitly.

## 5. Operational backup and recovery runbook

**PROPOSED executable operator protocol, NOT EXECUTED.** Do not substitute the current migration command for a backup tool. Before this runbook can run, fill all placeholders in a private approved run record: operator, approver, maintenance window, exact source, vetted tool/hash/version, unique destination, custody/encryption method, access list, recovery authority, retention and RPO/RTO. An unfilled field is a stop condition. RPO is an owner-selected loss tolerance, not a promise that an old snapshot contains later commits.

### 5.1 Roles, storage and quiescence

- Owner/approver issues separate action permits; operator executes only that permit; recovery custodian controls encrypted artifact/key release. Separation of operator/approver is recommended and requires named assignments. No current identities are invented.
- Use an approved private local recovery directory outside webroot, Git, shared temp and unmanaged synchronization, protected by explicit ACLs and approved encryption. Keep an independently protected recovery copy; OneDrive replication of an open database is not the backup protocol. Record size/free-space margin for source snapshot, rebuild stage, verification and restore copies, with a measured safe bound.
- With separately authorized maintenance controls, drain mutations and stop web/CLI writers, queue workers, schedulers, external tasks and reconnecting agents. Existing login/session/cache writes count as writes. A maintenance page or zero queue count is not proof. Verify process/service identities and quiescence at OS and application boundaries; do not kill unknown processes casually.
- Control OneDrive only under its approved maintenance scope: inspect local/hydrated filesystem, sync/reparse behavior, exclude/pause or relocate only if explicitly authorized, and prevent a sync client replacing the active DB or recovery artifact. Unknown remote filesystem/locking behavior stops execution.
- Normal shutdown of a last WAL connection can perform storage housekeeping. Permission to read-only revalidate does not authorize service shutdown/checkpoint/journal recovery; the maintenance/backup permit must define any allowed shutdown effects. Do not silently broaden a backup to repair storage.

### 5.2 Consistent backup — action permit `backup`

1. Verify permit/replay claim, quiescence, source identity and journal state using the reviewed tool; reject hot journal, unapproved WAL state or need for source recovery. A SQLite read-only open that would require sidecar writes must stop. Never use `immutable=1` on a changing source as a shortcut.
2. Exclusively reserve a new destination with create-new semantics; refuse existing file, symlink, reparse or alias of source. The backup API can replace destination contents, so refuse-overwrite must be enforced **before** invoking it. Keep custody permissions effective during creation, not only after completion.
3. Primary tool contract: standalone vetted SQLite3 backup-API wrapper; open existing source with OPEN_READONLY, set source query-only and permitted connection protections; open only the newly reserved owned destination read/write. Invoke **source.backup(destination, 'main', 'main')**, check completion/return/error state and close both handles. No Laravel boot, ATTACH, provider or schema migration. Pin exact wrapper/library and rehearse it first. PDO alone does not establish this API capability. SQLite describes the engine-aware snapshot behavior in its [Backup API documentation](https://www.sqlite.org/backup.html).
4. Bound BUSY/LOCKED acquisition/retry and total duration; on error mark this destination incomplete, do not publish it as verified or fall back to an OS copy. Handle incomplete restricted files under the approved retention/disposal procedure. Do not overwrite a previous good backup.
5. No live main-file-only copy, WAL deletion, checkpoint, VACUUM or journal-mode setter. SQLite's WAL is part of persistent state; moving/copying it inconsistently can lose commits. An engine-aware snapshot avoids manually mixing main/WAL captures. Any alternative cold-copy/checkpoint procedure requires its own exact permission and rehearsal. [SQLite WAL file](https://www.sqlite.org/wal.html#the_wal_file).
6. While quiescence remains established, compare a source snapshot manifest with the completed backup: schema/ledger/index/trigger definitions, counts and private deterministic typed-content digests ordered by actual primary keys. Preserve NULL/type/byte distinctions and encrypted/raw metadata bytes; do not decrypt evidence or emit individual record hashes/data. Stop if a writer or changed capture invalidates equivalence.
7. On the separate backup, require integrity_check=`ok` and zero foreign_key_check violations. Integrity checking alone does not replace the FK check. Hash the closed completed file, apply approved encryption/custody and verify the retained encrypted artifact/hash and authorized decrypt-to-verification path. Do not treat successful file copy/encryption as proof of recoverability. [SQLite integrity checks](https://www.sqlite.org/pragma.html#pragma_integrity_check).
8. Publish only a redacted backup receipt: unique backup/run ID, consistent point, source identity, approved manifest digest, protected destination reference, tool/hash, verification outcome and custody reference. No key bytes, record contents or credentials. Do not automatically migrate or begin restore rehearsal when this succeeds.

No CLI command is supplied as ready-to-run: the backup wrapper and trusted paths/custody are not yet implemented/selected. The API sequence above defines exactly what its separately approved implementation must do. A future SQLite CLI alternative must also pin startup/init behavior, use structured validated arguments and prove no hidden initialization commands execute; it is not an implicit fallback.

### 5.3 Restore-to-new-file rehearsal — separate `restore_rehearsal` permit

1. Validate backup receipt, custody release, permitted real-data processing and unique restore destination. Verify protected artifact hashes before decrypting/opening. Source working path must never be the destination; no in-place restore.
2. Restore/decrypt into an exclusively created isolated directory with restrictive ACLs, network egress disabled and workers/mail/providers disabled **before boot**. This authorization covers a real-data-bearing recovery copy; ordinary public-key fixtures are forbidden for it.
3. Open/check the restored copy independently: integrity/FKs, full schema/ledger and typed-content manifest equality, existing encrypted evidence byte preservation and original identity/casing. Existing APP_KEY custody is needed before any explicitly authorized decryption/functional rehearsal; do not substitute fixture APP_KEY or generate new NIN keys merely to create empty tables.
4. If authorized, rehearse the exact working revision on a disposable child of that restored copy, preserving the untouched verified backup. Permission to restore does not automatically authorize running migrations even on a real-data clone; list that activity in its separate rehearsal scope.
5. Record replayable recovery evidence and keep restore readiness held independently of snapshot DB flags. Do not resume real authorization from an old proof, absent pending queue or restored `ready` flag. Dispose/retain sensitive rehearsal copies under the approved policy; no silent deletion of audit obligations/history.

### 5.4 Migration/recovery state decision table

All states are determined by full fingerprints and preservation evidence, not table counts alone. **Quarantine** means no automatic write, rerun, ledger adoption, service resume or permit reuse; retain maintenance and request an authorized diagnosis/recovery decision.

| Observed state | Required response |
|---|---|
| Original foundation schema + original nine-entry ledger; no committed activation | Eligible only for a fresh approved attempt with current backup/quiescence/permit. A failed/consumed permit is not reused. |
| Complete final schema + genuine activation ledger row + verified preserved old data + seven empty new tables | Postflight candidate. Record completion; release existing operations only under its approved resume decision. No new runtime feature activation follows. |
| Final/partial new schema without activation ledger | Quarantine known DDL/ledger crash-gap possibility. Do not rerun or insert an inferred ledger row. Preserve incident state; separately approve a verified forward reconciliation or restore. |
| Activation ledger present but missing/mismatched schema | Quarantine before migrator skip. No mark-applied/reset/down shortcut. Investigate artifact/deployment/integrity state. |
| Unexpected schema/trigger/index/dependency, nonempty new table, changed old evidence, FK/integrity error or ambiguous COMMIT | Quarantine, retain writers stopped, bounded redacted incident record. No automatic rollback script or change to approved fingerprint to make it pass. |
| Transaction failure demonstrably rolled back | Revalidate original state and ledger; record failure. A new permit/reviewed retry is required, not blind operation replay. |
| Restored snapshot predates later commits/revocations/obligations | Hold use. Independent authoritative post-backup evidence or explicit data-loss/recovery disposition is needed. Snapshot alone cannot reconstruct missing history; waiting thirty minutes cannot recreate it. |

Before working migration, authorize an exact target/release/command and maintenance window only after the above backup/restore evidence exists. Existing artifact down refuses, and must remain unchanged. A restore of the preactivation point is safe for preserving application history only if **no later legitimate commits** would be lost or those changes are explicitly reconciled under recovery authorization. Once writers resume, prefer reviewed forward recovery; never overwrite newer evidence from an old backup automatically.

Lossless post-backup recovery requires independently durable history/replication and custody beyond the same SQLite snapshot. No such infrastructure exists merely because this package names it. Permit/run records record operational actions, not a substitute copy of all business/security events.

## 6. Isolated validation and implementation status

**No new executable guard, selector, backup tool or migration was implemented in this task. No tests ran.** The requested guard/release work is specified as design; the existing approved artifact/guards remain unchanged and working-disabled. This avoids presenting an unreviewed enable-working branch as readiness.

**REPORTED baseline:** CAR final package tests 18/121, C suite 69/1,293 and combined 232/3,142 passed. These are historical results, not newly verified passes. Current source inspection confirms explicit selection, exact source/foundation hashes, BEGIN IMMEDIATE, preservation checks, refusing down and package tests for default discovery, DDL failures and both ledger/schema mismatch directions.

Future guard/selector implementation must be tested on independently generated memory/guarded-temp databases only, without real records/secrets. Mandatory cases:

| Test area | Required assertion before promoting a working revision |
|---|---|
| Pre-access denial | Missing/invalid/expired/wrong-action/wrong-operator/revoked permit, replay, unavailable register, untrusted clock, wrong hashes, URL/cache ambiguity and synthetic substitution invoke **zero** connection/file-open/backup callbacks. Use strict injected callbacks/mocks, not working path opens. |
| Identity continuity | Simulated canonical-path/file-ID/volume changes, reparse/hardlink aliases and replacement between validation/open/commit reject; no implicit create. Valid controls use generated fixtures. |
| Config/connection | Reject wrong driver/attached DB/actual identity mismatch/FK disabled; correct synthetic working-class simulation passes only in the isolated adapter. No test config grants a real working target. |
| Selection | Default discovery excludes activation; staged inventory excludes synthetic migrations; tampered/missing/extra/duplicate assets and broad path requests refuse; reviewed one-artifact control succeeds. |
| Ledger/state | Both gap directions quarantine before migrator skip/retry; signed permit cannot adopt drift. Simulate crashes at permit claim, lock, DDL commit and log/completion receipt. |
| Contract | Repeat final-schema/trigger-order parity, historical raw-byte preservation, empty tables, tenant/OTP/proof/R06 and original regression tests. Do not weaken guards/tests to pass. |
| Backup tool | On generated fixtures only, API snapshot, destination collision/alias refusal, read-only source, failure status, integrity/FK/typed-content verification and separate restore work; no source/destination confusion. |
| Operational | Actual OneDrive/filesystem behavior, custody release, disk-full/power-loss, independent post-backup evidence, privileged access and service quiescence remain later infrastructure exercises, not mock passes. |

The current preparation cannot certify production infrastructure, power-loss durability or every unknown-COMMIT recovery case. Any implemented code must be separately reviewed with actual new results; no pass count is forecast here.

## 7. Remaining blockers and separate approval gates

| Gate | Required authorization/evidence; current status |
|---|---|
| Guard/selector offline implementation | Approve this exact design or scoped revisions, signing/replay custody model and synthetic test harness; keep working execution disabled. **Next recommended development authorization.** |
| 1. Working read-only revalidation | Separate current-target permit allowing only confirmed no-create/read-only inventory and redacted evidence. No backup/checkpoint or config/provider boot. **NOT authorized by this task.** |
| 2. Working backup | Separate backup/maintenance permit with operator, tool, protected destination, quiescence, allowed shutdown effects, custody/retention and stop criteria. No migration or restore inherited. **NO-GO now.** |
| 3. Restore rehearsal | Separate real-data-copy/restore permit, isolated destination/egress controls, custody access and verification scope. No source overwrite/migration implied. **NO-GO now.** |
| 4. Working migration | Approved working revision, independently pinned release/command, passing synthetic and permitted recovery rehearsal, fresh matching preflight, consistent backup and one-run migration authorization. **NO-GO now.** |
| 5. Runtime activation | Separate service/authorization/readiness/reconciliation/delivery-fencing implementation and activation decision. Empty schema is not a runtime grant. **NO-GO now.** |
| 6. Real identity/contact processing | Separate lawful purpose/notices/issuer/normalization evidence, real key custody/recovery and safe capture permission. **NO-GO now.** |
| 7. External providers/payments | Independently scoped SMS, email and Selcom/payment approvals, contracts/security/quotas/spend/legal/operational evidence and exact activation permissions. No provider approval implies another provider/payment permission. **NO-GO now.** |

Owner must still choose named custodians/operator/approval trust root, protected storage, tooling, RPO/RTO/retention, sync/maintenance policy and current host/target identity. These are concrete execution prerequisites, not new blockers to already accepted synthetic B. Existing OTP/proof/encryption values need no repeated approval. No financial or multi-currency scope is introduced.

## 8. Preservation and explicit next request

**Source-only preservation baseline:** 215 tracked/nonignored-untracked existing files were SHA-256 inventoried in memory, deliberately excluding **all SQLite files/sidecars and .env variants**. No database/secret content or file metadata was accessed for this task's preservation check. Earlier report database hashes are historical; this report makes no new working hash or integrity claim. Ignored dependencies/caches/recovery trees are not exhaustively certified.

Only this requested Markdown report is created. Existing migrations/guards/manifest/SQL contract, tests, reports, frontend and application code are unchanged. No tests, formatter, runtime boot, SQL connection, working backup/restore, checkpoint, sync/service/ACL change, key generation, provider action, commit/push/deploy occurred. Official SQLite browsing was read-only. **VERIFIED post-write:** all **215 source-baseline files unchanged**, zero changed/missing; this report is the sole new nonexcluded inventory entry. HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`; `git diff --check` passed. This final report-only edit records those results; SQLite/.env remain explicitly outside this task's access and hash verification.

**Explicit next owner authorization request (PROPOSED):** authorize implementation of the WSR-1.0 permit validator, staged-release selector and state classifier **only in an offline synthetic harness**, retaining hard denial of working paths and leaving every approved migration immutable. Approve the proposed authority/replay trust model or name an alternative before coding it. Require strict pre-access-denial tests, B/C parity/regressions and a concrete review report. This request does **not** combine fresh target revalidation, backup, restore or migration permissions; each remains a later separate decision.

**STOP for owner review. Working activation remains NO-GO.** Hakuna working SQLite, backup, restore au production activation iliyoguswa. XLipa remains a website supporting TZS only.
