# XLipa — Business Name Completion Security Contract

12 November 2026 · Africa/Nairobi · Documentation and inspection only

## 1. Conclusion and authority boundary

The existing onboarding page is the appropriate integration point for completing an absent business name. SE-1.0 already permits a correctly versioned merchant-name update and synchronization of a current draft without rewriting submitted evidence. It does **not** supply a semantically suitable completion audit event or an atomic name-change audit bundle.

**Recommendation:** require an atomic, redacted completion record for an actual empty-to-named change. Obtain owner approval for a minimal separately versioned append-only completion-audit contract before implementation. Do not overload an existing event, extend the frozen audit enum, silently omit the audit or implement unaudited completion first. Exact amendment/readiness composition must be reviewed separately.

The product behavior is approved: optional signup name, existing merchant/owner/draft creation, verified-email dashboard access without full KYC/OTP, mandatory business name before application submission, and later owner completion inside existing onboarding. This document specifies the proposed security/implementation contract; it does not authorize source changes, an audit amendment, runtime execution or activation. It is internal source-based analysis, not independent security certification or a legal opinion.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**

## 2. Current implementation and inspected baseline

Read backend `AGENTS.md`. `.ai/rules` is absent at workspace, XLipa and backend paths. HEAD remains `014460f1b8070e6f823fff3bd80ede294d4e8341`. The existing uncommitted PD-05A and minimal-registration changes are baseline work and are preserved. No application runtime, migrations, tests, browser or formatter was executed for this review.

Reviewed the Minimal Registration Implementation report and relevant PD-05A Final Decision Contract Specification sections against current source:

| Source, relative to `backend/` | Finding / exact integration relevance |
|---|---|
| `app/Models/Merchant.php`, foundation merchant migration | business_name is NOT NULL text; signup absence is `''`. `businessNameLabel()` supplies display text only. profile_version is integer-cast; broad optional fields exist but are outside this operation. |
| `app/Actions/Auth/RegisterMerchantOwner.php`, `CreateMerchant.php` | Registration creates the atomic user/terms/merchant/active-owner/current-draft/merchant.created bundle at profile version 1. Nullable creation name becomes `''`; no completion writer exists. |
| `app/Http/Controllers/MerchantOnboardingController.php` | GET-only show action resolves merchant from route, authorizes viewSummary, loads current application and renders existing onboarding. It currently selects no application profile_version; a future form must load authoritative versions explicitly. |
| `resources/views/merchants/onboarding.blade.php`, `MerchantOnboardingSummary.php` | Existing premium light status page, account link and application/verification links. Unnamed label exists, but no name input or completion form. |
| `routes/web.php` | Existing `/merchants/{merchant}/onboarding` GET under authenticated, readiness, active-session, verified-email and ResolveMerchantContext middleware. No completion POST/resolve route exists. |
| `app/Http/Middleware/ResolveMerchantContext.php` | Member-scoped route resolution selects id/name/operational/verification status, **not profile_version**. The route model is not an authoritative version snapshot for a new write. |
| `app/Policies/MerchantPolicy.php` | viewSummary permits active verified tenant members. manageVerification additionally requires active owner with revoked_at NULL. View access is not sufficient write authority. |
| `app/Models/MerchantApplication.php`, foundation application migration | Current slot, attempt/profile versions and draft/submitted history. Model prohibits submitted-field edits/deletion, but raw-SQL safety comes from schema, not model callbacks alone. |
| `app/Actions/Merchants/SubmissionEvidenceSchema.php` | SE profile guard increments exactly once per logical profile change and forbids version-only changes/overflow. Submitted application identity/profile/snapshot/submitter/time are frozen. Exact schema object/ledger admission rejects unknown additions. |
| `app/Actions/Merchants/MerchantApplicationSubmission.php`, `ApplicationSubmissionService.php` | Empty name is a submission blocker; public success remains unconditional denial. Internal service needs exact matching profile versions and full operational/evidence admission. No completion behavior is supplied. |
| `LifecycleMutation.php` | Detects partial SE profiles and denies rather than falling back. Its legacy branch is not an adequate default for a new SE-governed completion operation. |
| `VerificationStore.php` | Existing zero-row SQLite writer reservation before workflow reads; rollback exceptions are suppressed and there is no dedicated commit-uncertainty result. Do not claim it already meets this contract's unknown-outcome guarantee. |
| `SubmissionTransaction.php`, `SubmissionOutcomeUnknown.php` | Existing one-writer/one-commit discipline; COMMIT uncertainty or rollback failure discards connection and raises explicit unknown. Reuse of the mechanism may be reviewed; it does not itself provide completion authorization, restore admission or a completion resolver. |
| `AuditEvent.php`, `AuditLog.php`, foundation audit migration, C audit SQL/guards and SE audit guards | Fixed event set, bounded metadata, append-only history and operation-specific submission/R06 insertion contracts. No business-profile/name completion event. |

