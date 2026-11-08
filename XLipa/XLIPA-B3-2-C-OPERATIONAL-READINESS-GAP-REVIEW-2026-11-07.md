# XLipa — B3.2-C Operational Readiness Gap Review

**ORG-1.0 · 7 November 2026 · Africa/Nairobi · READ-ONLY SOURCE AND DOCUMENT REVIEW**

## 1. Executive summary

**Recommendation: stop broad synthetic security expansion; proceed to owner decisions, one bounded working-safety package and isolated merchant product development in parallel. Working B3.2-C activation remains NO-GO.** No implementation or working access is authorized by this review.

XLipa already has a branded public website, native account authentication, merchant foundation models/constraints and a tenant-scoped read-only onboarding-status page. B1 activation and subsequent local authentication use are **REPORTED** historical events. The most recent reviewed working snapshot, FRR-2.0, reports the 14-table foundation and nine genuine migration records, with the seven B3.2 security tables absent. Consequently, “C activation disabled” must not be misread as “nothing has ever worked locally.” Current target/runtime state is **NOT TESTED** here. [B1] [B2] [B31] [FRR]

The accepted B security contracts and C recovery controls have substantial fixture evidence. SOB reports **444 tests / 3,958 assertions**, including 86 new SOB cases; these results are **REPORTED**, not freshly executed in ORG. The contracts do not yet provide product-facing identity capture, operational OTP, submission, Chief Admin review, dashboards or financial services. RRT demonstrates SQLite backup/restore on generated databases; SOB demonstrates encrypted logical-content custody, not restoration of a runnable SQLite database. [FOD] [CAR] [RRT] [SOB]

The shortest defensible route to C activation is: **record operational decisions → prepare and approve a precise read-only package → separately authorize fresh target checks → prove the missing native/tool/custody boundaries → separately authorize backup → separately authorize restore to a new file → separately approve one working migration → separately approve named local runtime capabilities.** A successful step never grants another step. A further general simulation framework would not close these specific gaps. [WSR] [OSD] [SOB]

Meanwhile, a read-only Merchant Dashboard can reuse `/account`, the onboarding summary and the current membership policies in isolated tests. Chief Admin screens, contact-proof/runtime services and application/review workflows can follow as separate packages using generated fixtures. Selcom access is not a dependency for that work. Existing branding and canonical frontend remain the starting point; financial examples must remain explicitly non-operational. [ROUTES] [SUMMARY] [TENANT] [PAYPLAN]

XLipa remains a Tanzania-first payment gateway **WEBSITE supporting TZS only**. No completion percentage, calendar deadline, provider entitlement or production-readiness claim is made.

## 2. Current implementation baseline

### 2.1 Evidence labels and scope

- **VERIFIED:** source/document content newly inspected in this review, including route and component inventory. It does not mean a fresh execution.
- **REPORTED:** prior test, target or runtime result recorded in a named report. Historical approvals/results retain their original scope.
- **PROPOSED:** a recommendation or unimplemented work package; no execution permission follows.
- **NOT TESTED:** no new executed evidence for that behavior/current environment in this review.

All findings below combine **VERIFIED source inspection** with explicitly attributed **REPORTED test evidence** where relevant. No tests were executed. The filenames “Final,” “Completed” and “Owner Acceptance” are not proof of deployed capability or an actual human signature. The FOD report recommends bounded B acceptance and records owner sign-off as pending; preserve the accepted technical contracts while recording actual operational approval separately. Do not restart B acceptance testing solely to relabel an unchanged pass. [FOD]

### 2.2 Source anchors

Reference links throughout this report resolve to existing files relative to `XLipa/`.

| Anchor | Newly inspected evidence and limits |
|---|---|
| [ROUTES] | `backend/routes/web.php`: public pages, account authentication and one merchant onboarding GET. No profile PATCH, submit/reapply/review, admin dashboard or payment API route. `routes/console.php` contains the inspirational command, not an operational recovery executor. |
| [AUTH] / [READY] | Authentication controllers, requests and middleware; transactional owner registration, session/auth-version enforcement, signed email verification, reset and rate-limit source. Readiness explicitly requires foundation schema, `local/testing` and `log/array` mail. This is not a production delivery profile. |
| [TENANT] / [SUMMARY] | Member-scoped merchant lookup, current-attempt owner/manager policy, safe summary view model and read-only onboarding controller. Merchant staff summary differs from application-detail access; platform roles do not grant tenant access. |
| [MODELS] / [APP] / [AUDIT] | Merchant/profile and application fields, guarded identities/statuses, encrypted hidden application evidence and model-level immutability. Stored fields/states do not create mutation workflows. |
| [AUTH-TEST] / [TENANT-TEST] / [FOUNDATION-TEST] | Inspected acceptance assertions for atomic creation, duplicate handling, CSRF/sessions, tenant isolation, role downgrade/revocation, history constraints and audit behavior. Existence of assertions is not a new pass. |
| [ACTIVATION] / [MANIFEST] | Consolidated C artifact and explicit rehearsal-only working-disabled selection. Exact artifact/frozen contract plus exclusion of three synthetic B migrations are preserved. |
| [VERIFY] / [RECONCILE] | Synthetic verification, provenance, expiry/consumption and pending-audit reconciliation. `eligibleForLive()` returns false; these helpers are not application services. |
| [RRT-CODE] / [SOB-CODE] | Fixture-only recovery orchestration and operational-boundary composition; tests/bootstrap, source hashes and limitations recorded in the corresponding reports. |
| [FRONTEND] / [DEVELOPERS] / [CONTACT] | Existing canonical public/auth website and shared assets. Payment/settlement/fee descriptions are website content, not financial implementation or provider-contract evidence. Contact uses a mailto handoff, not a support case system. |

Reviewed the applicable backend `AGENTS.md`; `.ai/rules` was absent at checked workspace/project/backend locations. Inspected existing source/tests/migration/activation text and relevant B0/B1, B2, B3.1, B3.2-A/B/C, OSG/OAD/FRR/RRT/OSD/SOB and payment-roadmap documentation. No application/tool runtime, dependency manager, formatter, browser, test or external-provider call was needed for this review.

### 2.3 Reconciled historical baseline

| Milestone | What its evidence establishes | What it does not establish |
|---|---|---|
| Phase 2 / B1 | Public website/local Herd history; SQLite foundation source, models and invariants. B1's initial “not migrated” report predates its separately recorded local activation. [PH2] [B1] [B1-ACT] | Current hosting, configuration, current target state or production-engine support. |
| B2 / B3.1 | Account workflows, foundation ownership and tenant-scoped read-only status. B3.1 reports 108 tests / 847 assertions and 31 frontend interaction checks. [B2] [B31] | Complete Merchant/Chief Dashboard, profile mutations, submission, live mail or full visual/browser sign-off. |
| B3.2-A v2.0 | Accepted product direction: individuals/unregistered applicants may apply; business name, NIDA, phone, email minimum; verified email/phone; optional broader fields/documents. [SPEC] | Real NIN collection permission, implemented capture/OTP/submission or an online NIDA integration mandate. |
| B3.2-B / C rehearsal | Accepted fixture schema/crypto/OTP/proof/R06/audit contracts; consolidated C schema parity and interruption/ledger-gap refusal. [FOD] [CAR] | Working C migration, runtime services, real verified contacts or financial authorization. |
| FRR-2.0 | Historical 14-table foundation, nine migration rows, seven C tables absent; original eight-event audit definition, no partial C activation, 235 allowed authorizer events / zero denials. [FRR] | Freshness now, ongoing quiescence, perpetual permission, recoverable backup or current application configuration. |
| OSG / OAD / RRT / SOB | Bounded permits/selection/classification, corrected authorizer, engine backup/recovery fixtures and native/crypto/witness/fence prototype evidence. SOB's final combined 444/3,958 is the latest reviewed reported suite. [OSG] [OAD] [RRT] [SOB] | Operational principals/keys, independently protected replay storage, real maintenance/custody or production readiness. |

The latest FRR snapshot reports existing foundation records; do not describe working storage as disposable or empty merely because the seven new tables would initially be empty. Backup custody must protect the existing account/contact/application material. No such records were accessed in ORG. [FRR]

## 3. Component readiness matrix

Status meanings apply to the **named scope**, not the entire product:

