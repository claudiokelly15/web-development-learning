# XLipa PD-05A — Decision Contract Review

12 November 2026 · Africa/Nairobi · Inspection and logical contract proposal only

## 1. Review outcome

**A separately versioned schema amendment is required for the proposed decision guarantees.** Existing review fields and the `application.reviewed` event do not establish immutable decision evidence, an atomic decision/audit/application bundle, decision idempotency or reviewer authority epochs. SE-1.0 also forbids reopening an information-requested application.

The owner-approved low-friction journey is recorded below. Approval of that product journey does not approve this proposed contract, an amendment, implementation, real collection or operational activation. This is an internal source-based security analysis for owner/security review, **not an independent security audit, legal opinion, provider acceptance or production certification**.

Only this document is added. Existing uncommitted PD-05A implementation, tests, report and roadmap edits are preserved. No application code, schema, migration, configuration or dependency is changed.

**Working B3.2-C activation: NO-GO. Working SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED. STOP FOR OWNER REVIEW.**

## 2. Inspection and actual implementation findings

Read backend `AGENTS.md`; `.ai/rules` is absent at workspace, XLipa and backend paths. HEAD is `014460f1b8070e6f823fff3bd80ede294d4e8341`. The starting tree contains the previous PD-05A package: six modified tracked files and four untracked files, with staging empty. Those changes are baseline, not work introduced here.

Reviewed implementation outcomes and relevant contract/limitation sections through PD-05A: PD-01, PD-02, PD-03, PD-04A, PD-04B, PD-04B.1, PD-04B.2, PD-04B.3 and PD-05A, plus the B3.2-A minimum-field specification. Later source takes precedence over historical implementation status. For example, PD-04B.3 now calls evidence crypto internally, although PD-04B.2 predates that integration; public successful submission remains blocked.

Source references below are relative to `backend/`:

| Source inspected | Finding |
|---|---|
| `app/Http/Requests/Auth/RegisterRequest.php` | Signup requires fullName, businessName, email, phone, password/confirmation and accepted terms. Names max 200; email lowercase/ASCII/RFC/max 254; phone normalization and syntax validation; password min 12 and max 72 bytes, no NUL. Privilege/status claims are prohibited. |
| `app/Actions/Auth/RegisterMerchantOwner.php`, `app/Actions/Merchants/CreateMerchant.php`, registration controller | Creates user, merchant, active owner membership, current draft attempt 1 and merchant-created audit. Declared phone is stored in `merchants.business_phone`; no possession proof is created. Registrant remains a guest and is directed to login/email verification. |
| `routes/web.php`, authentication middleware, `MerchantPolicy.php`, dashboard controller | Dashboard access requires fresh active session, verified email and tenant membership. It requires neither phone proof nor full KYC. Auth readiness is restricted to local/testing and log/array mail; production auth readiness is not established. |
| `app/Actions/Auth/VerifyAccountEmail.php`, email verification controller | SE verification binds the exact email and signed binding epoch, advancing it once. Foundation verification retains its existing path. Verified email does not establish the person's identity. |
| `MerchantVerificationRequest.php`, `app/Actions/Merchants/MerchantVerification.php`, `VerificationEnvironment.php`, `VerificationReadiness.php` | Owner-only draft capture; password confirmation for capture; expected context version; encrypted versioned phone/NIN; individual-owner or representative declaration. Existing application-phone OTP/proof contract is five/thirty minutes. Default authorization, identity policy, keys and delivery are unavailable. |
| `MerchantApplicationController.php`, `app/Actions/Merchants/MerchantApplicationSubmission.php` | HTTP submission still calls the unconditional `submit(): never` guard, not PD-04B.3. The old evidence-blocker wording is a conservative integration hold, not evidence that the internal service is absent. |
| `app/Actions/Merchants/ApplicationSubmissionService.php`, admission and transaction classes | Strict 14-expectation internal command; fresh owner/session authorization, independent operational admission, protected SE capsule, single-use proof, atomic submission bundle, historical receipt and explicit uncertain-outcome resolution. Runtime admission is denied. |
| `SubmissionEvidenceSchema.php`, cipher/environment, `LifecycleMutation.php`, password-reset controller | Immutable submission evidence, logical-content commitments, epoch/profile/context checks, schema admission and reconciliation/time holds. Account-status/password/auth-version mutation hold and SE password-reset 503 remain. |
| `AdminApplicationController.php`, `PlatformRoleAssignmentPolicy.php`, `ResolvePlatformRole.php` | Chief-only redacted list/detail and application-scoped audit reads. Creator, submitter and all historical memberships conflict. Display indicators check selected references only; they are not cryptographic or operational decision admission. Staff has no decision grant. |
| Foundation merchant/application/platform-role/audit migrations; application/audit models; SE guards | Optional broad profile fields; five application states; mutable review columns; append-only bounded audits. No immutable decision object, decision writer, reviewer authority epoch or structured representative-authority evidence contract. |

