# XLipa — B3.2-C Operational Safety Design Finalization

**OSD-1.0 · 7 November 2026 · Africa/Nairobi · DESIGN AND REVIEW ONLY**

## 1. Executive summary

**GO for owner review of this proposed architecture. NO-GO for every working execution gate.** This report finalizes a reviewable operational safety design; it does not certify that its controls are provisioned, implemented or effective on working storage. XLipa remains a WEBSITE supporting TZS only.

The accepted synthetic B security contracts and isolated C release/recovery behavior remain the baseline. The next work should implement and challenge operational boundary controls using independently generated fixtures, without extending the accepted test permits to real targets. Owner approval, permit issuance, verification and execution must remain separate decisions and components.

Recommended first operational profile, subject to owner approval: an explicitly named Windows host and operator; an independent signing custodian; a single action per short-lived permit; handle-based target and directory identity; encrypted custody outside the repository, webroot and synchronization roots; externally witnessed single-use claims; and a conservative quiescent DELETE-journal SQLite profile. Unexpected WAL/journal state, identity changes, ambiguous completion or missing evidence stop the operation. None is automatically repaired.

**VERIFIED** means source/document content inspected in this review. **REPORTED** means prior execution or target observations recorded by earlier reports, without a fresh run. **PROPOSED** means design/recommendation only. **NOT TESTED** means no executed evidence in this review. All operational controls and numerical policy choices below are **PROPOSED / NOT TESTED**, unless explicitly identified otherwise.

Kwa Kiswahili: hii ni design na review pekee. Hakuna working SQLite au secrets zilizofunguliwa, hakuna backup/restore/migration iliyofanyika. Majina ya operator/custodian, storage, keys na kila action vinahitaji approval yake. Working activation bado NO-GO.

## 2. Existing verified baseline and review limits

### 2.1 Evidence reviewed

Paths below are relative to this report. Historical conclusions retain their original scope.

| Baseline | Evidence and interpretation in this review |
|---|---|
| B owner acceptance decision | [FOD-1.0](XLIPA-B3-2-B-FINAL-OWNER-ACCEPTANCE-DECISION-2026-11-07.md) recommends GO for bounded synthetic acceptance. Its text states actual owner sign-off remains pending; a report title is not proof of a recorded human approval. Preserve the accepted technical contracts; attach actual owner approval to future packages rather than repeating B review. |
| Isolated C activation | [CAR](XLIPA-B3-2-C-ISOLATED-ACTIVATION-REHEARSAL-2026-11-07.md) reports 18/121 targeted, 69/1,293 complete C and 232/3,142 combined tests/assertions. It identifies the post-DDL/pre-ledger interruption gap and restart quarantine. These are **REPORTED**, not newly executed results. |
| Working-target design | [WSR](XLIPA-B3-2-C-WORKING-TARGET-SAFETY-AND-RECOVERY-PACKAGE-2026-11-07.md) separates working access, recovery and activation gates. Proposed working protections are not deployed capabilities. |
| Offline security guards | [OSG](XLIPA-B3-2-C-OFFLINE-SECURITY-GUARD-IMPLEMENTATION-REPORT-2026-11-07.md) reports 54/172 targeted and 286/3,314 combined. Current permit, selector and register source confirms fixture-only authority and durable claim-before-access sequencing. |
| Authorizer diagnosis | [OAD](XLIPA-B3-2-C-OFFLINE-SQLITE-AUTHORIZER-DIAGNOSIS-2026-11-07.md) reports 25/149 targeted and 311/3,463 combined. The inspected authorizer uses native SQLite action constants, a limited function/PRAGMA policy and default denial. It provides no path opener or real authorization. |
| FRR readiness | [FRP](XLIPA-B3-2-C-FRR-2-0-READINESS-REVIEW-2026-11-07.md) confines future inspection to an exact approved read-only sequence, target lease and conservative sidecar/header profile. |
| Completed FRR-2.0 | [FRR-2.0](XLIPA-B3-2-C-FRR-2-0-READ-ONLY-REVALIDATION-2026-11-07.md) records 235 allowed authorizer events, zero denied events, unchanged file ID/hash, no sidecars and DELETE/FULL settings during that attempt. These are **REPORTED historical observations**, not current target facts or perpetual authorization. |
| Isolated release/recovery tooling | [RRT-1.0](XLIPA-B3-2-C-WORKING-RELEASE-AND-RECOVERY-TOOLING-2026-11-07.md) reports 47/193 targeted and 358/3,656 combined, plus six subprocess termination cases. Process termination is not power-loss testing. |

### 2.2 Current source findings — VERIFIED

Inspected `backend/AGENTS.md`, the activation selection manifest and the relevant support sources: `OfflinePermit.php`, `OfflineRunRegister.php`, `OfflineReleaseSelector.php`, `OfflineStateClassifier.php`, `OfflineInspectorAuthorizer.php`, `ScopedRecoveryAuthorization.php` and `SyntheticRecoveryPackage.php` under `backend/tests/Support/`.

