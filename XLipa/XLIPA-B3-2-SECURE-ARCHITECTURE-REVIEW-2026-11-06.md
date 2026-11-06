# XLipa Website — B3.2 Secure Architecture Review

**Version SA-1.0, 6 Novemba 2026, Africa/Nairobi. Documentation only.** Product-policy baseline: [B3.2-A v2.0](XLIPA-PHASE-3-B3-2-A-FINAL-SPECIFICATION-2026-11-06.md). This review proposes technical implementation boundaries; it does not authorize implementation or amend historical reports.

Labels: **VERIFIED** = inspected source/schema/Git evidence; **OWNER-APPROVED** = product requirement; **PROPOSED** = technical design; **NOT VERIFIED** = missing evidence/implementation; **REQUIRES APPROVAL** = separate owner gate. XLipa is a browser-based payment gateway website. Tanzania `TZ` is the initial market; future production domain remains unknown.

## 1. Verified current project

Canonical root `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`; Laravel `backend/`; frontend `frontend/`; Git root is parent `WEB-DEVELOPMENT`. Branch main; HEAD **7c3e4743384e0ca0acd7b029c944569c84304541**. Initial working tree contains the two untracked B3.2 planning/specification reports; stage area empty. They remain unchanged.

Authoritative roadmap sources located: `PHASE-3-STAGE-A-ARCHITECTURE-2026-11-06.md`, refined by `PHASE-3-B3-ONBOARDING-AUTHORIZATION-PLAN-2026-11-06.md`; subsequent owner-approved product policy is v2.0. No separately named master-roadmap file was found; the companion addendum references these sources without replacing them.

Source inspection confirms native session authentication; signed email verification/reset; fresh account_status/auth_version; member-scoped merchant context; MerchantPolicy summary access; current-attempt owner/manager application policy; staff-safe view model; one GET/HEAD onboarding route; separate platform roles; trusted-host configuration. Auth readiness intentionally permits local/testing and log/array mail only. Production auth/privileged workflows are not enabled.

READONLY SQLite evidence: **14 application tables, 9 migration records; users/merchants/memberships/applications/audit logs 1/1/1/1/1; platform assignments 0; integrity ok; foreign-key violations 0**. SHA-256 `214ae83a31f53e80d3068d1aeb1366008bb5accb2df212dd2c534ae4121b6d8f`. `.env` hash unchanged; no secret values read into this document.

Actual reusable schema: users integer IDs, merchant/application/audit ULIDs; nullable contact/address/registry columns; membership owner/manager/staff, active/revoked; merchant profile_version; application attempt/version/current_slot and five states; encrypted application snapshot/internal notes; audit enum CHECK/scalar allowlist and composite application+merchant FK. Positive-version triggers and existing indexes/uniqueness must survive later evolution. SQLite varchar lengths do not replace validation.

**Missing:** phone proofs/OTP infrastructure, sensitive NIDA store, document module, submission/review actions, payment methods, payment APIs, financial ledger. Existing business_phone is a declaration, not a verified number. Historical executed tests are **108/847**, frontend checks **31**; no tests executed in this read-only planning task and no future-module tests claimed passed.

## 2. Approved policy and independent trust gates

**OWNER-APPROVED:** individuals, sole proprietors, registered businesses/companies and unregistered individuals may apply in TZ. Minimum application information is **business name, NIDA number, phone, email**; exact email and phone ownership must be verified before submission. An individual supplies the individual owner's NIDA; a company supplies that of a legally authorized representative. Membership role does not establish legal representation. Other profile/evidence/payment-method fields are generally optional at initial submission.

Keep these facts separate:

| Fact | Evidence | Must not imply |
|---|---|---|
| NIDA supplied | Sensitive record exists under a lawful collection process | Valid format, identity match or authority |
| Syntax validated | Approved canonical-format policy passes | Number exists or belongs to claimant |
| Identity independently verified | Authorized review/service finding with provenance | Authority to represent a company |
| Representative authority established | Appropriate independent authority evidence | Merchant/provider approval |
| Chief internal approval | Authorized, nonconflicted application decision | Legal/provider eligibility, verified bank account or enabled payments |

