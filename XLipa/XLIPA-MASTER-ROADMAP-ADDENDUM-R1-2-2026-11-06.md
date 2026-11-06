# XLipa — Master Roadmap Addendum R1.2

**Merchant Notifications, Withdrawal Documents and Automated Receipts**  
**Version R1.2 — 6 November 2026, Africa/Nairobi. DOCUMENTATION ONLY.**

## 1. Executive summary

**OWNER-APPROVED:** customer-payment notifications to merchants use email only, never per-payment SMS. Merchants can select individual emails, daily/weekly digests or disable optional payment emails. Withdrawals use both SMS and email, with optional status preferences subject to an approved mandatory-financial-notice policy. Merchants pay no per-notification SMS/email charge; XLipa bears approved messaging costs. Withdrawal requests generate a distinct request/advice document; only actual confirmed and reconciled payout generates a paid receipt.

**PROPOSED:** one event-driven notification service, durable delivery intents and private immutable document artifacts, tied to the authoritative payment/withdrawal/ledger facts already specified in R1.0/R1.1. Optional message preferences never delete history, suppress document generation or overrule mandatory security/legal communications. Delivery success is not financial success. This document creates no service, schema, queue, provider account, template, PDF or outbound message.

Labels: **VERIFIED** = current read-only evidence; **OWNER-APPROVED** = product/commercial direction in the R1.2 instruction, not implementation permission; **PROPOSED** = future design pending review; **NOT VERIFIED** = missing contract/implementation/legal evidence; **REQUIRES APPROVAL** = separate concrete execution/operational decision. All future mechanisms below are PROPOSED unless explicitly owner-approved.

## 2. Relationship to R1.0, R1.1 and current status

R1.2 supplements [R1.0 Payments & Analytics](XLIPA-MASTER-ROADMAP-ADDENDUM-PAYMENTS-ANALYTICS-2026-11-06.md) and [R1.1 QR, Buttons and Operations](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-1-2026-11-06.md); neither is overwritten or simplified. Its communication-channel policy refines their general notification requirements. Signed merchant webhooks remain a separate machine-to-machine integration, not merchant SMS or a replacement for required document email. R1.0 accounting, KPI, currency and reconciliation rules and R1.1 pricing/settlement/refund boundaries remain intact.

Reviewed alongside these: [Secure Architecture Review](XLIPA-B3-2-SECURE-ARCHITECTURE-REVIEW-2026-11-06.md) and [B3.2-A v2.0](XLIPA-PHASE-3-B3-2-A-FINAL-SPECIFICATION-2026-11-06.md). Preserve B3.1–B3.5 and F0–F5; the sub-gates below are review slices, not new automatic milestones or execution authority. Minimum onboarding policy remains four application values, exact email/phone ownership proof and separate internal approval/financial capability. Optional documents/payment methods do not become blanket initial-submission prerequisites because notifications need secure contacts.

**VERIFIED this task:** canonical `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`, Laravel `backend/`, authoritative public frontend `frontend/`, Git root parent `WEB-DEVELOPMENT`; branch main, HEAD `7c3e4743384e0ca0acd7b029c944569c84304541`. Initial status showed five pre-existing untracked documents: B3.2 planning, v2.0 specification, secure review, R1.0 and R1.1. No tracked modifications were reported; these documents are preserved as pre-existing work. Hash baseline covers 184 existing tracked/untracked files plus explicitly included `.env`/working SQLite (unique path count 184).

Existing documents record completed local B3.1 and native email verification, with production authentication intentionally gated. They record no implemented phone proof, live payout or messaging infrastructure. Historical 108 tests / 847 assertions and 31 frontend checks are not fresh results here. This task does not execute application/runtime/tests or repeat database integrity inspection. Email/SMS production delivery infrastructure, payout contracts and legal document classification remain **NOT VERIFIED**. Local `http://xlipa.test` remains unchanged; future production domain is not selected.

## 3. Owner-approved channel and event policy

