# XLipa — PD-04B.1 SE-1.0 Schema Amendment & Isolated Constraint Tests

**8 November 2026 · Africa/Nairobi · Production-intended database definitions; generated fixture execution only**

## 1. Outcome and boundary

**IMPLEMENTED / VERIFIED:** a separately selected SE-1.0 amendment, source manifest, exact composed-schema capability check, immutable application evidence table, database-level bundle/lineage/epoch guards, trigger-generated redacted audit and deferred final-state foreign keys. The valid **schema-only fixture** sequence inserts evidence → generates audit → consumes proof → changes draft to pending_review → commits. Incomplete bundles cannot commit in the executed cases.

**Not implemented or enabled:** production successful-submission service/HTTP path, evidence encryption service, authentic live provenance admission, operational keys, retention operation, account/credential lifecycle integration, working amendment/C activation, Chief decisions or financial eligibility. PD-04A's unconditional evidence blocker remains intact. `SubmissionEvidenceSchema::ready()` means **schema capability only**, not operational readiness, processing authority or authentic proof.

Final focused evidence: **46 tests / 339 assertions**, zero failures/errors. Vetted nine-class regression: **280 tests / 2,391 assertions**, zero failures/errors. Required formatter passed. No unrestricted full suite was run.

No working SQLite/sidecar was read, hashed, copied or modified. No real dotenv, identity, operational secret/provider account, real OTP/email, provider API or Selcom was accessed. No ordinary working Artisan boot/migration, actual administrator provisioning, payment, commit, push or deployment occurred. Working C activation remains **NO-GO**.

XLipa is a Tanzania-first, internationally scalable payment gateway **WEBSITE supporting TZS only**. No international country/identity/currency workflow was added.

Labels: **IMPLEMENTED** = source added here; **VERIFIED** = inspected source or newly executed bounded evidence; **PROPOSED** = future work; **BLOCKED** = intentionally unavailable pending integration/approval; **NOT TESTED** = no execution here.

## 2. Inspection, plan and necessary deviations

Read backend `AGENTS.md`, checked `.ai/rules` at workspace/project/backend paths (absent), and reviewed PD-04B, PD-04A, PD-03, B3.2-A/B decisions, frozen C JSON/artifact/selection manifest, actual foundation migrations/models, audit/verification/transaction/readiness code, factories, isolated bootstrap and selected test storage paths. No applicable testing-best-practices skill was available or installed. No dependency, framework, website service or sub-agent was added. Local installed package metadata confirms Laravel **v13.35.0**; existing PHP **8.5.12** was used. Files were created directly to avoid ordinary working generators/application boot.

The concise plan and incompatibilities were presented before edits. PD-04B remains a proposal baseline, not operational approval. Its historical text was not rewritten; the following implemented deviations/clarifications require owner review:

| Proposed point | Actual implementation and reason |
|---|---|
| Engine-maintained epoch increments, while denying all direct version-only assignment | With only the two proposed scalar columns, SQLite cannot distinguish an inner trigger UPDATE from the same caller UPDATE based on OLD/NEW row data alone. No authorization flag, hidden bypass or mutable helper table was invented. **Selected implementation: caller-supplied increment constrained by BEFORE triggers.** A change requires exactly old+1; no change requires the same version; clearing verification is mandatory on address change. Unversioned writers fail closed. This preserves the logical monotonic guarantee but changes the proposed increment mechanism. |
| Existing auth/account lifecycle guards | No new authentication lifecycle semantics were inferred. In the amended profile, changes to account status, password or auth_version are **blocked outright**, including session-version-only changes. Existing email verification/revocation/profile writers that do not supply the required epoch/CAS fail closed. They are not rewritten here. C-only/default runtime behavior is unchanged because SE-1.0 is not automatically installed. |
| Current-slot comparison | Foundation `current_slot` is text (`'1'`), not an INTEGER slot. The application transition guard compares its actual text representation and rejects NULL. No foundation definition changed. |
| Deferred constraint errors | SQLite reports failed deferred COMMIT through PDO, whereas statement failures may be Laravel QueryExceptions. Tests accept the real constraint exception family and require rollback/zero framework transaction level; no security constraint was removed to accommodate the error surface. |
| Cryptographic envelope/approved-policy/provenance admission | SQL enforces shape, size, typed references, lineage and stored state. It cannot decrypt AEAD, validate a real legal policy or authenticate a provider merely from `live`. Those remain separate required service/operational gates. Fictional opaque envelopes are used only in database-constraint fixtures; no valid evidence cipher or real verification is claimed. |
| Migration ledger | Standard Laravel records the migration after `up()` returns. DDL is atomic under the managed writer; ledger recording is outside that DDL commit. An interrupted schema-complete/ledger-missing state is **not ready**, and reinstall refuses to adopt it. There is no automatic repair/adoption or claim of atomic framework-ledger commit. |

