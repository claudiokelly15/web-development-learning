# XLipa — B3.2 Technical Decisions and Schema Approval Package

**TD-1.0 — 6 November 2026, Africa/Nairobi. DOCUMENTATION ONLY.**

## 1. Executive summary

The owner has approved initial NIN protection, exact email/phone binding and five OTP policy values. This record distinguishes those approvals from unimplemented controls and unresolved choices. It develops OD-1.0 B-SYN-1 into a bounded synthetic schema approval package. **The filename does not constitute schema execution approval. No migrations, code, keys or tests are created/run.**

Labels: **VERIFIED** = actual read-only source/Git evidence this task; **OWNER-APPROVED** = explicit policy in the current instruction or identified prior decision; **PROPOSED** = technical design pending review; **REQUIRES APPROVAL** = a separate decision/execution gate; **NOT VERIFIED** = missing implementation/official/professional evidence. All new table designs are PROPOSED, not existing schema.

Recommendation: **GO for owner review of this package; NO-GO for executing B3.2-B now.** Before safe concrete implementation authorization, finalize the schema-affecting crypto envelope, state/binding, rate-window storage, audit and bounded support-code decisions below. External live-provider/legal prerequisites can remain explicit blockers to real use without becoming fabricated prerequisites to inert synthetic schema preparation.

## 2. Existing architecture evidence

Canonical project `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`; Laravel `backend/`, authoritative public frontend `frontend/`, Git root parent `WEB-DEVELOPMENT`. **VERIFIED:** branch main, HEAD `7c3e4743384e0ca0acd7b029c944569c84304541`; initial status has eight pre-existing untracked documents (B3.2 planning, v2.0, secure review, R1.0–R1.3, OD-1.0), no tracked changes. Baseline covers 187 unique tracked/untracked existing files plus explicitly included `.env`/working SQLite within that count. These pre-existing documents are not this task's additions.

Reviewed [B3.2-A v2.0](XLIPA-PHASE-3-B3-2-A-FINAL-SPECIFICATION-2026-11-06.md), [Secure Architecture Review](XLIPA-B3-2-SECURE-ARCHITECTURE-REVIEW-2026-11-06.md), [OD-1.0](XLIPA-OWNER-DECISIONS-AND-B3-2-READINESS-2026-11-06.md), existing migrations, User/Merchant/MerchantApplication/AuditLog, policies, middleware, routes, enums and test inventory. Actual source is implementation truth:

| Source | VERIFIED fact and preservation implication |
|---|---|
| User; routes/web.php | MustVerifyEmail, normalized identity email, signed verification route, throttles and verified gate; no application-phone proof fields/module in inspected source |
| EnsureActiveAuthentication | Reloads user, checks active account/session auth_version, invalidates stale authentication |
| EnsureAuthenticationReady | Existing schema checks plus intentional local/testing and log/array mail gate; do not weaken it for new modules |
| Merchant::forMember; ResolveMerchantContext | Fresh active-user/member scoping from route context; foreign tenant inaccessible |
| MerchantPolicy; MerchantApplicationPolicy | Summary permission; current-attempt detail for active owner/manager; no Chief blanket bypass or new write permissions |
| Nine migration files | Existing user integer identities; merchant/application/audit ULIDs; foundation migrations explicitly guard SQLite. File inventory is not a fresh working-DB migration-status query |
| create_merchant_applications | Five existing statuses, unique merchant+attempt, merchant+current_slot, id+merchant; INSERT/UPDATE triggers enforce positive integer attempt/profile versions |
| MerchantApplication | Encrypted profile_snapshot/internal notes via existing casts, hidden sensitive fields, identity/submitted-evidence mutation guards. These existing casts do not meet a new separate-key NIN design automatically |
| create_audit_logs; AuditEvent | Eight existing event values; nullable actor/target/merchant/application, UUID request_id, metadata and occurred_at; composite application+merchant FK; merchant/time, actor/time, event/time indexes |
| AuditLog | Scalar metadata keys limited to from, to, role, previous_role, profile_version, reason_code; encoded bound 2048 bytes; append-only model events, not tamper-proof privileged SQL storage |

Existing audit values: merchant.created, membership.revoked, application.submitted, application.reviewed, platform_role.granted, platform_role.revoked, account.status_changed, merchant.status_changed. Do not remove/rename them. No new identity/contact/OTP structures are claimed present. No live authentication/DB inspection/tests executed here; historical 108 tests/847 assertions and frontend 31 checks are not new results.

Preserve TZ initial applications, four minimum values plus verified contacts/security, optional profile/documents/methods generally nonblocking, separate syntax/identity/representation/internal-approval/service facts, B3.1–B3.5 and F0–F5. D13-1 product approval in OD-1.0 remains valid; its legal/delivery exceptions are unresolved, not reset to pending product ratification. No future production domain selected; local `http://xlipa.test` unchanged.

