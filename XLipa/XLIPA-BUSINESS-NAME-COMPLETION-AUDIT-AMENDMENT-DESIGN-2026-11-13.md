# XLipa — Business Name Completion Audit Amendment Design

13 November 2026 · Africa/Nairobi · Documentation-only proposal

## 1. Current verified baseline

Owner approval covers technical design preparation. Implementation, migration execution and activation require separate approval. This document continues the Business Name Completion Security Contract and PD-05A Final Decision Contract Specification.

Current Git HEAD is `fc1d7e1877674f65a9e70fd0e7ebedd5c4c68054`. Initial `git status --short` was empty. The older HEAD and uncommitted inventories quoted in the 12 November reports describe their historical execution state. The current repository contains that completed work. Backend `AGENTS.md` was read. Workspace and XLipa root AGENTS.md files and `.ai/rules` at workspace, XLipa and backend were absent. No applicable testing skill is available or needed for this documentation-only task.

Inspected the Minimal Registration Implementation report, Business Name Completion Security Contract, PD-05A final specification, merchant/application/audit foundation migrations, AuditEvent, frozen C JSON/artifact, SE source/manifest, readiness consumers, VerificationStore and SubmissionTransaction. Source inspection establishes the following.

- Optional signup absence persists as exactly `business_name=''`. Existing merchant/owner/current-draft/creation-audit workflow is retained.
- Merchant profile_version is positive. SE requires exactly one increment when any covered profile field changes and forbids a version-only increment. It freezes submitted application identity, profile and submission fields.
- Applications have same-tenant identity uniqueness, unique merchant/attempt and merchant/current_slot. Current slot is text `'1'`. Draft profile synchronization has no completion-audit guarantee today.
- The fixed audit event set contains no name-completion event. Existing audit metadata and submission/R06 contracts cannot represent this operation safely.
- SE checks exact foundation definitions, C definitions, object catalogue and migration ledger. Added objects cause unchanged `SubmissionEvidenceSchema::ready()` to deny.
- SubmissionTransaction reserves a writer before workflow reads, attempts one COMMIT and purges its connection after uncertain acknowledgement or rollback failure. VerificationStore suppresses rollback errors and supplies no equivalent explicit outcome classification.

The reported 400 tests / 3,503 assertions and responsive checks remain historical evidence. No runtime verification was performed for this proposal.

## 2. Proposed amendment version and exact object design

Proposed identifier **BNC-1.0**, record contract `business-name-completion-v1`, composed profile `b32c+se1+bnc1`. Proposed isolated artifact migration identity `2026_11_13_210000_add_bnc1_business_name_completion`. Mode is `isolated-only`. These names are design selections for owner review.

A record-first protocol requires a second, redacted immutable witness table. Its deferred circular reference prevents an orphan record from committing. It avoids a lifetime foreign key to a mutable merchant/draft version. Both tables are amendment-owned. The witness contains no command payload.

### Completion table

Proposed table `merchant_business_name_completions` has exactly these columns.

| Column | Proposed definition and meaning |
|---|---|
| id | Canonical server-generated uppercase ULID, PRIMARY KEY, NOT NULL |
| contract_version | TEXT NOT NULL, exact `business-name-completion-v1` |
| merchant_id | NOT NULL parent-compatible merchant ULID |
| application_id | NOT NULL parent-compatible application ULID |
| attempt_number | INTEGER NOT NULL, typeof integer, >=1 |
| actor_user_id | INTEGER NOT NULL, typeof integer, >=1 |
| membership_id | INTEGER NOT NULL, typeof integer, >=1 |
| auth_version | INTEGER NOT NULL, typeof integer, >=1 |
| email_binding_version | INTEGER NOT NULL, typeof integer, >=1 |
| authority_version | INTEGER NOT NULL, typeof integer, >=1 |
| expected_profile_version | INTEGER NOT NULL, typeof integer, 1 through 9223372036854775806 |
| resulting_profile_version | INTEGER NOT NULL, typeof integer, exactly expected_profile_version+1 |
| completion_request_id | TEXT NOT NULL, canonical lowercase UUID with 8-4-4-4-12 hexadecimal groups, 36 bytes |
| occurred_at_ms | INTEGER NOT NULL, typeof integer, 0 through 253402300799999 |
| occurred_at | TEXT NOT NULL, exactly UTC `YYYY-MM-DD HH:MM:SS.mmm` derived from occurred_at_ms using SE's timestamp expression |
| record_policy_version | TEXT NOT NULL, 1–50 ASCII bytes from `[a-z0-9._-]`, approved retention/access/hold policy reference |

