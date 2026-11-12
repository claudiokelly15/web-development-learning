# XLipa PD-05A — Final Decision Contract Specification

12 November 2026 · Africa/Nairobi · Documentation and proposed technical contract only

## 1. Status, authority and approved owner decisions

This specification consolidates the existing onboarding/submission/review workflow. **Final specification does not mean approved schema, implemented decisions or production readiness.** Only this document is added. No application, migration, schema, configuration or operational change is authorized or performed.

Owner-approved product behavior:

1. Minimal account registration; business name is optional at signup.
2. Phone is collected at signup; possession verification is not a dashboard prerequisite.
3. Verified email is required before dashboard access; full KYC is not required.
4. Business name, verified application phone, protected owner/representative NIN, verified owner email, fresh owner authority/evidence and operational/security admission are mandatory before submission.
5. Individuals, including unregistered businesses, are supported subject to applicable legal/provider requirements. Company documents are not universal individual-applicant requirements.
6. Automation establishes preliminary technical eligibility only; Chief Admin retains final merchant application approval authority.
7. Application approval never enables payments automatically.
8. Information-request continuation preserves history through a linked successor application attempt.

These decisions supersede the prior review's unresolved product questions about business-name timing, dashboard phone gating and the choice of successor approach. They do **not** select MFA, authentic identity assurance, legal/provider policies, retention duration or amendment execution. Those security/operational decisions remain explicit dependencies below.

**Working B3.2-C activation: NO-GO. Working SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED. STOP FOR OWNER REVIEW.**

## 2. Verified source baseline and compatibility gaps

Read backend `AGENTS.md`. `.ai/rules` is absent at workspace, XLipa and backend paths. HEAD remains `014460f1b8070e6f823fff3bd80ede294d4e8341`. Starting Git state contains six tracked PD-05A modifications and five untracked prior deliverables, including the Decision Contract Review; staging is empty. These are preserved baseline work.

Inspected current registration request/action/controller/form; CreateMerchant and foundation merchant schema; email verification and lifecycle; dashboard/member authorization and display; phone/NIN capture, readiness and OTP contracts; internal PD-04B.3 submission/admission/transaction implementation; PD-05A read controller/policies; frozen C/SE definitions, readiness and source pins; and the prior decision review. Earlier report evidence is historical, not newly executed verification.

| Area / source relative to `backend/` | Current behavior | Required bounded adjustment or dependency |
|---|---|---|
| `app/Http/Requests/Auth/RegisterRequest.php`, registration Blade form | businessName required in server validation and HTML; phone/name/email/password/terms required | Optional businessName handling at both boundaries; preserve all other signup requirements. |
| `app/Actions/Auth/RegisterMerchantOwner.php`, `app/Actions/Merchants/CreateMerchant.php` | Action indexes businessName; CreateMerchant takes string and rejects trimmed empty; creates merchant/member/draft/audit atomically | Explicit absent-name creation semantics, retaining the same architecture and ownership. |
| `database/migrations/2026_11_06_000002_create_merchants.php` | business_name is NOT NULL text; no nonempty-name CHECK at creation | Canonical empty string can represent absence without NULL, fabricated name or schema change. Submission nonempty checks remain. |
| Dashboard routes, `MerchantPolicy.php`, active-auth middleware | Fresh active verified-email tenant member; no phone/KYC gate | Already matches approved access behavior; preserve it. Production auth readiness is independently unestablished. |
| `VerifyAccountEmail.php`, lifecycle, signed notification/controller | SE binds exact email/epoch; stale links fail; legacy path retained | Preserve email verification and session/epoch protections. |
| Signup phone versus verification records | business_phone is declared ordinary profile text; encrypted application_phone is separately verified and version-bound | Keep collection; do not copy declared phone into a verified flag or gate dashboard on application proof expiry. |
| `MerchantVerification.php`, request/environment/readiness | Owner-only current-draft capture; syntax/declaration and possession proof, not independent identity; default denial | Preserve minimum and default denial. Authentic assurance policy/provider remains unselected. |
| `ApplicationSubmissionService.php` and public submission guard | Strict internal protocol; runtime admission denied; public controller calls unconditional guard | Preserve holds. Unnamed merchant must remain unable to submit. |
| PD-05A controller/views/policy | Redacted Chief reads; partial reference indicators; no decisions | Reads remain informational. Decision authorization/evidence must be stronger and independently rechecked. |
| Foundation review fields and platform assignments | Mutable reviewer/time/feedback; role keyed by user without authority epoch | Immutable decision bundle and monotonic reviewer authority history needed. |
| `SubmissionEvidenceSchema::assertProfile/ready` | Exact object catalogue and migration ledger; foundation SQL hashes, with only its two specified epoch-column exceptions | A new table/index/trigger/ledger entry is rejected by unchanged SE readiness. Additive DDL alone is insufficient; strict separately versioned composed readiness and consumer integration are required. |