Relevant inspected tests include the appended minimal-signup cases in AuthenticationTest, MerchantDashboardTest, SubmissionLifecycleTest and ApplicationSubmissionServiceTest; schema profile/version/raw-SQL tests in SubmissionEvidenceSchemaTest; and generated contention/before-and-after-COMMIT acknowledgement cases in ApplicationSubmissionServiceTest. These demonstrate existing mechanisms within earlier fictional runs, not proof of the unimplemented completion protocol.

## 3. Proposed operation and narrow scope

Use the **existing onboarding controller/page** for a proposed owner-only completion POST inside the same merchant middleware group. A possible suffix is `/merchants/{merchant}/onboarding/business-name`; final route name is a later source-review detail. No GET mutation, new dashboard, general profile editor, arbitrary model mass assignment or new product/service. A separately authorized read-only outcome-resolution request is described in section 8; it must never auto-submit.

This operation permits only:

- Current persisted name exactly `''` → validated nonempty supplied name.
- A supplied name exactly equal to the current canonical stored name, with fresh matching expectations → genuine no-op.

An existing **different nonempty** name is outside completion scope and conflicts; do not turn this into rename/edit functionality. Noncanonical legacy whitespace/other inconsistent stored states require explicit review, not silent cleanup. Current absence does not prove historical signup omission; merchant.created did not freeze that fact. Do not invent a historical-origin attestation.

### Input contract

Allowlist only `businessName`, `application_id`, `attempt_number`, `merchant_profile_version`, `draft_profile_version`, `membership_id`, `authority_version`, `auth_version`, `email_binding_version`, and canonical lowercase `completion_request_id`, plus the transport CSRF token. Tenant is taken from the authorized route and actor from authentication. No client actor, old name, new version, event/time/key, approval/status or other profile fields.

Validate IDs using existing parent-compatible identifiers and exact tenant lookup. Internal expectations are positive integers; HTML hidden fields may be parsed only from bounded canonical positive decimal strings, rejecting booleans, arrays, floats, exponents, leading-sign ambiguity and overflow before casting. UUID is correlation, not permission. Unknown/nested/extra fields fail without writes. Expected merchant/draft versions must both equal authoritative V; mismatches are not repaired.

`businessName` must be a string of valid UTF-8, trimmed under an explicitly tested ASCII-whitespace convention, nonempty/non-whitespace, at most 200 characters, without NUL/control characters or embedded line breaks. Preserve case and supplied text; no registered-company inference, title case or Unicode-normalization rewrite. Reject numeric input types; a string consisting of digits need not be forbidden as a business display name. Escape rendering; never interpret markup. Approve/test the exact validator in the later implementation diff rather than silently changing signup semantics in this task.

Use POST with CSRF protection; GET/PUT/PATCH/DELETE cannot perform completion. Proposed bounded route throttle may follow the existing 10/minute owner-write convention; exact limiter mapping needs review. Do not require phone OTP, NIN, full KYC or a Chief permission for this name operation. No new privileged MFA requirement is inferred for a low-impact owner completion; account/session freshness remains mandatory.

## 4. Fresh authorization and tenant requirements

Existing middleware and manageVerification policy provide reusable **starting checks**, not sufficient once-only authorization. Under the reserved writer, reload:

1. Authenticated identity; session auth epoch and current user; active account and verified exact email binding. Require supplied actor context/model/session/expected auth epochs to agree.
2. Exact merchant and expected membership belonging to this actor/tenant; active owner role, revoked_at NULL, observed authority epoch equal expected. Platform role alone grants nothing. A Chief/Staff who independently is a legitimate owner acts only through that ownership, never impersonation or a platform override.
3. Merchant operational status not suspended/closed; current profile version/name.
4. Exact application ID/merchant/attempt, current_slot text `'1'`, draft status, matching merchant/draft V, NULL submission fields/profile_snapshot, and no SE evidence or submitted audit for this draft. Missing/partial/conflicting workflow state is held, never adopted or repaired.

Recheck relevant live authority/state before commit. Foreign tenant/missing membership remains nonenumerating 404 through context resolution; in-tenant nonowner is 403. Guest/unverified/stale/inactive session behavior follows existing login/verification controls. Suspended/closed/stale-version/wrong-current-application/submitted contexts conflict without writes. Unexpected adapter/SQL failures return generic unavailable responses, never debug traces/private values.

Do not trust route-bound Merchant's missing profile_version, a previously loaded membership, cached policy result or a name/version in old form data. No Chief/Admin/Staff delegation. Writes require an explicitly admitted composed SE profile; foundation/C-only, partial SE or unknown schema is not a permitted weaker fallback for this new operation.

## 5. Name and version semantics

| Situation | Required result |
|---|---|
| Stored `''`, supplied valid name, exact current draft and V | Change name and merchant profile V→V+1 once; draft profile V→V+1 in the same transaction; append required completion record once. |
| Current stored name equals supplied normalized bytes; both expected versions current/equal | No-op: no version increment, updated_at write, audit/completion row, notification or verification change. Still enforce authorization/current draft/schema/holds. |
| Identical name but stale expected V/draft | 409 conflict, **not** no-op success. Do version/current-draft validation before equality classification. |
| Stored different nonempty name | Conflict: general renaming is outside this operation. |
| Blank/malformed/oversize proposed name | Generic validation error, no persistence or sensitive logging. |
| Merchant V and draft version disagree | Hold/conflict; do not silently synchronize an already inconsistent graph. |
| Two competing requests with expected V | Writer serialization and CAS yield one actual winner. Later stale request conflicts, even if names match. Fresh current-expectation identical-name command may be a new no-op. |
| V at signed 64-bit/PHP platform maximum | Actual change denied before arithmetic. A genuine validated no-op requires no increment and need not overflow. Reject nonpositive/out-of-range/coerced versions. |
| Application already submitted/pending/reviewed | Completion/no-op operation denied; no submitted evidence or version rewrite. |
| Failure after merchant update, draft update or record insertion | Entire known-uncommitted transaction rolls back, including version/timestamps/record; no partial result. |
| COMMIT acknowledgement or rollback failure | Dedicated unknown outcome, purged connection, no automatic replay or asserted rollback. |

Initial V=1 becomes 2 when the name is completed. Do not hard-code V=1: earlier legitimate changes to other profile fields may have advanced V. Freeze submission profiles only at actual submission. Unrelated historical applications retain their original profile versions, snapshots, actors and timestamps; only the exact current draft is synchronized.

Do not change contact/identity/context/email/membership versions, consume/revoke proofs, reset OTP/R06 counters or mutate operational/verification status solely for name completion. A legitimate current application-phone proof need not be revoked just because the business name changes; later submission still validates exact current profile/evidence. Existing reconciliation/time/restore holds must not be cleared or ignored by this operation.

## 6. Audit compatibility conclusion

### Existing contracts

Foundation audit event types: merchant.created, membership.revoked, application.submitted, application.reviewed, platform_role.granted, platform_role.revoked, account.status_changed, merchant.status_changed. Frozen C adds identity.recorded, identity.changed, contact.created, contact.changed, otp.requested, otp.changed, otp.verified and contact.proof_changed.

AuditLog's model-level metadata keys are exactly `from`, `to`, `role`, `previous_role`, `profile_version`, `reason_code`; scalar/null values only, maximum encoded metadata 2048 bytes, and application references require merchant context. This model allowlist is not a database-level permission to reinterpret events. C's audit table has a fixed CHECK enum and exact pinned SQL; r06_audit_no_update/delete enforce append-only history. R06 discharge requires its exact obligation audit tuple; SE's se1_audit_insert binds application.submitted to its exact evidence and draft contract. Neither is reusable for a profile name.