| Business event | Merchant communication requirement | Authority / limits |
|---|---|---|
| Customer payment successful | Optional email or selected digest; **no SMS** | Approved provider-confirmed persisted payment fact, not browser success |
| Customer payment pending | Optional email/digest; **no SMS** | Real persisted pending transition, not invented completion evidence |
| Customer payment failed | Optional email/digest; **no SMS** | Validated persisted failure under provider/payment state contract |
| Customer payment refunded | Optional email/digest; **no SMS** | Approved confirmed refund fact where applicable, not merely refund request/approval |
| Withdrawal requested | SMS + email default financial profile; optional changes subject to mandatory matrix | Authorized committed request and reservation, not paid |
| Withdrawal approved | SMS + email as above | Recorded authorized decision, not provider confirmation |
| Withdrawal processing | SMS + email when operationally meaningful | Defined persisted processing transition; avoid repetitive unchanged-status spam |
| Withdrawal completed | SMS + email; distinct receipt workflow | Authoritative payout confirmation plus approved reconciliation |
| Withdrawal failed/rejected | SMS + email under approved event classification | Distinguish financial failure, ambiguous pending and administrative rejection |
| Important security event | Email and/or SMS according to separately approved risk policy | Password change, suspicious login, sensitive recovery, destination change or critical restriction are candidates; not every event automatically SMS |

Payment transport policy is enforced centrally for all customer-payment events, including QR, links, buttons and API checkout. No preference, template or Chief switch can enable per-payment SMS. OTP challenges and separately approved security/withdrawal messages are different purposes, not an exception allowing payment SMS. This policy concerns merchant communications; payer receipts/marketing are outside scope and not silently enabled.

Mandatory security and legally required notices cannot be disabled through optional transaction/marketing controls. Exact mandatory financial/security event matrix, channels, recipients, timing and failure fallback are **REQUIRES APPROVAL**; no law or compulsory SMS rule is asserted here.

## 4. Free notifications and platform cost controls

**OWNER-APPROVED commercial policy:** no merchant per-SMS/per-email notification charge. Track messaging expenditure in platform operational accounting only; do not deduct it from merchant available balance, withdrawal net, pricing commissions or per-notice surcharges. R1.1 pricing rules must not reintroduce a notification fee under another label. Provider costs/rates/contracts and monthly budget numbers are undecided.

Proposed cost facts: provider/channel/environment, message/attempt reference, template/event category, billable units where officially defined, estimated/actual cost and currency, billing period and reconciliation status. Mark unknown cost unknown; a retry or provider acceptance may be billable even without handset/mailbox delivery, depending on actual contract. Do not assume all SMS segments equal one message, all email failures free or costs from different currencies additive. Reconcile provider usage/invoices against delivery attempts, not merchant transaction ledgers.

Controls: approved monthly budget with soft/hard alerts, per-tenant/actor/recipient-purpose limits, global/channel concurrency, duplicate suppression, verified-target restrictions, queue size/age thresholds, bounded retries and provider spend circuit breakers. Payment digests reduce optional volume; optional messaging may be deferred/throttled under disclosed approved policy without changing financial facts. Prevent attackers creating cheap repeated state/no-op events to exhaust the SMS budget. Track counts and outcomes by event/channel/provider without exposing raw targets.

**Mandatory-message exception handling:** reserve approved operational capacity/budget for required categories; alert before exhaustion; prioritize required queues. If limits/outage threaten a mandatory notice, open an incident and escalate to a named on-call owner, with an approved verified alternative provider/channel or audited manual process. Never silently drop, relabel optional, mark delivered, charge merchants or send to an unverified target. Alternative channels must meet the specific approved requirement; email alone is not assumed sufficient for a mandated SMS. If required delivery readiness is unavailable, an approved launch/action policy may block new sensitive initiation while preserving already-committed financial records and continuing necessary reconciliation. No messaging failure rolls back or fabricates money movement. Exact escalation deadlines/fallback authorities need approval.

## 5. Merchant Notification Center

Location proposal: **Settings → Notifications**, extending existing Notifications/Security and Account Settings, not a second preference system. Default proposed control is active verified owner-only preference/contact management with recent reauth for destination changes; manager/delegated user scopes require explicit approval. Staff status access does not confer financial notification history or settings access. Server checks merchant membership/account/auth_version freshly; preference actor/tenant IDs are not client authority.

Controls: per supported payment event select one mutually exclusive delivery mode: individual email, daily digest, weekly digest or optional off. Configure withdrawal optional status SMS/email separately, constrained by a locked mandatory matrix; default both channels enabled. Show unavailable/mandatory channels clearly. Settings show which document/security/legal communications follow independent policies, so “optional payment emails off” never implies every email stops. Marketing consent is separate and cannot be bundled into transactional delivery.

Preference changes are versioned, effective-dated, audited with bounded codes and optimistic concurrency; record requester and safe previous/new modes, not raw recipients. Proposed rule: optional preferences at event eligibility determine intended mode, with a fresh pre-send suppression check. Opt-out cancels unsent optional intents/digest memberships; enabling does not replay all historic events automatically. Mode changes must not produce both individual and digest delivery for the same event. Explicit history/reissue requests remain separately authorized. Approve default modes and change semantics before implementation.