No nullable fields, arbitrary metadata, name text, name digest, phone, email, NIN, request body, credential or key reference. Identifiers and authority epochs remain access-controlled pseudonymous data. Timestamp syntax does not establish trusted time.

Named unique indexes are `bnc1_request_tuple` on `(merchant_id,actor_user_id,completion_request_id)` and `bnc1_transition_tuple` on `(merchant_id,expected_profile_version)`. The latter allows later independently valid completion after a separately governed future transition to empty. It imposes no lifetime one-completion-per-merchant rule. Identity/epoch validation follows the existing SE ULID/integer conventions. All text checks reject embedded NUL and invalid length/type.

### Witness table and stable foreign keys

`merchant_business_name_completion_witnesses` has exactly one column `completion_id`, canonical ULID NOT NULL PRIMARY KEY. Both tables use explicit named indexes for additional uniqueness. SQLite-created primary-key autoindexes are catalogued through table/index introspection.

All FKs use ON DELETE RESTRICT and ON UPDATE RESTRICT, except the completion-to-witness FK which uses ON UPDATE NO ACTION and is DEFERRABLE INITIALLY DEFERRED.

| Child | Parent | Requirement |
|---|---|---|
| completion.merchant_id | merchants.id | Stable tenant reference |
| completion.actor_user_id | users.id | Stable actor reference |
| completion.(membership_id,merchant_id,actor_user_id) | merchant_memberships.(id,merchant_id,user_id) | Reuse `se1_memberships_tuple` parent key |
| completion.(application_id,merchant_id,attempt_number) | merchant_applications.(id,merchant_id,attempt_number) | New UNIQUE index `bnc1_application_identity_tuple` |
| completion.id | witnesses.completion_id | Deferred FK, record must have witness at COMMIT |
| witness.completion_id | completions.id | Immediate FK, witness must have record |

Historical auth/email/authority/profile versions are values observed at creation. They have no lifetime FK to mutable current epochs. Application status/current_slot and merchant name/profile_version have no lifetime FK from this history. Parent identity delete/REPLACE protection remains active.

## 3. Constraints and trigger ordering

Connection admission requires SQLite, foreign_keys=1, recursive_triggers=1, an idle managed connection, exact composed profile and no unsupported conflict policy or schema mutation privilege. Recheck PRAGMAs under the reserved writer. Raw DML integrity assumes these enforcement settings remain enabled. A database-file/schema administrator can remove constraints or disable enforcement. This proposal cannot enforce session authenticity or trusted clock provenance against such an actor.

The following is the exact proposed semantic trigger catalogue. Later executable SQL must implement these predicates without widening them and pass the matrix before implementation is approved. No executable DDL or SQL hash is claimed in this design.

| Name | Timing/table | Predicate or action |
|---|---|---|
| bnc1_completion_insert | BEFORE INSERT completions | Abort unless complete prestate/authority predicates below hold. Reject any existing ID, scoped UUID, transition tuple, or unwitnessed record for this merchant before conflict resolution. |
| bnc1_completion_update | BEFORE UPDATE completions | Unconditionally ABORT |
| bnc1_completion_delete | BEFORE DELETE completions | Unconditionally ABORT |
| bnc1_witness_insert | BEFORE INSERT witnesses | Reject duplicate ID. Require exact record and final merchant/draft tuple below. |
| bnc1_witness_update | BEFORE UPDATE witnesses | Unconditionally ABORT |
| bnc1_witness_delete | BEFORE DELETE witnesses | Unconditionally ABORT |
| bnc1_merchant_completion_guard | BEFORE UPDATE merchants | Any exact empty-to-nonempty name transition requires exactly one unwitnessed record matching OLD id/version and NEW version, fresh authority and exact original draft. Restrict this transition to name, profile_version and updated_at changes only. All other columns must be IS-equal. |
| bnc1_merchant_completion_apply | AFTER UPDATE merchants | On that empty-to-nonempty transition, sync the bound draft, require one affected row, validate final tuple, then insert witness. Ordered statements appear in this single trigger body. |
| bnc1_draft_profile_guard | BEFORE UPDATE merchant_applications | For profile_version changes, require OLD and NEW status draft, unchanged identity/attempt/current slot/submission/review fields, strictly increasing version, and NEW profile_version equal current merchant profile_version. While an unwitnessed completion binds this application, permit only its exact expected→result synchronization and timestamp update. |

