# XLipa — B3.2-C Offline Security Guard Implementation

**OSG-1.0 · 7 November 2026 · Africa/Nairobi · OFFLINE / SYNTHETIC ONLY**

## 1. Executive summary — Muhtasari

**IMPLEMENTED / TESTED:** signed single-action permit validation, persistent single-use fixture claims outside SQLite, strict staged-release selection and read-only schema/ledger state classification. Invalid authorization and invalid release selection invoke **zero target-access callbacks** in the negative tests. Two independent PHP processes competing for one claim produce exactly one winner; restart/interruption, terminal outcome, corrupt journal and missing journal do not automatically release the permit.

The harness accepts **`:memory:` targets only**, regardless of a valid signature. It supports only `offline_classify` and `offline_select_release`; it has no working migration, backup, restore, provider or runtime-activation action. Classifier output is data, not an executor or recovery grant. Existing B/C migrations, guards, SQL/manifest and tests remain unchanged.

Kwa Kiswahili: guard inafanya validation ya synthetic permits na classification pekee. Permit iliyotumika haiwezi kurudi kuwa mpya baada ya restart. Hata signature halali haiwezi kuruhusu working path au `working_migrate`. Hakuna real signing key, working database access, backup au activation iliyofanywa.

**Final actual results:** targeted **54/54 tests / 172 assertions**; B/C + offline security **178/178 / 2,467 assertions**; full suite **286/286 / 3,314 assertions**. All final runs have **zero failures/errors**. **GO for owner review of the bounded offline implementation. Working activation remains NO-GO.** XLipa remains a WEBSITE supporting TZS only; no financial/multi-currency functionality was introduced.

Labels: **VERIFIED** denotes inspected source/file evidence or newly executed assertions within the stated fixture scope; **REPORTED** denotes earlier report evidence; **NOT IMPLEMENTED / NOT TESTED** identifies operational gaps; **REQUIRES OWNER APPROVAL** denotes separate future scope/execution permission.

## 2. Source review and exact files

Reviewed [WSR-1.0](XLIPA-B3-2-C-WORKING-TARGET-SAFETY-AND-RECOVERY-PACKAGE-2026-11-07.md), [CAR-1.0](XLIPA-B3-2-C-ISOLATED-ACTIVATION-REHEARSAL-2026-11-07.md), existing activation PHP/frozen SQL/selection manifest, existing B/C fixture and security tests, isolated bootstrap and Laravel migrator transaction/logging behavior. The accepted activation is still rehearsal-only; migration repository logging remains after its DDL transaction. No accepted guard was altered to accommodate the new harness.

Backend AGENTS.md was read; `.ai/rules` was absent at checked workspace/project/backend locations. No testing-best-practices skill was available in the catalog; none was installed. No dependency, plugin, sub-agent or production service was added. New files were created directly to preserve isolated boot boundaries; Artisan/test application work used only the existing testing bootstrap, never working dotenv.

**No pre-existing file changed.** New paths relative to `XLipa/`:

| File | Implementation |
|---|---|
| `backend/tests/Support/OfflinePermit.php` | Exact-byte Ed25519 validation and immutable, nonserializable/nonclonable synthetic capability; memory-only target/action/environment restrictions. |
| `backend/tests/Support/OfflineRunRegister.php` | Explicit fixture initialization, locked/fsynced append-only claim journal and terminal transitions; no automatic recreation/reuse. |
| `backend/tests/Support/OfflineReleaseSelector.php` | Complete staged-file allowlist, hash/path/manifest validation; returns metadata only, never includes/executes a migration. |
| `backend/tests/Support/OfflineStateClassifier.php` | Read-only foundation/completed/quarantine classification with exact expected schema/ledger maps and FK/integrity checks. |
| `backend/tests/Support/OfflineSecurityGuard.php` | Signature/release/claim/time/revocation/identity orchestration; the sole harness target-access callback boundary. |
| `backend/tests/Support/offline-claim-worker.php` | Independent synthetic PHP process for concurrent claim tests; no SQLite connection. |
| `backend/tests/Feature/OfflineSecurityGuardTest.php` | Positive controls, authorization/release denial matrices, state fixtures, replay/crash/register loss and concurrency assertions. |
| `XLIPA-B3-2-C-OFFLINE-SECURITY-GUARD-IMPLEMENTATION-REPORT-2026-11-07.md` | This requested report. |