- `OfflinePermit` requires testing/synthetic mode, exact canonical signed bytes, fixture target `:memory:`, synthetic environment, exact operator/host/release expectations and valid timing. Its maximum validity is five minutes and maximum execution duration sixty seconds. These fixture limits are not operational performance promises.
- `ScopedRecoveryAuthorization` binds `simulate:<operation>:<scope digest>` through the existing signed approval reference and limits disk scope to named synthetic temp roots/basenames. It does not confer working authority.
- The recovery package claims before source access, rechecks authorization/release, uses read-only source connections and the SQLite backup API, reserves exclusively new outputs and requires completed original claim plus sealed receipt evidence for restoration. Migration/activation scopes return disabled execution metadata.
- The register has explicit fixture initialization and checksum-linked durable records; missing/corrupt evidence fails closed. Checksums do not protect against an administrator replacing the entire journal and anchor with an old consistent version.
- `backend/database/activation/b32c/selection-manifest.json` remains `isolated-rehearsal-only`, with working execution **DISABLED**. It pins activation artifact `be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97` and SQL contract `09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0`, and excludes the three synthetic B migrations from a future working release. These are values read from the manifest, not newly computed artifact hashes.

Accepted seven-table scope, tenant/identity/AAD/encryption, OTP/proof rules, R06 pending obligations and audit meanings are preserved. No new evidence justifies reopening those contracts. There is no operational signer, custody store, Windows identity broker or working executor established by this review.

### 2.3 Method and boundaries

Only source and non-secret documentation were read. `.ai/rules` was absent at checked workspace, project and backend locations. No working SQLite or sidecar was opened, queried, hashed or copied; no real `.env` or secret was read. No application boot, test suite, formatter, migration, backup, restore, key generation, service/sync/ACL change, dependency change, commit, push or deployment was performed. This task creates only this report.

No current configuration, installed-runtime behavior or target freshness is certified. A current-time external documentation lookup did not return before it was stopped; no external-source verification is claimed. Platform integration details below are acceptance requirements for future prototype review, not assertions of tested API behavior.

## 3. Operator and authorization architecture

**PROPOSED:** maintain an owner-approved authority registry, separate from the application database and repository, binding named people to individual authenticated accounts and roles. Names and account identifiers are **unassigned** here.

| Role/component | Authority and separation |
|---|---|
| Owner | Approves purpose, exact action, target, release, window, evidence disclosure, custody and recovery boundaries. Records a digest-bound approval through an authenticated channel. Does not grant blanket future actions. |
| Named operator | Authenticates with an individual account and phishing-resistant MFA where supported; requests and runs only the approved operation on the approved host. No shared login; no signing-key access. |
| Independent signing custodian | A different person from the operator, who authenticates separately and checks owner approval and the complete package before signing. Cannot silently widen an approval. If staffing cannot provide this separation, working execution remains blocked. |
| Permit verifier | Uses independently provisioned public trust roots, current revocation/freshness evidence and locally trusted policy. Validates exact bytes, host/operator/target/release bindings and claims single-use execution. Has no signing secret. |
| Restricted executor | Receives a verified capability for one action and fixed parameters. Has no general shell/action dispatcher, permit-minting privilege or authority to relax policy. Application web/queue identities cannot invoke it. |
| Evidence custodian / reviewer | Protects claim history and completion witnesses separately from operator write access, reviews results and approves any later recovery investigation. May share the signing-custodian role if explicitly approved, but cannot be the executing operator. |

Authenticate the host by a provisioned device identity, approved OS/build posture and measured tool/release digest, rather than a hostname supplied by the caller. Bind the operator to the actual OS principal and the authenticated approval session. A string comparison against caller-provided identity is insufficient. Deny if account/device state or trustworthy authentication evidence cannot be established. Administrator/kernel compromise remains outside the executor's ability to contain; suspension and independent custody limit its consequences.

### 3.1 Operational permit contract

Create a separately versioned operational protocol in future work; do not broaden the accepted synthetic permit class, fake testing configuration or reuse fixture keys. Domain-separate operational signatures and reject synthetic signatures in every operational verifier.

An owner-reviewable package and its signed permit must bind:

- Protocol/version, unique approval/permit/run IDs, exactly one action, purpose, issuer/key ID, operator principal and authenticated device identity.
- Exact environment identity and target class; approved canonical target, volume/file ID, directory identities and lease strategy; expected schema/ledger/content baseline and journal profile. Acquisition of any fresh target fingerprint itself requires the read-only gate; never obtain it casually while preparing a package.
- Immutable release inventory, full manifest/artifact/contract/executor/policy hashes, exact fixed command/argument representation and expected outcome. No wildcard migration discovery or caller-controlled SQL/shell text.
- New destination reservation policy and approved custody-root identity; backup/receipt/key-version/provenance digests for restore; maintenance plan, resource limits, evidence policy and incident contact route.
- Not-before/expiry UTC timestamps, bounded clock uncertainty, monotonic maximum duration, current revocation/suspension epoch and required independent register generation/checkpoint.

The verifier compares these fields with separately protected trusted expectations, not a second copy supplied by the operator. Exact canonical encoding, duplicate-key rejection, bounded parser inputs, signature domain and unknown-field rejection require adversarial tests before adoption.