## 3. Added schema and object catalogue

The deterministic, production-intended definitions are in `backend/app/Actions/Merchants/SubmissionEvidenceSchema.php`. The separate artifact is `backend/database/activation/se1/2026_11_08_210000_add_se1_submission_evidence.php`; it is outside ordinary migration discovery. No existing model, foundation migration or frozen C definition/file was modified.

Two additive foundation columns:

- `users.email_binding_version INTEGER NOT NULL DEFAULT 1`, positive stored INTEGER.
- `merchant_memberships.authority_version INTEGER NOT NULL DEFAULT 1`, positive stored INTEGER.

Existing rows receive structural defaults only; this creates no verification event, lawful-processing authorization or fresh grant.

**31 amendment-owned SQL objects: one table, six unique indexes and 24 triggers.**

| Object | Purpose |
|---|---|
| `merchant_application_submission_evidence` | 27 required typed columns; application PK, unique proof, unique audit, unique merchant/actor/request UUID; immediate tenant/logical-version FKs and three deferred final-state FKs. |
| `se1_applications_tuple` | UNIQUE application/merchant/submitter/time/profile/attempt parent tuple. |
| `se1_memberships_tuple` | UNIQUE membership/merchant/user tuple. |
| `se1_identities_tuple` | UNIQUE identity/merchant/declarer/logical version tuple. |
| `se1_contacts_tuple` | UNIQUE contact/merchant/actor/purpose/logical version tuple. |
| `se1_proofs_tuple` | UNIQUE proof/merchant/actor/contact/purpose/consumed application/consumed time tuple. |
| `se1_audits_tuple` | UNIQUE audit ID/type/actor/merchant/application/request/time tuple. |
| `se1_evidence_insert` | Fresh SQL-expressible owner/account/epochs, draft/attempt/profile/context, current identity/contact, live-shaped proof/intent/challenge, timestamp/high-water, R06 and collision checks. |
| `se1_evidence_update`, `se1_evidence_delete` | Unconditional mutation/deletion denial, including no-op UPDATE. Insert collisions reject replacement/UPSERT attempts before historical evidence can change. |
| `se1_evidence_audit` | AFTER INSERT generates exactly one canonical redacted `application.submitted` audit. |
| `se1_audit_insert` | New submission audits require matching evidence and current draft; orphan/mismatched INSERTs fail. Existing audit UPDATE/DELETE guards stay frozen C objects. |
| `se1_proof_consume` | Available → consumed requires matching preinserted evidence and still-current authority/context. Existing C state/consumption-history guards remain in force. |
| `se1_application_insert`, `se1_application_delete`, `se1_application_update` | New attempts begin draft/unsubmitted, no replacement/deletion/identity reassignment, complete draft → pending_review binding, frozen submitted fields, no non-draft → draft revival or unsupported state edge. Existing review enum edges are data constraints only, not a decision service or permission. |
| `se1_users_delete`, `se1_users_identity`, `se1_users_replace` | Prevent account deletion, ID reassignment and ID/email collision replacement. |
| `se1_merchant_memberships_delete`, `se1_merchant_memberships_identity`, `se1_merchant_memberships_replace` | Preserve membership ID/tenant/user lineage and prevent deletion or collision replacement resetting epochs. |
| `se1_merchants_delete`, `se1_merchants_identity`, `se1_merchants_replace` | Preserve merchant identity/creator and deny replacement/deletion. |
| `se1_email_version`, `se1_email_initial` | Initial epoch 1; exact increment on email/verified-timestamp changes, same version on no change, no reset/jump or address edit retaining verification. |
| `se1_authority_version`, `se1_authority_initial` | Initial epoch 1; exact increment on role/status/revocation changes, no reset/jump/version-only edit. |
| `se1_profile_version` | Exact increment on existing business/profile content changes; no unversioned edit, no-change increment or integer overflow. |
| `se1_account_lifecycle_hold` | Deny unsupported account-status/password/auth-version changes in this profile pending reviewed writer integration. |