Identity capture records a syntax-checked declaration under a policy. A subject claim, owner membership, name, NIN format or OTP success establishes neither authentic identity, legal representation, company existence nor payment-destination ownership. This review introduces no official NIN format, legal rule or provider requirement.

## 3. Approved low-friction journey and compatibility

The owner's approved journey is:

1. Register with minimal account information.
2. Verify email and phone through approved mechanisms.
3. Access the Merchant Dashboard without full KYC.
4. Complete required merchant profile and identity verification before applying to receive payments.
5. Submit only after required checks pass.
6. Undergo automated preliminary validation.
7. Receive a final Chief approval or rejection.
8. Become eligible for payment activation only after separate provider, legal and operational authorization.

Individuals without registered companies are supported, subject to applicable requirements. Company applicants use an authorized representative. Company documents must not become blanket individual-applicant requirements. Tanzania/TZS scope and the existing four minimum submission values are preserved.

| Journey element | Compatibility with source | Difference / unresolved decision |
|---|---|---|
| Minimal signup | Partial | Current signup already requires business name and phone and creates a merchant/draft. A replacement exact field set has not been approved. Do not silently remove fields or decouple registration. |
| Both contacts verified before dashboard | Partial | Email gate exists. Registration phone is only declared; application-purpose proof is not account-wide phone verification. Dashboard has no phone gate. Decide purpose, initial-entry/freshness semantics and access to verification before dashboard. |
| Dashboard without full KYC | Compatible | No NIN/company-document gate. Preserve unavailable financial functions. |
| Minimum profile before application | Compatible with existing minimum | Business name, verified owner email, verified application phone and owner/representative NIN remain required. Broad optional fields are not universal blockers. No full-profile editing workflow is established by the inspected routes. |
| Identity verification before applying | Unresolved assurance gap | Current protected declaration is not authentic identity verification. Define required assurance/evidence and phase. If authentic verification is required and unavailable, eligibility remains held; do not relabel syntax or invent a provider. |
| Successful submission | Internal-only capability | Protocol exists; HTTP success and operational admission remain disabled. Product approval is not integration authorization. |
| Preliminary validation | Existing technical checks | Checks already run before/inside submission. Preserve them; do not add a scoring engine, post-submit service or automatic approval. Revalidate again at decision time. |
| Chief final decision | Read-only foundation only | Requires the separately approved contract/amendment below. |
| Payment activation | Intentionally unavailable | Approval satisfies at most one review prerequisite, never provider/legal/operational permission or live payment eligibility. |

## 4. Field-level minimum information

### A. Registration requirements

| Field/fact | Current handling | Proposed disposition for owner review |
|---|---|---|
| Full name | Required, bounded; users.name | Retain account name. Not independent identity proof. |
| Email | Required normalized account address; verified later | Retain. Exact verified submitting owner's address remains the minimum application email. |
| Password/confirmation | Existing secure password rules; hash stored | Retain. Confirmation is transient, not profile data. |
| Terms acceptance | Required; version/time recorded | Retain; does not substitute for sensitive-data purpose/notice/legal basis. |
| Business/display name | Required at signup; merchant created immediately | Preserve until owner approves deferral. Always required by submission. Trading name does not imply registered-company status. |
| Phone | Required syntax-valid declared `business_phone`, stored in ordinary foundation profile | Preserve current behavior here. It is distinct from the encrypted verified application contact. Decide minimization/retention and whether duplicate declared storage should be deferred or retained; no erasure/migration is authorized. |
| IDs, tenant, role, status, epochs | Server-controlled; privileged claims prohibited | Preserve. Signup cannot grant approval or platform authority. |
| NIN, company documents, bank/mobile-money destination | Not signup prerequisites | Do not add to signup or dashboard entry. |