History allowlist: event type, authorized payment/withdrawal reference, channel, creation/scheduled/attempt times, delivery/retry status, masked recipient, template version and safe error category. Separate suppressed-by-preference, waiting, attempting, provider-accepted, delivered-if-evidenced, bounced/failed, unknown and dead-letter states. No raw provider credentials/body/recipient or other merchant history. Dashboard transaction/withdrawal history remains authoritative regardless of communication preference. In-app history is a companion, not a replacement for approved mandatory SMS/email.

## 6. Payment email notifications and digests

Individual emails derive from approved persisted transitions for successful/pending/failed/refunded customer payments. A transition has stable event/entity version; a repeated callback does not create a second meaningful pending/failure notice. Distinguish current state from historical event: delayed pending email must not imply a now-confirmed payment is still pending. Proposed coalescing suppresses superseded unsent optional pending notices and records that reason; otherwise clearly labels event time and current status. Financial events/history remain unchanged.

Daily/weekly digest proposal: one configured timezone (initial proposed `Africa/Dar_es_Salaam`), local calendar daily windows and owner-approved week start/send time; UTC storage and `[start,end)` boundaries. No hardcoded guessed timezone from phone/IP. Use unique merchant/recipient-scope/cadence/window keys, a manifest of included event IDs and a reproducible `as_of`/metric policy version. Late events appear in a labelled later correction section or an approved revised manifest, never silently omitted or double-counted. Repeated scheduler jobs resume the same manifest/intent.

Digest includes eligible event counts, currency-separated amounts, known/unknown fee status, safe references and authorized dashboard links. Do not sum pending/failed attempts as confirmed revenue or subtract the same refund twice. Avoid adding repeated lifecycle events as distinct successful transactions. Apply R1.0 KPI/rounding/cutoff definitions and state clearly whether it reports period events or a cutoff snapshot. Email is a summary, not a new financial statement of record. Bound digest size and link to authorized history for detail; no raw payer identities/NIN/accounts/secrets. Proposed skip-empty-digest default requires owner approval; records remain available.

Per-recipient/event eligibility is assigned to individual or one digest mode, never both. Boundary/timezone/mode-change/late-arrival tests are prerequisites. Optional off stops optional emails while payments/dashboard/webhooks remain intact. Customer-payment SMS stays unavailable even for “urgent” or large-value payments; any distinct critical security event requires its own approved policy, not a renamed payment SMS.

## 7. Withdrawal SMS and email notifications

Default future withdrawal profile sends **both** SMS and email for requested, approved, meaningful processing, completed and failed/rejected transitions. Optional status controls can suppress only events/channels the approved mandatory matrix permits; absent that approved matrix, do not silently permit opt-out of required financial notices. The distinction must be resolved before live withdrawal messaging, not guessed in code.

Recipient proposal: current authorized withdrawal owner/requester with a current verified identity email and separately verified purpose-appropriate phone contact. Merchant notification phone may derive from the approved contact architecture only through an explicitly authorized binding/reuse policy; onboarding proof is not automatically general-purpose SMS authorization. Optional business contacts, bank/mobile-money destination and another member's phone are not automatically verified notification targets. Multi-owner recipient selection and delegates need explicit scope policy; no automatic broadcasts to all members.

SMS uses a concise truthful status and safe opaque withdrawal reference; amount/currency only if privacy-approved. Never full bank/wallet number, raw NIDA, sensitive identity, OTP/auth token, API secret or security credential. Email can include approved amount/fee/net/masked destination, event time and safe authenticated document/history links. Avoid public token download links or login codes in status messages. Processing is not completed; rejected is not provider-refunded; provider acceptance is not paid.

Each channel has its own delivery intent/status/retry and cost trail; SMS success must not mark email delivered or vice versa. Outage/ambiguous acknowledgement uses safe retry/provider idempotency and escalation. Notification failure does not modify withdrawal balance/reservations/payment evidence. Contact change/revocation triggers fresh target eligibility checks; do not redirect old financial details automatically to an unverified replacement. Reassignment/reissue requires an audited approved policy and current permission.

## 8. Withdrawal Request / Payout Advice

**OWNER-APPROVED:** after an authorized withdrawal request is committed, generate a private PDF provisionally titled **Withdrawal Request / Payout Advice** and make it available in Withdrawal History. It is a request-stage document, **not proof of payment**. “Invoice,” legal/accounting classification, numbering requirements and disclaimers require professional review.

