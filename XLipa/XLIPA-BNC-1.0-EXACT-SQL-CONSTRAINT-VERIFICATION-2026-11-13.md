# XLipa â€” BNC-1.0 Exact SQL and Constraint Verification

13 November 2026 Â· Africa/Nairobi Â· Isolated fictional schema verification

## 1. Baseline and exact files created

Continued the approved candidate BNC-1.0 design at HEAD `fc1d7e1877674f65a9e70fd0e7ebedd5c4c68054`. Initial Git status contained only the prior untracked amendment design report. Staging was empty. Read backend AGENTS.md and the candidate design, inspected frozen source/ledger contracts, isolated bootstrap, existing schema tests, transaction boundary and Laravel migrator implementation. Workspace and XLipa root AGENTS.md and `.ai/rules` at workspace/XLipa/backend were absent. No testing-best-practices skill was available in the session catalog.

Created exactly these six files, relative to XLipa.

| Path | Purpose |
|---|---|
| backend/app/Actions/Merchants/BusinessNameCompletionSchema.php | Deterministic fourteen-object SQL catalogue and atomic memory-fixture installer |
| backend/app/Actions/Merchants/ComposedSubmissionSchema.php | Strict independent composed validator, source pins and exact isolated ledger |
| backend/database/activation/bnc1/2026_11_13_210000_add_bnc1_business_name_completion.php | Selected amendment artifact, callable only inside its installer, downgrade denial |
| backend/database/activation/bnc1/manifest.json | Canonical source/artifact/object/ledger manifest |
| backend/tests/Feature/BusinessNameCompletionSchemaTest.php | Focused fictional constraint, migration, concurrency, lifecycle and uncertainty cases |
| XLIPA-BNC-1.0-EXACT-SQL-CONSTRAINT-VERIFICATION-2026-11-13.md | This evidence report |

The two new classes and test were scaffolded through Laravel generators using the environment-file-free bootstrap. Laravel 13.35.0, PHPUnit 12.5.38, PHP 8.5.12 and SQLite 3.53.4 were verified locally. The sandbox initially hid the installed PHP executable. Approved escalation made that existing runtime available for the isolated commands.

## 2. Exact SQL objects and constraints

`BusinessNameCompletionSchema::objects()` is the executable SQL authority. The manifest lists every exact SQL SHA-256, type, name and owning table. No foundation table is rebuilt. Existing merchant/application table definitions receive additive triggers and an additive parent index only inside fictional fixtures.

The completion table has sixteen NOT NULL typed columns from the candidate design. Integer storage types and positive bounds, checked V+1 overflow, parent-compatible ULIDs, canonical uppercase record ULID, lowercase UUID, UTC millisecond timestamp consistency and bounded policy syntax are enforced. The witness has one NOT NULL canonical ULID primary key. Neither contains name text, a name commitment, contact/identity values or arbitrary JSON.

Five completion FK groups bind merchant, user, same-tenant membership, stable application/attempt and witness. The record-to-witness FK is DEFERRABLE INITIALLY DEFERRED with ON UPDATE NO ACTION. All other references are immediate and RESTRICT. The witness immediately references its record. Historical epochs and profile versions have no lifetime FK to current mutable values.

Request uniqueness is `(merchant_id,actor_user_id,completion_request_id)`. Transition uniqueness is `(merchant_id,expected_profile_version)`. The application parent key is `(id,merchant_id,attempt_number)`. Both tables reject UPDATE, DELETE and primary-key REPLACE. Their BEFORE INSERT guards explicitly deny duplicates before SQL conflict handling.

Catalogue and per-object hashes are included in the appendix below.

## 3. Atomicity and raw SQL bypass results

Completion records are inserted first under the reserved writer. Insertion requires the exact empty merchant, same-version current draft with no submission/review projection or evidence, and active verified owner with matching auth/email/authority epochs. The deferred FK prevents a standalone record from committing.