| Existing event family | Why unsuitable |
|---|---|
| merchant.created | Completion is not creation; would fabricate a second creation event. |
| merchant.status_changed / account.status_changed | Name/profile version is not an operational/verification/account status transition. Using from/to for names would additionally disclose private values. |
| application.submitted / application.reviewed | Completion changes neither application status; submission has strict evidence admission and review remains disabled. |
| contact/identity/OTP/proof events | Different protected records and verification lifecycles; business-name completion does not change them. |
| Membership/platform-role events | No authority grant/revocation occurs. |

**No approved, semantically correct existing event is available.** Raw SQL cannot make a new event pass C's CHECK. Editing the PHP enum alone is insufficient; rebuilding audit_logs or repinning C/SE would violate this task. A label such as profile_version inside existing metadata does not create event authority. Do not write business-name values, suffixes, unkeyed hashes or source payloads into audit metadata or generic logs.

### Smallest recommended separate amendment

Propose **one amendment-owned typed append-only completion record**, distinct from audit_logs. This is a proposed audit-contract extension for owner approval, not a newly invented approved event or implemented table. No final table/event/migration identifier or operational policy is selected here. It avoids changing audit_logs's frozen enum/SQL and does not require the unrelated DR-1 Chief decision amendment.

Minimum logical record: server ID/contract version; merchant and current draft identity/attempt; actor/membership and observed auth/email/authority epochs; prior/result profile versions with result=prior+1; scoped request UUID; canonical trusted event time; approved record policy reference; fixed empty-to-named operation semantics. No old/new business-name text, private identity/contact values, arbitrary JSON, raw request body, credentials or low-entropy hash.

Require UNIQUE merchant+actor+request UUID, one completion transition for the bound draft/profile step, exact same-tenant references, typed bounds, append-only/REPLACE denial, and insertion checks that the current merchant/draft move through the admitted empty-to-named pair. Actual completion must not commit without exactly its record; an orphan or duplicate record must not commit. No-op produces no record. Do not insert a fabricated creation/status/submission audit in parallel.

Keep the record's historical references stable. A lifetime FK requiring current merchant/draft profile=result forever would block future profile changes/submission; do **not** use it. The later exact design must prove creation-time merchant/draft/record atomicity with additive guards/ordering while permitting normal later lifecycle. This logical specification is not proof that those SQL constraints already exist. If a minimal exact design cannot guarantee both, stop for review rather than weaken an invariant.

Owner must approve that this dedicated redacted record satisfies the audit requirement, its retention/access/hold rules and exact atomicity catalogue. Do not implement an unaudited variant by default. No new encryption key or name commitment is selected: this minimal record supports only the conservative resolver in section 8. Durable replay after arbitrary later profile changes would require an additional expressly approved protected command-binding/history contract and is outside this proposal.

### Readiness/source-pin consequence

SE-1.0 admits exact objects/ledger and foundation definitions. **Any extra table/index/trigger/migration entry makes unchanged SE ready() deny the profile**, even if old SQL remains byte-identical. Completion amendment therefore requires its own source/artifact manifest, explicit composed-profile admission and deliberate existing-consumer integration outside pinned SE source. Verify all base pins/SQL plus all additions/exact ledger; no unknown-object exception, manifest repinning, bypass or legacy fallback.

Inventory direct ApplicationSubmissionService/LifecycleMutation readiness calls and their registration/email/revocation/reset consumers before a later rollout. Keep base-SE validation strict; partial amendment must fail closed. No working migration/activation command is supplied. If owner instead wants the new record integrated into audit_logs, that requires a separately reviewed superseding audit representation; it is not permission to edit frozen C now.

## 7. Exact proposed transaction protocol

