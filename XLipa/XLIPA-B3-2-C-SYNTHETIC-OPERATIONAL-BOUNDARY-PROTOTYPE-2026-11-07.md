# XLipa — B3.2-C Synthetic Operational Boundary Prototype

**SOB-1.0 · 7 November 2026 · Africa/Nairobi · ISOLATED SYNTHETIC IMPLEMENTATION ONLY**

## 1. Executive summary

The package adds a separate prototype permit protocol, native Windows read/share identity leases, pinned synthetic release selection, a two-store witnessed consumption model, authenticated encrypted-content custody and a generation-based writer-fence simulation. All components remain under `backend/tests/`; no route, provider, application command, working executor or operational signing authority is installed.

**VERIFIED final combined regression: 444/444 tests, 3,958 assertions, zero failures/errors, 669,514 ms.** This includes all 86 new SOB cases and all 358 existing cases. The earlier revised focused run passed 19/19 with 64 assertions; its revision boundary is recorded in section 11.

**Working execution remains DISABLED.** `migration_simulation` only inspects a generated fixture and returns `execution=disabled`. The new capture/restore simulations publish encrypted logical fixture content, not a working SQLite backup or an executable restored database. Existing RRT SQLite backup-API tests remain unchanged and are included in regression verification.

**VERIFIED** means newly inspected source or executed tests within the stated fixture scope. **REPORTED** means historical report evidence, not a new target observation. **PROPOSED** means future work. **NOT TESTED** identifies missing operational or physical evidence. Synthetic success is never production readiness.

Kwa Kiswahili: prototype hutumia fixtures na keys za muda pekee. Hakuna working SQLite, `.env`, real secrets, huduma au payments zilizotumika. Witness na writer fencing ni simulation; native file-handle tests ni za disposable files. Working activation bado NO-GO.

## 2. Reviewed baseline

Reviewed backend `AGENTS.md`, the existing support/test structure, isolated bootstrap, manifest, accepted permit/register/selector/classifier/authorizer and recovery implementation, with these reports:

| Evidence | Review disposition |
|---|---|
| [B final acceptance decision](XLIPA-B3-2-B-FINAL-OWNER-ACCEPTANCE-DECISION-2026-11-07.md) | Preserve accepted seven-table and security contracts. Its recommendation is not a fabricated human sign-off. |
| [C isolated activation rehearsal](XLIPA-B3-2-C-ISOLATED-ACTIVATION-REHEARSAL-2026-11-07.md) | Preserve explicit selection, guarded rehearsal and DDL/ledger-gap quarantine. |
| [Working-target safety design](XLIPA-B3-2-C-WORKING-TARGET-SAFETY-AND-RECOVERY-PACKAGE-2026-11-07.md) | Keep action-specific approvals and write-compatible identity requirements separate. |
| [Offline guards](XLIPA-B3-2-C-OFFLINE-SECURITY-GUARD-IMPLEMENTATION-REPORT-2026-11-07.md) | Preserve synthetic `OfflinePermit`, replay and release contracts unchanged. |
| [SQLite authorizer diagnosis](XLIPA-B3-2-C-OFFLINE-SQLITE-AUTHORIZER-DIAGNOSIS-2026-11-07.md) | Reuse corrected native action policy without widening its allowlist. |
| [FRR readiness](XLIPA-B3-2-C-FRR-2-0-READINESS-REVIEW-2026-11-07.md) | Retain conservative read-only/no-recovery profile. |
| [Completed FRR-2.0](XLIPA-B3-2-C-FRR-2-0-READ-ONLY-REVALIDATION-2026-11-07.md) | Historical file-ID/hash/configuration observations remain **REPORTED**; no target freshness was repeated. |
| [RRT-1.0](XLIPA-B3-2-C-WORKING-RELEASE-AND-RECOVERY-TOOLING-2026-11-07.md) | Historical 47/193 targeted and 358/3,656 combined tests/assertions are **REPORTED** until separately rerun; accepted helpers remain unchanged. |
| [OSD-1.0](XLIPA-B3-2-C-OPERATIONAL-SAFETY-DESIGN-FINALIZATION-2026-11-07.md) | Implements selected fixture prototypes while retaining every working gate and unresolved native/custody requirement. |