The evidence fields preserve application/merchant/contract, actor/membership/auth/email/authority epochs, attempt/profile/context, identity/contact IDs and logical versions, purpose/proof, request UUID, millisecond/canonical-text time, audit reference/type, opaque ciphertext and dedicated key references, envelope version and retention-policy version. There are no nullable authority tuples, mutable completion flags or invented identity-verified/payment-ready fields.

Stored integers require INTEGER type and positive versions. New security ULIDs use uppercase C rules; existing merchant/application representations preserve parent-compatible case and binary FK lookup. UUIDs require the canonical lowercase shape. Timestamps are bounded epoch milliseconds and must equal SQLite's canonical UTC seconds plus the exact three millisecond digits. TEXT bounds use byte lengths, no NUL and identifier alphabets where applicable. Ciphertext is bounded nonempty TEXT; canonical AEAD parsing and authenticity remain future cipher responsibilities.

## 4. Atomic bundle and raw SQL evidence

The three evidence FKs to final application, proof-consumption and audit tuples are `DEFERRABLE INITIALLY DEFERRED`, with UPDATE NO ACTION and DELETE RESTRICT. Other referenced identity/contact/membership/user/merchant tuples use immediate RESTRICT. No FK pins mutable current epochs, so a legitimate later binding change does not rewrite or destroy history.

Executed schema-fixture sequence:

1. Reserve the SQLite writer through unchanged `VerificationStore`, before workflow reads.
2. Insert evidence while application is current draft and proof is available.
3. AFTER INSERT generates the exact redacted audit; its BEFORE INSERT guard matches evidence.
4. Consume the exact proof; C's state/version/history rules and the new evidence/current-context guard both apply.
5. Change application to pending_review with matching actor, profile, attempt and canonical text time.
6. Commit validates the deferred final tuples. No notifications, network calls or production service are involved.

The audit metadata is the existing allowlisted scalar object `{"from":"draft","to":"pending_review","profile_version":N,"reason_code":"minimum_evidence_bound"}` in that order; actor/merchant/application/request/time exactly match evidence, target user is NULL. No NIN, phone, email, ciphertext, MAC, key reference, OTP or fingerprint is copied into audit.

**VERIFIED:** standalone evidence autocommit fails; missing evidence/proof/application/audit paths fail without leaving a partial bundle. Missing final application update reaches a real deferred-COMMIT FK failure. Audit failures before and after audit INSERT roll the whole operation back. Managed transaction level returns to zero. There is no substituted partial-success state.

Raw SQL tests deny UPDATE of every evidence column even to itself, DELETE, REPLACE, UPSERT, duplicate evidence, reused proof and reused request. The request-UUID test supplies an otherwise valid new draft and fresh proof: the old UUID fails, then a new UUID permits the schema-only bundle, establishing the collision as a real constraint rather than merely a missing-parent case. Orphan audit and unsupported/fabricated application transitions fail. Changed authority between evidence insertion and consumption rolls back. Later permitted proof revocation preserves its original consumption/application tuple and the complete immutable evidence row; foreign-key checks still pass.

