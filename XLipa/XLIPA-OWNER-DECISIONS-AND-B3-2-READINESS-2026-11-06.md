# XLipa — Owner Decisions and B3.2 Security Readiness

**Decision record OD-1.0 — 6 November 2026, Africa/Nairobi. DOCUMENTATION ONLY.**

This records the owner's explicit product approval of **D13-1**, reviews unresolved security decisions and proposes a bounded future B3.2-B scope. It grants no code, migration, working-schema, data collection, messaging, integration, UI or deployment permission.

Labels: **VERIFIED** = evidence inspected this task; **OWNER-APPROVED** = explicit product decision, not legal clearance or execution permission; **PROPOSED** = technical recommendation pending decision/evidence; **NOT VERIFIED** = required evidence unavailable; **REQUIRES APPROVAL** = separate owner/technical/legal/operational gate.

## 1. Existing project evidence and preserved requirements

**VERIFIED:** canonical project `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`; Laravel `backend/`; authoritative public frontend `frontend/`; Git root parent `WEB-DEVELOPMENT`. Branch main, HEAD `7c3e4743384e0ca0acd7b029c944569c84304541` matches the historical B3.1 checkpoint. Initial status shows seven pre-existing untracked documentation files: B3.2 planning, B3.2-A v2.0, secure architecture review and R1.0–R1.3. No tracked modifications were reported. These are pre-existing work, not new additions in this task.

Reviewed sources: [B3.2-A v2.0](XLIPA-PHASE-3-B3-2-A-FINAL-SPECIFICATION-2026-11-06.md), [Secure Architecture Review SA-1.0](XLIPA-B3-2-SECURE-ARCHITECTURE-REVIEW-2026-11-06.md), [R1.0 Payments & Analytics](XLIPA-MASTER-ROADMAP-ADDENDUM-PAYMENTS-ANALYTICS-2026-11-06.md), [R1.1 QR/Buttons/Operations](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-1-2026-11-06.md), [R1.2 Notifications/Documents](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-2-2026-11-06.md) and [R1.3 Dashboards/Settings](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-3-2026-11-06.md). Their requirements are preserved except the explicitly identified product-policy amendment below; no source document is edited.

Existing reports describe completed local B3.1 scoped read-only merchant status, B2 native session/email verification, active/auth_version checks and separate platform assignments. They do not establish live OTP, secure NIN capture, financial services, messaging or premium dashboards. Historical suite evidence is 108 tests / 847 assertions and 31 frontend checks; not rerun here. No fresh runtime/schema integrity or legal/provider verification is claimed. This task uses file/Git reads and hashes only.

Preserve: TZ application market; individuals/sole proprietors/registered businesses/companies and unregistered individuals may apply, without a guarantee of payment-service eligibility. Minimum application data remain business name, NIDA, phone and email with exact contact ownership proof and fresh authorized owner/security checks. Optional broad profile/documents/payment methods do not become blanket submission gates. Individual NIN concerns the individual owner; company NIN concerns an authorized natural-person representative. Membership owner is not proof of legal authority. Internal Chief approval, independent KYC facts and service activation remain separate. Existing application states, immutable submissions, tenant policies and B3.1–B3.5/F0–F5 milestones remain unchanged. Future production domain is unknown; local `http://xlipa.test` unchanged.

## 2. Approved PDF and communication policy — D13-1 decision

**OWNER-APPROVED in the current instruction:** D13-1 is now approved at the **product-policy layer**. The pending-product-approval description in historical R1.3 is resolved by this dated decision record; it remains historical text and is not rewritten. Legal/contractual applicability and technical execution remain independent gates.

