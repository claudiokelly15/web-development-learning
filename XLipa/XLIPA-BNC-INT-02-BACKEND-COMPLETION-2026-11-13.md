# XLipa — BNC-INT-02 Backend Completion Command and Resolver

13 November 2026 · Africa/Nairobi · Isolated backend implementation and fictional evidence

## 1. Baseline and exact changed files

Continued the verified BNC-INT-01 work at HEAD `fc1d7e1877674f65a9e70fd0e7ebedd5c4c68054`. Initial index was empty; ten tracked PHP modifications and thirteen untracked files were preserved. Those changes belong to prior BNC work, including its recovery report. Read backend AGENTS.md, the business-name security contract, amendment design, exact-SQL verification, integration security review, rate-limit recovery and completed BNC-INT-01 report. Workspace/XLipa root AGENTS.md and workspace/XLipa/backend `.ai/rules` were absent. No testing-best-practices skill is available in the session catalog.

Fresh checks matched all six frozen C/SE pins, three BNC source/artifact pins, four BNC-INT-01 runtime pins, original BNC and integration manifest digests, and nine foundation migration pins. A fresh 301-file inventory was captured before editing. No existing unfinished work was discarded or regenerated.

Exactly these files are changed/created by this stage, relative to XLipa:

| File | Change |
|---|---|
| backend/app/Actions/Merchants/BusinessNameCompletionAdmission.php | New independent trust interface |
| backend/app/Actions/Merchants/DeniedBusinessNameCompletionAdmission.php | New denied implementation |
| backend/app/Actions/Merchants/BusinessNameCompletionContext.php | New shared strict validation, source admission, fresh authority and graph checks |
| backend/app/Actions/Merchants/BusinessNameCompletionService.php | New internal command |
| backend/app/Actions/Merchants/BusinessNameCompletionResolver.php | New explicit internal fresh-connection resolver |
| backend/app/Actions/Merchants/BusinessNameCompletionOutcomeUnknown.php | New generic uncertainty exception |
| backend/app/Providers/AppServiceProvider.php | Add only the denied admission binding and its two imports |
| backend/database/activation/bnc1/backend-manifest.json | New separate seven-source backend manifest, compatible with unchanged BNC-INT-01 manifest |
| backend/tests/Feature/BusinessNameCompletionTest.php | New command/resolver acceptance suite |
| backend/tests/Support/FakeBusinessNameCompletionAdmission.php | New fictional mutable trust boundary, registered only by tests |
| XLIPA-BNC-INT-02-BACKEND-COMPLETION-2026-11-13.md | This report |

This is one existing-file change plus ten new files. The six runtime files and feature test were initially scaffolded with Laravel generators through the isolated bootstrap. The user's explicit internal-service authorization supersedes the earlier documents' proposed controller integration; no controller, request, route or UI change is included.

## 2. Denied-by-default admission

The interface has separate requirements for `expectedProfile()`, `authorized(actor, merchant, expectations, resolving)`, `milliseconds()`, `recordPolicy()`, `retentionAllowed(policy, occurredAt)`, `authoritative(connection, actor, merchant, expectations, resolving)` and `reconciliationReady(actor, merchant)`.

An operational implementation would have to independently authorize the exact current owner and explicit operation/resolution context, supply trusted time and approved policy/retention, bind the actual connection to authoritative store/restore/cutover/replay fencing, and establish relevant external reconciliation holds. Request assertions, local rows, schema validity and system time do not implement that trust. The interface is a requirement on an independently reviewed future adapter, not evidence that such infrastructure exists.

The provider binds the interface to DeniedBusinessNameCompletionAdmission. It returns no profile, denies authorization/fencing/retention/reconciliation and throws for time/policy. There is no default clock, environment fallback or database-derived trust. Only the fictional test adapter permits operations. Neither phone OTP, NIN, full KYC nor a platform role is a completion prerequisite or source of completion authority.