| Status | Meaning in this review |
|---|---|
| **IMPLEMENTED AND TESTED** | Source exists and relevant successful tests are reported for the limited scope stated; no fresh run or production certification. |
| **IMPLEMENTED BUT NOT FULLY TESTED** | A real partial implementation exists; significant behavior or visual/operational evidence is missing. |
| **SYNTHETIC-ONLY** | Implemented/tested on generated fixtures or fake identities/adapters; cannot be promoted by changing environment flags. |
| **DESIGN-ONLY** | Reviewed specification/architecture exists, with no corresponding complete runtime feature. |
| **NOT IMPLEMENTED** | No corresponding application service/route/storage workflow was found in the inspected project-owned runtime inventory. |
| **BLOCKED BY OWNER DECISION** | Scope, roles, custody, risk/data/fund policy or authorization must be decided before the named work can proceed. |
| **BLOCKED BY EXTERNAL PROVIDER** | An authoritative external contract/access capability is a prerequisite not evidenced in the reviewed project. This does not assert that a provider refused access or that credentials do not exist elsewhere. |

### 3.1 Merchant-facing features

| Component | Status | Concrete capability / gap and evidence |
|---|---|---|
| Merchant registration | IMPLEMENTED AND TESTED | B2 form/POST, normalized identity, password/terms rules and atomic user → merchant → owner → draft → audit graph. Not a submitted/approved merchant. [AUTH] [AUTH-TEST] [B2] |
| Authentication and sessions | IMPLEMENTED AND TESTED | Login/logout/reset/signed email verification, CSRF, throttling and fresh account/auth-version controls, within local/testing readiness. Current real-browser session is NOT TESTED. [AUTH] [READY] [AUTH-TEST] |
| Merchant tenancy | IMPLEMENTED AND TESTED | Fresh active membership root, explicit merchant selection, cross-tenant denial and role redaction on current read routes. Future actions need their own policies. [TENANT] [TENANT-TEST] |
| Merchant staff roles/read permissions | IMPLEMENTED AND TESTED | Owner/manager/staff model and limited read permissions; last-owner/revocation helper invariants. No staff lifecycle UI or general permission catalogue. [MODELS] [TENANT-TEST] [FOUNDATION-TEST] |
| Staff invitations/role administration | NOT IMPLEMENTED | No invitation/grant/revoke controller/workflow. A tested low-level membership helper is not public team management. [ROUTES] [B1] |
| Merchant profile storage | IMPLEMENTED AND TESTED | Business name/phone from registration and nullable structured profile/registration columns. No complete profile editor. [MODELS] [AUTH] [B1] |
| Merchant profile editing/readiness UI | DESIGN-ONLY | Optional-field and minimum-readiness rules specified; no PATCH route/request/action/CAS integration. [SPEC] [ROUTES] |
| Individual/unregistered onboarding | IMPLEMENTED BUT NOT FULLY TESTED | Generic account/draft creation exists; no complete applicant-type/representation/contact-proof/submission flow. Do not infer company/individual from absent business type. [AUTH] [SPEC] |
| Business registration information | IMPLEMENTED BUT NOT FULLY TESTED | Nullable business type/country/number storage exists; no dedicated collection, issuer checks or review workflow. Optional-at-submission policy remains. [MODELS] [SPEC] |
| Identity/contact security contracts | SYNTHETIC-ONLY | Seven-table contracts, XChaCha20-Poly1305/AAD, OTP/proof binding and R06 pending obligations. Simulated possession cannot satisfy live eligibility. [VERIFY] [RECONCILE] [FOD] |
| Real identity capture and phone verification | NOT IMPLEMENTED | No secure product capture service/endpoint, operational keys, actual OTP adapter/fencing/readiness or lawful real-data activation. Existing phone formatting proves no possession. [AUTH] [VERIFY] [SPEC] |
| Merchant application submission/reapplication | DESIGN-ONLY | Draft/current-slot/state/evidence models exist; no submit, single-use proof consumption, immutable snapshot transaction or reapply/history endpoints. [APP] [ROUTES] [SPEC] |
| Chief Admin approval | DESIGN-ONLY | Accepted decision authority and review states; no authorized review action, queue or independent decision workflow. Approval must never enable payments by itself. [SPEC] [ROUTES] |
| Onboarding status | IMPLEMENTED AND TESTED | Current safe summary GET, owner/manager detail redaction, read-only suspended/closed status and no automatic repair. [SUMMARY] [TENANT-TEST] [B31] |
| Merchant Dashboard | NOT IMPLEMENTED | `/account` and onboarding status are foundations, not a transaction/operations dashboard. No completed private dashboard found; reuse their seams and branding. [ROUTES] [SUMMARY] |
| Payment links | DESIGN-ONLY | Future capability in payment roadmap and public copy; no persisted link, expiry/disable, checkout or payment lifecycle. [PAYPLAN] [FRONTEND] [ROUTES] |
| Checkout | DESIGN-ONLY | No hosted checkout controller/payment attempt model. Provider methods/state/callback semantics remain to be contracted. [PAYPLAN] [ROUTES] |
| QR payments | NOT IMPLEMENTED | No QR request/link-generation/expiry/backend flow found. No rail or QR scheme capability is assumed. [ROUTES] [PAYPLAN] |
| Wallet and ledger | DESIGN-ONLY | Proposed immutable balanced bookkeeping; no runtime accounts/journals/entries or balances. “XLipa Balance” copy is not evidence of fund custody. [PAYPLAN] [FRONTEND] |
| Withdrawals | DESIGN-ONLY | Proposed protected payout lifecycle; no verified destination, holds, dispatch, reversal or provider adapter. [PAYPLAN] [ROUTES] |
| Settlements | DESIGN-ONLY | No statement ingest, settlement matching, exception workflow or verified fund-flow contract. [PAYPLAN] |
| Transaction history | NOT IMPLEMENTED | No financial transaction storage/API/private listing; proposed filtered views have no actual payment facts yet. [ROUTES] [PAYPLAN] |
| Reports and exports | DESIGN-ONLY | Roadmap only; no implemented totals, fee reports, scoped export jobs or leakage/capacity controls. [PAYPLAN] |
| API keys | DESIGN-ONLY | Proposed lifecycle/scopes/revocation; no credential issuance/storage/auth middleware or public payment API. [PAYPLAN] [ROUTES] |
| Merchant webhooks | DESIGN-ONLY | Outbox/signing/retry concepts only. No sender, endpoint policy, per-merchant secrets or delivery records. Distinct from provider callbacks. [PAYPLAN] |
| Notifications | IMPLEMENTED BUT NOT FULLY TESTED | Native auth notification paths are tested with inert transports; no operational delivery or merchant event/preference/outbox service. [AUTH] [READY] [PAYPLAN] |
| Support and disputes | IMPLEMENTED BUT NOT FULLY TESTED | Public contact/help content and mailto handoff exist; no authenticated tickets, case access, dispute/refund workflow or service-level evidence. [CONTACT] [ROUTES] |
| Branding/public/auth interfaces | IMPLEMENTED BUT NOT FULLY TESTED | Existing canonical pages/assets/form wiring and historical DOM checks; full current browser/mobile/accessibility sign-off is NOT TESTED. Do not rebuild this frontend. [PH2] [B2] [FRONTEND] |

### 3.2 Platform administration

