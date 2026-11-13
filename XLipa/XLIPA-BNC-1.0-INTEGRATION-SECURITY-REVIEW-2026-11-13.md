# XLipa — BNC-1.0 Integration Security Review

13 November 2026 · Africa/Nairobi · Documentation-only source review

## 1. Verified baseline

The BNC amendment supports a separately reviewed fictional integration stage. Operational integration remains held by the fixture-specific ledger, isolated installer, missing completion admission and unresolved recovery evidence.

HEAD is `fc1d7e1877674f65a9e70fd0e7ebedd5c4c68054`. Initial tracked/staged diffs were empty. Initial untracked work comprised the candidate design, exact-SQL report, two BNC classes, migration artifact/manifest and focused test. All are baseline work.

Read backend AGENTS.md. Workspace and XLipa root AGENTS.md and `.ai/rules` at workspace, XLipa and backend were absent. Inspected all BNC sources, manifest and focused test, candidate/security contracts, prior verification report, frozen C/SE sources, listed readiness/auth consumers, notification/session flows, onboarding/dashboard/verification/submission and Chief read-only boundaries. No testing-best-practices skill is available in the session catalog.

Fresh filesystem checks matched all six C/SE source/artifact/manifest pins and three BNC source pins to the manifest. Foundation migration hashes also match the frozen C selection manifest. The source catalogue contains fourteen additive objects, comprising two tables, three indexes and nine triggers. Source inspection covered deterministic SQL and manifest entries. Database objects and ledger state were never queried.

Exact migration identities are `2026_11_07_200000_activate_b32_security_contract`, `2026_11_08_210000_add_se1_submission_evidence` and `2026_11_13_210000_add_bnc1_business_name_completion`.

The reported **208 tests / 1,915 assertions**, including 72 BNC cases, are historical evidence. No runtime, tests, migrations, Artisan, application boot, browser or formatter ran in this review.

| File under backend | SHA-256 |
|---|---|
| `app/Actions/Merchants/SubmissionEvidenceSchema.php` | `1fde336da8709ea7d052fe4fdb41262e47cea4aad5cd505e32d26c0ee769ee61` |
| `database/activation/b32c/2026_11_07_200000_activate_b32_security_contract.php` | `be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97` |
| `database/activation/b32c/security-contract.json` | `09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0` |
| `database/activation/b32c/selection-manifest.json` | `20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873` |
| `database/activation/se1/2026_11_08_210000_add_se1_submission_evidence.php` | `75cbd497fa5f5b219087d2777c376f3e0234fb6007be10c48da9e0666dd10431` |
| `database/activation/se1/manifest.json` | `a44be8d2c4ff2cc228b25a55fd4c22ffb90912aebb393251c14a408cf5be7034` |
| `app/Actions/Merchants/BusinessNameCompletionSchema.php` | `0b581d576a818d2e7fb684ea414fb4e9d9cd063c236d8c64e714181ad50d302a` |
| `app/Actions/Merchants/ComposedSubmissionSchema.php` | `5320c1d623873d0d8b0da525c2074c56b74e5b563cf0d464690d82f1b91a2b76` |
| `database/activation/bnc1/2026_11_13_210000_add_bnc1_business_name_completion.php` | `9080c232392f8ae1caf3258361234455f349b97ba8496ba63f925ff1f1aac017` |
| `database/activation/bnc1/manifest.json` | `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3` |
| `tests/Feature/BusinessNameCompletionSchemaTest.php` | `daae220f5d6fba775607a3e43a9d7c73ef818dd5f4471c8b7c21eca40dc53074` |

## 2. Exact consumer integration map

Paths below are relative to backend. Proposed edits require later implementation approval. Frozen C/SE definitions retain their exact compatibility requirements.

