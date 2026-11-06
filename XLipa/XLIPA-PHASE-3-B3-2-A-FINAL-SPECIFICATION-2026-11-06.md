# XLipa Website — B3.2-A Final Merchant Onboarding, KYC & Verification Specification

**Version: B3.2-A v2.0 — owner-decision amendment, 6 Novemba 2026, Africa/Nairobi.**

**Scope: PLANNING AND DOCUMENTATION ONLY.** “Final” hapa ina maana version hii ndiyo proposed implementation baseline ya owner review, si ruhusa ya code/schema execution, SMS, KYC collection au deployment. XLipa ni browser-based payment gateway website. Future production domain UNKNOWN; local `http://xlipa.test` haibadilishwi.

Labels: **VERIFIED** = actual source/schema/Git inspection; **OWNER-APPROVED** = product decision katika maelekezo mapya; **PROPOSED** = technical design inayohitaji review; **NOT VERIFIED** = hakuna supporting implementation/contract/legal confirmation; **REQUIRES APPROVAL** = explicit next execution/technical/operational gate.

## 1. Existing foundation and preservation evidence

**VERIFIED:** Git branch main, HEAD `7c3e4743384e0ca0acd7b029c944569c84304541`, B3.1 checkpoint ipo. Initial working tree ina untracked `XLIPA-PHASE-3-B3-2-PLANNING-REPORT-2026-11-06.md` pekee; historical report hiyo imeachwa bila kuandikwa upya. Hakuna Git write/commit/push katika task hii.

Sources reviewed: approved B3 plan, B3.1 report/source, B3.2 planning report, existing user/merchant/application/membership models, policies, current status controller/view model, auth routes/verification controller/RegisterRequest, middleware, enums, migrations na relevant test cases. B3.1 scoped merchant context, active account/auth_version, verified-email guards, role redaction na trusted-host foundation vimehifadhiwa.

READONLY actual schema inspection: users/merchants/memberships/applications/audit records **1/1/1/1/1**; platform role assignments **0**; integrity `ok`, FK violations `0`. Working SQLite SHA-256 kabla/baada ya inspection:

`214ae83a31f53e80d3068d1aeb1366008bb5accb2df212dd2c534ae4121b6d8f`.

`.env` full-file hash unchanged; APP_KEY haijabadilishwa/haijachapishwa. Actual users table haina phone/OTP fields; merchants ina declared business_phone/business_email na optional profile columns; applications haina NIDA/contact-proof/payment-method bindings. Hakuna KYC/OTP/payment-method module iliyopatikana katika project-owned source inventory.

Existing auth **VERIFIED by source**: User implements MustVerifyEmail, signed verification URL na framework EmailVerificationRequest, resend limiter, active/auth_version checks, verified gate kwa account/status. Historical latest executed regression ni **108 tests / 847 assertions**, frontend checks **31**; **hazijarun tena katika documentation-only task hii**. Hakuna future OTP/KYC/submission test inayodaiwa kupita.

## 2. Owner-approved decisions

**OWNER-APPROVED:**

1. Initial market ni Tanzania (`TZ`) pekee. Individuals, sole proprietors na registered businesses/companies wanaweza kuapply; unregistered individuals pia wanaweza kuapply. Hii si eligibility/activation guarantee kwa payment services.
2. Minimum application information ni **business name, NIDA number, phone number na email address**. Before Chief Admin submission: email ownership verified, phone ownership verified by OTP, supplied required fields valid, authenticated authorized merchant account.
3. Kwa individual, NIDA ni ya individual owner; kwa company, ni ya legally authorized representative. Account/membership owner si automatic proof ya legal representation. NIDA syntax si verified identity.
4. Comprehensive business profile iwe generally optional kwa initial submission; optional documents/payment methods zisiblock initial submission automatically. Confirmed legal/provider rules ziwe explicit, evidenced na scoped kwa phase/service.
5. NIDA ni sensitive identity data; dedicated KYC architecture, encrypted restricted storage na private document controls. No raw NIDA katika ordinary merchant profile/logs.
6. Chief Admin ndiye final internal application decision-maker. Internal approval, legal/provider eligibility, payment-method verification na financial activation ni gates tofauti.
7. Hakuna provider/API integration, uploads, real SMS/email, admin account/endpoints, code, migrations au deployment inayoruhusiwa na task hii.

Existing B2 account creation prerequisites—name, password/confirmation, terms na authentication security—hazijaondolewa. “Four minimum fields” zinahusu **merchant application submission**, si replacement ya secure account-registration architecture. Hakuna form/content change sasa.

## 3. Versioned reconciliation with historical plans

Version hii supersedes **policy assumptions zilizoorodheshwa hapa**, bila kufuta historical reports au kubadili code iliyokamilika:

| Historical location/assumption | v2.0 replacement |
|---|---|
| B3 plan B2 na B3.2 report E: legal_name/type/activity/address/representative/full-profile completeness kama general submit gate | Four-field minimum + verified contacts + fresh authorization. Missing optional profile fields si blanket blockers |
| B3.2 report C/L: unregistered individual application path blocked pending payment eligibility confirmation | Individuals may apply now as product policy; legal/provider checks remain before relevant approval/activation, no eligibility assertion |
| B3.2 report E: NIDA/TIN/documents excluded from any near-term application design | NIDA mandatory for initial application, in dedicated sensitive store; TIN/documents supported by future KYC module, generally optional at submission |
| B3.2 report G/H: broad completeness/authority-profile matrix universally required for submit | No broad completeness prerequisite. Minimal submission declaration/security/privacy controls are distinct from optional business fields and later authority evidence |
| B3 plan G1/G2 na previous report I: eight profile columns + audit extension are sufficient schema scope | Still optional profile candidates, **not sufficient anymore**: identity/contact-proof/OTP/KYC/immutable submission bindings need separately reviewed additive schema. No migration generation authorization |
| B3.1 current application-policy view = current owner/manager attempt only | Remains unchanged; future history/submission/reviewer privileges require explicit extensions |
| Earlier proposed admin/chief review matrix | Chief Admin final approve/reject authority; ordinary platform admin/staff does not inherit final-decision rights without separately approved delegation |
| Generic “verified/enabled” projection could be mistaken for payment readiness | Preserve existing enums/transactions, but document internal onboarding meaning. No payment capability grant from those fields alone |

**Preserved:** B3.1 tenancy/membership/policies, existing app states, immutable submitted evidence, audited atomic transitions, version/CAS checks, no self-review, and separate B3.3/B3.4 implementation approvals. Expanded identity/KYC/payment-method work is a new explicitly gated design dependency, not retroactively implemented by this amendment.

## 4. Minimum submission field contract

The following source bindings are **PROPOSED implementation choices**, not existing new functionality:

| Mandatory information | Proposed authoritative source and validation | Submission meaning |
|---|---|---|
| Business name | Existing merchants.business_name; nonempty, trim, ≤200, plain text; owner confirms | Display/trading name, not proof of registered entity |
| NIDA/NIN | Write-only sensitive POST input to dedicated merchant-scoped identity record; bounded canonical string, no integer conversion, no controls; preserve zeros | Declared identity number with syntax validation, **not identity verified** |
| Phone | Explicitly confirmed application contact in canonical international form, stored/bound through proposed contact-proof module | Exact current number must have valid OTP proof for this actor/merchant/purpose/version |
| Email | Default v2.0 recommendation: authenticated submitting owner's current users.email, with email_verified_at valid for that exact current address | Existing verified identity email supplies minimum contact; optional business_email is separate |

Changing application email to a different business contact is an **alternative requiring approval and independent ownership verification**; merely copying users.email_verified_at would be invalid. Optional business_email does not silently replace the verified submission contact. Likewise existing business_phone is a candidate input, not proof of ownership and not automatic verification.