Independent authorization and expected BNC selection are required before completion database queries or resolver reconnection. Full admission before and under the writer, and again after the complete bundle, additionally requires exact WorkflowSchemaReadiness BNC admission, unchanged source manifests, trusted time bounds and nonregression, approved policy syntax and retention. Existing merchant-scoped pending-audit/future-observed-time rows cause a hold; they never advance the trusted clock or discharge obligations. External holds remain an independent admission requirement.

## 3. Internal input and command behavior

`BusinessNameCompletionService::complete(User $actor, string $merchant, array $expected, mixed $name)` accepts exactly these expectation keys:

```text
application_id, attempt_number, merchant_profile_version, draft_profile_version,
membership_id, authority_version, auth_version, email_binding_version,
completion_request_id
```

The authenticated actor is not a client field. Tenant is the explicit internal service scope and is freshly checked against ownership and draft identity. Parent-compatible ULIDs and canonical lowercase UUIDs are required. Versions, attempts and membership IDs must be positive PHP integers; strings, booleans, floats and unknown keys fail. No HTML decimal parser is introduced in this backend-only stage. Any future HTTP parser requires separate review.

The name must be a UTF-8 string. Reject Unicode control/format characters and line/paragraph separators before trimming, including boundary tab/newline/NUL. Trim only ASCII space U+0020; reject empty results and remaining Unicode separator characters at either boundary. Interior ordinary Unicode spaces are preserved. Limit to 200 Unicode characters. Digit strings, punctuation and valid case/Unicode bytes are preserved without normalization or company-status inference. This deliberately rejects invisible format characters rather than silently deleting them and leaves signup behavior unchanged.

Under the single reserved writer, reload the active verified user and authenticated identity; require current email, auth epoch and email-binding epoch agreement with both actor and authenticated model, plus exact session auth_version. Require the expected current epochs, exact active unrevoked owner membership/tenant/authority epoch and a merchant outside suspended/closed. Platform authority supplies no override.

Require both profile expectations equal the authoritative merchant V, exact same-tenant current draft ID/attempt/slot at V, all submission/review projections NULL and no submission evidence or submitted audit. Missing or inconsistent state is denied, never repaired. Resolve an existing scoped UUID before no-op classification: the command always conflicts on a recorded UUID and requires a separate explicit resolver invocation. It never automatically replays or converts a stale command to success.

A fresh matching-version identical canonical name returns `status=unchanged` only after acknowledged transaction completion and final graph/admission checks. It makes no row changes, completion record, witness, timestamp/version update or notification. The transaction still executes its inherited zero-row writer-reservation statement. A genuine no-op works at PHP_INT_MAX without arithmetic. Stale identical-name requests conflict. A different nonempty stored name conflicts; only exact stored `''` can become nonempty.

For an actual change, overflow is checked before V+1. Generate the server ULID and redacted record with trusted time and policy. Insert that record first. CAS only the merchant's name, version V→V+1 and canonical updated_at, constrained by original empty name/version and tenant, requiring one affected row. The unchanged BNC AFTER trigger synchronizes the exact current draft and inserts the witness. Application code does not issue a second draft update or witness insertion.

The complete tuple, exact stored record, witness, current name/version, both timestamps, draft/authority/admission and retention are rechecked before one COMMIT attempt. Full readiness is never invoked in the intentional deferred record/witness gap. Acknowledged success returns only status, completion ID and result version. No name bytes/digest, request body or arbitrary metadata enter records, receipts or errors. No audit_logs event is fabricated. Unexpected errors become generic unavailable responses with no chained SQL exception. SensitiveParameter marks public payload/expectation boundaries.

## 4. Separate explicit resolver

`BusinessNameCompletionResolver::resolve(...)` accepts the same original expectations and caller-retained desired name. It requires independent explicit resolution authorization. It refuses a nested/active transaction, purges the prior managed connection and reconnects. It initializes recursive triggers on that new SQLite connection, then requires full source/schema/enforcement and independent store fencing; it performs no schema repair or migration. A fresh memory connection cannot preserve an old memory store, so positive resolver tests use newly generated, owned temporary fixture files.

