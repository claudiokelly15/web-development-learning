# XLipa PD-04B.3 — Merchant Application Submission Service

8 November 2026 · Africa/Nairobi · Production-intended source; isolated fictional execution only

## Outcome

Implemented an internal submission orchestration service around the existing SE-1.0 contract, with strict expectations, fresh owner/session authorization, dedicated evidence crypto, independently denied issuance/retention/time/restore admission, atomic bundle writes, deterministic historical receipts and explicit unknown-outcome resolution.

**Final bounded verification: 368 tests / 3,024 assertions, all passed, zero failures/errors.** This includes 43 new service cases and the vetted 325 existing regression cases. Required Pint and `git diff --check` passed.

**Public successful submission remains disabled.** The existing controller still calls the unchanged `MerchantApplicationSubmission::submit(): never` guard. It does not call the new internal service. No activation route, synthetic runtime configuration or operational adapter was added. A test explicitly verifies public denial even with fictional admission and crypto injected into the test container.

**Working B3.2-C activation: NO-GO. Working SE-1.0 activation: NO-GO. Live payments: DISABLED. STOP FOR OWNER REVIEW.**

## Inspection and compatibility findings

Read backend AGENTS.md and reviewed PD-04A, PD-04B/SE-1.0, PD-04B.1, PD-04B.2 and UI-01 implementation context; inspected submission/controller guards, merchant policies, verification provenance/readiness/cipher, schema definitions and pins, audit protocol, transaction helper, synthetic bootstrap/TestCase and test fixture/storage paths. `.ai/rules` was absent at workspace, XLipa and backend paths. No applicable testing-best-practices skill was available. Installed metadata confirms Laravel v13.35.0 and PHPUnit 12.5.38. No dependency or sub-agent was added.

The approved schema supports the implemented bundle ordering without alteration:

1. Insert one immutable evidence row while the application is a current draft and the proof is available.
2. Let the existing SE-1.0 AFTER INSERT trigger create the exact redacted submission audit.
3. CAS the exact available proof to consumed, retaining application/time lineage.
4. CAS the exact draft attempt/profile to pending_review with the same actor and canonical timestamp.
5. Validate the resulting evidence/application/proof/audit agreement and protected contents; recheck live authority/epochs/context, time, expiry and reconciliation state; commit once.

The deferred final-state FKs and frozen triggers remain the database backstops. The service neither inserts a separate success audit nor changes constraints to accommodate service writes. New submissions retain NULL `profile_snapshot`; historical evidence is never backfilled or rewritten. Text `current_slot='1'` is handled using the actual foundation representation.

No schema gap requiring a weakened constraint or a schema amendment was found for this bounded protocol. SQL `live` labels still do **not** establish authentic issuance. That guarantee depends on the separate trusted-admission contract, whose production implementation denies. Restore freshness, policy validity, custody and legal processing authority likewise cannot be established by database labels alone.

SE source remains pinned at `1fde336da8709ea7d052fe4fdb41262e47cea4aad5cd505e32d26c0ee769ee61`. No SE source, migration, manifest, frozen C artifact or lifecycle hold was changed. The PD-04B.2 SE-1.0 password-reset hold remains.

## Service and operational boundaries

### Command and authorization

`ApplicationSubmissionService::submit(User, merchantId, expectations)` accepts exactly 14 expected fields: application ID, membership ID, auth/email-binding/authority epochs, attempt/profile/context versions, identity ID/version, contact ID/version, proof ID and canonical lowercase request UUID. Unknown fields, string-valued epochs, malformed identifiers and nonpositive versions fail. Actor identity and evidence timestamps/keys/policy/audit IDs are derived internally, not accepted as activation inputs.

The service requires the authenticated identity to match the supplied actor, the authenticated user model/session auth epoch to match, and a freshly loaded active, email-verified account. Under the reserved SQLite writer it rechecks the existing `manageVerification` policy and the exact active, nonrevoked, tenant-scoped owner membership. Platform roles grant no submission authority. New writes require matching merchant/application profile versions, current draft attempt, email-binding and authority epochs, exact context and identity/contact references. Suspended/closed merchants, stale state, pending R06 obligations and time high-water rollback are held.

### Independent operational admission

`SubmissionAdmission` explicitly defines:

- `authorized`: approved submission authority for actor, tenant and expectations.
- `restoreReady`: an authoritative current database, not a stale restored copy or replica.
- `milliseconds`: trusted time.
- `retentionPolicy` and `retentionAllowed`: approved policy plus source-record retention/hold coordination.
- `trusted`: issuer/adapter admission for the exact proof, challenge, delivery intent, declaration and event time.

`DeniedSubmissionAdmission` returns false for permissions/trust/restore/retention and throws for unavailable time/policy. The sole runtime binding resolves this denied implementation. No environment variable or request flag enables admission. Future implementations must use maintained trusted local admission state; this package adds no network/provider calls or issuer registry.