NIN formatting: government e-GA dictionary describes a bounded numeric NIN element of maximum size 20; that is not a full NIDA validation contract. **PROPOSED product validator** for later approval: canonical 20 ASCII digits, accepting only explicitly supported display separators before normalization; reject arbitrary characters/length. Current official canonical-format/checksum rules remain **NOT VERIFIED**. Confirm the validator against current NIDA documentation before real collection/submission; never invent checksum, infer date of birth/citizenship, or expose a real NIN example. No mandatory online-NIDA lookup is added to the minimum submission gate. [e-GA institutional data dictionary](https://www.ega.go.tz/pdf-viewer?file=https%3A%2F%2Fwww.ega.go.tz%2Fuploads%2Fstandarddocuments%2Fsw-1640950814-Final+Institutional+Data+Dictionary+Technical+Standards+and+Guidelines-EDITED+%281%29.pdf).

### Holder/representation binding without a hidden broad-profile gate

Business type/legal name/representative-name fields remain optional. Do not infer individual/company or NIDA ownership from an absent business_type, account name or membership role. **PROPOSED:** a short submission declaration confirms that the number belongs to the individual owner or, for an entity, its authorized natural-person representative, and is supplied lawfully. Store actor, purpose, declaration version/time and claimed relationship separately from independent verification. This is a procedural confirmation, not a fifth broad profile-completeness requirement or proof of legal authority; exact wording/legal basis needs approval.

If entity classification is not yet supplied, keep it explicitly unclassified/declared within KYC review context—never silently map to individual. Chief Admin can request classification/representation evidence during review. Once a company is declared, identity evidence must concern its authorized representative; a company has no personal NIDA of its own. Unresolved identity/authority evidence blocks any phase/service whose confirmed rules require it, not every initial application by default.

Unsupported IDs, absent NIDA or a non-TZ market are not silently substituted with passport/other-country flows. Alternative identity products need explicit owner/legal/provider review. This does not imply Tanzanian citizenship or payment eligibility from a number/country code.

## 5. Revised readiness and eligibility rules

**OWNER-APPROVED outcomes; PROPOSED predicate implementation:** maintain independent computed results, not new application states:

- **minimum_submission_ready:** configured TZ application scope; authenticated active verified owner with fresh membership/auth_version; valid current draft/version; merchant not suspended/closed; four minimum values valid; exact current email verified; exact current phone OTP proof valid; identity input securely persisted with required lawful-processing controls; approved submission infrastructure available. Existing nonfinancial security/invariant checks remain.
- **optional_profile_completeness:** helpful missing optional fields only; no percentage/flag that silently blocks minimum submission.
- **kyc_ready_for_decision / kyc_ready_for_service:** purpose/type/jurisdiction/service-specific evidence requirements whose policy and authoritative basis are approved; never guessed “all documents required”.
- **chief_internal_approval:** independent authorized Chief Admin's recorded decision under B3.4.
- **payment_method_verified:** separate evidence for a particular version of a bank/mobile-money method.
- **service_activation_allowed:** separately authorized capability policy + internal approval + applicable law/provider/KYC conditions + operational/technical readiness + verified method where relevant. Default denied until implemented and authorized.

**Explicit non-blockers at initial submission:** missing optional address/legal name/description/website/registration certificate/TIN/business licence/bank account/mobile-money method. Supplied optional values still validate; unsupported or contradictory input is not accepted as valid data. A confirmed obligation may impose a narrow condition only with rule ID, authoritative source, affected type/jurisdiction, phase, version/effective date and user-facing reason; no vague “compliance incomplete” universal blocker.

**Fail-closed:** no supported OTP implementation/provider configuration, unresolved required verifier failure, unavailable sensitive-data/key service, invalid/missing phone proof, schema/policy mismatch or current-version conflict means no submission transaction. Do not fake phone_verified, create pending_review anyway, or require optional documents as a substitute. NIDA verification API absence alone is not a new minimum gate; missing secure storage/approved syntax handling is a technical/privacy blocker.

## 6. Email verification specification

**VERIFIED existing implementation:** native user email verification, signed user-bound links, resend throttling and verified account access; verification does not approve merchant. Preserve all existing CSRF, sessions, signatures, reset, account-status/auth_version and rate limits.

**PROPOSED future submission checks:** read fresh owner identity email and its verification binding. Freeze contact reference/version evidence at submission, not just a stale session boolean. A future email-change action must clear verification for the changed address, revoke outstanding email/contact-dependent readiness and apply session/auth_version policy; no such action is introduced here. Old historical submitted evidence stays immutable; current review/activation checks detect stale contacts and require fresh verification/versioned evidence.

Local mail log/array transport remains development-only. Do not send real mail, expose signed/reset URLs, or claim end-to-end delivery. If separate business-contact-email verification is later approved, scope token/email/version/purpose, preserve generic responses and implement it separately from users.email verification.

## 7. Phone OTP architecture

**NOT VERIFIED/NOT IMPLEMENTED:** no SMS provider, OTP challenge storage, phone ownership proof or delivery contract exists in XLipa source. Phone syntax normalization currently does not prove ownership.

**PROPOSED v1 parameters** (technical choices, not legal/provider mandates):

| Control | Proposed value/behavior |
|---|---|
| Code | CSPRNG-generated 6 decimal digits, preserve leading zeros; no predictable counters/user data |
| Lifetime | 5 minutes from creation; queue delay never silently extends expiry |
| Guess limit | 5 failed attempts per challenge; additionally 10 failures per actor+merchant+target in 30 minutes |
| Resend | ≥60 seconds between sends; at most 3/15 minutes and 5/hour per actor/merchant/target; target-wide and IP/global quotas/budgets also apply |
| Request limits | Proposed IP 20 send requests/hour; configurable service-wide spend ceiling/circuit breaker; tune through approved test/pilot evidence |
| Reuse/replay | Single successful consumption; one active challenge per actor/merchant/contact-version/purpose; new challenge revokes previous, does not reset aggregate failed-attempt counters |
| OTP persistence | Keyed HMAC/digest bound to challenge ID, merchant, actor, normalized target/version and purpose; separate secret key/version, constant-time comparison; no plaintext code storage/logging |
| Target privacy | Encrypted phone/contact storage; keyed target fingerprints for quotas/search; no raw phone in cache keys/URLs/errors |

NIST discusses random minimum-six-digit secrets, replay protection, failed-attempt throttling and risks of PSTN verification; the settings above are XLipa design proposals. SMS possession is vulnerable to SIM swap/recycling/interception and is not legal identity, payment-account ownership or strong privileged MFA proof. No NIST assurance/compliance claim. [NIST SP 800-63B-4](https://pages.nist.gov/800-63-4/sp800-63b.html).

### Challenge and delivery protocol

1. Verified authenticated owner chooses/confirms the exact normalized application phone; server checks fresh tenant membership, purpose, country policy, cooldowns and quotas. No arbitrary recipient list or user-supplied SMS body.
2. Create a bounded send intent with ULID/idempotency reference; generate OTP inside the authorized sending worker close to delivery. Persist only bound digest/expiry/state, not a plaintext OTP in an ordinary queue payload. If any encrypted temporary delivery payload is necessary, use separately protected private queue/storage and short retention, approved before implementation.
3. Send through a modular approved adapter outside DB-held locks. No real adapter/endpoints/headers chosen here. A fake adapter is testing-only, and its results must never become production-eligible phone proofs or bypass live submission readiness.
4. Provider acceptance can make a challenge eligible for entry; it **does not verify ownership**. Provider outage/rejection/ambiguous timeout returns generic temporary failure, creates no proof and consumes appropriate anti-abuse quota. Avoid blind resend/retry on ambiguous delivery without the actual provider's idempotency contract. Delayed/duplicate jobs and messages cannot revive a revoked/expired challenge.
5. Verify code using server time, fresh actor/tenant/contact version, purpose, challenge state, expiry and persistent failure counters. Atomically consume challenge and write provenance-bound proof + redacted success audit; only one concurrent verification can win. A received code for another merchant/user/purpose/old phone fails.
6. Errors/status responses are generic, masked, no-store and rate-limited; no OTP/digest/full target/provider response leak. Do not reset user quotas/sessions to make tests pass. Resend-induced failure limits must not allow permanent lockout; approved cooldown/support recovery, not forced verification, is the recovery path.

### Phone changes and invalidation

An owner-controlled phone-change operation requires recent reauthentication, fresh permission and exact new target confirmation. It creates a new contact version, revokes old current verification/challenges and blocks readiness until the new phone passes OTP. Profile changes to unrelated optional fields do not unnecessarily invalidate phone proof. Replacing optional business_phone must not silently replace a verified submission phone.

Proof includes actor, merchant, target/version fingerprint, challenge, verification time, channel/provenance and policy version. Server-set verification data is never mass-assignable. Contact/identity changes during pending review invalidate **current** decision/activation eligibility; old frozen evidence is retained and a new review/attempt follows authorized lifecycle. No global “phone verified” boolean transferable across merchants/accounts or settlement methods. Proof expiry/reverification policy and service-outage gating require approval; initial recommendation treats proof as valid only for unchanged bound contact and applies an operational fail-closed submission gate until verifier readiness is established.

Audit candidates: verification_requested, verification_failed/expired, phone_verified, contact_changed, proof_revoked; use bounded codes/IDs/count buckets, no phone/code/digest. This requires a reviewed audit-event schema extension, not silent writes into today's CHECK constraint.

## 8. Optional comprehensive profile

**OWNER-APPROVED optional-at-submission direction:** business type, legal/trading names beyond minimum name, description/activity/operating model/sales channels, address, website, registration identifiers, business contacts, representative name/capacity and other justified operating information.

**PROPOSED storage/validation:** reuse existing bounded nullable merchant columns; retain prior candidate new legal_name(200), business_activity(1000), website_url(255), representative_name(200), representative_capacity(100) as optional additions after schema approval. Existing business_name stays minimum/nonempty. Country-aware address/registration rules validate supplied values; no guessed government checksum/region hierarchy; website HTTPS/no userinfo/controls, never fetched. Business_type can remain unspecified; accepted supplied families map to owner-approved applicant types without asserting legal identity.

Owner/manager may prepare permitted optional fields on current draft; staff remains B3.1 safe-summary only. Server identity/contact verification flags, sensitive KYC, role, actor/status/version/snapshot/audit fields are prohibited in general PATCH payload. Missing key preserves value; null clears only allowed optional draft fields. No public publication of private contacts/address/representative data. No more sensitive operational information unless its purpose/permissions are separately approved.

The prior three attestation metadata columns are **not a universal submission completeness gate**. A purpose-specific secure declaration/identity-evidence binding is now preferable to treating broad profile attestation as independent legal proof. Retaining/replacing those proposed columns must be resolved in the revised schema review; the old eight-column list is not automatically authorized.

## 9. NIDA sensitive identity-data specification

**OWNER-APPROVED:** raw NIDA must not enter ordinary merchant columns, application free text, audit/log messages or public/normal frontend responses. **PROPOSED dedicated identity records**, merchant-scoped and versioned:

- Record ID/merchant FK; declared subject/relationship claim (unknown is explicit, not inferred); encrypted canonical NIN; optional separately protected keyed duplicate-detection fingerprint; syntax-policy version; submitted-by actor; lawful-processing notice/declaration version/time; provenance/review metadata; immutable version/supersession/retention references.
- Use vetted authenticated encryption with integrity protection, ideally envelope encryption/managed key separation. Bind ciphertext use to record/merchant/purpose/version through supported cryptographic context or verified application binding. No custom crypto, reversible encoding masquerading as encryption, or plain/unsalted hash of NIN.
- Separate encryption keys from DB/backups; key ID/version in envelope, restricted decrypt service access, audited use and rotation procedure. If keyed blind indexing is justified, use a separate key/domain from encryption and OTP. Equality fingerprints leak correlation and are private; no automatic global-unique NIN constraint—one lawful person may represent several merchants.
- Ordinary UI shows no number or a masked hint (at most last four), never stored-number autofill. Initial user entry is write-only over TLS, with no analytics/session recording/third-party scripts on sensitive input; redact request capture, exceptions, traces and error old-input. Existing dontFlash list is insufficient for future NIN/OTP/payment fields and must be extended in separately authorized implementation.
- Reviewers normally see masked identifier, provenance and outcomes. Full-number reveal/export is **not approved** by this design; any demonstrated need requires separate purpose-specific authorization/control review. Future authorized NIDA verification adapter can decrypt server-side only after legal/integration approval; no invented API/credentials or production calls.
- Keep syntax_valid, declared ownership/representation, independent identity match and authority evidence as separate KYC facts. Chief approval, email OTP or a matching NIN fingerprint never sets identity_verified automatically.

Official NIDA portal advertises stakeholder verification services; that establishes neither XLipa access rights nor an API contract. Stakeholder eligibility, exact integration/protocol/consent/data matching rules remain **NOT VERIFIED**. [NIDA official services](https://services.nida.go.tz/).

Privacy/key safeguards must be approved **before collecting real NINs**, even if the field is a product minimum. Legal basis/notice/data-subject handling/retention and actual secure storage are separate prerequisites; owner approval of a requirement is not a legal processing opinion. PDPC publishes minimization, storage limitation, security and cross-border principles; exact XLipa obligations require qualified review. [PDPC notice](https://pdpc.go.tz/privacy-notice/).

## 10. Dedicated KYC and legal evidence module

**PROPOSED model:** case/context per merchant and versioned identity/evidence records, associated with exact application/version when submitted. Accommodate NIDA identity information, TIN/tax metadata, business licence, BRELA certificate/registry details, representative authority evidence and other justified categories without making each universally required.

| Evidence | Initial submission default | Later handling |
|---|---|---|
| NIDA number | Required, secure syntax-valid declaration | Independent identity verification/provenance separate; no implicit document-photo requirement |
| TIN/tax identifiers | Optional | Required only by evidenced type/jurisdiction/provider/service rule; sensitive metadata store, no guessed global format |
| Business licence | Optional | Reviewer may request; relevant issuer, validity/activity/territory checked under confirmed rules |
| BRELA/registry certificate/details | Optional | Correct registry/jurisdiction and legal form; company vs business name not conflated |
| Representative authority evidence | Optional at minimum initial submission unless confirmed narrow obligation | For company identity/authority, Chief requests what is justified; membership alone is insufficient |
| Other evidence | Not enabled by vague “other” field | Approved category/purpose/access/retention/scanner rule before collection |

Different entity structures can have different requirements; absence of optional evidence does not auto-block submission. Rule records/config identify requirement phase (initial submission, internal decision or service activation), scope, authoritative evidence and policy version. No real requirement is invented here. Additional-information requests are bounded and explain what/why through safe public feedback; internal risk notes stay separate.

### Private documents — design only

- Dedicated nonpublic disk/bucket, quarantine then clean area, random object keys, encrypted at rest, no public symlink/static URL. Scoped ULID references; no original filenames as storage paths or NINs embedded in filenames/URLs.
- Proposed allowlist PDF/JPEG/PNG, ≤10 MiB/file and ≤10 files/attempt plus account/global quotas; exact limits require review. Validate server MIME, signature, extension, dimensions/pages/resource limits; reject executable/archive/SVG/remote URL fetch. No uploads exist now.
- Scan/sanitize through approved isolated services; fail closed for pending/error/timeout. Do not send documents to public scanning services. Download only verified clean exact-checksum object; missing scan infrastructure disables upload/review acceptance, not optional-document-free minimum submission.
- Authenticated private streaming rechecks user/tenant/case/application association and purpose each time, attachment disposition, nosniff/no-store, access audit without contents/secret URL. Owners may manage their draft evidence; managers only specifically approved nonsensitive categories; staff no access. Chief evidence access requires separate privileged authorization, reauth/MFA readiness and conflict checks.
- Submitted evidence references/versions become immutable; corrections are new objects/versions with history, not overwritten bytes. Object store and DB are not one transaction: quarantine/orphan reconciliation, deletion/holds and retry manifests are required.
- Retention is category/purpose/legal-policy based; approve durations and holds before collection. Cover drafts, rejected cases, audit, queue residues and backup expiry, rights requests and controlled deletion. No invented statutory retention years or auto-delete-on-rejection behavior.

## 11. Optional bank and mobile-money methods

**OWNER-APPROVED future dashboard capability; no implementation now.** Optional during onboarding; never feeds minimum-submission completeness.

| Method | Required metadata when the user voluntarily adds that method | Verification semantics |
|---|---|---|
| Mobile money | Approved provider identifier/display name, account phone, account holder name, server verification metadata | Phone OTP proves possession of selected contact only; not wallet-account holder match/provider support/funds eligibility |
| Bank | Approved bank identifier/name, account number as string preserving zeros, account holder name, server verification metadata | Format is not ownership proof; exact bank/country/provider rules and verification workflow require separate authorization |

Proposed merchant-scoped versioned payment-method records: ULID, kind, approved provider/bank reference, encrypted account details/holder name, masked hint, current verification state/provenance/time, creator/changes, policy/key version. No secret API credentials in merchant methods; no balances/ledger/payment transaction creation.

New/changed details are unverified; verification applies to exact version and cannot be copied from old method or from onboarding approval. Do not impose blind global account uniqueness—lawful shared/entity arrangements need policy. Default UI access owner-only masked management, fresh reauth and strong controls; manager/staff permissions require future explicit capability approval, not inherited broad profile-edit rights. Chief review of a method does not authorize executing transfers.

Only a provider-supported, separately authorized workflow may set method verified. No bank account-name lookup, micro-deposit, payment callback, Selcom request or mobile-money provider list is assumed. Payment collection, withdrawal and settlement remain disabled until separate financial architecture/capability authorization.

## 12. Role-based permissions

**PROPOSED implementation matrix aligned to owner decisions:**

| Action | Merchant owner | Merchant manager | Merchant staff | Chief Admin | Other platform admin/staff |
|---|---|---|---|---|---|
| Existing B3.1 status | Own allowed summary/details | Own allowed summary/details | Own limited summary | No tenant bypass | No tenant bypass |
| Optional draft profile editing | Yes within policy | Yes within policy | No | Not through merchant route | No |
| NIDA submission/change, phone proof/change, application submission | Owner-only proposed | No by default | No | No impersonation | No |
| Draft KYC files | Own allowed categories | Only separately approved categories; no sensitive identity by default | No | Review path only | Separate explicit future delegation only |
| Submitted cases/authorized masked KYC review | Own permitted status/public feedback | Existing safe view rules | Limited status | Yes through future privileged review policy | No automatic right |
| Request information / approve / reject | No | No | No | Final internal decision-maker | No final decision without owner-approved delegation |
| Payment-method management | Future owner-only scoped permission | Not automatic | No | Review-only if separately approved | No |
| Service activation | No self-service override | No | No | Internal approval alone insufficient | Separate operational/capability authority only |

All privileges require fresh persisted active/verified identity, correct auth_version and appropriate resource context. Chief review needs active role assignment, recent password confirmation, MFA/HTTPS readiness before nonlocal privileged use, no self-review/conflict of interest and safe reassignment if recused. No Chief account, Gate::before superuser bypass or new staff roles are created now. If no independent authorized reviewer exists, decisions remain blocked; do not bootstrap an admin as a side effect.

## 13. Existing application lifecycle and Chief Admin review

Preserve `draft → pending_review → approved | rejected | information_requested`. Submitted/resubmitted remain events with timestamps/attempts, not new persisted states. More-info response uses a new current draft/attempt under later B3.3 rules; rejection reapplication policy remains separately approved. Keep historical snapshots immutable and at most one current_slot per merchant, plus action-level at-least-one-current invariant.

### Future B3.3 submission

Fresh owner/contact/identity/policy readiness → transaction/CAS on expected current draft + profile/contact/identity versions → freeze **allowlisted optional profile snapshot and protected evidence references**, no raw NIN/OTP in snapshot → pending_review, submitted actor/time, audit → after-commit local/future approved notifications. Repeated identical submission returns safe authorized current outcome without duplicate application/audit; stale/conflicting input gets 409 and no partial writes. A browser “ready” flag is never authoritative.

### Future B3.4 review

Chief-only final decision policy, independent reviewer/conflict check, current submitted evidence/policy version, required decision-phase evidence, bounded public reason codes/text, distinct encrypted internal notes, and expected-version CAS. Request additional information uses existing information_requested and public feedback, never reveals internal risk material. Exactly one competing decision wins; audit failure rolls back decision/projections. No hidden automatic decisions or generic application-status PATCH.

Approval preserves existing approved/verified projection semantics only as **internal onboarding approval**, not a declaration that every KYC fact is independently verified. Existing onboarding→enabled transition, if retained, enables nonfinancial access only and must not revive suspended/closed merchants. Future UI must clearly distinguish internal approval, identity/KYC outcomes and payment capability; current website wording is unchanged by this report. A later explicit service policy verifies legal/provider evidence before financial activation, regardless of the enum label.

Changing contacts/identity/evidence while pending must not rewrite submitted material: create a proposed replacement version, revoke current readiness where relevant and require an authorized new-attempt/information-request path. Optional profile changes during review remain controlled/read-only under parent lifecycle. Approved material changes follow later audited re-review; none may bypass suspension or pending KYC/activation requirements.

## 14. Revised schema implications — proposals, no migrations

The previous two-migration/eight-column proposal is **superseded as the complete implementation package**. Optional profile additions can still be reviewed independently; sensitive identity/contact/KYC/method data must not be folded into ordinary merchants columns merely to minimize table count.

| Proposed structure | Responsibility / essential constraints |
|---|---|
| Existing merchants + optional profile additions | Reuse bounded draft profile/version/state; no raw NIN/OTP/bank fields; optional columns remain nullable, no guessed backfill |
| Merchant identity record versions | Merchant/actor references, declared subject binding, encrypted NIN, optional private keyed duplicate fingerprint, validation/provenance/declaration/key/policy metadata; no global unique-NIN constraint |
| Application contact/proof records | Exact merchant+actor+contact purpose/version; encrypted canonical phone, optional email-binding fingerprint/reference, verified_at/proof channel/provenance/revocation; no global users.phone_verified shortcut |
| OTP challenges and aggregate abuse state | Opaque IDs, target fingerprint, actor/merchant/purpose/contact version, keyed digest/key ID, expiry/failures/send/consume/revoke state; per-challenge and cross-resend limits, TTL cleanup |
| Versioned KYC case/evidence metadata | Merchant/application/identity association, category, object key/checksum/scan/verification/provenance/retention/hold fields; enforce composite tenant relations |
| Immutable submission evidence bindings | Exact identity/contact/document versions + declaration/policy references tied to application; no raw NIN snapshot copy; FK/immutability decisions reviewed with lifecycle |
| Optional payment-method versions | Separate encrypted bank/mobile-money identifiers/holder name, scoped verification/provenance; no financial transaction/settlement activation fields masquerading as profile readiness |
| Audit extension | Bounded contact/identity/KYC/OTP/submission/review/method event types; PHP enum and SQLite CHECK revised together with row/FK/index preservation |

Final exact table/column names, key types, indexes, uniqueness/current-pointer design and migration grouping require a concrete approved schema review. Preserve bigint user IDs, merchant/application ULIDs and composite tenant association. Avoid one polymorphic attachment table with unenforced tenant IDs. Keep independent module states separate from the five application states; no new application state is authorized here.

Suggested indexes: merchant/version and application/evidence lookups, challenge expiry/current state, hashed target/purpose quota lookup, audit merchant/time. Secrets/ciphertext/blind indexes are never exposed for generic search. No private identity/name full-text indexes or auto-merging duplicates. Record-version changes require consistent parent serialization; existing SQLite constraints/positive version triggers must survive additive migration or rebuild.

Existing rows remain intact. New identity/proof data initially absent; **never migrate existing phone/email/name into verified NIDA/OTP evidence**. Existing draft remains draft and becomes not-ready-for-future-submit until new requirements are met. Historical verified identity email stays valid under unchanged B2 rules. Missing key/schema/provider support fails closed in future submit routes without breaking current read-only B3.1.

## 15. Security and privacy acceptance specification

All tests below are **PROPOSED future tests**, not written/executed now:

- Cross-tenant root/nested identity/contact/document/method IDs; fresh roles/revocation/auth_version, forged browser state, missing/disabled country/purpose, no administrator-to-merchant bypass.
- Four-field minimum positive path with other profile/documents/methods absent; negative cases for invalid minimum values/unverified exact contacts, without broad optional completeness gates.
- NIN never appears in merchant/profile JSON, logs, errors, traces, old input, audit, filenames, URLs or generic snapshots; tampered ciphertext/key-unavailable paths fail safely; masked reads role-tested; independent validation never inferred from syntax.
- OTP expiry/time boundary, attempt/resend/aggregate limits, new-code no reset of failures, cross-actor/merchant/purpose/version binding, wrong/old/replayed codes, simultaneous verification, delayed/duplicate/ambiguous delivery, provider disabled/failure, no real network tests.
- Number/email changes revoke current proof/readiness appropriately; old immutable submitted facts preserved. Optional business-contact edits do not silently alter authoritative verified contact.
- Documents: MIME/extension/signature/resource checks, quarantine/scanner timeout/error, checksum binding, unauthorized stream/reveal, other-tenant denial, object/DB failure/orphan reconciliation, retention holds/deletion audit.
- Submission CAS/idempotency/current-slot invariant, simultaneous submit/contact-change/revoke/review, audit failure rollback, after-commit notification semantics, no duplicate success event or automatic merchant approval.
- Chief role/recency/MFA readiness/no self-review, bounded escaped reasons, encrypted internal-note redaction, concurrent decision conflict, suspended/closed merchant not re-enabled.
- Payment method masked access/version revocation, no phone-OTP→wallet/bank ownership shortcut, and all collection/withdrawal/settlement capabilities remain denied by default.
- Existing auth/host/B3.1/public frontend regressions; isolated migration up/down/reapply and preservation of data/FKs/indexes/triggers; no working-database fixtures.

Privacy operations before collection: approved lawful basis/notice for identity and representative data, data-subject access/correction/retention/deletion/holds, recipient/processor review, transfer/storage location, independent encrypted backups and recoverable keys. Backups and keys must have separately controlled access; rehearse restoration to a new isolated target and decrypt synthetic evidence without exposing values. Losing keys makes encrypted evidence unreadable; replacing APP_KEY is not a recovery strategy. Do not promise tamper-proof audit or regulatory certification from model append-only guards.

## 16. Implementation stages and approval gates

No stage begins automatically; these extend rather than silently replace the authoritative B3 roadmap:

| Stage | Scope / dependencies | Gate and exit evidence |
|---|---|---|
| **1 — B3.2-A v2.0 specification** | This document records owner product decisions; approve exact contact/subject/declaration/validation policies and technical parameters | Owner reviews v2.0 and decisions in section 17; no code/data change |
| **2 — Secure identity + phone-verification architecture review** | Exact threat model, privacy/legal controls, crypto/key plan, OTP/provider adapter and failure behavior; approved NIN syntax policy | Separate technical/privacy approval; no NIDA/SMS integration authorization implied; no real collection yet |
| **3 — B3.2-B expanded additive schema preparation** | Concrete profile/identity/contact/proof/OTP/evidence-binding/audit schema subset, migrations and isolated synthetic tests; optional document/method schemas can be deferred to their own gates | Explicit code/schema-preparation approval; rehearse preservation/up/down/failure/key recovery; working DB untouched |
| **4 — B3.2-C working-schema activation** | Named target, exact reviewed migration list, fresh preflight, no active writes, consistent private SQLite backup API + restore rehearsal | Separate explicit execution approval; preserve current records/key/schema constraints; no seed/reset/import |
| **5 — B3.2-D merchant profile + secure minimum-data/contact capture** | Approved schema first; scoped optional draft fields, secure identity input and contact-proof UX/logic; current auth/design retained | Explicit implementation scope, isolated fakes/tests; real SMS/email/provider adapter activation needs separate approval; no submission bypass when unavailable |
| **6 — Dedicated KYC document functionality** | Private storage, scanner, streaming, category/retention/access/privacy prerequisites | Separate implementation/collection approval. Optional documents may remain unavailable without blocking document-free minimum submission once other gates work |
| **7 — B3.3 application submission/history** | Minimum four fields + exact contact proofs + secure identity persistence + approved operational readiness; versioned evidence links | Separate B3.3 approval; positive optional-fields-absent tests, fail-closed provider/key cases, immutable/idempotent transactions |
| **8 — B3.4 Chief Admin review** | Restricted reviewers/reauth/MFA readiness/conflict controls, evidence policy and existing state transitions | Separate B3.4 approval; no admin provisioning side effect. Document requests only for actually available approved categories |
| **9 — Optional payment-method management** | Independently approved data model, ownership verification contract and owner permissions | Separate scope/financial-security approval; no transaction/settlement routing activation |
| **10 — Future payment-service activation** | Official Selcom/provider contracts, applicable legal/KYC requirements, real verified methods as needed, financial/ledger/reconciliation/risk/operations architecture | Explicit launch/capability authorization, provider sandbox/operational validation, deployment approval; default no financial capability |

Full B3.5 acceptance/hardening remains its later roadmap milestone; scoped tests in each stage do not claim it complete. Optional documents/methods are not new blanket prerequisites for B3.3: mandatory dependencies are secure minimum identity/contact handling, actual contact proofs and required evidenced obligations. If implementation sequence cannot deliver safe submission without an unavailable mandatory service, submission stays disabled rather than broadening optional fields into substitutes.

## 17. Remaining questions and exact approvals needed

### Legal/provider matters — NOT VERIFIED

1. Exact current NIN format/checksum and authorized validation/identity-matching process; stakeholder access/consent/provenance/data-sharing/retention requirements. No official NIDA API specification supplied.
2. Applicability of laws and issuer/registry rules across Tanzanian jurisdictions, legal forms and unregistered applicants; precise obligations at collection, submission, internal decision and particular service activation.
3. Identity/representative/TIN/licence/registry/UBO/document requirements for each Selcom/provider service; supported individuals/entities, channels/currencies/settlement verification and callback contract. No inferred support.
4. Privacy lawful basis, notice/consent where required, sensitive-data controls, controller/processor obligations, retention/holds/transfer/storage and external KYC/SMS vendors; obtain qualified review before actual collection.

### Owner/technical approvals — REQUIRES APPROVAL

- Ratify this amendment as the policy baseline; old broad optional-profile minimums stay superseded.
- Approve authoritative submission email binding (recommended current submitting owner users.email), separate phone-contact scope and per-merchant/actor proof reuse policy.
- Approve subject/declaration workflow that preserves optional type/profile fields while requiring truthful NIDA ownership/representation; exact privacy/declaration wording and any later full-number access exception.
- Confirm NIN canonical syntax after authoritative review; approve OTP proposed TTL/attempt/resend/budget/cooldown/proof-lifetime/outage policy and testing-only provenance boundaries.
- Approve dedicated identity/contact/OTP/KYC schema and key/storage/backup architecture before generating migrations; decide which optional eight-profile/attestation fields remain useful under the amended minimum.
- Approve Chief-only final decision policy, future support/delegation boundaries and no-self-review/MFA/reauth operational gates; no account creation authorized by policy approval.
- Separately authorize SMS provider selection/contracts/integration and actual sends; separately authorize NIDA integration if ever chosen. Specification approval authorizes neither.
- Approve document categories/limits/scanner/storage/retention and collection stage; separately approve payment-method verification/permissions; financial activation remains independent.
- Approve each concrete implementation diff/migration preparation, working-schema execution and subsequent B3.3/B3.4 scope at its own gate. No blanket permission from this document.

## 18. GO / NO-GO and stop

**GO:** owner-approved product decisions are reconciled into versioned B3.2-A v2.0 specification for review. B3.1 foundation remains intact.

**NO-GO:** implementation, real NIDA collection, OTP/SMS/email provider operations, KYC uploads, Chief endpoints, working migrations or payment activation during this task. Current source lacks required phone-proof/secure identity/submission infrastructure; it must not be described as ready to submit live applications under the new requirements.

Only new artifact is this specification. Existing B3.2 planning report and historical documents remain unchanged; no code/migration/frontend/config/Git-history/database mutation, dependency addition, real SMS/email, provider integration, commit/push or deployment.

**STOP. Next owner action: review v2.0 and authorize the specific architecture/schema-preparation stage only after its listed decisions are resolved.**