The last point refines the earlier review: preserving C/SE object bytes is necessary, but does not mean existing SE readiness will accept an extension. The new profile must deliberately compose and verify old objects; never change the old manifest/pins, ignore unexpected objects, or treat old `ready() === false` as permission to fall back.

## 3. Exact proposed registration adjustments

### 3.1 Canonical representation and request behavior

Use **business_name = empty string (`''`)** for an initially absent name. This is an explicit absent-value representation in a NOT NULL text field, not a placeholder business identity. Never store NULL, an account name, merchant ID, email, “My business,” “Pending,” or another invented business name.

| Input | Proposed result |
|---|---|
| businessName omitted, null, empty or ASCII-trimmed whitespace-only | Normalize to absent at request/action boundary; persist exactly `''`. |
| Nonempty string | Trim under the existing name convention; require at most 200 characters; store the supplied value. |
| Array/object/boolean/numeric or oversize value | Validation error, not coercion to a name or absence. |

Change the existing request rule to optional/nullable string/max 200, with deliberate canonicalization. Remove HTML required semantics and identify the field as optional, or omit that optional field from the minimal signup presentation while maintaining compatible optional input acceptance. Preserve name/email normalization, password confirmation/minimum/maximum/NUL rules, phone collection/normalization, terms version/time, forbidden privilege claims and duplicate-email privacy.

RegisterMerchantOwner must safely pass an absent businessName without indexing a missing array key. Proposed CreateMerchant parameter becomes nullable: **null explicitly requests an unnamed initial merchant**, canonicalized internally to `''`; a supplied non-null name still follows its nonempty/length contract. This is limited to creation of the initial incomplete merchant/draft. Only the registration action is a current runtime caller found in source; do not add a request-controlled bypass flag or relax submission/name-completion checks.

No signup schema amendment is needed on the inspected profile: foundation NOT NULL accepts `''`; inspected C has no business-name creation constraint; SE checks nonempty name when admitting evidence. Exact compatibility still requires fictional fixture tests before implementation acceptance. If a different admitted target profile forbids absence, stop rather than silently change constraints or invent a name.

### 3.2 Preserve creation and access architecture

Keep one atomic registration operation: user + accepted terms + merchant with `profile_version=1`, declared signup phone and normal nonfinancial defaults + active owner membership + current draft attempt 1 at profile version 1 + existing merchant-created audit. Do not create an account-only branch or defer merchant/member/draft identity. Duplicate-email races and failures must retain existing generic handling and complete rollback.

Registration still leaves the registrant a guest and directs them to login/email verification. Fresh session/auth epoch, active account, verified email and authorized tenant membership remain required for dashboard access. Neither `''`, absent NIN, unverified signup phone nor expired application-phone proof blocks dashboard access. No live auth/email execution is authorized here; existing local/testing/readiness restrictions remain.

For display only, use a clear label such as “Business name not provided” and an accessible neutral workspace mark when the persisted name is empty. This is UI status text, never stored in business_name or submission evidence. Update the existing account selector, onboarding summary/dashboard and relevant headings so they do not produce blank accessible links/avatars. Preserve XLipa premium light styling. No new dashboard, product, percentage or generalized profile editor is needed.

### 3.3 Later name completion

Specify a narrow business-name completion operation within existing merchant onboarding, not a new service/product. It accepts only the new name and exact application/current-profile expectations; actor and tenant authority come from the authenticated context. Use CSRF/method controls, fresh active verified owner authority, non-suspended/non-closed merchant and a current draft. Managers/Staff/platform role alone cannot complete the name as owner.

Under the established SQLite writer reservation, reload the tenant/current application and expected merchant/application versions, validate a nonempty trimmed name of at most 200 characters, then update the merchant name and its profile version **exactly once** and synchronize the current draft's profile_version in the same transaction. Initial `''` → supplied name advances profile version 1 → 2; no-op same-name write preserves it. Overflow, stale expectations, affected-row mismatch or failure rolls back both. Use the accepted SE profile-version guard; never bypass it with a name-only unversioned write.

Do not alter context/contact/identity logical versions or revoke a valid phone proof solely for name completion; submission must freeze the resulting exact profile version. Do not edit submitted application fields or its historical name capsule. This bounded operation is draft-only; general pending/final-state profile editing is outside it. Record no private name payload in audit. Whether a profile-change event is required needs an approved existing event/metadata mapping before implementation; do not invent an audit enum or silently overload merchant.created.

No such completion route/action is currently implemented. Later approval must include its strict input, transaction/unknown-outcome behavior and existing-controller integration. If commit acknowledgement is uncertain, use the established discard/fresh-resolution discipline; do not claim rollback or automatically repeat a version increment.

## 4. Merchant application minimum and identity distinctions