Minimum readiness is computed from four valid values, exact contact proofs, fresh owner/account/tenant authorization, current draft/version, allowed operational context and approved secure infrastructure. Optional profile completeness is advisory. A specific legal/provider obligation can change a gate only through a versioned evidenced rule identifying type/jurisdiction/service/phase; do not reinstall the old broad profile checklist.

Chief is final internal decision-maker; ordinary admin/staff final decision delegation is not implicit. Preserve application states draft, pending_review, information_requested, approved, rejected. Internal verified/enabled projections remain nonfinancial semantics; service activation is a separate default-deny policy.

## 3. Security boundaries and sensitive identity design

**PROPOSED:** isolate public website, authenticated merchant context, sensitive-data service, privileged reviewer context, transport adapters and future financial services logically within the existing modular Laravel architecture; no new deployment/microservice dependency is required by this document.

### NIDA subject binding

Dedicated merchant-scoped identity content versions hold encrypted NIN and declaration metadata. Optional business_type may remain unknown; do not silently classify an applicant as an individual or treat account name as a verified legal name. Use a concise truthful relationship/declaration claim for the supplied number; exact wording and lawful basis need approval. Missing optional legal-name/address fields are not hidden submission blockers. Unresolved identity/authority findings remain explicit for later review/service conditions.

No real NIN is collected here. Current canonical NIDA format/checksum, stakeholder access and integration contract remain **NOT VERIFIED**; v2.0's proposed 20-digit format needs authoritative confirmation before real collection. Do not infer birth date/citizenship or invent validation algorithms/APIs. Secure storage and approved input policy are technical prerequisites; online NIDA verification itself is not silently made a universal minimum-submission requirement.

### Encryption and key separation

- Vetted authenticated encryption, TLS in transit and approved at-rest encryption for DB/object storage/backups. Use envelope encryption with record-level data keys and a separately controlled key-encryption key where operationally supported; approve actual library/KMS before implementation. Bind ciphertext use to merchant, record and content version using supported authenticated context or verified binding, not homemade crypto.
- Store ciphertext/envelope/key identifiers, not plaintext keys. Database credentials and routine merchant/reviewer access do not grant key administration. Use independent key domains for NIDA encryption, optional identity fingerprints, phone encryption, OTP HMAC and future webhook signing; no APP_KEY reuse as a universal security secret.
- Optional NIN duplicate detection uses a private keyed fingerprint with separate key/version. Avoid unsalted hashes, global uniqueness and automatic identity merging: one person may lawfully represent multiple businesses. Fingerprints expose equality and require restricted access/retention.
- Rotate wrapping keys by controlled rewrap; rotate data keys through audited re-encryption. Cryptographic-envelope revision is separate from identity-content version so unchanged identity is not falsely treated as newly verified. Keep compatible old key access only while legitimate live/backup recovery requires it. Test rotation interruption, wrong context, corrupt ciphertext and unavailable key service.
- Back up encrypted data independently from protected keys; separately controlled recoverable key backups, operator separation and restore rehearsal are required. Do not regenerate APP_KEY to recover old evidence. Revoked keys fail closed; restoration must replay legal holds/deletion tombstones to avoid resurrecting erased data. Targeted crypto-erasure must not destroy unrelated records.

### Masking and access

Write-only sensitive POST over TLS; no raw NIN in merchant columns, profile snapshots, ordinary JSON/HTML, URLs, filenames, exceptions, old input, analytics, traces or ordinary logs. Existing dontFlash/redaction controls need separately approved extension before sensitive capture. Normal reads return no identifier or a masked last-four hint; mask derivation stays inside authorized sensitive service. Do not prefill stored NIN into forms. Chief normally reviews masked values/provenance/findings; full-number reveal/export is **not approved**, even for Chief, without a separately justified access policy.

Every direct action/job/stream rechecks tenant association and purpose, not merely route middleware. Key use is audited without NIN/digest/ciphertext/body contents. Privacy notice, lawful basis, consent where required, processing records, retention/rights/transfer controls and approved processors precede real collection. PDPC's own notice describes minimization/security/storage-limitation principles; it does not certify XLipa or determine its legal basis. [PDPC notice](https://pdpc.go.tz/privacy-notice/).

## 4. Existing email binding and proposed contact model

