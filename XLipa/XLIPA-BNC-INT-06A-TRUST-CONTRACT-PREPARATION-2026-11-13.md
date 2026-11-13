# XLIPA — BNC-INT-06A Trust Contract Preparation

13 November 2026 · Africa/Nairobi · Isolated contracts and fictional verification only

## A. Repository baseline

HEAD: `70b2d1a4078923df99c9509522f52d4b4481e73c` (`MODIFIED`). Initial index and tracked diff were empty. Eight existing untracked paths from INT-03/04/05 were retained: those three reports, MerchantOnboardingPreparationTest, BusinessNameCompletionHttpPreparationTest, BusinessNameCompletionHttpInput, BusinessNameCompletionRequestContext and FakeBusinessNameCompletionRequestContext. None was overwritten or formatted.

Read backend AGENTS.md, INT-01 through INT-05 reports, WorkflowSchemaReadiness, completion admission/denied implementation/command/resolver, INT-05 validator/context and fictional testing infrastructure. No other AGENTS.md was found; workspace, XLipa and backend `.ai/rules` are absent. The referenced testing-best-practices skill is unavailable in this session. Installed metadata identifies Laravel 13.35.0, PHPUnit 12.5.38 and Pint 1.32.1. Historical 530/600/616/702-test results were treated as reports, not substituted for fresh execution.

Before edits, captured **321** existing Git-selected tracked/untracked nonignored source/asset/test/config/report hashes in the session inventory. Excluded environment/secrets/credentials/private-key, dependencies/storage and database/sidecar paths. Verified all 29 existing source/artifact/foundation pins and the three BNC manifest digests. This proves current-session preservation, not universal historical provenance.

## B. Exact files created

Paths relative to XLipa; eight persistent files are added:

| File | Purpose |
|---|---|
| backend/app/Actions/Merchants/TrustedWorkflowProfile.php | Pure, fail-closed descriptor/release-binding validator |
| backend/app/Actions/Merchants/TrustedReleaseApproval.php | Independent approval/revocation authority interface |
| backend/app/Actions/Merchants/TrustedStoreLineage.php | Actual connection/store/fence and scoped replay interface |
| backend/app/Actions/Merchants/TrustedCompletionTimePolicy.php | Trusted clock, policy, retention and context-access interface |
| backend/tests/Support/FakeCompletionTrustAuthority.php | Fictional release/time/lineage authorities and owned memory connections |
| backend/tests/Support/FakeTrustedCompletionAdmission.php | Fictional implementation of the existing admission interface |
| backend/tests/Feature/TrustedCompletionContractsTest.php | Isolated trust and INT-05 compatibility acceptance tests |
| XLIPA-BNC-INT-06A-TRUST-CONTRACT-PREPARATION-2026-11-13.md | This report |

No new operational adapter, configuration, provider binding, route, controller, schema, migration, manifest or UI was created or modified. A temporary generator wrapper outside the repository was removed after use.

## C. Existing contracts reused

Reused BusinessNameCompletionAdmission unchanged rather than adding a competing admission abstraction. Runtime still binds DeniedBusinessNameCompletionAdmission. Reused WorkflowSchemaReadiness's four constants and preserved its complete source/catalogue/ledger compatibility gate unchanged. Reused BusinessNameCompletionHttpInput::expectations for exact original types and FakeBusinessNameCompletionRequestContext for custody/state semantics. The INT-05 interface/validator and completion command/resolver are byte-preserved.

The new selector does not replace schema readiness, populate workflow_schema configuration, infer a profile or provide operational release authenticity. Its three collaborators are unbound interfaces. The only implementations of those interfaces introduced here live under tests/Support, require synthetic testing, and provide no independent real-world authority.

## D. Trusted profile and release authenticity

`TrustedWorkflowProfile::select(descriptor, runtime, observed, approval, time)` accepts an exact version-1 descriptor with these distinct fields:

```text
requested_profile, approved_profile, release_id,
manifests, sources, environment, store_id, generation, fence_epoch, worker_id,
approval_id, provenance, not_before, expires_at, revoked, version
```

Requested and independently approved profiles must match and belong to exactly foundation, b32c, se1, b32c+se1+bnc1. There is no foundation fallback or config/database/request inference. Exact runtime inputs are requested_profile, environment, store_id, generation, fence_epoch and worker_id; they must match the descriptor. These inputs must ultimately come from trusted deployment custody, not merchant assertions. Fence epoch is a positive PHP integer; identifiers have bounded ASCII syntax; validity timestamps are bounded integers and checked against the independent clock. Missing, expired, future, revoked, unsupported, unknown-field or mismatched descriptors fail with a generic exception and no chained private error.