| Required fact | Contract |
|---|---|
| Business name | Nonempty bounded current name and matching merchant/application profile versions. Empty initial name remains a hard submission blocker. |
| Verified owner email | Exact current verified users.email, active account, fresh binding/auth epochs. Optional business_email is not a substitute. |
| Verified application phone | Current Tanzania mobile contact with trusted actor/tenant/purpose/contact-version proof, unrevoked/unexpired/available at submission, consumed once. Declared signup phone is insufficient. |
| Protected owner/representative NIN | Current dedicated encrypted record, approved syntax/normalization/declaration/notice, subject claim and logical-content commitment. No raw NIN in profile snapshot/audit/decision/receipt. |
| Fresh owner authority and evidence | Exact current draft/attempt/profile/context/identity/contact/membership/email expectations, fresh session/owner authority and atomic SE evidence/audit/consumption bundle. |
| Operational/security admission | Lawful capture purpose/policy, dedicated custody, authentic issuance, trusted time, retention/source holds, authoritative restore and no unresolved R06. Default unavailable means deny. |

Individuals, including unregistered businesses, use the individual-owner identity claim; company applicants use the authorized-representative claim. These are declarations, not verified classification or legal authority. Missing optional business_type remains unclassified; never infer company from a trading name or individual from absence. No company registration certificate, TIN, licence, address, bank account or mobile-money destination becomes a universal initial submission requirement. A conditional requirement needs approved rule/version, authoritative basis, affected type/market and phase. A company requiring representation evidence cannot be substantively approved while that evidence is unavailable; do not demand the same company documents from individuals.

**Declared identity, syntax validation, email verification, phone possession and independently authenticated identity assurance are separate facts.** NIN format/OTP/declaration do not prove identity or representation. Required independent assurance, acceptable evidence/method and phase remain security/legal/provider decisions; no NIDA API, alternative ID, provider or invented checksum is selected. If a phase requires authentic assurance and no admitted evidence exists, hold that phase. Do not relabel a declaration or invent a pass record to satisfy the owner's minimum.

No new real sensitive collection is permitted. Current protected-capture authorization stays denied. Data purposes/notices/access/retention/holds/recovery/deletion must be approved before real processing; terms acceptance alone does not establish that authority.

## 5. Automated preliminary validation

Reuse existing technical checks: required-field shape/completeness; exact verified email and phone proof; current owner/session/tenant authority; authentic issuance admission beyond SQL live labels; no conflicting attempt/request/bundle; matching evidence/profile/context/authority epochs; contextual AEAD/source-content integrity; trusted time/schema/retention/restore admission and R06/merchant-status holds.

Run mandatory checks before/inside submission, not merely after accepting an invalid bundle. At decision time revalidate applicable facts under the writer. PD-05A display indicators or earlier browser preflights cannot authorize decisions. Existing deduplication covers requests/attempts/proofs, not a global identity/company/NIN uniqueness check. Do not add scoring, blacklists, new validation services, states or screening APIs.

Automated technical eligibility never grants Chief approval. Missing infrastructure, untrusted evidence, timeouts and restore uncertainty yield safe hold/error, not automated applicant rejection. Application approval never grants payment activation.

## 6. Chief decision contract — proposed technical baseline

### 6.1 States and outcomes

Existing names remain: draft, pending_review, information_requested, approved, rejected. No under_review/preapproved state is added. Only the existing SE bundle moves draft → pending_review. Only an admitted Chief decision moves pending_review → approved/rejected/information_requested. There is exactly one immutable decision per submitted attempt. Approved/rejected attempts have no reversal/reopening in this scope. Information-requested attempts continue through section 7, never by changing their status back.

Approval is an explicit Chief choice based on admitted evidence under an approved policy. Rejection requires supported applicant-related grounds. Information request is procedural clarification with safe feedback. Outage, corruption, missing custody, conflict, suspended/closed merchant, R06, uncertain restore or unavailable assurance is an operational hold/error, not a fabricated rejection. Do not commit a decision without admitted historical submission integrity.

For approval require current owner/email/profile/context/identity/contact bindings to agree with admitted frozen submission facts and applicable assurance. A known current-profile change may support an information request only if historical integrity and current Chief authority remain provable and the approved reason policy permits it. Policy not approved means that decision remains unavailable.

### 6.2 Chief authority, conflicts, epochs and step-up

Require authenticated reviewer identity, freshly loaded active verified account, matching model/session/auth epoch, effective unrevoked Chief assignment, exact expected authority epoch and action-specific decision authorization. Current queue-read permission alone is insufficient. Staff, merchant permission, request flags and cached relations confer no decision authority.

Deny the merchant creator, submitter and every historical tenant member, including revoked memberships. Recheck under the reserved writer and before commit. If all Chiefs conflict, hold for owner resolution; no Staff delegation or bypass. Every application/evidence/audit lookup is tenant/application bound.