**Recommended initial policy:** permit start-validity at most five minutes; clock uncertainty at most five seconds; fresh signed revocation/suspension status at most sixty seconds old at claim and each irreversible boundary. Execution duration must be explicitly bounded per action from fixture timings; no automatic extension of the current sixty-second fixture limit. Long operations require a reviewed cancellation/duration design, not a wildcard long-lived permit. The owner must approve these numbers and the clock/status source.

### 3.2 Claim, execution and suspension

Validate approval, signature, policy, authentication, clock, release and status before target access. Durably claim both permit and run ID in an independently protected register before opening the target; a denied request should invoke zero target-access callbacks. Claim identity is never refunded on timeout, crash or verification failure.

Proposed state machine: `approved → claimed → executing → completed | failed | unknown | quarantined`. A receipt file alone is not completion. Completion requires verified output, durable receipt digest, terminal claim and independent witness acknowledgement; define and test crash states between each step. Incomplete publication remains quarantined. Restart never initializes a missing register or resumes a consumed permit automatically.

Protect an increasing register epoch/sequence with an independent witness outside operator/admin custody for the executing host. Require an acknowledgement of each claim before access, and completion before declaring success. Deny stale, missing, conflicting or rolled-back checkpoints. Separate register restoration and epoch rollover require incident review; copying an application backup must never restore/revert replay history. No offline availability fallback is proposed for the first working profile.

Owner or custodian emergency suspension blocks new claims immediately through fresh signed status. Operator may request an immediate local stop. During execution, recheck status before reservation/API start/commit/publication and final return. Stop admission and keep maintenance enforced on revocation. If an API call or transaction is already in flight, use only its reviewed cancellation/rollback boundary; do not promise instant revocation of an uninterruptible call or forcibly kill storage writes by default. Mark uncertain outcomes unknown/quarantined, preserve evidence and require a new investigation approval. Emergency suspension grants no restore or repair authority.

## 4. Signing-key custody design

**PROPOSED / NOT PROVISIONED:** use a vetted hardware-backed signing facility or isolated signing station under the independent custodian, with authenticated signing requests displaying the exact action/package digest. Algorithm and facility selection require review; the synthetic Ed25519 implementation demonstrates a fixture protocol, not a production custody decision.

Keep signing keys distinct from application encryption keys, backup encryption keys, device credentials and evidence-attestation keys. Never place private keys in the repository, application `.env`, permit, receipt, web process, command line or synchronized folder. The verifier pins public trust roots through a separately approved installation process; do not trust a key bundled with a submitted permit.

Document key ID, creation approval, allowed action/environment classes, validity, custodian, recovery custodian and rotation/revocation procedure. Rotate using dual-approved trust-root updates and signed transition evidence. Retain old public verification material for historical receipts; preserve required backup decryption key versions independently. New trust roots must not retroactively make a previously revoked permit executable.

For suspected compromise: suspend affected authority, reject unexecuted permits, isolate the host if implicated, preserve external evidence, compare all issued permits and claims against owner approvals and rotate through a fresh approved trust process. Do not reset consumption history. If key recovery is offered, require two authorized people and offline protected recovery material; rehearse loss and custodian unavailability using synthetic keys first. No real keys or persons are selected here.

## 5. Backup storage and encryption policy

**PROPOSED location criteria; no actual destination selected:** a dedicated custody volume/directory outside the webroot, project repository, OneDrive and every other synchronization/share root. It must have verified local filesystem semantics, space headroom, restricted ownership and a documented separate failure domain for the secondary copy. Same-host storage alone does not survive host loss or theft.

The current workspace is visibly under OneDrive. That path is evidence of synchronization risk, not proof of whether synchronization is currently active. Pausing a client alone is not an adequate long-term storage boundary. Before any working action, the owner must approve a source-location/synchronization strategy. Relocating a working database/configuration is a separate action requiring its own package; this report authorizes no move or setting change. If source/sync exclusion and file identity cannot be established safely, stop.

Use restrictive explicit ACLs for the approved execution identity and custody roles; remove inherited broad grants and deny the app/web/queue identities access. Review effective access, directory creation/replacement rights, owner privileges, recovery operator access and backup-service accounts. Avoid ordinary user temp directories for plaintext staging. ACL review must account for administrators' residual authority.

Require full-volume encryption for approved source/staging/custody devices plus authenticated encryption of every exported backup. Full-volume encryption alone does not protect an unlocked host or a copied artifact. Use a vetted envelope-encryption implementation: fresh data key per artifact, approved authenticated cipher, unique nonce discipline, versioned metadata and wrapped data key under a separately controlled recovery key. Authenticate artifact identity, environment, source identity, capture time and schema/release provenance as associated metadata. Do not invent a custom cipher/format.

SQLite API output may require plaintext staging before encryption. Its future design must stage only on an encrypted, restricted, non-synchronized volume; never log rows or leave plaintext in generic temp. Completion requires verification of the encrypted artifact and the approved treatment of staging residue. A failed run retains only access-restricted quarantine material, counted in the custody inventory and retention process.