Existing `SubmissionEvidenceEnvironment` remains the separate dedicated evidence/binding-key, crypto retention, time and restore boundary, denied by default. `VerificationEnvironment` remains independently unprovisioned. The service requires authorized live verification mode and matching evidence/verification namespaces; a live-shaped database row alone is rejected by the default admission contract.

### Evidence and provenance

The service checks current approved declaration policy, exact contact/identity logical versions, available/unrevoked/unexpired proof, consumed live challenge and accepted timely live delivery intent, then requires independent trusted issuance. It decrypts source values internally with their authoritative key references and envelope revisions, checks the phone fingerprint, and produces the existing minimal contextual AEAD capsule/content commitments. It subsequently decrypts and verifies that capsule before acknowledging the bundle.

Plain NIN/phone/OTP do not enter evidence capsules, audits or receipts. Email/business name remain in the protected capsule. The canonical trigger audit contains only the existing allowlisted status/profile/reason metadata. Generic service errors discard underlying SQL/adapter exception details. No reveal endpoint, notification, delivery job, review decision or financial side effect was introduced.

### Idempotency and historical receipts

The lookup scope is merchant + authenticated actor + request UUID. A matching complete bundle must match every expected field and pass audit, proof-consumption, application, AEAD, source-content MAC, retention and issuance checks before the same five-field receipt is returned. The receipt contains application ID, merchant ID, request UUID, submitted time and `application.submitted` event only.

Receipts describe a historical committed event. Later proof expiry/revocation does not erase that event or imply current eligibility. Current owner/session access is still required; revoked owners cannot use replay as an access bypass. Same UUID with a changed expected tuple conflicts; a different UUID for a submitted attempt conflicts. Replays do not consume again or create another audit/evidence row. Source-envelope rewrapping with unchanged logical content preserves the receipt; substituted content fails its frozen MAC.

### Unknown outcomes and retry discipline

`SubmissionTransaction` reuses the established zero-row SQLite writer-reservation technique, with no retry loop. It refuses nested/non-SQLite transactions. Known precommit failures roll back the managed transaction. A COMMIT acknowledgement error, or failed rollback, discards the connection via `DB::purge` and raises a dedicated generic `SubmissionOutcomeUnknown`; it does not claim that a commit failed or automatically replay it.

`resolve` performs a serialized, authorized, schema/restore-admitted lookup on a fresh connection. It returns a validated historical receipt, or null for an absent bundle with a matching current draft/expectations and reconciliation state. Null does not retry anything. An explicit later `submit` may reuse the same UUID/expectations; it rechecks all admission and evidence prerequisites. Unknown/conflicting/partial state is held, never repaired by manufacturing evidence.

No production restore-admission implementation is supplied. Thus the service cannot currently assert authoritative absence or success operationally. Connection-loss tests below are bounded fault injection, not deployment-grade power-loss recovery evidence.

## Exact task changes

Paths relative to `XLipa/`:

| File | Purpose |
|---|---|
| `backend/app/Actions/Merchants/ApplicationSubmissionService.php` | New strict internal orchestration, bundle validation, receipt and resolution |
| `backend/app/Actions/Merchants/SubmissionAdmission.php` | New explicit operational admission interface |
| `backend/app/Actions/Merchants/DeniedSubmissionAdmission.php` | New default-denied implementation |
| `backend/app/Actions/Merchants/SubmissionTransaction.php` | New writer/rollback/commit-uncertainty boundary |
| `backend/app/Actions/Merchants/SubmissionOutcomeUnknown.php` | New safe uncertain-outcome exception |
| `backend/app/Providers/AppServiceProvider.php` | Bind admission interface to denied implementation; only pre-existing source file changed |
| `backend/tests/Feature/ApplicationSubmissionServiceTest.php` | New isolated protocol/security/fault/contention cases |
| `backend/tests/Support/FakeSubmissionAdmission.php` | New fictional issuance/admission fixture |
| `backend/tests/Support/FakeSubmissionVerificationEnvironment.php` | New fictional source crypto/live-shaped mode fixture; delivery remains denied |
| `XLIPA-PD-04B3-SUBMISSION-SERVICE-IMPLEMENTATION-2026-11-08.md` | This report |

Eight source/test files plus this report were added. Existing public submission code, routes, policies, UI-01 assets/views, evidence cipher/environment, verification store and all existing tests were preserved.

## Tests and executed evidence

The new test class uses the inspected isolated bootstrap/TestCase and generated foundation/C/SE-1.0 memory fixtures. It creates genuine encrypted **fictional** source values and injects disposable keys plus a test-only issued-lineage registry. Its live-shaped SQL records are protocol fixtures, not authentic live verification or live-eligible merchant submissions. Test fakes are never imported or bound by runtime source.

The only file-backed cases export that newly generated memory database to a fresh `tempnam` path, assert its parent is the system temp directory, open a second connection or inject a commit acknowledgement fault, then purge connections, restore memory configuration and delete only their own generated file/sidecars in `finally`. No fixture is derived from PREVIEW-01 or working storage.