Manifest maps require exactly bnc/integration/backend; source maps must be nonempty. Lowercase SHA-256 digest values and exact map equality to supplied observed artifacts are mandatory. Observed artifacts are a measurement contract, not self-authenticating facts: the selector performs no filesystem scan and accepts no claim that local matching hashes establish provenance. A future trusted measurer must bind the actual release contents independently. Source map membership and compatibility are also bound by independent approval; the unchanged readiness gate remains responsible for the complete frozen source/schema/ledger checks.

TrustedReleaseApproval must independently approve the entire descriptor, including immutable release identity, profile, manifest/source maps, environment, store generation/fence, worker, approval provenance and revocation status. An unavailable authority denies safely. No live signer, keys, network service or operational verifier is invented. The fixture authority stores approvals separately from supplied descriptors. Its immutable release ID cannot be reused with different source/manifests/profile; a changed release requires a distinct successor identity and independent approval. Tests change only fictional digest values, never files or existing manifests.

The tests measure current frozen files read-only, compare their source pins and then separately enroll a fictional approval snapshot. This models independence of inputs; it does not prove independent production custody, signature authenticity or deployment approval. Local source and local test authority are in the same trust domain during tests. Future provider/source changes still require a separately approved successor release definition and compatibility chain. None was prepared or activated here.

## E. Admission and authority design

FakeTrustedCompletionAdmission implements the exact existing BusinessNameCompletionAdmission API, and is never container-bound. It composes the selector and fictional authority. Grants are explicit per operation mode and bind actor, merchant, original nine-field expectation tuple and session. A completion grant cannot authorize resolution; resolution requires its own grant. No grant is read from request/config/workflow rows.

Fictional current-owner state is checked independently from release approval: exact actor/tenant/membership, verified and active user, owner role, unrevoked membership, available merchant, current session identity and session auth/email epochs. Completion additionally requires the original auth/email/authority epochs. Resolution preserves the immutable historical expectation tuple but checks current session epochs independently, matching the existing resolver distinction. Changed membership, revoked user, manager/staff, wrong mode, wrong session or withdrawn owner authority denies.

The local owner array is a fictional fact provider, not operational evidence or a replacement for Context::authority. Existing command/resolver fresh DB authority checks remain unchanged and independently required. Reconciliation readiness is separately actor/tenant scoped; external reconciliation failure remains a veto. A recovery hold blocks new completion while independently approved resolution may inspect the original operation; it never authorizes replay or deletion.

**Fictional grants cannot authorize working-store operations:** pre-query expected-profile and authorization calls require the established testing/synthetic environment, SQLite memory configuration with no URL, and a managed connection whose raw PDO is one explicitly constructed/enrolled by the fake via literal sqlite::memory:. The check never invokes a lazy PDO closure. Changed target configuration, unknown PDO/closure, mismatched store generation or unapproved connection denies. No working path is opened even in negative tests. The fixture cannot be used as an operational admission adapter by merely wrapping configuration values.

## F. Store lineage, restore fencing and replay

TrustedStoreLineage requires independent binding of the actual connection to authoritative store identity, generation, restore/cutover fencing epoch and admitted worker. It also declares a scoped request replay veto. Reconnect or schema/row equality cannot supply authority. A future implementation must revalidate these facts across writer acquisition, reconnection and final admission without entering the deliberate record/witness gap.

The fake explicitly enrolls only its own new SQLite memory connection object/PDO pair alongside a snapshot of current runtime identity. It checks object enrollment, exact PDO, database name and runtime snapshot. A newly reopened but unenrolled connection is denied even if it is also memory SQLite. Replacing the PDO on an enrolled object denies. Cutover denies all stores; generation/fence/worker changes exclude existing connections and stale descriptors. A new generation requires new independent approval and a newly enrolled connection; it does not readmit the old writer. Authority outage denies.

Replay fixture state tracks dispatched actor+merchant+UUID. Once marked dispatched, completion is denied; independent resolution remains possible but never clears the spent marker. Original tuple/grant checks prevent substitution of a different actor, tenant, membership, application, attempt, epoch, version or UUID. This models a replay veto only; it is not a durable distributed reservation, nor does it invoke/retry the existing command. INT-05 remains responsible for demonstrating dispatch-once context transitions; no new transaction engine was added.

There is no real restore, backup, target inspection, old-process termination, distributed lease or cutover coordinator in this stage. The tests prove fictional rejection behavior, not actual production old-writer exclusion or authoritative absence.

## G. Trusted time, policy and retention

TrustedCompletionTimePolicy separates trusted milliseconds, current record policy, historical retention permission and context-access decisions. The interface requires bounded/nonregressing time, abnormal-jump/outage denial, approved versioned policy, explicit-resolution authority and preservation of unresolved holds. No PHP system clock or Laravel now() is used by the new authority.