Completion prestate means exact merchant `business_name=''`, expected merchant version, nonsuspended/nonclosed merchant, exact application ID/tenant/attempt/current_slot `'1'`/draft with expected profile version and NULL snapshot/submitter/submitted_at, and no SE evidence or submitted audit for that application. Require active verified user, exact auth/email epochs, same-tenant active owner membership with revoked_at NULL and exact authority epoch. The application must have no review projection. Policy/time/restore admission remains enforced by the orchestrator and approved trust boundary.

Final witness predicate means exact same tenant/application/attempt/current slot, draft at result version with original NULL submission/review fields, merchant at result version with canonical valid nonempty name, exact fresh actor/membership epochs and the sole unwitnessed transition record. The merchant trigger copies the completion's occurred_at to the merchant/draft updated_at only if the later approved timestamp convention supports that format. The merchant BEFORE guard requires the same timestamp. Timestamp-format compatibility is an explicit implementation dependency.

The application synchronization uses CAS on ID, tenant, attempt, slot, status, expected profile version and NULL submission fields. It changes only profile_version and updated_at. A SQL affected-row assertion must use a mechanism proven on the selected SQLite version inside the trigger. Failure raises ABORT. No same-timing sibling trigger order is assumed. Existing SE BEFORE guards evaluate the same accepted state. The merchant AFTER trigger performs draft synchronization before witness insertion.

### Canonical operation order

1. Reserve writer, reload and authorize command, validate expected merchant/draft versions and current draft identity, admit time/policy/restore state.
2. Resolve an existing request UUID before any insertion. For a fresh identical-name/current-version command, perform no DML and return current-state no-op after a confirmed transaction boundary.
3. Insert the redacted completion record. Its deferred witness FK is temporarily unsatisfied. Insertion alone cannot commit.
4. CAS merchant from exact empty name/version V to validated supplied name/version V+1 with canonical server timestamp. Require exactly one row. This is the only payload-bearing write.
5. The merchant AFTER trigger synchronizes the draft and inserts the witness. A complete bundle exists before the merchant statement returns.
6. Recheck record/witness/merchant/draft and authority/admission. Attempt exactly one COMMIT.

A record-first transaction which never changes the merchant fails its deferred FK at COMMIT. A merchant-only update fails the record guard. A record plus manually inserted premature witness fails final-state checks. A manual draft-first update fails the merchant-version check. Duplicate/REPLACE insertions fail BEFORE guards, including existing primary-key collisions. UPDATE OR REPLACE cannot overwrite immutable rows. An inserted record cannot represent a genuine no-op because its prestate is empty and its witness requires the actual V→V+1 named transition.

Later valid profile changes use the frozen SE increment rule and may synchronize a current draft to the new merchant version under the additional draft guard. They create no completion record unless they are a newly authorized empty-to-named operation. No lifetime version linkage blocks later submission or valid review status transitions. Submitted history remains protected by unchanged SE guards. No general rename writer or emptying operation is introduced.

**Unproven constraint details remain review gates.** Trigger interaction, affected-row assertion, deferred circular FKs and conflict-policy behavior require fixture evidence. DML-only actors can supply another active actor's identifiers and forge a syntactically valid timestamp. Database constraints cannot establish HTTP actor identity, lawful policy approval or external time/restore trust. Missing trust admission must deny the operation. No SQL design is claimed to eliminate those gaps.

## 4. Composed-readiness integration

Proposed new manifest path `backend/database/activation/bnc1/manifest.json`. Exact required keys are `version`, `record_contract`, `profile`, `mode`, `migration`, `base_pins`, `source_pins`, `objects`, `ledger`, `connection_requirements`. Reject extra/missing keys and duplicate JSON keys.

Fixed values are BNC-1.0 identifiers from section 2. `base_pins` maps the six paths below to their exact SHA-256. `source_pins` maps each new schema/artifact/composed-validator source path to its release SHA-256. `objects` is a sorted array of `{type,name,table,sql_sha256}` entries, one for each object below, with exact SQL stored in the new deterministic schema catalogue. `ledger` contains ordered exact migration identities and approved batch relationships. `connection_requirements` requires SQLite, foreign_keys=1, recursive_triggers=1. Hashes of unwritten sources/SQL cannot be populated truthfully now. Their absence makes the manifest unissuable and readiness false. The manifest's own digest must be pinned by an independently reviewed release boundary to avoid self-authorizing edited sources.