Commands from `XLipa/backend`, using `C:/Users/hp/.config/herd/bin/php85/php.exe -d auto_prepend_file=`:

```text
vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/ApplicationSubmissionServiceTest.php
vendor/bin/pint --dirty --format agent
vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'ApplicationSubmissionServiceTest|SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest|SubmissionLifecycleTest|SubmissionEvidenceCipherTest'
```

| Execution | Actual result |
|---|---|
| Expanded focused service run | **43 passed / 407 assertions / 23,738 ms**, exit 0 |
| Final combined vetted regression | **368 passed / 3,024 assertions / 110,499 ms**, exit 0; zero failures/errors |
| Final required Pint | Passed without changes, exit 0 |
| Git whitespace check | Passed, exit 0 |

The combined result includes the focused class: **325 prior cases / 2,617 assertions + 43 new cases / 407 assertions**. Do not add the focused run to that combined total. The final combined run covers the final formatted source, including the final fresh-authority replay check. Visual export flags were 0. No unrestricted suite, live browser or new frontend visual checks were run; UI-01 preservation is supported by unchanged files and existing regression behavior.

New coverage includes:

- One complete immutable bundle, exact safe receipt shape, no private-value leakage, one proof consumption/audit/evidence, unchanged merchant operational/verification status, NULL snapshot and no notifications.
- Every expected ID/version/epoch/attempt substitution; malformed/extra fields and string epochs.
- Guest, foreign actor/tenant, manager, revoked owner, mismatched session epoch and denied runtime container binding.
- Missing submission authority, restore/custody readiness, retention, trusted issuer, wrong source key, bad evidence key, time rollback and simulated mode.
- Simulated **database** proof/challenge/intent lineage rejected even while the injected verification mode says live.
- Exact expiry, proof revocation, same-request replay, changed-request/tuple conflicts and historical receipts after expiry/revocation.
- Trigger-generated audit acknowledgement failure, late proof/application acknowledgement failures and authority change between evidence and proof consumption, all with rollback fingerprints spanning the complete synthetic workflow state.
- Genuine pending R06 hold with no counter/obligation discharge, time high-water hold and source retention admission.
- Source-envelope revision rotation preserving evidence/receipt bytes and changed logical content failing frozen commitments.
- Missing schema trigger denial rather than repair/adoption.
- Two-connection writer contention on a generated fixture: competing writer refusal, release, one successful bundle, identical replay and different-UUID conflict.
- Lost acknowledgement **before and after actual COMMIT** on a generated file: exactly one commit attempt, discarded connection, no automatic retry, restore denial, fresh resolution of absence/presence, explicit same-request retry when absent, and exactly one final bundle/audit.

These are deterministic two-connection and commit-fault cases, not simultaneous production HTTP/process-kill/storage-failure certification. Database raw tampering/deferred-bundle constraints and broader role/tenant/auth preservation remain covered by the existing schema and regression classes.

Development history: initial 35 service cases passed 328 assertions. The expanded 41-case run passed 40 cases / 380 assertions and had one setup error: a retention-row UPDATE omitted the frozen row-version increment. The fixture was corrected by supplying its intended retention at initial insertion; no trigger was weakened. Review also corrected source decryption to use stored envelope revisions and added rotation/MAC coverage. One invocation used the workspace rather than backend directory and could not locate PHPUnit; no tests ran in that invocation. Final results above supersede intermediate evidence.

## Preservation and remaining operational blockers

A bounded pre-edit SHA-256 inventory covered **201 existing source/asset/script files**: **200 unchanged, one intended provider change, zero missing**. The inventory deliberately excluded working databases/sidecars, dotenv, secrets and dependency/storage trees. It is not a whole-workspace certificate.

Frozen C hashes remain:

- Selector artifact: `be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97`.
- Security contract: `09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0`.
- Selection manifest: `20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873`.

SE-1.0 source/artifact/manifest, existing public guard/controller, lifecycle/cipher source and UI-01 files are unchanged from this task's starting point. HEAD remains `e2e746a60580d5d4adbf50511a45b3084c38587c`; staged diff remains empty.

Still required before operational use: independently reviewed real admission/issuer trust, dedicated key custody and recovery, approved retention/holds and source retention coordination, trusted time, authoritative restore/replay admission, lawful identity policies/historical verification admission, account lifecycle decisions, working activation/release tooling and production crash/concurrency evidence. Any future public endpoint integration requires its own strict-input and activation review; this package leaves that path unconditionally blocked even if an integration later provisions dependencies.

No working SQLite or sidecar was opened, read, copied, hashed or modified. No real dotenv, operational key or provider credential was accessed. No working migration or SE/C activation, real identity processing, OTP/email delivery, Chief decision, Selcom connection or payment occurred. No UI styling change, commit, push or deployment occurred.

**Internal service ready for owner review of bounded source and fictional evidence. Public successful submission remains DISABLED. Working B3.2-C and SE-1.0 activation remain NO-GO. Live payments remain DISABLED. STOP FOR OWNER REVIEW.**