Require an independently admitted recent privileged step-up/MFA policy before new decisions. Method, acceptable factors, freshness, recovery and revocation are **not selected**. Existing password confirmation and application SMS proof must not silently become privileged MFA. Missing approval or operational admission means no writes. Preserve SE account/password/auth-version holds and reset 503; role revocation and account recovery require a reviewed operational procedure before live use.

Use amendment-owned **append-only reviewer-authority revisions** rather than adding a column to the frozen platform assignment table. Each user has a monotonic positive epoch and frozen authority tuple (role, grantor, granted_at, revoked_at). Initial structural snapshots do not authenticate a grant. Amendment-owned triggers append epoch+1 on each logical authority change; no-op changes preserve epoch. Revoke/regrant or role-away/role-back advances it. Deny history mutation, branch/gap/overflow, duplicate/no-change revision and assignment delete/reinsert/REPLACE reset. Authorization still loads the live assignment and independent grant admission; revision history is not trust by itself.

Exact trigger/initialization mechanics require isolated proof against raw SQL. No role-management service or authority provisioning UI is introduced. Store observed reviewer epochs in decisions without lifetime-equality FKs to mutable current authority, so future revocation remains possible.

### 6.3 Immutable decision evidence and input

Proposed strict command: tenant/application, expected pending attempt/submission/profile/context/identity/contact/email/owner bindings, expected reviewer auth/authority epochs, canonical decision UUID, selected outcome and approved reason/template version. Reject unknown/nested/coerced/malformed input. Derive actor, event times, IDs, policy/admission facts and safe feedback internally. No client-supplied verification, timestamp, key, audit or activation authority.

Logical decision record:

| Field group | Proposed constraints |
|---|---|
| Identity | Server ULID decision ID; fixed contract version; tenant/application/attempt; immutable SE reference/version; UNIQUE application, with exact tenant FKs. |
| Reviewer | Existing user ID and observed positive auth/authority epochs; approved step-up/authorization policy evidence reference, no credential/token. |
| Command | Canonical lowercase UUID; UNIQUE merchant+reviewer+UUID; frozen exact expected tuple, outcome and reason/template semantics. |
| Outcome/feedback | from=pending_review; to in approved/rejected/information_requested; closed versioned reason/template reference and immutable bounded plain-text feedback. |
| Time/audit | Trusted bounded UTC milliseconds and matching canonical millisecond text; unique audit ID/type=application.reviewed and exact reviewer/tenant/application/request/time tuple. |
| Admission history | Validation/retention/assurance policy versions and references to actual evidence checked; no invented checks-passed or identity-match stamp. |

All decision fields are append-only, with UPDATE/DELETE/REPLACE denied. Use typed integer/text/alphabet/length/NUL checks and RESTRICT references. Do not copy raw NIN, phone, email, documents, fingerprints, content MACs, source envelopes, keys or free-form internal notes. Optional values absent from the historical SE capsule cannot be treated as submitted history. Source rewrap must be judged by existing logical-content commitments, not ciphertext hashes.

### 6.4 Atomic decision/application/audit protocol

1. Admit exact composed schema, trusted time/restore/retention/custody, reviewer scope and step-up. Reserve one SQLite writer before reads; refuse nested/non-SQLite use and automatic retry.
2. Resolve matching scoped request first. Otherwise require exact current pending attempt, no existing decision, valid historical SE bundle and outcome-specific current prerequisites.
3. Insert server-derived immutable decision while application is pending. An amendment-owned trigger creates its exact canonical redacted application.reviewed audit.
4. CAS pending application to the decision result, exact reviewer/time/safe-feedback projection and unchanged submitted identity/version fields; require one affected row.
5. Require deferred final-state decision/application/audit agreement, recheck live authority/time/admission and commit once. Known precommit errors roll back the entire bundle.

New guards deny direct decision-like application writes, orphan review audits, incomplete commits and subsequent reviewer/time/feedback/status projection edits. Freeze new decision projections while allowing only the separately guarded current-slot handoff for information-request continuation. No merchant operational_status/verification_status or financial/provider mutation occurs.

Canonical audit metadata remains within the existing allowlist/2048-byte ceiling: ordered from, to, integer profile_version, reason_code; target_user_id NULL. No feedback, private values, extra metadata keys or internal notes. Exact tuple indexes/FKs/canonical bytes are later artifact deliverables, not assumed already enforced.

### 6.5 Closed reasons and feedback

Define an immutable versioned catalog mapping outcome+reason+template to approved safe plain text. No arbitrary reviewer text in v1. Freeze selected version and rendered safe feedback, escape it, and exclude HTML/links/copied identity/contact values/internal risk signals. Candidate semantic categories are requirements satisfied, clarification needed, changed profile requiring fresh submission, and unsupported application under an approved rule. These are **not approved legal findings or final codes**.