## 3. Newly approved owner policies and reconciliation

**OWNER-APPROVED by the current instruction:**

| Policy | Approved initial rule | Approval does not establish |
|---|---|---|
| NIN protection | Encrypted at rest using reviewed authenticated encryption; cryptographic key management separate from APP_KEY; preserve leading zeros, masked-only where justified, no unrestricted reveal | Selected library/KMS/envelope, real custody/recovery or completed encryption tests |
| NIN exposure | Never plaintext in ordinary profile/logs/URLs/exports/audit | A new UI, decrypt endpoint, migration/backfill or legal processing authority |
| NIN meaning | Syntax is not official identity verification; no invented NIDA API/checksum | Exact canonical format/checksum or authorized independent identity match |
| Email | Submitting owner's current verified identity email; fresh exact binding at submission; business email cannot inherit proof | New email-change action or implemented submission/binding table |
| Phone | Exact application phone ownership proof bound to merchant/actor/contact version/purpose; phone change invalidates dependent proof | Settlement authorization, login MFA or cross-merchant proof transfer |
| OTP | Six decimal CSPRNG digits; five-minute validity; five failed guesses/challenge; minimum 60-second resend cooldown; aggregate ten failed attempts within 30 minutes for actor+merchant+target | Enforced counters, approved window algorithm, other send/IP/global limits or proof lifetime |
| OTP integrity | Preserve zeros, purpose-bound single-use atomic consumption, never log plaintext OTP | Real delivery, provider readiness or acceptability of simulated production proof |
| Delivery | No live SMS without separate provider readiness; invent no endpoint/credential/contract; simulated proofs/test transports never production eligible | Provider selection/contract/activation permission |
| Real NIN | Collection prohibited until legal/privacy review, tested encryption/key controls, sufficiently verified purpose-specific validation, operations/recovery and explicit real-data activation | Collection permission from policy/specification approval |
| Schema safety | Additive migrations rehearsed only in isolated synthetic DB; preserve existing records; B3.2-B never changes working SQLite | Permission to generate/run migrations now or advance to C |

This record updates the approval status of OD-1.0 S03/S06–S12 and the OTP decision sheet **only to the extent explicitly approved above**. Historical proposed-status statements remain unchanged. Six digits/five minutes/five failures/60 seconds/ten failures per 30 minutes no longer need the same product approval again; enforcement semantics and unapproved thresholds still do. NIN mechanism/key custody, exact syntax and privacy obligations remain unresolved. Masking is not general permission to reveal the last four to every member.

## 4. Pending decisions and evidence ownership

Decision classes: **O** owner/product authority; **T** technical review/rehearsal; **P** official issuer/provider evidence; **L** qualified legal/privacy review. Proposed choices do not become approvals by inclusion.

| Pending issue | Authority/evidence | Impact / explicit blocker |
|---|---|---|
| Exact NIN canonical syntax/separators/checksum | P/T/L; O approves purpose-specific product validator | Prior 20-digit proposal remains NOT VERIFIED as full official contract. No real collection; opaque encrypted TEXT schema can be reviewed without guessing plaintext format |
| AEAD library/service and envelope layout | T/O; P if managed service | **Schema blocker:** field/encoding/bounds/context/key-reference format must be frozen before corresponding migrations/support casts |
| Custodians, rotation and disaster recovery | O/T, L/P as relevant | Identify responsible operators/key permissions and tested recovery plan; no live use until actual readiness. Synthetic interface/rehearsal scope needs explicit approval |
| Aggregate send and wider abuse limits | O/T/P for provider capacity/cost | Previous 3/15min, 5/hour and IP 20/hour remain PROPOSED; target-wide/actor/IP/global values not approved |
| Exact 30-minute window/counter algorithm | T/O | **Schema blocker:** strict rolling events/buckets versus other semantics and atomic reservation/locking must be selected; no permissive fixed-counter approximation |
| Proof lifetime/reverification | O/T/L/P where obligations apply | No infinite validity default; no live submission until decided |
| Contact-change step-up/recovery/outage | O/T/L as relevant | Approved invalidation is settled; exact recency/factor/recovery path and existing-proof outage gating still open |
| SMS contracts/delivery semantics | P/O/T/L | No real send/provider proof; ambiguity/idempotency cannot be invented |
| Legal basis/notices/retention/transfers | L/O; T implementation | Blocks real NIN/contact processing until applicable readiness; no invented retention years or compliance claim |
| Document legal exceptions/delivery | L/O/P contract as relevant | D13-1 product policy remains approved; request-advice email versus retrieval and mandatory notices not decided here |
| Production DB engine | T/O | SQLite synthetic constraints/concurrency cannot certify eventual production engine; port/load/lock tests separately gated |
| State/value sets, key bounds, audit extension and support-code scope | T/O | **Schema blockers:** approve exact sets/dependencies and safe limited enum/model changes before generation |