These guarantees assume schema/FK/recursive-trigger enforcement remains intact. A principal able to drop guards, replace files, disable enforcement or control operational keys is outside database-row mutation protection. Source/file/key custody and restore/replay admission remain required; no tamper-proof database or authentic live verification is claimed.

## 5. Migration, manifest and schema capability

`manifest.json` pins the SE-1.0 schema-source and migration-artifact hashes and the unchanged frozen C contract and selection-manifest hashes. It identifies `submission-evidence-v1`, the exact migration name and `isolated-only` mode. The migration can run only with explicit canonical `se1.selected`, testing environment, isolated sentinel, URL-free SQLite **memory** configuration and actual memory connection, FK enforcement and recursive triggers enabled. The runtime source imports no Tests classes or fixture keys. No selection endpoint, environment-only activation toggle or ordinary migrations-path entry was added.

Installation uses the existing Laravel-managed writer transaction. It verifies frozen C table/index/trigger/audit definitions and ledger, original foundation object hashes and exact allowed object names, then adds columns/objects. Existing-object/ledger fragments or duplicate application are refused. Postflight rechecks exact C/foundation-plus-amendment definitions, allowed object names and FK integrity. An injected interruption after evidence-table creation proves both added columns, preceding indexes/table and ledger are absent after rollback. This is a bounded DDL fault case, not power-loss testing after every instruction.

`ready()` composes source-pin checks, unchanged C checks, exact foundation definitions reconstructed by removing only the two known additions, all 31 exact amendment object definitions, allowed object names, expected migration-name set, and exactly one SE-1.0 ledger row. Unknown object/ledger, missing trigger, missing ledger or disabled recursive triggers deny capability. Batch/timing provenance and actual operational authorization are not established by a ledger label.

The standard framework's after-up ledger gap is detected, never silently adopted. A second installer invocation refuses schema-present/ledger-missing state. The test-only manual ledger row in the gap case emulates the framework recording stage to isolate readiness behavior; it is not an operational repair tool or proof that raw ledger insertion grants authority. `down()` always refuses; there is no destructive epoch/evidence rollback.

The original C selector requires its original foundation and must not be rerun against this post-C profile. New tuple indexes/triggers are additional SE-1.0 objects; original C SQL definitions and files remain unchanged. A future release runner needs separately reviewed maintenance, backup, transaction/ledger-gap and forward-recovery gates. This artifact's selection gate is intentionally not a working migration mechanism.

## 6. Monotonic versions and unsupported writers

**VERIFIED:** email A → B → A plus reverification yields versions 1 → 2 → 3 → 4; the old submission expectation fails even when the original address/timestamp is restored. Owner → manager → owner yields authority versions 1 → 2 → 3; stale evidence fails until the fresh epoch is supplied. Direct version-only edits, stale/unversioned edits, resets/fractional versions and user/membership replacement/deletion fail. A pre-existing maximum profile version cannot overflow through a content edit.

The epoch mechanism is explicit constrained CAS-style mutation: callers must include the expected-version predicate as well as new=old+1. The database rejects incorrect increments/content-version relationships, but does not manufacture session authority or determine which caller may perform an otherwise well-formed grant. All service authorization remains necessary.

**BLOCKED in the amended profile:** existing framework email-verification writer without the increment, membership revoke writer without the increment, unversioned profile/registration contact writes, password reset/account/session-version mutation under the lifecycle hold. No auth controller, registration helper, revoke helper, middleware or model was changed. These are integration blockers, not completed compatibility claims on an activated SE-1.0 deployment. The executed existing-feature regression runs the normal C-only/foundation paths; it does not certify these unintegrated writers on an operationally activated amendment.

Before future submission, approve/integrate these writers and their current-readiness invalidation. Add the evidence cipher and protected content commitments, approved-policy/key-retention/provenance/time/restore admission, strict expected-input/idempotent receipt service and unknown-commit resolution. Structural epoch 1 on historical rows is never fresh verification or operational eligibility.

## 7. Provenance correction