Store a minimal signed provenance manifest and encrypted sensitive metadata with the artifact: backup ID, capture point, source/release/schema/ledger digests, logical verification digest, ciphertext hash, encryption/key version, permit/run IDs and completion witness. A plaintext hash or receipt alone cannot authenticate provenance; hashes do not replace protected signatures/witnesses. Keep sensitive paths/identities private. Permit restoration only when ciphertext authentication, provenance and independent original completion all verify.

**Proposed initial retention:** seven daily completed recovery points plus four weekly points, with mandatory pre-change backups retained until post-change acceptance and at least fourteen days. Review volume/cost and data minimization before approval; legal holds override scheduled deletion. Track failures and quarantined outputs separately. Proposed resilience target: one local encrypted copy and one independently controlled encrypted copy in a different failure domain, with synchronization-free transfer and verified receipt. Do not introduce an external storage provider without its separate approval.

Secure deletion must cover primary/secondary copies, staging, version history and key backups. Do not claim file overwrite securely erases SSD, snapshot or cloud remnants. Prefer approved encrypted-media lifecycle and, where suitable, cryptographic erasure after all required key copies/retention holds are accounted for. Record deletion approval, affected artifact/key IDs and completion evidence; key deletion must not destroy retained recovery points. Recovery access requires separate approval, MFA, two-person key release and an isolated destination with no automatic app/network access.

## 6. SQLite maintenance and quiescence design

**PROPOSED runbook, not executed.** First working profile supports only an explicitly approved, quiescent DELETE-journal source with no WAL/SHM/journal sidecars and compatible header. A different profile requires new design and fixture evidence; a checkpoint or journal recovery is a write operation and is not an implicit preparatory step.

1. **Prepare the window:** owner approves action, outage budget, host/source/custody identities, package hashes, operators, communication, resource bounds and stop/resume authority. Freeze release/configuration changes. Verify storage headroom for the database, snapshot, encryption output, restore copy and temporary/journal growth with a documented safety margin; no arbitrary universal multiplier is asserted sufficient.
2. **Fence all writers:** use a vetted maintenance mechanism to reject/drain mutating web requests, including bypass sessions and long-lived workers. Stop new queue admission, drain or explicitly quarantine in-flight jobs, fence scheduler/cron/Windows tasks and identify CLI/admin writers. Database-backed queues/cache/sessions may write despite a maintenance page; external callbacks must be held or rejected according to an approved replay-safe policy. Do not drop jobs or accept provider work silently. The concrete Laravel/host commands depend on the inventoried installed deployment and need separate review.
3. **Prove quiescence:** record acknowledgements from every writer source, active-job/request counts and the enforced writer fence. Close writer connections and verify no unexpected target handles/transactions through the approved host integration. A momentary successful lock attempt or stable hash is insufficient proof of future exclusion. Maintain fencing for the whole operation and final evidence. If an unlisted writer appears, stop.
4. **Acquire target identity:** authenticate approved volume and parent directories; reject network/UNC/device paths, alternate streams, unexpected case/short-name aliases, hard links, junctions, reparse points and substituted directories according to the approved profile. Obtain final path, volume/file IDs, link count and identity of parent components from trusted handles. Protect directories against replacement and hold the relevant handles through close/evidence capture. String canonicalization and PHP `is_link` alone are insufficient. Recheck the SQLite connection's actual target against the approved identity. Native integration must prove that the engine opens the same object; if it cannot, refuse execution.
5. **Inspect conservatively:** under the authorized read boundary inspect header and sidecar names/state before open. Refuse WAL/SHM/journal presence, incompatible header, BUSY/LOCKED, recovery requirement or unexpected filesystem changes. No deletion of sidecars, immutable-mode bypass, raw file copy, checkpoint, journal-mode change or recovery attempt. Read-only checks use explicit no-create/read-only open flags, query-only, memory temp, FK checking and the approved restrictive authorizer. Do not treat authorizer success as OS containment.
6. **Execute the one action:** establish a consistent read snapshot for backup; keep the writer fence and validated source identity while the engine API opens its own source handle. Destination must be exclusively new in a protected directory, reserved and opened as the same verified object without CREATE fallback. A source deny-write/delete sharing lease may support read-only work, but cannot simply be retained unchanged for a migration that must write. A future migration requires a distinct reviewed lease/handle-sharing protocol that admits only the authorized engine and prevents identity replacement; inability to prove this is a blocker.
7. **Verify and publish:** close/flush according to a reviewed durability protocol, reopen output read-only under its own identity protection and verify section 8. Recheck source identity/hash/schema/ledger as appropriate to the action. For migration, compare against expected post-change state rather than claiming source hash unchanged. Publish/seal receipt and witness completion before declaring success. A source change during read-only/backup work quarantines the run even if the engine produced a consistent snapshot.
8. **Leave maintenance safely:** review outcome and unresolved work before releasing fences. The action package must say whether return to the previously approved service state is allowed; migration approval never enables new runtime functionality. Unknown completion, unexpected schema/ledger or failed verification holds maintenance and requires owner incident review. Record withheld work and restart reconciliation; no automatic retry of the whole action.