## 5. Proposed B3.2-B table and constraint design

**PROPOSED candidate B-SYN-1T**, refining OD-1.0's seven-table scope. No tables/columns below exist merely because named here. No optional profile fields or financial/team/dashboard schema. Types: **U** ULID stored compatibly with existing ULID keys; **I** existing integer user-ID-compatible FK; **V(n)** bounded string with reviewed runtime/DB enforcement; **TXT** TEXT/envelope; **TS** UTC timestamp; **INT+** positive integer with actual SQLite typeof/value constraint; **INT0** nonnegative integer. Nullable explicitly stated; otherwise required. Strings/bounds are proposed contracts, not assertions that SQLite VARCHAR enforces length. All key references are identifiers, never secret key material.

Common defaults: no guessed identity/contact/proof backfill, server actor/time, RESTRICT deletion for evidence, merchant and user FKs, composite child tuples rather than independent FKs permitting tenant mismatch. Candidate state/claim strings below require ratification; exact SQL is not generated. Immutable logical content differs from mutable envelope revision/revocation/state version. Requiredness/allowed combinations use explicit CHECK/NOT NULL; SQLite CHECK passing NULL must not accidentally allow partial-null authority tuples.

### 5.1 merchant_identity_records

**Purpose:** versioned declared sensitive identity/representation record, not identity approval.

Columns: id U PK; merchant_id U; version INT+; subject_claim V32 (proposed unknown/individual_owner/authorized_representative); nin_ciphertext TXT; encryption_key_ref V128; envelope_version INT+; envelope_revision INT+ proposed for crypto-only rotation; syntax_policy_version V50 nullable; syntax_checked_at TS nullable; declaration_version V50; notice_version V50; declared_by_user_id I; declared_at TS; superseded_at/retention_due_at TS nullable. Optional equality fingerprint and its key ID are **excluded by default** until a justified duplicate-detection policy approves them; no global NIN uniqueness.

Constraints: merchant/user FKs; UNIQUE(id,merchant_id), UNIQUE(merchant_id,version); nonempty ciphertext/key refs, allowed subject/version/paired syntax metadata and ordered supersession times. Encoding/field lengths/context format remain blocked on crypto review; do not create a plaintext NIN column or fixed unverified canonical-length DB check. Index merchant+version (unique index suffices); justified retention/supersession lookup only. No NIN/name full-text index.

Versioning/security: declared content/version immutable after reference; key rewrap changes envelope revision, not declared identity or syntax/verification fact. Mask inside authorized sensitive service only. Audit future supplied/superseded/crypto-operation reason codes without NIN/ciphertext. Dependency: existing merchants/users. Rollback drops new evidence if used; prohibit blind down outside synthetic fixture. Tests: duplicate merchant/version, foreign merchant pointer, invalid versions/claim/pairs, ciphertext swap/wrong key/tamper/rotation; no full reveal.

### 5.2 merchant_contact_versions

**Purpose:** exact versioned application-phone target bound to actor/merchant/purpose.

Columns: id U PK, merchant_id U, actor_user_id I, purpose V50, version INT+, phone_ciphertext TXT, phone_key_ref V128, target_fingerprint V128, fingerprint_key_ref V128, created_at TS, retired_at TS nullable. Initial purpose candidate `application_phone`; no settlement/MFA/notification purpose automatically enabled. Fingerprint encoding/algorithm/key-reference lengths require crypto/window review; mandatory private target fingerprint here supports abuse scopes, unlike optional NIN equality search.

Constraints: FK merchant/user; UNIQUE(id,merchant_id,actor_user_id,purpose); UNIQUE(merchant_id,actor_user_id,purpose,version); nonempty encrypted/fingerprint fields, allowed purpose, retirement≥creation. Same number across merchants/users is not globally unique. Index tenant/actor/purpose via unique key; private target lookup only with approved scope/key version. Changing target creates new version; retirement mutable, not rewriting content. Fingerprint-key rotation must not reset abuse history or split aggregate scope unnoticed; approved dual-key/mapping transition required.

Audit future contact created/retired codes, no raw phone/fingerprint. Depends existing merchants/users and approved encryption/fingerprint interface. Down loses encrypted contacts; referenced proof/history prevents unsafe deletion. Tests: actor/merchant/purpose tuple mismatch, duplicate version, invalid retirement, preserved zeros/target privacy and rotation continuity.

### 5.3 merchant_onboarding_contexts

**Purpose:** merchant current identity/contact pointers and parent CAS sidecar; avoids adding cyclic pointer columns to existing merchants.