Reused unchanged: `tests/bootstrap-isolated.php`, Composer development autoloading, `OfflineInspectorAuthorizer`, installed sodium/SQLite facilities and Symfony Process. The full suite also exercises accepted B/C and RRT helpers. A new register is necessary for the separate SOB protocol and paired-witness semantics; `OfflinePermit` and `OfflineRunRegister` were not broadened or rewritten.

`.ai/rules` was absent at checked workspace/project/backend locations. No testing-best-practices skill was available in the listed skills or local skills directory; none was installed. Files were created directly under test support to avoid an ordinary Artisan boot and real environment loading. No sub-agent, dependency or application registration was introduced. The source lock records Laravel `v13.35.0`; no assumption of Laravel 12 APIs was made.

## 3. Implemented components and exact intended delta

Paths in this table are relative to `XLipa/`. All are additions; no pre-existing file requires modification.

| New file | Purpose |
|---|---|
| `backend/tests/Support/SobFixture.php` | Synthetic bootstrap/root restrictions, strict canonical serialization, bounded evidence reads and create-new durable writes. |
| `backend/tests/Support/SobPermit.php` | Separate SOB authorization protocol; no working target/environment accepted. |
| `backend/tests/Support/SobWindowsLease.php` | Fixture-only native helper lifecycle and framed identity/liveness responses. |
| `backend/tests/Support/sob-windows-lease.ps1` | P/Invoke native handles, file/volume/parent identities, reparse/link checks and disposable junction-test setup. |
| `backend/tests/Support/SobRelease.php` | Exact staged allowlist, hash/source pins, native asset leases and repeated inventory checks. |
| `backend/tests/Support/SobWitnessRegister.php` | Serialized paired claims/completions with checkpoint and history consistency checks. |
| `backend/tests/Support/SobCustody.php` | Sodium authenticated encryption, fresh artifact data keys and sealed-box wrapping. |
| `backend/tests/Support/SobFence.php` | Synthetic writer classes and maintenance-generation evidence. |
| `backend/tests/Support/SobBoundary.php` | Composes the above into read-only inspection and encrypted-content simulations; no migration dispatcher. |
| `backend/tests/Support/sob-worker.php` | Isolated claim/concurrency/crash subprocess using disposable fixture metadata/keys. |
| `backend/tests/Unit/SobBoundaryTest.php` | Native boundary, authorization, staging, witness, crypto, maintenance, contention and termination assertions. |
| `XLIPA-B3-2-C-SYNTHETIC-OPERATIONAL-BOUNDARY-PROTOTYPE-2026-11-07.md` | This report. |

The existing OSD report was already untracked when this task started. It is part of the preservation baseline, not a new SOB addition. Existing frontend, migration history, accepted security contracts, tests and working-disabled activation manifest are preserved.

## 4. Authorization protocol — VERIFIED in fixtures

The detached Ed25519 signature covers `xlipa-sob-prototype-permit-v1` followed by a NUL byte and the exact canonical permit bytes. The accepted offline signature domain is deliberately incompatible. Canonical JSON sorts object keys, preserves list order and requires byte-for-byte decode/re-encode equivalence; whitespace variants and duplicate fields are rejected even when signed. Exact top-level and target key sets reject unknown fields. Parser/signature sizes are bounded.

Each permit binds version, random 128-bit permit/run identifiers, one action, simulated operator and host, fixed synthetic environment, named fixture source/root, native identity digest, full source hash, reviewed release/policy/executor hashes, not-before/expiry, maximum duration, revocation epoch and exact register checkpoint. Trusted fixture expectations are supplied separately by the harness. Capability serialization, deserialization and cloning are refused.