**Failure/rollback boundary:** before any write, abort without changing the source. Failed backup/restore outputs remain incomplete, never overwritten by a retry. After migration begins, rely only on the reviewed transaction's rollback behavior; a lost connection/unknown commit is not proof of rollback. The known DDL-commit/ledger-log gap requires quarantine, never fabricated success, ledger adoption or blind rerun. A backup is evidence, not automatic rollback authority. Restoring over working storage, switching the application's database pointer or executing a down migration each needs a new explicit recovery package, comparison of lost changes and owner approval. Default response to physical I/O failure is preserve evidence and suspend, not continued probing.

Maintenance itself can have storage effects: closing the final WAL writer may trigger housekeeping. The future package must inspect the authorized journal profile before shutdown and explicitly authorize any expected shutdown effects. Do not first stop services and then use the resulting absence of sidecars to claim that an originally unsupported WAL source met this conservative profile. Read-only inspection permission alone never authorizes writer shutdown or checkpoint behavior.

## 7. RPO/RTO options

**PROPOSED; none achieved.** RPO measures the maximum acceptable age of the last *verified recoverable capture point* relative to failure, including copy lag and post-backup obligations. RTO starts when service becomes unavailable and ends only when approved service is validated and usable; detection, people, key access, authorization, restore, reconciliation and restart all count.

| Option | Proposed RPO / RTO | Trade-off and applicability |
|---|---|---|
| A — current development recommendation | RPO ≤24 hours during development and a verified quiescent backup immediately before every approved database change; RTO ≤1 business day with documented support hours | Lowest staffing/storage complexity; accepts loss of development changes since capture. Does not permit a 24-hour loss of future financial history. Define the business calendar/timezone and out-of-hours response before approval. |
| B — controlled pilot, separately approved | RPO ≤1 hour; RTO ≤4 elapsed hours | Requires frequent verified capture/copy, available custodians, capacity, monitoring and measured drills. Frequent maintenance may be disruptive; do not claim the current quiescent profile meets this. |
| C — future live-payment planning only | Candidate RPO ≤5 minutes for recoverable application state, with no silent loss/duplication of externally committed financial obligations; RTO ≤1 elapsed hour | Requires a separately designed resilient data/ledger/reconciliation architecture, continuous evidence, provider idempotency and staffed recovery. Periodic SQLite snapshots alone do not establish this capability. |

Recommend Option A only for the current development stage, pending owner acceptance of loss/outage budgets. It is not permission to start collecting real data. Test measured end-to-end drills before claiming an objective; include a cold recovery with unavailable primary host and primary custodian. Backup duration is not RTO. A completed file older than the objective is a failed recovery posture even when its integrity is good. Alert on missed captures/copy delays and hold risky changes until a valid recovery point is available.

## 8. Recovery verification requirements

**PROPOSED evidence contract:** maintain a protected run dossier outside the application database and repository. Record approval/permit/run IDs, actual authenticated operator/host, UTC and monotonic timing/clock health, revocation epoch, release/policy/tool digests, source/destination handle identities, maintenance acknowledgements, capture point, engine capability, space/error status, verification results, encrypted artifact/key IDs and external claim/completion checkpoints. Minimize/redact sensitive metadata; never log plaintext rows, keys, contact data or raw sensitive SQL.

Verification must independently establish:

1. Engine API success plus authenticated encrypted-artifact round trip; exact ciphertext hash, decryptability using the approved recovery-key version and verified provenance/completion chain. File existence is insufficient.
2. SQLite integrity result `ok`, zero foreign-key violations, exact expected schema/index/trigger definitions and genuine expected migration ledger/batches. Check typed row/content digests in deterministic order, relevant sequences and parent/tenant/audit invariants; counts alone cannot prove equality. Keep content-derived digests private.
3. Restore to an exclusively new, isolated file under a separate restore permit, without mutating source/backup, copying into a runtime path or enabling delivery/network services. Close and reopen before final verification. A working-data rehearsal remains sensitive real-data processing even if runtime stays disabled.
4. Application/security compatibility with safe isolated checks, original IDs/encrypted bytes preserved, and a recovery-readiness hold until post-capture revocations, pending audit obligations and external side effects can be reconciled. Never reconstruct missing history by inventing audit rows or clearing pending states. Backup consistency is not complete business recovery.
5. Durable receipt plus terminal completion and independent witness acknowledgement. Failures at every publication boundary must remain distinguishable on restart; absence of any required evidence denies restoration.

Recommended initial drill cadence: every pre-migration backup verified before the migration gate; an independently approved restore rehearsal before the first working migration and after relevant tool/key/storage/schema changes; then monthly for approved development custody. Record actual elapsed time, recovered capture point, simulated loss against objective, comparison failures, reviewer and disposition. Each drill using working data still requires its own action-specific approval. A rehearsal pass does not authorize a working replacement restore.

## 9. Per-action authorization gates