Columns: merchant_id U PK/FK; context_version INT+; current_identity_id U nullable; current_contact_id U nullable; contact_actor_user_id I nullable; contact_purpose V50 nullable; updated_at TS. Composite FKs (current_identity_id,merchant_id)→identity(id,merchant_id) and (current_contact_id,merchant_id,contact_actor_user_id,contact_purpose)→contact(id,merchant_id,actor_user_id,purpose). Explicit all-null or all-nonnull contact triple; identity independently nullable. No default inferred identity/contact row.

Checks: integer positive context version and tuple null pairing; FK ensures actor binding, no separate actor ID can override contact association. PK and FK indexes sufficient unless approved query needs more. Every future change compares expected context version and checks exactly one affected row in shared serialized transaction; fresh role/account/current-draft checks still required. Audit bounded reason/context correlation using existing compatible fields; no pointer dump to generic metadata. Depends identity/contact tables. Drop sidecar before parents in synthetic down. Tests: all partial-null permutations, foreign identity/contact/actor/purpose, stale CAS, absent sidecar not-ready rather than automatic repair.

### 5.4 phone_otp_challenges

**Purpose:** finite single-use proof challenge persistence, no executable verification endpoint.

Columns: id U PK; merchant_id/contact_id U; actor_user_id I; purpose V50; active_slot nullable integer1; state V32; state_version INT+; code_digest V128 nullable; digest_key_ref V128 nullable; issued_at/expires_at TS nullable; failed_attempts INT0 default0; created_at TS; consumed_at/revoked_at TS nullable. Exact digest encoding/bounds and state timestamps remain ratification blockers.

Proposed states: queued, active, consumed, expired, exhausted, revoked, delivery_failed, delivery_unknown. Queued/active/delivery_unknown retain slot1 until a safe resolved replacement; terminal states clear slot. Proposed active requires all digest/key/issued/expiry fields, expiry=issued+approved five minutes under reviewed timestamp precision; at most five failed attempts per challenge, consumed requires consumed_at and cannot simultaneously be revoked. Queue must not create an expiry that is later extended by delivery delay. Exact clock/state CHECK combinations need a concrete truth table before migration approval. State transition history rules require later action/CAS logic or expressly approved narrow triggers; CHECK alone does not prevent valid-looking replay updates.

Constraints: FK exact contact tuple; UNIQUE(id,merchant_id,contact_id,actor_user_id,purpose); UNIQUE(merchant_id,contact_id,actor_user_id,purpose,active_slot); active_slot NULL/1 and state consistency. One active challenge per contact-version tuple does not replace wider actor+merchant+target counter across versions/resends. Index state/expiry and scoped creation lookup. No code/phone in queue/logs; keyed bound digest only, separate secret. Audit future request/consumed/revoked bounded codes; noisy failures require approved logging limits. Depends contact table. Down after proof/intents; expiry cleanup must preserve referenced history/digest scrubbing policy. Tests: six-digit leading zero represented only in synthetic transient input, five-attempt cap, active duplicates, invalid state/null pairs, expiry/consume race, old challenge cannot revive.

### 5.5 otp_delivery_intents

**Purpose:** inert challenge-bound delivery-intent metadata, not a general notification outbox or SMS worker.

Columns: id U PK; merchant_id/challenge_id/contact_id U; actor_user_id I; purpose V50; state V32; claim_version INT+; transport_mode V32 (proposed simulated/live provenance); requested_at TS; claimed_at/completed_at TS nullable; failure_code V50 nullable; provider_reference_ciphertext TXT nullable with provider_reference_key_ref V128 nullable if retained. No plaintext OTP, phone, SMS body, endpoint or credentials.

Constraints: UNIQUE(challenge_id); exact FK to challenge(id,merchant,contact,actor,purpose); approved states queued/claimed/accepted/failed/unknown/cancelled with timestamp/error consistency and paired encrypted-reference key; exact state set/truth table pending. Index state/requested_at. Claimed worker fencing proposed; stale claim cannot confirm old challenge. Provider acknowledgement is never phone ownership. Audit safe result/error codes only. Depends challenge. Rollback cannot cancel already sent SMS in any future live system; synthetic down only. Tests: foreign tuple/duplicate intent, stale claim, ambiguous outcome/finalization ordering, simulated mode no live eligible proof. No sending performed even in this schema slice.

### 5.6 contact_verification_proofs

**Purpose:** immutable successful challenge-consumption evidence bound to exact application target, with audited revocation.

Columns: id U PK; merchant_id/contact_id/challenge_id U; actor_user_id I; purpose V50; verified_at TS; channel V32; provenance V32; policy_version V50; revoked_at TS nullable; reason_code V50 nullable. Proof expiry column versus policy-derived validity remains **blocked on proof-lifetime decision**; never silently choose infinite validity or a guessed duration.