Resolution uses one SubmissionTransaction writer reservation, without calling the command. It checks current verified owner/session authority independently of immutable historical epochs. The exact record must still match the original tenant/actor/request UUID/application/attempt/membership/auth/email/authority/prior-profile tuple. A changed tuple conflicts. Current ownership remains mandatory even when a historical record exists.

| Result | Required meaning |
|---|---|
| `confirmed` | Exact record and witness; original unchanged result V+1 and desired current name; exact current draft and recorded timestamps; trusted time, historical retention, current authority and authoritative-store admission. Returns the existing completion ID, never a new receipt/record. |
| `absent` | No scoped record or conflicting merchant completion history; exact original empty merchant/current draft at V; all independent current-store/restore fences admitted. It is authoritative absence under that boundary, not automatic replay or a claim based on local absence alone. |
| `historical` | Matching immutable event exists, but current payload/result conditions do not support confirmation. Reports only that event ID; no saved-name/current-success claim. |
| `held` | No matching record with advanced/nonempty/conflicting state, or an uncertain no-op without durable history. No success/rollback claim and no mutation. |
| 403/409/503 or explicit unknown | Current authority, tuple, workflow, admission, policy or transaction cannot support a result. Missing restore trust returns unavailable, never absent. |

Before committing resolution, recheck admission, authority, unchanged merchant state and the draft for confirmed/absent classifications. No automatic command invocation, UUID substitution, history reconstruction or name commitment is introduced. The caller must preserve the original UUID, expectations and desired bytes in bounded private context; this internal stage does not implement that HTTP/session flow. The redacted record has no name commitment, so confirmation is deliberately restricted to unchanged result state. Historical payload proof after later changes remains unsupported.

## 5. Transaction and recovery evidence

SubmissionTransaction.php and SubmissionOutcomeUnknown.php are unchanged. Both command and resolver reuse the existing managed writer reservation, one callback invocation, one COMMIT attempt, no automatic callback replay, and connection purge on uncertain acknowledgement/rollback failure. The new boundary maps inherited uncertainty to BusinessNameCompletionOutcomeUnknown without attaching private exception details. Successful rollback after a precommit error remains an ordinary denied operation; COMMIT uncertainty stays unknown even if cleanup rollback succeeds.

Generated-file tests inject loss of acknowledgement before and after real COMMIT. Each records one commit attempt, checks a different managed connection after purge, denies resolution while the fictional restore fence is false, and then explicitly resolves on another new connection. Depending on independently supported fixture state the result is absent or confirmed; the resolver adds no record or second version change.

A PDO rollback-failure fixture throws before acknowledging rollback after one record-first command attempt. The service raises unknown and purges without replay. After disposal of the owned fixture connection, independently admitted explicit resolution observes absence. This is bounded driver fault evidence, not a promise about storage recovery or rollback durability in production.

Additional tests cover busy independent writers with zero timeout, one eventual winner and stale loser; exceptions after record insertion and after trigger-completed merchant update; final-admission withdrawal rolling back the whole bundle; revocation at writer acquisition; trusted-time regression; no-op final-state change; and lost no-op acknowledgement. The latter creates no new record and resolves held, preventing an invented historical no-op receipt. Existing BNC schema tests retain deferred-FK, partial-record/witness, trigger-boundary, immutability and conflict-policy integrity coverage.

## 6. Execution results and commands

Verified PHP 8.5.12 CLI NTS x64 at `C:/Users/hp/.config/herd/bin/php85/php.exe`; the existing runtime required approved sandbox execution escalation. Installed versions are Laravel 13.35.0, PHPUnit 12.5.38 and Pint 1.32.1; SQLite is 3.53.4 as previously verified in this session. No alternative runtime or dependency was installed.

