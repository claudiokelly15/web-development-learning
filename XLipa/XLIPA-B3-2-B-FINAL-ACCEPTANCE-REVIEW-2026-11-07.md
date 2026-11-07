# XLipa — B3.2-B Final Acceptance and B3.2-C Readiness Review

**7 November 2026 · Africa/Nairobi · FAR-1.0 · READ-ONLY REVIEW**

## 1. Executive summary — Muhtasari

**Recommendation: NO-GO for unconditional final B3.2-B acceptance; NO-GO for B3.2-C implementation/activation now.** The ULID correction remains sound in the inspected source, and useful synthetic capabilities are present. However, source inspection identifies a proof-history transition gap and test isolation-of-constraints weaknesses that the reported passing suite does not exclude. Correct and independently test these before declaring the seven-table contract fully accepted. No production exploit, working-data corruption or newly executed failing test is claimed.

Kwa Kiswahili: marekebisho ya ULID yanaonekana sahihi. Lakini tests zilizopita hazitoshi kuthibitisha kila kanuni ya schema. Kuna update ya revocation inayoweza kuongeza consumption history ambayo haikuwahi kuwepo, na baadhi ya tests za constraints zinaweza kupita kwa sababu tofauti na inayolengwa. Hivyo napendekeza marekebisho madogo ya synthetic contract/tests kabla ya kukubali B3.2-B bila masharti. Hakuna code iliyorekebishwa katika review hii.

**REPORTED:** latest implementation report records **135/135 tests, 1,177 assertions, zero failures/errors**, comprising 27 B3.2 cases/330 assertions and 108 existing regressions/847 assertions. This review did not run tests, PHP/Artisan, migrations, a database connection or formatter. Those numbers remain reported historical execution evidence, not a fresh independent pass. Source inspection confirms 23 B3.2 feature methods plus four crypto unit methods.

**VERIFIED:** the source separates binary database identity from uppercase-only AAD encoding; existing-parent references accept valid lowercase ULIDs without rewriting parent IDs; no NOCASE workaround appears in the new schema. Exactly seven candidate business tables are declared. New OTP/crypto helpers remain in tests and have no inspected public route registration. These are accepted source-level observations, not working-schema or production certification.

**PROPOSED next action:** owner authorizes a bounded B3.2-B correction-and-regression task for findings R01–R04 below, retaining isolated databases and current exclusions. Prepare a separate B3.2-C preflight/activation design after acceptance. **REQUIRES OWNER APPROVAL:** any code/test/specification correction or later stage execution; this review makes no such changes.

## 2. Evidence, method and status labels

Labels used precisely:

- **VERIFIED:** inspected source/document/file/hash fact in this task. Where a finding predicts behavior from conditions, it is explicitly a static conclusion, not an executed reproduction.
- **REPORTED:** execution, operational or preservation evidence recorded by the prior implementation report/user; not re-executed here.
- **PROPOSED:** recommended correction, acceptance criterion or sequence.
- **REQUIRES OWNER APPROVAL:** a new scope/policy/execution decision. Previously approved OTP/encryption/proof/ULID policies are not reset to pending.

Repository root `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT`; project `XLipa`. **VERIFIED** HEAD `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`. Initial tree already had four tracked modified files and the untracked B3.2 documents/migrations/tests. No attempt was made to revert, stage or attribute that prior work to this review.