| Component | Status | Concrete capability / gap and evidence |
|---|---|---|
| Chief Admin Dashboard | DESIGN-ONLY | Future platform workspace; no private dashboard or platform controller. [PAYPLAN] [ROUTES] |
| Merchant KYC review | DESIGN-ONLY | Masked/case-scoped review direction; no actual clean-document store, review case service or reviewer access controls. Full NIN reveal is not implicitly approved. [SPEC] [PAYPLAN] |
| Approval/rejection/information requests | DESIGN-ONLY | Model states/public-feedback/internal-note fields exist; no reviewed mutation service, concurrency/CAS, authority checks or audit transaction for decisions. [APP] [SPEC] |
| Platform transaction monitoring | NOT IMPLEMENTED | No financial source records or monitoring UI/service. [ROUTES] [PAYPLAN] |
| Fee and revenue reporting | DESIGN-ONLY | Static 5% examples exist; no implemented fee engine, accounting treatment or reconciled revenue totals. Commercial/provider treatment needs approval. [FRONTEND] [PAYPLAN] |
| Provider management | NOT IMPLEMENTED | No adapter configuration/health/credential lifecycle/permission surface. Logos/text are not integration. [ROUTES] [PAYPLAN] |
| Risk and fraud controls | IMPLEMENTED BUT NOT FULLY TESTED | Foundation auth throttles/tenant rules exist; no payment risk rules, limits engine, investigation or financial fraud controls. [AUTH] [PAYPLAN] |
| Platform staff administration | IMPLEMENTED BUT NOT FULLY TESTED | Separate `chief_admin/admin` assignment model/enum; no provisioning/grant/revoke workflow or action-level platform permissions. Chief authority is not granted to merchants/managers. [PLATFORM] [TENANT-TEST] |
| Audit logs | IMPLEMENTED BUT NOT FULLY TESTED | Foundation model-level immutability/metadata limits and historical audit writes; expanded event union and raw-SQL append-only triggers are C fixture-only until separately activated. No admin audit explorer. [AUDIT] [FOUNDATION-TEST] [FRR] [CAR] |
| Operational settings | DESIGN-ONLY | No permissioned settings mutations, versioning, secrets interface or operational change approval UI. [ROUTES] [PAYPLAN] |

### 3.3 Backend and infrastructure

| Component | Status | Concrete capability / gap and evidence |
|---|---|---|
| Foundation schema | IMPLEMENTED AND TESTED | Original plus B1 source/constraints/models; historical local activation and FRR foundation snapshot. Current schema is NOT TESTED. [B1] [B1-ACT] [FRR] |
| B3.2 schema and migrations | SYNTHETIC-ONLY | Accepted guarded B migrations and selected consolidated C artifact; no working-capable C edition/command. [FOD] [CAR] [ACTIVATION] |
| Application evidence encryption | IMPLEMENTED AND TESTED | Hidden encrypted application snapshot/notes in the foundation model. Operational key custody is not thereby established. [APP] [FOUNDATION-TEST] |
| Dedicated identity/OTP crypto | SYNTHETIC-ONLY | Domain-separated keys, bound AEAD/identities and typed fixture tests; no real decrypt service/key custody. [FOD] [VERIFY] |
| Authentication | IMPLEMENTED AND TESTED | Local/testing native account stack; production transport/profile and privileged MFA flows not delivered. [AUTH] [READY] |
| Authorization | IMPLEMENTED AND TESTED | Current merchant read policies and fresh state only. This is not permission for future submit, review, payout or provider actions. [TENANT] [TENANT-TEST] |
| R06/audit integrity contract | SYNTHETIC-ONLY | Persisted exhaustion accounting and pending-obligation gate/exact reconciliation accepted in fixtures. Runtime coordinator/scanner/readiness still needed. [FOD] [VERIFY] [RECONCILE] |
| Backup | SYNTHETIC-ONLY | RRT engine snapshots and receipts on generated databases. Historical B1/private backups are reported checkpoints, not a freshly verified approved recovery point. [RRT] [B1] |
| Restore | SYNTHETIC-ONLY | RRT engine restore-to-new-file fixtures; SOB encrypted-content replay is a different demonstration. No authorized current working-data rehearsal. [RRT] [SOB] |
| Recovery | DESIGN-ONLY | Incident boundaries, objectives and custody designed; no provisioned recovery service or achieved RPO/RTO. [OSD] |
| Release selection | SYNTHETIC-ONLY | OSG/RRT actual reviewed artifact selection; SOB inert allowlist/native leases/source pins. No immutable working release. [OSG] [RRT] [SOB] |
| Operational permits | SYNTHETIC-ONLY | Signed fixture protocols do not authenticate real people/host or working targets. Owner/issuer/verifier/executor separation remains to provision. [OSG] [SOB] [OSD] |
| Operational trust/custody assignments | BLOCKED BY OWNER DECISION | Named roles, public trust, protected witness/keys/destinations, sync strategy and authority record missing as operational evidence. [OSD] [SOB] |
| Runtime readiness | IMPLEMENTED BUT NOT FULLY TESTED | Foundation auth readiness exists; no corresponding operational C OTP/capture/submission readiness. `eligibleForLive=false` is intentional. [READY] [VERIFY] |
| Hosting | IMPLEMENTED BUT NOT FULLY TESTED | Local Herd history only; production domain/topology/TLS/secrets/deployment not selected or verified. No service inspected now. [PH2] [B31] |
| Monitoring | IMPLEMENTED BUT NOT FULLY TESTED | Health route and limited redacted error handling exist; no operator-alerting, recovery age, provider/reconciliation or payment monitoring system. [BOOT] [PAYPLAN] |
| Incident handling | DESIGN-ONLY | Stop/quarantine/no-refund principles documented; named responders, maintained runbooks, drills and privileged recovery authority not provisioned. [OSD] [SOB] |

### 3.4 External dependencies

| Component | Status | Evidence boundary |
|---|---|---|
| Official Selcom integration documentation | BLOCKED BY EXTERNAL PROVIDER | No reviewed official API/onboarding/callback/settlement contract in the inspected active source/document package. Do not infer it from XLipa marketing or third-party examples. [PAYPLAN] [DEVELOPERS] |
| Authorized Selcom credential handoff | BLOCKED BY OWNER DECISION | No reviewed access/provisioning authorization evidence; secrets were not inspected, so their existence elsewhere is unknown. Owner must obtain approved access and specify custody. [PAYPLAN] |
| Provider sandbox and entitlements | BLOCKED BY EXTERNAL PROVIDER | An account's actual test environment, supported methods, test data and permissions need official confirmation. No external service was contacted. [PAYPLAN] |
| Payment callbacks | NOT IMPLEMENTED | No receiver, verified signature/raw-body policy, event mapping, deduplication or authoritative amount/reference validation. Exact protocol awaits official documents. [ROUTES] [PAYPLAN] |
| Reconciliation | DESIGN-ONLY | No provider statement/status adapter, immutable match/exception records or financial closure evidence. [PAYPLAN] |
| SMS/email providers | BLOCKED BY EXTERNAL PROVIDER | Actual delivery contracts/access, supported idempotency/error handling and authorized recipients are unverified. Auth uses log/array; a production adapter is absent. [READY] [SPEC] |
| NIDA/approved identity arrangements | BLOCKED BY OWNER DECISION | Owner must choose lawful approved identity-processing/verification approach and authoritative syntax handling. Online verification, if chosen, also depends on official access; it is not an accepted universal initial-submission prerequisite. [SPEC] |

## 4. Working activation blockers

Here **L** means a *new local working C operation*, **U** real-user/sensitive onboarding and **P** live payments. Existing reported B1/B2 activation is not repeated or retrospectively revoked. **M** = mandatory safety, **D** = operational decision, **R** = feature/runtime implementation. Each closure below is **PROPOSED** and requires a separately scoped owner authorization; none is performed here.