**PROPOSED. All eight gates currently NO-GO for execution under this task.** Every package requires the common authenticated identities, exact target/environment/release, current status/clock, protected evidence and bounded single-use permit from sections 3–8. Each approval names one action and explicit window; success or approval at one gate grants none of the others.

### Gate 1 — Working read-only revalidation

- **Prerequisites:** reviewed fixed inspector, protected public trust, target/configuration resolution method that avoids secret disclosure, canonical identity/lease and conservative SQLite profile. Historical FRR observations are inputs, not fresh proof.
- **Owner approval:** exact host/target, permitted metadata/aggregates, code/policy hashes, time window and evidence recipients; any need to read sensitive configuration must be expressly bounded in that future scope.
- **Permitted:** authorized identity/header/hash checks and fixed read-only schema/ledger/integrity/FK inspection under the lease.
- **Prohibited:** data export/copy, backup, writes, recovery/checkpoint, sidecar manipulation, migration and runtime activation.
- **Evidence:** unchanged file identity/content and relevant configuration evidence, engine/authorizer events, schema/ledger classification and completed witnessed run.
- **Failure / mandatory stop:** any authorizer denial/error, BUSY, drift, sidecar, identity/clock/status change or missing evidence. No relaxed-policy retry.

### Gate 2 — Working backup

- **Prerequisites:** fresh authorized target evidence, approved custody/encryption/retention, proven quiescence/identity protocol, vetted API executor, capacity and recovery-key availability. Freshness must be maintained through the fence; specify an explicit evidence validity limit in the package.
- **Owner approval:** exact source, new custody destination/root identity, capture window, retention, key version, tool and verification plan.
- **Permitted:** one consistent API snapshot into a new protected staging object, encryption, verification and approved custody publication.
- **Prohibited:** overwrite, raw-copy fallback, source mutation, restore, migration or service activation.
- **Evidence:** source preserved, complete content/schema/ledger verification, decryptable ciphertext, signed provenance and witnessed completion.
- **Failure / mandatory stop:** API/space/flush/encryption errors, writer/sync/identity change, mismatch or incomplete evidence. Quarantine output; consume claim.

### Gate 3 — Restore-to-new-file rehearsal

- **Prerequisites:** completed authenticated backup/provenance and independent register evidence, available approved decryption key, isolated protected destination, no-runtime/no-egress controls and an explicit real-data handling plan.
- **Owner approval:** exact backup/receipt digests and new destination, recovery users, permitted verification and disposal/retention.
- **Permitted:** decrypt in protected staging and API restore to one new isolated file; independently verify recovered content and measure elapsed recovery.
- **Prohibited:** replacing working storage, changing app pointers, migration, sending messages/provider traffic or live processing.
- **Evidence:** backup unchanged, new identity, parity/integrity/FK/ledger checks, measured RPO/RTO and completed witnessed receipt.
- **Failure / mandatory stop:** authentication/key/provenance failure, existing output, incomplete source claim or any parity/isolation violation. Preserve restricted quarantine; no promotion.

### Gate 4 — Working migration

- **Prerequisites:** actual recorded acceptance/approvals, separately reviewed working-only artifact/executor and immutable release build, approved synthetic-migration exclusion, fresh foundation evidence under continued fencing, verified pre-change backup and successful separately authorized restore rehearsal. Current rehearsal-only artifact/manifest is insufficient.
- **Owner approval:** exact migration artifact/contract/tool hashes, exact command/arguments and selection, source identity, expected schema/ledger, window, maintenance and incident/recovery plan.
- **Permitted:** only that migration and bounded pre/post verification using the reviewed write-compatible identity protocol.
- **Prohibited:** default discovery, synthetic B migrations, automatic down/restore, ledger fabrication/adoption, runtime activation or real collection.
- **Evidence:** preserved historical IDs/rows/audit constraints, exact resulting schema and authentic migration log/batch, integrity/FK and sealed external result.
- **Failure / mandatory stop:** unexpected object/ledger/row change, DDL/log gap, lost transaction outcome, time/resource/identity fault. Keep maintenance; no blind retry or repair.

### Gate 5 — Runtime activation

- **Prerequisites:** independently accepted schema state, reviewed runtime services/registration, scoped access controls, readiness/pending reconciliation, clock/keys, concurrency and delivery fencing, monitoring, suspension and rollback plan.
- **Owner approval:** exact features, roles/endpoints, host/release/configuration, allowed data/provider classes and activation window.
- **Permitted:** enable and validate only the named runtime capability within already approved data/provider limits.
- **Prohibited:** implied real identity/contact capture, provider delivery, payment processing or broader feature enablement.
- **Evidence:** access/tenant/security checks, readiness/alerts, bounded smoke results and controlled stop/resume evidence.
- **Failure / mandatory stop:** readiness/pending conflicts, failed authorization/tenant checks, unexpected egress or unhealthy clock/storage; disable the approved capability safely and retain evidence.

### Gate 6 — Real identity/contact processing