All implementation classes are under `Tests\Support`, with no route/provider/command/production autoload registration. No new application/business table or working entry point exists.

## 3. Security architecture

### 3.1 Permit validator and fixture authority

Each test generates a disposable Sodium Ed25519 keypair. Only the public key enters the validator; private key bytes are never persisted or printed and are zeroized during fixture cleanup. No APP_KEY, real signing key, approval infrastructure or real owner permit is provisioned.

Signature covers `xlipa-offline-permit-v1` + NUL + the **exact JSON bytes**. The strict bounded JSON profile requires exactly ordered fields, correct primitive types and exact re-encoding; duplicate/extra/noncanonical fields are rejected. Signature is canonical lowercase hex of 64 bytes; public key length is validated. Missing/wrong public key and missing/tampered signatures fail closed with a generic authorization error.

Permit contains version, permit/run UUIDs, synthetic approval reference, one action, operator, host, exact target tuple, offline environment, not-before/exclusive expiry, maximum elapsed duration and release/artifact/contract hashes. All required operator/host/action/target/hash values must exactly match the independently supplied trusted fixture expectations. No wildcard, action array, real operation or environment substitution is accepted.

The harness's technical profile bounds permit lifetime to five minutes and elapsed duration to sixty seconds maximum; tests use controlled values. These are **offline permit parser bounds**, not changes to approved OTP/proof timing or selected production permit policy. UTC and monotonic health/order/budget are checked before access and before returning an outcome. Revocation is checked initially, after claim and at completion through an injected fixture authority. Unavailable/unhealthy state denies; no real revocation service is claimed.

`:memory:` plus a signed fixture identity token and `synthetic-only` class are the only permitted target. Disk paths—including the known working path—are rejected lexically before any target filesystem lookup or callback. Host/operator/identity are fixture assertions, not real OS principal/file-ID attestations. Signature correctness authenticates only the disposable test authority, **not real owner approval**.

Private construction and rejected serialization/deserialization/cloning prevent ordinary capability fabrication/transfer. The guard accepts raw permit/signature input and always validates it; no configuration boolean or reusable execution context skips validation. Arbitrary privileged PHP/reflection or malicious test adapters are not an OS sandbox boundary.

### 3.2 Run register outside the application database

A random private fixture directory under the system temporary root contains `register.anchor` and `claims.log`. Explicit fixture bootstrap uses create-new semantics and refuses a nonempty root; the guard/restart path never invokes initialization. Constructor rejects missing anchor/journal; an existing journal is opened **r+b**, not create-if-missing. Losing the journal cannot silently reset claims.

Each claim locks the journal using flock LOCK_EX, reads/validates prior entries, checks both permit UUID and run UUID, appends one combined claim and flushes/fsyncs before returning. Concurrent processes serialize the read/check/append; exactly one can claim either identifier. The journal is outside SQLite, so application rollback cannot refund an authorization.

Records form a checksum-linked canonical sequence. Malformed/torn/oversized journals fail closed; the fixture journal has a one-MiB read bound and no eviction/rotation/reset fallback. Checksums detect corruption, **not malicious rewriting by a privileged storage owner**. Operational ACL/anti-rollback custody remains unimplemented.

Transitions are claim → completed/failed/unknown/quarantined. A claimed record left by interruption stays consumed. Terminal states cannot return to claim or change to another terminal state. No TTL, expiry, successful classification, register restart or fresh permit with the same run UUID releases it. A signature/release rejection does not append a claim or invoke target access. A failure after durable claim consumes it even if no target callback occurs.

### 3.3 Release selector

Fixture staging copies only the existing immutable activation PHP and SQL contract into a new random `xlipa-offline-release-*` directory. It does not copy working records. A permit pins the staged manifest digest; manifest pins both asset digests. Exact mode/version/keys, one fixed artifact path, one contract path, requested migration list and complete filesystem inventory must agree.

Unfiltered/wildcard/directory/traversal/multiple selections, extra/duplicate artifacts, synthetic migration leakage, missing/modified files, unauthorized directories and link entries reject before target access. Selection returns metadata only; no shell command, include, migrator, bootstrap, backup or repair operation is dispatched. This is a simulation of release policy enforcement, not a staged production release.

Existing CAR selection manifest/artifact/frozen contract are read-only inputs and remain byte-identical. The new harness does not amend a working release allowlist or mark any migration applied.

### 3.4 Classifier and access boundary