1. Parse/allowlist input and perform ordinary request/auth/tenant checks. Treat all pre-writer data as provisional. Admit the exact approved composed schema, retention/time/restore state and idle managed SQLite connection; reject nested/non-SQLite or partial profiles.
2. Begin one managed transaction and reserve SQLite's writer using the established zero-row write before authoritative workflow reads. This requires the admitted onboarding-context table, not a working-schema probe/repair. Disable automatic reconnect/retry after operation begins.
3. Freshly reload and validate section 4 authority, exact expectations, name/current-draft/absence-of-submission state, reconciliation/high-water and operational admission. Do not acquire authority from a zero-row lock alone.
4. If the same write request UUID is already recorded, do not re-execute it or turn a stale command into a no-op. Route it to explicit outcome handling/conflict. A fresh, exact-version identical-name request is a genuine no-op and performs no updates/record insertion; acknowledge only after the transaction boundary completes.
5. For actual empty→named change, check V+1 safely. CAS merchant by exact tenant/current version and stored name `''` to the validated name, profile V+1 and approved server updated_at semantics. Require affected count 1.
6. CAS exact application ID/merchant/attempt/current_slot/draft/profile V/NULL submission fields to profile V+1, with any updated_at value derived server-side. Require affected count 1. The old SE guard remains enabled.
7. Insert exactly one canonical redacted completion record under the approved amendment protocol. Its final guards/constraints must bind this operation to both row changes and deny partial commit/direct bypass. Exact implementation may require an amendment-controlled insertion order rather than this logical listing; the later DDL must demonstrate it without changing frozen triggers. Never acknowledge the two CAS writes independently.
8. Reload merchant/draft/record and verify name/version/tenant/attempt agreement, unchanged submission fields and no extra effects. Recheck authority/epochs/time/restore/admission before the single COMMIT attempt.
9. A known precommit failure plus successful rollback yields no business change or success record. Any uncertain COMMIT acknowledgement or failed rollback discards the connection and returns unknown; never retry implicitly.

Reusing SubmissionTransaction's mechanism requires a deliberate reviewed existing-workflow integration and safe generic completion errors. Do not reuse its owner submission authorization/receipt/resolver as if completion were submission. VerificationStore and a generic `DB::transaction` callback alone do not establish the required uncertainty guarantee. No new orchestration service is authorized by this design; later implementation belongs within the bounded existing onboarding integration.

## 8. Unknown outcomes, no-op distinction and authoritative resolution

An unknown result is not confirmed rollback. Preserve the original request UUID, desired name and expected tuple in the caller's bounded explicit retry/resolution context, never ordinary logs or a hidden automatic POST loop. Purge/discard the old connection. The interface must say it could not confirm the result and offer explicit checking/reloading, not “Saved” or “Nothing changed.”

On a **fresh serialized connection**, first require current authorized owner/tenant access, exact composed schema, trusted time/retention and independently authoritative restore/replay admission. The existing denied restore/admission contracts are not a production resolver. Then:

| Observed state | Permitted resolution |
|---|---|
| Exactly one matching completion record for actor/tenant/UUID/expected prior and result tuple, and current same draft/merchant are still at result V+1 with the exact caller-supplied canonical name | Confirm this recorded completion and current result after full admission/consistency checks. No rewrite or extra record. |
| Record absent, exact original merchant remains `''` at V and exact original draft at V with no conflicting operation/record; authoritative current store proven | Confirm authoritative absence of this completion. Return absent, not an automatic retry; an explicit later command rechecks everything. |
| Recorded event exists but later profile version/draft/status/name changed, or original desired payload cannot be validated | May state a historical completion event exists, but do not claim the requested name/current outcome is proven. Hold payload-specific resolution for review; do not manufacture a historical name snapshot. |
| No record but nonempty/advanced/mismatched state, partial tuple, duplicate record, missing history/key/policy or restore admission unavailable | Unknown/conflict/held; never infer absence, adopt arbitrary state, repair history or replay blindly. |
| Original operation classified as no-op | No durable write record exists. Fresh matching-state validation can report current no change needed; it cannot invent a historical completion receipt. |

The minimal redacted record contains no name commitment. Therefore positive payload-specific resolution is deliberately restricted to the unchanged result version/name, rather than promising deterministic historical payload replay after arbitrary later mutations. Exact profile epochs guard change-away/change-back; matching text alone is insufficient. A reused UUID with changed expectations or mismatching result conflicts. A later explicit fresh current-version identical-name command is a no-op, not proof that the original uncertain command succeeded.

