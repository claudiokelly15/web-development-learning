# XLipa — BNC-INT-01 Strict Readiness Integration

13 November 2026 · Africa/Nairobi · Isolated integration evidence

Final verification completed on 13 November 2026 after the rate-limit recovery review. The existing implementation was retained. Fresh syntax, focused and combined regression results are recorded below; no implementation defect or bounded code fix was required. This completes isolated verification for owner review, not operational activation. The recovery report remains the historical account of the interrupted state.

## 1. Baseline and exact files changed

Continued BNC-1.0 at HEAD `fc1d7e1877674f65a9e70fd0e7ebedd5c4c68054`. Starting tracked/staged diffs were empty. The prior design, SQL report, integration review, BNC classes/artifact/manifest/test were existing untracked work. Read backend AGENTS.md and the latest Integration Security Review. Workspace and XLipa root AGENTS.md and `.ai/rules` at workspace, XLipa and backend were absent. No testing-best-practices skill was available in the session catalog.

Verified all six frozen C/SE pins, three BNC source pins and the existing BNC manifest digest before editing. Existing migration identities, fourteen SQL objects, object hashes and BNC ledger contract are preserved. Confirmed installed Laravel 13.35.0 and PHPUnit 12.5.38 through local installed metadata. Used existing PHP 8.5.12 through the environment-file-free bootstrap.

Paths below are relative to XLipa.

| File | Change |
|---|---|
| backend/app/Actions/Merchants/WorkflowSchemaReadiness.php | New trusted-profile selector and strict source/schema/ledger admission |
| backend/app/Actions/Merchants/ApplicationSubmissionService.php | Replace both direct SE readiness checks with selected SE/BNC admission, preserving profile identity across writer acquisition |
| backend/app/Actions/Merchants/LifecycleMutation.php | Exact admitted profile replaces presence detection, with reserved-writer revalidation and denial of BNC residue |
| backend/app/Actions/Merchants/VerificationReadiness.php | Add selected-profile schema gate before existing operational checks |
| backend/database/activation/bnc1/integration-manifest.json | New independent four-source manifest with existing BNC compatibility digest |
| backend/tests/Feature/WorkflowSchemaReadinessTest.php | New 53-case profile and BNC consumer acceptance suite |
| backend/tests/Feature/ApplicationSubmissionServiceTest.php | Append five BNC integration cases reusing existing fictional submission/replay/contention/unknown-outcome assertions |
| backend/tests/Feature/AuthenticationTest.php | Use supported foundation fixture, inject audit outage through the connection without changing schema |
| backend/tests/Feature/MerchantAccessTest.php | Use supported foundation fixture |
| backend/tests/Feature/MerchantApplicationSubmissionTest.php | Connection-level audit outage and recursive-trigger enforcement on generated reconnect |
| backend/tests/Feature/MerchantVerificationTest.php | Connection-level switchable audit outages preserve R06 fault/recovery tests under exact schema admission |
| backend/tests/Support/B32CRehearsal.php | Enable recursive triggers on the generated foundation connection |
| backend/tests/TestCase.php | Test-only trusted expected-profile selection using explicit canonical artifact selectors |
| XLIPA-BNC-INT-01-STRICT-READINESS-INTEGRATION-2026-11-13.md | This report |

Ten existing files changed and four task files were created. Existing registration implementation, onboarding controller/view, routes, Chief review source and BNC schema sources/artifact/manifest/test were preserved.

At the start of this final verification, the same ten tracked modifications and thirteen untracked files were present, including the recovery report; the index was empty and HEAD unchanged. Read backend AGENTS.md, recovery report, this draft and the preceding security review. No additional applicable AGENTS.md or `.ai/rules` was found. This continuation changes only this report; all existing unfinished work was retained.

## 2. Schema selection behavior

The helper reads trusted server configuration `workflow_schema.expected_profile`. A supported string or trusted Closure returning that string is accepted. Missing/null, malformed or unknown selection denies with generic 503 before schema admission. Request input supplies no selection and no setter was added to a route or controller. A deployment must supply an independently reviewed trusted selection. This task adds selection only to isolated test setup.

Tests/TestCase configures a fixture-only Closure from explicit canonical bnc1.selected, se1.selected and b32c.selected artifact selectors, in that order, or the explicit foundation fixture default. Invalid artifact selection returns an invalid profile and denies. The Closure never reads schema contents to infer a profile. Application source has no fixture selector fallback.

