# XLipa — B3.2 Security Blockers Resolution

**SBR-1.1 · 7 November 2026 · Africa/Nairobi · ULID compatibility amendment**

**OWNER-APPROVED correction B32-ID-CASE-1 (7 November 2026):** preserve all stored parent IDs and binary tenant/FK identity. Permit structurally valid Laravel lowercase parent ULIDs in referencing columns. Canonicalize validated ULID components to uppercase exclusively inside AEAD associated-data encoding, identically for encryption/decryption. No NOCASE collation, existing-ID rewrite or permission bypass. The remaining proposed operational decisions and real-use exclusions retain their status. This amendment authorizes only the separately bounded isolated correction/testing, not working activation or B3.2-C. Earlier review-time preservation/runtime statements below are historical; current implementation evidence is in the updated B3.2-B report.

## 1. Executive summary

This is a proposed implementation contract for owner and technical review. It resolves the four previously unspecified design choices into concrete recommendations; it does not approve them or implement them. **GO for review; NO-GO for migration generation or execution until the schema-affecting proposals and bounded support scope below are explicitly approved.** Once ratified, isolated synthetic preparation need not wait for a real SMS provider or legal permission to collect real identity data. Working activation, real collection and live delivery remain separate NO-GO gates.

Labels throughout: **VERIFIED** means inspected file/Git evidence in this review, not runtime certification; **OWNER-APPROVED** means the current request or recorded owner policy; **PROPOSED** means this review's recommendation; **REQUIRES APPROVAL** identifies an outstanding decision or execution boundary; **NOT VERIFIED** identifies absent runtime, operational or external evidence. Every new schema/interface/state/test below is **PROPOSED**, unless explicitly identified as an existing fact or owner-approved requirement.

Recommended decisions:

- PHP Sodium XChaCha20-Poly1305 AEAD, dedicated versioned NIN and phone keys, deterministic associated-data encoding, bounded authenticated envelope, no ordinary encrypted model accessor for NIN.
- Strict rolling failures in `(now − 1,800 seconds, now]`, with ten ordered timestamps in the existing proposed `verification_limit_state` row; SQLite write serialization and CAS, no eighth table.
- A versioned, 30-minute current phone proof, consumed once at future submission; immutable historical evidence is distinct from current authorization. Thirty minutes is **REQUIRES APPROVAL**, not an already approved duration.
- Exact seven-table column/constraint/state contract, with narrow new-table integrity triggers and an explicitly proposed audit compatibility slice. No changes to existing applied migration files.

**OWNER-APPROVED:** authenticated NIN encryption independent of `APP_KEY`, masked display and no plaintext NIN in ordinary fields/logs/URLs/audits; exact application-phone proof bound to merchant, actor, contact version and purpose; contact changes invalidate dependent proofs; onboarding proof is neither financial authorization nor login MFA. Six digits, five-minute OTP expiry, five failed guesses per challenge, 60-second minimum resend cooldown, and ten aggregate failed attempts per actor + merchant + target in 30 minutes remain unchanged.

## 2. Inspected source evidence

Canonical root: `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`. Git root is its parent. **VERIFIED:** initial `git status --short` was empty; current HEAD was `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`, rather than the historical reports' older checkpoint. No inference about runtime readiness follows from that difference.

| Inspected source | Evidence and implication |
|---|---|
| [B3.2-A v2.0](XLIPA-PHASE-3-B3-2-A-FINAL-SPECIFICATION-2026-11-06.md), [secure review](XLIPA-B3-2-SECURE-ARCHITECTURE-REVIEW-2026-11-06.md), [owner decisions](XLIPA-OWNER-DECISIONS-AND-B3-2-READINESS-2026-11-06.md), [technical package](XLIPA-B3-2-TECHNICAL-DECISIONS-AND-SCHEMA-APPROVAL-2026-11-06.md) | Planning artifacts; TD sections 5.1–5.8 leave crypto envelope, rolling storage, proof expiry and audit details unresolved. OD section 6 narrows B-SYN-1 to seven tables and limited synthetic support. These are not execution permissions. |
| [R1.0](XLIPA-MASTER-ROADMAP-ADDENDUM-PAYMENTS-ANALYTICS-2026-11-06.md), [R1.1](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-1-2026-11-06.md), [R1.2](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-2-2026-11-06.md), [R1.3](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-3-2026-11-06.md) | Payments/attribution, QR/buttons/operations, notifications/documents, dashboards/settings are future gated work. Preserve OD's already approved D13-1 product amendment; legal exceptions and document delivery remain unresolved. None expands this schema into payments, messaging or MFA. |
| [B3 plan](PHASE-3-B3-ONBOARDING-AUTHORIZATION-PLAN-2026-11-06.md), [B3.1 report](XLIPA-PHASE-3-B3-1-IMPLEMENTATION-REPORT-2026-11-06.md), [hardening report](XLIPA-PRE-B3-1-SAFETY-HARDENING-REPORT-2026-11-06.md), B3.2 planning report | Historical context and preservation requirements. Historical test counts and SQLite integrity/row counts are not current test or runtime evidence. |
| `backend/composer.json`, `composer.lock`, `vendor/composer/installed.json` | Requested framework constraint `^13.17`; lock and installed manifest identify Laravel `v13.35.0`. `composer show` was attempted but Composer is unavailable on this shell PATH. Manifest inspection is the read-only fallback. PHP/Sodium runtime availability is **NOT VERIFIED**. No dependencies installed. |
| `backend/database/migrations/2026_11_06_000001` through `000006`, initial users/cache/jobs migrations | Existing user IDs are integers; merchants/applications/audits use ULIDs. Foundation migrations guard SQLite. Membership unique merchant/user; roles owner/manager/staff; membership states active/revoked, with no suspended enum. Merchant states onboarding/enabled/suspended/closed; verification states unverified/pending/needs_information/verified/rejected. |
| Merchant applications migration and `MerchantApplication` | States draft/pending_review/information_requested/approved/rejected; unique merchant/attempt and merchant/current_slot, unique id/merchant. Positive integer attempt/profile versions enforced by INSERT/UPDATE triggers. Model guards submitted evidence and identity changes; existing encrypted snapshot/notes casts use framework encryption, not a dedicated NIN key domain. Model guards do not constrain raw SQL. |
| Audit migration, `AuditEvent`, `AuditLog` | Eight existing event identifiers; SQLite enum CHECK must agree with PHP enum. Actor/target/merchant/application refs, UUID request ID, metadata, occurred_at. Composite application/merchant FK; indexes merchant/time, actor/time, event/time. Metadata scalar allowlist `from,to,role,previous_role,profile_version,reason_code`, 2,048-byte encoded bound. Append-only model hooks do not make privileged SQL tamper-proof. |
| Merchant/User/Membership models, policies, context middleware, onboarding controller/view model | Fresh active user/membership scoping, verified identity email; current-attempt details for owner/manager, staff-safe summary. No Chief blanket bypass. User implements MustVerifyEmail; email normalized, auth_version integer. Existing read permissions do not authorize new sensitive writes/decryption. No proposed seven-table module in project-owned source inventory. |
| `routes/web.php`, `config/auth.php`, auth middleware/config | Session guard, signed email verification and throttles, verified account/status gate, fresh active account/auth_version. Readiness intentionally allows local/testing and log/array mail. Preserve CSRF/session/reset/host/privacy controls. No OTP/submission routes currently established. |
| `config/database.php`, `RevokeMembership` | SQLite foreign keys default enabled; transaction mode DEFERRED, busy_timeout unset. Existing action uses `lockForUpdate()` within DB transaction and writes audit atomically. This is not proof of SQLite row locks or a concurrency protocol suitable for new OTP operations. |
| Feature test inventory, including Authentication/MerchantAccess/DatabaseFoundation/TrustedHosts | Existing regression targets, inspected only; no tests run, no current passing count claimed. |

No working database connection, migration status query, runtime application boot, secret contents or external provider account was used. Live schema/constraints, PHP extension versions, actual custody, delivery and production readiness remain **NOT VERIFIED**. Database file hashes establish byte preservation only. `.ai/rules` was absent at workspace, project and backend locations checked; backend `AGENTS.md` was read. No sub-agents were used.