The fictional clock is explicitly advanced by tests. It accepts 0..253402300799999, rejects regression, rejects a step greater than 1,000ms and denies on outage. **The 1,000ms bound is a fictional test policy, not an approved production skew/jump rule.** Clock provenance, persistence across process restart and independent review of actual time remain missing.

Fictional policy versions have bounded retention windows. Historical access uses the original policy, not automatic substitution of the current policy. Unknown/withdrawn policy, future occurrence and retention expiry deny. An unresolved hold does not override payload access expiry. Context access requires valid preparation/expiry/retention ordering; new dispatch requires unexpired preparation and no recovery hold. Explicit resolution may access unresolved context after preparation expiry only before retention expiry and only with separate authorization. Access denial never deletes or discharges the hold. Existing INT-05 fake status retains unresolved work after expiry; no cleanup policy or live retention activation is introduced.

## H. INT-05 private-context compatibility

No actual signature incompatibility was found and **the interface was not changed**. The compatibility test prepares canonical combining-Unicode/interior-space name bytes and original tuple, dispatches once, marks uncertainty, then resolves explicitly through a callback consulting the new fictional mode-specific grant. Exact original UUID, application/attempt, versions, membership/epochs and name bytes survive.

The existing generation string can carry a server-derived opaque binding to store ID + generation + fence epoch (the test uses a digest of those public fictional identities). A future adapter must authenticate that binding through lineage/release authority; hashing strings alone is not trust. Runtime authority can also bind environment/release/worker without changing the existing context method signatures.

Session rotation and generation mismatch are denied rather than silently rebinding the old operation. Revocation denies explicit resolution even with an existing grant; the unresolved state remains. The original session can access only under current approved authority. This is conservative compatibility, **not implementation of post-rotation recovery**. A reviewed handoff/rebinding mechanism and original-context retention after logout remain dependencies. If future requirements demonstrate that the interface cannot support a safe handoff, owner approval must precede any interface change; none is requested or inferred here.

## I. Focused tests and formatting

Used `C:/Users/hp/.config/herd/bin/php85/php.exe` through approved execution escalation. Scaffolding used a temporary wrapper that required tests/bootstrap-isolated.php, selected the tests directory's absent __no_real_environment filename before kernel bootstrap, and called make:class, make:interface and make:test with --no-interaction (test also --phpunit), exit 0. Invocation: executable with `-d auto_prepend_file=` and `$env:TEMP/xlipa-int06a-scaffold.php`. That owned temporary wrapper was removed afterward. Read-only checks confirm the selected absent environment filename and its testing variant do not exist. No ordinary operational Artisan bootstrap was used.

All tests use the established bootstrap/TestCase: synthetic environment and keys, memory SQLite, absent environment/cache paths, array mail/session/cache, blocked stray HTTP. New focused tests create only literal SQLite memory PDOs, run no migration and execute no application/workflow SQL. Existing combined suites retain their guarded fictional migrations and owned generated temporary recovery databases.

Commands run from backend; `PHP` below denotes that exact executable with `-d auto_prepend_file=`:

```powershell
$files=@(
 'app/Actions/Merchants/TrustedWorkflowProfile.php',
 'app/Actions/Merchants/TrustedReleaseApproval.php',
 'app/Actions/Merchants/TrustedStoreLineage.php',
 'app/Actions/Merchants/TrustedCompletionTimePolicy.php',
 'tests/Support/FakeCompletionTrustAuthority.php',
 'tests/Support/FakeTrustedCompletionAdmission.php',
 'tests/Feature/TrustedCompletionContractsTest.php'
)
PHP vendor/bin/pint --dirty --format agent @files
PHP vendor/bin/pint --format agent @files
foreach($p in $files){ PHP -l $p; if($LASTEXITCODE -ne 0){exit $LASTEXITCODE} }
PHP vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/TrustedCompletionContractsTest.php
```

Final syntax: **7/7 passed, each exit 0**. Both final bounded Pint invocations passed, exit 0. Earlier formatting changes were limited to new-file imports, operator/brace whitespace and PHPDoc alignment; inspected before final execution. No existing/unrelated PHP file was passed to Pint.

Initial focused run: 60 tests, 59 passed, 1 error, 1,383 assertions, 1,948ms, exit 1. One test built a release identifier containing '+' from the BNC profile; the identifier syntax correctly denied it. Changed the generated fixture identifier to a hash-based ASCII value without widening the contract. Strengthened the fake's owned-memory pre-query guard. Intermediate run: 60 passed / 1,386 assertions / 1,979ms, exit 0. Added malformed-artifact, unsupported-approved-profile, database-configuration and session/owner-outage cases afterward.

**Final focused: 68 passed / 1,564 assertions / 2,180ms, exit 0.**

```json
{"tool":"phpunit","result":"passed","tests":68,"passed":68,"assertions":1564,"duration_ms":2180}
```