| Event/purpose | Approved product behavior | Preserved condition |
|---|---|---|
| Customer payment receipt/PDF | On authorized demand where applicable | Transaction/financial record exists independently; legal obligations reviewed |
| Pending/failed customer payment | No routine PDF | Do not delete state/event history |
| Withdrawal request/advice | **On demand**, replacing R1.2 automatic-generation product assumption | Advice is never proof of payment; private authorized access |
| Confirmed withdrawal payout | Automatically issue one authoritative receipt | Actual authoritative payout confirmation **and approved reconciliation**; not approval/HTTP acceptance/browser/scheduler/message delivery |
| Failed withdrawal | Never generate a paid receipt for failure | Preserve any earlier genuine paid evidence and explicit later reversal/adjustment; never rewrite history |
| Settlement PDF | On demand unless applicable law/contract requires otherwise | Actual settlement evidence required for confirmed statements |
| Daily/weekly/monthly reports/exports | On demand unless applicable law/contract requires otherwise | Approved metric/currency/cutoff definitions, secure PDF/XLSX/CSV exports |
| Customer-payment notifications | Email only; no per-payment SMS | R1.2 individual/daily/weekly/off optional modes remain |
| Withdrawal status notifications | SMS and email under mandatory-notice policy | Optional controls cannot defeat approved mandatory notices |
| Notification price | Free to merchants; platform bears approved costs | No per-message fee or hidden deduction from merchant funds |

**Three different decisions:** product approval establishes intended behavior; legal clearance determines applicable document/notice obligations, classification/retention/delivery requirements; implementation permission authorizes specific reviewed files/migrations/actions. Only the first is supplied here. No legal clearance, official provider confirmation, real document generation or migration authority is inferred. Any verified legal/contract exception is explicitly scoped/versioned rather than silently replacing the approved default.

Receipt uniqueness, same-number redelivery, immutable evidence/artifacts, private storage/download authorization, separate generation/delivery/retention and no loss of mandatory documents remain. On-demand reports do not stop daily/weekly email digests: summaries may continue under R1.2 preferences without generating PDF attachments. No financial records are removed to reduce storage.

## 3. R1.2/R1.3 reconciliation and exact future amendment

| Location/conflict | Resolution recorded now | Future controlled documentation/implementation change — not performed |
|---|---|---|
| R1.2 executive summary and section 8 automatically generate request/advice PDF on committed request | D13-1 product default is now on-demand | In a separately authorized amendment, state that committed request creates durable request/evidence/history; an authorized document request generates or reuses its immutable advice, unless applicable automatic issuance is mandatory |
| R1.2 section 10 proposes always-email delivery of advice and receipt, even optional messages off | Automatic advice-artifact emailing cannot remain unconditional when no advice is routinely generated | Remove automatic advice-document delivery on every request; retain withdrawal request SMS/email status notices. Such email may link to authenticated history/on-demand document action, without claiming an unissued PDF exists |
| Emailing an on-demand advice versus secure retrieval only | **Still PROPOSED / REQUIRES APPROVAL** | Specify whether authorized generation defaults to secure retrieval, optional requested verified-email delivery, or mandatory delivery for a verified obligation; approve attachment versus authenticated link and recipient policy. Current approval does not settle this delivery choice |
| R1.2 section 9 automatic confirmed receipt | Unchanged | Keep unique issuance after authoritative payout + reconciliation, automatic verified-email delivery direction and private download; outstanding delivery/recipient/legal details remain to resolve before implementation |
| R1.2 sections 15/16 gates and acceptance assume automatic request advice | Scope/test expectations must follow D13-1 | Replace automatic advice generation tests with authorized on-demand, reuse/idempotency and legal-exception tests; preserve request notifications and all paid-receipt evidence gates |
| R1.2 section 17 / R1.3 section 13 and sections 18–20 say D13-1 owner approval pending | Product approval is now recorded here | Reference this record in a later expressly authorized revision; retain legal/contract/technical approval requirements. Do not keep asking for the same product approval |

Exact proposed future policy wording:

> A committed withdrawal request retains authoritative request and financial evidence without routinely generating an advice PDF. An authorized user may request its private advice PDF; reuse an existing issued artifact for the same issuance/version. Generate automatically only where a verified applicable legal or contractual requirement requires it. Withdrawal request status SMS and email remain governed by the mandatory-notice policy. Advice-document email delivery occurs only under the separately approved on-demand/mandatory delivery policy. Confirmed payout receipts remain automatically issued only after authoritative payout confirmation and approved reconciliation; delivery retries reuse the same receipt number.