Content: XLipa branding, unique document reference, merchant business name, withdrawal reference, requested gross amount/currency, applicable fees with known/estimated/unknown status, expected net amount if determinable, masked approved destination, request timestamp/timezone, and request status **as of issuance**. Prominent wording: payment has not yet been confirmed. Never invent fees or a definite net when unresolved; show pending calculation and later version under approved policy. Currency/minor-unit and frozen R1.1 pricing references keep figures reproducible.

Generate from immutable committed request/version facts, not browser payload or transient approval state. Unique withdrawal + document type + issuance version prevents duplicate advice on retries. PDF bytes have checksum, template/policy version, generated/issued times and private storage reference. Request advice remains its original historical snapshot as status changes; current history shows the latest live status separately. An amended advice has its own explicit revision linked to the original, not overwritten bytes or a paid label added to the request document.

PDF generation is bounded, escapes input, permits no remote resource fetch or arbitrary HTML, embeds only approved branding resources and contains no unnecessary payer/KYC/account details. Document rendering failure creates a retryable artifact job/alert without rolling back an already valid request or falsely marking it paid. Readiness policy must decide whether generation infrastructure is a prerequisite for accepting new withdrawals. No PDF renderer/library is selected or installed here.

Send to verified email under the proposed financial-document policy in section 10; authenticated PDF download remains available independent of optional notification settings. Failed email does not remove the advice. No real request/document is generated in this task.

## 9. Withdrawal Receipt / Payout Confirmation

**OWNER-APPROVED:** a distinct receipt is issued only after actual paid withdrawal is established using authoritative provider evidence **and approved reconciliation**, not Chief approval, payout initiation, HTTP success/acceptance, scheduler run, browser display or notification delivery.

Receipt fields: unique immutable receipt number, original withdrawal reference, merchant business name, confirmed payout amount/currency, applicable actual fees, net paid amount with clear component definitions, masked destination, confirmed-payment timestamp/timezone, safe authorized provider reference where available and clear confirmed status. If any required component/evidence is unresolved, keep issuance pending for reconciliation; never substitute an estimate while claiming a final paid receipt. Number sequence/legal gap requirements and timestamp semantics require professional approval; no statutory classification or sequential numbering scheme is invented.

Proposed durable issuance: confirmed financial transition + immutable evidence/journal references + receipt issuance intent commit atomically; reserve a unique issuance identity/number once according to the approved numbering policy. Renderer uses that frozen payload and template version. Concurrent callbacks/workers hit the same uniqueness guard; retry recovers the same logical artifact/number. Atomically publish the stored checksum/version metadata so interrupted rendering/upload cannot produce two official receipts. Private orphan-object reconciliation handles DB/object-store non-atomicity.

Email delivery intent references the existing receipt ID; a bounce/worker retry does not issue a fresh number or recreate financial evidence. On later authorized redelivery, preserve original receipt and record a reissue/delivery event, not a second payout. Original bytes/evidence stay immutable. Payout reversal/correction creates a separately identified linked notice/adjustment under approved policy; it never edits the original receipt to hide the original event.

Partial payouts need approved allocation and receipt policy. Proposed default: a final full-withdrawal receipt requires full eligible completion; partial confirmed allocations remain explicit in history and may receive distinct clearly labelled partial-payment documents only after that policy is approved. Do not call an entire request completed because one portion arrived. Multiple actual payouts/duplicate-payment exceptions are reconciled, not hidden by receipt uniqueness.

Automatic verified-email delivery and authorized PDF download follow section 10. Receipt pending/rendering/delivery statuses remain separate from paid financial status: a paid withdrawal with a document outage is still paid, with a visible operational exception to fix. Notifications cannot assert a final document exists before publication.

## 10. Documents versus notification preferences

| Class | Preference behavior | Proposed delivery boundary |
|---|---|---|
| Optional payment/withdrawal status messages | Respect event/channel/mode opt-out and pre-send suppression | Payment email only; optional withdrawal channels per approved matrix |
| Financial documents | Generate/retain independently of optional email/SMS preferences | Advice and confirmed receipt have separate artifact and delivery obligations |
| Mandatory security communications | Locked policy; optional settings cannot disable | Approved risk-based channels/recipients/fallback only |
| Legally required notices | Verified requirement/policy overrides optional controls | Applicable scope, timing, delivery evidence and fallback require legal review |

**PROPOSED owner/legal decision:** always initiate verified-email delivery of both request advice and paid receipt as a distinct financial-document communication, even when optional transaction emails are off. Default to an email containing an authenticated document-center link rather than a public link or sensitive attachment; confirm whether “send document” requires attached PDF for a particular lawful purpose. Receipt/advice remains downloadable after fresh authentication. Email includes clear purpose and distinguishes document delivery from optional status emails; no marketing.