Constraints: UNIQUE(challenge_id); UNIQUE(id,merchant_id,actor_user_id); exact composite FK challenge tuple and contact tuple; approved channel/provenance values and revocation pair/time checks. Proposed provenance simulated/live_verified; schema label is not sufficient evidence of true live origin. Later only authorized adapter/consume service can create eligible live proof; general request/mass assignment/direct client cannot set it. FK cannot alone prove challenge consumed successfully: approved future transaction protocol/state checks must do so. Add no independent identity-verified flag.

Index merchant/actor/purpose/verified_at and current revocation lookup if justified. Audit future phone_verified/proof_revoked codes, no target/digest. Depends challenge/contact; challenge digest may be scrubbed after terminal retention policy while minimal referenced IDs remain; do not delete referenced challenge blindly. Synthetic down before challenge/contact. Tests: duplicate consume proof, cross actor/tenant/purpose, invalid revocation, immutable target binding, fake provenance exclusion in narrow test predicate, lifetime/expiry once policy approved.

### 5.7 verification_limit_state

**Purpose:** aggregate abuse state independent of individual challenge, so resends/contact versions do not reset approved ten-failures/30min scope.

Proposed columns: scope_key V128, scope_key_version V50, policy_rule V50 composite PK (all three); state_version INT+; updated_at TS; blocked_until TS nullable; counter/reservation/window payload **not finalized**. Private HMAC scope binds actor+merchant+normalized target with a stable approved key transition strategy; no raw IP/phone/NIN in keys. Actor/target/IP/global scopes have distinct rule IDs, not invented approved limits. Reject negative/overflow counters and invalid windows; indexes rule/blocked_until/updated_at for bounded maintenance only.

**Explicit blocker:** choose exact algorithm/storage before creating this table. A scalar fixed-window counter is not assumed to enforce a strict rolling 30-minute rule. Candidate bounded timestamp events within one row require atomic CAS/size/retention semantics; a separate event/bucket table would expand the seven-table package and needs explicit approval. Tenant-linked scopes must have validated tenant association (e.g. nullable merchant FK with rule-specific nullability); global/IP/target-wide scopes must not be modelled with fake merchant IDs. Exact scope columns/CHECK/nullability remain pending with algorithm. Do not expose global cross-merchant correlation to merchant reads.

Audit bucketed denial/control events, not every raw target/IP value; review retention/privacy. Depends selected scope/key policy and merchant reference where appropriate; no application FK needed. Recovery/down must not clear live attack counters to bypass limits; synthetic disposal only. Tests: rolling boundary bursts, atomic counter increments, no lost failures after resend/key rotation, simultaneous fifth/tenth failure behavior, rule-scope isolation, no indefinite attacker-controlled lockout, size/overflow/cleanup. No algorithm is implemented here.

### 5.8 Audit compatibility and migration ordering

Proposed minimal future event candidates for review: identity.recorded, identity.superseded, contact.created, contact.retired, otp.requested, otp.verified, otp.revoked, contact.proof_revoked. Exact names/enumeration and need for bounded failure/security events require approval; none are existing AuditEvent values. Add only approved core events, not all future KYC/payment/team events. Key-management audit may need a separate controlled operator audit mechanism; do not add actor/key fields to existing generic metadata silently.

Existing audit schema has no identity/contact/challenge object FK. For the bounded package, use existing merchant/actor/request_id plus bounded reason_code correlation; do not stuff identifiers or sensitive data into unapproved keys. If exact resource-level audit linkage is required, approve an explicit typed extension before generation instead of fake application IDs or model-only arbitrary JSON. Preserve existing scalar allowlist/2048 bound, eight original values, UUID request IDs, composite application/merchant FK, indexes and all rows. CHECK/enum rebuild SQL and event model casts must agree. Success audit atomicity belongs later action implementation; synthetic fixtures test constraints/compatibility only.

Proposed dependency order: identity + contact → context → challenges → delivery intents + proofs → finalized abuse-state table; audit extension at a reviewed point preserving all existing dependencies. Reverse creation order for synthetic down, with context dropped before identity/contact, proof/intent before challenge. Exact migration grouping/filenames not generated. Rebuild audit into a new compatible table only under reviewed synthetic transaction/failure rehearsal; no blanket constraints-off workaround. Existing foundation files/triggers unchanged. New rows/events make down destructive/incompatible after use; production/working rollback needs separate forward-fix/recovery/retention review, never automatic audit deletion.

## 6. Cryptographic security decision matrix

All mechanisms below require approval and evidence; encryption and separate APP_KEY-independent management are already owner-approved requirements.