| Evidence reviewed | What it establishes / limitation |
|---|---|
| [SBR-1.1 specification](XLIPA-B3-2-SECURITY-BLOCKERS-RESOLUTION-2026-11-06.md), especially 3.2, 5–9 and 11–15 | Approved ULID correction and concrete intended schema/state/crypto contracts; some older proposal/approval statements remain historical/stale. |
| [Implementation report](XLIPA-B3-2-B-SYNTHETIC-IMPLEMENTATION-REPORT-2026-11-06.md) | Reported test commands/results, limitations, preservation and bounded scope. Its conclusion is reviewed rather than assumed. |
| [Technical Decisions](XLIPA-B3-2-TECHNICAL-DECISIONS-AND-SCHEMA-APPROVAL-2026-11-06.md), [Owner Decisions](XLIPA-OWNER-DECISIONS-AND-B3-2-READINESS-2026-11-06.md), especially OD section 6 | B-SYN-1 boundary; B3.2-C explicitly means working activation, distinct from secure capture D and submission B3.3. |
| New schema migration `backend/database/migrations/2026_11_06_100000_create_synthetic_verification_contract.php` | Exact seven-table DDL, FK/index/CHECK/transition/immutability/deletion contracts and synthetic guard. Source, not live sqlite_master. |
| New audit migration `2026_11_06_100001_extend_synthetic_audit_contract.php` | Memory-only audit rebuild, old/new event union, dependency detection and downgrade refusal. |
| Existing merchant/membership/application/audit migrations, Merchant/Membership/User/MerchantApplication/AuditLog models and enums | Integer user identities, ULID parent keys, existing unique parent tuples, status sets, encrypted historical fields and model-only audit/evidence guards. |
| Policies, routes, EnsureActiveAuthentication, RevokeMembership, database config | Existing scoped read/auth behavior; SQLite DEFERRED default and existing lockForUpdate usage; no new public OTP/capture endpoint. |
| SyntheticCipher/Keys/Value, SyntheticVerification, SyntheticCoordinator, concurrent-worker | Actual test-only crypto, state/rate/proof and concurrency protocol. No production key service or complete delivery lifecycle. |
| B32SyntheticContractTest, B32SyntheticCipherTest, base TestCase/bootstrap/phpunit config; DatabaseFoundationTest migration selection | Actual assertions, broad rejection helper, synthetic file copying, process barriers and compatibility limits. |
| Backend AGENTS.md; package manifests/installed source inventory | Project instructions; installed Laravel manifest/source, not fresh runtime capability. `.ai/rules` absent at workspace/project/backend locations checked. |

**REPORTED runtime:** PHP 8.5.12, Sodium available, SQLite 3.53.4. **VERIFIED manifest:** Laravel v13.35.0. No runtime/platform check was repeated. No live database version, migration status, row counts or integrity was queried. No secret contents were read. No sub-agent delegation was used.