### B. Merchant application requirements

| Value/control | Required status | Evidence and limits |
|---|---|---|
| Business name | Required | Current nonempty bounded name; protected historical name in SE capsule. Current display name is separate. |
| Verified account email | Required | Exact active submitting owner's address and verification binding/epoch. Optional business_email cannot inherit it. |
| Verified application phone | Required | Explicit current Tanzania mobile contact; trusted actor/tenant/purpose/contact-version proof, available/unrevoked/unexpired at submission and consumed once. Declared signup phone is insufficient. |
| Owner/representative NIN | Required | Dedicated protected current record, approved normalization/syntax/declaration/notice and logical-content commitment. Never raw NIN in capsule, audit, decision, receipt or logs. No alternative ID or official format invented. |
| Relationship/declaration | Required procedural binding | Individual owner or authorized representative; actor/time/policy recorded. This is not proven legal representation or a broad company profile requirement. |
| Owner/session and exact draft/version tuple | Required security state | Fresh owner authority, current attempt/profile/context/email/membership/identity/contact epochs; no conflicting bundle or pending reconciliation. |
| Custody, lawful processing, issuance, time, retention/hold and restore admission | Required operational prerequisites | Missing admission denies capture/submission. SQL labels and policy names alone are insufficient. |
| Authentic identity assurance | Owner wording requires resolution | Current declaration cannot establish it. Approve its meaning/method and phase; if mandatory before applying, absence blocks eligibility. This proposal neither removes that requirement nor claims an unimplemented verifier exists. |

### C. Conditional individual/company requirements

| Information | Individual | Company | Current gap / boundary |
|---|---|---|---|
| Classification | May be unregistered | Company assertion needs approved classification | Optional business_type is not an independently verified classification. Absence remains unclassified; never infer individual/company from name. |
| Identity subject | Individual owner's NIN/declaration | Natural-person authorized representative's NIN/declaration | Existing subject_claim distinguishes assertions, not authentic identity or authority. |
| Representation evidence | No company-authority document merely because companies need one | Establish authority to the approved assurance before a decision requiring it | Platform ownership is not legal authority. No approved structured authority evidence/access contract is implemented; required evidence unavailable means substantive approval held. |
| Registration country/number, legal name, TIN, licence/certificates | No blanket initial-submission requirement | Conditional only under a specifically approved type/phase rule | Optional columns do not prove registration. No new uploader, document store or provider is proposed. |
| Address/website/description/extra contacts | Optional absent approved narrow rule | Same phase-specific discipline | Some columns exist; SE-v1 does not archive optional historical profile values. Current values cannot be treated as historical submitted evidence. |
| Bank/mobile-money destination | Separate activation-stage requirement if approved | Same | Not an initial-application minimum; no collection/verification flow authorized here. |

Any conditional requirement needs an approved rule ID/version, authoritative basis, affected merchant type, market and phase. No legal/provider source was established in this task; no universal registration/TIN/licence/document rule is asserted.

### D. Information held pending lawful processing and security approval

Do not collect real NIN, identity scans, representative authorizations, company documents, biometrics/selfies, registry responses or payment-destination details before the applicable purpose, lawful processing and operational security are approved. This task adds no collection channel. Real application-phone capture/OTP remains behind existing denial. Existing account-name/email/declared-phone source capabilities are not a new authorization for real onboarding execution.

Do not retain plaintext OTP, passwords/confirmation, reset tokens, secrets, ordinary-log NIN/phone, or unkeyed low-entropy identity hashes. Collect no speculative future-use identity attributes. Approval must cover notice, minimum data, recipients/access, retention/holds, rights, custody/recovery and deletion across backups. No legal conclusion or retention duration is supplied here.

## 5. Automated preliminary validation

Automation returns technical eligibility or hold results. It grants neither merchant approval nor payment activation. Browser indicators and earlier preflight checks are advisory; authoritative checks run under the write protocol.