This proposal follows the owner's automatic email requirement but is **not a claim that every document email is legally mandatory**. Owner/legal review must ratify always-email policy, permitted recipient and attachment/link method, acceptable delivery evidence and fallback. An alternative secure-retrieval-only mode when optional email is off would require explicit owner amendment/approval and confirmation of applicable obligations; do not silently adopt it. Until the distinction is approved, live document delivery is not ready.

For completed withdrawals, coalesce an optional completion email with the required receipt email when policy permits, preserving each obligation's audit reference without duplicate near-identical messages. Required SMS remains independently evaluated. Missing/unverified email, permanent bounce or unavailable provider creates an exception/escalation, not preference suppression or a fabricated delivered status. Safe authenticated retrieval is a fallback aid, not assumed legal delivery equivalence. Generation and retention remain unaffected.

## 11. Event-driven architecture and consistency

### Event and recipient model

Business transaction commits stable event ID, type/version, merchant ID, entity/version, effective/received UTC timestamps and safe reference plus outbox intent. Payment success/refund events require approved financial evidence; pending/failed events require legitimate persisted states. Withdrawal events derive from the authorized lifecycle. Notification processing never accepts a browser success flag, client tenant/recipient ID or arbitrary free-text SMS command as business truth.

Dispatcher validates tenant/actor/resource association, recipient current verification/contact version/purpose, category channel policy and preference version, then creates a unique delivery intent scoped to event + recipient binding + channel + template purpose. Digest membership and document delivery have their own stable keys. Store decision snapshot (policy/preference/template versions and suppression reason) plus current checks, not raw PII in ordinary jobs/cache keys. Recipient target is encrypted/restricted where necessary and masked in ordinary UI. Binding to a specific verified address/version prevents retries silently moving historic details to a different recipient.

### Durable sends and failure handling

Proposed records/concepts, **not migrations:** immutable business events/outbox; versioned notification preferences; category/channel policy; digest runs/event manifests; delivery intents; delivery attempts with provider correlation/ack/status; template versions; document issuance/artifact metadata; notification cost aggregates and incidents. All use exact tenant/resource associations, server actor IDs, reviewed composite constraints/uniqueness and indexes for due/status/merchant/time. Do not introduce arbitrary polymorphic IDs that bypass tenant FKs. Existing audit CHECK/enum must be extended only by a separately reviewed migration if necessary.

Claim work with fencing/version tokens; call provider outside database-held locks. Record each bounded attempt and provider-acknowledged status distinctly from actual delivered evidence. Provider callbacks must be authenticated under the official contract, tenant/binding checked, deduplicated and applied without status regression. No invented SMS/email endpoint, delivery webhook or cost code. Email open tracking is not proof of financial notice receipt and is not enabled by default.

Retries use approved exponential backoff/jitter, age/attempt limits, channel/provider error classification and dead-letter/incident handling. Ambiguous send timeout cannot be blindly resent as known failure: use official idempotency/status support if available, else an approved delayed resolution/possible-duplicate policy. Network delivery cannot promise exactly once. Internal unique intents and stable provider keys where supported suppress avoidable duplicates across callback replay/worker restart. Required-notice retries/escalation must meet approved obligations, not vanish after generic retry exhaustion.

Reconciliation jobs compare eligible committed events with intent/digest/document manifests, then attempts with provider status and costs; repair missing work idempotently, respect optional opt-outs and escalate mandatory gaps. Financial-to-document reconciliation finds confirmed payouts lacking an issued receipt, duplicate identifiers, mismatched amount/currency or missing private object/checksum. It may queue regeneration of an unissued artifact; it cannot fabricate provider evidence or post money to make documents match.

**Invariant:** notification/provider-ack/delivery/rendering failure changes only communication/operational status. It does not roll back a real payment, mark a withdrawal complete, release uncertain payout reservations, alter ledger balances or modify original financial evidence. After-commit outbox/retry provides recovery. Optional preferences do not affect merchant webhooks or digital fulfillment authority.

## 12. Security, privacy and recovery

Fresh tenant/member and separate platform policies apply to settings, history, background jobs, document generation/download and operations. Default owner-only financial recipient/settings actions; delegate permissions need explicit matrix approval. Sensitive contact/destination/recovery changes require recent reauth, independent verification and notification under approved policy. Do not treat an account-recovery target as verified merely because a recovery request names it; notify an established safe contact or escalate under the separately approved recovery protocol. Avoid leaking sensitive details to a possibly compromised contact.