| Consumer | Inspected behavior | Exact proposed change or integration obligation |
|---|---|---|
| app/Actions/Merchants/ApplicationSubmissionService.php, execute() | Calls SE ready() before writer and inside SubmissionTransaction. Submit/resolve share this path. | Replace both calls with strict selected-profile admission for complete SE or BNC. Preserve operational(), fourteen expectations, owner authority, lineage, custody, encryption, retention, proof consumption, replay/resolution and reconciliation. |
| app/Actions/Merchants/LifecycleMutation.php, versioned() | Detects only two SE columns or its evidence table, then requires SE ready(). Complete BNC causes 503. BNC-only residue without these markers can reach legacy. | Classify the complete schema. SE/BNC returns true. Legacy false requires an exact supported foundation/C profile with no amendment residue. Partial/unknown/mismatched profiles deny. |
| LifecycleMutation::run() | Uses VerificationStore for versioned work and DB::transaction for legacy. Admission precedes writer acquisition. | Revalidate selected profile inside the reserved versioned callback before workflow reads. Preserve existing caller APIs. Completion uses SubmissionTransaction directly. Existing lifecycle recovery semantics need separate evidence before stronger claims. |
| app/Actions/Auth/RegisterMerchantOwner.php | Calls lifecycle run(), creates the user/terms/merchant/owner/draft/creation-audit bundle, emits Registered after return. Duplicate email may return before mutation. | Support indirectly through LifecycleMutation. Preserve optional-name normalization, full bundle, guest redirect and generic duplicate handling. Notify only after acknowledged commit. No implicit replay after uncertainty. |
| app/Actions/Auth/VerifyAccountEmail.php | Requires versioned mode, reloads user under writer, checks auth/email/binding epoch and CAS-increments email epoch, then emits Verified. | Indirect support with all fresh email/epoch checks preserved and in-writer schema recheck. Complete BNC must choose this path. |
| app/Actions/Merchants/RevokeMembership.php | Reloads actor/merchant/target under writer, checks expected authority and last owner, invalidates readiness, appends existing audits. | Indirect support. Preserve authority CAS/increment, tenant scope, last-owner rule, proof/challenge invalidation and immutable consumption/audit lineage. |
| app/Http/Controllers/Auth/PasswordResetController.php | requestLink uses broker/token/notification flow. reset() rejects every versioned profile before password/auth writes. | BNC must remain versioned and reach the reset hold. Link preparation supplies no reset availability. Preserve SE account/password/auth trigger hold. |
| app/Http/Controllers/Auth/EmailVerificationController.php | verify() chooses epoch-bound action or legacy fulfill(). resend() calls User notification selection. | BNC selects signed versioned verification. Partial/mismatched profiles deny before fulfill()/delivery. Preserve signed route, throttle and active-session checks. |
| app/Models/User.php, sendEmailVerificationNotification() | LifecycleMutation selects VersionedVerifyEmail using a fresh user. | Indirect support. BNC uses the versioned notification. Invalid profiles cannot select legacy delivery. Registration event remains postcommit. |
| app/Notifications/VersionedVerifyEmail.php | Signed URL binds user, email hash and email_binding_version. | Reuse implementation. Test stale binding, changed email, resend and intended-URL login round trip under BNC. |
| app/Actions/Merchants/VerificationReadiness.php | Validates operational environment/keys/time and required C objects/audit/ledger, without a full SE/BNC catalogue check. | Add shared full schema-profile admission before existing operational checks. Preserve every environment/key/time condition. Deny partial/unknown BNC additions across all callers. |
| MerchantVerification.php / MerchantVerificationController.php | Status/available/mutation paths depend on VerificationReadiness, including writer checks. | Inherit the stricter profile gate. Preserve capture/delivery holds, owner authority, counters, policies and reconciliation. Completion does not call identity/phone capture. |
| MerchantApplicationSubmission.php / MerchantApplicationController.php | Blockers use VerificationReadiness. Public submit always denies. | Inherit strict profile gate. Preserve unconditional public denial. Name completion supplies only its own profile prerequisite. |
| MerchantDashboardController.php | Member/viewSummary controls reads. verificationAvailable depends on VerificationReadiness. | Indicator inherits strict gate. Dashboard access remains verified-email/session/member based, including unnamed merchants. |
| EnsureAuthenticationReady.php | Foundation structure plus local/testing and log/array mail restrictions. | Retain current restrictions. Completion independently requires full profile/owner/trusted admission. This middleware alone supplies no BNC capability. |
| EnsureActiveAuthentication.php / AuthenticatedSessionController.php | Reload account, compare session auth_version, invalidate stale session, regenerate on login, constrain intended signed URL, clear logout state. | Reuse controls and recheck actor/session/expected epoch under completion writer. Test normal session entry points. |
| AuthenticationResponseHeaders.php / bootstrap/app.php | Private/no-store/no-referrer/frame/nosniff headers and category-only merchant logs. Specialized safe renderer covers verification/applications/admin applications. | Extend safe validation/error/unknown rendering specifically to completion endpoints. Existing onboarding paths currently fall outside that specialized renderer. |
| AdminApplicationController.php | Read-only Chief scope excludes creator, any historical member and submitter. Evidence display checks selected references without full readiness assertion. | Preserve read-only/conflict scope. BNC supplies no submission or decision authority. Test current merchant versus immutable submitted profile and limited evidence labels. |