| ID/type | Missing control, risk and evidence | Blocks | Smallest acceptable remediation | Owner / synthetic feasibility / closure evidence |
|---|---|---|---|---|
| O1 — D | Named operator, independent custodian/reviewer, approved host and authority record. Caller-provided fixture identities cannot authorize real access. [OSD] [SOB] | L/U/P | Complete one bounded local-operation decision sheet and recorded acceptance/disposition; do not build an IAM platform. Owner may also be independent signer/custodian if different from executor and explicitly approved. | Owner decision mandatory. Role behavior can be modeled; actual assignments cannot be fixture-proved. Close with named authenticated principals, separation, action/window/target and stop authority. |
| O2 — M/D | Real trust roots, signature issuance/verification, clock/status checks and replay evidence outside executor rollback custody. Local paired logs can be rolled back together. [OSG] [SOB] | L/U/P | Provision one separately protected signing workflow/public trust and genuinely independent claim/completion checkpoint custody. A reviewed attended/offline witness workflow is an option; a new network service is not inherently required. Keep single-use consumption and fail-closed freshness. | Owner approves facility/roles/epoch recovery; real provisioning needs separate permission. Test parser/replay/crash paths with existing fixtures; close with independently held fresh checkpoints, authenticated issuance/status and loss/rollback denial. |
| O3 — M | Fresh target/config/schema/ledger identity under an effective lease; historical FRR is not perpetually fresh. Wrong environment or a replaced object can invalidate all downstream evidence. [FRR] [WSR] | L/U/P | Prepare one reviewed fixed read-only invocation from corrected policy/FRR sequence. Obtain separate read-only authorization, then inspect only approved metadata and establish a freshness/fence boundary. No generic app boot or secret dump. | Owner approves exact target/tool/policy and sensitive configuration access, if necessary. Fixture denial tests possible; current target facts require the separately permitted real observation. Close with matching identity/schema/ledger and unchanged read-only preservation evidence. |
| O4 — M | Native destination/write-compatible identity, SQLite engine-to-object binding, protected tool deployment and bounded helper supervision remain unproved. Hash sampling/path strings alone are insufficient. [SOB] §§5–6 | L at relevant backup/migration boundary | Review the exact native acquisition/publication primitive or a vetted tool providing equivalent evidence for one controlled Windows/filesystem profile. Stage the tool immutably under protected custody; reject unsupported aliases/state. Add only focused tests for this demonstrated gap. | Owner approves profile and residual privileged-host threat. Fixtures can demonstrate acquisition, replacement and timeout behavior; close with enforced source/engine/destination identity across operation and denied substitution/helper failure. If it cannot be demonstrated, stop that action. |
| O5 — M/D | Approved encrypted custody/key recovery, space, ACLs, destination and source OneDrive strategy. A working snapshot includes existing private data, not just new empty tables. [FRR] [OSD] | Backup/restore before L migration; U/P | Select protected non-repository/non-webroot/non-sync staging and custody, a recoverable encryption facility, limited access and retention. Approve relocation or verified sync exclusion separately; pausing a client alone is not a permanent boundary. | Owner must choose actual locations/keys/retention; none selected here. Crypto/error logic can use disposable keys. Close with effective permissions/encryption, independent key recovery, capacity and documented sync/identity evidence. |
| O6 — M/D | Actual web/queue/scheduler/CLI/session/cache writer exclusion and controlled resumption. SOB counters are not process fencing. [READY] [OSD] [SOB] | Backup/migration L; recovery U/P | Inventory the real writer paths during separately authorized preparation; use one explicit maintenance window with admission/drain/connection/fence acknowledgements and loss detection. Account for shutdown journal effects before stopping writers. | Owner approves services/window/allowed effects/resumption. Fixture generation-loss tests suffice for logic only. Close with actual enforced writer evidence throughout capture/change/postflight; unknown writer or fence loss stops. |
| O7 — M | A current completed, encrypted, verifiable backup and independently approved real-data restore-to-NEW-file rehearsal. SOB `.sob` replay is not that rehearsal; old private copies do not establish current recoverability. [RRT] [SOB] | L migration; recovery U/P | Adapt the proven engine-API sequence into the reviewed bounded custody tool, acquire one separately permitted backup, then use a distinct restore permit and isolated destination. Verify schema/typed contents/ledger/integrity/FKs and decryptability with external completion evidence. | Both actions require separate approvals and real-data handling scope. Fixtures close tool faults; actual approved capture/drill closes recoverability for that capture. No source overwrite or promotion. |
| O8 — M | Working-capable selected artifact/release and write-compatible lease. Current artifact/manifest deliberately refuse working execution; stock migration logging retains the post-DDL/pre-log gap. [ACTIVATION] [MANIFEST] [CAR] | C schema L, then U/P features using it | Produce a separately named/versioned working edition and exact command/package, preserving frozen contract parity and original files. Exclude synthetic B migrations; validate pre/post schema/ledger and historical bytes, retain ambiguous-outcome quarantine. | Owner approves new source/hash, exact action and prerequisites. Existing parity/B assertions can be reused on fixtures; execution requires fresh O3/O6/O7 evidence and a new migration permit. Close with genuine expected schema/ledger and no drift. |
| O9 — R/M | Product services, feature-specific readiness, contact invalidation, secure capture and pending-audit reconciliation are absent; broad production auth/mail profiles are intentionally unavailable. [ROUTES] [READY] [VERIFY] | Expanded runtime E; U/P; **not inert empty-table migration by itself** | Implement only the next named feature in application code with injected fake adapters in tests. Reuse contracts, not `Tests\\` helpers/keys. Define readiness and redaction before exposure. | Owner approves feature and data/provider scope separately. Most behavior is fixture-testable. Close with route/action/policy tests, pending-obligation failure behavior and explicit runtime authorization; real identity/delivery prerequisites still apply. |

### 4.1 Essential now versus deferrable scope

| Requirement class | Must retain before the relevant local action | May defer without exposing real users/funds |
|---|---|---|
| Accepted security guarantees | Tenant isolation, original ULIDs/binary identity, AAD rules, encryption, OTP/proof policies, R06 exhaustion accounting/pending gate, audit history and no fabricated ledger completion. [FOD] | No blanket weakening. Further permutations are justified by changed code or a demonstrated defect, not another unchanged acceptance review. |
| Authority/identity/custody | Actual authenticated authority, independent replay evidence, correct source/engine/output identity, protected real backup data and separate action approvals. [OSD] | Enterprise directory integration, an online authorization portal and a highly available remote witness service are implementation options, not automatic prerequisites to a small attended profile. Equivalent independence/freshness remains mandatory. |
| Filesystem/runtime scope | One proven supported profile and refusal of unsupported paths/journals/recovery; real source synchronization control and actual fencing. [SOB] | Universal filesystem/OS/alias support, unattended daemon/scheduler, cross-platform recovery and defence against every kernel/admin actor. Do not claim these guarantees or use their absence to justify an unproven supported path. |
| Recovery objectives | A verified recovery point, usable key, separately authorized restore rehearsal, honest unknown-outcome stop and explicit development loss/outage tolerance. [RRT] [OSD] | Multi-region failover, continuous replication and production SLA certification. Physical power-loss guarantees remain NOT TESTED; do not claim them. Decide and validate stronger resilience before reliance on sensitive/financial availability. |
| Product/provider scope | No real NIN capture/delivery/financial side effect before corresponding approval and controls. [SPEC] [PAYPLAN] | Official online NIDA integration is not required merely to activate empty C tables or develop isolated UIs. Optional documents, wallet/withdrawals, analytics/exports and international currencies need not precede the first narrow approved collection product. |

This review does not waive OSD controls. A simpler implementation of a proposed operational control must provide equivalent evidence and receive explicit owner disposition. If no independent custodian/witness or enforceable identity profile is available, keep working execution blocked and continue isolated product work.

## 5. Live-payment blockers

These are additional to O1–O9 and do not all block local C schema preparation. Financial integrity remains mandatory even if the first product omits wallet/payout features. [PAYPLAN]

| Gap | Why / evidence | Minimum closure before the affected live scope |
|---|---|---|
| Approved provider relationship and actual fund flow | Marketing says Selcom processes/settles; that proves no contractual entitlement, custody model, method, fee or settlement API. [FRONTEND] [PAYPLAN] | Owner obtains official documents/authorized account, confirms permitted products, flow of funds, fee/settlement/exception responsibilities and commercial/legal readiness. No credentials inspected or provider capability invented here. |
| Payment/order/attempt lifecycle | No financial models/routes, immutable references, idempotent initiation or authoritative completion logic. [ROUTES] [PAYPLAN] | One TZS-only vertical slice with bounded integer monetary representation under the approved precision contract, fresh tenant authorization, server-owned amounts, durable attempts and tested duplicate/timeout/unknown states. A redirect is never confirmation. |
| Callback and reconciliation integrity | No callback receiver or provider evidence/statement matching; no genuine paid/settled facts. [PAYPLAN] | Official protocol mapping, authenticated replay-safe acceptance, idempotent durable processing, immutable payment/accounting effects and exception reconciliation against authoritative provider evidence. Test duplicates/reordering/conflicts/missing outcomes; no blind resend/charge on ambiguity. |
| Accounting and money movement | No ledger/holds/reversals/fees/settlement book. “Balance” cannot be calculated from unconfirmed UI events. [PAYPLAN] | Approve book/asset/liability meaning and implement auditable balanced entries/corrections and reconciliation for the chosen model. If a verified provider model settles directly, a platform-held wallet may be deferred; accurate financial records may not. Payouts/refunds need explicit individual scopes. |
| Real data, verification and privileged actors | No operational capture/OTP/review service, privileged authentication or lawful-processing activation. [SPEC] [READY] [OSD] | Secure transport, real key custody, purpose/notice/retention/access decisions, safe syntax handling, actual contact verification and invalidation; Chief/Staff action permissions, strong privileged authentication, audit and conflict handling. Do not collect real NINs merely to test a screen. |
| Operations and controlled rollout | Local Herd is not public hosting, monitoring, incident staffing or measured financial recovery. [PH2] [OSD] | Approved hosting/TLS/configuration/secrets, bounded live rollout/limits, alerts and responders, recovery/readiness drills, settlement/reconciliation review and explicit financial authority. No unmeasured RPO/RTO guarantee. |
| Fulfilment/support boundaries | No entitlement, dispute or refund workflow; customer/merchant-facing copy implies capabilities beyond source. [CONTACT] [PAYPLAN] | For the selected product, define safe payment-status/fulfilment evidence, human escalation, reversal/dispute procedures and truthful launch disclosures. Automated ticket/analytics systems may follow; an accountable support path may not be absent. |