Only after valid authorization, valid release, durable claim and fresh time/revocation checks may the trusted fixture adapter return its memory PDO plus identity token. Actual driver/database_list/identity/FK checks follow; attached databases or mismatches reject. Query-only is enabled and verified by the classifier. The classifier runs read-only metadata/ledger/count/integrity queries within its own read snapshot, then rolls that snapshot back without mutation.

| Classification | Exact structural requirement / response |
|---|---|
| `foundation` | Approved foundation schema-object map and original nine ledger names/batches match exactly. |
| `completed` | Complete final object map, original ledger plus exactly one batch-3 activation entry, and seven empty new tables. |
| `quarantine:schema_without_ledger` | Complete final schema with original ledger but no activation entry; no adoption/logging. |
| `quarantine:ledger_schema_mismatch` | Activation entry exists but expected schema/ledger state does not match. |
| `quarantine:drift_or_partial` | Unexpected foundation schema/index or partial installation/ledger drift. |
| `quarantine:nonempty_security_tables` | Structural activation exists but violates this inert-stage empty-table boundary. |
| `quarantine:integrity` / `connection` / `ambiguous` | FK/integrity failure, unsafe connection or inability to establish consistent classification. No automatic recovery. |

Expected maps are built from approved foundation metadata and frozen SQL, not learned from the database being classified. **Completed is a structural classification**, not independent proof of historical row preservation, lawful processing, backup freshness or runtime readiness. Those require their own baseline/postflight evidence; accepted CAR tests continue to cover synthetic historical-byte preservation.

Pre-access invalid authorization/selection/replay invokes zero database callbacks. An actual connection identity/FK problem can be detected only after the authorized fixture callback; those tests correctly record one callback and no successful classification. No secret/application records are selected by this classifier. No target access callback exists for release-only action.

## 4. Test coverage and isolation

The new 54-case suite includes:

- Twenty-one invalid authorization cases: missing/invalid/tampered signature, wrong/missing key, expired/not-yet-valid permit, operator/host/action/target/hash mismatch, action arrays/real working actions, environment substitution, invalid duration, unhealthy clock, revoked permit, signed working path and duplicate JSON key. Every case asserts zero access and an unchanged empty claim journal.
- Ten bad release cases: unfiltered/wildcard/multiple/traversal requests, unexpected/duplicate files, synthetic migration leakage, altered/missing asset and manifest tampering. All assert zero access and no appended claim.
- Foundation/valid control; replay/restart and reused run ID; interrupted claim/unknown terminal; corrupt/unavailable/lost register; expiry/revocation/clock rollback/duration boundaries after claim; wrong returned identity; disabled FK; attached memory DB; release-only/capability forgery rejection.
- Nine independent schema/ledger fixtures covering completed activation, DDL/ledger gap, missing/partial schema, ledger/schema drift, ambiguous state, nonempty security tables and deliberately injected FK inconsistency. The guard does not repair or mutate their schema.
- Two actual PHP processes compete at a controlled barrier for the same permit/run. One wins; the other and a restarted guard refuse reuse. Worker input contains only synthetic signed public metadata, never a signing secret.

Foundation/final-state fixtures use the original guarded memory-only foundation builder and existing selected activation rehearsal. Deliberate ledger deletes/changes and schema corruption occur **only inside independently generated negative fixtures**; no real ledger is accessed or fabricated. Existing B/C guards/tests are not weakened or edited.

The trusted callback adapter is test infrastructure, not an isolation boundary for executing arbitrary untrusted PHP. The harness implements no disk database opener, no OS identity-lease adapter and no production launcher. Temporary register/staging directories and public worker inputs are removed in checked fixture cleanup; signing secrets remain memory-only.

## 5. Actual commands and results

Executed from `XLipa/backend` using existing runtime `C:\Users\hp\.config\herd\bin\php85\php.exe`:

```text
<php85> vendor/bin/pint --dirty --format agent
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter OfflineSecurityGuardTest
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'OfflineSecurityGuardTest|B32C|B32Synthetic'
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result
```

| Final revision execution | Tests / assertions | Failures / errors | Duration |
|---|---|---|---|
| Targeted offline suite after formatting/register-loss hardening | **54 / 172** | **0 / 0** | 6,238 ms |
| B/C + offline security regression | **178 / 2,467** | **0 / 0** | 45,183 ms |
| Full combined regression | **286 / 3,314** | **0 / 0** | 55,555 ms |