Document storage is private, encrypted/access-controlled with separately recoverable keys. Downloads reauthorize user/account/auth_version/membership/resource/document version on each request, use short-lived authenticated access and audit purpose; no permanent public symlink or bearer-only public URL bypass. Revoked users cannot download through a previously issued link. PDF responses use attachment/no-store/nosniff; filenames/URLs omit bank identifiers/NIN. Safe email links use configured canonical origin and named references; reject open redirects. Production requires approved HTTPS/domain/host configuration; local environment remains untouched.

No raw NIDA/NIN, OTP, API/signing secret, password/reset URL or unnecessary payer identity in notifications, audit, logs, provider error text or templates. OTP content belongs only to its separately authorized challenge transport and never logs; status messages cannot reuse that template to bypass restrictions. Provider credentials live in approved secret management, not message payloads. Escape template fields, enforce approved template/version/locale and bounded subject/SMS length; prohibit unreviewed remote template code/tracking/third-party analytics.

Approve purposes/lawful basis, recipient notice/consent where applicable, processors/data locations/transfers, provider security/subprocessors, retention/holds and rights workflows before real messaging. No legal classification or retention duration is invented. Separate short-lived delivery payloads/diagnostics from financial document/evidence retention; minimize recipient data, retain only justified metadata and protected evidence, reconcile deletion with holds/backups. Controlled document corrections preserve original evidence where required, not indefinite retention by default. Backup/restore rehearsals must preserve issued numbers, deduplication keys, document hashes and contact/key version binding, preventing restore/replay from duplicating receipts or messages. APP_KEY replacement is not recovery.

## 13. Chief Admin Notification Operations

Extend existing platform operational permissions with **Notification Operations**, not unrestricted merchant-message access. Separate ability to inspect redacted metadata, approve templates, manage provider configuration, handle incidents or authorize fallback; no generic Chief decrypt/view-all body privilege. Fresh active assignment/account/version, privileged reauth/MFA readiness and approved delegation apply. No Chief account or endpoint is created now.

Operational views: SMS/email volumes by event/provider/period/environment; accepted/delivered/bounced/failed/unknown/dead-letter counts; queue age/health/retry backlog; provider outage status; estimated/actual platform messaging costs by currency; monthly budget and reserved mandatory capacity; security/legal notice failures; document issuance/delivery gaps. Distinguish provider accepted from delivered and unknown costs from zero. Avoid misleading delivery-rate denominator (e.g. count attempts separately from unique notices and show cohort/cutoff).

Template governance: draft, review, approval, version/effective date, safe test preview with synthetic values, locked channel/category rules, branding/accessibility/localization and audit. Emergency rollback selects a future approved version; already sent messages/history remain intact. Provider configuration changes use secret references, reviewed contract/rate mapping, test-only readiness, limits, rotation and audited activation. A fallback cannot bypass verification or inject payment SMS. Content access for a specific incident needs purpose-specific exceptional approval and redaction, not ordinary dashboards.

Failure alerts route to authorized operators with safe reference, category/severity and next action, no raw message/recipient. Incident runbooks cover provider outage, budget exhaustion, queue stall, credential compromise, template error, contact compromise and missing receipt. Track resolution without fake delivered status. Financial Document Audit can verify issuance number/evidence/hash/version/delivery trail under restricted scope without exporting all PDFs or NIDA.

## 14. Dashboard integration and responsibilities

No R1.0/R1.1 module is removed and no duplicate financial ledger/history is introduced. Preserve XLipa premium brand, responsive navigation, accessible status/errors and future browser acceptance. No frontend edits now.

| Area | Proposed location / responsibility |
|---|---|
| Merchant Notifications | Existing Notifications module: authorized status/history inbox, not financial truth or message-content archive |
| Notification Settings | Settings → Notifications: optional payment modes, withdrawal controls bounded by mandatory policy, recipient verification status |
| Notification History | Notifications → History: masked per-channel/retry/error metadata and safe resource links |
| Withdrawals / Withdrawal History | Finance → Withdrawals: actual lifecycle, amounts/reservations/evidence and independently labelled notification/document status |
| Withdrawal Documents | Within authorized Withdrawal History detail: request advice, paid receipt and explicitly linked revisions/adjustments; not a separate payout engine |
| Reports and Statements | Existing Insights module: same PDF/XLSX/CSV financial totals; document references/notification status only as permitted, never new fee/balance sources |
| Chief Notification Operations | Operations: governance, costs/budgets/templates/incidents and redacted volume metrics |
| Chief Delivery Monitoring | Notification Operations subview: queue/provider/retry/mandatory failure health |
| Chief Messaging Costs | Notification Operations cost subview: platform spend and invoice reconciliation, no merchant notification charges |
| Chief Financial Document Audit | Restricted audit/finance workspace: issuance/evidence/hash/delivery exceptions |
| Chief Withdrawal Operations | Existing Finance/Settlements operations: authorization/provider/reconciliation facts; messaging panels do not confer execution powers |