| Selected profile | Exact acceptance | Lifecycle | Internal submission |
|---|---|---|---|
| foundation | Frozen foundation definitions/source/complete catalogue and exact nine-row isolated ledger | Existing legacy operations under reserved writer | Denied |
| b32c | Foundation plus exact frozen C objects/audit/source and ten-row ledger | Existing legacy operations under reserved writer | Denied |
| se1 | Unchanged SE ready() plus strict internal-object/connection/ledger checks | Versioned | Schema capability only |
| b32c+se1+bnc1 | Exact unchanged ComposedSubmissionSchema::assertReady() | Versioned | Schema capability only |
| Partial, unknown, mixed or selected/actual mismatch | Generic denial | Callback never executes | Denied |

The selected validator is authoritative. Failure never selects a lower profile. Complete BNC selected against a restored SE store denies. BNC-only tables/indexes/triggers/ledger rows cannot enter legacy execution. The helper rejects unknown permanent objects, ANALYZE statistics, temporary objects and attached databases. It checks FK enforcement, CHECK enforcement, writable_schema disabled and recursive triggers for C/SE/BNC. Foundation permits its original recursive-trigger setting. All profiles require a clean foreign_key_check.

Foundation/C comparison uses the frozen foundation SQL hashes, exact C SQL where applicable and exact object-name set. SE calls the unchanged strict SE validator and adds exact supported ledger and internal-object admission. Its original ready() behavior is preserved, including false on BNC. BNC delegates to the existing composed validator with its original catalogue and FK/index checks.

The supported isolated ledgers are exact ID/migration/batch prefixes of the existing contract, comprising foundation IDs 1–9/batches 1–2, C ID 10/batch 3, SE ID 11/batch 4 and BNC ID 12/batch 5. Unexpected entries, missing entries, duplicate entries, batch changes and ordering/ID changes deny. This deliberately bounds foundation/C/SE integration to the verified fixture lineage as well as preserving BNC's existing contract. It establishes no working-ledger compatibility.

The helper rechecks every call and keeps no successful readiness cache. Release mismatch, missing selection, schema mismatch and unexpected SQL failures return safe unavailable behavior. VerificationReadiness maps failure to false.

## 3. Consumer integration results

ApplicationSubmissionService validates the selected SE/BNC profile before opening SubmissionTransaction and again after the writer reservation. It compares the second admitted profile to the first. Existing operational(), fourteen expectations, actor/session/tenant checks, lineage/custody/crypto/retention, proof consumption, audit/reconciliation, CAS, receipt/replay and explicit resolve behavior are preserved. Both submit and resolve use the same gate. The public submission controller remains separate and denied.

LifecycleMutation::versioned() returns true for exactly admitted SE/BNC and false only for exactly admitted foundation/C. run() captures the admitted profile, reserves a writer and revalidates before invoking the existing callback. Versioned operations reuse VerificationStore's context-table reservation. Foundation/C reserve with a zero-row merchants UPDATE inside their existing one-attempt DB transaction before rechecking. No row or profile version is modified by that reservation.

Indirect RegisterMerchantOwner, VerifyAccountEmail, User notification selection, RevokeMembership and PasswordResetController inherit this selection through their existing APIs. BNC tests prove unnamed registration and its original graph, versioned email notification/signed verification, dashboard access, authority increment/revocation and reset hold. Invalid schema blocks registration/notification before account writes. Existing local/testing/mail/session controls remain in place.

VerificationReadiness adds the schema gate and then executes all existing environment authorization, mode, distinct-key, namespace/time, frozen-C and ledger checks. A complete admitted profile supplies no collection/delivery authority. A default unprovisioned environment still reports false. Existing MerchantVerification and public submission checks already call readiness before and inside their writer operations, so they inherit the stricter gate. Dashboard reading retains its existing verified-member policy.

Tests inject a schema or selection change at the reserved-writer boundary. Lifecycle callback and internal submission are denied before workflow mutation. The schema-change fixture is rolled back and becomes ready again. No readiness check is inserted into the intentional BNC deferred record/witness gap because this task adds no completion command.

## 4. Release manifest and hashes

The independent manifest is `backend/database/activation/bnc1/integration-manifest.json`, version `BNC-INT-01`, mode `isolated-only`. It has the exact supported profile list, existing BNC manifest SHA-256 and four changed runtime source pins. Canonical JSON comparison rejects duplicate/extra keys or altered content. Runtime admission compares current source bytes to that reviewed manifest and verifies the fixed BNC manifest compatibility digest, then checks existing C/SE/BNC source pins through unchanged composed source assertions.