An official SMS/email adapter is required before real delivery, not before every offline UI. Online NIDA access is required only if the approved identity policy selects it or a confirmed rule demands it; accepted initial submission must not acquire a new blanket requirement for online NIDA, TIN, BRELA documents, bank account or a withdrawal method. [SPEC]

## 6. Security effort assessment

### 6.1 Sufficiently demonstrated within accepted fixture scope

The B contract work has directly relevant source/tests for tenant/identity/AAD/crypto, OTP/proof lifecycle and durable R06 exhaustion obligations. CAR reuses compatibility assertions against the consolidated C schema and identifies the DDL/ledger gap. OAD supplies a corrected restrictive authorizer. RRT exercises actual engine backup/restore and six process kills on generated databases. SOB adds eight process kills, native sharing/identity, parser/binding, crypto and witness/fence failure models. Those are useful completed assets; unchanged historical success need not be re-reviewed recursively. [FOD] [CAR] [OAD] [RRT] [SOB]

### 6.2 Overlap and where effort should go

OSG, RRT and SOB deliberately overlap on authorization, replay, selection, interruption and refusal. Their scopes differ: memory permits, scoped simulated recovery and a separate SOB protocol. They must not be chained into working authority or all rewritten into another framework. Preserve their accepted assertions, select one separately reviewed operational interface and use the existing cases as regression requirements. SOB's inert artifact/crypto payload does not replace RRT's engine evidence. [OSG] [RRT] [SOB]

The next tests should attach to actual missing production-code seams: target/output acquisition and helper failure in the selected working tool; transactional contact/proof/pending services; submission snapshot/proof consumption; Chief decision policy/CAS; payment idempotency/accounting/callback reconciliation. An additional broad “security prototype” without one of these concrete gaps is not recommended. [SOB] [SPEC] [PAYPLAN]

### 6.3 Practical stopping point

**Stop general synthetic boundary development now.** Do not stop targeted tests for changes. Open only a small, demonstrably necessary native/deployment/witness test package where O2/O4 cannot otherwise be evidenced. If the owner has not chosen operators, custody or a fund-flow model, more fake identities or journal layers will not make that decision. Advance a real product slice with generated fixtures while those decisions are resolved.

Do not weaken R06 by adding TTL release, dropping pending history, resetting counters or fabricating audit completion. Implement the accepted runtime coordinator/reconciler/readiness behavior when the OTP service is built. Preserve encryption/context binding, tenant checks, immutable financial/audit corrections and no automatic retries after ambiguous external/commit outcomes. [VERIFY] [RECONCILE] [FOD] [PAYPLAN]

## 7. Minimum safe activation ladder

**PROPOSED gates; none authorized now.** Acceptance of this report authorizes no implementation or operation. Every working permit must remain fresh and independently scoped; one maintenance window may contain several separately approved actions, never one transitive approval. Administrative delay does not extend a permit.

### Stage A — Owner decisions and operational prerequisites

- **Entry:** reviewed baseline and operational gaps accepted as the discussion inputs; no new target access.
- **Scope/implementation:** OP-01 decision record, actual identities/separation, host/trust/witness approach, source/sync/custody, maintenance/recovery policy and incident/resume authority. Provisioning is a distinct expressly approved action, not implied by a design choice.
- **Acceptance tests:** document completeness, role separation/conflict review and no unresolved placeholder needed by the next action. Existing simulated role tests are not real authentication evidence.
- **Exit:** owner signs the bounded policy/authority record and explicitly authorizes only the next preparation/provisioning scope.
- **Owner approval:** required for decisions, actual destinations, custody/provisioning and residual risks.
- **Prohibited next actions:** opening working storage, copying/relocating it, signing an unrestricted permit or enabling a feature automatically.

### Stage B — Working-target safety preparation and separately authorized inspection

- **Entry:** Stage A decisions sufficient for OP-02; existing corrected authorizer/FRR sequence available.
- **Scope/implementation:** prepare the exact standalone read-only package, source/policy pins and supported identity/profile checks. Resolve O2/O4 to the extent required for read-only access. Then seek a separate working read-only permit before collecting any fresh fingerprint/configuration evidence.
- **Acceptance tests:** invalid authority invokes zero target access; supported identity/alias/helper/status failures deny on generated files; fixed metadata reads pass and do not write/recover. After explicit authorization, real inspection must meet its approved unchanged-identity/hash/schema/ledger and sidecar conditions.
- **Exit:** accepted fresh target dossier and effective freshness/fence policy. Drift or ambiguity stops; no dynamic baseline adoption.
- **Owner approval:** source/package preparation and the actual read-only attempt are distinct scopes.
- **Prohibited next actions:** backup, journal recovery/checkpoint, migration, service shutdown/sync changes or real secret disclosure beyond the approved inspection scope.

### Stage C — Protected backup and restore-to-NEW-file rehearsal

- **Entry:** fresh accepted Stage B evidence, actual writer fence, native engine/output protections, approved capacity/encryption/key/witness custody.
- **Scope/implementation:** OP-03 engine snapshot tool and OP-04 verification. **C1:** separately approve one working backup to a new protected destination. **C2:** independently approve decryption/restore of that completed backup into a new isolated file.
- **Acceptance tests:** source preserved, no overwrite, API completion plus schema/typed-content/ledger/integrity/FK equivalence, decryptability, authentic provenance and independent claim/completion evidence. Exercise interruption, missing keys/evidence and relevant I/O failures on fixtures first. Record actual capture point and restore duration.
- **Exit:** accepted recoverable backup and completed isolated rehearsal; keep custody/maintenance evidence current for migration.
- **Owner approval:** separate backup and real-data restore permits, exact destinations and data-handling/disposal terms. A backup includes existing private foundation data.
- **Prohibited next actions:** replacing the working database, repointing the app, restoring register history, migration, runtime activation or automatic retry/overwrite.

### Stage D — Working database migration

- **Entry:** O1–O8 satisfied; current schema/ledger and source remain valid under maintenance; verified backup/rehearsal accepted; separately reviewed working-only artifact/release.
- **Scope/implementation:** OP-05 explicit single-artifact migration package; genuine logging and postflight only. Preserve original guarded artifacts/manifest and all accepted contracts.
- **Acceptance tests:** new working edition passes frozen-contract/B parity and historical-data tests on fixtures; real execution must yield exactly expected schema/indexes/triggers/ledger and preserved old IDs/bytes, integrity/FKs and externally sealed result. Any DDL/log ambiguity quarantines.
- **Exit:** owner reviews a complete migration dossier; no inferential ledger repair.
- **Owner approval:** exact source/contract/tool hashes, command/arguments, host/target, window and incident boundaries; new single-use migration permit.
- **Prohibited next actions:** unfiltered discovery, three synthetic B migrations, automatic down/restore, new runtime services, new identity capture or provider/payment traffic.

### Stage E — Named local runtime activation