This is a reviewable amendment specification, not a rewrite of R1.2/R1.3 or authorization to apply one. Existing R1.2 legal-delivery uncertainty is not “resolved” by calling every notice mandatory. Product approval removes the routine automatic advice requirement, not applicable legal obligations. R1.0 accounting/provider/attribution requirements and R1.1 pricing/settlement/refund gates have no newly identified conflicting requirement; R1.3 dashboard/team/MFA proposals remain deferred and do not broaden onboarding schema scope. Earlier eight-profile-column/broad-completeness assumptions stay amended by v2.0/secure review, not reintroduced here.

## 4. B3.2 security decision matrix

Decision classes: **I** internally selectable product/technical policy; **T** technical implementation/rehearsal evidence; **P** actual external provider contract/evidence; **L** qualified legal/privacy review. A row may need several. Owner approval of PDF policy does **not** approve technical parameters in this matrix. Existing product safeguards remain requirements; suggested mechanisms are still proposals.

| ID/topic | Existing requirement / proposed decision for review | Current status and decision/evidence needed |
|---|---|---|
| S01 NIN syntax vs identity | Bounded canonical string, preserve leading zeros; supplied, syntax-valid, independently identity-verified, authority-established and internally-approved are five different facts | **PROPOSED:** 20 ASCII digits with explicitly allowed separator normalization from prior specification. **NOT VERIFIED:** full current official canonical/checksum rules. I/T validator contract + authoritative NIDA evidence/P/L before real collection; invent no checksum/API, birth/citizenship inference or identity-verified flag |
| S02 Subject/declaration | Individual owner versus legally authorized natural-person representative; unknown classification explicit, no role/name inference | I/L approve truthful relationship claim, purpose/notice/declaration wording/version; T bindings. Declaration is not legal-authority/identity proof and must not secretly require optional full profile |
| S03 NIN encryption/display | Dedicated merchant-scoped versioned sensitive store; authenticated encryption, write-only entry, restricted masking (at most last four if justified) | I/T choose vetted envelope/service interface and record/merchant/purpose/version binding; L minimization. No raw NIN in merchants/profile snapshots/logs/errors/old input/URLs/exports. No general full-number reveal approved |
| S04 Key ownership/rotation/recovery | Separate keys from DB/backups; no universal APP_KEY reuse; distinct encryption/OTP/fingerprint domains and key IDs | I name accountable security owner, custodians/decrypt/rotation/recovery permissions; T select mechanism, rewrap/rotation/tamper/restore evidence; L/P processor review where relevant. No real key creation or APP_KEY change here |
| S05 Minimization/privacy | Collect only justified identity/contact data, private defaults, category retention/holds/deletion and processor/transfer review | I/L settle purpose/lawful basis/notices/rights/retention operators; T redaction/deletion/backup-manifest enforcement. No invented retention years or consent-as-universal-legal-basis claim |
| S06 Verified identity email | Existing native signed verification preserved; proposed submission uses fresh submitting owner's current users.email verified for that exact address | I ratify authoritative binding; T fresh email/verification/version and immutable submission-reference behavior. Optional business_email cannot inherit proof. Alternate email requires separate ownership flow; no new email change/verification implementation here |
| S07 Verified phone ownership | Exact application phone requires a provenance-bound successful OTP proof | I approve E.164-shaped normalization/country policy/proof lifetime; T successful atomic challenge consumption; P delivery readiness. Format/delivery acknowledgement is not possession or identity proof; no backfill from business_phone |
| S08 Purpose binding | Proof scoped to merchant + actor + contact version + purpose; no cross-merchant/account/settlement/MFA transfer | I approve reuse/lifetime/outage rules; T composite binding/action tests. Notification phone-purpose reuse needs separate policy; not automatic from onboarding proof |
| S09 OTP generation/expiry/guesses | Suggested six CSPRNG decimal digits, five-minute expiry, five failed guesses/challenge and ≥60-second resend cooldown | **PROPOSED, not independently approved/implemented.** I/T exact boundary, clock and expiration behavior; generate near authorized send, no queue-delay extension; leading zeros preserved |
| S10 Aggregate/rate/spend | Prior review additionally suggests 10 failures/30min actor+merchant+target; ≤3 sends/15min and ≤5/hour same scope; IP 20 send requests/hour | **PROPOSED** additional per-actor/target-wide/global/spend limits with numeric thresholds still open. I approve budgets/windows/lockout recovery; T exact rolling-window/atomic counters/boundaries; P real cost/throughput. Resend cannot reset aggregate failures |
| S11 Delivery readiness | Replaceable adapter, approved sender/provider contract and outage/idempotency/delivery semantics; fake transport test-only | P/I/T provider contract/security/cost/readiness and explicit real-send activation; L privacy/vendor review. No endpoints/credentials invented; acceptance is not possession; ambiguous timeout cannot create proof or trigger blind retries |
| S12 Replay/abuse | One active challenge per bound tuple; single-use consume; persistent quotas, expiry, target privacy and attempt limits | I/T keyed HMAC/digest with separate secret, bound challenge/tenant/actor/contact/purpose, constant-time compare; fencing/atomic one-winner tests. No plaintext code in ordinary DB/queue/logs, no unkeyed low-entropy hash-only storage |
| S13 Contact changes | New email/phone version requires appropriate reauth/reverification, revokes current dependent proof/readiness; historical frozen evidence unchanged | I define recency/recovery/proof-lifetime and pending-review replacement lifecycle; T races/invalidation/session/auth_version tests. Unrelated optional profile edits need not invalidate phone proof; new unverified email never receives financial documents by automatic transfer |
| S14 Tenant isolation | Scoped root resolution plus exact composite tenant FKs, current resource/version association and fresh checks in jobs/actions | T review referenced UNIQUE keys, null pairing, DB/raw-fixture and HTTP/direct-action tests. Browser/session IDs or cached relations are not authorization |
| S15 Role permissions | Preserve owner/manager/staff; proposed sensitive identity/contact/submission owner-only; staff safe status only; Chief review separate | I ratify future per-action matrix; T fresh active verified account/membership/version/role. Owner is not legal representative proof; no Chief Gate::before bypass, provisioning, team/custom role expansion |
| S16 Audit/redaction | Bounded same-transaction success audit, server actor/time/resource; denial logs redacted | I exact event/reason allowlist and retention; T PHP enum/SQLite CHECK synchronized, scalar bounds/FKs/index preservation and rollback. No NIN/OTP/raw targets/ciphertext/digests/provider bodies in ordinary logs/audit; model append-only not tamper-proof DB |
| S17 Backup/restore/key recovery | Consistent private backup plus independent protected recoverable keys; restore to new isolated target | I operators/access/recovery objectives; T synthetic decrypt/rotation/restore and current-data independent recovery evidence; L retention/transfer/holds. Same-disk/OneDrive copy alone is not independent recovery; replacing APP_KEY is not recovery |
| S18 Production readiness | Default-deny live capture/submission without approved schema/key/privacy/verifier/operational readiness; preserve local gate | I/P/L/T official services/eligibility, deployment domain/HTTPS/hosts/proxies/cookies, production auth/MFA/queues/monitoring/recovery and explicit activation. None settled merely by inert schema or document approval |