The merchant BEFORE trigger requires its unique unwitnessed record, exact expected/result version, unchanged unrelated fields and canonical valid nonempty name. The merchant timestamp must equal the record's canonical occurred_at string. The AFTER trigger performs the exact draft synchronization, asserts `changes()=1`, inserts the witness and asserts `changes()=1` again. The witness insertion requires the complete final merchant/draft/authority tuple and matching timestamps. SQL statement ordering lives in this one AFTER body. Same-timing sibling trigger creation order supplies no authority.

The additional draft guard prevents a draft-first version change and restricts pending completion synchronization to the exact profile/timestamp update. Later valid merchant profile changes and increasing draft synchronization remain possible. Submitted application profile and evidence remain frozen by unchanged SE guards.

Executed cases cover record-only autocommit and explicit COMMIT denial, merchant-only denial, premature/absent-record witness denial, draft-first denial, malformed/foreign/stale tuples, invalid names, non-name side effects, pending duplicates, immutable records and six conflict policies. Savepoint release cannot discharge the deferred FK. Multirow insertion of a duplicate transition cannot leave a partial record. Failure during draft or witness work aborts the merchant statement, and the surrounding transaction rolls back the record.

A direct same-name SQL update leaves the fixture fingerprint unchanged. Version-only advancement and an attempted completion record for a named merchant are denied. HTTP command parsing and a user-facing no-op result remain future integration work.

## 4. Composed readiness and migration ledger

The independent validator requires all six frozen base pins, foundation migration source pins, exact frozen C and SE definitions and the complete BNC catalogue. Canonical JSON re-encoding rejects duplicate keys and alternate/unknown manifest fields. It compares the entire manifest with the expected contract, source/artifact hashes, sorted object catalogue and exact ledger.

Readiness also requires SQLite, FK enforcement, recursive triggers, CHECK enforcement and writable_schema disabled. It rejects temporary objects, attached databases, unknown permanent objects including ANALYZE statistics, missing/changed triggers/indexes, partial profiles and ledger drift. It checks foreign_key_check, declared FK groups, primary-key autoindex shape and BNC object ownership. Exact SQL comparison also covers FK clauses and trigger bodies.

Unchanged SE ready() denies the composed schema as expected. Existing submission/LifecycleMutation consumers retain their existing readiness path because this authorization permits new artifacts only. No existing consumer selects the composed validator. Later consumer integration remains a separate approved diff. No legacy fallback exists in the new validator.

The manifest deliberately fixes the generated isolated ledger to foundation IDs 1â€“9 with their frozen batches 1/2, C ID 10 batch 3, SE ID 11 batch 4, and BNC ID 12 batch 5. This narrows the candidate's relative batch rule to the verified fixture baseline. Other ledger histories are denied and require separate review. Existing rows are preserved exactly.

Laravel's generic migrator logs a migration after its schema transaction, so it cannot provide the candidate's atomic schema/ledger guarantee by itself. `BusinessNameCompletionSchema::install()` owns one SubmissionTransaction and invokes the selected artifact inside it, inserts its own ledger row, then verifies the complete profile before COMMIT. Direct artifact up() and all down() calls deny. No generic migration command for BNC is supplied. Downgrade is conservatively disabled even for empty fixtures, with forward recovery requiring review.

Fault injection at all fourteen object boundaries and after ledger insertion verifies complete schema/data/ledger rollback and restoration of base SE readiness. A missing ledger cannot admit partially installed objects.

## 5. Isolated tests and actual assertions

Final combined run passed **208 tests / 1,915 assertions / 57,878 ms**, exit 0, with zero failures/errors. This includes **72 BNC cases** and the preserved SE schema, AdminApplicationReview, Authentication, MerchantDashboard and minimal-signup regression selection. The 72-case count was confirmed by PHPUnit --list-tests. Assertions are reported for the actual combined run.

```text
vendor/bin/pint --dirty --format agent
vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'BusinessNameCompletionSchemaTest|SubmissionEvidenceSchemaTest|AdminApplicationReviewTest|AuthenticationTest|MerchantDashboardTest|test_minimal_signup'
```

Final required Pint passed without changes. The combined run is the final authoritative result. Earlier focused/intermediate results are not added to it.