### Shared admission proposal

Proposed `app/Actions/Merchants/WorkflowSchemaReadiness.php` centralizes profile selection outside frozen sources and current BNC source pins. It accepts the trusted expected deployment/fixture profile, never a request flag, and returns an admitted profile or denial. Submission permits complete SE/BNC. Completion requires BNC.

| Expected profile | Required schema | Versioned lifecycle | Completion |
|---|---|---|---|
| Supported foundation | Exact approved foundation definitions/ledger, no amendment residue or unknown objects | Existing legacy path | Unavailable |
| Supported C | Exact frozen foundation+C source/definitions/ledger, no SE/BNC fragments or unknown objects | Existing legacy path | Unavailable |
| SE-1.0 | Unchanged SE ready() plus strict connection/temp/attached/unknown-object admission, no BNC fragments | true | Unavailable |
| b32c+se1+bnc1 | ComposedSubmissionSchema::assertReady() and exact expected-profile match | true | Eligible for further admission |
| Partial, unknown, mixed DR-1 or mismatched selection | Denial | No fallback | Unavailable |

Detect the full object/ledger/column set, including a single BNC index/trigger or ledger row and every SE marker. Unknown objects also deny. A selected-validator failure never tries a weaker profile. Expected BNC restored to apparently complete SE remains held. Foundation/C exact-catalogue admission needs generated-fixture parity tests because current VerificationReadiness validates a required subset.

Proposed `database/activation/bnc1/integration-manifest.json` independently pins helper, admission, request, modified consumers, provider, controller, routes, bootstrap and view sources, with compatibility pins to the current BNC manifest. It adds no SQL object/ledger row. External release approval must pin its digest. Existing BNC releaseDefinition() attests three artifact sources and does not cover new integration sources.

## 3. Onboarding command design

Use MerchantOnboardingController and the existing onboarding page. show() omits application profile_version, and ResolveMerchantContext omits merchant profile_version. Reload authoritative merchant/current-application/membership epochs explicitly for an owner form. Rendering preserves empty storage and the existing display label.

Proposed endpoints are POST `/merchants/{merchant}/onboarding/business-name` named `merchants.onboarding.business-name.complete` and POST `/merchants/{merchant}/onboarding/business-name/resolve` named `merchants.onboarding.business-name.resolve`. Both use the existing web/AuthenticationResponseHeaders/auth/EnsureAuthenticationReady/EnsureActiveAuthentication/verified/ResolveMerchantContext chain, ULID constraint, CSRF and bounded throttling following the existing 10/minute owner-write convention. Final limiter keys should bind actor and tenant with focused tests. The resolver POST validates state only and carries private command context in its body.

Reuse manageVerification for preliminary owner authorization. viewSummary permits more tenant members. Under the reserved writer, reload the exact active owner membership with revoked_at NULL and compare membership_id/authority_version. Platform Chief/Staff status grants no completion override. An independently legitimate owner acts through that ownership.

### Exact input

Allowlist `businessName`, `application_id`, `attempt_number`, `merchant_profile_version`, `draft_profile_version`, `membership_id`, `authority_version`, `auth_version`, `email_binding_version`, `completion_request_id` and transport CSRF token. Actor and tenant come from authentication/route. Server generates record ULID, result version, trusted timestamp and policy reference. Unknown/nested fields, query command fields, client actor/time/policy/status claims and method overrides fail.