- **Entry:** accepted required schema and complete tests for the specific runtime capability; operational readiness/suspension/monitoring plan.
- **Scope/implementation:** OP-06 enables only listed components. This is activation of new capabilities, not repetition of historical B2. Keep fake adapters inert and production-eligibility disabled until the real feature/provider gates are met.
- **Acceptance tests:** readiness refuses missing keys/schema/clock/pending/audit conditions; tenant/auth/version checks and disable/resume behavior pass; no unexpected delivery/egress. Old auth's mail readiness must not be widened through an incidental config change.
- **Exit:** bounded local smoke/operational evidence accepted for the named feature only.
- **Owner approval:** separate activation permit and explicit old-service resumption policy.
- **Prohibited next actions:** real NIN/phone processing, live mail/SMS, provider access or financial enablement by virtue of schema/runtime success.

### Stage F — Merchant onboarding and separated dashboards

- **Entry:** feature-specific contracts/policies approved. **Isolated development can start before B–E** using foundation/C fixtures; connecting new workflows to working storage requires D/E as applicable.
- **Scope/implementation:** PD-01/PD-02 read views, then PD-03/PD-04 actual contact-proof, secure capture, submission/history and Chief decision services. Preserve minimal four-field policy and optional broader profile/documents. Keep merchant, Chief and Staff Admin access separate.
- **Acceptance tests:** two-tenant/revocation/role leakage, stale versions, immutable snapshots, exact proof consumption, pending-audit failure, concurrent decision winner and public/internal-feedback redaction; branded UI/DOM/accessibility and browser checks on isolated data.
- **Exit:** useful approved feature slice, with a separate real-data processing decision before real capture. Optional document upload is not a substitute for required contact proof and need not be built first.
- **Owner approval:** each feature implementation/runtime scope and distinct sensitive-data permission. Actual administrator provisioning is separately approved.
- **Prohibited next actions:** treating synthetic proof as real possession, self-approval, full-NIN exposure, mandatory optional documents without an evidenced rule, or enabling payments after Chief approval.

### Stage G — External provider sandbox

- **Entry:** official Selcom documentation, actual sandbox/access approval and protected test credentials; approved narrow product/fund/accounting contract. Each SMS/email/identity provider is a separate dependency/approval.
- **Scope/implementation:** PX-01/PX-02 one documented test collection flow with durable payment/accounting state, official callback/status/reconciliation semantics and isolated synthetic merchant/payer scenarios.
- **Acceptance tests:** provider-supported scenarios plus duplicate/reordered/forged callbacks, wrong amount/currency/merchant, idempotency, timeout/unknown outcomes, ledger/reconciliation and environment separation. No invented endpoint/header/status contract.
- **Exit:** reviewed sandbox dossier and unresolved provider/financial exceptions recorded.
- **Owner approval:** named provider/account/environment/actions/recipients/limits; possession of credentials grants no general access.
- **Prohibited next actions:** live credentials/funds/settlements, arbitrary recipients, unsupported provider methods or a second integration without its own approval.

### Stage H — Controlled pilot and live payments

- **Entry:** accepted G evidence; legal/commercial/data permissions, production hosting/secrets/access, financial reconciliation, monitoring, incident/support and measured recovery readiness for the actual pilot scope.
- **Scope/implementation:** PX-03 an explicitly bounded TZS-only merchant/product/value rollout, with accountable financial review and suspension/exception handling.
- **Acceptance tests:** authoritative end-to-end status/accounting/settlement matching, duplicate/recovery/failure controls, access limits/alerts and actual approved operational drills. No silent loss/duplication of externally committed obligations.
- **Exit:** owner accepts measured pilot evidence before any expansion.
- **Owner approval:** explicit financial authority and limits; refunds/payouts/new products/rails require separately named scopes.
- **Prohibited next actions:** non-TZS support, broad rollout, unapproved payouts, assumed regulatory certification or automatic promotion after a successful sandbox payment.

## 8. Parallel product development roadmap

All packages below are **PROPOSED**, with no dates or completion percentages. **S/M/H** estimate bounded engineering complexity, not elapsed delivery time; external waiting/staffing are separate. “Implementation approval” below permits only a future explicitly agreed source/fixture scope; working, data and provider actions still need individual approvals.

| Package | Objective and deliverables | Dependencies / scope | Complexity | Tests, exit and owner boundary |
|---|---|---|---|---|
| **OP-01** | Record minimum operating choices and authority/incident checklist. | Existing OSD/ORG; decisions only, no secrets/provisioning. | S; staffing-dependent | All O1/O2/O5/O6 decision fields for next action completed, independent roles and scope signed. Owner chooses; no execution follows. |
| **OP-02** | Produce one reviewed read-only working-target package and exact fixed invocation/dossier template. | OP-01 sufficient decisions; OAD/FRR reused. No new broad protocol/prototype. | M; native gaps may require a small focused proof | Pre-access denial, native identity/timeout failures and unchanged fixture inspection; exact code/policy hashes accepted. Owner separately approves implementation, then any real inspection. |
| **PD-01** | Merchant Dashboard v1: account/merchant selector, safe onboarding/readiness status and honest unavailable-feature states. | B3.1 source; isolated B1 fixtures only. Does not depend on Selcom or C working activation. Reuse branding/assets/current views. | M | Membership/role/revocation/two-tenant and no-leak tests, no write endpoints or invented balances, isolated visual/DOM acceptance. Owner approves product slice; working connection is separate. |
| **PD-02** | Distinct Chief/Staff Admin workspace shell and read-model contracts. | Agreed platform authority matrix; fake role fixtures, no real admin provisioning. | M | Merchant ownership alone cannot grant platform access; ordinary Admin cannot gain Chief final-decision ability; current-role revocation denies; masked case fixtures only. Owner approves capabilities and later provisioning separately. |
| **OP-03** | Bounded custody/backup tool: exact native engine/output identity, protected tool/staging, approved key/witness interfaces and engine snapshot verification. | OP-01; OP-02 pattern; RRT reused as specification/tests. Only specific O2/O4/O5/O6 gaps receive new focused tests. Actual capture needs fresh Stage B and C1 approval. | M–H; native feasibility uncertainty | API/new-file/identity/encryption/provenance/crash evidence; missing key/status/witness/fence stops. Accepted package is not a working backup permit. |
| **OP-04** | Restore-to-new-file verifier/run package and measured recovery dossier. | OP-03 tool; a completed approved backup required for real rehearsal. | M | Exact contents/schema/ledger/integrity/FK parity, source/backup preserved, independent completion and isolated destination; C2 permit only, no promotion/replacement. |
| **OP-05** | Separately versioned working C artifact/release plus exact migration execution ticket. | CAR/frozen contract; O4 write-compatible identity proof. Source preparation can overlap OP-03/04; actual execution waits for their accepted evidence. | M–H | Existing B/C parity/old-row/ledger-gap tests plus native write-boundary tests; exact inventory excludes synthetic migrations. Owner independently approves source and then Stage D execution. |
| **OP-06** | Named local feature activation/readiness and maintenance-resume package. | Required accepted schema, implemented named feature, O9 readiness tests. | M | Safe failure/disable, pending/key/clock/schema checks and no egress. Separate Stage E approval; no real-data/provider implication. |
| **PD-03** | Application-code contact/identity/proof runtime services with fake adapter injection, accepted R06 coordinator/reconciler and sensitive-input redaction. | SPEC/FOD; C fixtures and real-data/key policy interfaces. No `Tests\\` service registration or fixture-key promotion. | H; split contact service from secure capture | Existing contract cases plus runtime races, delivery ambiguity, expiry/version/revocation/pending readiness and no-secret-log tests. Owner authorizes each source slice; real processing/delivery remain separately gated. |
| **PD-04** | Merchant submission/history/reapply and Chief review actions, each as a small vertical slice. | PD-03; APP/TENANT, platform permissions and accepted minimum rules. | M per slice | Atomic snapshot/proof consumption/audit, CAS competing decisions, immutable history, redacted feedback and no payment enablement. Owner approves each workflow, real-data use and actual Chief provisioning separately. |
| **PX-01** | Official provider evidence/access pack: documented product, accounts, environments, auth, callback/status, fees, settlement and reconciliation responsibilities. | Owner/provider cooperation; can begin while UI and OP packages proceed. NIDA online integration only if selected/required. | S review; externally dependent | Official sources reviewed and unknowns recorded; authorized credential handoff/custody defined without publishing secrets. No API invocation until separately approved. |
| **PD-05** | TZS payment/order/attempt/accounting/reconciliation core with synthetic adapters and immutable financial facts. | PAYPLAN plus owner fund-flow/precision/fee contract; mapping refines against PX-01. | H; deliver initiation, effects and reconciliation separately | Idempotency, balanced entries under chosen book, exact amount/currency/tenant, correction/reversal and duplicate/unknown-state tests. No wallet custody/payouts inferred; new schema separately approved. |
| **PX-02** | One official sandbox vertical: payment link → checkout → initiation → callback/status → accounting/reconciliation. | PX-01, PD-05 and approved feature/access readiness; new working schema/routes need their own D/E tickets. | H; provider-dependent | Documented sandbox cases plus adversarial callback/retry/reconciliation evidence, environment/limit isolation. Stage G only; no real payment. |
| **PX-03** | Controlled live pilot operational acceptance. | Accepted sandbox, approved real-data/runtime/hosting/financial scopes and incident/recovery/support readiness. | H; operational/external | Stage H dossier, limits, reconciliation, alerts/drills and independent financial review. Explicit live authority required; rollout expansion remains prohibited. |