The frozen C `contact_verification_proofs` table permits `simulated` and `live`; its challenge FK binds provenance to transport mode. PD-03 `MerchantVerification::verify()` copies that mode into proof provenance. PD-04A `MerchantApplicationSubmission.php:65` compared `live_verified`.

**IMPLEMENTED narrow correction:** compare with `live` instead. No enum, schema, operational environment or provenance issuer was expanded. The evidence-contract blocker and `submit(): never` denial remain unchanged. Existing and regression tests continue to prove default/simulated submission denial. The new schema accepts live-shaped fixture lineage as a database constraint case only; neither that label nor the string correction proves real provider verification.

## 8. Executed tests and limits

Commands from `XLipa/backend`, using existing PHP at `C:/Users/hp/.config/herd/bin/php85/php.exe`:

```text
<php85> -d auto_prepend_file= vendor/bin/pint --dirty --format agent
<php85> -d auto_prepend_file= vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/SubmissionEvidenceSchemaTest.php
<php85> -d auto_prepend_file= vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest'
```

| Final execution | Actual result |
|---|---|
| Pint | Passed, exit 0. |
| Focused SE-1.0 | **46 passed / 339 assertions / 9,280 ms**, exit 0; zero failures/errors. |
| Vetted nine-class regression | **280 passed / 2,391 assertions / 69,292 ms**, exit 0; zero failures/errors. Includes 234 prior cases and 46 new cases. |

Before execution, reviewed storage behavior in the isolated bootstrap/TestCase, B32CRehearsal, new test and selected eight existing test classes/support paths. Test environment is guarded memory SQLite, fake notifications, null logs, blocked stray Laravel HTTP and sentinel dotenv/cache configuration. The normal migration commands seen in those tests execute only inside that isolated environment. No fixture visual-export flags were enabled.

New tests build foundation/C on memory, insert only generated synthetic rows and explicitly select the new amendment. The live-shaped challenge/intent/proof sequence is raw **schema-test data** with fictional opaque envelope strings, not OTP delivery or a cryptographically validated application proof. No test fake is imported/bound in runtime source. Ciphertext integrity, actual provider trust, official identity syntax, real collection authority and evidence key custody are **NOT TESTED** here.

The new contention test exports only its newly generated fixture to a fresh random temporary path, verifies its parent is the system temp directory, opens a second SQLite connection to that file, proves competing writer refusal without bundle writes, then proves a single bundle can commit after release and replay cannot add one. `finally` closes/purges connections, restores memory configuration and deletes only that newly created file and its sidecars. Existing foundation/PD-03/04A contention tests use their guarded disposable paths. No working file/sidecar was touched. This is deterministic two-connection contention, not simultaneous production HTTP or deployment-grade power-loss evidence.

Coverage additionally includes exact C compatibility, legacy snapshot/application/identity/contact/proof/audit row-byte preservation, no fabricated historical evidence, migration interruption, ledger gap/refusal, raw evidence tampering, malformed IDs/versions/UUID/time/key references, expiry/revocation, genuine C-shaped pending R06 and no discharge, deferred rollback, epoch ABA, unsupported account/profile writers and unexpected-object readiness denial.

Development failures are retained as evidence: the first 37 cases failed setup because the raw queued challenge fixture omitted its required active slot; no schema guard was weakened. After correcting the fixture, 24/37 passed, revealing the text current-slot comparison and raw PDO deferred-commit error handling. Both were corrected. Intermediate runs passed 37/279 and 43/324 before the final UUID/R06/overflow additions. Only the final 46/339 and 280/2,391 results above cover the final source. Later formatting passed without changes, and manifest source hashes match that formatted revision.

No frontend/view/style changed, so no new browser/visual/frontend execution was needed. Historical frontend results are not reclassified as new evidence. No unrestricted suite, C acceptance restart, real authenticated browser, evidence crypto, true unknown-commit recovery, retention purge, production concurrency, crash/power-loss or working activation test was executed.