| Decision | Proposed design and approval/evidence | Migration relevance / failure boundary |
|---|---|---|
| Authenticated mechanism | Vetted maintained AEAD implementation or managed encryption service; review supported library API/nonce/tag behavior, no custom algorithm | Freeze envelope encoding/bounds before ciphertext schema/casts; no unverified algorithm chosen here |
| Dedicated key vs managed service | Separate controlled KEK/key service and per-record data-key envelope where justified; independent encryption/OTP/fingerprint key domains | Approve key-reference size, wrapped-key/nonce/tag storage (inside envelope or typed fields); no real key provisioning |
| Identifiers/versioning | Nonsecret key ID, envelope format/revision, content version distinct | Stable encoding/type and length required for migrations; ciphertext rewrap does not establish new identity fact |
| Context binding | Authenticate merchant+record+purpose+content version via supported AAD/context or reviewed authenticated payload binding | Canonical encoding and service validation must resist swapping ciphertext/IDs; fail closed on mismatch |
| Rotation | Controlled wrapping-key rewrap; data-key re-encryption only with scoped authorization/checkpoints | Envelope revision and interrupted rotation behavior approved; no logical identity-version overwrite |
| Recovery | Named custodians/dual control as approved; independent protected recoverable key backups | Synthetic known-key rotation/restore tests before real use; loss cannot be fixed by APP_KEY replacement |
| Backup/restore | Consistent encrypted DB/artifact backup separate from controlled key recovery; replay holds/deletion manifests | No working backup/restore in this task; synthetic replay must preserve identity/proof/history without secrets in outputs |
| Tamper/wrong key | Authenticate before decrypt/use, reject unknown/revoked key or malformed envelope | Generic errors, no plaintext fallback, no syntax-valid from failed decrypt |
| Access | Fresh scoped actor/purpose policies; separate encrypt/decrypt/key-admin permissions, masked output | No general decrypt accessor/generic model serialization or full-NIN reveal; audit safe operator actions |
| Secret storage | Approved protected key service/environment interface, not repository/DB/logs/jobs | Synthetic key injection allowed only after explicit test support-code scope; never production secrets or APP_KEY reuse |
| Service unavailable | Required secret/schema/context readiness denies sensitive operation | No placeholder ciphertext/forced proof or data in ordinary columns; B3.1 read-only functionality preserved |

**Before migration implementation:** finalize envelope field contract/key ID bounds/versioning/context representation, fingerprint encoding/key transition, proof lifetime representation and abuse-store shape. Full production service procurement need not precede inert TEXT schema if a concrete portable contract is approved, but a fake placeholder contract cannot be called reviewed encryption. Custody/rotation/recovery operational details must be sufficiently specified for safe tests and resolved before real capture. This task selects no library, installs no dependency and generates no keys.

## 7. OTP abuse-protection matrix

| Control | Approval status | Enforcement decision/test still needed |
|---|---|---|
| Six digits, CSPRNG, preserve zero | OWNER-APPROVED | Reviewed generator/representation/transient memory, no plaintext log/storage |
| Five-minute validity | OWNER-APPROVED | Exact issuance clock/precision and comparison; proposed valid only before expires_at, no delayed-job extension |
| Five failed attempts/challenge | OWNER-APPROVED | Atomic fail increment/exhaustion before another guess; no decrement after resend |
| ≥60-second resend cooldown | OWNER-APPROVED | Scope/timestamp origin, failed/ambiguous send quota reservation, boundary policy |
| Ten failures/30min actor+merchant+target | OWNER-APPROVED | Exact rolling-window/storage/clock/atomic serialization; include old challenges/contact versions for same target |
| Three sends/15min, five/hour | PROPOSED only from prior review | Owner approval not supplied; choose limits and shared-scope semantics |
| IP 20 send requests/hour | PROPOSED only | Proxy/client-IP trust, shared-network fairness, privacy/threshold approval |
| Actor-wide, target-wide, global | REQUIRES APPROVAL | Numeric limits, cross-merchant privacy and anti-rotation/target spraying controls |
| Spend/budget/provider limits | REQUIRES APPROVAL + provider evidence | Actual cost units/quotas/reserved capacity/alert/circuit breaker and escalation; no rates invented |
| Provider failure/timeout | Approved no live send without readiness; semantics NOT VERIFIED | Rejection vs ambiguous status; no blind duplicate external send, no proof from acknowledgement; charge/quota handling approval |
| Atomic consume/replay | OWNER-APPROVED requirement | One transaction checks fresh tenant/actor/purpose/current target, expiry/attempt/global limits; conditional state change+proof/audit one winner |
| Temporary block/recovery | PROPOSED | Bounded cooldown/support process, avoid permanent attacker lockout; never override verified or clear working counters for convenience |
| Proof lifetime/contact recovery | REQUIRES APPROVAL | Exact expiry/reverification/risk/step-up and provider-outage gate; phone change invalidation already approved |