Proposed `app/Http/Requests/BusinessNameCompletionRequest.php` accepts internal positive integers or canonical bounded positive decimal HTML strings. Reject booleans, floats, arrays, exponents, signs, leading-zero ambiguity and overflow before casting. Parent-compatible ULIDs and canonical lowercase UUID are required. Both expected profile versions must equal authoritative V. Check overflow only for an actual V+1 change.

Validate businessName as UTF-8 string. Reject NUL/disallowed controls/embedded line breaks before normalization, trim only the approved ASCII whitespace convention, require non-whitespace content and at most 200 Unicode characters. Preserve case and other bytes without Unicode normalization. Unicode-only whitespace fails. Digit strings remain valid display text. This review proposes rejecting controls and line breaks even at boundaries. The later validator diff must state that precedence explicitly and test it against the existing security contract. Escape output.

Laravel TrimStrings uses Str::trim(), which removes Unicode whitespace/invisible characters and may strip boundary NUL before request validation. In bootstrap/app.php, add a narrowly scoped trimStrings skip callback for the two completion URI patterns, preserving existing nin/code exceptions. The locally inspected API accepts closure exceptions. All route fields then receive explicit parsing. ConvertEmptyStringsToNull may continue converting empty input to invalid null. Test malformed UTF-8, padded IDs and Unicode/invisible name boundaries through the actual middleware pipeline. Signup keeps its existing normalization contract. The new request must produce safe field errors without automatic raw-body or businessName flash. Use a scoped failedValidation response and bounded private context only for explicit uncertain-outcome handling.

### Authoritative graph and mutation

Inside one writer, require current active verified user, matching session/current/expected auth epoch and exact email binding, same-tenant active owner with expected authority, and merchant outside suspended/closed. Require exact current application ID/tenant/attempt/current_slot `'1'`/draft at V, NULL snapshot/submission/review fields, no SE evidence/submitted audit and no conflicting BNC history. Inconsistent or missing state is held. Recheck live authority/admission before commit.

Process an existing UUID before no-op classification. A fresh exact-version identical-name request performs zero DML, timestamp/version/record/witness/notification changes. A stale identical-name request conflicts. A different existing nonempty name conflicts. Only exact stored `''` can become named. Noncanonical legacy whitespace names require review.

For actual completion, insert the redacted record with expected V/result V+1. CAS only merchant name/profile_version/updated_at by original empty name/version, requiring one affected row. Use the exact record occurred_at string. Its AFTER trigger synchronizes the draft and inserts the witness. Application code inspects that bundle and issues no second draft update or witness insertion. Preserve unrelated profile/status/proof/identity/contact/context/authority/counter semantics.

Show the form only for an admitted unnamed current draft and authorized owner. Other members receive existing read-only status. Named merchants receive their existing name. Completion admission requires no phone OTP, NIN or full KYC. Existing dashboard access remains verified-email/session/member based.

## 4. Transaction and resolver requirements

SubmissionTransaction requires idle SQLite, starts a managed transaction, reserves the writer with a zero-row context UPDATE, invokes once and attempts COMMIT once. Uncertain COMMIT or failed rollback purges the connection and raises SubmissionOutcomeUnknown. Its message mentions submission, so onboarding must map the type to completion-specific safe feedback without exposing that text.

The existing onboarding integration needs a narrow trusted completion-admission contract for actor/tenant/expected tuple, record policy/access/retention/holds, authoritative current-store restore/fencing and canonical milliseconds. Proposed `BusinessNameCompletionAdmission` with a denied default supplies these facts through a separately reviewed binding. SubmissionAdmission includes proof/identity/custody requirements and cannot silently define completion prerequisites. A request flag, local row, plausible clock or policy-shaped string supplies insufficient trust.

### Required sequence