Exact amendment object set comprises two tables, the three named indexes above and nine named triggers in section 3. No views, columns, policy rows, temporary objects or additional triggers are admitted. Engine primary-key autoindexes must match the exact table keys and index_xinfo. Composed catalogue equals the union of the frozen foundation/C catalogue, unchanged SE objects and these fourteen BNC objects. Compare type, name, owning table and exact sqlite_master SQL, plus table/FK/index introspection. Unknown permanent or temporary objects, shadow/attached databases or unknown migration rows deny this write path.

Exact ledger identity multiset equals foundation_ledger migration identities from the frozen C selection manifest, `2026_11_07_200000_activate_b32_security_contract`, `2026_11_08_210000_add_se1_submission_evidence`, and the single proposed BNC migration. Preserve all existing ledger row IDs/batches. Require exactly one new identity with a positive integer batch greater than every preflight batch, within integer bounds. Record that selected batch in the reviewed fixture/release plan. No numeric batch is invented for an unopened working database. No ledger success before complete object creation, FK check and postflight. A later runner must reconcile this requirement with its transactional ledger insertion behavior.

Existing SE ready() must still return false on the composed schema. A new strict validator outside pinned source must reproduce all base validation obligations and admit only the exact additional catalogue/ledger. Calling SE ready() and ignoring its false result, removing objects for validation or treating a manifest as a wildcard is prohibited. Reuse public pure assertions where suitable, independently check base source hashes and exact definitions, and prove parity against base validation. The private exact-catalogue logic currently prevents simple composition by delegation.

Direct consumers requiring deliberate integration are ApplicationSubmissionService at both pre-writer and under-writer checks and LifecycleMutation::versioned(). Indirect consumers include RegisterMerchantOwner, VerifyAccountEmail, RevokeMembership, PasswordResetController, EmailVerificationController and User notification selection. Completion POST and explicit resolver require this exact composed validator. Base-only workflows retain strict base admission when the selected supported profile is genuinely base-only. Any BNC object/ledger presence selects full composed validation or denial. Partial SE/BNC, unknown mixes and attempted legacy fallback deny. DR-1 is excluded from this proposed profile and needs separate future composition review.

Schema capability supplies no operational activation. Existing public submission, decision and payment denials remain in force.

## 5. Transaction and recovery guarantees

Reuse the reviewed one-writer/one-COMMIT discipline of SubmissionTransaction through bounded existing-onboarding integration. Completion-specific result mapping must preserve explicit unknown classification. Do not adopt VerificationStore's suppressed rollback failures as a guarantee. No reconnect, callback replay, implicit busy retry or automatic POST retry after operation begins.

| Observation | Required result |
|---|---|
| COMMIT positively acknowledged | Confirm completion only after final consistency/admission checks and acknowledgement. Record+witness+merchant+draft commit together. |
| Precommit error and rollback positively acknowledged | Known rollback, no completion success. Preserve original graph. |
| COMMIT acknowledgement missing, COMMIT error or rollback failure | Unknown, discard connection, preserve bounded caller request context, no automatic retry. Even a later successful cleanup rollback does not turn uncertain COMMIT into confirmed absence. |
| Duplicate scoped UUID | Do not execute again. Compare actor/tenant/expected/application tuple and invoke explicit resolution. Changed tuple conflicts. |
| Stale expected versions or wrong draft | Conflict before mutation, including identical desired name with stale expectations. |
| Concurrent requests at same V | SQLite serializes writers. At most one actual completion. Re-evaluate loser after writer acquisition, stale loser conflicts. |
| Busy acquisition | Unavailable without hidden callback retry. Caller may explicitly reload/check. |
| Restore uncertainty | Held/unknown until independently authoritative store lineage, cutover fencing and replay/high-water admission are proven. Local absence proves nothing. |
| Later profile/draft/status change | Historical event existence may be reported to authorized owner. Payload-specific success remains unresolved. No reverse mutation or second completion. |

Resolver uses a fresh serialized connection with exact composed admission, current authorized owner/tenant, trusted time and authoritative restore admission. Exact record+witness+original expected tuple plus unchanged merchant/draft at result V+1 and exact canonical desired name permits confirmation. Record absence plus exact original empty merchant/draft at V, no conflicting history and authoritative store freshness permits authoritative absence. It permits an explicit later command after full revalidation. It does not retry automatically.