Two primary references were consulted read-only to check API semantics: SQLite supports a single writer and BEGIN IMMEDIATE can return BUSY; PHP Sodium decrypt authenticates and returns false on failure. [SQLite transaction documentation](https://www.sqlite.org/lang_transaction.html), [PHP Sodium decrypt manual](https://www.php.net/manual/en/function.sodium-crypto-aead-xchacha20poly1305-ietf-decrypt.php). Local source remains the evidence for implementation-specific findings. A SQLite CREATE TABLE page fetch timed out; no finding depends on that failed fetch.

## 3. Accepted capabilities — Uwezo unaokubalika kwa mipaka iliyotajwa

Acceptance here is of the inspected bounded approach and represented behavior, **not unconditional final acceptance of all B3.2-B invariants**.

| Capability | Status and precise acceptance |
|---|---|
| ULID storage/AAD separation | **VERIFIED:** migration helper broadens only merchant_id/consumed_application_id alphabets for existing-parent representations; new-record identifiers remain uppercase. SyntheticCipher validates both ULID components, uppercases a local binding copy, and uses the same aad() method in encrypt/decrypt. Binary FK/lookups remain authoritative. |
| Parent/tenant associations | **VERIFIED:** merchant/user RESTRICT references, exact composite contact/challenge/provenance/application tuples and parent UNIQUE definitions align at source. Nullable context/contact tuples explicitly all-null/all-present. No independent FK is presented as equivalent to tenant binding. |
| Narrow schema scope | **VERIFIED:** seven persistent business structures, no financial/dashboard/team/MFA/submission-evidence table. Audit replacement is a transient rebuild object, not an eighth persistent scope table. |
| Crypto mechanics | **VERIFIED:** Sodium AEAD, random 24-byte nonce, combined tag, length/type/version/key/revision/base64url checks, LP context binding, false/error rejection without fallback. Separate deterministic fixture key domains; no APP_KEY lookup by new cipher. |
| OTP bounded happy/failure path | **VERIFIED source:** five-minute expiry, five wrong guesses/challenge, 60-second reserved-send cooldown; ten retained failure timestamps for a strict rolling interval. Expired/ineligible guesses do not become successful proofs. |
| Atomic test operations | **VERIFIED source:** coordinator begins IMMEDIATE before helper reads, performs rollback on exception and bounded busy retries; actions use version predicates/affected row checks. No reliance on SQLite row-level lockForUpdate. **REPORTED:** selected two-process races passed. |
| Proof use | **VERIFIED source:** 30-minute explicit expiry, exact current actor/merchant/contact/purpose lookup and one-time state change; matching challenge lineage/provenance and unique challenge/application relationships. Revoked/consumed proofs do not normally return to available. R01 qualifies history immutability. |
| Test/activation separation | **VERIFIED:** synthetic guard/bootstrap, no real key custody/provider registration, fake modes, helper live-eligibility predicate always false. No current production onboarding-phone feature is implied. |
| Regressions | **REPORTED:** existing 108 cases passed within the combined run. **VERIFIED:** existing policies/auth/routes were not replaced by a new permission bypass. This review does not independently certify current runtime auth. |

## 4. Seven-table compatibility and state review

Source references below are relative to XLipa; line numbers refer to this inspected tree. `M` abbreviates the new `2026_11_06_100000_create_synthetic_verification_contract.php` migration.

| Table | Compatible/intended constraints | Gaps or acceptance qualifications |
|---|---|---|
| merchant_identity_records | ULID merchant FK, integer declaring user FK; unique merchant/version and partial current; current→superseded; envelope revision distinct from logical version; immutable declarations; delete blocked | Ciphertext/authenticated context cannot be established by a TEXT CHECK. Rotation is exercised by test operations, not an operational CAS/recovery service. New timestamp type is ms INTEGER, not existing Eloquent datetime format; future model casts must be explicit. |
| merchant_contact_versions | Composite id/merchant/actor/purpose parent tuple; unique scoped content version/current slot; immutable target/fingerprint; current→retired; dedicated phone envelope | No automatic proof revocation on parent/membership changes; action integration is deferred. Fingerprint-key epoch transition/drain not implemented. |
| merchant_onboarding_contexts | Merchant PK/FK, composite identity/contact pointers, paired nulls, positive context version, current-record check at pointer writes | A pointed-to record may later retire/supersede without a pointer write. This is acknowledged by the specification; runtime readiness must recheck referenced state and change pointers/revoke atomically. No DB-wide invariant alone ensures a current context. |
| phone_otp_challenges | Exact contact tuple, unique active slot per binding, bounded attempts, five-minute stored interval, enumerated transitions, digest/key pair and terminal shapes | R02: temporal chronology insufficiently constrained for raw state changes; tests do not isolate all row-shape/transition conditions. Same-state terminal maintenance permits digest scrubbing, not resurrection. |
| otp_delivery_intents | Unique challenge and scoped request UUID; exact challenge tuple including transport; terminal intent transitions, claim version and bounded lease formula on update | R05: claim cancellation fence is not fully represented; no local “external invocation started” marker or lifecycle action can establish the allowed cancellation boundary. No provider callback service exists. |
| contact_verification_proofs | Challenge/contact/application composite associations, unique proof/challenge and unique consumed application, 30-minute interval, versioned available/consumed/revoked states | R01: unconsumed→revoked can fabricate consumption pair. R02: revocation chronology can precede an already recorded consumption. These are evidence-integrity gaps, not a found HTTP vulnerability. |
| verification_limit_state | Tenant/actor FKs, HMAC scope+key version+rule PK; ten ordered prefix timestamps, monotonic high-water/send time, version increments, delete restriction | Exact rolling pruning/append is service logic, not fully a DB transition invariant. Raw SQL can still clear an occupied prefix while increasing state_version; specify the trusted-writer boundary and test approved pruning. Global/IP/send spending scopes absent intentionally. |

Existing `MerchantApplication` offers `UNIQUE(id,merchant_id)` for the new application FK; its five statuses remain unchanged. Membership roles remain owner/manager/staff with active/revoked status; no synthetic “suspended membership” enum was introduced. Integer user keys align. Existing HasUlids generates lowercase values; new independent records are built with uppercase Str::ulid in fixtures. **There are no new seven-table Eloquent model definitions** in app/Models; future model creation must account for uppercase new IDs, composite associations, ms timestamps and nonstandard version fields rather than blindly applying existing HasUlids/datetime conventions.

## 5. Findings and required corrections

All findings below are **VERIFIED source observations with static behavioral deductions**. No attack SQL was executed. **PROPOSED** corrections/test cases require a new bounded authorization. Priority refers to acceptance/data integrity, not an exposed production service severity.

### R01 — Proof revocation can fabricate previously absent consumption history

**Priority: acceptance blocker.** M:195–216, particularly the state CHECK at 206 and immutability predicate at 214–215. The revoked state permits a consumption pair, but the trigger freezes that pair only when `OLD.consumed_at IS NOT NULL`. For an available proof, OLD consumption fields are NULL.

Consequently, a raw available→revoked update with state_version+1 may also set `consumed_at=verified_at+1` and `consumed_application_id` to an existing unused same-merchant application, plus valid revocation time/reason. The row satisfies the interval/null/FK/unique checks and the transition predicate does not reject the newly populated pair. This invents historical consumption at revocation instead of retaining only prior history. It contradicts SBR sections 7/8.7's “original consumption metadata retained / may retain prior consumption pair”. The current helper's revoke() does not exploit this; the required raw-SQL contract remains incomplete.

**PROPOSED correction:** only available→consumed may initialize the consumption pair. Every transition into revoked must preserve both old consumption fields exactly, including NULL→NULL. Add raw-SQL tests for available→revoked with fabricated pair, consumed→revoked changed pair, and legitimate revocation of each state; use correct state_version increments and valid supporting FKs. Existing histories/approved lifetime remain unchanged.

### R02 — Time ordering is not fully protected by schema transitions

**Priority: acceptance blocker for an exact state/evidence contract.** M:165–175 constrains terminal_at≥created_at but does not require a consumed timestamp to be ≥digest_created_at and <expires_at. A valid active row can therefore be moved by raw SQL to consumed with a terminal_at before code creation or at/after code expiry, preserving all currently required fields/version/counts. The proof insert trigger only equates verified_at to that terminal_at; it does not repair chronology. This does not prove a code match and should not be mistaken for one.

M:204–215 also permits consumed→revoked with revoked_at≥verified_at but earlier than the already recorded consumed_at. That can misorder immutable evidence. No tests target these coherent-but-false timestamp combinations.

**PROPOSED correction:** define and enforce state-specific chronology explicitly (consumption after digest issuance and before OTP expiry, exhaustion after issuance and before expiry when generated by a guess, expiry at/after expiry, revocation after any prior consumption). Where the present specification is underspecified, ratify exact predicates instead of silently inventing a different lifetime. Add boundary tests with otherwise-valid rows and expected trigger/CHECK errors. Preserve the approved 300,000/1,800,000-ms intervals.

### R03 — Constraint rejection tests can pass for unrelated reasons

**Priority: acceptance-evidence blocker.** Feature test helper `rejected()` catches any Throwable (approximately lines 63–75). `test_raw_sql_cannot_bypass_contact_immutability_checks_or_tenant_constraints`, lines 162–172, performs many updates without incrementing row_version. The general immutable trigger rejects those updates regardless of whether the targeted type/state/fingerprint CHECK is correct. Several mutations also target an immutable field, so a correct version alone would still not isolate the column CHECK.

This does not show those CHECKs are absent. It shows the reported pass count cannot establish each claimed constraint independently. The broad helper could accept a coding/fixture/table error as a successful security assertion.

**PROPOSED correction:** test immutable fields, row-shape CHECKs, unique keys and tenant FKs independently. Use valid mutable-row updates with correct CAS, or suitable inserts that do not violate unrelated guards; assert database exception/SQLite constraint category and inspect unaffected data. Exercise all nullable tuple permutations and valid control writes. Add negative test coverage proving the test would detect removal of the particular intended constraint, without weakening production rules.

### R04 — Repeated revocation is not an audit no-op; schema approval status remains stale in places

**Priority: bounded correction before final exact-contract acceptance.** SyntheticVerification::revoke updates only non-revoked proofs/active challenges but always inserts contact.proof_changed audit, even when no row changes. Repeated identical revoke therefore adds a success-looking mutation event. SBR section 7 explicitly says repeated identical revocation is a no-op. Tests revoke only once.

**PROPOSED correction:** make mutation-event creation conditional on actual affected objects; retain one transaction and distinguish any separately approved attempted-action event. Test repeat/no-row/wrong-binding calls and evidence stability. The test-only helper currently is not an authorized operator endpoint.

Documentation status hygiene: SBR-1.1's older sections still mark the already approved 30-minute proof/no-grace and other selected core choices as pending. The current user history and implementation report record approval. **Do not request the same policy approval again.** In a separately authorized correction, annotate approval provenance/effective scope while retaining genuinely pending activation/operational choices. No source specification is changed by this review.

### R05 — Delivery outcome fencing is incomplete beyond simple fixture flows

**Priority: prerequisite before promoting helpers to runtime, not a need to add a provider during inert schema preparation.** SyntheticVerification::deliver has two transactions. Its second transaction does not rerun authorized(), check current contact/context, compare an observed claim version from the first transaction, or assess lease expiry with a new clock sample; it uses the original supplied now. There is no crash/lease reconciliation path. The intent schema permits claimed→cancelled but cannot distinguish cancellation before external invocation from a post-invocation unknown outcome.

The duplicate-delivery test calls deliver again on an already nonqueued challenge; it exercises the early return, **not an authenticated provider callback handler**. It is not evidence of callback-signature/replay/fencing implementation.

**PROPOSED prerequisite:** future runtime design/test must fence observed claim version and fresh context/time, resolve unknown/late/conflicting outcomes, and define safe cancellation. Keep transport calls outside writer transactions. These paths must not be declared production-ready based on fake outcomes.

### R06 — Failure-accounting durability needs an outage test

**Priority: acceptance gap for abuse-under-failure behavior.** On a fifth/tenth wrong guess, verify() updates failure state then writes an exhaustion audit inside the same transaction. Audit INSERT failure rolls back the counted guess. The existing failure-injection test targets only correct-code otp.verified success. SBR section 9 calls for counted failed guesses to remain accounted for; no test resolves the audit-outage boundary for wrong guesses.

No successful proof on outage is demonstrated; source rolls back success. The issue is whether guessing remains bounded when accounting/audit is unhealthy, and avoiding a correct-vs-wrong response oracle. **PROPOSED:** define fail-closed verification availability during audit/accounting failure, add fifth/tenth wrong-guess audit-failure tests and prove durable limits or denial before further code evaluation. Do not casually decouple success auditing from its transaction.

### R07 — Guard ordering and activation packaging need explicit treatment

**Priority: isolation correction/activation prerequisite.** Audit migration down() first queries audit_logs to see whether downgrade is allowed, then calls rebuild(), where the environment/database guard lives. An accidental call outside testing could open/query the working connection before refusal. It does not itself bypass the guarded rebuild, but “guard before any database access” is not satisfied for this path.

The core migration accepts guarded temporary files, whereas audit reconstruction explicitly accepts only :memory:. Neither can be a working activation artifact unchanged. Guards are intentional protections, not suggestions to impersonate testing for activation.

**PROPOSED:** guard audit down before its first query; add rejection tests before connection access. Separately approve how test-only migration artifacts become a reviewed C activation package, with explicit targets/SQL/versioned artifact selection and isolated populated-file rehearsal. Never remove/disable guards or set the working environment to testing as an informal workaround.

### R08 — Evidence limits of reversal, restore, crypto and total abuse capacity

**Priority: distinguish existing synthetic evidence from activation readiness.** Migration down/reapply calls migration objects directly, compares users/audits and audit indexes, and does not exercise the actual new-migration rollback ledger/batch via migrator. Rebuild failure injection creates a replacement-table collision before copying; it does not fail after copy, drop, rename or index recreation. Snapshot/restore is a SQL reconstruction of synthetic rows, not the SQLite backup API, independent key recovery or full history comparison.

Rotation tests re-encrypt and update synthetic data, but do not cover interrupted persisted CAS rotation, old-key recovery manifest loss, compromise or fingerprint epoch rollover without resetting active failure history. Global spend/IP/actor/target-send controls and row-cardinality limits intentionally do not exist in this seven-table slice. These are not automatically blockers to safe inert tables; they are blockers to claiming operational/live readiness.

## 6. OTP, proof, audit and crypto coverage assessment

| Security area | VERIFIED source/test coverage | Not adequately established / next synthetic scenarios |
|---|---|---|
| AEAD/ULID binding | Four unit methods cover round-trip, selected malformed/tampered headers, wrong context/key/version, nonce differences, case equivalence and invalid ULIDs; shared aad() validated before uppercase | Same kid with different fixture key material; isolated byte flip of tag/noncanonical base64; explicit domain-separation negative; phone-domain full matrix; encrypted values absent from all error/debug/exception surfaces. No standalone approved masking policy/service. |
| Six-digit generation | Fake code 000123 and strict six-digit check preserve zeros | No production unbiased CSPRNG OTP generator; fixed code only a public fixture. No real send/recipient proof. |
| OTP expiry/replay | verify checks now<expires_at; consumed state/unique challenge proof; tests at expiry and after exhaustion | Raw chronology R02; expiry versus replacement/claim race; delay reaching acceptance after expiry. |
| Rolling failures | Ordered ten-slot persistence; pruning uses `> now−1,800,000`; aggregate scope actor+merchant+decrypted target excludes contact version/purpose; cooldown reserved before fake delivery | Same-target return across new contact versions, repeated success/resends not resetting failures, tenth/eleventh concurrent failure with slot assertions, epoch drain, rollback/forward clock shocks, accounting failure R06. Count/time fields do not prove correct HMAC scope by themselves. |
| Concurrency | BEGIN IMMEDIATE, CAS and selected two-worker winner/fifth-failure/resend/proof cases; no network while holding writer | Busy timeout exhaustion/retry reauthorization, concurrent revoke/owner/contact change, same request-ID race, process kill/rollback/restart, full slot preservation. Current race helper inspects counts but not every timestamp/audit invariant. |
| Proof expiry/binding | Explicit verified/expires; current owner/contact predicate; single-use consumption; foreign application FK/case tests; 30-minute equality tested | Revocation metadata R01/R02/R04, owner-change/suspend/reinstate scenarios, fresh auth_version integration, current-context changes during consumption. Helper consumes simulated proof internally; this is no real submission permission. |
| Revocation/history | Spec distinguishes current proof from historical fact; helper preserves original consumed pair on ordinary revoke; test checks original reference/time | Parent changes do not automatically call revoke; test invokes it manually. Existing RevokeMembership lacks new proof lifecycle integration. Frozen submission-evidence table/action is deliberately absent; a proof reference on a draft is not a completed immutable submitted evidence package. |
| Audit | Sixteen enum values align with rebuilt CHECK; scalar metadata allowlist/2,048 bound remains in existing model; helper inserts safe fixed metadata; event/mutation transaction on success | PDO helper bypasses Eloquent audit metadata validation; per-event reason allowlist not DB-enforced, tests use mostly empty enum metadata. Hard-coded fixture occurred_at is not operational clock proof. No object-specific FK in audit; limited request correlation deliberately accepted. No privileged SQL tamper resistance. |
| Migration/restore | Additive files, reversed table dependency order, FK RESTRICT, audit index restoration and old-value synthetic comparisons | R07/R08; multiple new-migration batches; fully populated old graph/snapshot bytes; full table/index/trigger definitions after rebuild; inbound dependency rollback; temp-file audit rehearsal; revocation replay after old backup restore. |

SQLite single-writer semantics support the chosen coordination direction, but they do not certify performance, row locks or eventual non-SQLite behavior. The inspected app default remains DEFERRED. Reusing existing actions with lockForUpdate without a unified runtime coordinator would not extend the test guarantees automatically.

The test bootstrap is a valuable boundary: synthetic key, testing environment, disabled real dotenv, memory DB, array mail and blocked Laravel HTTP requests. It is not an OS-level egress sandbox and does not protect arbitrary new raw sockets/provider libraries. Future test-only helpers/keys must remain unregistered outside their guarded testing entry points. SyntheticKeys itself checks a fixture flag rather than comprehensive deployment custody; no production secret backend exists.

## 7. Migration reversibility and preservation assessment

**VERIFIED:** original applied migration files are not edited by this task. New core down drops only the seven new tables in reverse creation order under its synthetic guard. That is destructive disposal of synthetic evidence, **not lossless rollback after real collection**. No broad application backfill is present. A missing context is not backfilled as verified.

Audit rebuild copies the original row columns, replaces only the event CHECK, preserves explicitly defined indexes/triggers from sqlite_master and checks foreign keys within its transaction. It refuses old-enum downgrade if new event rows exist. The proposed dependency check refuses inbound audit FKs instead of dropping them. These are sound source-level boundaries; they do not establish working-target compatibility without actual preflight.

Audit down refusal means a future release rollback cannot assume removing schema/code symmetrically. PHP enum and DB event acceptance/deployment order must be reviewed together. Production recovery is generally a separately reviewed forward fix or coordinated restore; no deleting/relabeling audit events to force a downgrade. Real restore needs key custody, fresh post-backup revocation/hold evidence and egress-disabled isolation.

**REPORTED:** previous correction left working SQLite/.env/frontend/applied migrations unchanged. **VERIFIED this review:** those files are included in the fresh 201-file baseline, preserved by comparison; no DB connection was opened. The current DB file hash matching the report is corroboration of byte preservation, not evidence of current rows/migration status/integrity.

## 8. B3.2-C prerequisites and owner decisions

The project defines **B3.2-C as working database activation**, not a generic invitation to build secure forms or new OTP endpoints (OD section 6; TD stage table). The separate decision rows below distinguish preparing its implementation artifacts from executing them. Both currently require approval and evidence; this review begins neither.

### 8.1 Before declaring B3.2-B final acceptance

1. **REQUIRES OWNER APPROVAL:** bounded correction task for R01/R02 proof/state chronology, R03 meaningful constraint tests, R04 revocation idempotency and R07 guard ordering. R06's precise failure-accounting acceptance behavior should be resolved in that task. No new table or OTP/lifetime threshold change is implied.
2. **PROPOSED technical evidence:** targeted raw-SQL tests with correct versions/FKs, clear error assertions, valid controls; core migration/enum/audit tests; full isolated regression. Reproduce static findings only in disposable synthetic fixtures. Record new actual results rather than copying 135/1,177.
3. **REQUIRES OWNER APPROVAL:** accept or explicitly revise any underspecified chronology predicates, stricter canonical JSON parser profile and bounded audit correlation limitation. These are contract questions, not approval to use real data.
4. **PROPOSED documentation correction after authorization:** reconcile stale proposal labels with existing approvals. Do not rewrite historical test runs or reset approved NIN/OTP/proof/ULID policy.

### 8.2 Before B3.2-C implementation and working activation

| Decision/evidence | Required owner action / technical prerequisite |
|---|---|
| Corrected B acceptance | Explicit review/disposition of findings and final acceptance evidence. Unconditional acceptance cannot rest solely on present reported passes. |
| Exact C scope/artifacts | Approve a concrete migration list/SQL/target connection and deployment order; define how synthetic-only guards/paths remain safe without masquerading as testing. No endpoints, real identity records or provider work bundled. |
| Target preflight | Separately authorize fresh effective config/SQLite version, migration ledger, schema/index/trigger/FK inventory and dependency review. Determine actual pending files, no reset/seed/import or guessed schema state. |
| Preservation and write quiescence | Approve maintenance/no-active-writes window, consistency/checkpoints, exact protected inventory and rollback/stop criteria. Avoid counting a hash of a live changing DB as a consistent backup. |
| Backup and recovery rehearsal | Approve private destination, access/custodian, consistent backup, restore-to-new-file verification and recovery procedure. No working record copied in this review. No in-place restore improvisation. |
| Populated target-shaped isolated rehearsal | After authorization, exercise concrete migration ledger up/rollback refusal/reapply, old encrypted snapshot and graph preservation, dependency handling and late rebuild failures. Any real-data clone requires separate authorization; synthetic target-shaped rehearsal first. |
| Key safeguards for an inert C | Explicitly keep new sensitive tables empty/no capture; no production key provisioning merely to create opaque columns. Preserve APP_KEY for existing encrypted evidence. Named key custodians/custody/recovery must be ready before any real sensitive write, not fabricated to justify inert DDL. |
| Recovery/deployment decision | Approve enum/CHECK ordering, downgrade limits, forward-fix/restore responsibilities, stop-on-dependency behavior and final reviewed artifact fingerprints. |
| Execution boundary | Exact working migration command/target and explicit owner execution authorization after all reviewable evidence. Approval of this report is not that command's permission. |

External SMS provider and legal real-NIN requirements do **not automatically block corrected empty-table preparation/activation** if its scope is explicitly inert and safely reviewed. They do block enabling corresponding real processing. The present NO-GO is independently supported by schema/evidence findings, unready activation artifacts and absent C authorization.

### 8.3 Remaining future product/operational decisions

All below are **REQUIRES OWNER APPROVAL**, with qualified technical/provider/legal evidence where relevant. They are not all prerequisites to inert C, and none is claimed configured:

- Production domain/hosting/DB engine and capacity, trusted-clock health/drift response, retention/deletion/holds, incident response and audit access/tamper-resistant operations.
- Named encryption/recovery custodians, actual secret-delivery/custody mechanism, active/retired/compromised key policy, rotation schedule, fingerprint epoch drain, independent key backup, recovery RPO/RTO and lost-key procedure. No invented KMS.
- NIN purpose-specific official syntax/issuer evidence; lawful processing, notices/declaration wording, representative claims, legal retention/rights/processors/transfers and explicit real-data activation. No identity approval from syntax or SMS.
- Real phone normalization/TZ treatment, contact-change reauthentication, owner transfer/support recovery, and shared revocation integration. Current proof is not login MFA/financial authorization.
- Pending-review freshness/changed-contact evidence path and any fresh proof before contact-dependent activation; the 30-minute submission readiness/no-grace/single-use policy itself is already approved.
- Actual SMS provider/contract/security, accepted/unknown billing and idempotency, timeout/lease value (30 seconds remains proposed operational timing), callback authentication/fencing and outage recovery.
- Additional scoped sends 3/15 minutes and 5/hour, IP 20/hour, proposed actor/target 10 sends/hour, global spend/provider cost/throughput ceilings, cross-tenant privacy/cardinality controls and any additional bounded storage amendment. These remain proposals, not enforced approved limits.
- Future submission frozen identity/contact/email/version evidence, Chief review privileges/strong reauthentication/MFA/conflict rules, and financial eligibility/activation under their own stages. Optional broad profile/documents/payment methods remain nonblanket gates; no hidden extra required application field.
- R1.0–R1.3 financial/document/dashboard/team work remains separate. D13-1 product approval is already recorded; legal exceptions and on-demand document delivery details remain outstanding for that future scope, not blockers to these seven inert tables.

## 9. GO / NO-GO decisions

| Area | Recommendation | Evidence / remaining boundary |
|---|---|---|
| Accept ULID compatibility correction and useful bounded source capabilities | **GO for acknowledging that subset** | VERIFIED casing/FK/AAD source separation; REPORTED dedicated tests. No need to reopen B32-ID-CASE-1. |
| Final unconditional B3.2-B synthetic acceptance | **NO-GO pending corrections/evidence** | R01–R04 prevent full proof-history/state/constraint acceptance; R06/R07 need bounded disposition. Existing test success remains valid reported evidence, not exhaustive proof. |
| B3.2-C implementation/artifact preparation | **NO-GO to start now** | Corrected B acceptance, precise activation packaging and explicit owner authorization missing. **PROPOSED GO for owner review of this report only.** |
| Working database activation | **NO-GO** | No target preflight/consistent backup/restore-to-new-file rehearsal/approved commands; current migrations intentionally testing-only; no execution authorization. |
| Production/real NIN/live SMS/Selcom/payment service | **NO-GO** | Unimplemented/unauthorized; production custody/legal/provider/security/load gates outstanding. Synthetic results grant no live capability. |

No automatic progression, acceptance-by-silence or production readiness inference. Review-only planning does not implement C.

## 10. Preservation verification

Before the only write, **201 unique pre-existing files** were inventoried and SHA-256 hashed in memory: tracked/nonignored-untracked workspace files plus explicit backend .env variants and `database/*.sqlite*`. This includes the prior draft implementation/specification/report exactly as found. No extra inventory artifact was written. Ignored vendor/cache/recovery directories are not exhaustively covered by that inventory.

Current working SQLite SHA-256, read as a file only:

`214AE83A31F53E80D3068D1AEB1366008BB5ACCB2DF212DD2C534AE4121B6D8F`

.env content/key values were not printed/read into the report. Full-file hash comparison covers secret preservation without exposing them. Initial status already contained the prior four tracked modifications and untracked B3.2 work; those remain prior work, not changes by this review.

**VERIFIED post-write:** all **201 protected baseline files unchanged**, **zero missing**. The sole new nonignored inventory entry is this requested report; the existing tracked/untracked changes remain as found. HEAD unchanged; `git diff --check` returned no errors. No source/tests/specifications/existing reports/migrations/frontend/working DB or Git history changed. The final edit only records these verified results in this new report.

No tests, SQL/migrations, runtime boot, formatting, dependency installation, real data capture, messages, accounts, commit/push or deployment performed. Read-only official documentation browsing did not interact with provider accounts.

## 11. Recommended next action — Pendekezo

**PROPOSED:** review this report and explicitly authorize a narrowly bounded B3.2-B integrity/test correction task. Close the source-derived proof/state holes, make negative tests discriminate the intended constraints, address isolation/audit-accounting edge cases, then rerun isolated acceptance/regressions and obtain owner acceptance. After that, request a separately bounded C preflight/activation package, keeping real collection/delivery excluded.

**REQUIRES OWNER APPROVAL:** those corrections and any later-stage implementation/execution. Hakuna code iliyofixiwa kimyakimya, hakuna test iliyodaiwa kuendeshwa upya, na hakuna working activation. **STOP — wait for owner review.** XLipa remains a web-based payment gateway website, not a mobile application.