1. Parse input, perform ordinary session/owner checks and require trusted expected BNC profile. These reads remain provisional.
2. Enter SubmissionTransaction and reserve writer before authoritative workflow reads. Recheck enforcement, composed profile, time/policy/restore and actor/session/tenant authority.
3. Check scoped UUID/history, expectations and original graph. Resolve duplicates explicitly. Fresh exact-version same-name requests are no-op without mutation.
4. For empty-to-named, insert record and CAS merchant. BNC triggers finish draft/witness work synchronously.
5. Reload final bundle, verify unrelated state and fresh authority/admission/time. Full composed readiness may be checked after witness creation. Its foreign_key_check would reject the intentional deferred gap between record insertion and merchant CAS. That phase contains no readiness probe or commit.
6. Acknowledge only after confirmed COMMIT. Any uncertain COMMIT or failed rollback remains unknown, with purge and no automatic replay.

BNC guards establish structural authority consistency at record/witness creation. HTTP session, external time, policy and restore provenance require integration checks. BNC triggers also lack R06/time-hold admission. Before/after mutation, inspect relevant tenant reconciliation/high-water state and deny pending obligations/backward time without altering counters. An absent onboarding context row is allowed. Writer acquisition uses a zero-row statement against the admitted table and creates no identity/contact context or KYC requirement.

| Observation | Required handling |
|---|---|
| Acknowledged COMMIT with consistent final tuple | Confirm saved, still draft, then POST/redirect/GET to existing onboarding |
| Precommit failure with acknowledged rollback | Known rollback, appropriate validation/conflict/unavailable result |
| Missing COMMIT acknowledgement, COMMIT exception or rollback failure | Explicit unknown, purge, bounded private original command context and explicit check/reload |
| Duplicate scoped UUID with changed application/expectation tuple | Conflict without mutation |
| Exact matching record+witness and original actor/tenant/UUID/version tuple, unchanged current draft/name at V+1 | Fresh authorized authoritative admission permits confirmation |
| Record absent, exact original empty merchant/current draft V, no conflicting history | Authoritative absence only with independent restore/current-store fencing. Later explicit command revalidates everything |
| Historical record exists but profile/name/draft/status changed later | Authorized historical existence only, payload-specific outcome held |
| Absent record with advanced/nonempty/mismatched state, missing witness/policy/trust or uncertain restore | Held/unknown/conflict, preserve history |
| Genuine no-op | Current no change needed after confirmed boundary, with no durable historical receipt |

Resolution uses a fresh connection and serialized transaction, current verified owner/tenant authority and complete schema/time/policy/restore admission. Compare historical epochs to the original command/record while validating current authority independently. No query-string names, generic logs or automatic repost. Preserve original UUID, expectations and desired name in bounded private caller context until resolved or held for review.

No name commitment exists. Exact current name at unchanged result version permits bounded positive resolution. Later change-away/change-back or new draft cannot establish historical payload success. Absence after restore cannot prove rollback. A new UUID or fresh no-op supplies no historical receipt.

| Guarantee | Evidence status |
|---|---|
| Types, immutable record/witness, partial SQL denial and trigger ordering | Historical inspected BNC tests, including reversed trigger creation order |
| One writer and stale loser | Historical generated-file two-PDO test, broader multi-process/storage faults unverified |
| Faults during record/draft/witness/final work roll back fixture graph | Historical focused BNC tests |
| Before/after real COMMIT acknowledgement loss, one attempt and purge/fresh inspection | Historical BNC generated-file tests |
| Later profile synchronization and submitted-evidence preservation | Historical raw-schema tests, normal HTTP/service BNC integration pending |
| Rollback failure and migration COMMIT ambiguity | Source branches inspected, separate BNC fault injection absent |
| Form/CSRF/normalization/session races/explicit resolver | Proposed acceptance work |
| Operational time/policy/restore/release and power-loss recovery | Unverified dependencies |

VerificationStore suppresses rollback errors and lacks a dedicated unknown classification. LifecycleMutation uses it for existing versioned operations. Schema integration alone supplies no stronger account/notification recovery guarantee. Any later transaction generalization must review registration, verification events and revocation together, retaining one invocation and postcommit side-effect discipline.

## 5. Migration compatibility findings