No stale-backup/local-row lookup can assert authoritative absence: an old restored store may show the original empty name after a newer store committed it. Restore/cutover fencing and authoritative admission remain prerequisites. Never resolve through the discarded connection or label bounded fault tests as deployment-grade storage recovery.

## 9. Existing-onboarding user experience

Inside the existing onboarding status page, show a single owner-only business-name input when the admitted current draft is unnamed. Preserve the XLipa premium light layout, existing navigation and dashboard access; no additional dashboard or unnecessary business/KYC fields. If operation admission is unavailable, explain unavailability without implying that an editable form can already save.

Suggested plain language for later copy review:

- Heading: “Add your business name”.
- Label: “Business name”. Helper: “Enter the name you use for your business. You need it before you can submit an application.”
- Button: “Save business name”.
- Success after confirmed commit/resolution: “Business name saved. Your application is still a draft.”
- Fresh no-op: “This business name is already saved.”
- Invalid input: “Enter a business name of up to 200 characters.”
- Stale state: “Your onboarding details have changed. Reload this page before trying again.”
- Uncertain result: “We could not confirm whether your business name was saved. Check the result before trying again.”

Do not imply registered-company status, identity verification, merchant approval or payment readiness. Do not request company documents, OTP or NIN here. Nonowner read-only visitors see status, not a write form. A named merchant sees its existing name and no rename editor. A direct exact-version identical-name request remains a safe no-op even though the normal UI has no rename form.

Use visible programmatic labels, text description, error association/summary, aria-live success/error status, keyboard focus management, native submit controls and comfortable mobile touch targets. Keep field max length as assistance, never sole validation. Prevent accidental duplicate clicks without claiming disabled-button state is idempotency. After confirmed write use POST/redirect/GET; preserve uncertain-state handling rather than blindly resubmitting. Escape values, keep password/identity/key data out of flash/logs, and avoid private name echo in generic error bodies. Hidden expectations must be rendered from fresh server reads; no extra visible fields.

Desktop/tablet/mobile and keyboard checks must verify readable feedback, no clipped name/control/label, usable focus and unchanged premium theme. No new language framework or unapproved claim of Kiswahili localization is introduced; approved localization can later translate the same concise meaning.

## 10. Fictional test acceptance matrix — not executed here

| Area | Required later tests |
|---|---|
| Empty→named | Actual registered unnamed fixture; exact owner/draft; merchant and draft V→V+1 once; one canonical completion record; same IDs/attempt/current slot; nonfinancial flags unchanged. |
| Genuine no-op | Current identical canonical name with matching versions; no version/updated_at/record/notification writes; stale identical request conflicts; no-op at max version performs no arithmetic. |
| Validation | Omitted/null/empty/whitespace/control/NUL/malformed type/invalid UTF-8/oversize; valid Unicode/punctuation/200-character boundary; unknown fields/privileged claims denied; escaped output. |
| Authorization | Guest, unverified/inactive/stale session, manager/member Staff and platform-only Chief/Staff denied; legitimate owner checked freshly; revoked membership/auth/email/authority epoch substitution denied. |
| Tenant/merchant | Foreign ID/membership/application nonenumerating denial; suspended/closed merchant; no application, wrong current slot/attempt, submitted/pending/reviewed states; no implicit graph repair. |
| Versions | Merchant and draft each stale, unequal current versions, wrong application ID/attempt, overflow/negative/coerced values; current route model missing profile_version must not bypass reload. |
| Concurrency | Two generated connections, same expected V with same/different names; one actual winner, loser stale, no duplicate record. Busy writer no automatic retry. |
| Atomicity | Fault after each CAS, record insertion, final check and before commit; fingerprints include merchant/draft/journal/audit/evidence/proof/context state; successful rollback leaves exact original graph. |
| Audit amendment | Current enum rejects unapproved event; no merchant.created/status misuse or private values; canonical typed completion record and exact request/tenant/version binding; orphan/duplicate/UPDATE/DELETE/REPLACE/direct partial-commit denials. |
| Lifecycle compatibility | New completion record does not pin future profile versions or draft status; later legitimate submission preserves historical record and frozen old application evidence. Nonempty different-name rename stays denied. |
| Unknown outcomes | Inject acknowledgement loss before and after real COMMIT and rollback failure; exactly one commit attempt, purged connection, explicit fresh resolver; authoritative presence/absence and later-change/restore-denial holds; no phantom success or second version increment. |
| Frozen/schema admission | Base C/SE hashes unchanged; exact new composed profile only; absent/partial/unknown objects or ledger rows fail closed, no legacy fallback. Migration fault tests preserve old history/ciphertext/ledger if separately authorized. |
| Submission/security preservation | Unnamed remains blocked; named completion alone still cannot enable public submission, identity/OTP, Chief decisions or payments. Profile-only change does not reset/revoke unrelated evidence/counters; old submitted evidence byte-identical. |
| Interface | Existing page only, owner form/nonowner read-only, label/focus/errors/PRG/uncertain messaging; 1440/820/390 px offline fictional exports, no overflow or network/provider effects. |