Actions are exactly `inspect`, `backup_simulation`, `restore_simulation` and `migration_simulation`. Only generated `xlipa-sob-<20hex>` roots directly under the system temp directory and fixed source basenames are accepted. Production environment substitution, working action names and arbitrary targets fail before the target-access hook. The source/action relationship is checked during authorization.

Validity is at most 300,000 ms, execution at most 60,000 ms and simulated status age at most 60,000 ms. Health, suspension, revocation epoch, revoked permit IDs, UTC validity and monotonic duration are checked repeatedly. Clock/status providers are explicitly synthetic trusted inputs, not deployed time/revocation services. Operator/host strings are simulated identities; they are not OS authentication or device attestation.

## 5. Windows identity results

The helper uses `CreateFileW`, `GetFileInformationByHandle` and `GetFinalPathNameByHandleW`. Parent directories are opened from the volume root downward with reparse-point opening semantics and delete-sharing excluded; source/release files are opened for actual read access with read-sharing only. Native metadata supplies volume serial and file index, plus directory/reparse attributes and link count. Exact final-path comparison rejects unexpected aliases; hard-linked files are refused. Held handles and link/reparse checks are refreshed by liveness requests.

The selected flags follow the native sharing/open semantics described by [Microsoft CreateFileW documentation](https://learn.microsoft.com/en-us/windows/win32/api/fileapi/nf-fileapi-createfilew). Actual fixture assertions, rather than string/path checks alone, establish the bounded behavior below.

| Boundary | Executed fixture assertion |
|---|---|
| Canonical path / unexpected alias | `../` root and filename bypasses refused; native final path must equal the requested canonical path. |
| Volume/file/parent identity | Structured native IDs are observed; file and parent identities differ and are included in the target digest. Replacing either the source file or its fixture directory before acquisition invalidates the approved identity. |
| Hard links | A real disposable hard link increases link count; native acquisition refuses it. |
| Junction/reparse | A real disposable junction is refused while walking parents; nonrecursive junction removal preserves its target fixture. |
| Replacement while held | PHP write, file rename and directory rename attempts are rejected; read-only SQLite access still succeeds. After lease release, normal fixture renames succeed. |
| Contention | An existing SQLite writer prevents the restrictive native source open with Windows sharing error 32. Closing that writer permits a separately claimed capture. No lease is relaxed to force a SQLite BUSY result. |
| Helper loss | A closed lease fails liveness; the operation cannot treat it as valid fencing. |

**NOT TESTED / unresolved:** Windows privilege escalation, hostile filter drivers, a malicious parent-reparse mutation between checks, every alias/filesystem type, authenticated helper deployment, atomic binding to SQLite's own native handle, native destination-handle publication and a write-compatible migration lease. The helper uses a blocking framed pipe; production-grade watchdog/job-object supervision is absent. The tests do not prove an OS sandbox or general malicious-filesystem race resistance.

The helper launches hidden/noninteractive with `-NoProfile` and a process-only execution-policy option. No persistent machine/user policy, ACL, service or synchronization setting was changed.

## 6. Immutable release staging results

The reviewed fixture inventory is exactly `artifact.txt`, `contract.json`, `executor.txt`, `policy.json`. The artifact is inert text. `executor.txt` contains the pinned digest inventory of nine fixed prototype source/helper files; the policy is a fixed canonical synthetic-only profile. The permit binds their digests and the complete inventory digest. Source/contract substitutions, executor/policy substitutions, unknown migration files and direct absolute-artifact requests are refused.

Native leases prevent tested writes/renames to selected assets and their directories. Inventory, asset digests and actual prototype source/policy digests are rechecked at execution boundaries. A newly inserted release file after selection stops publication and leaves the claim consumed. No artifact is included/evaluated; no Laravel migration directory discovery or selected migration execution occurs.

This is a lease-and-validation prototype, not a deployed immutable release build or restrictive ACL policy. Directory handles do not prohibit creating another file; repeated inventory checks detect that tested change. Current interpreter/helper sources are sampled by hashes rather than installed as an independently protected immutable binary. Malicious deployment-time substitution remains an operational blocker.

## 7. Independent witness/register results

Explicit initialization creates an empty primary journal and a witness journal in distinct generated fixture roots. Execution never invokes initialization or recreates missing files. A fixed lock order serializes both journals. Canonical records bind sequence, prior head, permit/run IDs, permit digest, claim/completion state and result digest; checksum-linked histories must match exactly before use.

Claim order is primary append/flush/fsync, then witness append/flush/fsync, then target access. A positive hook asserts both stores already contain the claim before the first target-access boundary. Two subprocesses competing for one permit produce exactly one claim winner. Permit and run identifiers are both single-use, and a stale checkpoint cannot claim a newer history.

Completion follows verified output and binds its exact digest through the same paired publication. A crash between primary and witness appends makes the register inconsistent and blocks subsequent execution. A crash after witnessed claim never refunds it. Failed operations remain claimed/incomplete; no successful terminal result is fabricated, no automatic journal repair/reset/resume exists. Missing, torn, replayed, conflicting or singly rolled-back history is rejected.

**Independence is simulated.** Both stores are local disposable files controlled by the test account, without operational ACL separation, a remote witness, signing custody or an external freshness root. An actor able to roll back/rewrite both histories consistently is outside the demonstrated guarantee. This is an unresolved deployment blocker, not a production anti-rollback claim.

## 8. Encrypted recovery custody results

Each test independently constructs a new SQLite source with two synthetic fixture tables. The read-only inspector uses the accepted authorizer unchanged, verifies the conservative header/sidecar profile, checks SQLite integrity/FKs and expected fixture schema/ledger state, then serializes ordered logical rows. It does not copy a working database or use one as a template.

`backup_simulation` encrypts that logical payload to `backup.sob`. `restore_simulation` requires original completed digest evidence, decrypts the content and republishes it encrypted as a new `restore.sob`, chaining provenance to the original ciphertext. This demonstrates encrypted custody and failure boundaries only. It does **not** restore SQLite pages, recreate a runnable database or extend RRT's actual backup API. The original SQLite/source and completed backup remain unchanged in the success test.

Cryptography uses sodium XChaCha20-Poly1305 authenticated encryption with a fresh random 32-byte data key and random 24-byte nonce for each artifact. A sodium sealed box wraps the data key to the disposable encryption identity. Version/algorithm, provenance, wrapped key and nonce are authenticated as associated metadata. No custom cryptographic primitive is introduced. See the [PHP AEAD API](https://www.php.net/manual/en/function.sodium-crypto-aead-xchacha20poly1305-ietf-encrypt.php).

Sealed boxes provide recipient encryption and integrity, not sender identity authentication. Accepted restoration also requires the independently modeled original permit/run completion record to name the exact ciphertext digest. A test creates a fresh, cryptographically valid envelope using the public encryption key and copied provenance; restore refuses it because it has no matching completion evidence. This separation follows the [PHP sealed-box contract](https://www.php.net/manual/en/function.sodium-crypto-box-seal.php).

Tests distinguish fresh wrapped data keys/nonces/ciphertexts; reject missing/wrong recovery keys, modified ciphertext/provenance/wrapping/nonce and unknown metadata; and verify independent backup/restore claims. Publication uses `x+b` exclusively new files, durable writes, ciphertext read-back, decryption/content/provenance round trip and paired completion evidence. Interruption never authorizes overwrite. Capacity and partial-I/O failures are explicitly injected, not physical disk-full experiments.

Signing and encryption keypairs are generated separately per test. Artifact data keys are zeroized in `finally`; parent test private key variables are zeroized at teardown. Crash workers receive disposable recovery material through stdin for round-trip verification, never a signing secret or operational key. No claim of comprehensive PHP-memory erasure is made. No row values or key material are written into this report.

**NOT TESTED:** real key storage/rotation/recovery, encryption-at-rest settings, ACLs, secure deletion/retention, tamper-resistant evidence custody, untrusted directory replacement during output creation, key release by independent people or storage-device durability. Plaintext logical content exists in test process memory; no general sensitive-data handling approval follows.

## 9. Maintenance/quiescence simulation

The model tracks web, queue, scheduler, CLI and long-running-transaction writers. Any active writer prevents acquisition; new writers, explicit fence loss and generation changes invalidate existing evidence. Unknown writer classes fail closed. The boundary rechecks generation before access, encryption, writes and completion. Tests interrupt maintenance and introduce a writer after output reservation; the output remains incomplete and the permit consumed.

This model does not enumerate or fence real Laravel/Windows writers, enforce admission control, drain jobs, stop services or control OneDrive. Its generations and trusted state are fixture inputs. Native read-sharing restrictions complement these tests but do not turn the model into an actual maintenance service. Backup and migration simulations require valid fence evidence; migration simulation still dispatches no migration.

## 10. Security and crash-test evidence

The new suite covers authorization denial before target access; signature/domain/canonical/unknown-field failures; host/operator/environment/action/release/policy/executor binding; expiry, duration, suspension and revocation; native file/directory replacement; hard links/junctions; release substitutions and late inventory drift; claim replay/concurrency/checkpoint/rollback/loss; cryptographic forgery/tampering/key loss; partial publication; all five writer classes and mid-run fence loss; sidecars/header rejection; contention; and unknown migration completion state.

Eight real subprocess termination cases target backup claim-primary, witnessed-claim, output reservation, ciphertext publication and completion-primary boundaries, plus restore reservation, ciphertext publication and completion-primary boundaries. Restart must deny consumed claims or inconsistent witnesses; incomplete files cannot be overwritten, and a published-but-uncompleted backup cannot authorize restore. These are process-kill tests, **not power-loss tests**.

Development evidence is retained: an initial helper launch was rejected by script execution policy; a process-only launch option addressed it without persistent policy changes. An EOF-oriented Windows pipe read deadlocked a disposable run, which was stopped and replaced with newline framing. An attribute-only file handle allowed a write in a negative assertion; actual read access corrected the lease, and the write-denial test passed. The first complete run passed 80/81 with 285 assertions; its sole failure expected SQLite `locked` although native sharing error 32 had already safely denied access. The assertion now requires that exact native error and checks success after writer release. No guard was weakened to pass. An initial inline runtime-capability probe also had a shell-quoting error and was not used as evidence.

## 11. Regression results

Existing runtime: `C:\Users\hp\.config\herd\bin\php85\php.exe` (**VERIFIED** PHP 8.5.12); PHPUnit reports 12.5.38. Commands run from `XLipa/backend`:

```text
<php85> vendor/bin/pint --dirty --format agent
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Unit/SobBoundaryTest.php --filter 'test_native_identity|test_encrypted_synthetic_capture|test_native_hardlink'
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Unit/SobBoundaryTest.php
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Unit/SobBoundaryTest.php --filter 'test_native_junction|test_extra_release|test_claim_is|test_public_key|test_sqlite_journal|test_encrypted_synthetic|test_real_process'
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result
```

Pint completed before test runs; its final invocation passed. Earlier focused native/custody tests passed **3/3, 21 assertions, 21,426 ms**. The revised focused selection, before the final pre-acquisition source-pin check/two additional pin tests, passed **19/19, 64 assertions, 174,895 ms**, including all eight SOB termination cases. The initial complete development run's 80/81 outcome is retained in section 10 and is not acceptance evidence.

| Execution | Tests / assertions | Failures / errors | Duration |
|---|---|---|---|
| Revised focused SOB selection, prior revision as described above | **19 / 64** | **0 / 0** | **174,895 ms** |
| Final combined regression, final formatted source pins in section 12 | **444 / 3,958** | **0 / 0** | **669,514 ms** |

The final command freshly executed all 358 existing cases plus 86 new SOB cases; it did not reuse a historical pass. Existing B/C tenant/roles, IDs/AAD/crypto, OTP/proof, R06 pending obligations, audit append-only, migration selection/ledger safety, authorizer and RRT backup/restore assertions passed unchanged. SOB's eight process-kill cases and the existing six RRT process-kill cases were included; other accepted concurrency/crash controls also remain in the suite. No test was skipped, deleted or weakened. There was no separate final 86-case-only run; the final new-case evidence is within the combined execution.

The existing isolated bootstrap uses memory/default SQLite, fixture keys, inert mail/queue configuration and absent real dotenv/config-cache paths. Existing Laravel tests explicitly select the synthetic environment path and prevent stray HTTP requests; SOB unit tests and workers load only the isolated bootstrap/autoloader. No ordinary working Artisan boot/install/setup command ran. Runtime/formatter/test sandbox escalation was allowed; no automatic approval rejection occurred.

## 12. Preservation evidence

Before implementation, 215 pre-existing source/document files were SHA-256 inventoried in memory from tracked/nonignored-untracked paths using an explicit source-extension allowlist. All SQLite/sidecar/database, `.env`, credential/key/secret and protected metadata paths were excluded before hashing. This inventory includes the existing OSD report. It is not an exhaustive certification of ignored dependencies, caches, binary assets or storage.

**VERIFIED final comparison:** all **215 baseline files unchanged**, zero changed/missing. All eleven prototype/test source hashes below also remained unchanged across the final combined run. Git tracked and staged diffs are empty. There are exactly twelve task additions (eleven source/test files and this report); Git status also retains the pre-existing untracked OSD report. Current HEAD is `d78797cf2cceb8d4c41b4fe375cee0f8cdff9975`; historical report HEAD values are not relabeled as current observations. No Git stage/commit/push/deploy occurred in this task.

Four disposable roots abandoned by the interrupted development run were removed only after checking their exact resolved temp-parent paths, non-reparse status and expected file inventories. Removal was nonrecursive and used literal paths. Final inspection found **no `xlipa-sob-*` fixture directory remaining**. This report does not hash or certify working SQLite, real environment files or secrets.

Repository whitespace checks and separate no-index whitespace checks for all twelve additions passed with no whitespace diagnostics. All nine local baseline-report references resolve, and all sixteen requested report sections are present. Git's existing LF/CRLF normalization notice is not a source change or a test failure. No-index exit 1 for an added-file difference was distinguished from whitespace errors; no repository Git setting was changed.

SHA-256 of the final formatted prototype source revision (the report has no self-referential hash):

| Source path | SHA-256 |
|---|---|
| `backend/tests/Support/SobBoundary.php` | `0f2b2273b6c2182db519a385e35b334dc700cd2dedafa1d340d3d202a3213f0b` |
| `backend/tests/Support/SobCustody.php` | `38ec0927ddc5de99bdc027137d40c108abb96c8f14586f2fd1ad62defa3bf8c7` |
| `backend/tests/Support/SobFence.php` | `b72f49dd263860eb033c699eb1f1aa5f4771a5d12ed050595f1d872f1006f61c` |
| `backend/tests/Support/SobFixture.php` | `b2b1e0976b78996786eecb7a71e95976dcce9440ddc73b62ac1c95f219c281e4` |
| `backend/tests/Support/SobPermit.php` | `886b89e84c2af976f54b7493a9d002e4999a03cf86da86de463db3f7378835cf` |
| `backend/tests/Support/SobRelease.php` | `3befff3af0e6437d629583f78c1ff669d5f00b82bb899875c9a64771b734a798` |
| `backend/tests/Support/SobWindowsLease.php` | `0634be6dec0cd18e85fbe303ec69f7b80d240c0675609f931aa381f157dcb0a5` |
| `backend/tests/Support/sob-windows-lease.ps1` | `acb69d843a4613d0f89c2d3e46bc10d7f57107352ff10d42e4b193c682673aca` |
| `backend/tests/Support/SobWitnessRegister.php` | `973679b48ad67531009c748164f1de6ad7724ab74214bd0c5c673bca9f2ff2eb` |
| `backend/tests/Support/sob-worker.php` | `dccf10ec3bd2090989d1bc9a1ae817e297bb83ac605729199fd23ab38ca1ef85` |
| `backend/tests/Unit/SobBoundaryTest.php` | `677246bb9af6bd4a349e0273715912a44a2251fe9ceeeda8d956d46ca08b55cc` |

## 13. Remaining operational blockers

- Named/authenticated owner, operator, independent signing/evidence/recovery custodians, trusted host posture, real time and fresh revocation/suspension services remain unassigned/unprovisioned.
- Native write-compatible migration/destination identity, SQLite engine-handle identity binding, hostile reparse/race resistance, helper watchdog supervision and immutable deployment custody require separately reviewed implementation and adversarial evidence.
- The two local journals do not provide independent production custody or protection against coordinated rollback. Operational witness provisioning, authentication, availability and incident epoch recovery remain unresolved.
- Actual backup storage location, source OneDrive strategy, restricted ACLs, encryption at rest, secondary failure domain, real key lifecycle, retention/deletion and independent recovery access require owner decisions and separate provisioning authority.
- Actual quiescence, queue/scheduler/web/CLI fencing and controlled service resumption remain unimplemented. Unexpected WAL/journal recovery and DDL/ledger ambiguity still require stop/incident review, never repair or ledger adoption.
- Physical disk-full/I/O/power-loss behavior, measured RPO/RTO, real-data restore readiness, post-backup obligations and provider/financial reconciliation are **NOT TESTED**. No synthetic result establishes these capabilities.

## 14. Decisions requiring owner approval

Review acceptance of this bounded prototype; select the residual-threat model and the exact next synthetic implementation package. Separately name real roles/alternates, authentication and host identity; approve signing/encryption custody and time/revocation sources; choose an independent witness and incident process; approve source/synchronization and protected custody locations; and decide maintenance, retention, RPO/RTO and recovery access policies. No actual people, storage destination or operational keys are invented here.

Approval of this report supplies no working gate. Working read-only revalidation, backup, restore rehearsal, migration, runtime activation, real identity/contact processing, each external provider and live payments still require their own concrete reviewed scope and explicit approval.

## 15. Recommended next stage

**PROPOSED:** owner/technical review of SOB-1.0, followed by separately authorized synthetic hardening of the remaining native destination/write-compatible handle, immutable helper deployment and genuinely independent witness interfaces. Preserve accepted helpers and the working-disabled manifest. Prove the selected native guarantees and document unsupported cases before preparing any working package.

Only after the operational identities/custody/maintenance decisions are explicitly approved and their protections validated should a concrete working read-only package be prepared for separate owner authorization. Do not gather fresh target fingerprints while preparing it without that authorization. No backup, restore, migration, service activation or provider action is an automatic next step.

## 16. GO/NO-GO assessment

**GO for owner review of this isolated prototype and its bounded evidence.** Synthetic control tests do not remove the operational blockers above or reopen accepted B contracts.

**NO-GO for working access and every later execution gate under this task.** No working SQLite, real `.env`/secret, real signing/encryption authority, provider account, service/sync configuration or real identity/contact record was accessed or provisioned. No working backup/restore/migration/runtime action, real message/payment, commit, push or deployment occurred.

**WORKING ACTIVATION REMAINS NO-GO. STOP FOR OWNER REVIEW.** XLipa is a WEBSITE supporting TZS only.