**VERIFIED:** existing User MustVerifyEmail, signed EmailVerificationRequest, resend throttling and verified account route guards. Preserve CSRF, signatures, reset/session regeneration and active/auth_version enforcement.

**PROPOSED:** submission email is the fresh submitting owner's users.email, with its current verification evidence; freeze an encrypted email binding and observed verified_at at submission. Optional business_email does not replace it. A different submission email needs an independently approved ownership flow. Any future email-change action must clear its verification, apply session revocation policy and invalidate dependent readiness; no such change is implemented here.

Application phone is an explicitly confirmed, versioned merchant+actor+purpose contact, independent of optional business_phone. Phone normalization to E.164 shape is not possession proof. Contact proof cannot transfer to another merchant, owner, purpose or bank/mobile-money account. Historical submitted facts are immutable; current decision/activation checks reject revoked/stale bindings.

## 5. Proposed OTP challenge and delivery protocol

Initial technical policy (approval pending): six CSPRNG decimal digits, five-minute expiry, five guesses/challenge; aggregate ten failed guesses per actor+merchant+target over 30 minutes; resend ≥60 seconds, ≤3/15 minutes and ≤5/hour; per-actor, target-wide, IP and system spend limits. Proposed IP send limit 20/hour is a tunable starting value, not measured production capacity. Define exact rolling-window semantics before coding; test boundary bursts and rotated targets/accounts. New challenges do not reset aggregate failed-attempt counters.