No online NIDA verification API is added as an invented universal initial-submission gate. Independent identity/authority evidence may be required by a verified phase/service rule. Secure storage/approved syntax/contact proofs remain mandatory technical/privacy prerequisites. Missing required verifier/key/schema/readiness means no submission, not optional-document substitution or forced “verified” override.

### OTP parameter decision sheet — all pending technical approval

| Parameter | Existing recommendation for review | Remaining precise choice |
|---|---|---|
| Code | Six decimal CSPRNG digits | Approved generator/mechanism and transient plaintext boundary |
| Expiry | Five minutes | Exact issuance/send timestamp, expiry comparison, clock policy; queue delay never extends |
| Challenge attempts | Five failures | Atomic count/check/consume and terminal behavior |
| Resend | At least 60 seconds | Same actor/merchant/target/purpose scope, failure/ambiguous-send quota treatment |
| Aggregate guesses | Ten failures per 30 minutes actor+merchant+target | Exact rolling-window algorithm; persist across resends/restarts |
| Aggregate sends | At most three per 15 minutes and five/hour actor+merchant+target | Exact window/boundary/counter reservation policy |
| IP send request limit | Twenty/hour proposed | Trusted client-IP/proxy model, shared-IP fairness and attack tests; not approved production capacity |
| Wider abuse controls | Target-wide, actor-wide, global/service spend ceilings | Numerical budgets, cooldown/support recovery, retention/privacy and alerts remain undecided |
| Proof validity | Unchanged exact target/version/purpose plus operational readiness | Duration/reverification/change/outage policy and simulated-proof exclusion |