The BNC manifest fixes foundation IDs 1–9/batches 1–2, C ID 10/batch 3, SE ID 11/batch 4 and BNC ID 12/batch 5. assertBaseLedger() requires the exact pre-BNC sequence. The installer admits testing, selected canonical artifact, no connection URL and memory SQLite only. Direct artifact up() is guarded, and down() always denies.

Operational compatibility cannot be established from source. Even a working store with those rows would still fail the isolated installation restrictions and independent activation gates. No working database was inspected and no compatible ledger is presumed. Renumbering IDs/batches, adding synthetic activation rows or weakening the manifest would invalidate provenance.

Laravel's generic migrator logs outside the migration's own transaction. A future target-bound design must preserve atomic object/ledger installation and uncertain-COMMIT recovery. Deleting the memory guard cannot supply that design. A broader migration/ledger contract requires separately versioned reviewed artifacts, retaining historical BNC and frozen C/SE pins.

Before any future working-database preflight, require the following evidence and authorization.

1. Owner-approved exact read-only scope, intended target identity, access/custody arrangements and responsible operators. This source review supplies no working-database access authorization.
2. Reviewed preflight implementation exercised on generated compatible/incompatible/partial fixtures, with redaction and explicit prevention of implicit boot/migration/repair/fallback.
3. Target-bound release contract defining expected prior schema/ledger provenance, exact additions, atomic ledger semantics and mismatch quarantine. Actual working IDs/batches remain unknown until separately authorized inspection.
4. Independent release hashes/signatures and custody covering C/SE/BNC and integration sources, with isolated/synthetic artifacts excluded from working activation paths.
5. Authoritative-store lineage, restore/cutover/replay fencing and backup/recovery custody, engine/journal/durability assumptions and uncertainty procedures.
6. Approved minimum-data consistency checks for merchant/current drafts, versions, ownership, submitted history and pending obligations. Incompatible records are preserved for review.
7. Generated-fixture fault evidence at every DDL/ledger boundary, COMMIT before/after durability, failed rollback, restart and forbidden downgrade. Meaningful append-only history requires forward recovery.
8. Reviewed consumer rollout/version matching with writes held during mismatch and tested recovery to an admitted release. Working migration execution and activation require separate later authorization.

A future preflight may establish incompatibility. Preserve that result without fabricating provenance or adopting unknown schema.

## 6. Security blockers

| Finding | Required later resolution |
|---|---|
| Fixture ledger and isolated installer | Separate target-bound migration/ledger design and evidence before working preflight/activation |
| Direct SE consumers reject complete BNC | Strict helper at both submission checks and LifecycleMutation with in-writer admission |
| LifecycleMutation misses BNC-only residue | Full object/ledger/column detection and exact legacy admission |
| VerificationReadiness validates a required C subset | Shared profile gate while retaining environment/key/time checks |
| Structural authentication readiness | Independent completion profile/fresh authority/trusted admission |
| Global trimming and onboarding error handling | Scoped trimming exception, explicit UTF-8/control validation and safe validation/unknown responses |
| Missing trusted time/policy/restore boundary | Narrow admission contract with denied default, independent of phone/identity verification |
| No historical name commitment | Conservative resolver limits, stronger payload binding requires separate review |
| Versioned reset/account mutation hold | Preserve controller denial and frozen SE account/password/auth hold |
| Recovery evidence gaps | Isolated rollback-failure, migration-uncertainty and HTTP resolver evidence before stronger guarantees |

These source findings supply no working-deployment state. Existing local/testing/mail restrictions and denied SubmissionAdmission remain active boundaries.

## 7. Proposed bounded implementation stages

Each stage requires subsequent approval. This task creates the review document only.