Practical priority within PD work: **read-only Merchant Dashboard → separate Chief/Staff shells → contact-proof/private capture service → minimum submission → Chief review/history → narrow payment collection and reconciliation**. Do not first build every analytics card, document upload, settlement screen, wallet, payout or multi-country feature. Reuse completed public/auth pages and native private-view seams; do not introduce another frontend framework or edit generated copies independently. [B2] [B31] [SPEC] [PAYPLAN]

Future payment-link/QR/history interfaces may be designed against explicit fixture contracts, but mock values must never appear as paid balances or provider-confirmed facts. Keep unimplemented actions disabled/unavailable with accurate explanations. Build authoritative transaction/read models before “live” charts. Staff Admin permissions are action-specific; a generic admin shell cannot inherit Chief decision authority. [PAYPLAN] [TENANT-TEST]

## 9. Owner decision register

All defaults below are **PROPOSED**. No real identity, destination, facility, provider entitlement or cost quote is selected here. Costs are qualitative because no procurement/hosting/provider quotes were reviewed.

| Decision | Recommended default | Alternatives / trade-off / cost implication | Consequence of deferral |
|---|---|---|---|
| Acceptance and target scope | Record acceptance/disposition of completed bounded B/C/RRT/SOB work and approve a single attended local C preparation profile. Preserve accepted product/security policies. | Broader unattended/production scope increases implementation and operational evidence. Administrative review cost; no reason to repeat unchanged historical suites now. | OP-02 may be drafted against explicit assumptions, but no real access/provisioning/execution may follow. |
| Operator/custodian/reviewer and authentication | Individual authenticated operator plus independent signer/evidence custodian, named alternate and emergency stop authority. | Owner may hold an independent custody role; operator self-signing is not the OSD separation. Two-person availability adds coordination/staff cost; managed identity is an optional later mechanism. | No working permit/independent witness authority; PD fixture work continues. |
| Signing/public trust/time/status/witness | One protected offline/attended custody workflow with verifier-pinned public trust, current revocation/suspension and independently held monotonic claim/completion checkpoint. | Hardware-backed signer or online witness service may improve controls/availability but add acquisition/integration/recurring cost. Facility must actually demonstrate separation/freshness; two logs under one admin do not qualify. | O2 stays blocked; another synthetic register cannot supply real custody. |
| Source/OneDrive and supported host profile | Prefer a separately authorized move to a validated local non-synchronized source location, with exact configuration/identity transition and recovery plan. Until approved, move nothing. | A proven controlled exclusion/fence may avoid relocation; pause-only status is insufficient. Migration/maintenance effort versus ongoing synchronization risk. No actual filesystem path chosen. | No safe working backup/migration claim while interference cannot be excluded. |
| Backup destinations, keys, access and retention | Private encrypted non-sync custody outside repo/webroot, independent key recovery and separate failure-domain copy; OSD's daily/weekly/pre-change retention proposal as decision input. | Offline removable custody versus approved managed storage: handling/key/availability versus recurring service fees and external data access. Choose actual paths, roles and key facility explicitly. | O5/O7 remain blocked; do not copy existing private data into generic temp/repository. |
| Maintenance and incident/resume authority | One bounded window, named writer owners, explicit admission/drain acknowledgements and default maintenance hold on ambiguity. | Repeated attended windows versus automated maintenance later. Downtime/staff effort; no invented time estimate. | No safe backup/migration; source preparation and isolated UI work continue. |
| Development recovery objectives | **RPO ≤24 hours and RTO ≤1 business day**, with verified pre-change backup; define calendar/support hours and measure before claiming achievement. | Tighter objectives cost more capture/copy/drill/staff capacity; looser objectives explicitly accept more loss/outage. These are not suitable financial-loss guarantees by default. | No objective is achieved by assertion; owner cannot assess acceptable recovery loss/outage. |
| Real identity/contact and optional evidence | Preserve accepted minimum/four-field/optional-profile rules; approve lawful purpose/notices/syntax/key/retention/access before real capture. Select online identity verification only with an evidenced need/arrangement. | Manual bounded review versus authorized verification provider affects assurance, workflow and provider cost. No legal-compliance opinion or NIDA entitlement inferred. | PD-03/04 stay fixture-only; no real NIN/OTP capture or submission eligibility. |
| First payment product/fund/fee model | Choose one officially supported TZS collection product with explicit accounting/reconciliation; omit platform wallet/payout scope unless specifically needed and authorized. | Direct-settlement/reporting versus platform-controlled obligations only if provider/legal approval supports it. Costs/fees/capital/safeguarding implications cannot be quoted or assumed from marketing's 5%. | PD-05 can explore contracts but cannot finalize financial behavior; PX-02/03 blocked. |
| Providers, hosting and live operations | Obtain official docs/test access first; keep public hosting/live rollout independently approved, limited and monitored. | Different provider/hosting choices affect entitlements, secrets, data flows, support and recurring fees. No production domain or provider account chosen in ORG. | Isolated product development continues; real delivery, external integration and payments do not. |

Do not reopen already accepted applicant eligibility or make optional documentation universally mandatory just because later service eligibility remains unresolved. New legal/provider requirements need authoritative evidence, affected phase/type/service and explicit owner disposition. [SPEC]

## 10. Next three recommended work packages

These are recommendations for **future explicit authorization**, not actions undertaken by ORG.

1. **OP-01 — Minimum decision/authority record.** Deliver a short completed decision register for the first attended local operation: named roles/host, trust/witness, source/sync/custody, window and incident/recovery rules. Accept when the next operation has no unresolved authority/custody placeholder and owner approval is recorded. No keys, path move, access or service change follows automatically. This resolves decisions rather than commissioning more fake infrastructure.
2. **OP-02 — Exact read-only preparation package.** Reuse the corrected authorizer and successful FRR sequence; prepare one source-pinned invocation with actual identity/helper failure bounds and a fixed permitted metadata list. Accept after focused fixture denial/preservation checks and review of exact command/inputs/output redaction. If a native supervision/identity defect prevents that scope, solve that defect only. A separate owner permit is still required to execute against the working target; backup/migration remain prohibited.
3. **PD-01 — Useful Merchant Dashboard v1, isolated.** Reuse account/selector/status, current policies and branding. Deliver a safe merchant home showing actual fixture-backed onboarding/readiness state and accurately unavailable future features. Accept with two-tenant/role/revocation/no-leak/no-write tests and isolated UI review. No new sensitive schema, real NINs, payment amounts, provider access or working runtime activation is needed to develop this slice. Chief/Staff workspace follows under PD-02 with separate platform permissions.

OP-01/02 and PD-01 can progress as independent delivery lanes once their scopes are approved; product development need not wait for a provider account, a new broad security framework or C working migration. The activation lane still cannot bypass O1–O8 or treat dashboard acceptance as an operational grant.

## 11. Risks and dependencies