| Check | Existing baseline | Meaning and limits |
|---|---|---|
| Completeness/shape | Request validation, strict 14 expectations, business name/current declaration | Bounded required inputs, not truthful person/company data. |
| Verified contacts | Exact email binding and actor/tenant/contact-purpose proof | Address verification/phone possession at a defined event, not permanent identity assurance. |
| Fresh owner authorization | Reload active verified user/session, exact owner membership and epoch, tenant policy | Permission to submit this tenant's application, not legal representation. |
| Provenance | Consumed challenge, timely accepted delivery intent and exact proof plus independent trust admission | SQL `live` alone does not authenticate issuance. Default denies. |
| Duplicates/conflicts | Current draft/attempt; unique evidence/proof; scoped UUID replay tuple | Command/attempt duplication protection. No global duplicate-NIN/person/company detection exists; no new identity index or blacklist proposed. |
| Evidence/profile integrity | Exact logical versions/epochs, contextual AEAD and protected source-content MACs | Historical integrity/binding. Source-envelope rewrap preserves logical content; ciphertext equality is not the criterion. |
| Submission eligibility | Schema, trusted time/high-water, R06, suspension/closure, policy/retention/restore/custody | Technical prerequisite, never approval. |
| Authentic identity/representation | Not established | Cannot pass automatically from format, declaration or OTP; unresolved required assurance remains a blocker. |
| Review-time readiness | PD-05A displays only selected reference checks | Future decision processing must independently validate the complete required contract. |

Keep checks before submission as well as at the later decision boundary; the journey's preliminary-validation step does not mean invalid submissions should first be committed. No new durable preliminary-approved/under-review state, score, provider, background service or automated rejection is required. Outages and integrity failures produce holds/errors, not adverse applicant decisions.

## 6. Proposed Chief decision contract

Everything in this section is **PROPOSED**, not approved, implemented or independently audited.

### States and information-request continuation

| From | Allowed proposed result | Constraint |
|---|---|---|
| draft | pending_review | Existing SE submission protocol only; HTTP remains disabled. No Chief decision on draft. |
| pending_review | approved | Explicit Chief choice and all approval prerequisites; one immutable decision bundle. |
| pending_review | rejected | Explicit supported reason under approved policy; no financial side effect. |
| pending_review | information_requested | Procedural clarification, never approval; only after continuation semantics are approved. |
| approved/rejected | No reopening/reversal in this contract | Preserve historical decision. Appeals/general reapplication require separate authority. |
| information_requested | No in-place pending_review/draft transition | SE-1.0 forbids it; do not change/drop the trigger, reuse consumed proof or rewrite evidence. |

**Recommended continuation, requiring owner approval:** retain the information-requested attempt's submitted evidence, decision and status unchanged. An explicitly authorized later owner action may create exactly one linked successor draft, with a new application ID, monotonically increasing attempt and exact predecessor-decision reference. Release of the predecessor's current_slot, successor linkage and draft creation must be one guarded transaction under the new contract; releasing that slot must not alter historical submission fields. The successor needs fresh current authority/contact/identity/profile admission, a fresh proof and its own new SE submission bundle/request UUID. No copied proof, backfill, automatic resubmission or expiry extension. Its later Chief decision is independent of the predecessor's procedural outcome.

This avoids SE's forbidden transition while retaining existing state names. It is a proposal to resolve the identified information-request gap, not permission for a general reapplication product. Owner may instead defer the action/continuation. Do not enable an information-request action that appears recoverable without an approved recovery path.

### Decision authorization and conflicts

Require authenticated reviewer identity, freshly loaded active verified account, matching model/session/auth epoch and an effective unrevoked Chief assignment. Queue-read permission is insufficient: require action-specific decision authorization. Staff roles, merchant membership, request flags and cached relations grant no Chief decision power. Recheck under the reserved writer and immediately before commit.

Deny reviewers who created the merchant, submitted the application or have **any historical membership** in that tenant, including revoked membership. Scope every application/evidence/audit query to the exact merchant. If all Chiefs conflict, hold for owner resolution; no Staff fallback or conflict bypass.