Final codes/template wording, permitted disclosure and byte bounds require policy approval. A proposed technical feedback ceiling may be finalized with that catalog; do not treat an arbitrary number as owner-approved. Do not use existing internal_review_notes as a substitute unrestricted evidence channel. Reason/template references and decisions must remain reproducible for approved retention; policy withdrawal blocks new use without rewriting history.

### 6.6 Idempotency, concurrency and uncertain outcomes

Matching replay requires the same merchant/reviewer/UUID and entire command/outcome/reason/template semantics plus a complete valid bundle. Changed command/same UUID conflicts. Different UUID for a decided attempt conflicts. UNIQUE application and pending-state CAS permit one concurrent winner; no decision overwrite or extra audit. Busy/stale failures do not auto-retry.

Return only historical receipt fields: application ID, merchant ID, UUID, outcome, canonical time, application.reviewed. Current authorized nonconflicted access is still required for replay/resolution; a revoked reviewer cannot use correlation IDs as access grants. Another authorized Chief may read allowed history but cannot impersonate that command.

On COMMIT acknowledgement error or failed rollback, discard connection and raise generic unknown outcome. No claim of rollback, automatic replay or opposite decision. Explicit fresh serialized resolution requires authoritative restore/replay admission and exact bundle validation; return receipt if present, or authoritative absence only with unchanged expected pending state and no partial/conflicting decision/audit. Null does not retry. Explicit later same-command retry rechecks all admission. Missing restore authority stays unknown/held. No repair/manufacture of history.

### 6.7 Freshness, revocation, legacy and payment separation

Natural expiry after valid proof consumption does not erase submission history and does not require that proof to become available again. Decision-time freshness/review-age policy remains unselected. If fresh assurance is required, use an approved successor/lifecycle path, never rewrite the old capsule. Logical source-content substitution, revocation, changed owner/email/profile/context/identity/contact bindings, trust withdrawal or required key loss hold current approval. Envelope rewrap with identical logical content remains compatible.

All decisions need admitted historical SE integrity and applicable policy/custody/retention/time/restore checks. Do not invoke the owner-only submission service as Chief or impersonate the owner; reuse of validation internals needs a reviewed existing-workflow integration contract. PD-05A partial read indicators are insufficient.

Legacy evidence-less submitted/reviewed rows remain redacted historical reads. No automatic decision backfill/adoption or new substantive decision on them. A future legacy policy would need separate approval; this scope recommends denial.

A coherent stale restore can show pending state after a newer database decided it. Local absence/schema agreement cannot prove freshness. Require authoritative cutover/restore admission and replay fencing; no operational implementation is supplied. Subsequent revocation changes current readiness, not historical decision bytes.

All outcomes leave merchant verification/operational flags unchanged. Application approval is at most one prerequisite for a later separately authorized current-eligibility/provider/legal/operational payment gate. No enabled/verified mapping, provider contact, financial account, API activation or payment side effect.

## 7. Linked information-request successor specification

This continuation approach is owner-approved at product level; the following technical mechanics require amendment review.

| Invariant | Required behavior |
|---|---|
| Predecessor | Exact current information_requested application with admitted immutable submission and matching information-request decision; no existing successor. |
| Initiator | Fresh active verified owner for this merchant, expected membership/auth/email/profile/context epochs and explicit request UUID; no automatic creation, Chief impersonation or Staff fallback. |
| Successor identity | New server ULID, same merchant, attempt = prior maximum+1 under writer, draft status, same current merchant profile version, no submission/review fields, NULL profile_snapshot. Check integer overflow. |
| Relationship | Immutable same-tenant predecessor application+decision → successor link; UNIQUE predecessor decision, predecessor application and successor application; exact next attempt; no branching/cycles/cross-tenant links. |
| Slot | One atomic handoff: clear predecessor current_slot, create successor current_slot='1', insert link and validate final tuple. Preserve predecessor status/submitted fields/evidence/decision. No detached old-slot clearing or unlinked replacement draft. |
| New evidence | Explicit new SE submission UUID, fresh proof and currently admitted contact/identity/declaration/profile/owner facts. No copied capsule/proof or reused consumed lineage. Unchanged logical identity/contact records may be referenced only if still current and policy-admitted; historical evidence is never cloned as new verification. |
| Retry/uncertainty | Link has scoped continuation request identity and expected tuple; matching replay returns same successor, changed tuple conflicts; one writer/one successor. Commit ambiguity discards connection and resolves on authoritative fresh state, never creates another attempt. |

The request to create a draft need not already hold a fresh phone proof: the draft must exist so the owner can perform current draft-only verification. Fresh evidence is mandatory before that successor is submitted. Pending R06/time/restore/security holds still prevent unsafe continuation. Do not mutate source verification records as part of handoff or reset counters.