NIST describes single-use random out-of-band secrets, throttling and PSTN limitations. These design parameters are not an assurance-level claim; SMS possession is vulnerable to SIM changes/recycling and is not identity proof or privileged MFA. [NIST SP 800-63B-4](https://pages.nist.gov/800-63-4/sp800-63b.html).

Protocol:

1. Fresh verified owner requests a scoped challenge; validate contact/version/purpose, quotas and configured approved transport. Reserve capacity atomically; no arbitrary SMS bodies or recipient lists.
2. Create an opaque delivery intent; worker claims it once with a fencing/version token. Generate code near delivery, record keyed HMAC bound to challenge/tenant/actor/contact/purpose and expiry. Constant-time compare; no plaintext code persisted in ordinary DB, queue, logs or telemetry. Low-entropy codes must not rely on an unkeyed digest alone.
3. Send outside DB locks through a replaceable adapter. No provider endpoint/header/credential is defined. If a temporary queued secret is unavoidable, private encrypted storage/TTL/operations need separate review; default is generate in worker and keep plaintext only for the bounded send.
4. Provider acceptance is not phone verification. Rejection/outage/ambiguous timeout creates no proof; do not blindly retry a possibly delivered message without an actual idempotency contract. Delayed accepted responses cannot revive revoked/expired challenges. A crashed worker may require a fresh user resend under preserved limits; no promise of exactly-once SMS.
5. Code verification atomically checks fresh context, expiry, active challenge, failed-count limits, keyed digest and current contact version. Consume once and insert proof + success audit in one transaction. Cross-tenant/purpose/old/replayed codes fail; concurrent consumption has one winner.
6. Generic masked errors/no-store responses, quotas surviving resends, temporary cooldown/recovery rather than permanent forced lockout. Fake adapter outcomes are testing-only and cannot produce live-eligible proofs.

Phone change requires recent owner reauthentication, new contact version and revocation of old current proof/challenges; reverify new number. Unrelated optional profile edits do not invalidate proof. Pending-review contact changes do not rewrite frozen evidence; current readiness/review eligibility is invalidated and follows authorized replacement/review lifecycle.

**Fail-closed submission:** absent OTP module/configuration, unavailable mandatory verifier/key/schema policy, stale/missing proof or operational verification gate failure means no pending_review write. Do not substitute optional documents or a Chief “override verified” flag. Approve proof lifetime and transient-outage policy explicitly: no live submission path until verification operations are approved; established proofs are never relabeled false/true merely by browser state.

## 6. KYC and evidence architecture

Categories supported by future design: NIDA evidence, tax/TIN metadata, business licence, BRELA/other justified registry documents, representation authority and explicitly justified additional categories. Different entity/jurisdiction rules may differ. Optional documents stay optional at initial submission; specific verified obligations are versioned by phase/service. No legal requirement list or provider underwriting rule is invented.

Document lifecycle separate from application state: uploading/quarantined → clean or rejected/scan_failed; reviewer finding requested/pending/accepted/rejected/expired is distinct from malware result. A clean PDF is not authentic evidence. Structured TIN/identity metadata belongs in sensitive records, not an overloaded file field.

Controls: private nonpublic object storage, encryption/key controls, random keys, no public symlink; proposed PDF/JPEG/PNG ≤10 MiB/file and ≤10 files/attempt with total quotas (limits require approval); server MIME/signature/extension and resource/dimension/page validation; malware scanning/sanitization under approved isolated service; no external-URL fetch or public scanner upload. Scan outage fails closed for document acceptance but does not block a document-free minimum application.

Versioned bytes/checksum bind scan/review results. No overwrite of submitted evidence. Authorized attachment streaming checks actor/tenant/case/application/version/clean status at access time; no-store/nosniff, purpose audit, no permanent public download URLs. Ordinary staff has no evidence access; managers do not inherit sensitive identity access. Chief review requires explicit masked/evidence permissions, reauth/MFA readiness and conflict rules.

Object upload and SQL are not one transaction: quarantine allocation, commit metadata, scan, publish authorized availability; reconcile incomplete/orphaned objects. Approved retention schedules/holds/rights requests cover rejected/draft files, audit and backup copies. No automatic deletion on rejection/rollback. Document-request workflow uses public bounded request codes and existing information_requested transition; no internal risk notes disclosed.

## 7. Concrete additive schema proposal

**PROPOSED, not migration code.** Types: U = ULID compatible with existing merchant/application keys; I = existing integer/bigint user key; V(n) = bounded string with application validation and reviewed DB checks; T = UTC timestamp; CT = encrypted text/envelope; N = nullable. All actor IDs are server-set. Foreign keys default RESTRICT; no cascade deletion of evidence. Every tenant child has explicit merchant_id and an exact composite referenced key; do not trust independent valid FKs that still permit mismatched tenants.

### 7.1 Minimum identity/contact foundation — next B3.2-B candidate scope

| Proposed structure | Principal columns | Keys/indexes/constraints |
|---|---|---|
| Existing merchants optional additions | legal_name V200 N, business_activity V1000 N, website_url V255 N, representative_name V200 N, representative_capacity V100 N | No backfill/NOT NULL/default facts. Other three prior broad-attestation fields are not automatically included; declaration handled below. No raw identity/contact-proof fields here |
| merchant_identity_records | id U, merchant_id U, version positive int, subject_claim V32 (unknown/individual-owner/representative claim), nin_ciphertext CT, encryption_key_ref V128, envelope_version int, optional fingerprint/key_ref V128 N, syntax_policy_version V50 N, syntax_checked_at T N, declaration_version V50, notice_version V50, declared_by_user_id I, declared_at T, superseded_at/retention_due_at T N | PK id; FK merchant/user; UNIQUE(id,merchant_id), UNIQUE(merchant_id,version); content immutable after referenced submission. No unique-NIN/fingerprint constraint. Index merchant/version and private fingerprint only if justified |
| merchant_contact_versions | id U, merchant_id U, actor_user_id I, purpose V50, version positive int, phone_ciphertext CT, phone_key_ref V128, target_fingerprint/key_ref V128, created_at T, retired_at T N | FK merchant/user; UNIQUE(id,merchant_id,actor_user_id,purpose); UNIQUE(merchant_id,actor_user_id,purpose,version). Immutable number/version, separately retire. Target digest private, not a public identity key |
| merchant_onboarding_contexts | merchant_id U PK, context_version positive int, current_identity_id U N, current_contact_id U N, contact_actor_user_id I N, contact_purpose V50 N, updated_at T | FK merchant; composite current-identity→identity(id,merchant); current-contact tuple→contact(id,merchant,actor,purpose); paired-null checks. CAS context_version. New sidecar avoids rebuilding existing merchants for cyclic current-pointer FKs |
| phone_otp_challenges | id U, merchant_id U, contact_id U, actor_user_id I, purpose V50, active_slot nullable 1, state V32, code_digest/key_ref V128 N, expires_at T N, failed_attempts nonnegative int default0, created_at T, consumed_at/revoked_at T N | FK contact composite tuple; UNIQUE(id,merchant_id,contact_id,actor_user_id,purpose); UNIQUE(merchant_id,contact_id,actor_user_id,purpose,active_slot). State consistency checks: active needs digest/expiry; terminal cannot verify; expiry/state index |
| otp_delivery_intents | id U, merchant_id U, challenge_id U, contact_id U, actor_user_id I, purpose V50, state V32, claim_version positive int, transport_mode V32, requested_at/claimed_at/completed_at T N as appropriate, bounded failure_code V50 N, provider_reference CT N | UNIQUE(challenge_id); exact composite FK to challenge tuple; no OTP/phone in ordinary payload. Claim/fencing and state CAS; index state/requested_at. Provider reference access restricted |
| contact_verification_proofs | id U, merchant_id U, contact_id U, actor_user_id I, purpose V50, challenge_id U, verified_at T, channel/provenance V32, policy_version V50, revoked_at T N, reason_code V50 N | UNIQUE(challenge_id); composite challenge/contact/actor/purpose association; UNIQUE(id,merchant_id,actor_user_id); content immutable except audited revocation. Real-service eligibility distinct from simulated tests |
| verification_limit_state | scope_key V128, policy_rule V50, window/version/counter/block metadata, updated_at T | Composite PK(scope_key,policy_rule); private HMAC actor/target/IP scope keys, no raw PII. Atomic reservation/CAS. Exact rolling-window policy may additionally need bounded timestamp buckets/events; finalize algorithm/storage in approval, not an approximate counter presented as strict rolling limit |

Current pointer sidecar may initially be absent: not-ready, not a repair/migration backfill. Only an approved actor's future capture action creates it transactionally. Contact purpose is application contact, not login MFA or settlement ownership. The database tuple validates binding; fresh role authorization still belongs in actions. No mandatory FK from these minimum tables to deferred KYC files, so optional uploads cannot become hidden prerequisites.

Independent identity/authority verification findings should be append-only, protected facts referencing identity record+merchant, checker/provenance/policy/evidence and timestamps; they are **deferred until an authorized reviewer/service actually exists**, not fabricated “verified” columns populated by migration. Supplied/syntax/identity/authority facts stay distinct. Key rotation changes envelope revision, not content version or independent findings.

### 7.2 Submission and optional evidence — later gated structures

| Structure / timing | Proposed data and relational enforcement |
|---|---|
| application_submission_bindings — B3.3 preparation | application_id U PK, merchant_id U, profile_version/context_version, identity_record_id U, contact_proof_id U, submitting_user_id I, encrypted email snapshot/key ref, email-binding fingerprint, observed email_verified_at, policy/declaration versions, submitted_at. FK(application,merchant)→existing application unique pair; FK(identity,merchant); FK(proof,merchant,submitting_user). Immutable, one/app; validate proof purpose/contact/current pointers in same serialized action |
| merchant_kyc_cases — optional KYC stage | id U PK, merchant_id U, application_id U, case_version positive int, policy_version, created_at; FK(application,merchant), UNIQUE(application_id), UNIQUE(id,merchant_id,application_id). Case need not exist for a document-free minimum submission |
| kyc_evidence_files — optional KYC stage | id U, case_id/merchant_id/application_id U, category V50, document_version int, random private object key V255, server MIME V100, bytes positive int, SHA256 checksum V64, scan/review states V32, encryption key/version metadata, uploader I, created/scanned/reviewed/retention/hold/deleted timestamps, bounded reason/provenance | 

For files, require UNIQUE(id,merchant_id,application_id), UNIQUE(case_id,category,document_version) if category permits one logical version sequence; otherwise introduce an explicit logical_document_id rather than rejecting multiple legitimate licences. Composite FK(case,merchant,application)→case; no mixed case/application ownership. Private filenames/notes only encrypted if needed. File-review state never implies independent NIDA verification.

Later `application_submission_evidence` references exact application+merchant+evidence version via composite keys; UNIQUE(application_id,evidence_id). No raw NIN in profile_snapshot or audit. References survive current-proof revocation as historical facts; current eligibility checks evaluate revocation separately. Do not prune referenced challenge/proof rows blindly: scrub OTP digest after terminal/retention policy while retaining minimal referential metadata, and retain failure counters until their aggregate window closes.

Tax metadata, authority findings and payment-method structures should have dedicated typed schemas when their stage is approved, not arbitrary polymorphic JSON used to bypass tenant FKs. Bank/mobile-money management belongs the companion roadmap, not minimum B3.2-B scope. No financial tables are proposed for creation in B3.2-B.

### 7.3 Audit and migration boundaries

Candidate audit families: identity supplied/replaced, contact requested/changed/revoked, OTP verified/failed under bounded logging policy, profile updated, evidence uploaded/scanned/accessed/reviewed, application submitted/decided. Retain existing event names, scalar allowlist ≤2048 and atomic success audit. Final exact event enum/reason set needs approval; no PII/hash/ciphertext dumps. SQLite CHECK extension may require reviewed audit table rebuild; preserve every row, FK, index and existing model casts. Failure logs must not falsely record success.

Old applied migrations remain unchanged. Current user IDs, merchant/application IDs, states, current_slot rules, positive version triggers and all unrelated schema remain. Requiredness of new fields is runtime readiness, not a destructive backfill. No existing signup phone is backfilled as verified, no guessed NIN/country/representation and no fake proof.

SQLite-specific acceptance: FK enforcement on each connection; exact referenced composite UNIQUE keys and paired-NULL checks; positive integer type/value constraints (not merely unsigned declarations); review nullable uniqueness/partial-index portability; transactional DDL/rebuild failure tests and SQLITE_BUSY retries. Future production engine needs deliberate port/testing; SQLite lockForUpdate alone does not prove serialization.

## 8. Concurrency and lifecycle protocol

Profile, identity/contact pointer changes, proof consumption, submission and review require an agreed parent context/version protocol. First serialize/CAS expected merchant/context/current application version, then fresh actor/membership/state checks within transaction; a failed authorization rolls back any reservation/version update. Check affected-row count; stale client returns 409, no last-writer-wins. Retried transactions rerun authorization and preserve client expected versions. No external SMS/scanning/provider network call inside held transaction.

Submission atomicity: current draft + four minimums + exact valid email/phone/identity bindings → immutable references + pending_review + audit, then notifications/outbox after commit. Repeat authorized same request does not create duplicate attempt/audit. Independent current-slot uniqueness is only “at most one”; action enforces required current attempt. Contact replacement during review cannot rewrite frozen versions or enable a stale approval. No optional-profile score controls minimum submit.

Chief decisions require current submitted evidence, current operational restrictions, fresh Chief assignment, verified identity, recent reauth/MFA readiness and no conflict/self-review. Application approved does not automatically make identity findings true or activate financial services. Without an independent authorized reviewer, fail closed; no bootstrap account creation here.

## 9. Threat model and acceptance criteria

All criteria are **PROPOSED future tests**, not completed security controls beyond existing B3.1:

| Threat | Safeguard / required acceptance |
|---|---|
| Foreign merchant/child IDs | Member-scoped root + policies + composite FK + action checks; mismatched identity/contact/proof/case/file rejected in HTTP, jobs and raw constrained DB fixtures |
| Stale/forged roles, actor or purpose | Fresh persisted account/auth_version/membership, no mass assignment, no Chief merchant bypass; role-change/revoke race tests |
| NIDA leakage or decryption misuse | Write-only input, scoped crypto context, no old input/telemetry/log/export; wrong-key/tamper/foreign-context tests; masked authorized reads only |
| False verification | Distinct supplied/syntax/identity/authority/internal-approval facts; no SMS→identity/bank proof, no migrated verified flags |
| OTP brute force/replay/resend abuse | Persistent aggregate counters, target/account/IP budgets, expiry, single consume, contact-version/purpose binding; resends cannot reset guesses |
| SMS timeout/queue duplication | Claim fencing, bounded operations, no proof on ambiguous failure; old message cannot revive challenge; spending/retry tests |
| Key loss/rotation failure | Separate recovery, authenticated encryption, corruption detection, synthetic decrypt/restore/rotation rehearsal; no replacement APP_KEY shortcut |
| Document malware/SSRF | Quarantine, strict bounded validation, scan checksum binding, no remote fetch/public scanner; scanner outage disables evidence use only |
| Evidence tampering/history rewrite | Versioned immutable submissions/files, fresh findings/references; revoke current eligibility without altering old evidence |
| Duplicate/concurrent submission or decision | CAS/unique current slot, atomic audit/reference write, idempotent outcomes; conflicting one-winner tests |
| Audit compromise or excessive collection | Bounded redacted events, no secrets/NIN/body data, access controls and separately approved immutable export; model append-only is not tamper-proof DB |
| Privacy/retention failure | Approved lawful basis/category retention/holds/processors/transfers, deletion manifests and backup expiry; no unapproved real identity collection |

Future suite must prove minimum-four-field submission succeeds with optional profile/documents/methods absent, but fails with missing mandatory contact proof/infrastructure. Include existing auth/host/merchant/DOM regressions and working-DB hash preservation. Testing-only transports and fake proofs must never authorize live service activation.

## 10. Implementation sequence and migration recovery

1. Owner reviews this security design and resolves section 11; product v2.0 remains baseline.
2. Explicit **B3.2-B preparation** approval for a bounded subset: optional profile + identity/contact/context/challenge/proof/abuse/audit schema, encryption/key interface and isolated synthetic tests; no endpoints/real collection/sends. Submission bindings may be prepared only if expressly included; document/payment-method tables may remain deferred.
3. Present concrete migrations/SQL/keys/indexes/constraint map and test evidence. Rehearse populated synthetic up/down/reapply, audit CHECK rebuild, failure rollback, previous B3.1 compatibility, proof/identity immutability, crypto rotation/restore. No real personal fixtures or working DB writes.
4. Separate **B3.2-C activation**: exact canonical target, fresh read-only preflight, no active writes, consistent SQLite backup API, restore-to-new-file rehearsal, key safeguards and explicit owner execution approval. No seed/reset/import.
5. Separate **B3.2-D implementation**: optional profile and secure minimum-data/contact UX/actions. Real SMS adapter approval separate; unavailable verification cannot make live submission ready.
6. Optional KYC documents require their own private-storage/scanner/privacy stage. Then separately approved **B3.3** submission/history and **B3.4** Chief review; **B3.5** acceptance/hardening retains its original roadmap position. Evidence collection is not an excuse to advance admin/financial capabilities early.

Rollback is not automatically safe: new identity data and new audit event values may be incompatible with old schema/code. Prefer forward corrective change; any down/restore requires approved reconciliation of subsequent writes, retention/hold review and no deletion of audit events just to satisfy old CHECK. Backups include protected current data, separately recoverable keys and deletion/hold manifests; same-workspace copies are not independent disaster recovery.

## 11. Remaining decisions and B3.2-B recommendation

**REQUIRES APPROVAL:** authoritative email binding; actor/merchant phone-proof reuse/lifetime; OTP thresholds/window algorithm/outage/cost policy; NIDA syntax and subject/declaration/privacy wording; cryptographic/key-management implementation and operators; exact minimum table subset/FKs/current-pointer checks; legal-form/jurisdiction rules only where relevant; retention/processor/cloud-transfer policies; exact audit enum; future Chief access/conflict/MFA gates. Full NIN reveal is not approved.

**NOT VERIFIED:** official NIDA verification contract/access, current full NIN validation rules, SMS delivery/provider contract, Selcom eligibility/method/currency/callback contracts, applicable service-specific legal/KYC conditions, production infrastructure and independent key recovery. No invented endpoint or credential fills these gaps.

**GO for owner review; CONDITIONAL GO for authorizing a bounded B3.2-B preparation stage only after the technical subset/crypto/OTP/declaration decisions are ratified. NO-GO for creating migrations under this task, working-schema activation, real identity collection, SMS, uploads, Chief endpoints or payments.** Unknown external provider contracts need not block inert schema design, but must block falsely verified live flows and real collection where legal/privacy prerequisites are unresolved.

No code, schema, database records, environment, frontend, Git history or historical document was changed. No tests, migrations, installations, real messages, provider requests, commit/push or deployment were executed. Only the two requested documentation artifacts are outputs. **STOP for owner approval.**