All application boot/generator/test commands used tests/bootstrap-isolated.php, absent test environment/cache paths, public fictional keys, testing mode, memory SQLite without connection URL, array mail/session/cache, null logging/queue and blocked stray HTTP. Generated-file tests export only their own fictional memory fixtures and clean up only their owned files/sidecars. No working-store copy or real provider/notification delivery is used.

All nine task PHP files passed PHP `-l`, each exit 0. Pint was restricted to those same nine paths. The required dirty invocation and explicit-path invocation both passed finally, exit 0. Initial formatter changes were confined to spacing/brace style in the new Context and test; they were inspected and the tests run afterward. No unrelated file was formatted.

Final focused command, from backend:

```powershell
& 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/BusinessNameCompletionTest.php
```

**70 passed / 493 assertions / 71,345 ms, exit 0.** Captured result:

```json
{"tool":"phpunit","result":"passed","tests":70,"passed":70,"assertions":493,"duration_ms":71345}
```

Final combined command:

```powershell
& 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'BusinessNameCompletionTest|WorkflowSchemaReadinessTest|BusinessNameCompletionSchemaTest|AdminApplicationReviewTest|ApplicationSubmissionServiceTest|SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest|SubmissionLifecycleTest|SubmissionEvidenceCipherTest'
```

**Final combined result: 600 passed / 4,878 assertions / 266,696 ms, exit 0. Zero failures/errors reported.** Captured result:

```json
{"tool":"phpunit","result":"passed","tests":600,"passed":600,"assertions":4878,"duration_ms":266696}
```

This actual combined run is authoritative for the final source set, including all 70 new cases and the established 530-case selection. Counts from overlapping focused/intermediate runs are not summed. Only this report was edited after final test execution; no executable source or manifest changed.

An initial single normal case passed (one test/17 assertions). The first full new run had 62 cases, 61 passes, 425 assertions and one fixture setup error: deleting a draft violated the frozen SE deletion guard. Only that new fixture was corrected to request a nonexistent draft. The subsequent expanded run passed 67/469, then final edge additions passed 70/493 above. These intermediate results are not final combined evidence. No frozen trigger or admission requirement was weakened.

For lint/format, `$files` was the nine PHP paths in section 1 (six runtime classes, provider, feature test and fictional adapter), relative to backend:

```powershell
foreach($file in $files){ & 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' -l $file }
& 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' vendor/bin/pint --dirty --format agent @files
& 'C:/Users/hp/.config/herd/bin/php85/php.exe' -d 'auto_prepend_file=' vendor/bin/pint --format agent @files
```

The separate backend manifest was intentionally prepared from BusinessNameCompletionContext::releaseDefinition() after source formatting, using the isolated bootstrap without database access. Runtime only compares it and never writes/repairs it. It was refreshed during source preparation for final-check additions, without touching either existing manifest.

## 7. Source and schema preservation

The new backend manifest pins seven runtime/provider sources and the unchanged BNC-INT-01 manifest digest. External release approval must pin its digest independently; file consistency does not establish authenticity or deployment authority.

| Source relative to backend | SHA-256 |
|---|---|
| app/Actions/Merchants/BusinessNameCompletionAdmission.php | `e07544e316988a448175c0c5bf13f80f8fb643d12f54c014448f0109838785ad` |
| app/Actions/Merchants/DeniedBusinessNameCompletionAdmission.php | `0855fa85429c26f886fdecc3a00be765d167dae8ea19f098dc0cbd7ea1345651` |
| app/Actions/Merchants/BusinessNameCompletionContext.php | `e82d1bff179f3f9bfd018d52d8cf732d50b60b190daca719da1e73d191923328` |
| app/Actions/Merchants/BusinessNameCompletionService.php | `86b14197565b47be6642798a12a7aaf9c01508fc6eed09ee1bfa6d3243aa6e79` |
| app/Actions/Merchants/BusinessNameCompletionResolver.php | `a644c2f731ba378fb173e4cd9ee5ad42844a2ab2ff8d1d707a6fef001a8b5eb7` |
| app/Actions/Merchants/BusinessNameCompletionOutcomeUnknown.php | `35c2d3fa3d163132923e4343c0c0f4a521cd60f8f89783e761c4801fbd16f96c` |
| app/Providers/AppServiceProvider.php | `ffe3809476e5c672aa040df8f1ac1ef890368d91b8fd0f2eb5d382d9d0fc9fc7` |
| tests/Feature/BusinessNameCompletionTest.php | `e338275922c4c815b0b80f77855dc9c9275cee2f62492afc301618a9db5c625a` |
| tests/Support/FakeBusinessNameCompletionAdmission.php | `b8d083b6dc24997dd97149617b8c91b880f45c9c3cb9de92d669dd875da3c110` |

