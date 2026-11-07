# XLipa — R06 Option A Security Design

**OA-1.0 · 7 November 2026 · Africa/Nairobi · DESIGN ONLY**

## 1. Executive summary — Muhtasari

**PROPOSED:** persist a counted threshold failure and a bounded audit obligation in the same SQLite transaction; only afterwards attempt the final audit INSERT in a second transaction. Audit INSERT and obligation discharge are atomic together. An unresolved obligation blocks further OTP evaluation and related readiness operations for its exact actor + merchant + target scope. No eighth business table, general notification outbox, provider integration or production service is proposed.

Kwa Kiswahili: transaction ya kwanza itahifadhi failure count pamoja na wajibu wa audit unaosubiri. Transaction ya pili itaandika audit na kuondoa wajibu huo kwa pamoja. Audit ikishindwa, count haitarudi nyuma, na scope husika haitaruhusiwa kutathmini code nyingine mpaka audit ikamilike. Huu ni **muundo unaopendekezwa**, si code iliyotekelezwa.

**Minimum proposed schema delta:** five nullable fields on `verification_limit_state`; one supporting UNIQUE tuple on `phone_otp_challenges`; conditional pending-state integrity triggers; SQL append-only UPDATE/DELETE guards on existing `audit_logs`. Existing audit columns, enum meanings and metadata allowlist remain unchanged. All changes are **REQUIRES OWNER APPROVAL** before implementation.

**Important policy conflict:** the existing count + final audit same-transaction rule cannot remain literal for these two exhaustion cases while also preserving a count when its audit INSERT fails. Option A substitutes an atomic **count + durable obligation**, followed by atomic **final audit + discharge**. This permits delayed final audit visibility and an indefinite scope hold while audit is unavailable. **Owner authorization so far approves specification preparation only, not this policy amendment.** Success verification/proof consumption and their success audits retain their existing single-transaction behavior.

**VERIFIED** means inspected source/document/file evidence in this task; **PROPOSED** means this document's unimplemented contract; **REQUIRES OWNER APPROVAL** identifies a decision/execution gate. Historical executions are marked **REPORTED**, never a fresh pass. **REPORTED:** latest integrity report records 145 tests/1,432 assertions, including two diagnostics reproducing the R06 defect. No test, migration, runtime/database connection, backup or recovery operation is executed here.

GO for owner/technical review of this design. NO-GO for implementation, final R06 acceptance, B3.2-C, working activation or production until separately approved and tested. XLipa remains a website, supports TZS only, and gains no financial/currency functionality from this design.

## 2. Root-cause analysis and inspected evidence

### 2.1 What currently exists

**VERIFIED:** `backend/tests/Support/SyntheticVerification.php::verify()` increments challenge attempts and scope failure timestamps, then inserts `otp.changed` when the challenge reaches five failures or the scope reaches ten. The coordinator rolls the entire transaction back on an exception. The integrity test file's two `test_integrity_audit_outage_reproduces_*` methods reject that INSERT deliberately and assert that counts remain four/nine. The current code has no pending-obligation state or independent reconciliation path.

The review used the complete [integrity correction report](XLIPA-B3-2-B-INTEGRITY-CORRECTION-REPORT-2026-11-07.md), [final acceptance review](XLIPA-B3-2-B-FINAL-ACCEPTANCE-REVIEW-2026-11-07.md), [previous implementation report](XLIPA-B3-2-B-SYNTHETIC-IMPLEMENTATION-REPORT-2026-11-06.md), [SBR-1.1](XLIPA-B3-2-SECURITY-BLOCKERS-RESOLUTION-2026-11-06.md) and relevant technical decisions, current draft schema/audit migrations, AuditLog/AuditEvent, synthetic coordinator/crypto/helpers/tests and backend project guidance. No existing report/specification was edited. `.ai/rules` was absent at workspace/project/backend locations checked. No sub-agent was used.

**VERIFIED current contracts:** scope PK is `(scope_key,scope_key_version,policy_rule)`; merchant/actor identity is immutable; ten ordered nullable timestamps implement strict rolling failures; row versions are positive; ordinary DELETE is blocked. Challenge lineage binds contact/actor/merchant/purpose; proof history/chronology and migration guards contain the latest integrity corrections. AuditLog's metadata allowlist is scalar `from,to,role,previous_role,profile_version,reason_code`, with 2,048-byte bound; model hooks forbid audit update/delete. Existing audit primary key provides event-ID uniqueness, but current model hooks alone do not protect raw SQL.

Repository HEAD is `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`. Existing modified/untracked work was treated as baseline. Runtime versions/test outcomes in the reports are not reverified by execution. Working schema/migration ledger and real operational infrastructure were not queried.

### 2.2 Fault boundary