- **Prerequisites:** approved purpose/data minimization/notices, applicable privacy/legal review, secure capture/normalization/issuer validation, retention/deletion/incident response, real encryption-key custody and recovery, role/reauthentication and lifecycle invalidation.
- **Owner approval:** exact data fields, subjects/purpose, processing operations, access roles, environment and retention; any recovery processing remains specifically scoped.
- **Permitted:** only named collection/use/storage operations with consent or another reviewed applicable basis.
- **Prohibited:** unrelated use, raw sensitive logging, inferred verified possession, unapproved external disclosure or financial authority.
- **Evidence:** reviewed data flow, access/crypto/recovery/lifecycle tests, notices and incident/retention controls without exposing real values in reports.
- **Failure / mandatory stop:** disclosure, key/access/validation failure, unapproved purpose or missing legal/privacy decision; suspend capture and preserve minimum incident evidence.

### Gate 7 — External provider integration

- **Prerequisites:** provider-specific security/contract/data-flow approval, secret custody, sandbox evidence, callback authentication, idempotency, ambiguity handling, delivery fencing, quotas/spend limits and monitoring.
- **Owner approval:** one named provider/account/environment/action set, recipient/data classes, limits and exact activation package; SMS, email and payment providers each require separate decisions. Sandbox and live scopes are separate.
- **Permitted:** explicitly approved API operations and bounded tests in the named environment.
- **Prohibited:** another provider, arbitrary real recipients, live credentials in sandbox, unapproved charge/send or live financial settlement.
- **Evidence:** authenticated callback/replay tests, duplicate/timeout handling, reconciled provider receipts and no unexpected requests/spend.
- **Failure / mandatory stop:** unknown send/charge outcome, invalid callback, quota breach or unexpected environment/recipient. Hold and reconcile; never blindly retry an external side effect.

### Gate 8 — Live payments

- **Prerequisites:** independently approved runtime, real-data and payment-provider scopes; reviewed financial authorization, idempotency, ledger/reconciliation/refund/dispute controls, legal/compliance readiness, fraud/spend limits, staffed incidents and measured financial recovery objectives.
- **Owner approval:** exact TZS-only payment products/provider/account, transaction/value/merchant limits, rollout window, financial authority and suspension plan.
- **Permitted:** only named live financial actions within approved limits; refunds/payouts or changed products require their own explicit scope.
- **Prohibited:** non-TZS currency, inferred authority from an OTP/proof or schema success, arbitrary payouts, unreconciled retries or wider merchant rollout.
- **Evidence:** end-to-end authorization/accounting/reconciliation and recovery evidence, provider settlement matching, live-limit monitoring and independent financial review.
- **Failure / mandatory stop:** imbalance, unknown commitment, duplicate action, missing audit/recovery evidence or limit breach. Suspend admission, preserve obligations and reconcile under incident authority.

## 10. Operational risk register

Residual ratings below are qualitative planning judgments before operational controls are implemented. **S** = synthetic mitigation supported by inspected source and reported tests; **U** = unresolved real-world control/evidence. No risk is declared closed for working use.

| Risk | Existing boundary | Proposed control / residual risk / accountable role |
|---|---|---|
| Authorization-key compromise | S: signature/tamper rejection with disposable keys | Independent protected signer, scoped keys, owner digest, revocation/suspension and rotation. **U: Critical** until provisioned; signing custodian. A compromised issuer plus forged approvals remains a trust-boundary threat. |
| Unauthorized operator | S: expected fixture identity comparisons | Individual MFA/OS principal, least privilege, separate signer and witnessed runs. **U: High**; owner/operator administrator. Privileged host compromise can bypass local checks. |
| Wrong environment | S: synthetic target/environment rejection | Pinned device/volume/file/environment identities and immutable tool package. **U: Critical**; operator/reviewer. Never infer environment from hostname or `.env` label alone. |
| Database corruption | S: integrity/FK/schema/content checks | Quiescence, verified encrypted copies, separate failure domain and drills. **U: High**; recovery custodian. Integrity checks cannot recover uncaptured history. |
| Interrupted migration | S: transactional fixture cases and DDL/log-gap quarantine | Write-compatible identity protection, external claim, maintenance hold and separately approved recovery investigation. **U: Critical**; technical reviewer. No automatic ledger repair. |
| Incomplete backup | S: exclusive new output and crash/publication rejection | Durable encryption/publication/witness protocol and independent verification. **U: High**; backup operator. File existence never means completion. |
| Failed restore / lost key | S: provenance/content rejection and new-file restore | Separate key recovery, cold drills and original-source preservation. **U: Critical**; recovery/key custodians. A valid encrypted file without a key is unusable. |
| Power loss / unknown commit | S: process termination only | Validate device/flush behavior, approved power protection, storage fault tests and unknown-state handling. **U: Critical**; host/storage owner. fsync/process tests alone do not prove power-loss durability. |
| Disk full / I/O error | S: error paths; no physical guarantee | Capacity reservations/headroom, fault injection, failure-preserving quarantine and alerts. **U: High**; host/storage owner. Partial output cannot be promoted. |
| Filesystem alias/race | S: lexical/temp restrictions | Native handle/directory identity and lease tests including hard links/reparse/races. **U: Critical**; platform engineer. PHP path checks alone are insufficient. |
| OneDrive interference | S: none for real sync behavior | Approved source strategy and custody outside sync roots; verified exclusion/fencing. **U: Critical**; host owner. Pause status alone does not establish safety. |
| Lost/corrupt/rolled-back register | S: missing/torn/checksum failure denies | Independent freshness witness, protected append history and incident-only epoch recovery. **U: Critical**; evidence custodian. Availability loss must deny operations rather than reset history. |
| Recovery artifact theft | S: fixture content only | Envelope/volume encryption, restrictive ACLs, separate keys, inventory/access logs and retention deletion. **U: High**; custody owner. Unlocked-host and decryption-session theft remain risks. |
| Clock/revocation failure | S: fixture health/expiry checks | Authenticated status epochs, bounded skew, monotonic duration and deny-on-unavailability. **U: High**; security custodian. Revocation cannot undo a completed action. |
| Post-backup obligation loss | S: accepted pending/audit invariants | Independently retained revocations/provider outcomes, readiness hold and reconciliation before service use. **U: Critical** before sensitive/financial use; runtime/financial owner. Never infer missing history from a clean restored database. |