Propose recent privileged step-up and approved MFA admission for new decisions. No decision-specific mechanism is established in code. Do not promote password confirmation or application SMS possession to privileged MFA. Method/freshness/recovery policy needs approval; missing required admission denies writes.

Platform assignments lack monotonic authority epochs. Recommend an amendment-owned epoch for each logical role/grant/revocation change, rejecting overflow, rollback and delete/reinsert/REPLACE resets. Bind commands to the observed reviewer epoch to defeat change-away/change-back. Structural version 1 for old rows does not authenticate historical provisioning. No role-management service is authorized by this proposal.

### Immutable evidence and strict command

Propose one append-only typed decision record per submitted attempt, with no UPDATE/DELETE/REPLACE. Exact names/DDL/artifact identity require later review. Minimum logical fields:

- Server-generated decision ID and contract version; tenant/application/attempt; immutable SE evidence reference/version.
- Reviewer user ID, observed auth and platform authority epochs; approved authorization/step-up policy reference, never passwords, OTP or bearer credentials.
- Canonical request UUID and complete expected command: exact pending attempt/submission/profile/context/identity/contact/email/owner bindings, reviewer epoch, outcome and reason/template semantics. Client expectations detect staleness; server derives authoritative facts.
- Prior pending_review state, outcome, reason policy/code, immutable feedback template version and bounded safe plain-text feedback.
- Trusted UTC milliseconds and exact canonical text time, audit ID/type, validation and retention policy versions.
- Versioned evidence of the admitted historical submission and current facts actually checked. Identity/representation assurance references may be recorded only when authentic evidence exists under an approved contract; a policy label or checks-passed boolean cannot invent it.

No raw NIN, phone, email, documents, source envelopes, keys, fingerprints or content MACs in the decision/public receipt. Authorized internal verification may use existing dedicated crypto; no reveal route is implied. Do not substitute ciphertext hashes for logical-content checks, because legitimate source rewrap changes ciphertext. Optional historic values absent from SE-v1 require an expressly approved evidence extension before relying on them.

Typed references/uniqueness must bind tenant, application, submission, decision and audit fields. Freeze observed authority epochs; do not create lifetime-equality FKs to mutable current epochs that would prevent legitimate revocation. Check live epochs at insertion/current-use boundaries.

### Atomic decision, application and audit protocol

Compose new additive guards with unchanged accepted C/SE objects:

1. Admit exact composed schema, trusted time/restore/retention/custody and reviewer scope. Refuse nested/unsupported transactions. Reserve the SQLite writer before workflow reads; no automatic retry loop.
2. Resolve an existing scoped request first, validating exact command and complete historical bundle. Otherwise require the exact current pending attempt and no prior decision. Validate submission integrity and outcome-specific current prerequisites.
3. Derive server-side immutable decision evidence. Insert while application is pending; an amendment-owned trigger creates the exact redacted `application.reviewed` audit.
4. CAS the exact pending attempt/profile/submission tuple to recorded outcome and exact reviewer/time/safe-feedback projection; require one affected row. Leave merchant operational/verification flags unchanged.
5. Enforce deferred final-state application/decision/audit agreement, recheck current authority/time/lifecycle/admission and commit once. Known uncommitted errors roll back all transactional writes.

Require one decision per application, one audit per decision and unique scoped request. Deny direct decision-like application updates, orphan/replayed review audits and incomplete commits. Freeze result/reviewer/time/feedback projections after decision. Preserve legacy reviewed rows as explicitly nonadmitted history; never backfill fabricated decisions.

Proposed canonical audit metadata fits existing six-key allowlist/2048-byte bound: `from`, `to`, integer `profile_version`, `reason_code`. Bind ID/type, reviewer, tenant, application, UUID and time via columns; target_user_id is NULL. No feedback, notes, identifiers or new metadata keys. Exact canonical byte order, tuple references and bounds need concrete artifact review. Database constraints cannot authenticate issuer custody or stop an administrator who disables guards/replaces files/owns keys; operational admission remains separate.

### Outcomes, reasons and merchant-safe feedback

Approval is a Chief's merchant-application decision under a named policy. It does not assert identity checks that were never performed, permanent verification, provider acceptance or permission to receive funds. If required identity/representative assurance is unavailable, approval is held.