No record stores a name commitment. Therefore a matching historical UUID cannot prove which name was requested after later changes, nor distinguish a changed payload reuse solely from recorded metadata. Current-version/name equality permits the bounded resolver described above. Stronger historical payload binding requires separately approved protected history. No-op has no durable receipt and can only be re-evaluated as current state.

## 6. Security assumptions and unresolved dependencies

Approved logical direction permits this proposed witness design. Owner review must approve its additional table, exact SQL/manifest and later integration diff. Trusted clock source, authoritative restore fencing, retention/access/holds and release trust are unresolved operational dependencies. No retention duration or operational policy value is selected here.

The original security contract's allowlisted input, canonical name validation, CSRF, fresh session and owner checks remain requirements. DB checks provide structural authorization consistency, while the server binds authenticated actor and expected epochs. No OTP, NIN or full KYC prerequisite is added to dashboard/name completion. No evidence, proof, counters, financial flags or submission status is changed by completion.

Record-first creates an intermediate deferred violation inside one transaction. A caller must roll back after a failed COMMIT, purge on uncertainty and never leave the transaction available for subsequent requests. SQL statements using IGNORE/REPLACE, savepoints, multirow updates and manually synthesized witnesses require adversarial tests. If any incomplete bundle can commit, approval fails. Exact DDL must resolve the gap before a writer exists.

## 7. Isolated acceptance matrix — designed, unexecuted

All fixtures use the existing environment-file-free bootstrap, fictional identities/time/admission and generated memory or explicitly owned temporary databases. No working database copies, real dotenv, operational credentials or provider effects.

| Case | Required acceptance |
|---|---|
| Normal completion | One record+witness, exact V→V+1 merchant/draft, original IDs/attempt, NULL submission fields, all unrelated values unchanged |
| Genuine no-op | Fresh matching name/versions, zero DML, no record/witness/timestamp/version change. stale identical command conflicts |
| Raw SQL merchant-only | Empty→named without record denied, no partial commit |
| Raw SQL record-only | INSERT succeeds provisionally only with valid prestate. autocommit/COMMIT without witness fails FK. rollback restores original graph |
| Raw SQL fake witness | Before merchant/draft result denied. wrong ID/tenant/version/attempt denied. changing draft first denied |
| Atomicity faults | Inject after record, merchant CAS, draft sync, witness and final check. known rollback preserves full graph and ledger |
| Alternate SQL | INSERT/UPDATE OR IGNORE/REPLACE, UPSERT, multirow DML and savepoints cannot bypass guards or silently overwrite history |
| Duplicate UUID | Same tuple resolved explicitly. changed tuple conflicts. no second increment or record. actor/tenant scopes tested |
| Concurrent writers | Two independent owned connections with same/different names/UUIDs and V, one winner, loser stale/busy, no auto retry |
| Immutable history | UPDATE/DELETE/REPLACE on either table denied, parent identity substitution/deletion denied. later mutable epochs do not break FKs |
| Authorization/time | Cross-tenant/actor membership mismatch, revoked owner, wrong epochs, suspended merchant, malformed timestamp/policy denied. missing real trust adapter holds |
| Schema compatibility | Exact composed profile accepted only by new validator. unchanged SE denies additions. base/partial/unknown/mixed objects/ledger/temp/attached states denied appropriately |
| Migration failure | Fault at every object and ledger boundary, original schema/data/ciphertext/ledger byte-equivalent after known rollback. no admitted partial state |
| Migration uncertainty | Lost before/after COMMIT ack, purge, authoritative inspect only, no rerun on assumed absence |
| Downgrade | Empty fixture amendment may be atomically removed only under approved isolated runner. any record/witness/history refuses destructive down. forward recovery reviewed |
| Frozen preservation | All six hashes below and every base SQL/ledger identity unchanged. no foundation rebuild or audit enum amendment |
| Unknown completion | Before/after actual COMMIT ack loss and rollback failure, exactly one attempt, explicit fresh resolver, authoritative presence/absence and restore holds |
| Later valid lifecycle | Further admitted profile change and draft sync, draft→pending_review via existing complete SE bundle, valid review status changes, submitted profile evidence immutable, completion history stable |
| Future linked successor | Once separately approved DR-1 composition exists, verify new current draft and further lifecycle without historical version FKs. currently mixed DR-1 is denied |
| Overflow/privacy | V=max denies actual change, no-op safe. malformed types/UUID/ULID, sensitive values/logging/response leakage denied |
| Preservation gates | Public successful submission, Chief writes, live payments and operational C/SE activation remain denied |

