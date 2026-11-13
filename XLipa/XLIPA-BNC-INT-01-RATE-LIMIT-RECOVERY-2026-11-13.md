# XLipa — BNC-INT-01 Rate-Limit Recovery

13 November 2026 · Africa/Nairobi · Read-only source recovery; report-only change

## 1. Recovery conclusion and instructions

The interruption occurred **after substantive implementation and intermediate test work, but before verified completion of the stage**. All six requested components exist. The existing stage report remains a draft with `RELEASE_HASHES`, `FINAL_REGRESSION`, `FORMAT_RESULT` and `PRESERVATION_RESULT` placeholders. Its completion language is not sufficient evidence of a successful final run. Do not restart or automatically repair the implementation.

Read backend AGENTS.md in full. Workspace/XLipa root AGENTS.md and workspace/XLipa/backend `.ai/rules` were absent; the file search found no additional nested AGENTS.md. No testing-best-practices skill is available in the supplied catalog. No application source, tests, manifests or previous reports were edited during recovery.

## 2. Current Git status and attribution

HEAD remains `fc1d7e1877674f65a9e70fd0e7ebedd5c4c68054`. Index/staged diff is empty. At recovery entry there were ten modified tracked files and twelve untracked files. Tracked diff totals: 122 insertions, 19 deletions. `git diff --check` passed. Paths below are relative to XLipa.

Modified tracked files attributable to BNC-INT-01, consistent with the stage draft and the prior review's empty tracked/staged baseline:

- `backend/app/Actions/Merchants/ApplicationSubmissionService.php`
- `backend/app/Actions/Merchants/LifecycleMutation.php`
- `backend/app/Actions/Merchants/VerificationReadiness.php`
- `backend/tests/Feature/ApplicationSubmissionServiceTest.php`
- `backend/tests/Feature/AuthenticationTest.php`
- `backend/tests/Feature/MerchantAccessTest.php`
- `backend/tests/Feature/MerchantApplicationSubmissionTest.php`
- `backend/tests/Feature/MerchantVerificationTest.php`
- `backend/tests/Support/B32CRehearsal.php`
- `backend/tests/TestCase.php`

New untracked BNC-INT-01 files:

- `backend/app/Actions/Merchants/WorkflowSchemaReadiness.php`
- `backend/database/activation/bnc1/integration-manifest.json`
- `backend/tests/Feature/WorkflowSchemaReadinessTest.php`
- `XLIPA-BNC-INT-01-STRICT-READINESS-INTEGRATION-2026-11-13.md`

Eight baseline untracked files, already described by the prior security review, must not be mistaken for new recovery/integration work:

- `XLIPA-BNC-1.0-EXACT-SQL-CONSTRAINT-VERIFICATION-2026-11-13.md`
- `XLIPA-BNC-1.0-INTEGRATION-SECURITY-REVIEW-2026-11-13.md`
- `XLIPA-BUSINESS-NAME-COMPLETION-AUDIT-AMENDMENT-DESIGN-2026-11-13.md`
- `backend/app/Actions/Merchants/BusinessNameCompletionSchema.php`
- `backend/app/Actions/Merchants/ComposedSubmissionSchema.php`
- `backend/database/activation/bnc1/2026_11_13_210000_add_bnc1_business_name_completion.php`
- `backend/database/activation/bnc1/manifest.json`
- `backend/tests/Feature/BusinessNameCompletionSchemaTest.php`

This recovery adds only this report, making thirteen untracked files. Git cannot independently date untracked changes; attribution relies on the prior baseline narrative and current diff, not a recovered pre-interruption inventory.

## 3. Implementation present

`WorkflowSchemaReadiness` accepts only trusted configuration selecting `foundation`, `b32c`, `se1` or `b32c+se1+bnc1`. Missing, malformed and unknown selections deny with generic 503. There is no schema-inference downgrade. It checks release consistency, SQLite constraint settings, temporary/attached schema, object catalogue, exact fixture ledger and foreign-key consistency. Foundation/C use frozen definitions; SE uses existing strict readiness plus catalogue/ledger checks; BNC delegates to existing composed readiness. Each call revalidates.

The integration manifest exists, declares isolated-only mode, lists four supported profiles and pins all four runtime sources. All four pins match current filesystem bytes. It binds the original BNC manifest digest. Runtime compares the manifest; it does not rewrite a mismatch. External release approval/authenticity remains a separate requirement.

ApplicationSubmissionService gates both submit/resolve through their shared execute path before and inside the reserved writer and preserves profile identity. LifecycleMutation replaces presence detection with exact admission, rechecks under the writer and executes legacy callbacks only for admitted foundation/C. VerificationReadiness adds the gate while retaining existing operational authorization/key/policy checks and maps exceptions to false.

The focused suite and five appended BNC submission cases are present, including data-driven mismatches/residue, invalid selection, ledger changes, manifest tampering, writer-boundary changes, registration/email/dashboard/revocation/reset/Chief reads and submission contention/unknown outcomes. Existing tests replace schema-changing audit outages with connection callbacks. Foundation fixtures and reconnect recursive-trigger enforcement were adjusted. No obvious unfinished method, missing same-namespace reference or missing import was found by source inspection. This is not a PHP parser or execution result.

## 4. Incomplete implementation and acceptance

No missing requested component was identified. Completion remains **unverified**, with these exact outstanding items:

1. Recover or rerun isolated final regression evidence against the present bytes; the draft's final result is absent.
2. Verify PHP syntax and references with the intended PHP 8.5 runtime. `php` was not discoverable on PATH during recovery; no lint was executed. Limited conventional PHP/Herd path checks found no executable.
3. Establish formatter status. The draft names Pint but has no result. Running its mutating formatter belongs to a later authorized continuation.
4. Recover the referenced 274-file before/after inventory or explicitly qualify its unavailable result. The inventory itself was not located in the inspected project paths or the narrowly filtered temporary-directory listing.
5. Complete the draft's release hashes and evidence placeholders only with actual results.

No code change is proposed automatically. Completion command/form/resolver, production selection and operational adapters remain outside this stage.

## 5. Security findings and behavior

- Partial SE/BNC objects and unknown ledger/object entries have no lower-profile fallback: catalogue/source/ledger validation throws, profile admission converts failure to 503, and lifecycle callbacks are withheld. Tests for these paths exist; current execution is unverified.
- Exact foundation/C/SE ledger prefixes remain fixture-specific (IDs 1–9/10/11); BNC retains its twelve-row ledger. This supplies no working-store compatibility evidence.
- **Material availability limitation:** no runtime `workflow_schema.expected_profile` provisioning was found in application/config sources. Only tests supply selection. Registration and email-notification/verification mutations that use LifecycleMutation will be unavailable without separately reviewed trusted selection. Thus existing behavior is protected from unsafe writes, but ordinary runtime availability has not been demonstrated. Do not add an inferred fallback to restore availability.
- Dashboard controller/routes/policies are outside the tracked diff. Verified-email/session/member access remains in source; readiness false affects verification availability. A BNC dashboard acceptance case exists, but no fresh UI/runtime verification was performed.
- Public submission remains an unconditional unavailable path in MerchantApplicationSubmission, including the explicit immutable-evidence hold. The internal service remains bound to DeniedSubmissionAdmission by default.
- Chief application routes remain GET-only; no decision-write route or source change was added. No payment activation change exists in the recovered diff; live payments remain held. These are source-level conclusions, not live service observations.
- The source manifest proves consistency, not external authenticity. Every profile depends on the frozen/BNC source checks. This is deliberately restrictive and requires owner review before deployment.

## 6. Test evidence and limits

The only recovered pre-interruption run evidence is narrative in the existing BNC-INT-01 report, not retained console output or a machine-readable run artifact:

| Recorded run | What can be stated |
|---|---|
| Focused `WorkflowSchemaReadinessTest\|test_bnc_int01` | Draft reports 58 passed, 398 assertions, 10,882 ms, exit 0; not independently authenticated during recovery |
| First combined regression | Draft reports 530 cases: 524 passes, two failures, four errors; this was not a passing final run |
| Six corrected cases | Draft reports six passes, 47 assertions, 2,132 ms, exit 0; final combined suite after those edits is unverified |
| Final combined regression | `FINAL_REGRESSION` placeholder; no result recovered |
| Pint | `FORMAT_RESULT` placeholder; completion unknown |
| Prior BNC baseline | 208 tests / 1,915 assertions remains historical evidence from the earlier review |

A PHPUnit result-cache pathname exists, but no cache was used as proof of execution. Application logs and shell history were not opened to avoid incidental operational/secret exposure. No tests, application boot, Artisan commands, formatter or migrations were run during recovery. Current checks executed were Git/source inspection, JSON reading and filesystem SHA-256 comparisons. Do not present any historical/narrative pass count as a fresh verified result.

The isolated bootstrap and TestCase enforce testing, memory SQLite, absent synthetic environment/cache paths, fictional key, array mail/session/cache and blocked stray HTTP. Some existing tests use their own generated temporary stores. A later rerun must preserve those boundaries and must never point at a working database.

## 7. Preservation status

Fresh comparisons matched all six frozen C/SE pins, all three baseline BNC source/artifact pins, all nine foundation migration hashes and all four integration source pins. Original BNC manifest SHA-256 remains `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3`. Existing schema artifacts and their fourteen-object catalogue were not edited. Routes, registration implementation, dashboard controller and Chief sources have no tracked diff.

The prior security review recorded a 273-file unchanged comparison; the stage draft refers to 274 inventoried files but leaves its result blank. Neither is a new recovery-wide byte comparison. Broad byte preservation of every untracked baseline file cannot be independently established without that inventory.

No working database/sidecar was opened, read, copied, hashed, queried or modified in recovery. No environment file, credential or key was read. No working migration command was run. The prior draft states that no working migration occurred; source isolation is consistent with that statement, but historical database state cannot be independently certified without accessing prohibited operational evidence. **Working migration history is therefore unverified, not proven clean by this recovery.** No commit, push or deployment occurred.

## 8. Exact recommended next actions

1. Owner reviews the recovered diff, fixture-specific ledger restriction, absent runtime selection and evidence gaps. Stop here pending that review.
2. After authorization, locate the intended PHP runtime without reading real environment files; syntax-check the twelve task PHP files (ten modified files plus the new helper and test).
3. Recheck all pins and isolate the environment, then run the focused filter recorded above and the combined filter recorded in section 6 of the existing stage draft. Capture exact command, runtime/version, exit code, counts and output against the current source hashes. Do not run working migrations or operational databases.
4. If authorized continuation finds failures or formatter changes, retain the existing work, report exact causes, make only bounded reviewed fixes and refresh the independent manifest intentionally when runtime source bytes change. Never auto-repair admission at runtime.
5. Fill the original draft's four placeholders from verified evidence, qualify missing historical inventories, recheck preservation and return for owner acceptance. Keep production selection/release/recovery design separate.

**STOP FOR OWNER REVIEW BEFORE CONTINUING IMPLEMENTATION. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public submission: DISABLED. Chief decision writes: DISABLED. Live payments: DISABLED.**