The full run reran all prior 232 cases plus 54 offline cases. It includes the existing 108 regressions, original B/C suites and new guard assertions; these were not merely copied from historical results. No separate existing-regression command is invented.

All earlier development executions passed too: 44/137, 48/151, 52/162 and 53/167 targeted; the previous revision's full run passed 285/3,309 before register-loss hardening. Those totals are historical development evidence, not the final-revision totals. Negative authorization assertions are expected rejections, not failing tests. No constraint was relaxed to obtain a pass.

Pint completed successfully and formatted only the seven new PHP files over the development iterations. Final runtime/formatter/test access was allowed through sandbox escalation; no automatic approval rejection. No dependency install or real environment setup was run. Test bootstrap disables working dotenv, uses memory SQLite/public fixture APP_KEY, array mail/null queues and blocks stray Laravel HTTP requests. Existing B/C race fixtures remain disposable synthetic files; this task never connects to working SQLite.

## 6. Preservation evidence

Pre-write source-only baseline: **216 pre-existing tracked/nonignored-untracked files**, hashed in memory. **All SQLite paths/sidecars and all .env variants were deliberately excluded from enumeration/hashing/access.** No new claim about the working database hash, file identity, sidecars or current schema is made here. Earlier working observations remain earlier report evidence.

Pre-report comparison found all 216 source-baseline files unchanged, zero changed/missing. Only the seven new test/support files plus this report are intended additions. Existing approved B migrations, C rehearsal artifact/SQL/manifest, tests, historical reports, application source, frontend/branding and unrelated inventoried files retain their original hashes. No `.env`, signing infrastructure, real key, Git history or production registration change occurred. Ignored dependencies/caches/recovery trees are not exhaustively certified.

**VERIFIED final post-report comparison:** all **216 source-baseline files unchanged**, zero changed and zero missing. Exactly eight additions were found: the seven PHP implementation/test files and this report. No `xlipa-offline-*` temporary directory remained. `git diff --check` passed; HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`. This final report-only edit records those results. No working access/hash/copy, backup, restore, migration, runtime activation, NIN collection, live SMS/email, Selcom/payment service, staging/commit/push/deploy was performed.

## 7. Operational limitations and GO / NO-GO

**NOT IMPLEMENTED / NOT TESTED operationally:** real owner signing custody/public-key provisioning or rotation, authenticated OS principal/host attestation, current revocation service, trusted production clock, Windows file-ID/reparse/no-delete lease protection, immutable real release staging, protected independent run-register storage/anti-rollback, physical power-loss/disk-full durability, working-data backup/restore, OneDrive/filesystem controls and a working migration launcher.

Fixture root restrictions, flock and fsync demonstrate the tested local protocol; they do not prove real ACL/custodian or hardware guarantees. Journal checksums are not signatures. A privileged actor replacing/truncating all journal/anchor/trust material or restoring a stale operational register needs independent custody/freshness controls. No automatic recovery, register reset, claim rotation or inferred audit-history reconstruction is supplied. A corrupt/unavailable journal holds the harness closed.

**GO for owner/technical review of this bounded offline implementation and actual synthetic evidence.** This is not a working-target authorization mechanism ready for deployment. Working paths/actions are intentionally unsupported, rather than guarded by a flag that could be switched on accidentally. B/C accepted contracts remain intact; TZS-only WEBSITE scope is preserved.

**NO-GO:** working access/revalidation without a new explicit scope, working backup/restore/migration, runtime activation, real identity/contact processing, external providers/payments and deployment under this task.

## 8. Exact next owner approval required

After reviewing/accepting this offline result, the recommended next permission is **fresh strictly read-only target revalidation only**, using the already reviewed standalone read-only inspection approach: confirm canonical existing working target and effective config, refuse any creation/recovery/sidecar write, inspect redacted schema/ledger/integrity/FKs and produce one report. It does not promote this memory-only harness into a working opener.

Proposed approval wording:

> Naidhinisha fresh read-only target revalidation pekee kwa scope ya RTP-1.0, bila backup, restore, checkpoint, migration, runtime activation au kubadilisha guards. Ikiwa read-only access haiwezekani bila writes, simama na utoe ripoti.

This request is not assumed approved. Real signing/run-register custody and any future working-only implementation/permit issuance require their own reviewed scope; backup, restore rehearsal, migration, runtime, real processing and providers remain separate approvals. No old permit or earlier read-only approval is automatically reused.

**STOP for owner review. Working activation remains NO-GO.**