New backend manifest digest: `423d7ec0c2242aa42eb6e33c02036096c12fc3ff5a3160bf828c612904395990`. Existing integration manifest digest remains `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a`; original BNC manifest remains `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3`.

The fresh baseline inventory is retained in the session temporary directory as `xlipa-bnc-int02-baseline.json`. It covers Git-selected tracked/untracked nonignored source/asset/test/config/report paths, excluding dependencies, storage, environment/secrets/credentials, database/sidecar patterns and PHPUnit cache. Comparisons show 300 baseline files unchanged, one changed (the reviewed provider binding), zero missing. All previous BNC reports, all BNC-INT-01 edits, existing tests and all frozen/BNC schema sources/artifacts/manifests retain their inventoried bytes. This is preservation from the start of BNC-INT-02, not a universal reconstruction of earlier history. Earlier unavailable 273/274-file inventories remain historical limitations.

Final source-pin comparisons matched all thirteen existing C/SE/BNC/BNC-INT-01 pins, seven new backend pins and nine foundation migration pins. Git index remains empty; Git status retains the ten earlier tracked modifications and thirteen earlier untracked files, adding only this stage's provider change and ten new files. `git diff --check` and explicit new-file trailing-whitespace checks passed.

No SQL object, migration identity, ledger contract or existing manifest was changed. The BNC catalogue remains fourteen additive objects. Existing registration implementation, onboarding controller/views/routes, dashboard and Chief read-only sources retain their baseline bytes. Only the provider binding affects shared application setup. Public submission retains its unconditional denial and DeniedSubmissionAdmission binding; Chief review routes remain read-only; no payment/provider activation is added.

No working database or sidecar was opened, queried, copied, hashed, migrated or modified. No real dotenv, operational credential or key was accessed. Migrations ran only inside guarded disposable tests. No commit, push or deployment occurred. Historical working migration activity and operational ledger state were not inspected or certified.

## 8. Unresolved dependencies and owner review

Runtime expected-profile provisioning remains unimplemented. Ordinary affected registration/email lifecycle mutations remain unavailable without separately approved selection; fixture success establishes no ordinary deployment availability. Operational ledger/schema compatibility remains unverified and fixture-specific exact ledger prefixes remain unchanged. Neither new service is exposed publicly.

A future operational adapter needs independent owner/scope authorization, original-context custody, trusted timestamp provenance, policy/retention/access/hold authority, authoritative connection/store lineage, restore/cutover/replay fencing and reconciliation evidence. No implementation is supplied here. External source/release authenticity, real storage durability/power-loss behavior, multi-process recovery and operational fault evidence remain unresolved. Fictional PDO/COMMIT/rollback tests do not certify production recovery.

Owner review must cover the exact source hashes and denied binding, strict internal input convention, canonical timestamp/trigger ordering, current-versus-historical authority checks, conservative resolver meanings, separate manifest and test evidence. HTTP normalization/error/CSRF/throttling/private-context handling and onboarding UI remain separately authorized future stages. A stronger historical payload receipt would require a separately approved protected binding; none was invented here. DR-1, PD-05B/PD-06, operational migration/release, real identity/OTP providers, Selcom and live payments remain separate scope.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public submission: DISABLED. Chief decision writes: DISABLED. Live payments: DISABLED.**