Rejection requires an affirmative supported applicant-related finding under an approved reason policy. Missing keys, outages, conflicts, uncertain restore/time or corrupt/unverifiable evidence produce hold/error, not rejection or a fabricated fraud finding.

Information request records clarification only. It may address a known current-profile mismatch if historical integrity and reviewer authority remain established, under approved policy. Corrupt/unverifiable historical evidence cannot be converted into a valid procedural decision. Enable this outcome only with the approved continuation contract.

Approve a closed outcome-specific reason catalog and versioned safe feedback templates. Candidate categories for policy review include clarification needed, changed profile requiring fresh submission, unsupported application under an approved rule, and requirements satisfied. These are not final approved codes or legal findings. Freeze bounded plain-text template feedback, escape rendering, and exclude markup/links/internal risk signals/copied personal values. No unrestricted reviewer notes in v1; existing internal_review_notes is not an approved evidence channel.

### Idempotency, concurrent decisions and unknown outcomes

Replay scope is merchant + authenticated reviewer + canonical request UUID, with a separate UNIQUE application-decision invariant. Matching replay requires identical expectations, outcome, reason and feedback/template semantics plus a complete validated bundle. Changed command/same UUID conflicts; another UUID for a decided attempt conflicts. No overwrites or extra audits.

Safe historical receipt: application ID, merchant ID, request UUID, outcome, canonical decision time and `application.reviewed`. It proves a historical event, not current eligibility. Replay/resolution still requires current authorized nonconflicted access and admitted historical validation. A revoked reviewer cannot use a UUID as an access bypass; another Chief may read permitted history but cannot impersonate that command.

Concurrent decisions serialize; pending-state CAS and uniqueness admit one winner. Busy/stale/conflicting requests return generic errors without blind retries. A lifecycle change serialized before the decision is observed; one afterward affects current readiness without rewriting history.

COMMIT acknowledgement loss or failed rollback means **unknown outcome**: discard connection, no claim of failure, no automatic replay/opposite decision. An explicit fresh serialized resolver needs authoritative restore/replay admission, fresh scope and exact bundle validation. Return a validated receipt if present. Establish absence only with unchanged expected pending state and no partial/conflicting audit/decision; null does not retry. Explicit later same-command submission rechecks all prerequisites. Without authoritative restore admission, remain unknown/held. Never manufacture or repair decision evidence to resolve uncertainty.

## 7. Evidence freshness, lifecycle and approval eligibility

| Situation | Proposed handling |
|---|---|
| Proof naturally expires after valid consumption | Historical submission remains true. Do not require consumed proof to become available or test submission expiry against decision time. Owner must approve any review-age/fresh-contact window; no duration invented. A fresh-evidence requirement needs approved successor/lifecycle handling. |
| Source ciphertext rewrapped, logical content unchanged | Verify contextual AEAD and retained logical-content commitments using authoritative envelope revisions. Rotation is not a changed identity. |
| Owner revoked; email/profile/context/identity/contact binding changed | Preserve history; hold approval on stale authoritative bindings. No rebinding old evidence to another owner. Procedural clarification only under approved semantics. |
| Consumed proof later revoked/trust withdrawn | Preserve valid historical event; hold current approval readiness pending approved assurance/lifecycle disposition. Today's SQL live label is insufficient historical issuer admission. |
| R06, suspension/closure, retired policy, key loss, retention/custody denial, rollback time or restore uncertainty | No new substantive decision. Hold/error without application-status mutation. |
| Reviewer revoked/regranted/account/session changes | Deny/stale new commands by fresh authority/epochs. Historical decision remains; future access/readiness rechecks authority. |
| Legacy submission/review lacks admitted SE bundle | Read permitted redacted history; deny new decisions pending a separately approved legacy policy. Never fabricate/backfill evidence. |

Approval must independently validate the complete historical SE evidence/audit/consumption bundle and applicable current unchanged owner/email/profile/contact/identity bindings, source content, trust, policies, retention, custody and authentic identity/representation assurance. PD-05A partial display indicators cannot supply this admission.

The owner-only submission service cannot simply be invoked as Chief: its actor authorization, historical receipt and time semantics serve another operation. Any reuse of validation primitives requires a separately reviewed internal contract; no owner impersonation or broader disclosure.