releaseDefinition() is used explicitly during source preparation. Admission never regenerates a manifest to repair a mismatch. The tampering test restores only its own new integration manifest after proving denial. Independent release approval must pin the manifest digest externally. Source consistency supplies no operational release authority.

Fresh filesystem SHA-256 comparisons before testing and at final audit matched the manifest pins:

| Runtime source (relative to backend) | SHA-256 |
|---|---|
| app/Actions/Merchants/WorkflowSchemaReadiness.php | `b5a1eb748d7512a2537d6200a786fbeab1000d4ff4df0bf645990958951af7f7` |
| app/Actions/Merchants/ApplicationSubmissionService.php | `b37821531cd0447bece877daca388b4e076d6bbb3e08f62456108211735ffe8b` |
| app/Actions/Merchants/LifecycleMutation.php | `59354766808fc89b841bafb19d1a7b2249b6c810ea0adf928e419a1dfce3038e` |
| app/Actions/Merchants/VerificationReadiness.php | `66730dad1bf9c09b73169f13c496d6c76c0fb82e4758c1b1df4f52d75b7728f5` |

Integration manifest SHA-256 is `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a`. Original BNC manifest SHA-256 is `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3`. Neither manifest was regenerated. All six frozen C/SE pins, three existing BNC source/artifact pins and nine foundation migration pins matched before execution and at final audit. The combined tests also exercise unchanged SQL definitions and the fourteen-object BNC catalogue.

## 5. Focused tests and assertions

The intended runtime was located at `C:/Users/hp/.config/herd/bin/php85/php.exe` after a read-only installation-directory search. It was outside sandbox execution access, so approved sandbox escalation was used. No operational environment file was read. `-v` returned PHP **8.5.12**, NTS Visual C++ 2022 x64, Zend Engine/OPcache 8.5.12, exit 0. PHPUnit `--version` returned **12.5.38**, exit 0. Installed metadata identifies Laravel **13.35.0** and Pint **1.32.1**. PHP reports SQLite **3.53.4**. No runtime substitution or dependency change occurred.

All twelve task PHP files passed syntax checks, each exit 0. The ten modified files are the PHP entries in section 1 excluding the new helper/test; the two new files are WorkflowSchemaReadiness.php and WorkflowSchemaReadinessTest.php. The exact PowerShell syntax command, run from the repository root, was:

```powershell
$taskFiles=@(git diff --name-only -- '*.php')
$taskFiles+=@('XLipa/backend/app/Actions/Merchants/WorkflowSchemaReadiness.php','XLipa/backend/tests/Feature/WorkflowSchemaReadinessTest.php')
foreach($p in $taskFiles){
    & 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' -l $p
    if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}
}
```

The fresh focused command ran from backend:

```text
& 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'WorkflowSchemaReadinessTest|test_bnc_int01'
```

**Fresh result: 58 passed / 398 assertions / 140,772 ms, exit 0. Zero failures/errors reported.** Captured runner output:

```json
{"tool":"phpunit","result":"passed","tests":58,"passed":58,"assertions":398,"duration_ms":140772}
```

This comprises 53 new selector/consumer cases and five appended BNC submission cases. Coverage includes all four profiles, twelve mismatches, every individual BNC object residue, partial SE columns/table/ledger, unknown/temp/attached/statistics objects, exact ledger, absent/invalid selection, in-writer rechecks, BNC auth/dashboard/revocation/reset/Chief reading, manifest tampering, normal internal submission/replay/resolve, generated contention and before/after COMMIT acknowledgement loss. The interrupted draft's earlier 10,882 ms run is historical narrative, not the current result.

The new BNC submission cases reuse existing successful fictional test paths with the complete BNC schema. This preserves their existing encrypted-lineage, replay, authority, restore-denial, one-commit-attempt and fresh-resolution assertions. No business-name completion record or operation is manufactured by readiness tests.

## 6. Bounded regression results

**Fresh final result: 530 passed / 4,385 assertions / 304,101 ms, exit 0. Zero failures/errors reported.** Exact command from backend:

```text
& 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'WorkflowSchemaReadinessTest|BusinessNameCompletionSchemaTest|AdminApplicationReviewTest|ApplicationSubmissionServiceTest|SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest|SubmissionLifecycleTest|SubmissionEvidenceCipherTest'
```

Captured runner output:

```json
{"tool":"phpunit","result":"passed","tests":530,"passed":530,"assertions":4385,"duration_ms":304101}
```

Both fresh invocations completed successfully on the source hashes in section 4. Output was delayed until completion; process activity was observed while waiting. No test failure, implementation repair, fixture edit, readiness relaxation or manifest update was necessary in this continuation.

This selection covers the new integration, prior 72-case BNC constraint suite and existing authentication, minimal registration, merchant access/dashboard, verification, submission/crypto/lifecycle, frozen schema and PD-05A read-only regressions. The final combined result is authoritative. Counts from overlapping focused/intermediate runs are not added to it.

Historical interrupted-stage narrative, retained with qualification: the draft recorded a first combined run of 530 cases with 524 passes, two failures and four errors, followed by six corrected cases passing with 47 assertions in 2,132 ms, exit 0. Independent console artifacts for those historical runs were not recovered. The existing diff shows that five audit-outage fixtures now use connection beforeExecuting callbacks instead of unapproved triggers, with switchable recovery flags where required, and one generated-file reconnect now enables recursive triggers. Their rollback, durable failure accounting, pending audit, conflicting-audit and contention assertions remain. The fresh complete passing run above supplies current acceptance evidence. No unknown-object allowance was added to application admission.

Two older suites previously ran all discoverable migrations, including retired synthetic migrations. AuthenticationTest and MerchantAccessTest now use the established exact foundation fixture. The helper still requires successful migration completion through its existing checked setup. Other test bodies and expected product behavior are retained. This changes assertion totals relative to old reports, so no assertion delta is inferred.

All tests use tests/bootstrap-isolated.php and Tests/TestCase with an absent test environment filename, memory SQLite, public fictional keys, array mail/session/cache, null logging/queue and blocked stray HTTP requests. Existing contention/unknown-outcome tests export only their generated fixture to owned temporary SQLite files and clean up their own files/sidecars. No working database copy is used.

Pint was restricted to the explicit twelve-file list below. First, the required `--dirty --format agent` invocation passed, exit 0. The second invocation omitted the dirty filter to ensure both untracked PHP files were included; it also passed, exit 0. Both returned `{"tool":"pint","result":"passed"}`. Exact commands from backend:

```powershell
$taskFiles=@(
    'app/Actions/Merchants/ApplicationSubmissionService.php',
    'app/Actions/Merchants/LifecycleMutation.php',
    'app/Actions/Merchants/VerificationReadiness.php',
    'app/Actions/Merchants/WorkflowSchemaReadiness.php',
    'tests/Feature/ApplicationSubmissionServiceTest.php',
    'tests/Feature/AuthenticationTest.php',
    'tests/Feature/MerchantAccessTest.php',
    'tests/Feature/MerchantApplicationSubmissionTest.php',
    'tests/Feature/MerchantVerificationTest.php',
    'tests/Feature/WorkflowSchemaReadinessTest.php',
    'tests/Support/B32CRehearsal.php',
    'tests/TestCase.php'
)
& 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' vendor/bin/pint --dirty --format agent @taskFiles
& 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' vendor/bin/pint --format agent @taskFiles
```

SHA-256 inventory comparison after formatting found no executable-source or other inventoried byte change. There were therefore no formatter changes to review or affected tests to rerun. No unrelated path was passed to Pint.

## 7. Security holds preserved

- Frozen C/SE source, SQL and manifests and all existing BNC schema sources/artifact/manifest retain their exact hashes. Unchanged SE ready() still rejects the composed BNC catalogue.
- Registration implementation, onboarding controller/view, routes and Chief Admin review source are byte-preserved. Dashboard access remains verified-email/session/member based without phone possession or KYC prerequisite.
- Password reset and frozen account/password/auth mutation holds remain enforced for admitted versioned profiles. BNC schema capability supplies no password-reset authorization.
- Default VerificationEnvironment and DeniedSubmissionAdmission remain unprovisioned/denied. No operational adapter/provider or expected deployment profile was installed.
- Public successful submission, Chief decision writes, working C/SE activation, real OTP/NIN collection/delivery and live payments remain disabled.
- The task adds no completion command, form, resolver route, business-name change, new schema object, migration ledger row or activation path to a working store.

## 8. Unresolved operational dependencies

Runtime expected-profile provisioning remains **unimplemented**. Trusted server profile selection must be provided separately before ordinary runtime mutations can pass. Its absence produces unavailable responses by design, including affected registration and email-verification lifecycle mutations. Successful fixture behavior does not establish ordinary runtime availability. Tests inject selection only through fixture code. The fixture selector follows explicit selected artifact paths, never observed database state. A future server selection must retain authoritative profile identity across restore/cutover and supply a reviewed source/release binding.