Commands ran from backend using the existing PHP executable with `-d 'auto_prepend_file='`. Every application bootstrap used tests/bootstrap-isolated.php and Tests/TestCase, which select an absent test environment file, public fixture keys, memory SQLite, array mail/session/cache, null queue/logging and blocked stray HTTP requests. No working environment file was loaded.

Initial focused execution passed the normal bundle. The first full BNC run produced 68 passes and one fixture error in 69 cases. The fictional proof expiry violated the existing frozen `expires_at=verified_at+1800000` rule. Correcting only that fixture produced a passing lifecycle case. An initial manifest metadata parser also failed during preparation and was corrected before fixture execution. Final results below supersede those intermediate results.

## 6. Concurrency, rollback and unknown outcomes

Generated in-memory fixtures were exported with VACUUM INTO to newly owned random temporary fixture files for the two-connection and unknown-acknowledgement tests. These copies originate entirely from generated fictional data. Cleanup closes connections and removes only each explicitly owned file and sidecars.

Two independent PDO connections with zero busy timeout verify writer exclusion. The second writer receives SQLITE_BUSY during contention, then its stale original version/request is denied after the first completion commits. One record and result V+1 remain. No callback retry is used.

Completion fault cases after record insertion, during draft synchronization, during witness insertion and after the final bundle verify full rollback fingerprints. SubmissionTransaction is reused without modifying it. Before/after real COMMIT acknowledgement faults verify exactly one callback and one commit attempt, explicit SubmissionOutcomeUnknown, discarded connection and fresh-connection presence/absence inspection. Generated files make post-purge inspection possible.

This bounded mechanism test supplies no operational restore admission or customer resolver. Failed rollback and migration COMMIT ambiguity are not separately fault-injected in this new suite. Their inherited conservative unknown behavior remains a dependency for later orchestration and recovery evidence.

## 7. Future lifecycle and preservation of submitted evidence

A completed draft successfully executes the existing SE record/proof/audit/submission bundle using fictional opaque envelopes and fictional live-shaped lineage. This is raw-schema compatibility evidence. Existing ApplicationSubmissionService remains untouched and its base-only readiness continues to deny the composed profile.

After submission, a legitimate merchant profile change preserves the submitted application's old version and exact evidence bytes. Attempts to mutate submitted profile/evidence fail. An allowed pending_review to information_requested transition preserves the completion and submission records. Later membership authority changes do not break historical FKs. A separate fixture moves to a later empty profile state using existing SQL semantics, rejects reuse of the old scoped UUID and permits a distinct later Vâ†’V+1 completion. This proves the records impose no permanent current-version pin. It creates no profile-editing endpoint or product behavior.

## 8. Unresolved dependencies and exact scope

No tested bundle invariant was relaxed. Database guarantees assume admitted schema and enforcement settings and exclude a database administrator who can disable enforcement or replace source. SQL identifiers and timestamp syntax alone cannot establish authenticated session identity, trusted external time, approved policy or authoritative restore lineage. Those remain explicit operational dependencies. Policy syntax is constrained, while approval/retention/access/holds require trusted admission.

SQLite text checks reject ASCII controls, NUL, leading/trailing ASCII whitespace, empty and oversized names. The future request boundary must additionally validate UTF-8 and the complete approved input contract. No raw-name commitment exists, so historical payload-specific outcome resolution after later profile changes remains limited by the candidate security contract.

The manifest validates consistency against reviewed bytes. Independent release approval must pin its digest and source set outside writable runtime artifacts. No operational release trust system was invented here. Production crash durability, rollback failure, authoritative restore/cutover/replay fencing and multi-process power-loss behavior remain unverified. No operational certification is claimed.

## 9. Preservation evidence

A bounded before/after SHA-256 inventory covered 267 pre-existing files, comprising tracked source/asset/schema/test/config/report files and the prior untracked candidate design. Final comparison found 267 unchanged, zero changed and zero missing files. The inventory excludes environment/secrets, dependency and storage trees. All six C/SE source/artifact/manifest pins remain exact. Existing tracked diff and staged diff remain empty. Git status contains the preserved prior design and only the six new task deliverables. Existing git diff --check and explicit new-file trailing-whitespace checks passed. This evidence covers the inventoried files.