These values are carried forward for review, not newly approved by being listed here. No provider rate or legal mandate is inferred from them. Approve temporary cooldown/recovery instead of a permanent attacker-induced lockout; delayed/duplicate jobs cannot revive old challenges. Provider send acceptance, independent identity verification and financial-account ownership remain distinct.

## 5. Open decisions and dependencies — what can be settled where

**Internally selectable, with owner/technical approval (I):** exact B3.2-B table subset/naming/bounds/event set, authoritative email binding, actor/merchant/purpose proof scope, owner-only action matrix, OTP numeric policies/window semantics/recovery defaults, key custodians/permissions, declaration product flow and operational responsibilities. Internal choice alone does not prove the chosen control works or that collection is lawful.

**Technical evidence (T):** reviewed crypto service/key envelope implementation, composite constraints/version/CAS/active-slot checks, audit rebuild preservation, fail-closed config/schema behavior, exact rate-window concurrency, redaction and synthetic restore/decrypt/rotation tests. These need separately authorized preparation, not assumptions of Laravel support or tests mirroring unverified implementation. Production engine selection/port/concurrency remains separate from SQLite fixtures.

**Provider evidence (P):** current official NIN syntax/validation evidence; any optional future identity-match access/contract; actual SMS delivery/idempotency/error/security/cost/sender contract and operational readiness. Independent NIDA integration and real SMS sends each require separate permission. Payment/settlement/refund contracts are still needed only before relevant financial work, not as fabricated prerequisites for an inert identity schema.

**Legal/privacy/professional evidence (L):** lawful NIN/representative processing, notices/consent where applicable, processor/transfers, category retention/rights/holds, document classifications, applicable automatic issuance/email obligations and acceptable delivery evidence. Product approval of D13-1 is complete; the remaining review concerns applicable exceptions/delivery/compliance, not repeating the same owner product decision.

Practical next review package: (1) ratify exact core schema subset in section 6, (2) select key ownership/interface/recovery approach, (3) approve contact/purpose/declaration and parameter/window policies, (4) identify authoritative syntax/privacy evidence gaps and explicitly keep real collection blocked, (5) authorize only the concrete synthetic preparation scope. No elapsed time, document title “final,” or prior conditional recommendation supplies execution approval.

## 6. Candidate B3.2-B bounded scope — not authorized for execution

**PROPOSED candidate B-SYN-1:** inert additive identity/contact/OTP schema preparation plus narrowly necessary models/casts/enums/test support and synthetic constraint/migration/crypto-interface tests, **only if separately explicitly authorized**. This record creates none of them. No request controllers/routes/forms/capture actions/queue workers/provider adapters/sends or business lifecycle mutation functionality belongs in this candidate. Schema references to delivery intent are inert persistence preparation, not notification implementation.

### Core candidate structures

Names are review candidates inherited from secure review section 7.1, not existing tables. Preserve ULID merchant/record keys and existing integer user identities, UTC timestamps, positive versions, server-owned actor fields, restricted deletion and explicit tenant associations. Concrete migration count/filenames/SQL are intentionally not generated.