1. Review helper/profile selection, narrow completion admission, input precedence, safe-error behavior and release pinning with all operational holds retained.
2. Add proposed `app/Actions/Merchants/WorkflowSchemaReadiness.php` and `database/activation/bnc1/integration-manifest.json`. Integrate ApplicationSubmissionService, LifecycleMutation and VerificationReadiness in fictional fixtures. Prove foundation/C/SE parity, complete BNC admission and partial/unknown/mismatch denial through indirect auth/notification/session entry points.
3. Implement command/resolver inside `app/Http/Controllers/MerchantOnboardingController.php`, proposed `app/Http/Requests/BusinessNameCompletionRequest.php`, `app/Actions/Merchants/BusinessNameCompletionAdmission.php`, a denied default implementation and binding in `app/Providers/AppServiceProvider.php`. Reuse SubmissionTransaction. Add the two proposed routes in `routes/web.php`, plus scoped normalization/error handling in `bootstrap/app.php`.
4. Extend `resources/views/merchants/onboarding.blade.php` and show() data for owner form/status/explicit check using existing policies/middleware. Verify canonical timestamp bytes through merchant/draft updated_at round trips, accessibility and current theme.
5. Add proposed `tests/Feature/BusinessNameCompletionTest.php` and necessary bounded regression cases. Execute focused integration, vetted safe regressions and offline responsive checks, report exact results and stop for review.
6. Prepare operational preflight/migration compatibility only under separate authority and section 5 evidence. DR-1, PD-05B/PD-06 and payments retain separate scope.

Indirect caller files can retain APIs if the helper suffices. The future diff must account for User, RegisterMerchantOwner, VerifyAccountEmail, RevokeMembership, auth controllers, notification and session behavior through tests. Frozen C/SE and existing BNC artifacts/manifest require no edits for this isolated integration proposal. A discovered schema defect or changed ledger contract requires separate amendment review.

## 8. Proposed integration acceptance matrix

Every entry below is an unexecuted plan for generated fictional memory or explicitly owned temporary SQLite fixtures through the inspected isolated bootstrap. The historical 208/1,915 result remains the prior baseline.

| Area / test target | Exact acceptance |
|---|---|
| WorkflowSchemaReadiness and existing SE/BNC schema tests | Complete selected foundation/C/SE/BNC, every single BNC object/ledger fragment, partial SE columns, unknown/mixed/temp/attached/statistics objects, source/manifest/ledger drift. Expected BNC restored to SE denies. Unchanged SE ready() still rejects BNC additions. |
| AuthenticationTest minimal registration | Omitted/null/empty/whitespace business name persists empty, supplied name preserved, complete user/terms/merchant/owner/draft/audit graph and epochs, zero completion records at signup. Guest/generic duplicate behavior and audit/commit-fault side-effect discipline. |
| User/VersionedVerifyEmail/EmailVerificationController | BNC selects signed binding epoch. Stale/wrong-email/tampered signature/revoked auth denied. Explicit resend and intended-URL login round trip. Invalid profile cannot choose legacy fulfill/delivery. |
| PasswordResetController and session tests | BNC reset held before token/password/auth mutation. Session auth epochs, login regeneration and logout invalidation preserved. Link preparation grants no reset/completion success. |
| RevokeMembership/lifecycle | Authority CAS, tenant scope, last-owner rule, proof/challenge invalidation/audits preserved. Revocation between preliminary and writer checks denies completion. Later epochs do not break historical FKs. |
| MerchantDashboardTest / MerchantAccessTest | Verified owner accesses unnamed dashboard without OTP/NIN/KYC. Existing manager/member read visibility preserved, no completion controls. Guest/unverified/stale/inactive/foreign users denied. |
| Proposed BusinessNameCompletionTest authority | Direct command/resolver for owner/nonowner/platform-only Chief/Staff/independent legitimate owner, revoked membership and stale auth/email/authority. Foreign IDs nonenumerating denial. Suspended/closed/absent/wrong-current/submitted contexts conflict. |
| Real request middleware | Exact allowlist, CSRF and method/query overrides. Malformed types/UTF-8, controls/NUL/newlines, ASCII trim, Unicode-only whitespace/invisible boundaries, 200/201 characters and digit strings. Padded IDs, leading zeros/signs/exponents/overflow fail. Signup pipeline remains compatible. |
| Command/current draft | At initial and later valid V, merchant/exact current draft advance once with same IDs/attempt, one record+witness and canonical time. Fresh identical-name command zero DML. Stale identical/different existing named requests conflict. Max-version no-op avoids arithmetic. |
| BNC audit integrity | Server ULID/time/policy, exact redacted tuple/UUID uniqueness. Record-only/merchant-only/fake witness, REPLACE/UPSERT/IGNORE, savepoints and duplicates cannot commit partial bundle under admitted enforcement. No name bytes/digest in records/logs. |
| ApplicationSubmissionServiceTest / SubmissionLifecycleTest | Both service readiness boundaries require selected exact profile. Missing name blocked. Completion supplies no phone/NIN/proof/custody trust. Full fictional internal evidence can submit through service after completion. Public submit still denies. Later profile changes preserve submitted evidence bytes. |
| AdminApplicationReviewTest | Chief queue/detail remains read-only, historical conflicts excluded. Current merchant versus submitted profile distinguishable. No decision projection/audit/write endpoint enabled. |
| Concurrency and faults | Same/different names/UUIDs at V across independent generated writers, one winner and stale/busy loser, no callback retry. Faults after record, CAS/draft/witness/final checks/admission produce full known rollback or explicit unknown. |
| Resolver through routes | COMMIT acknowledgement loss before/after real commit and rollback failure. Purge/one attempt, explicit fresh resolver with unchanged tuple, authoritative absence, changed expectations, later profile/draft/status, revoked current owner, unavailable time/policy and restore holds. No automatic POST or phantom receipt. |
| Readiness sequencing | Full validation before record and after witness. Intentional deferred intermediate gap never weakens foreign_key_check or becomes a false rollback claim. Missing final witness denies commit/acknowledgement. |
| Error/privacy boundary | Safe 403/404/409/422/429/503/CSRF feedback, escaped input, no SQL/stack/name/key/identity leakage. Unknown never claims rollback or saved. No-store/no-referrer/frame/nosniff on success/errors. Bounded private resolution context. |
| Mobile onboarding | Existing light theme at 1440/820/390 px. Owner/nonowner views, keyboard labels/focus/errors/live status, long Unicode names, no clipping/overflow, explicit unknown check and confirmed PRG. Offline fictional exports have zero provider effects. |
| Preservation and release | Before/after source/frozen SQL/ledger/history fingerprints and exact integration manifest. No working database access. Public success, Chief writes, capture/delivery activation and live payments remain denied. |