Foundation/C/SE integration currently accepts the exact isolated ledger prefixes described above. Existing BNC's ID/batch contract and memory-only installer remain intact. Working ledger/schema compatibility is unknown and was never inspected. Independent target-bound preflight/migration/recovery design and owner authorization are still required.

The new manifest establishes consistency of reviewed sources. External release authenticity, custody, trusted time, lawful policy/retention, issuer/admission, authoritative restore/cutover/replay fencing and production storage fault evidence remain independent dependencies. No operational approval is encoded in the manifest.

VerificationStore and existing lifecycle transaction semantics are preserved. Their rollback-uncertainty classification and external postcommit delivery guarantees are outside this readiness stage. SubmissionTransaction's inherited one-attempt/purge/unknown behavior is exercised for internal submission under BNC. Real power loss, multi-process storage recovery, rollback failure and migration COMMIT ambiguity require separate evidence.

The separately designed completion admission, request normalization/error boundary, form and explicit resolver remain future work. No completion-specific trusted policy/time/restore adapter was implemented here.

## 9. Preservation evidence

A fresh pre-execution SHA-256 inventory for this continuation covered **301 existing tracked/untracked files** selected from Git's tracked and untracked nonignored XLipa paths. It excluded environment files, secret/credential/private-key paths, dependencies, storage, database/sidecar patterns and PHPUnit result cache. The baseline JSON is retained in the session temporary directory as `xlipa-bnc-int01-final-baseline.json`; it is a current verification inventory, not a reconstruction of pre-implementation history. Before report editing, including after formatting, all 301 files were unchanged with zero missing. Final comparison after this report update found **300 unchanged, one changed (this report), zero missing**. No PHP or manifest bytes changed in this continuation. `git diff --check` and this report's trailing-whitespace check passed.

The interrupted draft's referenced 274-file inventory and the earlier security review's 273-file inventory were not independently recovered. Their historical preservation totals cannot be certified by this continuation. The current inventory proves preservation only from the start of this final verification. For tracked registration, onboarding controller/views/routes and Chief read-only sources, Git also shows no changes against HEAD. Existing untracked BNC schema sources/artifact/manifest are bound by their freshly verified pins; no universal pre-interruption byte-preservation claim is made for other untracked files.

The original BNC manifest remains `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3`. All nine existing C/SE/BNC source pins and nine foundation migration pins match. The SQL object hashes, migration identities and BNC ledger contract remain unchanged; the included 72-case BNC schema suite and strict profile tests passed. Registration implementation, onboarding controller/views/routes and Chief sources retain their current-inventory bytes. Chief routes remain read-only, public submission retains its explicit unavailable branch and the provider retains DeniedSubmissionAdmission. No payment activation change was made.

No working SQLite/sidecar was opened, read, copied, hashed, migrated or modified. No real environment file, secret, operational credential or key was accessed. No working migration, real delivery/identity/payment operation, commit, push or deployment occurred.

These statements describe this verification pass and its inspected isolated execution boundaries. Tests ran migrations only inside their guarded disposable fixtures. Working database state and historical migration activity before recovery were not inspected or certified; operational ledger compatibility remains **unverified**. The recovery report's historical evidence limits remain valid.

## 10. Next owner review and roadmap

Review the exact four runtime source hashes, independent manifest, trusted expected-selection contract, stricter supported ledger prefixes and ten existing-file changes. The fixture migrations/outage changes preserve meaningful regression coverage under strict catalogue admission. Later integration remains subject to a separate bounded diff and evidence.

| Milestone | Current state |
|---|---|
| Minimal Registration / PD-05A | Completed implementation preserved, regression evidence refreshed |
| BNC exact SQL/readiness artifacts | Preserved with original SQL/source/ledger pins |
| BNC-INT-01 | Trusted strict selector, three consumers, independent source manifest and fictional regressions implemented for owner review |
| Completion command/form/resolver | Separate future authorization and admission/HTTP/UI evidence |
| Working preflight/migration/release | Compatibility unknown, no activation authorized |
| DR-1 / PD-05B / PD-06 | Separate scope |
| Operational readiness | NO-GO, independent trust and recovery dependencies unresolved |

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public submission: DISABLED. Chief decision writes: DISABLED. Live payments: DISABLED.**