Server UTC clock must be trusted/monitored; rollback/jump behavior and window edge precision need ratification. Contending operations use an agreed lock order/CAS and bounded whole-transaction retry; SQLite lockForUpdate alone insufficient. Failure state/counters must persist even when no success proof is written. Do not roll back failed-attempt increments with a rejected validation exception. Success and the last competing wrong guess require an explicit serialization rule, preventing consumption after exhaustion. Resend supersedes safely but preserves aggregate failure limits. Digest comparisons are constant-time and keyed to challenge/merchant/actor/target version/purpose; exact HMAC choice/key policy remains technical review, not plaintext or unkeyed low-entropy hash.

## 8. Future isolated migration/test acceptance plan

**No tests or migrations run in this task.** Future execution requires separate explicit permission and a reviewed isolation guard before schema access. Synthetic fixture data only; no working DB copy containing real personal information as a shortcut.

1. Assert testing environment, configured/effective PDO target, empty URL override and no cached production config. Accept only disposable `:memory:` or named random temporary test path; reject canonical SQLite and paths outside approved fixture root. FK enforcement checked per connection before writes; migrations never run merely because this document is approved.
2. Build fresh old-schema synthetic fixture with users, merchants, memberships, current/historical applications, existing eight audit types, encrypted sample snapshots and nullable edge cases. Fingerprint original IDs/values/indexes/FKs/triggers. Up → assert preservation/additions → down → old-schema equality where compatible → reapply. Test populated new tables and intentional down refusal/incompatibility separately; no deletion to fake compatibility.
3. Exercise every exact composite FK/UNIQUE/CHECK/null permutation: mismatched merchant/actor/purpose/current pointers, absent parent, duplicate active challenge/version/proof/intent, invalid types/negative/noninteger versions, missing required state/key/time fields. SQLite unsigned/VARCHAR declarations alone do not pass acceptance.
4. Audit CHECK rebuild: old rows/casts/indexes/composite FK and metadata exactly preserved, approved new events accepted, unknown values rejected; inject failure mid-copy/DDL and prove rollback/FK/integrity. Inspect actual synthetic sqlite_master/PRAGMA structure, not only migration text. Never permanently disable constraints.
5. With approved test-only crypto interface and fixed synthetic fixtures, tamper ciphertext/tag/context, wrong/unknown/revoked key, unavailable service; assert safe failure/no leakage. Test rotation/resume and backup-to-new-synthetic-file restore/decrypt with isolated test keys, no printed NIN/OTP/keys. No keys generated in this task.
6. Synthetic persistence/CAS concurrency fixtures: simultaneous active creation, failure-counter updates, fifth/tenth failure, expiry boundary, resend and stale delivery claim, double consume/proof. Exact algorithm tests deferred until algorithm approved; schema alone cannot claim a complete verifier.
7. Fake provenance test: simulated transport/proof cannot satisfy a narrow prospective production eligibility predicate; no actual provider adapter/endpoint or capture workflow added. Direct database ability to insert live-looking labels is not claimed tamper-proof proof provenance.
8. Record isolated integrity/FK results, raw constraint failures, hash/data preservation and actual counts. Run existing auth/host/B3.1 regressions safely only in later authorized preparation. Working SQLite/.env hashes before/after must match, no real notifications or working fixtures. Return exact migration/SQL/support diff, dependency/rollback map and remaining blockers, without commit or C activation.

## 9. Implementation dependencies and stage boundaries

| Stage | Independent authorization / dependency |
|---|---|
| B3.2-B | Isolated synthetic schema preparation after exact contract/scope approval; no working SQLite writes |
| B3.2-C | Exact reviewed migration list, fresh target preflight, no active writes, consistent private current-data backup/restore rehearsal, key controls and explicit working execution approval |
| B3.2-D | Secure identity/contact capture, purpose/role validation, actual OTP logic/UX, approved schema/key/privacy policies; real data/sends each separately activated |
| B3.3 | Submission/history and immutable email/identity/proof evidence binding, minimum readiness, CAS/idempotency/audit; no raw NIN snapshot |
| B3.4 | Chief-only final internal decision, fresh privileged role/reauth/MFA/conflict/evidence rules; no account provisioning or financial activation side effect |
| B3.5 | Security/lifecycle acceptance and browser/target-engine limitations; essential controls and meaningful isolated checks remain required before each earlier milestone acceptance |

No stage authorizes the next. Financial/payment/ledger/QR/buttons/pricing/settlement/refund, dashboards, team/MFA and general notifications/documents remain under existing R1.0–R1.3/F gates. Approved email binding belongs future submission; don't add submission tables/actions to B by association. Legal/provider gaps do not permit fake-ready production behavior.

## 10. Exact bounded candidate execution scope for later approval

**Candidate B-SYN-1T, not executed:**