Desktop uses grouped navigation; mobile uses contextual disclosures/tabs instead of new crowded top-level entries. Hidden/disabled navigation is not server authorization. Unsupported future services must not appear live or show fabricated sent/paid/zero-cost values.

## 15. Implementation gates and dependencies

Preserve B3.1–B3.5, B3.2-B/C/D and R1.0 F0–F5. Every slice below needs explicit owner approval of concrete scope/diff/acceptance. Schema preparation, working activation, provider activation and financial live permissions are separate. Email infrastructure can be built/tested with synthetic events/fake transports without making real payments or payouts operational.

| Proposed slice / order | Dependencies and required approvals | Exit evidence / blocked operations |
|---|---|---|
| Policy specification — before sends | Ratify channel matrix, mandatory security/financial/legal rules, verified recipient/purpose reuse, optional modes/defaults, always-email documents, fallback/budget/retention owners | Approved versioned policy, template classification and safe error/fallback matrix; no sending authorization |
| B3.2 contact foundations | Separately approved identity/contact/OTP schema/privacy/key subset and C/D activation/implementation as authorized | Exact recipient/contact proof/version validation; no business_phone inferred verified; notification tables not automatically included in B3.2-B |
| F0-N communication operations specification | Approved SMS/email contracts/security/cost/budget, legal privacy review, production auth/HTTPS and delivery domains when needed | Provider capabilities/idempotency/status/fallback verified; actual rates/credentials not invented |
| F1-N durable notification foundation | Reviewed outbox/event/preference/intent/template schema and synthetic job tests; separate concrete migration/activation approval | Atomic event-intent, channel restriction, retry/recovery/tenant tests; fake transports cannot authorize real sends |
| F4-N center/digests/operations reads | Reliable event facts, contact permissions, consent/preferences, queue/timezone/cutoff/budget policies; F1-N | Settings/history/digests/cost views with access/rounding/volume reconciliation; no live financial claim |
| F4-D secure document rendering/read foundation | Approved document classification/fields/numbering/private storage/renderer/resources/key recovery | Synthetic visibly test-only advice/receipt render and authorization/retry tests; never issue a real paid receipt from a test fixture |
| F5-W withdrawal messages and request advice | Actual approved withdrawal lifecycle/reservations, financial permission/official provider contract, verified targets, F1-N and document readiness | Request-stage advice plus both-channel policy, failure escalation and no paid assertion from request/approval |
| F5-R confirmed payout receipts | Authoritative payout evidence, approved ledger/provider reconciliation and immutable final financial components; document uniqueness/recovery | One official receipt per approved issuance identity, same number on redelivery, partial/reversal policy; blocked without financial infrastructure |
| Security-notice activation | Separately approved risk/legal/category channel/recipient/recovery policy and readiness/mandatory fallback | Optional opt-out cannot disable required notices; no blanket SMS assumption |
| Live messaging / launch gate | Explicit real-email/SMS activation, verified domains/recipients, provider/privacy/budget/runbooks and monitored queues; relevant B3.5/F/financial gates | Service-by-service sign-off, no real send or payout authorized by documentation/test success |

Financial launch must include all mandatory communication/document capabilities applicable to the service. Missing mandatory infrastructure can block launch/new sensitive initiation under approved policy, but does not allow inventing financial failure/completion or suppressing reconciliation for money already moved. Optional documents in onboarding remain optional; withdrawal documents arise only in separately approved financial operations.

## 16. Acceptance criteria for later implementation

All criteria below are **PROPOSED future tests, not executed results**. Isolated synthetic fixtures/fake transports only; working SQLite/real NIDA/users/live money are not test shortcuts.