Use the existing environment-file-free bootstrap and TestCase; newly generated memory fixtures or explicitly owned temporary fixture files only. No working SQLite copy, sidecars, real dotenv or credentials. No unrestricted suite. Future failure/concurrency evidence is bounded, not production crash certification.

## 11. Remaining approvals and next safe implementation step

| Decision / dependency | Recommendation |
|---|---|
| Completion auditing | Approve the dedicated append-only redacted completion record and its atomicity/retention/access contract; no existing event is suitable. Do not implement unaudited completion by default. |
| Minimal amendment/readiness | Approve exact separately versioned object/manifest/ledger design and strict composed consumer admission outside pinned source. No dependency on implementing Chief decisions. |
| Conservative outcome resolution | Approve the limited unchanged-result resolver; a stronger historical payload replay guarantee would need a separate protected command-binding contract. No keys/commitments selected silently. |
| Operational time/restore/retention | Establish maintained authoritative admission, holds and lawful retention/access with qualified owners; no duration or legal basis invented. Existing denial remains. |
| Input/error/limiter and UX details | Review the strict command, UTF-8/name normalization, existing-controller integration, generic errors and exact limiter/copy in the later concrete diff. |

Product choices already approved are not reopened. Next step is **owner/security review of this contract and its audit conclusion**. After explicit approval, prepare the smallest exact audit/composed-readiness amendment and isolated constraint evidence; only then separately review completion POST/resolver/form integration and tests. No implementation, new service, migration preparation or automatic PD-05B/PD-06 work is authorized by delivery of this document.

## 12. Roadmap, verification status and preservation

| Milestone | Current status |
|---|---|
| PD-01/02/03, UI-01 and PD-04 submission contracts | Existing source and operational restrictions preserved. |
| PD-05A read-only Chief foundation/specifications | Preserved; decision writes and successor amendment remain separately gated. |
| Minimal registration | Implemented previously: optional name, atomic graph and unnamed dashboard; reported 400 tests / 3,503 assertions and 18 responsive combinations are historical evidence. |
| Business-name completion | Product approved; security/audit/implementation contract delivered here for owner review. No writer, route, form, resolver, amendment or test implemented. |
| Remaining approved-scope work | Resolve completion audit/admission dependencies and approve concrete isolated amendment/integration package; no general profile editor. |
| Operational readiness | NO-GO; identity/issuer trust, custody, legal/provider/retention, trusted time/restore/release and production recovery evidence remain independent blockers. |

This section updates roadmap status **inside this new document only**; existing roadmap/reports are not edited. No unsupported completion percentage. No new test result is claimed: no runtime, tests, browser, Artisan, formatter or migrations ran.

Only `XLIPA-BUSINESS-NAME-COMPLETION-SECURITY-CONTRACT-2026-11-12.md` is added. A bounded pre-write SHA-256 inventory covers 214 existing source/asset/script/test/config/schema/report/roadmap files, including the generated signup runtime copy and prior uncommitted work. Final comparison: PENDING_FINAL_CHECK. This is not a whole-workspace/database certificate.

No working SQLite/sidecar was opened/read/copied/hashed/modified. No real environment file, secret, operational key or credential was accessed. No code/test/schema/frozen artifact/existing report/roadmap change, working boot/migration, real OTP/identity/payment integration, commit, push or deployment occurred.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**