| Candidate | Minimum persisted responsibilities | Required constraint review |
|---|---|---|
| merchant_identity_records | Merchant/version/subject claim; encrypted canonical NIN envelope + key/version; syntax policy/check metadata; declaration/notice version, actor/time; supersession/retention metadata | PK ULID; FK merchant/user; UNIQUE(id,merchant), UNIQUE(merchant,version); no global unique NIN, no raw plaintext index; no independent verified flag/backfill |
| merchant_contact_versions | Merchant/actor/purpose/version; encrypted normalized phone/key ref, private HMAC target fingerprint/key ref, created/retired times | Exact composite unique reference tuple (id,merchant,actor,purpose); UNIQUE merchant+actor+purpose+version; immutable target/version |
| merchant_onboarding_contexts | Merchant sidecar current identity/contact pointers, paired contact actor/purpose and positive context version | Merchant PK; current identity composite FK; current contact full composite FK; all-or-none nullable contact fields; CAS readiness, no guessed initial rows |
| phone_otp_challenges | Merchant/contact/actor/purpose tuple, active-slot/state, bound digest/key ref, expiry/failures, consumed/revoked times | Exact contact composite FK and referenced challenge tuple; at most one active slot per tuple, state consistency/positive/nonnegative checks, expiry/state lookup |
| otp_delivery_intents | Challenge-bound inert intent/state/claim version, requested/claimed/completed metadata, test/real provenance label, bounded failure code and protected provider reference if necessary | UNIQUE challenge, exact tenant/contact/actor/purpose composite FK; no plaintext OTP/phone/body or executable send job |
| contact_verification_proofs | Bound contact/challenge/merchant/actor/purpose, time/channel/policy/provenance, revocation reason/time | UNIQUE challenge; exact composite association; immutable historical content except audited revocation; simulated proof can never be treated as live eligible |
| verification_limit_state | Private HMAC scope/rule, window/counter/reservation/version/cooldown metadata | Composite scope+rule PK; atomic reservation design; exact rolling-window storage approved before generation. Any bounded event/bucket support requires explicit inclusion, not silent extra tables |

**Audit compatibility slice:** propose only the exact identity/contact/OTP event/reason names necessary for the approved core subset; no blanket all-future-event enum. Present synchronized PHP enum/DB CHECK change and table-rebuild plan if needed, preserving all original events/rows/IDs/FKs/indexes/metadata bounds. No working audit rows created. Production audit logging remains future action implementation.

**Explicit exclusions from this default candidate:** optional five profile columns (legal_name, activity, website, representative name/capacity) can be separately elected but are not silently bundled; old three broad-attestation columns are not automatically restored. Submission bindings/application snapshot changes are deferred to a separately approved B3.3 preparation slice. KYC cases/files/uploads/findings, tax/UBO/documents, broad retention engines, payment methods/financial tables, dashboard/team/MFA/notification preferences/outbox and PDF issuance are excluded. This is a narrower recommended review slice than the secure review's possible combined profile+identity package, not a claim that optional profile requirements are cancelled. Owner may approve a different explicit subset in a subsequent instruction.

### Isolated acceptance required if B-SYN-1 is later approved

1. Guard effective test environment and DB connection before any schema action: disposable SQLite `:memory:` or named random temporary synthetic fixture only; no DB_URL override, no cached production config, never canonical working SQLite. No real personal/provider data or external network.
2. Additive migration up/down/reapply on populated synthetic old-schema user/merchant/membership/application/audit graph. Compare existing IDs/values/FKs/indexes/triggers and encrypted baseline snapshots; no rewrite of old applied migrations. Inject audit-rebuild/copy failure and prove rollback.
3. Enforce tenant composites, paired-null pointers, positive integer/state checks, active uniqueness and referenced-key validity in raw DB fixtures. SQLite declarations/lockForUpdate alone do not prove enforcement/concurrency; review actual engine behavior.
4. Synthetic-only envelope/key-interface tests prove wrong key, tampered/foreign context, version rotation and isolated backup/restore/decrypt fail/succeed correctly without printing values. No real deployment keys, secret manager provisioning or custom cryptography. A concrete implementation interface/test scope must be approved before adding support code.
5. Counter/window/challenge/proof persistence constraints tested with synthetic timestamps/records; no real OTP issuance/verification endpoint. A fake provenance must fail any prospective live-proof eligibility check. Retention of minimal references cannot accidentally delete history or reset live abuse windows.
6. Required existing isolated auth/host/B3.1 regressions run only under separately approved safe test setup; report actual totals, not reused historical numbers. No tests executed now. Hash-check working DB/env before/after later approved preparation and verify no source/frontend drift.
7. Deliver exact reviewed migrations/SQL, key/index/constraint dependency map, bounded support-code diff, sensitive-data scan and actual test report for owner review. No working activation/commit/push follows automatically.