| Requirement | Minimum acceptance evidence |
|---|---|
| Payment email only | Successful/pending/failed/confirmed-refund events across QR/link/button/API yield eligible email/digest or suppression; SMS intents always absent, even on admin/template override attempt |
| Optional preference changes | Per-event mode/off, concurrent changes, pre-send opt-out and mode switch produce no individual+digest duplicate; records/dashboard unchanged |
| Daily/weekly digests | Timezone/window/week-start boundaries, cutoff, late events, retry/restart, unknown fees/currency separation and empty-policy cases; stable manifest totals |
| Withdrawal SMS + email | Default both channels for valid lifecycle events; optional controls obey mandatory matrix; one-channel failure does not mark other delivered or withdrawal paid |
| Verified recipients | Changed/revoked/unverified phone/email and wrong purpose/merchant bindings fail safely; multi-owner selection/delegation follows explicit policy |
| Duplicate event/worker | Callback replay and concurrent dispatcher/worker create one logical intent/document issuance; ambiguous provider acknowledgement never becomes guaranteed delivery |
| Failed delivery | Backoff/rate limits/dead-letter/incident/fallback and later recovery; no financial-state mutation or silent mandatory suppression |
| Request advice | Committed request only; unique reference, truthful as-issued status/disclaimer, known/unknown fee/net handling, private authorized PDF, rendering retry |
| Paid receipt gate | Chief approval/HTTP acceptance/scheduler/browser/SMS/email delivery cannot issue paid receipt; actual evidence plus reconciliation required |
| Receipt idempotency | Duplicate confirmation/concurrent renderer/crash/email retry retains one receipt number and original evidence/hash; correction/reversal creates linked artifact, not overwrite |
| Financial amounts | Same currency/pricing/fee/net definitions as immutable payout/ledger; partial payout and late fee resolution cannot falsely label full completion |
| Cross-tenant privacy | Forged preference/history/document/entity IDs denied; revoked users and expired links blocked; messages/logs/templates/errors omit NIN/secrets/full bank details |
| Mandatory notices | Optional-off and exhausted optional budget cannot suppress required category; approved fallback/escalation exercises and recipient-compromise recovery |
| Free commercial policy | No merchant fee/balance deduction; platform cost known/unknown/invoice/multi-currency and attempt-versus-message metrics reconcile |
| Operational/read readiness | Queue lag/outage/alert deduplication, least-privilege metadata/template/provider operations, no blanket body access |
| Recovery/regression | Restore/replay preserves issuance/idempotency/contact versions; existing auth/host/B3.1 and later finance tests pass; responsive/accessibility acceptance in approved UI stage |

## 17. Outstanding owner, provider and legal decisions

**REQUIRES APPROVAL:** default per-event optional email modes; digest timezone/week start/send time/empty/late/correction rules; manager/delegate/multi-owner rights; exact withdrawal mandatory matrix and which optional channels may be disabled; authoritative verified financial-notification recipients/contact-proof reuse; always-email advice/receipt proposal, attachment versus authenticated-link delivery and any secure-retrieval-only amendment; document numbering/revisions/partial payout/reversal policy; document/readiness failure handling; mandatory security categories and compromised-contact recovery; budget/reserved capacity/escalation/fallback deadlines/operators; retry limits, template/locale governance, retention and key recovery.

**NOT VERIFIED:** official messaging provider rates/contracts/idempotency/delivery evidence/security; production email/SMS readiness; official payout evidence/status/refund/settlement contracts; document legal/accounting classification (“invoice” not presumed), legally required communication channels/timing and acceptable delivery evidence; applicable privacy/consent/processors/transfer/retention obligations. Obtain official terms and qualified professional review before actual processing; no legal certification, fee schedule or external API is fabricated here.

The owner requirement that merchants pay no per-message charge is settled product policy; provider price/budget uncertainty must not become an undisclosed merchant surcharge. Distinguish ratifying this roadmap from authorizing actual messages or real financial documents.

## 18. GO / NO-GO, preservation and stop

**GO:** R1.2 is ready for owner review as a documentation-only supplement to unchanged R1.0/R1.1. Communication/document distinction and always-email proposal are explicit approval decisions, not silently implemented policy.

**B3.2-B readiness is unchanged:** conditional recommendation for a precisely scoped separately approved identity/contact/schema-preparation stage after outstanding secure architecture decisions are resolved. R1.2 does not authorize notification/financial tables, working activation or expand that scope. All previously listed NIN/crypto/privacy/contact/OTP decisions remain.

**NO-GO:** automatic implementation, schema/migration execution, real email/SMS, payout/receipt issuance, provider integration, accounts, dependency installation, code/frontend/environment changes, commit/push or deployment. Only this requested Markdown file is added. Existing 184-file baseline including R1.0/R1.1, secure review, v2.0, tracked backend/frontend/tests/historical documents, `.env` and working SQLite is hash-checked for preservation before delivery; HEAD remains the existing checkpoint. No runtime/tests/database writes are performed. Pre-existing five untracked documents remain separate from this task's one addition.

**STOP — await explicit owner approval before the next concrete stage.**