## 11. Decisions requiring owner approval

These decisions are deliberately open; this report invents no people, key material or actual filesystem destination.

| Decision | Recommended proposal / approval evidence needed |
|---|---|
| Acceptance record | Record actual owner acceptance of the bounded baseline and disposition of this design, with report/package digests; do not treat recommendations as signatures. |
| People and separation | Name owner, operator, independent signer, evidence/recovery custodians and alternates; approve individual authentication and emergency contact process. |
| Host/trust | Select device, OS principals, trust-root installation, time/revocation sources and status thresholds; approve the residual administrator threat. |
| Register custody | Choose independent witness/storage, acknowledgement/failure behavior, retention and incident epoch recovery. No reset-to-unblock procedure. |
| Storage/source | Approve actual non-synchronized custody/staging/secondary locations, ACLs, capacity, source OneDrive strategy and any separately packaged relocation. |
| Encryption/keys | Approve vetted signing/encryption facilities, key separation, recovery quorum, rotation and loss/compromise response. |
| Maintenance | Identify all web/worker/scheduler/CLI writers, allowed outage, fence/lease protocol, status checks and who may release maintenance. |
| Recovery policy | Approve Option A or another measured target, support hours, retention/drill cadence, deletion/legal holds and sensitive drill access. |
| Execution packages | Approve each future action independently after its implementation/evidence is reviewable. Design acceptance grants none of gates 1–8. |

## 12. Recommended next implementation stage

**PROPOSED:** a separately authorized synthetic operational-boundary prototype, preserving the existing accepted fixture helpers and working-disabled manifest. Do not implement a working executor as part of accepting this report.

Prepare a reviewable implementation brief for native Windows identity/directory protection, immutable release staging, authentication adapters with fake identities, separate operational permit schema using disposable keys, independently witnessed register protocol and encrypted custody using synthetic content. Exercise denial before target access, alias/replacement races, wrong device/principal, stale status/clock, concurrent replay, register rollback/loss, key unavailability, encryption tampering, capacity/I/O faults and crashes between every publication step. Include the different read-only and write-compatible sharing requirements. Keep external effects inert.

Acceptance requires evidence that each claimed protection is enforceable by the selected integration, with limitations recorded. Virtual/fault-injected storage tests and process crashes must remain distinct from physical disk/power-loss evidence. Produce exact hashes, source-only delta and measured fixture results without recertifying unchanged historical tests as fresh execution.

After owner review of that isolated evidence and approval/provisioning of the open custody/identity decisions, prepare the first concrete **working read-only revalidation** package. Obtain explicit authorization before collecting fresh target evidence. Use its accepted output to prepare a separate backup package; neither this sequence nor a successful prior gate grants the next action automatically. Working migration and runtime activation remain later independent decisions.

## 13. GO/NO-GO and preservation

**GO:** owner/technical review of this design document. Its architecture and approval questions are concrete; operational implementation and validation remain outstanding.

**NO-GO:** working read-only access under this task, working backup, real-data restore rehearsal, working migration, runtime activation, real identity/contact processing, external provider operations and live payments. No operational signing or storage authority is provisioned. No existing guard, migration, application source, accepted report or manifest was intentionally changed.

Validation for this documentation-only task is limited to inspecting the new report, checking the Git delta and whitespace. No operational test or objective is claimed achieved. Existing ignored/dependency/storage content is not exhaustively inventoried or preservation-certified; forbidden targets were excluded from review entirely.

**VERIFIED review checks:** Git status showed only this new report; tracked and staged diffs were empty. Both the repository whitespace check and a separate no-index whitespace check of this untracked report passed. All eight local baseline-report links resolve. Git emitted LF-to-CRLF normalization notices, without whitespace errors. These checks establish the visible source delta, not a fresh hash certification of working storage or secrets.

**WORKING ACTIVATION REMAINS NO-GO. STOP FOR OWNER REVIEW.** XLipa is a WEBSITE supporting TZS only.