## 8. Exact paths requiring later changes

Paths below are relative to XLipa. This is a proposed bounded change inventory, subject to a fresh call-site check before implementation.

New isolated schema/readiness artifacts would be `backend/app/Actions/Merchants/BusinessNameCompletionSchema.php`, `backend/app/Actions/Merchants/ComposedSubmissionSchema.php`, `backend/database/activation/bnc1/2026_11_13_210000_add_bnc1_business_name_completion.php`, and `backend/database/activation/bnc1/manifest.json`. No automatic discovery migration under database/migrations is proposed.

Existing changes would be required in `backend/app/Actions/Merchants/ApplicationSubmissionService.php`, `backend/app/Actions/Merchants/LifecycleMutation.php`, `backend/app/Http/Controllers/MerchantOnboardingController.php`, `backend/resources/views/merchants/onboarding.blade.php`, and `backend/routes/web.php`. Put bounded completion/resolver orchestration inside the existing onboarding workflow. Route-model data must be freshly reloaded. Any later reuse change to `SubmissionTransaction.php` needs explicit review and preservation of submission callers. Indirect auth consumer paths listed in section 4 need regression coverage and only need edits if integration cannot preserve their current API.

Proposed test additions are `backend/tests/Feature/BusinessNameCompletionSchemaTest.php` and `backend/tests/Feature/BusinessNameCompletionTest.php`. Existing regression targets are `SubmissionEvidenceSchemaTest.php`, `ApplicationSubmissionServiceTest.php`, `SubmissionLifecycleTest.php`, `AuthenticationTest.php` and `MerchantDashboardTest.php` under backend/tests/Feature. No tests or implementation files are created here.

Frozen C/SE sources, artifacts, JSON manifests, foundation migration definitions, AuditEvent and audit_logs SQL retain their exact bytes. They require no later edit for this proposal.

## 9. Preservation evidence

Pre-write inventory covers 266 existing tracked PHP/JSON/Markdown/HTML/CSS/JS/CJS/XML/lock files, excluding environment files, dependency and storage trees. Final comparison found 266 unchanged files, zero changed files and zero missing files. This bounded inventory covers existing tracked source/contracts/reports and prior completed work. Initial working tree and staging were clean.

Frozen SHA-256 pins retained from inspected sources and verified inventory are as follows.

| Path under backend | SHA-256 |
|---|---|
| app/Actions/Merchants/SubmissionEvidenceSchema.php | 1fde336da8709ea7d052fe4fdb41262e47cea4aad5cd505e32d26c0ee769ee61 |
| database/activation/se1/2026_11_08_210000_add_se1_submission_evidence.php | 75cbd497fa5f5b219087d2777c376f3e0234fb6007be10c48da9e0666dd10431 |
| database/activation/b32c/2026_11_07_200000_activate_b32_security_contract.php | be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97 |
| database/activation/b32c/security-contract.json | 09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0 |
| database/activation/b32c/selection-manifest.json | 20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873 |

The preserved SE manifest SHA-256 is `a44be8d2c4ff2cc228b25a55fd4c22ffb90912aebb393251c14a408cf5be7034`. Final Git status contains only this new untracked design document. Existing tracked diff and staging remain empty. `git diff --check` passed. No working SQLite/sidecar was opened, read, copied, hashed or modified. No secrets/environment/operational keys were accessed. No application code, configuration, migration, tests, old reports or frozen contracts were changed. No runtime, migrations, tests, browser, Artisan or formatter was run. No commit, push or deployment occurred.

## 10. Updated roadmap

| Milestone | Current development state |
|---|---|
| PD-01/02/03, UI-01, PD-04 contracts | Existing work preserved |
| PD-05A final specification/read-only foundation | Preserved, decision writes disabled |
| Minimal registration | Existing completed implementation preserved, historical evidence retained |
| Business-name completion security review | Completed source-based contract retained |
| BNC-1.0 amendment design | Proposed record/witness, constraints, readiness and recovery design delivered for owner review |
| Later isolated implementation | Await separate approval of exact SQL, manifest and integration package, then isolated acceptance evidence |
| Completion writer/form/resolver | Await separately approved bounded implementation after constraint/readiness evidence |
| DR-1 decision/successor composition | Separate future review, no automatic continuation |
| Operational readiness | NO-GO, trusted time/restore/release, policy and production fault evidence remain dependencies |

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public submission: DISABLED. Chief decision writes: DISABLED. Live payments: DISABLED.**