Preserve owner policy: TZ initial applications; individuals including unregistered applicants may apply; minimum business name/NIN/phone/email plus verified exact contacts/security; optional broad profile/documents/payment methods do not become blanket gates. Membership ownership does not prove legal representation. Chief internal approval, identity findings and payment activation remain distinct. No domain change; future production domain is unknown.

## 3. Encryption architecture

### 3.1 Mechanism decision

**PROPOSED / REQUIRES APPROVAL:** a small purpose-specific service over maintained PHP Sodium APIs, not a new cipher implementation. Use `sodium_crypto_aead_xchacha20poly1305_ietf_encrypt` and matching decrypt; algorithm identifier exactly `xchacha20poly1305-ietf`, key size 32 bytes, random nonce 24 bytes, combined ciphertext includes the 16-byte authentication tag. Authenticate before returning any plaintext. Decrypt returning `false` is a failure, never an empty accepted value. [PHP encryption API](https://www.php.net/manual/en/function.sodium-crypto-aead-xchacha20poly1305-ietf-encrypt.php), [PHP decryption API](https://www.php.net/manual/en/function.sodium-crypto-aead-xchacha20poly1305-ietf-decrypt.php).

| Framework-compatible choice | Assessment |
|---|---|
| Default Laravel Crypt/encrypted casts | Maintained and useful for existing fields, but default application key domain does not satisfy dedicated NIN ownership. Do not repurpose APP_KEY or APP_PREVIOUS_KEYS. Laravel supports previous-key decryption; that alone does not implement this context-bound contract. [Laravel encryption](https://laravel.com/framework/docs/encryption). |
| Dedicated Laravel Encrypter instance | Can isolate a key; inspected public API has no explicit caller AAD argument. Would need a reviewed authenticated inner context and comparison. Avoid this additional wrapper for the new NIN contract. Existing casts stay unchanged. |
| PHP OpenSSL AES-256-GCM with dedicated key | Maintained alternative with AAD/tag; requires strict nonce uniqueness and tag handling. Viable if a future deployment policy mandates it, but not a silent runtime fallback. [PHP OpenSSL](https://www.php.net/manual/en/function.openssl-encrypt.php). |
| PHP Sodium XChaCha20-Poly1305 | Recommended explicit AAD interface, long random nonce and small service surface. Extension/runtime compatibility is a future acceptance gate. No FIPS or regulatory certification is asserted. |
| Managed encryption/key service | Possible future custody adapter; none selected/configured. Do not fabricate a KMS or wrapped-key format. A future algorithm change requires a new envelope version and reviewed migration. |

### 3.2 Interface and envelope contract

Proposed interfaces (specification, not executable code): `SensitiveValueCipher.encrypt(domain, plaintext, Binding) -> Envelope`; `decryptForAuthorizedPurpose(domain, envelope, expectedBinding) -> SensitiveValue`; `rotate(envelope, expectedBinding, expectedRevision, destinationKeyId) -> Envelope`; `KeyResolver.active(domain)` and `KeyResolver.resolve(domain,keyId)` return a key only to the crypto boundary. Binding is constructed from authoritative rows, never trusted from envelope/request JSON. Public serialization of SensitiveValue is prohibited. A separate authorized `maskedNin` service returns a fixed mask or, only where policy permits, last four characters; no full reveal endpoint or persisted plaintext suffix in B-SYN-1.

Envelope v1 is UTF-8 JSON TEXT, maximum 4,096 bytes; exactly these fields, no extras or duplicate keys: `v` integer 1, `alg` fixed identifier above, `kid` nonsecret key ID, `rev` positive integer, `nonce` unpadded canonical base64url of 24 bytes, `ct` unpadded canonical base64url of ciphertext plus tag. No plaintext, key bytes, target fingerprint or user-supplied AAD inside it. Decode with strict size/type/canonical base64url checks. `ct` decoded length 17–272 bytes (1–256 plaintext bytes plus tag); NIN's 256-byte technical ceiling does not approve a real NIN format. Phone input has its own approved normalization policy and at most 16 canonical ASCII bytes. Reject unknown versions/algorithms and nonmatching column/envelope `kid`, `v`, `rev`. No key search across domains or guessed fallback.

Key IDs: 1–64 ASCII characters `[a-z0-9._-]`, immutable registry entries. Example identifiers `nin.enc.v1`, `phone.enc.v1`, `otp.mac.v1`, `contact.fp.v1` are identifiers only; no key material is supplied. Algorithm/envelope version differs from content version, envelope revision, key version and product validation policy.

Associated data uses `LP(s)` = four-byte unsigned big-endian UTF-8 byte length followed by the bytes. Concatenate LP of these ordered strings: `xlipa-sensitive-v1`, deployment namespace, algorithm, decimal envelope version, key ID, decimal envelope revision, domain (`nin` or `phone`), merchant ULID, record ULID, purpose (`identity_declaration` or `application_phone`), decimal content version, actor user ID as canonical positive decimal. Decimal has no leading zeros; namespace is immutable for a ciphertext lineage. Before LP encoding, validate each ULID as exactly 26 ASCII Crockford characters (case-insensitive alphabet, first character 0–7; reject I/L/O/U, whitespace, separators, non-ASCII and overflow), then uppercase only its local AAD value. Encryption and decryption use this identical procedure. Inputs/database values are never rewritten. Uppercase, lowercase and mixed-case representations of the same valid ULID intentionally encode identical AAD; another ULID still fails authentication. Case-only alteration must nevertheless fail authoritative binary database lookup/FKs where it differs from the stored ID. AAD equivalence is never authorization. Identity actor is declaring user; contact actor is contact user. No optional/null components. This binds record, tenant, actor, purpose and all relevant crypto/content versions without ambiguous delimiter concatenation.

Generate a fresh CSPRNG nonce for every encryption/re-encryption; never derive it from IDs/time or reuse with the same key. Use the library's complete combined-tag output; never truncate tag or release unauthenticated plaintext. Algorithm version cannot be changed by envelope input. Rotation produces fresh nonce and revision, authenticating the new header and same logical binding. Envelope is not an anti-rollback oracle: comparing to authoritative revision protects ordinary stale writes, but restoring a whole old DB needs external recovery/revocation controls.

### 3.3 Failure and migration behavior

Encrypt/key/entropy/serialization failure aborts the enclosing synthetic future transaction with no row/pointer/audit success committed. Decrypt authentication failure, missing/disabled key, wrong context, malformed/oversized data all produce a generic unavailable result to callers; internally only bounded error codes and safe row/request refs. Never log envelope, key, input, last-four, exception arguments or plaintext; no fallback to ordinary profile fields. Missing key never means delete/reset NIN or mark syntax verified. Existing B3.1 summaries should remain available without decrypting identity fields.

Rotation: authorize operator, inventory key references, keep old key decrypt-only, decrypt/authenticate with authoritative binding, encrypt under active new key, CAS `envelope_revision` and update envelope/key/version/revision atomically. Content version, declarations, historical references remain unchanged. Checkpoint only row IDs/revisions; retry conflicts from fresh data; read back/authenticate before retiring old key. Mixed-key rows are supported by explicit `kid`. Retain old recovery keys while any retained backup needs them. Never run a plaintext bulk export/backfill. Legacy APP_KEY fields are not silently imported as NIN. Any future algorithm/DEK wrapping change is a separately approved v2 migration.

## 4. Key-management and recovery contract

All recommendations here are **PROPOSED**; real operator identities, custody system and recovery readiness are **NOT VERIFIED / REQUIRES APPROVAL**.

| Domain/decision | Recommended boundary | Approval/evidence |
|---|---|---|
| NIN encryption | Dedicated 32-byte CSPRNG key; owner designates security custodian and independent recovery custodian. Sensitive service has use permission, not administration. | Named custodians, access review and production custody choice before real collection. |
| Phone encryption | Independent 32-byte key and resolver domain; same AEAD format, phone-specific AAD. | Approve separate domain; no APP_KEY reuse. |
| OTP digest | Independent 32-byte HMAC-SHA-256 key; short-lived challenge use; no NIN/contact key derivation. | Approve key lifecycle and disable old challenges on compromise. |
| Contact fingerprint | Independent 32-byte HMAC-SHA-256 key; no raw SHA phone hash. Private equality identifiers never merchant-visible. | Approve stable epoch and transition/drain procedure. |
| Development/test interface | Constructor-injected in-memory key resolver with public, clearly synthetic test vectors/fixture bytes; forbidden outside testing. No keys generated during this task. Future tests require no real encryption keys. | Explicit limited crypto-test support permission. |
| Future production interface | Same domain/key-ID resolver contract, backed by a separately selected protected custody system; repository/DB/log/queue/environment dump must not contain keys. Avoid storing secrets in framework config cache. | Service procurement/configuration, host/process isolation and secret-delivery mechanism unresolved. No KMS is claimed. |
| Generation/storage | Future authorized cryptographic generator, independent random keys, no passwords/APP_KEY-derived material; key length validated. Key IDs nonsecret; no web/request setter. | Controlled provisioning procedure, never this review. |
| Rotation | New ID per new material; active encrypt/HMAC issuance vs old decrypt/verify-only status explicit. NIN/phone gradual CAS rotation; OTP old keys retained only through challenges or revoke them. | Approved schedule/incident triggers; proposed review every 90 days, no invented mandatory legal interval. |
| Backup | Encrypted DB backup separate from independently encrypted/access-controlled key recovery backup and registry/namespace manifest; dual-person release recommended. | Custodians, backup protection key custody, RPO/RTO and retention approved and rehearsed. Encryption keys must not be recoverable solely through APP_KEY. |
| Compromise | Disable new sensitive writes/sends; revoke affected OTP/proofs, preserve safe incident evidence; restrict decrypt, rotate domain keys, assess exposure and required notifications with counsel. | Incident owner, authority and process. Re-encryption does not erase prior exposure. |
| Lost/unavailable key | Fail closed, hold affected workflows; restore from authorized independent backup or record unrecoverable loss. Never invent recovered NIN or force verified state. | Recovery drill required; recollection only after lawful separate authorization. |
| Restore isolation | Synthetic backup only for B3.2-B. For future real restore, isolated deny-egress host, workers/mail/SMS disabled before restore, least privilege and approved custody access, replay latest revocation/hold ledger, validate manifest/keys/integrity before any service. | Real restore never automatically authorized by database possession. |

Fingerprint is hex lowercase HMAC-SHA-256 of LP(`xlipa-contact-fp-v1`) + LP(canonical target); 64 characters. Aggregate scope is a separate HMAC under the same fingerprint domain with distinct LP prefix `xlipa-otp-fail-scope-v1`, followed by merchant, actor, canonical target. Include key ID as registry metadata; do not include contact version or purpose in aggregate scope. All application-phone contact versions for the same actor/merchant/target share the window. This is not global uniqueness or proof of identity.

Fingerprint rotation cannot reset a live window: proposed simple controlled drain rather than an extra alias table. Pause issuance and verification, revoke outstanding challenges/proofs, wait at least 1,800 seconds from the final counted failure and 60 seconds from the final reserved send (or the longer approved optional quota horizon), then change epoch and re-fingerprint authorized contacts. Old referenced contact identity/fingerprint stays immutable; create a new contact version under the new epoch. Keep old keys for recovery. During compromise, suspend use until the drain/recovery conditions are met. No simultaneous writable epochs. A zero-downtime dual-key merge would require separate design/approval. Namespace remains the original namespace on isolated restore; no live proof export into another deployment. Real restored proofs are held/revoked pending reconciliation with post-backup revocations.

## 5. OTP state machine

**PROPOSED protocol preserving OWNER-APPROVED values.** Exact binding `B=(merchant_id,actor_user_id,contact_id,purpose)`; current contact pointer and active owner/account must match on every action. One active-slot row per B. Aggregate failure scope excludes contact ID/version, challenge ID and purpose. Digest = hex HMAC-SHA-256 over LP(`xlipa-otp-code-v1`) + LP(challenge ID) + LP(merchant) + LP(actor) + LP(contact ID) + LP(content version) + LP(purpose) + LP(target fingerprint) + LP(fingerprint key ID) + LP(six-digit code). Constant-time `hash_equals`; preserve leading zeros. Future code generation is unbiased CSPRNG integer 0–999999 formatted to six digits. No raw code in database, jobs, audit or logs.

| Challenge state | Slot | Allowed next state / meaning |
|---|---|---|
| queued | 1 | dispatching, expired, revoked, delivery_failed. No digest yet. |
| dispatching | 1 | active on definitive acceptance; delivery_unknown on timeout/crash lease; delivery_failed on definite rejection; expired or revoked. Digest exists before external call. Verification denied until active. |
| active | 1 | consumed on correct code; exhausted on fifth counted wrong guess; expired; revoked (including replacement/contact change). |
| delivery_unknown | 1 | expired or revoked only. No proof, no automatic retry. Late callbacks cannot activate it. |
| consumed, exhausted, expired, revoked, delivery_failed | NULL | Terminal; no resurrection. Digest/key may be scrubbed under the narrow exception below. |

Intent state: `queued -> claimed -> accepted|rejected|unknown`; `queued -> cancelled`; `claimed -> cancelled` only for local cancellation before external invocation, otherwise unknown. All terminal; no retries of the same intent. One intent/challenge. Claim version fences workers. Delivery callbacks are advisory: exact same terminal outcome is an idempotent no-op; conflicting/late/foreign callbacks never reactivate a challenge. No provider callback implementation in B-SYN-1.

Issuance transaction: acquire SQLite writer first; read fresh owner/context/contact/account; materialize and prune aggregate scope row; reject if ten failures or resend cooldown; expire terminal-by-time rows; revoke replaceable existing challenge; insert new queued challenge and queued intent, set `last_send_at=now`, commit. Reserving a send starts the 60-second cooldown even if provider fails. Client retries with the same request UUID return the existing intent without a second reservation; fresh resend uses a new UUID and new challenge. Pending dispatch/unknown may be replaced after cooldown; replacement revokes predecessor, never resets failures. Replacing an in-flight challenge can produce an obsolete SMS; old code must remain invalid.

Five-minute expiry is exactly `created_at + 300,000 ms`, not provider receipt or callback time. Generation/claim near delivery does not extend it. Dispatch lease ends at `min(claimed_at+30,000,expires_at)`; expired lease becomes unknown. The 30-second lease is proposed operational timing requiring provider validation, not an owner-approved OTP parameter. Before a future network call, generate code transiently, persist digest/key and claimed intent/dispatching state atomically, then call outside transaction. Reacquire writer and CAS claim/challenge/current context before activating. Crash before/after send is conservatively unknown; generic failure, no proof. No plaintext durable send payload is introduced. Definitive acceptance only allows code entry; possession is proven only by correct code. Fake adapter can create only simulated fixture evidence in testing, never live-eligible proof.

Verification transaction: acquire writer; sample time; fresh authorization and exact binding/current pointers; require active and `now < expires_at`, failed_attempts <5, rolling failures <10. Invalid/replayed/expired/ineligible challenge is rejected without evaluating code or counting a new code guess. A malformed code presented to an otherwise eligible challenge counts as a wrong guess. Wrong guess atomically increments challenge attempts, appends rolling timestamp, increments versions, exhausts at five, commits a bounded audit. Tenth aggregate failure is recorded; subsequent verification (even correct code) and issuance are denied until the oldest retained failure ages out. Correct code atomically changes challenge to consumed, clears slot, inserts exactly one proof and audit. Failed proof/audit write rolls back consumption. Success/resend/restart do not clear aggregate history. Replay is denied and never creates another proof.

At aggregate recovery time, stale challenges stay exhausted/expired; user can resend subject to cooldown. No permanent account lock, automatic support bypass or manual proof grant. Generic masked/no-store responses; wider HTTP request flooding requires future additional limits. Actor/context/membership changes invalidate challenges/proofs in the same future lifecycle transaction; all uses also recheck live authority, protecting against missed cleanup. No new membership state is added to existing active/revoked enum.

## 6. Rate-window persistence algorithm

**PROPOSED exact rolling algorithm ROLL-10-1800-v1.** Scalar fixed counters or coarse buckets cannot meet the exact approved rolling boundary. Retain at most ten admitted wrong-guess timestamps in one scope row using `failure_01_at` through `failure_10_at`. No JSON extension dependency and no event table. This explicitly replaces TD's unspecified counter payload; it is an amendment to one of the seven candidates, not an eighth table.

Time is integer UTC Unix milliseconds from one trusted server clock abstraction; no browser/provider times. Sample only after writer acquisition; `t=max(clock.nowMs(),row.last_observed_at)`. Persist this value, including denials, so rollback of the wall clock cannot shorten a window. Forward clock jumps can shorten expiry; future production must monitor trusted clock drift, pause security mutations on unhealthy clock, and treat substantial correction as an incident. This runtime health mechanism is **NOT VERIFIED**. Isolated tests use a controlled clock. Comparisons use the same sampled t throughout a transaction.

1. `BEGIN IMMEDIATE` on the isolated SQLite connection before any security-dependent reads. Ensure FK enforcement enabled before transaction. Never nest a manual BEGIN in Laravel DB::transaction. A future tested transaction coordinator must own begin/commit/rollback and ORM writes on the same connection; bounded retries only for busy/conflict failures, with fresh authorization each time. Proposed busy wait 250 ms and at most three whole-transaction attempts; exhaustion returns temporary unavailability, never success. Existing DEFERRED default is not silently changed globally.
2. Create row if absent under writer ownership and unique PK; resolve target/fingerprint from authoritative current contact. Reject epoch mismatch. Compact timestamps strictly greater than `t−1,800,000` into ordered prefix; equality at lower boundary expires. Keep duplicates because concurrent failures can share a millisecond.
3. If ten remain, deny guess/issuance. Next eligibility is earliest timestamp +1,800,000, with exact equality eligible. Do not append rejected requests, extend a punitive cooldown or erase a timestamp.
4. For an eligible wrong guess append t in the first empty slot, update state_version by one, last_observed_at=t and challenge count in the same commit. Count is derived from occupied slots; no separate drifting counter/block flag.
5. Issuance requires `last_send_at IS NULL OR t >= last_send_at+60,000`. Persist reservation before leaving transaction; failed delivery does not refund it. Idempotent same-request replay does not reserve again. Maintain failure window across contact reversion/resends, not only the active challenge.
6. Updates use expected state_version with exactly one affected row, plus challenge/context versions. SQLite's single writer prevents lost concurrent updates; CAS guards stale input and portability. `lockForUpdate()` is not SQLite row-level protection. [SQLite transactions](https://www.sqlite.org/lang_transaction.html).
7. Cleanup prunes under the same coordinator. Never delete a row containing a failure in the active window or a send in cooldown. Proposed initial B-SYN-1 retains empty rows and minimizes payload; real retention/deletion policy requires approval. Restart never reinitializes stored slots. No cache eviction, TTL expiry or client action resets limits. A later safe row-deletion protocol must preserve clock/epoch high-water rules.

Bounded per row is not bounded total cardinality: future contact/request creation quotas and retention controls are necessary against row proliferation; no claim of comprehensive production abuse protection from only this rule.

| Additional proposed control | Initial recommendation; not owner-approved | Evidence/dependency |
|---|---|---|
| Scoped sends | 3 reservations/(rolling 15 min), 5/(rolling hour), same actor+merchant+target | Reasonable conservative pilot proposal given five-minute codes; needs usability/support validation. Requires separately approved bounded send timestamp storage (up to five slots if both enforced), not inferred from last_send_at. Excluded from v1 table below. |
| IP send requests | 20 requests/rolling hour, count accepted and denied requests | Shared NAT/IPv6 normalization/privacy review; proxy trust evidence. Additional scope/event storage required; not enabled by core schema. |
| Actor-wide | 10 send reservations/hour across merchants/targets | Pilot proposal against target churn; approve multi-merchant usability and scope design. |
| Target-wide | 10 send reservations/hour across actors/merchants | Pilot proposal; cross-tenant private service only, denial-of-service/SIM sharing review. |
| Global/provider spend | Default live-send cap zero until explicit currency-denominated daily budget, per-provider ceiling and capacity evidence are approved | No nonzero amount is justified without pricing, throughput, contract, retry/billing semantics and pilot demand. Atomic budget reservation/circuit breaker design is separate. |

No above proposal is silently treated as approved or supported by the seven-table core. Approving them later requires explicit scope/storage amendment; live SMS remains blocked until adequate wider controls and provider evidence exist.

## 7. Contact-proof lifetime and revocation

**PROPOSED policy `application-phone-proof-v1`; exact 30-minute duration REQUIRES APPROVAL.** Proof becomes valid at committed successful OTP consumption, not send/acceptance/callback. Store `verified_at` and `expires_at=verified_at+1,800,000` with policy version. Current ownership assertion usable only while `verified_at <= now < expires_at`, not revoked, unconsumed, exact current actor/merchant/contact/purpose, current active owner/account/context and eligible origin. Expiry is automatic by predicate; no job is required to deny an expired proof. A verification-time code with little OTP lifetime remaining still yields the proposed 30-minute proof, explicitly separate from five-minute code validity.

| Distinct fact | Lifecycle |
|---|---|
| Historical verification evidence | Immutable challenge/contact/actor/purpose/time/provenance. Remains a historical fact after expiry/revocation, without granting authority. |
| Current contact ownership proof | Short-lived scoped assertion above; cannot be extended by login, edit, pending review or resend. |
| Submission readiness | Future submission must atomically consume a fresh eligible proof, compare current pointers/context/profile/email and owner authority, and freeze evidence references. No grace period. |
| Pending review | Frozen evidence remains accepted as evidence of possession at submission for that review attempt; it grants zero continuing ownership authorization. No periodic OTP solely because review waits. Contact/owner/security change blocks current decision/activation until separately authorized fresh evidence. |
| Approval | Internal approval neither refreshes proof nor makes phone permanently verified. Proposed fresh proof within 30 minutes before a later contact-dependent activation decision; separate lifecycle approval needed. |
| Financial authorization / login MFA | Never supplied by this proof, even while fresh. Separate factors, purposes, schemas and policy required. |

Future submission consumption is one transaction: CAS proof `available -> consumed` with application tuple/timestamp, unique application use, audit and immutable submission binding. B3.3 submission bindings/actions remain excluded. Core columns merely reserve proof consumption state; synthetic fixtures can exercise it without implementing a submission action. Consumed proofs cannot be used for another attempt; information-request/resubmission requires fresh proof. Rejected/withdrawn workflow does not refund it.

Revocation: contact change retires old version, switches context with increment, revokes outstanding challenges and available/consumed proofs for old version with reason `contact_changed`. New owner never inherits old actor proof: revoke with `owner_changed`, require new actor contact/version/proof. Existing membership has no suspended state; if account is suspended or membership ceases active (revoked now; suspended only if separately introduced), deny immediately and revoke with `authority_lost`. Reinstatement never resurrects old proof. Security compromise/recovery uses `security_incident`; policy retirement uses `policy_retired`. Explicit operator invalidation uses `operator_revoked` under future reviewed permissions, never a grant/override path.

Proof immutable fields survive revocation, including any consumption reference. Revoked is terminal, with original consumption metadata retained. Revocation audit has safe references/reason/time in the same future transaction; repeated identical revocation is no-op. Revocation does not erase a frozen application's historical evidence. Later submission bindings must copy exact nonsecret IDs/versions/time/provenance and an authorized encrypted email snapshot, never NIN plaintext; that table is not added here. Historical integrity and current eligibility must be separate queries.

Pending owner decisions: approve 30 minutes/no grace; accept submission-time evidence throughout unchanged pending review; approve fresh contact proof before contact-dependent activation; approve owner/membership/security revocation policy and support recovery; approve policy effective date/retention. Schema stores expiry explicitly and therefore does not depend on a mutable configuration retrospectively changing old proofs.

## 8. Seven-table schema design

All columns/constraints/transitions in this section are **PROPOSED / REQUIRES APPROVAL**. This is a specification, not a migration or claim of existing schema.

### 8.1 Common contract

Types: `U` = TEXT ULID of length 26 (ASCII Crockford alphabet, first character 0–7), with storage casing determined by the referenced identity, never by AAD normalization. New independent B3.2 record PKs and their new-record FK columns remain uppercase. Every `merchant_id` across all seven tables, including the context PK, and nullable `consumed_application_id` preserve exact existing parent bytes and accept structurally valid uppercase/lowercase/mixed-case ULIDs; a case-altered FK still fails binary parent matching. Context merchant PK is also an existing-parent FK. New-record links `current_identity_id`, `current_contact_id`, `contact_id`, `challenge_id` keep their uppercase parent representation. Existing audit/merchant/application PKs and historical FK values are untouched. No storage normalization, NOCASE collation or new case-insensitive index is permitted. `I` = INTEGER matching existing users.id; `P` = INTEGER with `typeof='integer' AND value>=1`; `N` = INTEGER with type check and >=0; `T` = INTEGER UTC Unix milliseconds, type integer and >=0; `S(n)` = TEXT, `typeof='text'`, length 1..n, ASCII identifier where specified; `E` = TEXT envelope, length 1..4096. Nullable shown by `?`; **all other columns NOT NULL**. No default unless written. SQLite VARCHAR alone does not enforce a bound; explicit CHECKs are required. Every PK explicitly NOT NULL. Numbers/versions bounded to signed 64-bit, reject overflow before increment; never silently cast floats/numeric-looking strings. Timestamps of NULL use explicit `IS NULL` predicates; do not rely on CHECK expressions returning NULL to reject bad rows.

Every U/I merchant/user reference has FK to corresponding existing PK, ON UPDATE RESTRICT / ON DELETE RESTRICT. Child composite FKs also RESTRICT; each parent tuple has the exact UNIQUE index and binary collation. No cascade deletion. All new evidence rows reject ordinary DELETE using narrow triggers; dropping fixture tables for synthetic down is separate. `verification_limit_state` also rejects DELETE in v1, so cleanup only compacts. No hidden additional `created_at/updated_at` columns beyond those listed; models must not auto-expect them. All child FK tuples get an index unless a listed unique/index already covers that ordered prefix. [SQLite composite FK requirements](https://www.sqlite.org/foreignkeys.html).

New-table INSERT/UPDATE triggers enforce immutable columns, allowed transition edges, row_version increments and terminal no-resurrection below. CHECKs enforce row shapes. Triggers cannot supply authorization, trusted time, cryptographic correctness or external provenance. Those remain future action/crypto-boundary responsibilities. Narrow triggers are explicitly proposed scope, not assumed Laravel model protection. Raw SQL acceptance tests must cover both. Where a FK has nullable components, explicit all-null/all-present checks are mandatory.

### 8.2 `merchant_identity_records`

| Column | Type/default | Contract |
|---|---|---|
| id; merchant_id; version | U; U; P | PK id; tenant/content version |
| subject_claim | S(32) | CHECK in unknown, individual_owner, authorized_representative; declaration, not verified legal role |
| nin_ciphertext; encryption_key_ref | E; S(64) | v1 envelope and nin-domain ID |
| envelope_version; envelope_revision | P; P default 1 | envelope_version=1; independent crypto revision |
| syntax_policy_version; syntax_checked_at | S(50)?; T? | both NULL or both present; no official syntax assertion by default |
| declaration_version; notice_version | S(50); S(50) | version identifiers, no declaration body/PII |
| declared_by_user_id; declared_at | I; T | server-owned declaring actor/time |
| state; row_version | S(16) default current; P default 1 | current or superseded |
| superseded_at; retention_due_at | T?; T? | supersession shape; no retention default |

UNIQUE `(id,merchant_id)`, `(merchant_id,version)`; partial UNIQUE `(merchant_id) WHERE state='current'`. Index `retention_due_at` partial nonnull; declaring-user FK index. CHECK superseded iff superseded_at nonnull and >=declared_at; retention_due_at NULL or >=declared_at; syntax_checked_at NULL or >=declared_at. No NIN equality fingerprint/index/unique constraint.

State: inserted current; current -> superseded only; no current restoration. All logical content/actor/version immutable at insertion. Permitted updates: crypto quartet only with envelope_revision old+1 and same envelope_version v1; syntax metadata immutable once set (optional NULL->paired syntax result under future validator); retention_due_at under future approved retention decision; supersession edge; row_version old+1 on every update. No new independently verified-identity state. Sensitive classification: restricted identity ciphertext; declarations/actor linkage private. Audit `identity.recorded`, supersession/rotation via `identity.changed` reasons below. DB cannot verify ciphertext/AAD; future service must.

### 8.3 `merchant_contact_versions`

| Column | Type/default | Contract |
|---|---|---|
| id; merchant_id; actor_user_id | U; U; I | PK id and tenant/actor |
| purpose; version | S(50); P | purpose exactly application_phone |
| phone_ciphertext; phone_key_ref | E; S(64) | dedicated phone AEAD |
| envelope_version; envelope_revision | P; P default 1 | version=1 |
| target_fingerprint; fingerprint_key_ref | TEXT; S(64) | exactly 64 lowercase hex; domain ID |
| state; row_version | S(16) default current; P default 1 | current or retired |
| created_at; retired_at | T; T? | retirement >= creation |

UNIQUE `(id,merchant_id,actor_user_id,purpose)`, `(merchant_id,actor_user_id,purpose,version)`; partial UNIQUE `(merchant_id,actor_user_id,purpose) WHERE state='current'`. Index `(fingerprint_key_ref,target_fingerprint)` restricted service access, actor FK index. No global target uniqueness. Current -> retired only; retired iff retired_at nonnull. Logical contact/fingerprint/key epoch immutable; crypto-only updates increment envelope revision and row_version. Changing target/actor/purpose creates new content version; key epoch drain creates new version. Classified restricted contact/equality data; no decrypted default model serialization. Audit contact.created/contact.changed. Current context may select only a current version through future pointer action and trigger checks, not merely a valid FK.

### 8.4 `merchant_onboarding_contexts`

| Column | Type/default | Contract |
|---|---|---|
| merchant_id | U | PK/FK merchants |
| context_version | P default 1 | parent CAS |
| current_identity_id | U? | optional identity pointer |
| current_contact_id; contact_actor_user_id; contact_purpose | U?; I?; S(50)? | either all NULL or all nonnull; purpose application_phone |
| updated_at | T | server time |

FK `(current_identity_id,merchant_id)` -> identity `(id,merchant_id)`; FK `(current_contact_id,merchant_id,contact_actor_user_id,contact_purpose)` -> contact matching unique tuple; index both child tuples. Nullable pointers mean not ready, not permission to infer/backfill. Future pointer insert/update triggers reject noncurrent referenced records; actions must also reconcile retirement/supersession and switch pointers atomically. Existing pointer may transiently reference an old version within the same serialized replacement transaction; readiness must recheck final state. Trigger on pointer write alone does not prove other referenced rows never change later.

States derived: empty, identity_only, contact_only, both; all four transitions allowed under authorized CAS, context_version old+1, updated_at nondecreasing. Merchant ID immutable. Every pointer change revokes dependent current authority as appropriate. Classified private readiness linkage; not a verified flag. Audit domain events in same transaction with shared request ID; no duplicate context audit required. No application pointer or submission binding added.

### 8.5 `phone_otp_challenges`

| Column | Type/default | Contract |
|---|---|---|
| id; merchant_id; contact_id; actor_user_id; purpose | U; U; U; I; S(50) | PK id, exact B; purpose application_phone |
| active_slot | INTEGER? | 1 or NULL, state-derived |
| state; state_version | S(32) default queued; P default 1 | states section 5 |
| code_digest; digest_key_ref | TEXT?; S(64)? | both NULL or both present; digest 64 lowercase hex |
| failed_attempts | N default 0 | <=5 |
| created_at; expires_at | T; T | expires_at=created_at+300000 |
| digest_created_at; terminal_at | T?; T? | phase timestamps |
| transport_mode | S(16) | simulated or live, immutable |

FK exact contact tuple -> contact unique tuple. UNIQUE `(id,merchant_id,contact_id,actor_user_id,purpose,transport_mode)` for child lineage; UNIQUE `(merchant_id,contact_id,actor_user_id,purpose,active_slot)` (multiple NULL allowed). Index `(state,expires_at)`, actor FK index. No plaintext code or target stored.

Row shapes: queued requires paired digest NULL, digest_created_at NULL, attempts0, terminal_at NULL, slot1. dispatching/active/delivery_unknown require digest/key/time present, `created_at<=digest_created_at<expires_at`, slot1, terminal_at NULL. dispatching/unknown attempts0; active attempts0..4. Terminal states slot NULL and terminal_at>=created_at; exhausted attempts5, consumed attempts0..4; other terminal states attempts0..5. consumed/exhausted require digest_created_at present; digest pair can be scrubbed together only after terminal, retaining digest_created_at. Other terminal states may have no digest if never dispatched. No state change rewrites expiry/created/binding/mode; failed count monotonic, increases exactly one only for wrong guess in active->active/exhausted. Success does not increment. Transition to consumed requires old active. States section 5 are enforced by transition trigger; per-row CHECK is not enough. Restricted security data; no generic logs. Audit request/verification/change, denials as below.

### 8.6 `otp_delivery_intents`

| Column | Type/default | Contract |
|---|---|---|
| id; merchant_id; challenge_id; contact_id; actor_user_id; purpose | U; U; U; U; I; S(50) | PK id; exact challenge lineage |
| transport_mode | S(16) | simulated or live |
| request_id | TEXT | canonical UUID, server-scoped idempotency |
| state; claim_version | S(16) default queued; P default 1 | section 5 states/fencing |
| requested_at; claimed_at; lease_expires_at; completed_at | T; T?; T?; T? | state shape |
| failure_code | S(50)? | only provider_rejected, provider_unavailable, ambiguous_timeout, dispatch_cancelled, expired_before_send |

Composite FK `(challenge_id,merchant_id,contact_id,actor_user_id,purpose,transport_mode)` -> challenge unique tuple. UNIQUE challenge_id; UNIQUE `(merchant_id,actor_user_id,request_id)`; index `(state,lease_expires_at)` and actor FK index. Queued has all later times/failure NULL. Claimed has claimed_at>=requested_at, lease_expires_at>claimed_at, completed_at/failure NULL. Terminal accepted/rejected/unknown requires claim/lease and completed_at>=claimed_at; accepted failure NULL; rejected requires provider_rejected/provider_unavailable; unknown requires ambiguous_timeout/provider_unavailable. Cancelled requires completed_at>=requested_at and dispatch_cancelled/expired_before_send; claim+lease both present or both absent. Trigger validates lease bound against challenge expiry, full transition edges and claim_version old+1. Accepting a result cannot itself create proof; late callback never changes terminal state. All identity/request/mode/requested_at immutable. No provider reference, message body, ciphertext provider payload or credential fields in v1 (explicit minimization of earlier optional candidate). Private security/transport lineage. Audit otp.changed for terminal delivery transitions; no provider integration.

### 8.7 `contact_verification_proofs`

| Column | Type/default | Contract |
|---|---|---|
| id; merchant_id; contact_id; challenge_id; actor_user_id; purpose | U; U; U; U; I; S(50) | PK id; exact lineage/purpose |
| channel; provenance | S(16); S(16) | channel sms; provenance simulated or live |
| policy_version | S(50) | application-phone-proof-v1 |
| verified_at; expires_at | T; T | expires_at=verified_at+1800000 under proposed v1 |
| state; state_version | S(16) default available; P default 1 | available, consumed, revoked |
| consumed_at; consumed_application_id | T?; U? | both NULL or both nonnull |
| revoked_at; reason_code | T?; S(50)? | paired; section 7 reasons |

FK full challenge tuple including provenance -> challenge transport_mode tuple; direct exact contact FK; FK `(consumed_application_id,merchant_id)` -> existing applications `(id,merchant_id)`. UNIQUE challenge_id; UNIQUE `(id,merchant_id,actor_user_id)`; UNIQUE consumed_application_id (NULL allowed), meaning one core phone proof per attempt; indexes `(merchant_id,actor_user_id,purpose,expires_at)`, contact tuple, consumed application tuple. Available requires consumption/revocation NULL. Consumed requires consumed_at in `[verified_at,expires_at)` and application nonnull, revocation NULL. Revoked requires revoked_at>=verified_at and reason enum, may retain prior consumption pair; if pair exists it obeys same consumption bounds. New insert only available; transition trigger permits available->consumed/revoked, consumed->revoked, never restoration. Immutable all original evidence/binding/time/policy/provenance. Trigger on proof insert requires referenced challenge consumed with matching terminal_at=verified_at and matching digest-created lineage; tests still must show correct-code authorization because SQL cannot prove a code was checked. Simulation cannot become live by relabel/update. A production eligibility predicate must reject simulated rows regardless of fake fixture service claims. Proof state does not store expired; time determines current eligibility.

Private possession history; audit otp.verified on creation and contact.proof_changed on consumption/revocation. No submission action is authorized by the reserved consumption columns. A later B3.3 frozen binding is still required.

### 8.8 `verification_limit_state`

| Column | Type/default | Contract |
|---|---|---|
| scope_key; scope_key_version; policy_rule | TEXT; S(64); S(50) | composite PK all three; scope_key 64 lowercase hex; version is fingerprint key ID; rule otp.fail.rolling30.v1 |
| merchant_id; actor_user_id | U; I | real FK tenant/actor, never fake global merchant |
| state_version | P default 1 | CAS |
| last_observed_at; updated_at | T; T | same sampled clock value on update |
| last_send_at | T? | latest reserved send, <=last_observed_at |
| failure_01_at … failure_10_at | ten separate T? columns | ordered occupied prefix, <=last_observed_at |

UNIQUE `(merchant_id,actor_user_id,scope_key_version,policy_rule,scope_key)` for explicit association; scope PK remains authoritative. Index `(policy_rule,updated_at)` and actor FK. Ten pairwise CHECKs: a nonnull later slot implies predecessor nonnull and later>=earlier; first slot unconstrained by predecessor. At most ten by structure. All occupied timestamps >=0 and <=last_observed_at, updated_at=last_observed_at. Scope identity/tenant/actor/key/rule immutable. No cached count, window_start, blocked_until or reset field. Derived states empty/partial/full, all may transition by prune/append; state_version old+1, clock never decreases. Aggregate key omits purpose/contact/version; table rule permits only this core scope, no actor/IP/global rows. FK cannot establish HMAC correctness: resolver and transaction code must. No target/ciphertext stored in this table. Security-sensitive correlation data; no merchant query endpoints. Denial audit only when an eligible request creates the tenth failure, not every flood denial. No independent event table. Optional wider quotas require later amendment.

### 8.9 Integrity, ordering and deletion boundaries

Creation order: identity/contact, context, challenges, intents/proofs, limit state; audit compatibility slice may run before synthetic event fixtures. Drop children before parents: proofs/intents, challenges, context, identity/contact, limit state, then audit down only if compatible. Synthetic fixture teardown may drop tables with no real data. No production down or retention engine authorized. Tenant-null bypass, reused active slot, bad state, duplicate proof, invalid integer/version, mismatched provenance and cross-tenant application association must fail in raw SQL, not just validation. Key IDs/envelopes are structurally bounded; crypto validation remains at the service boundary.

Existing membership/user/merchant changes cannot automatically be prevented by new child CHECKs. Future shared lifecycle transactions plus fresh authority reads are mandatory; B-SYN-1 does not silently edit existing revocation actions. Logical evidence rows are retained; terminal digest scrubbing is not evidence deletion and does not erase rolling timestamps. Real retention, erasure/holds and independent recovery remain unapproved.

## 9. Audit events and compatibility

**PROPOSED minimal core extension:** retain all eight original values exactly: merchant.created, membership.revoked, application.submitted, application.reviewed, platform_role.granted, platform_role.revoked, account.status_changed, merchant.status_changed.

| New identifier | Allowed reason_code for that event |
|---|---|
| identity.recorded | declared |
| identity.changed | superseded, crypto_rotated, syntax_checked |
| contact.created | target_declared |
| contact.changed | retired, crypto_rotated |
| otp.requested | initial, resend |
| otp.changed | dispatch_accepted, dispatch_rejected, delivery_unknown, expired, exhausted, revoked, aggregate_exhausted |
| otp.verified | code_matched |
| contact.proof_changed | consumed, contact_changed, owner_changed, authority_lost, security_incident, policy_retired, operator_revoked |

No future wallet/payment/team/KYC-review/provider-webhook events. Not every wrong guess becomes durable general audit noise: failure slots/challenge count are security accounting; otp.changed captures exhaustion/terminal changes. Transport claims/duplicate callbacks need not generate individual audit entries. Revocation reasons for challenge may use `revoked` with state transition and shared request correlation; proof event carries specific invalidation reason. No plaintext error/provider response reason.

Safe references: existing actor_user_id, target_user_id where truly a different affected user, merchant_id, optional existing application_id only when a real related application exists, request_id UUID, server occurred_at (converted from ms to existing timestamp type). New events use only existing metadata keys: `from`, `to` = bounded state enum where relevant; `profile_version` only if it actually denotes merchant profile version; `reason_code` above. Never overload profile_version with crypto/context versions or fake an application ID. Resource linkage uses shared request_id (intent request ID) plus safe merchant/actor; this bounded slice does not provide a dedicated identity/contact resource FK. Exact object-level audit lookup would require another explicit typed audit amendment. No unapproved resource_id metadata key.

Audit allowlist and 2,048-byte scalar restriction remain; never record NIN/phone/code, digest/fingerprint/ciphertext/nonce, key bytes, URLs/tokens, provider payloads or human free text. Nonsecret key ID stays in restricted row/operator recovery manifest, not generic metadata. Operator key administration needs separately controlled operational records, not invented runtime audit actors.

Future success state mutation and audit INSERT share one transaction/connection; audit failure aborts mutation. A countable failed guess must commit its counters despite returning a verification failure response; do not throw inside the transaction in a way that rolls back abuse accounting. DB-busy/uncommitted operations produce no false success. Provider network remains outside transaction; outcome recording/audit is a second fenced transaction.

Compatibility proposal: additive new migration reconstructs audit table with union of old+new event CHECK values and unchanged columns/FKs/indexes/rows; synchronized PHP AuditEvent additions. No editing old applied migration, writable_schema hack or rewriting audit history. Rebuild rehearsal must preserve original raw metadata/snapshot bytes, IDs, times and all indexes. Verify actual sqlite_master dependencies/SQLite version in the disposable fixture before implementation chooses exact rebuild SQL. Use FK-on transactional create/copy/validate/drop/rename with a reviewed dependency strategy; if a dependency prevents this, stop for concrete SQL review rather than disabling FK enforcement globally. Existing source shows no child audit FK; live schema has not been queried. Inject copy/rename failures to prove rollback. No upgrade bypass of enum validation.

Down is permitted only in synthetic fixtures: if any event outside the old eight exists, refuse downgrade, do not delete/coerce it. Otherwise reconstruct original CHECK preserving every row and indexes; model enum compatibility must match deployment order. Production rollback is a separate forward-fix/recovery decision. Model append-only hooks remain; no claim of privileged database tamper resistance.

## 10. Synthetic acceptance tests

**DESIGNED ONLY — NOT EXECUTED.** Future authorization must name limited fixture/service test support, not expose real routes/actions/worker/provider. Use disposable `:memory:` for ordinary constraints; multi-connection concurrency uses a unique isolated temporary SQLite file with synthetic data. Reject effective DB_URL, cached working config, canonical working path and any real data/secret/provider network before any schema operation. No real NIN, real phone, live keys, SMS/email or working database writes. Use opaque `SYNTHETIC-NIN-A` for crypto (syntax not under test) and a reserved fake-contact normalization fixture which cannot reach a provider. Public deterministic fixture key bytes are test material only; never provision a real key.

| ID/area | Future test and required assertion |
|---|---|
| M1 up/down/reapply | Populate synthetic old-schema users/merchant/memberships/application/audit; additive up; compare all old IDs/raw values, FK/index/trigger definitions; old schema-only down succeeds; reapply succeeds. New audit event fixture makes old-enum down refuse safely. |
| M2 preservation/failure | Inject migration create/copy/index/rename failure; complete rollback, no orphan/partial audit table or changed old data. Foreign keys stay enabled; integrity and foreign_key_check on fixture only. |
| D1 raw constraints | Each NOT NULL, type/length/enum/check, invalid time/null pair, negative/float/version0, duplicate content version/active slot/challenge proof/application use rejected via SQL. Every partial-null context tuple tested. |
| D2 tenancy/state | Cross merchant/actor/contact/purpose/provenance/application tuple rejected; immutable mutation/terminal revival/deletion rejected. Same target across merchants allowed but proof transfer rejected. Current pointer to retired record denied. |
| C1 encryption | Public synthetic key round-trip, leading-zero byte preservation, different nonce each encryption. Verify wrong key, tampered ciphertext/tag/nonce/header, wrong merchant/actor/purpose/record/content/envelope revision fail with no output. Invalid base64/unknown algorithm/oversize fail. |
| C2 rotation/recovery | Old/new keys mixed, successful CAS rotation, conflicting revision, interruption/checkpoint resume, missing/disabled key; backup independent fixture DB/key registry, restore isolated, verify decryption. Lost fixture key fails closed; APP_KEY changes do not recover or affect NIN. Logs/error/output inspection contains no input/envelope/key/digest. |
| O1 challenge guesses | Exactly five wrong guesses exhaust challenge; sixth denied without new evaluation/count. Leading-zero correct code works. Correct code at expiry equality rejected. New challenge never clears aggregate failures. |
| O2 aggregate | Two challenges admit ten wrong guesses in 30 min; tenth stored and further guesses/sends denied. At oldest+1800000 equality it falls out. Boundary burst straddling fixed-window boundary still blocks; equal-ms entries count separately. |
| O3 resend | At 59999 ms denied; at 60000 allowed; old challenge revoked, new queued, same target window preserved across contact retirement/re-creation. Idempotent same request creates no second intent/reservation. |
| O4 concurrency | Two independent SQLite connections/controlled barriers: simultaneous fourth/fifth/sixth guesses, ninth/tenth/eleventh aggregate guesses, two correct-code consumes, two resends. Exactly permitted winners; no lost timestamps, two active slots, duplicate proofs or double reservations. Busy retry exhaustion safe. In-memory single connection cannot prove this. |
| O5 transport | Definitive rejection/outage creates no proof, reservation not refunded. Timeout/crash before/after send yields unknown; no blind retry; dispatch lease expiry; duplicate/conflicting/late callback no-op; replacement cannot revive predecessor. No actual adapter/network. |
| O6 persistence/cleanup | Restart/reopen temporary DB retains failures, cooldown and expiry. Pruning keeps in-window timestamps, old challenge cleanup leaves history. Backward clock pins high-water; unhealthy forward clock disables mutations under future guard. Failure-key epoch drain cannot reset active windows. |
| O7 replay/fake | Consumed/expired/revoked/wrong-binding code denied; fake transport evidence remains simulated despite callback/request labels. Live eligibility always denies simulated proof, including after fixture backup/restore. |
| P1 expiry/binding | Proof invalid at exact expires_at, stale submission blocked, contact/actor/merchant/purpose/context mismatch denied, revoked membership/account/merchant state denies use. Reinstatement does not revive proof. |
| P2 consumption/history | Two consumers, one winner; replay/another application fails. Audit write failure rolls back consumption. Contact/owner/security revocation preserves frozen synthetic application references and original evidence, without granting current authorization. Pending-review elapsed time does not rewrite history. |
| A1 audit | Every old/new enum round-trips; raw SQL unknown event rejected, original eight remain accepted; metadata secret/nested/oversize payload denied by model contract; no false claim raw SQL enforces model allowlist. Atomic exhaustion/success/revocation events and bounded failure logging. |
| F1 failure injection | Entropy/key/unavailable crypto, digest failure, FK/audit/commit error, worker crash, stale CAS, clock failure, SQLITE_BUSY all deny sensitive success with correct committed accounting semantics. No plaintext fallback. |
| R1 regression | Existing authentication, signed verification/reset, active/auth_version, trusted host, scoped merchant access, role redaction and readiness policy suites on isolated fixture; no new production permission bypass. Compare protected inventory/hashes again. |

Backup/restore tests preserve post-backup revocation manifests or hold restored proofs; stale backup is not treated as current authority. Optional-quota tests are contingent on separate approved scope; no assertion they are implemented by last_send_at. Record actual extension/SQLite versions and test results only in a future authorized implementation report. No test count or pass is claimed here.

## 11. Outstanding owner/technical/provider/legal decisions

| Decision | Status / responsible evidence | Gate affected |
|---|---|---|
| AEAD/envelope/AAD/domain/key IDs/rotation and bounded resolver support | PROPOSED, technical + owner ratification; Sodium availability and synthetic rehearsal pending | A schema generation until accepted; B/C before use |
| ROLL-10-1800 storage, writer coordinator, exact states/triggers and UTC-ms representation | PROPOSED, technical + owner | A until deterministic contract accepted; performance/runtime tests before B |
| Thirty-minute proof/no grace, pending review and activation freshness | REQUIRES APPROVAL, owner + technical | A ratify v1 expiry; C/submission before real use |
| Minimal audit extension/rebuild/resource correlation limitation | PROPOSED, technical + owner | A generation scope; B rebuild evidence |
| Dedicated named custodians/production secret delivery/recovery/RPO/RTO | NOT VERIFIED, owner/security operations | Not inert A; B/C real operations |
| Exact NIN syntax/declaration/lawful processing/notices/retention/rights/transfers | NOT VERIFIED, official issuer evidence + qualified legal/privacy review + owner | C, not opaque synthetic A; no invented 20-digit/checksum rule |
| Phone normalization/TZ treatment/owner-change reauthentication and support recovery | Product/technical detail REQUIRES APPROVAL; no new collection flow authorized | Future real contact/submission actions |
| Provider identity, idempotency, callbacks, timeout/billing/capacity/cost | NOT VERIFIED, provider contract/security evidence + owner | D, not A |
| Extra quotas/global spend and cardinality safeguards | PROPOSED values, separate storage/scope approval | D and production abuse readiness |
| Production DB engine/clock/retention/tamper-resistant operations | NOT VERIFIED, deployment review | B/C/D; SQLite fixture results do not certify another engine |
| D13-1 product policy | OWNER-APPROVED already; legal exceptions/delivery evidence still unresolved | Future financial documents only; no new blocker for A |

This is a security architecture review, not penetration testing, legal advice, issuer confirmation or a production security certification. No live dependency is invented or claimed configured.

## 12. B3.2-B bounded scope and proposed amendments

Retain exactly the seven B-SYN-1 tables named in section 8. **No eighth table proposed.** Explicit amendments/refinements for approval:

1. Replace unspecified limit payload with ten nullable timestamp columns, server high-water time, tenant/actor FKs and one resend reservation timestamp; no scalar approximate rolling counter.
2. Freeze v1 AEAD envelope, separate phone/NIN domains, envelope revisions and narrow crypto-interface/test fixtures; no managed service/DEK-wrapping implementation.
3. Add exact state/row-version/expiry/consumption/provenance columns and narrow new-table CHECK/transition/immutability triggers. These are schema support, not executable onboarding lifecycle actions.
4. Reserve consumed proof/application FK for synthetic constraint testing, while leaving B3.3 submission bindings and actions deferred. Explicitly approve this refinement rather than implying it was in the prior column list.
5. Minimal eight-event audit extension, unchanged metadata allowlist, compatible audit-table rebuild and synthetic refusal-to-down checks; existing applied migrations stay untouched. Omit previously optional encrypted provider-reference fields from inert delivery intents.
6. Narrow fixture-only transaction/clock/crypto/state eligibility helpers and test scaffolding necessary to exercise contracts; no production challenge issuance/verification, pointer mutation or submission service. Testing helpers are forbidden outside testing. If implementation needs broader executable action code to prove a property, stop and propose that scope separately; do not hide it in a model/cast.

Optional send/IP/actor/target/global quotas are reviewed but **excluded** from this core persistence contract; adding their bounded timestamp storage/scope rows later requires explicit amendment. This core is not live-SMS-ready without those decisions.

Explicit exclusions: real NIN collection, live OTP/SMS/email, provider integration/adapters/workers, real merchant data processing, working database activation, merchant forms, application submission, Chief Admin review actions, financial/payment schemas, wallets/withdrawals, dashboards, team management, MFA, production deployment. Also excluded: optional profile columns, submission evidence tables, KYC uploads/findings, notification outbox/preferences, retention engines and document generation. No environment/APP_KEY/dependency/account/commit/push/deploy changes.

## 13. GO/NO-GO matrix

| Area | Recommended decision | Evidence | Remaining approval | B3.2-B blocker? |
|---|---|---|---|---|
| A: encryption design | Select section 3 v1 | PHP maintained API + installed framework manifests/source | Ratify envelope/resolver/test scope; future extension test | Yes until selected; production KMS procurement is not an A blocker |
| A: exact rolling window | ROLL-10-1800-v1, ten slots | Section 6 algorithm, SQLite transaction model | Ratify persistence/serialization and trigger contract | Yes until selected; no eighth table needed |
| A: proof states/lifetime | Explicit 30-minute v1, single-use submission | Sections 7–8 | Owner lifetime and lifecycle decision | Yes for hard-coded v1 expiry contract until approved |
| A: schema/audit | Exact seven-table specification and compatible audit slice | Actual migration/model/enum inspection | Technical sign-off on constraints/support scope; separate explicit execution authorization | Yes until accepted |
| A: external collection/delivery | Keep fake-only/inert | No real module/provider verified | No real-use approval requested for A | No; do not import C/D dependencies into safe A |
| A: overall | **Ready to present for separate bounded authorization after ratification; NO-GO to execute now** | Concrete proposed choices, no implementation | Approve proposals/amendments and explicitly authorize isolated work | Current execution boundary remains |
| B: working database activation | **NO-GO** | No fresh schema/runtime/rehearsal results | Successful isolated migration/constraints/regressions, reviewed concrete migration/recovery/backup and explicit activation | Separate stage, not silently part of B3.2-B |
| C: real identity/contact | **NO-GO** | Legal/issuer/custody/operations and future collection actions unverified | Lawful processing, verified format/normalization, custody/recovery/security readiness, authorized collection flows and explicit permission | Real-use blockers; not automatically inert A blockers |
| D: live SMS/OTP | **NO-GO** | No provider integration/contract/capacity/budget or live abuse controls | Provider/security/legal/operations, wider quotas and cost ceilings, production proof provenance and explicit send permission | Real-send blockers; not inert A blockers |

All four architecture gaps now have deterministic **PROPOSED** resolutions. They remain approval blockers rather than unspecified technical alternatives. Accepting this document alone must not be inferred from silence, elapsed time or filename. No working schema or runtime approval is implied.

## 14. Preservation verification

Before writing, a read-only in-memory baseline hashed **188 existing files**: Git tracked/untracked nonignored inventory across the workspace plus explicit backend `.env` and working `database/*.sqlite*` files. SHA-256 contents were not printed for secrets; no .env content/APP_KEY was read into this document. This covers application/frontend/config/migrations/tests/existing reports and named working SQLite, with no dependency/cache/recovery-tree completeness claim for ignored directories. No separate inventory file was created. Initial Git status was clean. Baseline was captured after read-only source inspection and before the only write.

**VERIFIED post-write comparison:** all **188 baseline files matched SHA-256**, with **zero changed and zero missing**. The only added inventory entry was this requested Markdown file. `git diff --stat` was empty; `git status --short` listed only this file as untracked. Explicitly covered `.env` and working SQLite remained byte-identical. All 15 required numbered sections were present. A final comparison is repeated after inserting this result; the new document itself is outside the protected pre-existing baseline.

No runtime/database integrity, migration/test execution, real key generation, backup creation/restore, SMS/email, dependency install, account creation, commit/push or deployment was performed. Official documentation browsing was read-only. Preservation is file-content evidence within the stated inventory, not a claim that ignored dependencies/cache/recovery files or live runtime behavior were exhaustively audited.

## 15. Next explicit approval boundary

Owner review should explicitly ratify sections 3–9 and section 12 amendments, including thirty-minute proof policy, exact rolling interval, expiry semantics, narrow trigger/audit scope and synthetic helper boundary. Then separately authorize **B3.2-B isolated synthetic schema preparation only**: additive migration/model/enum/crypto-fixture/test artifacts, disposable SQLite rehearsal and acceptance evidence, with working SQLite and real identity/contact/provider operations prohibited.

If any schema-affecting choice changes, revise the proposed contract through a separately authorized document update before generating migrations. Production custody/legal/provider choices may remain pending while inert A proceeds after its own approvals. B/C/D each require later evidence and explicit authorization. **STOP here for owner review; no implementation has been authorized or performed.**