Proposed canonical database ordering is linkage first: insert the fully admitted immutable link while the predecessor is current/information_requested; an amendment-owned AFTER INSERT trigger releases its slot and creates the exact successor draft. Deferred stable-identity FKs permit the link to precede the new row. Additional guards accept that slot release/draft insertion only with the matching link. The trigger-generated draft, slot state and link are then validated before the single commit. Trigger failure aborts the operation; no independent autocommit slot-clearing/draft-creation step is exposed. Exact SQL must prove this ordering under recursive-trigger/FK enforcement, including that direct insertion cannot skip authority and request checks. Creation-time draft/slot checks must not become lifetime FKs to mutable status/current_slot: the successor must later submit and may itself release its slot under another valid link.

A successor may itself later receive an information request and one successor, forming a strictly increasing chain. Rejected/approved outcomes have no successor or appeal in this scope. No information_requested → pending_review/draft update, automatic resubmission, general appeals or deadline extension.

No new public audit event is silently selected for draft handoff: the immutable link is the structural history. If policy requires an additional audit event, approve its exact existing-compatible mapping or a separate explicit audit-contract revision before DDL. Do not fabricate application.submitted/reviewed for draft creation or extend frozen enum definitions implicitly.

## 8. Precise proposed versioned amendment plan

Working label **DR-1 (proposal only)** distinguishes this new review profile from unchanged C and SE-1.0; final artifact/migration identity requires review. No DDL/migration is written here.

### 8.1 New records and constraints

| Proposed amendment-owned record/object | Required properties |
|---|---|
| Reviewer authority revisions | Append-only (user_id, epoch) history, previous epoch linkage, frozen assignment authority tuple; trigger-maintained exact increment; no assignment delete/reinsert reset. Keep existing assignment table definition unchanged. |
| Application decisions | Typed immutable record described in section 6, unique application and scoped request, exact SE/reviewer/tenant references, canonical audit linkage and deferred final application tuple. |
| Decision reason/template catalog | Immutable version/outcome/code/template mapping, approved safe feedback/bounds; exact reference/content consistency from decision. Install only approved entries, not sample operational policy. |
| Successor links | Immutable predecessor+information-request decision+successor/tenant/attempt linkage; owner/observed authority and continuation UUID/time/expectations; uniqueness, increasing attempts, final-slot agreement. |
| Supporting parent indexes | Amendment-owned exact composite UNIQUE keys needed for typed FK tuples; no parent-table rebuild or mutable-epoch lifetime FK. |
| Decision/application/audit guards | Require canonical complete bundle; prevent unauthorized review projections/audits, mutation/deletion/REPLACE, incomplete commit and legacy adoption. |
| Current-slot/attempt guards | Initial attempt 1 at merchant creation or exact linked information-request successor only for new workflow rows. Deny bypass extra drafts/slot swaps, direct successor insertion and unlinked slot release; preserve existing historical rows. |
| Independent manifest/ledger/readiness | Exact new object/column-free catalogue, artifact/source hashes, expected composed ledger and compatibility pins; unknown/missing objects/rows fail closed. |

Structural initialization of reviewer revisions may preserve existing assignment tuples at epoch 1 without asserting authentic grants. Existing application/review/evidence records are not backfilled into decisions/links. Preflight flags incompatible history instead of repairing it.

Deferred constraints must prove both directions: a new decision requires its final application/audit tuple, and a new decided application/review audit requires the exact decision. A new successor requires a link and the link requires the stable same-tenant application/attempt identities, with trigger-enforced creation-time handoff. Do not pin a successor permanently to draft/current_slot='1' through a lifetime FK. Choosing foreign keys/triggers that allow incomplete autocommit fragments or prevent subsequent valid lifecycle is unacceptable. Exact insertion order and canonical tuple keys must be demonstrated in fixtures.

### 8.2 Frozen-contract compatibility and readiness dependency

Keep all frozen C/SE SQL, source, artifacts and manifests byte-identical. Never rebuild merchants to make business_name nullable or add authority columns to a hashed foundation definition. New triggers may strengthen allowed transitions, but cannot override a frozen trigger's denial; continuation therefore uses a new draft identity and allowed current-slot mutation.

**Unchanged SE-1.0 ready() must continue to deny DR-1's additional objects/ledger.** Implementing additive objects without adapting consumers would break versioned registration/email verification/revocation and internal submission. Current direct consumers are ApplicationSubmissionService (pre-writer and under writer) and LifecycleMutation; User notification selection and VerifyAccountEmail/revocation/reset paths depend indirectly on LifecycleMutation. Inventory all call sites again at implementation time.