Per-case setup includes 20 current source-pin comparisons, so the assertion total includes repeated source preservation checks as well as behavioral assertions. This is not 1,564 distinct security scenarios.

Coverage includes valid/missing/revoked/unavailable approval; mixed or missing/extra/malformed manifest/source artifacts; immutable successor release approval; exact four-profile vocabulary; wrong environment/store/generation/fence/worker; abnormal clock/expiry/policy/historical access; actor/tenant/membership/epoch/session/mode checks; separate resolution grant; memory connection enrollment/PDO replacement/cutover/reconnect; replay and unresolved holds; preserved INT-05 tuple/name; session rotation/revocation; absent provider bindings and working-target exclusion.

## J. Combined regression

```powershell
PHP vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'TrustedCompletionContractsTest|BusinessNameCompletionHttpPreparationTest|MerchantOnboardingPreparationTest|BusinessNameCompletionTest|WorkflowSchemaReadinessTest|BusinessNameCompletionSchemaTest|AdminApplicationReviewTest|ApplicationSubmissionServiceTest|SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest|SubmissionLifecycleTest|SubmissionEvidenceCipherTest'
```

**Final combined: 770 passed / 6,848 assertions / 112,793ms, exit 0**, zero failures/errors reported. This is the authoritative fresh combined run for the final seven PHP files. Historical or overlapping focused counts are not substituted for or added to it.

```json
{"tool":"phpunit","result":"passed","tests":770,"passed":770,"assertions":6848,"duration_ms":112793}
```

## K. Preservation evidence

Initial source pin verification matched all **29** frozen C/SE/BNC/INT-01/INT-02 source/artifact and foundation migration pins, exit 0. Existing manifest SHA-256 values:

| Manifest | SHA-256 |
|---|---|
| BNC | `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3` |
| INT-01 integration | `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a` |
| INT-02 backend | `423d7ec0c2242aa42eb6e33c02036096c12fc3ff5a3160bf828c612904395990` |

New files are outside the frozen release manifests; no existing manifest was regenerated. Source search found no new use in routes/config/providers. Tests assert that runtime BusinessNameCompletionAdmission resolves to DeniedBusinessNameCompletionAdmission and the new authority interfaces and INT-05 context remain unbound. No expected-profile config is provisioned. Schema readiness, command/resolver, original request validator/context, registration/email, onboarding UI, public submission denial, Chief read-only behavior and payment holds are preserved.

Final inventory: **321 pre-existing files unchanged, zero changed, zero missing**, with exactly the eight additions listed in section B, exit 0. The eight initial untracked paths remain byte-preserved. Final explicit pin comparison again matched all **29** existing pins and all three manifest digests, exit 0. Index remains empty; `git diff --check` and explicit new-file trailing-whitespace checks passed, exit 0. No unexpected modification was found. Only this report was updated after final test execution.

No working database or sidecar was accessed, queried, copied, hashed, migrated or modified; only source/artifact/report paths were hashed. No real dotenv, operational secret/credential/key, live provider, payment operation, commit, reset, revert, push or deployment was used.

## L. Remaining operational dependencies

Independent release/approval custody and revocation; trusted descriptor provisioning; trusted release measurement and successor compatibility; real owner/session grant issuance; approved time/skew persistence; policy/retention/hold authority; durable encrypted atomic context storage; actual connection/store lineage; restore/cutover/old-writer/replay coordination; external reconciliation; operational authentication/mail readiness; target schema/ledger compatibility; target-bound migration/recovery approval and storage fault evidence all remain unresolved.

Fictional arrays, memory connection enrollment, manually advanced time and session/generation string equality demonstrate contracts only. No real signing infrastructure, preflight, target compatibility, durable grant/replay store, distributed atomicity, backup authenticity or production recovery was established. Real CSRF/raw HTTP/middleware/browser integration is still absent. Names remain optional for dashboard access; phone possession/identity requirements for later application submission stay separate.

## M. Smallest recommended next milestone

Owner review of these contracts first. Next, separately approve a narrowly scoped **INT-06B authority-custody and target-preflight procedure/evidence stage**, naming existing deployment, store, session, time/policy and retention owners and the exact future target. Resolve durable context storage and post-session-rotation recovery policy before proposing an adapter. No target access is implicitly authorized: any read-only target preflight requires explicit target-bound authorization, and backup/copy/hash/restore, migration or writes require their separate approvals.

Do not implement an operational adapter, successor release binding or HTTP integration merely because these contracts pass. INT-06B, INT-07 and later stages were not started.

## N. Final GO/NO-GO

GO for review of isolated contract preparation only. Operational profile provisioning, release trust, admission, context storage, working migration and HTTP completion remain NO-GO.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Business Name Completion HTTP writes: DISABLED. Public merchant application submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**