| Risk/dependency | Practical consequence and response |
|---|---|
| Stale documents treated as current state | B1/B2 initial “not activated” text predates later activation; README's B3.2-not-started note predates B/C reports. Use chronology/source plus a future separately authorized fresh inspection. Edit no historical report in ORG. [B1] [B1-ACT] [FRR] [FOD] |
| Approval inferred from a report title or fixture signature | Wrong actor/target/action could execute. Record actual decisions and provision independent authority; no fixture promotion. [FOD] [OSD] [SOB] |
| C schema mistaken for product completion | Empty new tables do not deliver OTP, capture, submission or Chief workflow. Pair each later runtime slice with its real service/policy/readiness tests. [ROUTES] [VERIFY] |
| Copying existing private data under “synthetic” terminology | Foundation contains historically reported account data. Working backup/rehearsal requires real-data custody/access authorization even if the new C tables are empty. [FRR] |
| Audit compatibility and ambiguous migration completion | Historical eight-event working CHECK differs from sixteen-event C contract; source enum alone does not change storage. Require exact reviewed schema/ledger gate and quarantine gaps; no silent event downgrade/adoption. [FRR] [CAR] |
| Privileged filesystem/tool substitution or coordinated register rollback | Sampled hashes/local paired logs do not resist all privileged replacement. Enforce the supported native profile and independent custody or stop; do not claim a general sandbox. [SOB] |
| Product work becoming a demo-payment claim | Static balance/pricing/provider text and synthetic success are not money. Separate fixture/readiness presentation from authoritative provider/accounting facts and launch review. [FRONTEND] [PAYPLAN] |
| Unavailable staff/keys/witness or external documentation | Blocks only dependent operations. Continue isolated UI/contracts; no self-signing, register reset, guessed provider APIs or secret sharing as a workaround. [OSD] [PAYPLAN] |
| Production scope silently enters a local package | The current authentication gate deliberately disallows production/live mail. Public hosting/TLS, privileged roles, real data and financial services require separate packages and approvals. [READY] [OSD] |
| RPO/RTO asserted without a cold drill | A backup filename or process-crash pass is not recoverability or power-loss durability. Measure capture age, key availability, restoration, reconciliation and permitted service resumption. [RRT] [OSD] |

## 12. Evidence limitations and preservation

This was a source/non-secret-document review only. No tests, Artisan/PHP/Node application execution, dependency installation/audit, browser/HTTP inspection, database connection, migration, backup, restore, key generation, runtime activation or service/ACL/synchronization change occurred. No working SQLite/sidecar, `.env`, credential or real secret was opened, hashed, copied or modified. No external provider source/account was queried; official provider capabilities and any credentials outside the reviewed source remain unverified.

Historical test totals, visual results and current-runtime claims were not freshly re-executed. No reported test pass is relabeled as ORG execution. “NOT IMPLEMENTED” is bounded to the inspected project-owned routes/app/models/views/migration and test inventory; it is not a claim about undisclosed external projects, ignored archives, vendor frameworks or credentials elsewhere. The review does not certify legal/regulatory eligibility, physical storage durability, security against every privileged actor or a functioning live gateway.

A source-only SHA-256 baseline of **212 pre-existing source/document files** was retained in memory using a source-extension allowlist and excluding SQLite/database/sidecar, `.env`, credential/key/secret and protected metadata paths before hashing. Existing OSD/SOB documents and prototype additions were treated as pre-existing work. This is not an exhaustive inventory of ignored dependencies/cache/recovery trees or binary assets.

**VERIFIED final preservation:** all **212 baseline files unchanged**, zero changed/missing. Tracked and staged diffs are empty; the thirteen pre-existing untracked entries remain, with exactly one task addition: this report. Current HEAD is `d78797cf2cceb8d4c41b4fe375cee0f8cdff9975`. Repository and new-report whitespace checks passed; all **38 evidence reference targets** resolve and all **13 requested sections** are present. Git's existing LF/CRLF normalization notice is not a content change. No Git configuration or index write occurred. These are source/document checks, not test or working-target execution.

## 13. GO/NO-GO recommendation

**GO for owner review of ORG-1.0 and selection of OP-01, OP-02 and PD-01 as small next scopes.** Stop repetitive broad synthetic-only expansion. Retain accepted B security guarantees and regression assets; direct targeted engineering at a demonstrated missing boundary or an actual product feature.

**NO-GO for new working C access/backup/restore/migration/runtime activation under this task, and for real identity/contact processing, external provider execution or live payments.** Existing historical B1/auth activation is acknowledged without a new current-runtime claim or any action against it. Local C migration does not need every future financial/provider feature completed, but it does need its exact authority, identity, custody, quiescence, recoverability and release gates closed.

No roadmap work was implemented; no existing application/test/migration/manifest/frontend file was changed, no key/storage destination was selected and no commit/push/deploy occurred.

**WORKING ACTIVATION REMAINS NO-GO. STOP FOR OWNER REVIEW.** XLipa is a WEBSITE supporting TZS only.

[PH2]: PHASE-2-REPORT-2026-11-06.md
[B1]: PHASE-3-B0-B1-REPORT-2026-11-06.md
[B1-ACT]: LOCAL-DATABASE-ACTIVATION-REPORT-2026-11-06.md
[B2]: PHASE-3-B2-REPORT-2026-11-06.md
[B31]: XLIPA-PHASE-3-B3-1-IMPLEMENTATION-REPORT-2026-11-06.md
[SPEC]: XLIPA-PHASE-3-B3-2-A-FINAL-SPECIFICATION-2026-11-06.md
[FOD]: XLIPA-B3-2-B-FINAL-OWNER-ACCEPTANCE-DECISION-2026-11-07.md
[CAR]: XLIPA-B3-2-C-ISOLATED-ACTIVATION-REHEARSAL-2026-11-07.md
[WSR]: XLIPA-B3-2-C-WORKING-TARGET-SAFETY-AND-RECOVERY-PACKAGE-2026-11-07.md
[OSG]: XLIPA-B3-2-C-OFFLINE-SECURITY-GUARD-IMPLEMENTATION-REPORT-2026-11-07.md
[OAD]: XLIPA-B3-2-C-OFFLINE-SQLITE-AUTHORIZER-DIAGNOSIS-2026-11-07.md
[FRR]: XLIPA-B3-2-C-FRR-2-0-READ-ONLY-REVALIDATION-2026-11-07.md
[RRT]: XLIPA-B3-2-C-WORKING-RELEASE-AND-RECOVERY-TOOLING-2026-11-07.md
[OSD]: XLIPA-B3-2-C-OPERATIONAL-SAFETY-DESIGN-FINALIZATION-2026-11-07.md
[SOB]: XLIPA-B3-2-C-SYNTHETIC-OPERATIONAL-BOUNDARY-PROTOTYPE-2026-11-07.md
[PAYPLAN]: XLIPA-MASTER-ROADMAP-ADDENDUM-PAYMENTS-ANALYTICS-2026-11-06.md
[ROUTES]: backend/routes/web.php
[AUTH]: backend/app/Actions/Auth/RegisterMerchantOwner.php
[READY]: backend/app/Http/Middleware/EnsureAuthenticationReady.php
[TENANT]: backend/app/Http/Middleware/ResolveMerchantContext.php
[SUMMARY]: backend/app/Http/Controllers/MerchantOnboardingController.php
[MODELS]: backend/app/Models/Merchant.php
[APP]: backend/app/Models/MerchantApplication.php
[AUDIT]: backend/app/Models/AuditLog.php
[PLATFORM]: backend/app/Models/PlatformRoleAssignment.php
[AUTH-TEST]: backend/tests/Feature/AuthenticationTest.php
[TENANT-TEST]: backend/tests/Feature/MerchantAccessTest.php
[FOUNDATION-TEST]: backend/tests/Feature/DatabaseFoundationTest.php
[ACTIVATION]: backend/database/activation/b32c/2026_11_07_200000_activate_b32_security_contract.php
[MANIFEST]: backend/database/activation/b32c/selection-manifest.json
[VERIFY]: backend/tests/Support/SyntheticVerification.php
[RECONCILE]: backend/tests/Support/SyntheticAuditReconciler.php
[RRT-CODE]: backend/tests/Support/SyntheticRecoveryPackage.php
[SOB-CODE]: backend/tests/Support/SobBoundary.php
[FRONTEND]: frontend/xlipahomepage.html
[DEVELOPERS]: frontend/developers.html
[CONTACT]: frontend/contact.html
[BOOT]: backend/bootstrap/app.php