No working SQLite database or sidecar was opened, read, copied, hashed or modified. No real dotenv, secret, operational key or credential was accessed. Existing source, tests, registration, onboarding controllers/views, routes, submission services, PD-05A work, reports and frozen contracts remain byte-identical within the bounded inventory. No working migrations, external delivery, identity/provider processing, public submission, Chief decision or payment operation occurred. No commit, push or deployment occurred.

## 10. Next owner review and roadmap

The isolated BNC SQL/readiness artifacts and executed evidence are ready for owner review. Approval would cover their exact source/object hashes, fixture-specific ledger, atomic installer and conservative downgrade policy. Any later implementation must separately integrate the composed validator into ApplicationSubmissionService and LifecycleMutation consumers and add bounded onboarding command/resolver behavior under the existing security contract. Merchant-facing form, POST and resolver remain unimplemented.

Minimal Registration and PD-05A remain preserved completed work. BNC isolated schema verification advances the technical preparation milestone. Existing workflow integration, trusted time/restore/policy admission, production fault evidence and operational activation remain pending. DR-1 composition needs separate review. No completion percentage is assigned.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public submission: DISABLED. Chief decision writes: DISABLED. Live payments: DISABLED.**

## Appendix â€” exact release and SQL hashes

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

| Type | SQL object | Owning table | Exact SQL SHA-256 |
|---|---|---|---|
| index | `bnc1_application_identity_tuple` | `merchant_applications` | `b3c3d418fc36e80ef35a8f9841a8bf82cf8f425b70121476c77ec9561f298e0d` |
| trigger | `bnc1_completion_delete` | `merchant_business_name_completions` | `52b38519e4f59888b8e70324c50c9a662094d5c99d25441318a4c00a48b29390` |
| trigger | `bnc1_completion_insert` | `merchant_business_name_completions` | `c1082439e1d40f4b64735a35c90a63b6d63ce310a123ea97b67a0005d61054e3` |
| trigger | `bnc1_completion_update` | `merchant_business_name_completions` | `d323ebe41e2d81dea1e6ba4eaa72c53d2813f1ad01ad09cbba68a06b77a79d7c` |
| trigger | `bnc1_draft_profile_guard` | `merchant_applications` | `86d83da49e21092c38df7245c28978b774d33dd3d79df945990a8adb5b641e62` |
| trigger | `bnc1_merchant_completion_apply` | `merchants` | `248c5c816f37773e94509bf372ff34a6173be5c8711393124f6b1801004dd729` |
| trigger | `bnc1_merchant_completion_guard` | `merchants` | `fa4dcf1c166ad103f6c7300bb18f76da188a88248fd24731c3684448b6fa5fa3` |
| index | `bnc1_request_tuple` | `merchant_business_name_completions` | `c3124d86c2254cb4069e418fea71ad5b4a05c751d0b94fa3dfdb60fd0765489c` |
| index | `bnc1_transition_tuple` | `merchant_business_name_completions` | `22b48a50efcf4e8b3e47afd342166f0a330cf80fab9a4c37c968b02b3764bbca` |
| trigger | `bnc1_witness_delete` | `merchant_business_name_completion_witnesses` | `737369c32c7b1ad789ed7b2f61a37b9a120a9409fa462e384a606277e2e0f0ee` |
| trigger | `bnc1_witness_insert` | `merchant_business_name_completion_witnesses` | `ca438b67314f77b561676904e0778ab1d9659ffbb11508b5fcd7bed1494f29bb` |
| trigger | `bnc1_witness_update` | `merchant_business_name_completion_witnesses` | `85b0228d7f54e2c9004dc175bdbde6d29c51b89ab61dea507f4e3ce23e43786a` |
| table | `merchant_business_name_completion_witnesses` | `merchant_business_name_completion_witnesses` | `8283f5529cd99b229ad792cfe267c5651a855fe98b2e9ab12f1c0524fe3d6351` |
| table | `merchant_business_name_completions` | `merchant_business_name_completions` | `77db253939781a68a6dbe00155a125bc1503b7214e82c382aa7ff43fb28584be` |