A coherent restored backup can show pending state after a newer database committed a decision. Schema checks/local absence cannot detect that. Authoritative cutover/restore admission and replay fencing are mandatory and currently unimplemented operationally. Never reopen/approve solely because restored tuples agree.

All three outcomes leave merchant operational/verification flags and financial capabilities unchanged. No `enabled` or implicit `verified` mapping, provider call or financial account is created. A later payment-activation gate must separately establish current eligibility, provider/legal permission, operational authorization, assurance freshness and financial controls. Application approval is one historical prerequisite only. Any later merchant-status projection requires its own explicit semantics and lifecycle review.

SE-1.0's account-status/password/auth-version hold and password-reset 503 remain untouched. Privileged revocation/session recovery must be resolved before live decisions; this proposal does not relax the hold for convenience.

## 8. Exact gaps and amendment conclusion

| Gap | Proposed minimum resolution, pending approval |
|---|---|
| Mutable review fields, no immutable decision identity | New typed append-only decision record, one per submitted attempt; protected application projection. |
| No required atomic review tuple | Additive application/decision/audit guards and deferred final-state constraints; no edits to frozen C/SE artifacts or pins. |
| Submission protocol does not cover Chief command | Decision-specific request uniqueness, expectations, receipt, one-commit/explicit-resolution protocol. |
| Platform-role authority ABA and missing privileged step-up | Amendment-owned monotonic epoch/guards plus approved authorization/MFA/revocation admission. |
| information_requested → pending_review forbidden; capture requires draft | Owner-approved successor attempt, exact predecessor-decision link, one successor and atomic current-slot handoff. Otherwise leave action/continuation disabled. |
| Authentic identity/company authority missing | Explicit assurance/evidence policy; no invented API, document channel or pass stamp. |
| Signup/dashboard phone mismatch | Owner selects exact minimum signup and pre-dashboard phone-purpose/freshness/reachability semantics; later bounded integration only. |
| SE-v1 does not freeze optional historical data | No retrospective assumption; expressly approved historical allowlist extension if actually required. |
| Free-form feedback lacks approved semantics | Versioned closed reasons and bounded immutable safe templates, no arbitrary notes. |
| Live operational admission absent | Preserve all activation holds; source/schema approval cannot provision operations. |

**Amendment required even if information-request continuation is deferred.** Recommend a new independently named/versioned amendment, exact composed-schema readiness, manifest and isolated constraint tests. This is a logical design, not executable DDL or proof that composition has been implemented. One trigger cannot override another trigger's denial; the successor model avoids forbidden reopening. If concrete composition proves incompatible, stop for an explicitly versioned superseding design instead of editing frozen artifacts or weakening constraints.

Preserve existing IDs, ciphertext, history/audits and migration records; refuse partial/incompatible admission and unsafe downgrade. Do not invent historical decisions, authentic role grants or identity/provider approvals. No amendment or migration command is supplied here.

## 9. Owner/security decisions requiring approval

| Decision | Recommendation / unresolved boundary |
|---|---|
| Minimal signup fields | Keep current behavior until explicit approval to defer businessName/phone or automatic merchant/draft creation. Preserve four-field submission minimum. |
| Phone before dashboard | Clarify initial entry versus every visit, freshness, proof purpose and pre-dashboard access. Do not accidentally lock dashboards every thirty minutes by importing application-proof expiry. No new account-phone service authorized. |
| Identity verification before applying | Define authentic assurance, evidence/method and phase. If mandatory, hold until established; syntax/OTP cannot be relabelled. No provider selected. |
| Classification/representative authority | Approve asserted types and phase-specific evidence. No blanket company certificates for individuals. |
| Decision amendment and reviewer epoch | Approve logical invariants before exact isolated DDL/manifest/test preparation. Existing transition allowance is not write authority. |
| Information requests | Select successor-attempt model or defer. No in-place reopening, general appeals or automatic resubmission. |
| Chief step-up/MFA/conflicts/recovery | Approve method/freshness and emergency revocation. Historical membership conflicts remain; all-Chiefs-conflicted cases held. |
| Evidence freshness/lifecycle | Approve review age, changed-binding and post-revocation handling; no arbitrary timeout or lifetime approval. |
| Reasons and safe feedback | Approve closed outcome-specific catalog/templates, bounds and disclosure rules. |
| Lawful processing/provider/retention | Qualified owners establish purpose/notices, conditional rules, authority evidence, custody/access, holds, deletion/recovery. No legal conclusion or retention period invented. |
| Legacy admission and approved-status meaning | Recommend no new legacy decisions/backfill and no merchant/payment flag side effects. Alternatives need explicit policy. |