A later approved change must provide a strict composed-profile validation path in the existing readiness/integration layer outside the pinned SE source, and deliberately route these consumers to the exact profile validator. It must verify frozen source pins/SQL, all approved additions and exact ledger; retain strict base-SE validation when no amendment is selected; reject partial/unknown mixed profiles and no legacy fallback. This is schema admission for existing workflows, not a new product/admission service. Exact code placement/API is part of the later reviewable diff, not authority to edit frozen SE code or change its expected outcome. Schema capability never grants operational trust.

### 8.3 Migration, rollback and failure discipline

Proposed preparation is fixture-only. Before any later migration: admit exact supported base profile, idle SQLite connection, FK/recursive-trigger settings, exact source/ledger pins and consistent existing history. Validate required policies separately; missing approved policy blocks corresponding DDL/data configuration rather than inventing defaults.

Install all amendment-owned records/indexes/guards/structural authority snapshots/ledger in one controlled atomic operation. Inject failure at every boundary. Rollback must restore exact preflight schema/data/ledger/ciphertext and leave no partially admitted profile. Do not mark ledger success before complete postflight, foreign-key and catalogue validation. Unknown commit outcome requires connection discard and authoritative inspection, not rerunning migration on assumed absence.

Existing C/SE objects and history remain intact. New profile has its own manifest, admission and release exclusion; no working runner/activation command supplied. Consumer readiness integration must be ready before any proposed deployment of the new schema. No successful base readiness is forged to bridge a rollout window.

Refuse destructive downgrade once decisions, successor/authority history or other meaningful amendment state exists; no automatic down that erases epochs/evidence. Prefer reviewed forward recovery. Predecision fixture rollback tests do not authorize operational restore. Operational backup/key custody, cutover freshness/replay fencing and uncertain-outcome evidence remain independent NO-GO gates.

### 8.4 Dependencies preventing executable final DDL

Final reason/template catalog and feedback bounds; privileged authorization/MFA evidence and expiry/revocation; identity/representative assurance references; retention/hold policy; exact fresh-review rules; continuation audit semantics if required; and trusted time/restore admission remain unapproved. Their policy-dependent fields, checks and installed rows cannot be guessed. Core logical uniqueness/atomicity requirements above are fixed proposal invariants; exact DDL/manifest must await those decisions or explicitly omit and keep the dependent action disabled. No test-only live policy may enter runtime artifacts.

## 9. Outstanding approvals, implementation order and acceptance

### Security-policy decisions still open

| Decision | Required resolution |
|---|---|
| Chief privileged assurance | Approved method/factors, freshness, recovery, grant trust and emergency revocation; no mechanism chosen here. |
| Identity/representation assurance | Meaning, acceptable authentic evidence/method, conditional individual/company rules and phase. No provider/API/legal requirement selected. |
| Retention/lawful processing | Purpose/notices, minimum data, access, holds, source coordination, deletion/backups and duration by qualified owners. No period invented. |
| Evidence freshness/lifecycle | Review-age policy, consumed-proof revocation/change handling, procedural clarification eligibility and current post-approval readiness. |
| Reason/template policy | Exact closed catalog, safe feedback, bounds, disclosure and retirement rules. |
| Profile-completion/continuation audit | Exact compatible event mapping if additional audit is required; no enum or metadata extension assumed. |
| Restore/time/release and legacy admission | Authoritative operational admission/fencing and migration crash/recovery evidence; recommendation remains no new legacy decisions/backfill. |

The already approved signup optionality, dashboard email-only contact gate and successor approach are not asked again as unresolved product choices.

### Implementation order, only after separate approval

1. Owner/security review this specification and resolve policy-dependent contracts; preserve all holds.
2. Prepare exact DR-1 schema/manifest/readiness composition and isolated constraint evidence, with no operational activation. No decision writer before the atomic invariants exist.
3. Separately review a concrete bounded existing-workflow diff for optional signup/name completion/display and composed-readiness consumers. Signup's empty-string representation itself requires no schema change and can be reviewed independently against the current base profile; it must not be used to bypass amendment admission.
4. Separately approve internal Chief decision and successor orchestration/strict input integration only after schema and policy review. Reuse existing boundaries where appropriate; add no unrelated services/products or general appeals.
5. Run focused plus vetted safe regressions and relevant offline responsive checks, report exact results, then stop for owner review. Public successful submission, real decision writes/collection and payment activation need their own explicit authorization and genuine operational readiness.

This order is a plan, not permission to execute steps 2–5 now. No automatic PD-05B/PD-06 progression.

### Isolated acceptance criteria for later implementation