## 9. Exact task delta and preservation

Paths are relative to `XLipa/`. Earlier PD-04A/PD-04B uncommitted/untracked work is baseline, not this task's addition.

| Added file | Purpose |
|---|---|
| `backend/app/Actions/Merchants/SubmissionEvidenceSchema.php` | Deterministic SQL catalogue, guarded installer and composed schema-only capability check. |
| `backend/database/activation/se1/2026_11_08_210000_add_se1_submission_evidence.php` | Separately selected, working-disabled amendment entry; destructive down refusal. |
| `backend/database/activation/se1/manifest.json` | Version/mode/migration and source/C pins. |
| `backend/tests/Feature/SubmissionEvidenceSchemaTest.php` | 46 isolated raw SQL/deferred/version/migration/contention cases. |
| `XLIPA-PD-04B1-SE1-SCHEMA-AMENDMENT-IMPLEMENTATION-2026-11-08.md` | This report. |

**Modified existing file:** `backend/app/Actions/Merchants/MerchantApplicationSubmission.php`, one string correction only (`live_verified` → `live`). No existing test was edited by PD-04B.1. No route/controller/view/style/model, accepted migration/activation artifact, manifest, crypto environment/store/reconciler or earlier report changed.

Bounded pre-edit SHA-256 inventory: **198 pre-existing files**, from app/bootstrap/routes/resources/tests, migration/activation text and root Markdown using an explicit extension allowlist. Working databases/sidecars, dotenv, operational credentials, dependency/storage trees, binaries and protected metadata were excluded before hashing. Post-test comparison: **197 unchanged, one intended correction, zero missing** within this scope. No broader preservation certificate is claimed. No inventory file was created.

Final source pins (SHA-256; report has no self-referential hash):

| File | SHA-256 |
|---|---|
| `backend/app/Actions/Merchants/MerchantApplicationSubmission.php` | `c164fe8e76b33a58e6fa0e03af5ba8dabecff00e48ea9416e34f0223d84a2511` |
| `backend/app/Actions/Merchants/SubmissionEvidenceSchema.php` | `2d23f70fefc9d78c19950f871746acb0d3df54f61fb66202ec9eb2fcbcd9cae9` |
| `backend/database/activation/se1/2026_11_08_210000_add_se1_submission_evidence.php` | `75cbd497fa5f5b219087d2777c376f3e0234fb6007be10c48da9e0666dd10431` |
| `backend/database/activation/se1/manifest.json` | `c8a5c3170ec3a321578ce15f4fbbf2204ad13b0f8e76fe5ee7f6553eaf410332` |
| `backend/tests/Feature/SubmissionEvidenceSchemaTest.php` | `5c7fe451fde1b5e6b15e5614ba39856b6ba9b53fa208663314ce3bb22047f7a3` |

Repository HEAD remains `e2e746a60580d5d4adbf50511a45b3084c38587c`; staged diff is empty. Git's total status contains earlier baseline work, so the per-task comparison above is authoritative for this bounded scope. No commit/push/deployment occurred.

## 10. Owner review and next package

**GO for review of the implemented database amendment and bounded fixture evidence.** Owner review must explicitly consider the caller-supplied epoch mechanism and the account/lifecycle holds; they are documented deviations from the proposed engine-increment/integrated-lifecycle direction.

**PROPOSED next:** a narrowly approved SE-1.0 writer-integration/evidence-cipher package: version-aware email and membership/profile preparation, approved account/session invalidation semantics replacing the lifecycle hold, dedicated contextual evidence crypto/content commitments, maintained policy/retention/provenance/time/restore admission, and exact success/idempotent-receipt/unknown-outcome service tests. Removing the existing submission blocker must be a separate reviewed implementation, never an effect of `ready()` or changing the provenance string. Keep any real key/provider/identity processing and working migration/backup/release approvals independent.

**BLOCKED:** live processing/delivery, successful production submission, review decisions, payments and working C/SE-1.0 activation. **STOP FOR OWNER REVIEW.**