The overall journey approval does not resolve these details. A bounded subset may be approved while dependent portions remain held.

## 10. Next safe step and later acceptance criteria

Next step: **owner/security review of this concrete proposal**, particularly identity assurance, phone gating, amendment and information-request continuation. Only after explicit logical-contract approval may a later scoped package prepare exact isolated schema/manifest/constraint tests. Do not implement decision service/UI first; do not automatically start PD-05B/PD-06.

Later acceptance evidence must cover canonical complete bundles; raw-SQL orphan/partial/rewritten decision/audit denials; exact tenant/conflict and epoch ABA resistance; concurrent one-winner behavior; identical replay/changed-command conflict; before/after-COMMIT acknowledgement loss and authoritative explicit resolution; immutable submission preservation; rewrap compatibility; profile/contact/revocation/retention/time/R06/restore holds; safe feedback/privacy; successor slot/link atomicity if approved; legacy non-adoption; and zero merchant/payment side effects. Use freshly generated fictional fixtures. These are future criteria, not executed tests.

No runtime, PHPUnit, browser, Artisan, migrations or formatter ran in this documentation-only task. Prior PD-04B.3 **368 tests / 3,024 assertions** and PD-05A **376 tests / 3,099 assertions** are historical executed evidence, not fresh tests of this unimplemented proposal.

## 11. Updated XLipa roadmap status

| Milestone | Current development status |
|---|---|
| PD-01 dashboard; PD-02 Chief/Staff workspaces; PD-03 verification; UI-01 premium light UI | Existing work preserved, operational restrictions retained. |
| PD-04A/B/B.1/B.2/B.3 guard, evidence/schema, lifecycle/crypto and internal submission | Existing source and bounded evidence preserved; public success, real admission and SE password reset remain held. |
| PD-05A read-only Chief review | Prior implementation remains unchanged, awaiting owner review; test counts remain historical evidence. |
| PD-05A decision-contract review | This proposal delivered; awaiting owner/security approval. No decision implementation/amendment completed. |
| Remaining approved onboarding/review work | Resolve named policies, then separately approve concrete isolated amendment preparation and implementation. |
| PD-05B/PD-06/new features | Not started or authorized; scope freeze remains. |
| Operational readiness | NO-GO: trusted issuer/admission, lawful identity/provider policies, custody/recovery, retention/holds, privileged auth/revocation, trusted time, authoritative restore/fencing and deployment-grade crash/concurrency evidence unresolved. |

This section provides the requested roadmap update while preserving the existing master-roadmap file. Development progress is separate from production readiness. No unsupported completion percentage is assigned.

## 12. Preservation and delivery evidence

Only `XLIPA-PD-05A-DECISION-CONTRACT-REVIEW-2026-11-12.md` is added by this task. A bounded SHA-256 inventory covered 201 existing source, asset, script, test, config, schema and prior PD-05A report/roadmap files before writing. Final comparison: **201 unchanged**, identical sorted-path/hash inventory digest `547d7955b3c7bae91c555632ffb17347c20148813671db446106d0cbf3e23c23`. Git status retained exactly the prior six tracked modifications/four untracked files plus this new report; staging remained empty. Existing tracked-diff whitespace check passed. This is not a whole-workspace or working-database certificate.

No working SQLite/sidecar was opened, read, copied, hashed or modified. No real dotenv, secrets, operational keys or credentials were accessed. No working boot/migration, real identity capture, OTP/email delivery, new API/provider, public submission success, Chief decision, Selcom call or payment occurred. No source/schema change, commit, push or deployment occurred. Existing uncommitted work was preserved.

**STOP FOR OWNER REVIEW. Working B3.2-C activation: NO-GO. Working SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**