| Area | Required fictional evidence |
|---|---|
| Signup | Omitted/null/empty/whitespace name creates one unnamed merchant, owner membership, version-1 draft and existing audit; supplied valid name unchanged; malformed/oversize denied. Phone/password/email/terms and privilege denials preserved; duplicate-email races generic; injected failure leaves no partial account/tenant. |
| Auth/dashboard | Guest/unverified/inactive/stale-session/foreign tenant denied as before; verified active owner accesses unnamed merchant without phone proof/NIN/KYC. Signup phone does not manufacture verification; staff/manager visibility preserved. Blank-name labels/links/avatars accessible and escaped at desktop/tablet/mobile. |
| Name completion | Owner-only exact current draft/CAS; empty→named increments merchant/draft once; no-op preserves versions; stale/concurrent/overflow/audit/commit faults leave consistent state or explicit unknown; submitted history immutable. Unnamed submissions denied by service and raw SQL. |
| Submission | Existing 14-expectation/provenance/crypto/retention/time/restore/R06/consumption/idempotency checks preserved, complete named fixture succeeds only internally under fictional admission, public guard remains denied. |
| Authority | Chief-only, historical-member/creator/submitter conflicts, step-up default denial, cross-tenant requests, revoked/regranted and role ABA, no epoch reset/branch/overflow or direct history writes. |
| Decisions | One exact immutable decision/application/audit bundle; raw-SQL orphan/direct/projection/REPLACE/partial commit denials; feedback policy; unchanged submission evidence/merchant flags; one concurrent winner; replay and changed-command conflicts. |
| Unknown outcomes | Before/after actual COMMIT acknowledgement fault; one commit attempt, discarded connection, no auto-replay, restore denial and explicit authoritative presence/absence resolution. |
| Successors | New same-tenant identity, exact linked information-request predecessor, one successor, atomic slot handoff, no clone proof/evidence; capture reachable on draft; fresh submission required; cycles/branches/general rejected/approved appeals/direct old-state reopening denied; uncertain continuation does not create duplicates. |
| Composition/migration | Base strict readiness unchanged; exact DR-1 readiness accepted only for full admitted profile; unknown/partial objects and ledger gaps denied. Failure at every migration boundary preserves existing IDs/ciphertext/schema/audits/ledger; downgrade refuses meaningful history. |
| Privacy/lifecycle | Missing keys/policy/assurance, rotation versus substituted content, proof expiry/revocation, epoch changes, legacy rows, R06/time rollback, stale restore and safe generic errors; no real identities/provider/email/payment effects. |

All fixtures must be freshly generated in memory or explicitly owned temporary files through the inspected isolated bootstrap. No working SQLite copy, dotenv, keys or credentials. No unrestricted suite selection. Future concurrency/fault tests are bounded evidence, not production crash certification.

## 10. Roadmap, actual verification and preservation

| Milestone | Development status |
|---|---|
| PD-01/02/03 and UI-01 | Existing dashboards, permissions, verification and premium light UI preserved. |
| PD-04A/B/B.1/B.2/B.3 | Existing guards/evidence/schema/lifecycle/crypto/internal submission preserved; operational and SE reset holds retained. |
| PD-05A read-only Chief review | Existing implementation and historical 376 tests / 3,099 assertions retained; no change here. |
| Decision Contract Review | Preserved historical proposal; this specification resolves the newly approved product choices and refines readiness/amendment dependencies. |
| This final specification | Delivered for owner/security review; technical policies/amendment/implementation remain unapproved and unimplemented. |
| Remaining existing-scope work | Approve dependencies, exact isolated amendment/readiness preparation and bounded registration/completion/decision/successor integration in reviewed stages. |
| PD-05B/PD-06/new features | Not started or authorized. |
| Production readiness | NO-GO; independent lawful processing, issuer/custody/retention, privileged auth/revocation, trusted time/restore/release and production fault evidence remain outstanding. |

This section supplies the roadmap update without rewriting prior reports/master-roadmap history. No completion percentage is assigned. Earlier 368/3,024 submission and 376/3,099 PD-05A results are prior executed evidence, not fresh tests of this proposal. **No application runtime, tests, browser, Artisan, formatter or migrations were run in this documentation-only task.**

Only `XLIPA-PD-05A-FINAL-DECISION-CONTRACT-SPECIFICATION-2026-11-12.md` is added. A bounded pre-write SHA-256 inventory covered 202 existing source/assets/scripts/tests/config/schema and prior PD-05A reports/roadmap. Final preservation comparison: **202 unchanged**, with identical sorted-path/hash inventory digest `adbd78d70965c331970ec22ace8dd3a7fbecfbb35da79e3f0f8d4650f1a95c0d`. Git status retained the prior six tracked modifications/five untracked deliverables plus this new document; staging remained empty. Existing tracked-diff and new-document whitespace checks passed. This is not a whole-workspace or working-database certificate.

No working SQLite/sidecar was opened/read/copied/hashed/modified. No real environment file, key, secret or credential was accessed. No source/schema changes, working migrations, real identity/OTP/email processing, public submission success, Chief writes, Selcom/payment, commit, push or deployment occurred.

**STOP FOR OWNER REVIEW. Working B3.2-C activation: NO-GO. Working SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**