- Seven proposed structures only, with schema-affecting blockers resolved first. Any event/bucket eighth table, optional blind index, profile column or audit resource-link column requires an explicit revised scope, not an implementation surprise.
- New additive migration files and reviewed minimal audit enum/CHECK compatibility change; old applied migrations unchanged. Exact grouping/file list/SQL presented during approved preparation before any working activation.
- Narrow new guarded/hidden models/casts/enums/factories/test helpers needed for constraint/preservation tests; synthetic-only crypto interface adapter/harness only after its exact contract/support scope is approved. No general decrypt accessors, key manager provisioning, verifier actions, worker or business write service.
- Safe synthetic migration/constraint/crypto-contract/concurrency tests and focused existing regressions; report exact effective test DB and unchanged working hashes. No working migrations, copied real data, runtime routes/forms/config/env change or network.
- One future implementation report with explicit files/SQL/evidence/blockers. No commit/push or implicit schema activation.

Excluded: optional five profile additions/old three attestation columns; submission bindings; KYC cases/files/tax/UBO/identity-match integration; payment-method/financial schemas; dashboard/team/MFA/notification/PDF features; real encryption keys/secrets/NIN; send adapters/endpoints/live messages; admin account/roles. Where missing decisions prevent safe exact schema, stop that slice and present a concrete amended contract instead of choosing permissive defaults. If owner chooses a partial subset, approve its precise changed dependency/test plan first.

## 11. Explicit owner approval questions for the next package

These are recorded decision questions, not an execution request or approval assumed from silence:

1. **Scope:** approve precisely B-SYN-1T's seven-table synthetic package and bounded support/tests, or request a named reduced subset? Keep all exclusions and C activation separate?
2. **Crypto:** which reviewed AEAD library/service and envelope/context/key-ID/revision contract should be adopted? Who owns key custody/rotation/recovery, and which synthetic interface/test support is authorized? Technical review must substantiate the chosen mechanism.
3. **Window storage:** require a strict rolling 30-minute window? Approve an exact atomic bounded-event-in-row design or explicitly expand scope for event/bucket storage after review? What happens at boundary/clock rollback?
4. **Pending send quotas:** retain or revise proposed three/15min, five/hour and IP20/hour? What actor-wide/target-wide/global/spend budgets and failure/ambiguous-send charging/cooldown semantics should be tested? None approved here.
5. **Proof/recovery:** how long is an unchanged phone proof valid, what reverification/step-up/contact recovery is required, and how do existing proofs behave during provider outage? No unlimited validity default.
6. **Constraint/audit contract:** ratify exact claim/state/provenance sets, transitions/null/time checks, metadata bounds and core event names; is merchant/actor/request audit correlation sufficient or must a typed resource-link extension be separately reviewed?
7. **Real use:** identify qualified legal/privacy and official NIN/SMS evidence owners and activation checklist. This is planning of prerequisites, not permission to collect/send or invent official format/contracts.

Do not ask again for already approved five OTP values/email binding/NIN separate-key protection or D13-1 product policy. Ask only for unresolved mechanisms/semantics or concrete execution authorization. Document-delivery/legal exceptions and production-engine selection remain open independently of the synthetic subset.

## 12. GO / NO-GO recommendation

**GO for review:** owner policies are recorded with actual source constraints and remaining blockers. This is a concrete bounded proposal, not proof of implemented security.

**B3.2-B is not yet ready for unconditional execution authorization.** Resolve the schema-affecting blockers (envelope/key-reference/context contract; abuse algorithm/store/scope; proof-lifetime representation; precise state/check/audit/support-code contract), then explicitly authorize that final bounded synthetic scope. Official real NIN/SMS/privacy/production prerequisites remain separate blockers to real activation, not excuses to invent permissive defaults or an API.

**NO-GO under this task:** migrations/test execution, code/schema/data/env/key changes, real NIN, OTP/email/SMS, dependencies/accounts/integrations/UI, working activation or advancing B–C–D/later stages, commit/push/deployment. No production keys generated and APP_KEY unchanged.

## 13. Preservation verification and stop

Before/after SHA-256 verification: **187 pre-existing baseline files checked, 0 changed/missing**. This includes tracked files across projects, eight pre-existing untracked documents, `.env` and working SQLite. Existing code/frontend/migrations/tests/roadmaps/architecture and historical documents retain hashes; `.env` equality preserves APP_KEY without printing it. Working file equality is preservation evidence, not a new integrity or production-security certification. No unexpected tracked changes were reported.

Only new artifact: `XLIPA-B3-2-TECHNICAL-DECISIONS-AND-SCHEMA-APPROVAL-2026-11-06.md`. HEAD remains `7c3e4743384e0ca0acd7b029c944569c84304541`, staging unchanged, no tracked diff. No runtime/tests, migrations, real/synthetic DB creation, keys, backup/import, accounts, NIN collection, messages, provider operations, installs, UI edits, commit/push/deployment performed.

**STOP — wait for explicit owner approval of a resolved concrete scope before implementation.**