An audit-specific rejection can occur while the same database remains able to commit counters/obligations. Option A addresses this case by separating the commits. It cannot persist anything if the accounting database itself cannot commit. No local design can reconstruct acknowledged post-backup commits from an older backup alone. SQLite durability additionally depends on storage/journal/synchronous configuration and actual hardware; a successful in-memory test is not a power-loss guarantee. [SQLite atomic commit](https://www.sqlite.org/atomiccommit.html), [SQLite synchronous settings](https://www.sqlite.org/pragma.html#pragma_synchronous).

The protocol therefore distinguishes: (a) audit insertion unavailable but accounting commit works; (b) accounting commit failed/unknown; (c) restored/stale/corrupt storage. Cases b/c require fail-closed recovery handling, not pretending a failure was durably counted.

## 3. Proposed schema changes

**PROPOSED / REQUIRES OWNER APPROVAL — no migration generated.** The seven business tables remain exactly those already specified. Five fields below are added only to `verification_limit_state`. They hold **one current obligation per scope**, not arbitrary JSON, a list of events or a general-purpose outbox.

### 3.1 Five additional nullable columns

All default NULL. Existing scope key, actor/merchant, failure slots, state_version and high-water timestamps remain. No additional state/counter/lease/secret column is required.

| Column | Exact proposed type/shape | Necessity |
|---|---|---|
| `pending_audit_event_id` | TEXT NULL; uppercase canonical ULID, 26 ASCII Crockford characters, first 0–7; binary collation | Durable idempotency identity, generated once before count commit and reused by every reconciliation. Non-NULL means scope pending. |
| `pending_audit_challenge_id` | TEXT NULL; same uppercase new-record ULID contract | Safe immutable origin reference; permits tenant/actor FK and request-lineage validation without plaintext target/code. |
| `pending_audit_reason_code` | TEXT NULL; exactly `exhausted` or `aggregate_exhausted` | Reconstructs the existing audit meaning deterministically; no arbitrary reason/error text or new event type. |
| `pending_audit_request_id` | TEXT NULL; canonical lowercase UUID, length 36 with standard separators/32 hex digits | Freezes existing delivery-intent request correlation rather than worker retry identity. |
| `pending_audit_occurred_at` | TEXT NULL; canonical UTC `YYYY-MM-DD HH:MM:SS.mmm`, length 23, valid calendar instant | Freezes original failure occurrence with milliseconds. Later high-water updates/retries must not change event time. Compatible with SQLite's existing timestamp storage; no existing historical timestamp rewritten. |

Why not store more: event_type is always `otp.changed`; actor/merchant come from immutable scope columns; target_user_id/application_id are always NULL; metadata is the canonical one-key reason object. No retry counter or persisted claim is needed because reconciliation does no external I/O and runs within one serialized SQLite write transaction. No ciphertext, code, phone, fingerprint copy or key bytes are added.

Why one slot suffices: while occupied, **no further eligible code evaluation/send reservation/proof consumption may proceed in that scope**. Thus a second threshold event cannot be produced there before the first is discharged. This bound depends on enforcing the gate across all entry points, not merely the current verify helper. If future requirements allow continued verification while pending or buffer other event kinds, this single-slot design must be reviewed again.

### 3.2 Constraints, indexes and tenant lineage

1. Explicit CHECK: all five fields NULL **or** all five NOT NULL; no NULL-passing partial tuple. Validate TEXT/length/alphabet/UUID/calendar shape with the same precision as service validation. No implicit type/case coercion of stored identities.
2. UNIQUE `pending_audit_event_id` (NULLs permitted); partial index `(pending_audit_occurred_at,pending_audit_event_id)` where event ID non-NULL for bounded ordered scans.
3. Add supporting `UNIQUE(id,merchant_id,actor_user_id)` on existing `phone_otp_challenges`, needed for the exact parent tuple despite id already being globally unique.
4. FK `(pending_audit_challenge_id,merchant_id,actor_user_id)` → challenge `(id,merchant_id,actor_user_id)`, ON UPDATE/DELETE RESTRICT. Existing challenge→contact tenant/purpose relationships remain. Current scope's merchant/actor FKs stay unchanged.
5. Do **not** add an FK from pending event ID to audit_logs: an obligation must commit before that audit row exists. Discharge is controlled by an exact-match trigger/protocol instead.
6. Creation trigger validates an existing intent for that challenge with exactly the frozen request/merchant/actor; purpose remains `application_phone`. Service must also recompute the approved scope from authoritative contact/target/key epoch before creation; SQL cannot verify an HMAC or code match.

The partial scan index and supporting parent tuple are constraints/indexes within existing tables, not an eighth table. Existing PKs/FKs and default binary identity remain. Explicit new event IDs are stored as generated; no migration or Eloquent trait normalizes existing audit/merchant/application IDs.

### 3.3 Exact trigger transition contract

All scope updates retain the existing `state_version=OLD+1`, immutable identity and high-water/send-time rules. Define NULL equality with `IS`/`IS NOT`, never ambiguous `= NULL` expressions.

**CLEAR→PENDING:** require a new five-field tuple, no audit row already using that ID, and no existing pending slot to replace. After the counted write, reason `aggregate_exhausted` requires ten occupied current failure slots; reason `exhausted` requires an exhausted challenge at attempt five and fewer than ten scope failures. Last occupied timestamp equals the sampled failure time. The service verifies all retained timestamps are in the approved rolling interval, exact scope equality and exactly one new admitted wrong guess. Cross-row checks validate final shapes, not secret evaluation or the full previous challenge value by themselves.

Occurrence formatting is deterministic from the existing sampled integer `t=last_observed_at` at creation, not ULID time or retry time: UTC whole-second calendar rendering of integer floor(t/1000), followed by `.` and zero-padded `t mod 1000`. Format only representable valid UTC calendar instants; out-of-range clock data fails closed. A creation trigger validates the same conversion using integer whole seconds and millisecond remainder; it must explicitly reject a NULL/invalid conversion. This needs synthetic SQL/PHP equivalence tests before implementation approval is accepted.

**PENDING→PENDING:** the five-field tuple is immutable. Failure slots and last_send_at cannot change. Only existing monotonic last_observed_at/updated_at and state_version may advance for safe denied-request clock accounting; no pruning, reservation or code evaluation occurs.

**PENDING→CLEAR:** require exact unchanged failure slots/last_send_at and an exact matching existing audit row for the OLD tuple as specified in section 6. Clearing increments state_version but does not prune/reset counts, change challenge/proof state or rewrite clock history. All five fields become NULL together. Failure to establish match rejects discharge. No ordinary “force clear”, expiry, cancellation, DELETE or administrative waiver transition.

**CLEAR→CLEAR:** existing approved operations/rolling pruning remain possible with fresh authority and version checks. Only the normal protocol may append/prune failures; the pending mechanism is not a claim that arbitrary privileged SQL can prove correct accounting.

**Audit append-only constraint proposal:** add BEFORE UPDATE and BEFORE DELETE rejection triggers to existing audit_logs, aligning raw SQL with its current model append-only contract. No new audit columns/events/CHECK values; old rows untouched. These guards and their interaction with synthetic rebuild/rollback must be tested. Privileged DDL/trigger removal or replacing the entire database can still tamper with history; access controls/independent recovery evidence are separate requirements, not supplied by triggers.

### 3.4 Migration/rollback constraints

Future additive migration must use the guarded synthetic target and leave existing applied migrations unchanged. Prefer nullable additions and explicit indexes/triggers over a gratuitous audit-table rebuild; if SQLite requires a verification-state table reconstruction for the composite FK/CHECKs, review exact SQL and populated preservation/rollback before execution. No foreign_keys=OFF shortcut. Existing empty nullable rows represent no obligation, not evidence that unrecorded pre-upgrade failures were recovered.

Down must refuse if any pending slot exists. It must never discard obligations to satisfy rollback. Existing audit enum downgrade restrictions remain; final audit rows survive. Actual working activation is still a separate B3.2-C decision. No migration filenames/SQL artifacts are created here.

## 4. Transaction protocol

**PROPOSED / REQUIRES OWNER APPROVAL.** Both transaction types use the same primary SQLite database and writer coordinator, never an attached/cache database or external queue. BEGIN IMMEDIATE is acquired before security-dependent reads. It can return BUSY; only pre-evaluation acquisition may be retried with bounded waits. SQLite has one writer; lockForUpdate is not a row-lock substitute. [SQLite transactions](https://www.sqlite.org/lang_transaction.html).

### 4.1 Common preconditions and pending gate

Before every verification/issuance/resend/future proof-consumption operation: validate environment/schema/restore readiness and trusted clock; acquire writer; reload active account/auth context, current owner membership and merchant/contact/purpose bindings; derive the exact approved HMAC scope under the permitted key epoch; load/create the real scope row with tenant association validation.

**Before reading/comparing an entered code:** if pending event ID is non-NULL, deny generically, evaluate no digest/hash_equals, create no proof/challenge/send reservation and preserve failure slots. A denied request may advance only the existing monotonic clock/version fields. Clock/window expiry, a new request UUID, old-code replay, process restart or merely changing contact version cannot clear this gate. Same target/actor/merchant remains the same scope across versions.

Proof consumption is also gated for its exact authoritative contact scope; there is no new submission action. Current proof/OTP expiration continues normally during a hold; no grace or extension is added. Independent security revocation/invalidation must not be delayed just because this scope has an accounting-audit obligation; it must preserve the obligation and follow its separately approved audit rules.

A different legitimate target/actor/merchant is a different scope, not a transfer of this proof/history. No broad cross-merchant “unlock”. Wider actor/IP/spending policies remain separate. Shared SQLite contention/storage failure can affect availability globally; tenant isolation does not promise unlimited shared resources.

### 4.2 Normal wrong attempt below thresholds — T_count

1. With no pending slot, sample `t=max(trusted now,row.last_observed_at)` and prune only timestamps `<=t−1,800,000`. Require active exact challenge, `t<expires_at`, challenge failures<5 and rolling count<10.
2. Evaluate the six-digit input once (malformed eligible input counts wrong under existing policy), using the existing purpose-bound HMAC/constant-time comparison. No input/code/digest leaves the crypto/test boundary or appears in logs.
3. If wrong, increment challenge failed_attempts by one and append t once to the ordered scope prefix; update versions/time atomically.
4. If new challenge count<5 and new aggregate<10, there is no exhaustion audit/obligation under the existing event policy. COMMIT T_count; only after commit return the generic wrong-code result. No new audit-per-guess policy is added.

### 4.3 Fifth challenge or tenth aggregate failure — T_count

Perform steps 1–3 above. If new challenge count=5, transition to exhausted, clear active slot and fix terminal_at=t. If aggregate=10 while challenge count<5, leave challenge active but the aggregate rule prevents evaluation; no invented challenge terminal state.

Create one event ID and freeze all five obligation fields **in the same T_count** as both counters and any exhaustion transition. Select reason exactly as current code: if aggregate=10, use `aggregate_exhausted`; otherwise use `exhausted`. When fifth and tenth coincide, this is one aggregate_exhausted event, matching current precedence; do not create a second reason/event or use an ID derived only from challenge+reason.

COMMIT T_count **before attempting final audit insertion**. Then optionally invoke one immediate T_reconcile attempt on the saved ID. Return a generic denial/wrong-code response; no success/proof is possible for this wrong attempt. If reconciliation fails, return generic temporary unavailability without exposing whether a code was near a limit. Failure counters remain committed regardless of that second transaction's result.

At the fifth failure the old challenge stays exhausted after reconciliation. At the tenth, the rolling rule still denies while ten failures remain. Reconciliation is not quota recovery; only natural rolling pruning/new authorized challenges under unchanged rules can recover eligibility later.

### 4.4 Audit INSERT failure — T_reconcile only

T_reconcile is a separate short transaction detailed below. Constraint/trigger/model validation/INSERT failure rolls back **only that transaction**, leaving the committed counters, terminal state and five-field obligation intact. No verification-path savepoint catches/swallowing of audit errors inside T_count is required. No request loop repeatedly re-evaluates its code while trying the audit again.

Permanent payload collision/validation fault remains held and alerts operators through redacted operational status, not fabricated success. There is no retry limit that clears a slot. If the database cannot perform/read recovery transactions at all, verification is unavailable; do not assume audit-only failure.

### 4.5 Correct code and proof consumption

With no pending slot and all current eligibility checks satisfied, retain the existing atomic success transaction: challenge consumption + unique simulated/live-origin-appropriate proof + otp.verified audit; or proof consumption + its audit. On success-audit failure, all those success changes roll back. No obligation here authorizes success without its audit, and no simulated proof becomes live eligible.

### 4.6 Commit errors and retry discipline

Do not blindly reuse the current coordinator's whole-closure retry behavior after code evaluation. BEGIN IMMEDIATE acquisition may retry before evaluating; after evaluation, do not automatically compare the same submitted code again on a BUSY/COMMIT/connection error. Resolve a known committed threshold event by its saved event ID and authoritative persisted state using a fresh connection once healthy. An unknown outcome returns generic unavailability, never a guessed success.

If T_count failed before commit, its changes may be absent. No code-specific response is released, and the failed storage path must not admit continued evaluations until storage readiness is established. This five-field mechanism does not provide write-ahead reservations for **every** pre-commit comparison or general verification-request exactly-once processing. Guaranteeing that even an uncommitted computation is counted would require an additional reservation/accounting policy, **REQUIRES OWNER APPROVAL**, not a claim of Option A. Its concrete R06 guarantee applies to committed admitted failures when the subsequent audit transaction fails.

## 5. State-transition tables

No new persisted state string is needed; the tuple derives state. Existing state_version is reused.

| Derived scope state | Meaning | Allowed transition / atomic requirement |
|---|---|---|
| CLEAR | All five fields NULL | Normal operation; wrong threshold → PENDING in the same commit as count/terminal changes. |
| PENDING | All five fields present, immutable event payload | Hold eligible operations; retry reconciliation. Pending clocks may advance, not counts/send reservations. |
| PENDING → CLEAR | Exact matching final audit exists | Audit INSERT/match verification + tuple clearing + CAS commit together; counters unchanged. |
| CONFLICT (derived fault, not new DB value) | Event ID exists with different payload or invalid obligation lineage | Retain PENDING and deny; no overwrite/rekey/ignore/delete. Escalate incident. |
| STORAGE/RESTORE HOLD (deployment readiness) | Accounting unavailable or snapshot provenance incomplete | Deny before code evaluation; not a restored DB row flag trusted blindly. Separate recovery authority required. |

| Event/challenge condition | Obligation decision |
|---|---|
| Wrong count 1–4 and aggregate<10 | No exhaustion obligation; commit count. |
| Fifth wrong, aggregate<10 | exhausted state plus `exhausted` obligation. |
| Tenth aggregate, challenge count<5 | Existing active state plus `aggregate_exhausted` obligation. |
| Fifth and tenth together | Exhausted state plus one `aggregate_exhausted` obligation. |
| Pending request repeat/correct input/resend | No code evaluation or new obligation; same pending ID retained. |
| Reconciled event replay by stale worker | Reload row; absent saved ID is already completed/stale, not permission to recreate it. |
| Later distinct threshold on same still-valid challenge after legitimate window recovery | New committed occurrence gets a new saved ID; do not deduplicate all history by challenge+reason. |

No “expired obligation”, “discarded”, “cancelled”, “force cleared” or unaudited terminal state. Contact retirement, membership revocation and key rotation do not cancel historical audit debt.

## 6. Audit reconciliation algorithm

### 6.1 Frozen event contract

For pending tuple P on scope S, the expected audit row is exactly:

| Existing audit field | Frozen value |
|---|---|
| id | P.pending_audit_event_id, exact stored bytes |
| event_type | `otp.changed` |
| actor_user_id | S.actor_user_id (original actor, not reconciliation operator) |
| merchant_id | S.merchant_id, exact binary parent ID |
| target_user_id; application_id | NULL; NULL |
| request_id | P.pending_audit_request_id from the challenge's intent |
| metadata | Exactly `{"reason_code":"exhausted"}` or `{"reason_code":"aggregate_exhausted"}` with canonical bytes and unchanged allowlist |
| occurred_at | P.pending_audit_occurred_at, original UTC millisecond instant, never worker completion time |

No resource ID, counter dump, phone/target, fingerprint, ciphertext, secret, input or arbitrary error is added to metadata. Challenge/intent retention and merchant+actor+request correlation preserve existing bounded resource linkage; this is not a new object-specific audit FK.

**Timestamp compatibility requirement:** the writer must preserve canonical millisecond text in the existing SQLite timestamp column. Existing Eloquent serialization may format dates to seconds; therefore the future narrow writer must explicitly validate this fixed safe payload and bind the canonical string without accidental model truncation. Do not globally change old audit casts/date formats or rewrite history. This targeted writer contract/round-trip test is **REQUIRES OWNER APPROVAL** along with Option A.

### 6.2 T_reconcile — exact steps

1. A separately authorized internal reconciler, not a merchant-supplied event/payload endpoint, selects pending IDs. Discovery reads are only hints; open BEGIN IMMEDIATE and reload exact scope PK, event ID and state_version.
2. If row absent or ID changed/cleared, report stale/no-op; never reconstruct from stale task payload. If tuple malformed, hold/fail closed. Validate scope merchant/actor association and captured challenge/intent correlation. Do not require the original owner still active: the event records a past action even after later revocation/retirement.
3. Build the deterministic row above. Read audit_logs by exact binary event ID. If absent, INSERT exactly that row. No INSERT OR IGNORE, upsert, event renaming or generating a fresh ID on retry.
4. If present, compare **all** fields above exactly (including NULLs and canonical metadata/time), not just event_type/merchant. Matching row is already materialized; differing row is a collision/integrity incident. Abort without clearing or updating either record.
5. Update scope by PK + expected state_version + expected pending ID, all five fields NULL and state_version+1, preserving failure slots/send time/high-water values. Check one affected row. DB discharge trigger verifies the existing expected audit row before allowing this transition.
6. COMMIT. New audit and clearing happen together. Clear failure/rollback/connection loss leaves the audit insert uncommitted and the prior pending row intact, or both committed; reload rather than guess.

Reconciler does not need NIN/phone/OTP keys or plaintext. It must not derive a new target or transfer debt to another scope. A dedicated internal capability may read pending rows and insert only this fixed event payload; current merchant request permission is not enough. No such service/capability is configured now.

### 6.3 Retry/operational proposal

One immediate attempt after a new threshold commit; denied user requests do not become a retry amplifier. **PROPOSED initial synthetic/operational contract:** independent scan cadence five seconds, pages of at most 25 rows, keyset order by original occurrence/event ID, one attempt per row per scan. Continue pages fairly so one poison row cannot starve other tenants. This cadence/batch size is **REQUIRES OWNER APPROVAL**, not an approved OTP limit or deployed scheduler.

No persisted attempt counter/claim/lease: no external call occurs while the reconciliation writer lock is held; crash releases the transaction. A restart can rescan existing rows safely. Pending survives every retry and has no TTL; a missing scheduler causes unavailability, not audit deletion. Redacted alerts on first permanent validation/collision fault and proposed pending age≥five minutes; never attach sensitive payloads. Capacity/backlog and scheduling persistence need later operational evidence. No retry infrastructure is invented here.

## 7. Concurrency and crash recovery

### 7.1 Concurrent requests/workers

Two verification requests acquire one SQLite writer at a time and reload counts/pending/current authority. If the first commits the fifth/tenth failure plus obligation, the next sees PENDING before any code comparison. It cannot produce another fifth/tenth failure or proof. If reconciliation clears in between, unchanged exhausted/rolling-count guards still apply.

Two reconcilers serialize and reload. First may materialize+clear; second finds no matching pending ID and no-ops. Duplicate task execution cannot add another audit PK or a second failure count. A worker holding an old ID cannot clear a newer obligation because both state_version and saved ID are predicates. A different tenant's valid event ID cannot satisfy exact merchant/actor/intent/payload comparison.

No SQL row lock assumptions or external SMS/queue network call is introduced. Safe administrative invalidation preserves pending payloads; reconciliation never restores permissions or marks a contact verified.

### 7.2 Crash point matrix

| Crash/error point | Required persisted outcome / restart behavior |
|---|---|
| Before T_count commit, including after internal comparison | No partial count/obligation guaranteed; transaction rollback or recovery resolves it. No code-specific result acknowledged. Storage/unknown-outcome readiness rules apply; no automatic re-evaluation loop. |
| During T_count COMMIT | Either old consistent state or committed count+obligation, conditional on SQLite/storage durability. Reopen/recover and inspect; never assume success/rollback from a lost connection alone. |
| After T_count commit, before audit attempt/HTTP response | Count/terminal state plus pending survive. Restart scans obligation; replay sees gate and does not compare code again. |
| During audit INSERT | Reconciliation rolls back only its own changes. Count/pending from T_count survive. |
| After audit INSERT but before clear/commit | Transaction rollback leaves no newly committed audit and retains pending. |
| Clear rejected or CAS fails | Roll back audit insertion in the same transaction; do not clear or change failure counts. |
| After T_reconcile commit, before acknowledgement | Audit exists once and pending is clear; stale worker reload no-ops. Counters remain and still enforce limits. |
| Process restart with pending and no worker | Scope stays denied; no implicit pending reset. |

SQLite atomicity and correct storage recovery are prerequisites, not claims that this task ran power-loss testing. The existing coordinator's post-evaluation retry policy will require targeted revision/test after approval; code is unchanged now.

### 7.3 Restore from backup — explicit evidence boundary

**PROPOSED mandatory recovery hold:** every restored/replaced database starts with verification/issuance/proof-use disabled by independently controlled deployment readiness, before serving requests. Do not trust a `ready` flag or absence of pending inside the restored snapshot: it can predate later obligations/revocations. Startup does not turn historical simulated proofs into current live authorization.

| Restored evidence | Required response |
|---|---|
| Consistent snapshot contains pending count+obligation | Preserve it. Audit may be absent; normal idempotent reconciliation materializes it without erasing the count. |
| Snapshot includes committed audit+clear | It must also include the corresponding counters because this is one primary database and a consistent point-in-time backup. Still apply external freshness/revocation review. |
| Independently validated recovery material has the same audit row while snapshot retains pending | Compare complete payload; permit idempotent discharge only after lineage/recovery approval. No mixing arbitrary rows from different backups. |
| Snapshot predates an obligation, failed guess, proof revocation or owner change | That fact may be absent entirely. **The old backup cannot reconstruct post-snapshot commits.** Do not claim an empty pending queue proves no debt/revocation. |
| No authoritative post-snapshot evidence exists | Keep recovery hold; record evidence gap/incident and request owner/security recovery disposition. Waiting 30 minutes cannot recreate missing audit history. No automatic force-clear or restored-proof reuse. |

Future operations must specify consistent SQLite backup method, journal/WAL handling, backup provenance/last durable point, separately protected recovery evidence of security mutations/revocations, key/namespace custody, restore rehearsals and RPO/RTO. None is configured or created here. If lossless post-backup recovery is required, an independently durable authoritative history/replication mechanism needs its own approval; five fields in the same lost database cannot supply it.

Current/future stale authorizations should be revoked/held and refreshed only through separately approved recovery flows; original frozen evidence stays historical. Owner-approved gap acceptance, if ever necessary, must identify actual data loss and restrictions; it is not a declaration that missing events were recovered. Real backup copying, restore-to-new-file and deployment hold implementation remain outside this design task/B3.2-B execution permission.

## 8. Security invariants

These are **PROPOSED acceptance conditions**, not already implemented guarantees:

1. **Accounting:** every committed admitted wrong guess updates challenge count and rolling slots atomically. Every required exhaustion event has either a durable pending tuple or an exact final audit row; the audit transaction cannot undo that count.
2. **Gate:** no code comparison, proof grant/consumption or send reservation for a pending exact scope. Denial is independent of submitted code and generic; only safe clock accounting may change.
3. **At-most-one obligation:** one current tuple per scope; immutable contents, no overwrite/drop/TTL. Different event occurrences use distinct saved IDs.
4. **Exactly-once final materialization, conditional on liveness:** audit PK uniqueness plus exact-match reconciliation prevent duplicate rows for one obligation. Eventual visibility requires storage and reconciler recovery; no false unconditional “exactly-once delivery” claim.
5. **Tenant binding:** binary merchant/actor/challenge FK and intent correlation; authoritative scope/HMAC validation at creation; full audit match at discharge. No decrypt authorization inferred from AAD canonicalization.
6. **No replay grant:** completed/stale worker data cannot regenerate debt, increment counters twice or clear a newer ID; normal consumed/exhausted/expired/proof uniqueness still applies. Generic request-level exactly-once handling before commit is not added.
7. **No audit rewriting:** retries only insert new expected row or match existing exact row. Ordinary UPDATE/DELETE rejected by proposed SQL guards; mismatches held. Reconciliation never deletes historical events to unblock.
8. **No sensitive pending payload:** five typed metadata fields only; no code, target, fingerprint, NIN, key or free-form error. Original audit metadata semantics/size/allowlist remain.
9. **No policy/time reset:** six digits, five minutes, five wrong guesses, 60-second resend, ten rolling failures/30 minutes and proof 30 minutes/no grace/single use remain. Holds may outlast windows; clearing does not extend or reset anything.
10. **Current vs historical authority:** revocation/retirement cannot cancel a historical obligation; reconciliation cannot reactivate membership/contact/proof. Unresolved audit debt gates use, not security invalidation.
11. **Key epoch continuity:** no fingerprint-key switch or equivalent-scope recreation may bypass pending. Existing drain/rotation plan must additionally finish/recover all old-epoch obligations before retiring that epoch. Missing encryption/scope key denies verification; reconciliation itself requires no plaintext keys.
12. **Storage/restore honesty:** no “durable” result if T_count commit is not established; no data-loss recovery claim from a stale snapshot alone. Privileged SQL/DDL compromise and catastrophic media loss need controls beyond these seven tables.

## 9. Acceptance-test matrix

**DESIGNED ONLY — NONE EXECUTED.** Future permission must authorize isolated implementation/test support. Use public fixture keys and synthetic values, :memory: for most constraints and unique guarded temp SQLite for independent connections/process crash tests. No working DB connection, real NIN/phone/provider call or secret. Existing R01–R04/R07, ULID/AAD, auth/merchant/tenant regressions remain mandatory.

| ID | Required assertion | Environment |
|---|---|---|
| S01 | Add columns/indexes/FK/triggers on populated synthetic old schema; all old values/IDs/indexes retained; down refuses pending; old enum downgrade rules remain | Isolated SQLite |
| S02 | Each partial tuple, bad ULID/UUID/time/reason/type rejected for intended constraint with valid controls and correct versions | Isolated SQLite |
| S03 | Different merchant/actor challenge or wrong intent correlation rejected; valid binary lowercase parent passes; case-altered parent rejected | Isolated SQLite |
| S04 | Pending fields cannot change; pruning/resend/count mutation during pending rejected; safe clock/version update allowed; clear without exact audit rejected | Isolated SQLite |
| A01 | Normal nonthreshold wrong guess commits once, no new exhaustion event; six-digit leading-zero behavior and approved limits unchanged | Isolated SQLite |
| A02 | Fifth failure plus pending commit; rejected audit INSERT leaves count=5/exhausted and obligation stable; repeated wrong/correct submissions invoke zero code comparisons | Isolated SQLite with comparison spy |
| A03 | Tenth aggregate plus pending commit across resends; audit outage leaves ten slots; challenge count<5 remains active but gated; fifth+tenth yields exactly one aggregate reason | Isolated SQLite |
| A04 | Pending denial also blocks issuance/resend/future proof-consumption fixture, even after five-/30-minute boundaries; no TTL/clock/contact-version reset | Isolated SQLite |
| A05 | Correct verification/success audit remains atomic; success audit failure yields no consumed challenge/new proof; no simulated live eligibility | Isolated SQLite |
| R01 | Reconcile INSERT+clear commits together, original occurrence/request/actor/merchant preserved; pending clear does not prune counts | Isolated SQLite |
| R02 | Matching existing event no-ops safely; each differing field/NULL/metadata/time collision holds; no ignore/upsert/update/delete | Isolated SQLite |
| R03 | Repeated retries keep same ID/payload; retry success one final event; stale ID cannot clear newer debt; repeated later genuine threshold uses new ID | Isolated SQLite |
| R04 | Audit UPDATE/DELETE via raw SQL/model rejected; pending discharge fails if event removed in a deliberately tampered fixture; operator cannot silently force-clear | Isolated SQLite |
| C01 | Two correct/wrong/near-fifth/near-tenth verification processes: one threshold commit; later contender does not compare; counts/slots/audits exact | Guarded temp SQLite, process barriers |
| C02 | Two reconcilers and verification race: one materialized row, no duplicate counter/clear; another merchant remains logically usable | Guarded temp SQLite |
| C03 | BEGIN busy exhaustion denies before comparison; post-evaluation exceptions do not automatically rerun code; unknown commit recovery uses saved evidence | Synthetic failure hooks/temp SQLite |
| C04 | Kill worker before count commit, after count commit, after audit INSERT, before clear/commit and after final commit; restart matches section 7 matrix | Guarded temp SQLite/process kill, no working files |
| C05 | Count+pending commit succeeds while audit-only trigger rejects; separately simulate complete accounting failure and prove no success/code-specific response/automatic retries | Isolated SQLite plus guarded failure hooks |
| T01 | UTC millisecond integer→canonical text conversion agrees SQL/PHP at second/day/leap-year boundaries; Eloquent/writer cannot truncate; invalid dates/out-of-range fail closed | Isolated SQLite/PHP fixture |
| T02 | Rollback/forward clock tests, expiry/no-grace boundaries and pending high-water update retain exact intervals; reconcile never rewrites occurred_at | Isolated SQLite controlled clock |
| K01 | Same target/new contact version and key-epoch drain cannot bypass pending; missing key denies use but safe reconciliation still possible | Isolated synthetic keys/SQLite |
| B01 | Restore consistent synthetic pending snapshot then reconcile; restore committed audit+clear snapshot; verify counters/history and collision rules | Isolated temp SQLite |
| B02 | Restore an older snapshot predating a synthetic revocation/obligation; external fixture readiness hold denies use; test explicitly cannot infer/reconstruct missing event from snapshot | Isolated SQLite + mocked independent recovery authority |
| O01 | Durable filesystem/journal/synchronous behavior under power loss, disk-full/I/O failure and restart | Later controlled operational infrastructure; process kill alone is insufficient |
| O02 | Consistent backup/WAL capture, independent post-backup evidence, actual custody/revocation replay/RPO/RTO and restore release | Later separately authorized recovery infrastructure/data access |
| O03 | Scheduler fairness/backlog/alerts, least privilege, independent restore readiness/egress controls, peak lock contention | Later operational environment; mocks are not deployment proof |

Replace the two current rollback characterization diagnostics with positive R06 durability/gate acceptance tests only after implementing the approved contract; keep their prior result documented historically. Run targeted cases, full B3.2 synthetic suite and existing regressions on isolated connections, recording actual new counts. No pass count is forecast here.

## 10. Risks, trade-offs and Option B comparison

| Topic | Option A assessment |
|---|---|
| Audit atomicity | Solves audit-specific rollback using durable staged obligation, but intentionally changes exhaustion audit visibility from one commit to two; owner policy approval is essential. |
| Minimum schema | Five fields and supporting constraints reuse existing scope state; no eighth table or generalized outbox. More explicit constraints/triggers and a narrow writer increase migration/test surface. |
| Availability | Fail-closed scope may remain held indefinitely; even naturally aged-out failures cannot clear debt. Retry service/alerting becomes operationally necessary, with no forced bypass. |
| Latency/locks | Threshold failure needs two short writer transactions; batch reconciliation competes for SQLite's single writer. No network lock time, but load/backlog must be measured. |
| Completeness | One slot only supports the chosen exhaustion obligations; it cannot buffer unrelated mutations or continued guesses while held. Later expansion requires explicit new design, not arbitrary payloads. |
| Audit correlation | Reuses event meaning/request linkage and existing allowlist, avoiding sensitive metadata. It is not a new resource-specific audit FK or general event ledger. |
| Shared failures | Audit-specific error is isolated; complete accounting/storage failure cannot persist a count. Old backup cannot recover missing commits. Stronger guarantees need separately approved infrastructure/policies. |
| Attack/operations | Pending metadata private; scope holds may be induced by real wrong guesses plus audit failure. No permanent account lock is added, but support cannot invent verification/audit success. |

**Option B:** a separately designed durable health gate could refuse evaluations before entering an unhealthy audit/accounting path, with less per-event pending state. However, a SELECT/readiness flag does not guarantee a later INSERT cannot fail; unexpected mid-operation failure and lost health state across processes/restores remain. Pessimistic reservations/gating or independent failure evidence may still be needed, and global gates can impact all tenants. Option B is not automatically simpler or already implemented. This task designs Option A only.

**Explicit conflicts/limits:** Option A cannot preserve literal immediate count+final-audit atomicity while audit insertion is unavailable. It cannot guarantee counting every uncommitted internal comparison after a crash, nor recover unseen post-backup history without independent evidence. If the owner requires any of those stronger guarantees without accepting staging/reservations/recovery infrastructure, this five-field Option A is **NO-GO** and must not be implemented under an invented approval.

## 11. Decisions requiring owner approval

No implementation decision is approved merely by this specification. **REQUIRES OWNER APPROVAL:**

1. Accept the narrow policy substitution for wrong-attempt exhaustion only: count+obligation atomic, final audit+discharge atomic; success audits unchanged. Accept delayed audit visibility and exact-scope hold until reconciliation, including after ordinary windows expire.
2. Ratify five fields, one challenge UNIQUE tuple, pending guards/FK/index and audit append-only SQL guards within existing tables. No eighth table or new audit event/metadata key. Approve concrete additive synthetic migration/rebuild strategy before coding/execution.
3. Ratify existing fifth+tenth aggregate precedence as explicitly preserved, canonical millisecond event time/writer binding, exact-match conflict handling and immutable pending lineage. Do not rewrite existing audit history or parent IDs.
4. Approve internal reconciliation capability and synthetic support boundary; proposed five-second scan/25-row pages/five-minute age alert are operational recommendations, not selected production tooling.
5. Approve no-TTL/no-force-clear behavior, key-epoch/retirement dependency, lost-key handling and conflict escalation. Define responsible operators before any live service.
6. Ratify storage/unknown-commit boundaries and removal of blind post-evaluation closure retries. If every pre-commit comparison must be durably reserved, request a separate reservation contract rather than treating this design as sufficient.
7. Approve future restore hold authority, evidence source, RPO/RTO and documented data-gap disposition. No configured recovery ledger/KMS/scheduler is asserted; true lossless post-backup recovery needs separately evidenced infrastructure.
8. Separately authorize isolated implementation and tests after review, with no working SQLite/environment/frontend changes or later-stage activation. Production and B3.2-C remain separately gated.

Already approved ULID storage/AAD policy, encryption/key separation, exact OTP limits, proof lifetime/no-grace/single use, TZS-only scope and seven-table boundary are preserved and do not need reapproval. Legal/provider prerequisites for real collection/sends are not claimed satisfied by this design.

## 12. GO / NO-GO recommendation and preservation

| Area | Recommendation |
|---|---|
| Review Option A as a concrete design | **GO for owner/technical review**, with explicit policy/guarantee boundaries. |
| Implement the proposed fields/protocol/tests now | **NO-GO**: design authorization only; decisions in section 11 pending. |
| Declare R06 fixed or final B3.2-B accepted | **NO-GO**: current source unchanged; prior diagnostics still demonstrate the gap. |
| B3.2-C / working SQLite activation | **NO-GO**, outside this task and requires accepted B plus separate target/preflight/recovery/approval. |
| Real OTP/SMS/NIN, Selcom/payments/production | **NO-GO**, unimplemented/unauthorized. |

**Preservation method (VERIFIED file evidence):** before writing, SHA-256 inventory covered **203 unique existing files**: tracked/nonignored-untracked workspace files plus explicit environment variants/working database files. Baseline remained in memory, not another artifact. Ignored dependency/cache/recovery trees are not exhaustively certified. Initial Git state/HEAD retained; no secret contents were printed. Only this requested new Markdown file may be added.

**VERIFIED post-write:** all **203 protected existing files unchanged**, **zero missing**; the sole new inventory entry is this requested document. Git HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`; `git diff --check` passed. Working SQLite hash remains `214AE83A31F53E80D3068D1AEB1366008BB5ACCB2DF212DD2C534AE4121B6D8F`. The final document-only edit records these results.

Working SQLite was hashed only, never connected/copied/restored. No source, migration, test, fixture, prior report/specification, frontend, .env/secret or Git history edited; no test/runtime/migration/formatter/install/message/account/commit/push/deploy performed. Official documentation browsing was read-only.

**STOP for owner review.** Pendekezo hili halijatekelezwa. Owner akiridhia contract na mipaka yake, hatua inayofuata inaweza kuwa ruhusa tofauti ya isolated synthetic implementation; hakuna kuendelea B3.2-C kiotomatiki.