### Explicitly deferred stages

| Stage/scope | Separate gate; not included in B-SYN-1 |
|---|---|
| B3.2-C working activation | Exact reviewed migrations/target, fresh preflight, no active writes, consistent private SQLite backup + restore-to-new-file rehearsal, key safeguards and explicit execution approval; no reset/seed/import |
| B3.2-D secure capture | Scoped profile/identity/contact inputs, readiness/actions/UX and OTP runtime under approved policies; no live provider sends/real NIN collection without further readiness/permission |
| B3.3 submission/history | Immutable exact identity/contact/email/version bindings, minimum readiness, current draft/CAS/idempotency/audit; no raw NIN in profile snapshot |
| B3.4 Chief review | Privileged permission/reauth/MFA/conflict/evidence policies and audited decision actions; no account provisioning side effect |
| B3.5 acceptance/hardening | Full planned security/lifecycle/browser/regression/target-environment acceptance; essential controls/tests in earlier stages are not postponed until B3.5 |
| Financial/dashboard/team/notifications | F0–F5/provider/legal/permission gates and R1.0–R1.3 responsibilities remain separate; no ledger/payment/API/QR/buttons/pricing/payout/PDF/settings/MFA/role expansion or real messaging here |

Working rollback is never implied by code approval. New audit types/identity evidence may make down/restore unsafe after use; prefer approved forward correction and reconcile subsequent writes. Never delete audit events to fit an old CHECK or regenerate APP_KEY. Same-workspace backups are not independent recovery proof.

## 7. GO / NO-GO and next approval boundary

**GO:** record D13-1 as owner-approved product policy, with legal/contractual exceptions and delivery choices explicitly unresolved. This one document is ready for owner review. No further product ratification of on-demand advice is needed; future documentation revision/application implementation still needs its own scope approval.

**CONDITIONAL recommendation only:** B-SYN-1 is a precise candidate for an explicitly authorized isolated B3.2-B preparation task after minimum subset, crypto/interface/key ownership and contact/OTP/declaration/storage decisions are resolved sufficiently for concrete generation. Official live service/legal prerequisites may remain explicit blockers to real use without preventing inert schema review. Unresolved constraint/window/crypto choices must not be filled with permissive defaults merely to generate migrations.

**NO-GO now:** execute B3.2-B, create migration/support code, activate working schema, collect real NIN, send OTP/email/SMS, integrate providers, build UI, create accounts, alter data/config/keys, commit/push/deploy or automatically advance B3.2-C/D/B3.3/B3.4/B3.5/F stages. This record is not migration approval. Security parameters remain proposed; D13-1 product approval does not approve six-digit/five-minute/aggregate OTP settings by association.

## 8. Preservation verification and stop

Preservation baseline: 186 unique pre-existing files, comprising repository tracked files across projects, existing untracked documents and explicitly included `backend/.env` and working `backend/database/database.sqlite`. Before/after SHA-256 comparison found **186 unchanged, 0 changed/missing**. R1.0–R1.3, secure architecture, v2.0, existing migrations/tests/backend/frontend/historical documents and unrelated tracked projects retain their baseline hashes. `.env` equality preserves APP_KEY without disclosing its value; working SQLite equality proves file preservation, not a new integrity/production-readiness certification.

Only new deliverable: `XLIPA-OWNER-DECISIONS-AND-B3-2-READINESS-2026-11-06.md`. HEAD remains `7c3e4743384e0ca0acd7b029c944569c84304541`; no staged/tracked modifications. Seven pre-existing untracked documents remain untouched. No runtime/tests, migrations, backup creation/import, database writes, installs, accounts, financial documents, messages, integrations, UI edits, Git-history changes, commit/push or deployment.

**STOP — await explicit approval for the next concrete stage.**