## 9. Preservation evidence

A pre-write SHA-256 inventory covers 273 existing tracked/untracked source, asset, schema, test, configuration and report files, including all BNC artifacts, Minimal Registration and completed PD-05A work. It excludes environment/secrets, dependencies, storage and working database/sidecars. Final comparison found 273 unchanged files, zero changed and zero missing files. Tracked and staged diffs remain empty. Git status preserves the initial untracked work and adds only this review. Existing git diff --check and the new document trailing-whitespace check passed.

Only `XLIPA-BNC-1.0-INTEGRATION-SECURITY-REVIEW-2026-11-13.md` is created by this task. Existing application code, migrations, routes, views, tests, configuration, frozen contracts and reports retain their inventoried bytes. No working database/sidecar was opened, read, copied, hashed, migrated or modified. No real dotenv, secret, operational credential or key was accessed. No runtime tests/migrations, commit, push or deployment occurred.

## 10. Updated roadmap

| Milestone | Current state |
|---|---|
| Minimal Registration | Completed implementation/historical evidence preserved |
| PD-05A read-only Chief foundation/final specification | Preserved, decision writes disabled |
| BNC candidate security/audit design | Approved candidate direction preserved |
| BNC isolated exact SQL/readiness | Existing artifacts preserved, 208/1,915 remains historical |
| BNC integration security review | Delivered for review with consumer map, command/resolver, ledger findings and acceptance plan |
| BNC fictional consumer/HTTP/UI implementation | Await separately approved bounded stages and integration evidence |
| Working preflight/migration compatibility | Unknown, requires separately authorized target-bound evidence/release design |
| DR-1 / PD-05B / PD-06 | Separate future scope |
| Operational readiness | NO-GO, trusted time/policy/restore/release and production recovery unresolved |

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public submission: DISABLED. Chief decision writes: DISABLED. Live payments: DISABLED.**