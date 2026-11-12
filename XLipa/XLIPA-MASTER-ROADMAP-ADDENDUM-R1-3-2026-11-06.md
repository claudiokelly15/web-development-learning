# XLipa — Master Roadmap Addendum R1.3

**Premium Merchant and Chief Admin Dashboards, Analytics, Settings and Account Management**  
**Version R1.3 — 6 November 2026, Africa/Nairobi. DOCUMENTATION ONLY; NO IMPLEMENTATION AUTHORIZED.**

## 1. Executive summary

**OWNER-APPROVED direction:** a clean, premium, accessible browser-based fintech experience for small and enterprise merchants; financial clarity before chart density; separate Merchant and Chief Admin workspaces; high-priority traffic-source/method analytics, team management, account/security settings, Kiswahili/English and responsive desktop/tablet/mobile behavior. Ordinary merchant login 2FA may be configurable under an approved risk policy, while sensitive actions retain mandatory stronger verification. Privileged platform operators require strong MFA.

**PROPOSED:** four to six primary cards, one overview revenue chart, grouped navigation and contextual detail views; precise versioned read models and granular permissions before UI implementation. No invented balances, source certainty, provider rails or completed services. Financial document minimization includes an explicitly pending amendment to R1.2 automatic request-advice PDFs; confirmed payout receipts remain automatic, immutable and idempotent after authoritative evidence and reconciliation.

Labels: **VERIFIED** = current read-only source/Git evidence; **OWNER-APPROVED** = product direction in the R1.3 instruction, not a code/schema permission; **PROPOSED** = future technical/UI policy pending review; **REQUIRES APPROVAL** = separate concrete decision/execution gate; **NOT VERIFIED** = missing implementation/contract/professional validation. Unless explicitly stated otherwise, all dashboard/control behavior below is PROPOSED, not implemented.

## 2. Relationship to R1.0–R1.2 and inspected foundation

This supplements [R1.0 Payments & Analytics](XLIPA-MASTER-ROADMAP-ADDENDUM-PAYMENTS-ANALYTICS-2026-11-06.md), [R1.1 QR/Buttons/Operations](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-1-2026-11-06.md) and [R1.2 Notifications/Documents](XLIPA-MASTER-ROADMAP-ADDENDUM-R1-2-2026-11-06.md); it does not replace them. Preserve [B3.2-A v2.0](XLIPA-PHASE-3-B3-2-A-FINAL-SPECIFICATION-2026-11-06.md), [Secure Architecture Review](XLIPA-B3-2-SECURE-ARCHITECTURE-REVIEW-2026-11-06.md), Stage A and detailed B3 architecture. Existing B3.1–B3.5 and F0–F5 gate structure stays unchanged. Section 13 is the only identified proposed behavioral amendment, and cannot silently take effect.

**VERIFIED this task:** canonical project `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`; Laravel `backend/`; authoritative public frontend `frontend/`; Git root parent `WEB-DEVELOPMENT`. Branch main, HEAD `7c3e4743384e0ca0acd7b029c944569c84304541`. Initial Git status: six pre-existing untracked planning/architecture/addendum documents (B3.2 planning, v2.0, secure review, R1.0, R1.1, R1.2), no tracked changes reported. Preservation baseline: 185 unique existing files, including tracked files across the parent repository, existing untracked documents, `.env` and working SQLite. No unrelated edits are attributed to this task.

Actual source reviewed includes MerchantPolicy, account selector and onboarding view plus historical Stage A/B3 dashboard/role references. Current policy provides fresh active verified-user/member summary access; current views are account selector and read-only onboarding status, not implemented financial dashboards. Existing owner/manager/staff foundations remain; platform roles are separate. Earlier reports record local B3.1 completion and 108 tests/847 assertions plus 31 frontend checks; those are historical, not fresh tests here. No runtime/test or fresh database integrity run is performed.

Phone proof, NIDA secure capture, full profile/submission/review, financial accounts, team invitations, MFA/passkeys and premium dashboards are not established as implemented by these documents. Production auth remains separately gated. No production domain is chosen; local `http://xlipa.test` is preserved. Missing financial infrastructure means unavailable capability/data, never a mock zero/live chart.

## 3. Premium visual design principles

**PROPOSED design tokens for later approval**, not authorization to replace current branding or introduce a new frontend framework. Preserve the single-X identity, existing approved logo, language and visual continuity. Use a neutral layout, clear type scale, generous spacing, subtle borders, minimal shadows and one restrained accent. Avoid strong gradients, saturated panels, decorative motion and chart overload.

| Token | Proposed light mode | Proposed dark mode candidate |
|---|---|---|
| Background | `#F8FAFC` | `#0F172A` |
| Card | `#FFFFFF` | `#1E293B` |
| Primary text | `#1F2937` | `#F1F5F9` |
| Secondary text | `#64748B` | `#CBD5E1` |
| Primary accent | `#4169A8` | `#93B4E5` |
| Border | `#E5E7EB` | `#475569` |
| Status | Restrained green/amber/muted red | Separately approved accessible equivalents |

These are candidates, not tested contrast certification. Verify all real text/background/focus/chart combinations in both modes; a subtle border may require stronger contrast where it communicates a control boundary. Proposed acceptance target: at least 4.5:1 ordinary text, 3:1 large text and essential non-text control/graphic boundaries, plus usable focus indicators; exact accessibility target ratified at design approval. Color is never the sole status/financial sign indicator: show labels/icons/signs. Pending amber does not imply guaranteed earnings; green is not proof of reconciliation.

Define reusable spacing/type/icon scales, tabular numerals for money, consistent localized labels, bounded line lengths and clear primary/secondary/destructive actions. Preserve full values with accessible expansion when truncating references. Chart palettes distinguish series without relying only on hue. Dark mode supports explicit light/dark/system preference, persistent scoped setting and no unreadable chart/export inversion; legal documents use their approved template rather than screen-theme styling. No theme implemented now.

States: bounded layout-stable skeletons; meaningful no-activity versus not-enabled versus unavailable/error; disabled actions explain prerequisites without exposing private policy data. No spinner forever, fake zero, fake success or “sample revenue” posing as live. Respect reduced motion; avoid nonessential animation. Screen-reader changes should not announce every background tick.

## 4. Merchant Dashboard overview specification

Overview answers available funds, today's/yesterday's earnings, paid-out funds, pending payments and next authorized action. It is merchant-scoped, currency/book-specific and permission-aware. Financial view permission is not implied by existing staff summary permission. For an onboarding-only merchant, show truthful onboarding status and next permitted step; do not render an enabled wallet/withdrawal or fabricated financial cards.

**Default proposal:** four primary cards — Available Balance, Available to Withdraw, Net Merchant Earnings Today (yesterday comparison), Paid Out Today (yesterday comparison). A compact fifth summary may show Pending Payments Today when useful. If a capability is unsupported, omit that card or show clearly unavailable with explanation; never infer withdrawable funds from sales. Secondary financial detail exposes Total Balance, Yesterday values, pending cohorts, Total Successful Transactions, holds/reserves and settlement breakdown. Do not present ten equal cards.

Next-action panel prioritizes actual authorized onboarding/security/operational actions; it cannot be hidden by personalization if mandatory. Quick actions initially show at most two or three relevant items from Withdraw Money, Create Payment Link, Create QR Code, View Transactions, Download Report, API Keys. Remaining actions live in contextual menus. Permission and active capability are independently enforced server-side; verified email/internal merchant approval alone is not financial activation. Account suspension, team role, holds and product readiness can deny a visible requested action. Never create a dead control that suggests a service is operational.

Below cards: one revenue-trend chart, compact recent permitted transactions/next actions, then links to detailed Analytics. No default advanced heatmap or wall of charts. Clarify that “earned” means the defined platform net-proceeds metric, not profit, accrued tax income or money already settled.

## 5. Merchant KPI definitions and financial read contract

These definitions refine presentation using R1.0 semantics; finance/owner ratification is required before implementation. Each response includes merchant/account/book, currency, metric version, timezone, selected interval, data cutoff/as-of, ingestion watermark, reconciliation coverage and fee completeness. Store event times in UTC; proposed initial reporting timezone `Africa/Dar_es_Salaam`. Today is local midnight through current cutoff; yesterday is the full preceding local calendar day, converted to `[start,end)` UTC. A today-versus-yesterday comparison must label full-day versus elapsed-time; default growth comparison uses equivalent elapsed duration where appropriate. Approved timezone changes recompute boundaries explicitly and cannot silently alter an issued report.

Let **G(P)** = distinct eligible confirmed captured payment value effective in period P; **R(P)** = confirmed refund adjustments effective in P; **F(P)** = known fees effective in P net of approved fee reversals. **N(P)=G(P)−R(P)−F(P)** under the approved event-period policy. It may be negative when refunds concern earlier sales. Order-cohort net is a separate labelled report. Chargeback/tax/other adjustments require explicit approved mapping and separate disclosure, not an undocumented new formula. Unknown fees make net incomplete/estimated/unavailable, never zero-cost assumed. Payouts do not reduce earnings a second time; they are movement of funds.

| Required KPI | Proposed exact meaning / timestamp and limitation |
|---|---|
| Total Balance | Net merchant funds still represented in the approved operating/memorandum book at cutoff: disjoint available, reserved and held/pending-eligibility components. Exclude unconfirmed customer payment intents and already-settled funds. Define account membership to avoid counting both reservation contra entries and their original funds. Label “tracked merchant funds” if XLipa does not hold funds; not bank cash or lifetime gross sales |
| Available Balance | Posted eligible matured funds less active holds/reservations/committed obligations under R1.0 policy at cutoff. Use a single approved projection so reservations are not subtracted twice. Disclose deficits/holds; never silently clamp the underlying deficit to zero |
| Available to Withdraw | Eligible amount for a new withdrawal under current capability, destination, currency/provider, restrictions and approved limits, using available projection and fee policy. Distinguish maximum request principal from expected destination net. Minimum threshold below available can mean no permitted request; unsupported/blocked capability = unavailable, not a claim balance vanished. Never exceed available funds or bypass reserved obligations |
| Earned Today | N(today) on approved effective financial timestamps; labelled net merchant earnings/proceeds, not profit/withdrawable balance; fee completeness shown |
| Earned Yesterday | N(previous full local day), same policy/currency/fee treatment; revised late evidence traceable |
| Paid Out Today | Sum actual destination-net payout allocations confirmed and reconciled effective today, less linked confirmed payout reversals under approved policy. Show gross released/fees separately; request/approval/provider acknowledgement excluded |
| Paid Out Yesterday | Same payout definition for full previous local day; not withdrawal requests submitted yesterday |
| Pending Payments Today | Count and face value of distinct defined payment intents initiated today that are still pending at current cutoff. This is a current cohort stock, not confirmed revenue or pending settlement |
| Pending Payments Yesterday | Default: intents initiated yesterday and still pending now, labelled exactly. Separate historical option: intents pending at yesterday's close, reconstructed as-of from versioned events (including older initiated intents). These are different populations; never share one ambiguous label |
| Total Successful Transactions | Count distinct eligible confirmed payment IDs across the explicitly labelled authorized lifetime/selected scope as-of cutoff; not callback/attempt count. Later refund retains historical success and appears in refund dimensions |

Pending-cohort value is not receivable/ledger cash unless the approved accounting contract says so. Define treatment of multiple attempts: one selected payment-intent population for count/value; failed retry attempts do not inflate successful transactions or pending amounts. All states are backend facts, not browser flags. Gross payments, net earnings, pending payments, pending settlement, reserved funds, available and withdrawable balance, completed payouts and XLipa revenue remain distinct.

Currency: one selected currency/book per monetary card/series; integer minor units, approved precision and formatting, no mixed-currency total/implicit FX. Round only under approved policy; values reconcile to ledger/payment/settlement projections as appropriate. Known genuine zero only with complete coverage; unavailable feed, stale reconciliation or missing fees is labelled, with safe retry. Provider-confirmed captures may be shown with pending reconciliation badge under the approved metric contract; available/paid-out projections meet their stricter reconciliation requirements. Never label all raw confirmed revenue as settled.

### Performance chart

One overview confirmed revenue line chart: Today, Yesterday, Last 7/30/90 days and custom range; explicit include-current-day/range labels. Daily/weekly/monthly aggregation remains available in Analytics. Gross/net toggle uses G/N with completeness labels; transaction counts in a separate panel, not a currency axis. Comparable previous period is a distinct labelled series with equivalent interval/coverage. Refund impact is disclosed as R and optional detail rather than hiding negative net points. Payment success rate follows R1.0 successful/(successful+failed terminal eligible outcomes), excludes pending and has approved cancelled treatment; zero denominator N/A. Average value = eligible gross/successful count, zero count N/A.

Use zero buckets only for complete ingestion with no activity. Late events revise traceably; cache/as-of and freshness show clearly. Detailed refund, product/campaign/settlement charts belong in Analytics; no fabricated sample series. Accessible data table/download uses same filters and cutoff.

## 6. Traffic source analytics

**OWNER-APPROVED priority:** Facebook, Instagram, TikTok, WhatsApp, Google, Direct, Referral, Other and Unknown. Preserve R1.0 **YouTube** as an explicit supported reporting category; do not lose it because the R1.3 examples omit it. Source taxonomy/version and Direct-versus-Referral rules need approval. Missing referrer remains Unknown, not automatically Direct. Other identifiable sources differ from unknown evidence.

Data dimensions: source, medium, campaign, payment-link/QR/button identity, merchant product/order, observation/attribution timestamp, method, confidence/evidence provenance and mapping/model version. Distinguish validated first-party campaign ID (real XLipa/merchant campaign configuration), trusted server payment-link binding, syntactically validated browser UTM, referrer-based estimate and unknown. A valid campaign/link proves binding/integrity, not independently that a named social network generated the customer. A signed browser label is still a claim unless an authorized independent integration supplies actual evidence.

**Proposed initial model:** last eligible touch within the same authorized checkout/session, with approved eligibility/tie-breaking rules. Ratify before implementation. R1.0 first-touch remains an optional separately labelled comparison; do not add first- and last-touch revenue. Cross-session windows/cross-device matching need separate privacy/consent/integration approval. Freeze selected order attribution and reference confirmed payment IDs; one payment contributes once per model/currency. Corrections append traceable versions without ledger edits.

View: horizontal bar chart or ranked list showing attributed confirmed payment value, distinct successful count, average value, time trend and campaign drilldown. “Percentage of attributable revenue” denominator = confirmed value of all identified sources excluding Unknown in the same filters/model/currency; if zero, N/A. Also show share of **all** eligible confirmed value including Unknown and the unknown fraction. Never hide Unknown by presenting identified percentages as total coverage. Refund attribution follows original paid order under approved event-period/cohort policy and is separately labelled.

No perfect tracking promise: copied links retain old tags, messaging suppresses referrers, browser claims are spoofable, privacy blockers and cross-device journeys reduce evidence. Use source methodology and confidence labels, not invented percentages of confidence or causal claims. No advertising spend/impressions/ROAS unless separately authorized platform integration or explicitly provenance-labelled merchant input exists; merchant input is not independent platform reporting. Minimize UTM/referrer retention, strip sensitive queries, reject arbitrary script/overlong metadata, no fingerprinting default. Sources cannot alter price/fees/beneficiary/status.

## 7. Payment-method analytics

Report actual provider evidence using a reviewed versioned adapter; separate processor/provider from customer's method/network and merchant settlement destination. Categories may include M-Pesa, Airtel Money, Mixx by Yas, other approved mobile money/rails and Unknown; retain R1.0 HaloPesa/bank candidate categories where official support is confirmed. No Selcom codes/bank list/rail capability invented. Phone prefixes/logos are not evidence.

Per method: eligible confirmed value, successful count, pending/failed counts, success rate, average value, confirmed refunds and known/unknown fees. For pending/failed records provider-confirmed method may not yet exist: display Unknown or a clearly separate requested-method dimension, never present a requested choice as actual confirmed rail. Success-rate comparisons require a consistent documented population with method evidence; missing terminal classification is disclosed and never silently reassigned to improve a rail's rate. Zero denominator N/A.

Use currency/status/effective-period/cutoff filters consistent with transactions. Store restricted raw evidence/reference and mapping version, masked payer details and safe normalized value. Remapping is reproducible and corrections traceable; unknown codes remain unknown, not guessed. Refund/settlement are separate state dimensions and do not erase captured payment history. Method totals/counts reconcile to the same authorized transaction population including Unknown.

## 8. Advanced cross-analytics

Location: **Analytics → Advanced Insights**, not default homepage. Matrix/heatmap **Traffic Source × Payment Method** with accessible table supports Facebook/M-Pesa, Instagram/Airtel, TikTok/Mixx and WhatsApp/method analysis only where facts actually exist. These are illustrative combinations, not sample financial data or proof of live rail support.

Filters: date/time basis, currency, source/medium, campaign, method/provider, payment status, product/order/link/channel, attribution model/version. One selected model allocates each distinct eligible payment to one source and one method bucket; Unknown row/column always retained. One-to-many product/order joins require a defined amount-allocation rule (item amounts/discount/tax allocation) rather than multiplying whole payment value by every item. Refund allocation follows the same approved linkage.

Cell value/count uses the same snapshot as transaction reports. Overall percentage denominator is all eligible matrix value/count including Unknown; row percentage uses the row's total including unknown method; column percentage uses the column's total including unknown source. Display which denominator/unit; zero N/A. Counts and TZS never share a scale. Confirmed-value matrix row/column/grand totals reconcile to source/method/transaction reports for identical filters/as-of. Pending/failed matrices are separately labelled populations and must not masquerade as revenue. Protect small cohorts/PII under an approved analytics privacy policy.

## 9. Merchant settings and localization

One structured settings area, not a new account schema by implication. Explicit user versus merchant ownership of each setting prevents multi-tenant leakage.

| Section | Proposed fields/responsibility | Scope / security |
|---|---|---|
| Personal Profile | Display name/photo, preferred language, timezone, date/number formatting | User-owned; changing display name is not legal-name verification. Photo upload is a separately gated nonpublic/publication-controlled media workflow with strict validation/resource limits, no remote fetch |
| Account & Login | Identity email, phone bindings, password, recovery and verified status | User identity and purpose-specific merchant contacts remain distinct; recent reauth, exact contact proof/version and session/auth_version revocation policy |
| Business Profile | Business name/logo, optional contact/address/website, justified identifiers, verification status | Merchant-scoped ordinary profile; no raw NIN/OTP/bank fields. Logo upload/visibility needs explicit scope; do not automatically expose private profile data |
| Preferences | Language, timezone, theme, approved dashboard layout/default range/views | Personal display preferences default user-scoped; merchant report timezone/policy changes are separately authorized, audited and reflected in report metadata |
| Notifications | Single R1.2 preference service and masked history | User-recipient/merchant-purpose binding and explicit role policy; no duplicate controls that conflict |

Initial language direction: **Kiswahili and English**. Versioned translation catalog covers navigation/errors/notifications/reports/financial labels/dates/currency and accessible text. Language switching never changes numeric stored money, permissions, fees or legal meaning. Approved terms distinguish available, pending, paid, verified and internally approved. Machine fallback must not silently translate legally significant statements/documents; each locale needs reviewed template version. If unavailable, show a disclosed approved fallback language and request path. Reports include timezone/currency/metric/locale/template metadata; original issued documents retain their language/version.

Email change clears verification for the new address, invalidates dependent readiness and follows approved recovery/session revocation; financial documents cannot automatically transfer to an unverified new address. Phone ownership proof is purpose/version scoped, not global identity or bank proof. Material legal identity/ownership/settlement changes need approved independent review/reverification and cannot rewrite frozen evidence. B3.2-A still permits initial four-field application plus required proofs/security; profile photos, logo, address, business type and other optional fields do not become hidden submission blockers.

## 10. Team management and granular authority

**OWNER-APPROVED priority:** Settings → Team with invite, pending list, accept/reject, assign/change role, suspend/revoke, activity and permission review. **VERIFIED:** existing B3.1 roles are owner/manager/staff; richer templates/invites/custom roles are not established implementation. Proposed future templates: Owner, Manager, Accountant, Developer, Support Staff, Custom Role; require separate capability/schema/compatibility approval before activation. Do not silently map every finance/developer permission to manager or rename staff into a privileged role.

Invitation protocol: fresh authorized inviter/merchant, normalized intended email, scoped bounded single-use secret stored as digest, expiry/revoke/resend limits, generic anti-enumeration response. Acceptance requires authenticated verified identity matching the intended contact, fresh invitation state/tenant and permission bounds; no automatic acceptance by URL alone and no cross-merchant role claims. Email send requires separate R1.2/provider activation. Already-member/concurrent acceptance/replay must be idempotent. Invite never grants platform privileges.

Suspending merchant membership access is distinct from globally suspending the person's account. Preserve current active/revoked storage; any additional suspension representation needs reviewed additive design. Every request/job/action rechecks fresh persisted permissions; invalidating one tenant grant must not silently remove unrelated memberships. Audit actor/target/approved permission changes without private message bodies. Concurrent last-owner demotion/revocation/transfer uses parent serialization and active-owner invariant; never remove the last authorized owner. Ownership transfer needs separate high-risk confirmation policy, not a role dropdown side effect.

| Proposed capability | Suggested narrow template eligibility, not an implemented grant | Additional guard |
|---|---|---|
| View balances | Owner; separately assigned Accountant/Manager | Explicit book/currency scope; no automatic staff financial access |
| View transactions | Owner and expressly scoped finance/support users | Masked fields/purpose restrictions independent of full payer identity |
| Export reports | Owner or dedicated export grant | Job/download reauth where required, private files and current permission |
| Create payment links/QR/buttons | Owner or approved commerce Manager | Active product/currency/limit configuration; no beneficiary override |
| Manage API keys | Owner or explicit Developer credential capability | Recent strong step-up; scope ceilings, show-once secrets/revoke audit |
| Manage webhooks | Owner or explicit Developer endpoint capability | Step-up, SSRF restrictions, separate signing-secret access |
| View withdrawal requests | Owner or approved Accountant read capability | No execution power from viewing |
| Request withdrawal | Explicit financial initiator grant | Strong step-up, limits, verified destination, balance reservation |
| Approve withdrawal | Separately assigned financial approver if model permits | Independent initiator/approver, approved amount thresholds/dual control; not implied by Owner/Manager name |
| Manage settlement destinations | Separate sensitive destination grant | Strong step-up, ownership verification, approved delay/review/dual control |
| Manage team | Owner; bounded delegated administrator only after policy approval | Cannot grant beyond own delegable ceiling, self-escalate or bypass last owner |
| Edit business details | Owner/Manager approved draft fields | Version/CAS/current-state controls; no identity-verification/state assignment |
| Manage notification preferences | R1.2 owner default; future bounded delegate | Cannot disable mandatory notices/change unverified recipients |

Custom roles use an approved capability catalog with nondelegable rights and resource/amount constraints. Privilege changes require step-up, audit and fresh actor/target checks; race tests include revoke versus export/withdraw/key issuance. Account owner is not proof of beneficial ownership, legal representation or financial approval authority. Merchant team authority never grants platform-wide access; Chief assignment alone never grants merchant route powers. Sensitive identity collection/submission remains owner-only under existing proposal unless explicitly amended.

## 11. Security and 2FA

Settings → Security: password management, login 2FA enrollment/status, future authenticator/passkey options, recovery codes, active sessions, approved trusted devices, login history, sign out other sessions, alerts and recent sensitive actions. No provider/package/MFA/passkey implementation is claimed or selected. Review maintained framework-compatible mechanisms and threat model in a separately authorized stage.

Ordinary merchant **login 2FA ON/OFF flexibility** is owner-approved only under a ratified risk policy. Turning OFF requires recent strong confirmation, notification and a safe recovery path; an attacker with a stolen session cannot simply remove factors. Sensitive operations (destination changes, high-risk withdrawals, owner email changes, API secrets, privileged team changes) require action-bound recent strong step-up even if routine login 2FA is OFF. No enrolled usable factor/readiness means deny the sensitive operation and guide secure enrollment/recovery, never silently downgrade to password-only/SMS. Exact factor/recency/amount/risk thresholds require approval.

Prefer separately reviewed phishing-resistant passkeys/security keys for privileged operations, with authenticator options as approved; no standards compliance claim. SMS possession from onboarding is not strong privileged MFA or legal authority. Chief and other privileged platform operators require strong MFA plus HTTPS/active platform role/reauth/conflict policies; no configurable OFF escape from merchant settings.

Enrollment/challenge is user/session/purpose/origin bound with expiry/replay/attempt limits and safe factor verification. Passkey relying-party/origin configuration depends on the future approved domain; do not bind it to an invented production hostname. Encrypt recoverable authenticator seeds with controlled keys; store recovery codes as nonrecoverable digests, show once and consume atomically. Secret/QR/recovery-code values never logs/audit/exports. Factor reset/recovery must not be weaker than protected access: revoked factors/sessions, verified established contacts, risk-based waiting/manual independent review and limited recovery session; no support impersonation or email-only unrestricted financial access. Lost device/compromise runbooks and notification policy need approval.

Session list masks device/IP data under retention policy; sign-out-other-sessions uses server invalidation/auth_version controls and current session policy. Trusted devices, if approved, have revocable scoped expiry and never bypass high-risk step-up. Login history is observed evidence, not guaranteed person/location identity. Changes to role/contact/factor propagate to background jobs and privileged sessions as specified. Preserve existing CSRF, signed links, password reset/rate limits and auth behavior until separately approved changes.

## 12. Notification integration

R1.2 remains the single policy/service: customer-payment notifications **email only, no per-payment SMS**, individual/daily/weekly/off; withdrawals SMS + email default with optional controls bounded by approved mandatory matrix; separate risk-based security notices; mandatory/legal communications cannot be disabled through ordinary settings. Notifications remain free to merchants; platform budget controls cannot quietly suppress mandatory notices or add merchant charges.

Language/template version, verified recipient/purpose/contact version and preference snapshot follow R1.2. Email change does not transfer old documents to an unverified target. No duplicate Settings/Workspace preference systems: navigation aliases open the same authorized settings/history. In-app unread status is not financial state or delivery proof. UI hides full payload/credentials/NIN and shows masked channel/status/retry/error metadata. Financial documents and required notices remain separate from optional status messages, subject only to the explicitly proposed section 13 amendment.

## 13. Financial-document policy refinement — explicit pending amendment

**OWNER-APPROVED preference direction:** minimize unnecessary PDF generation/storage. **PROPOSED AMENDMENT D13-1 — REQUIRES EXPLICIT OWNER/LEGAL APPROVAL:** change R1.2 automatic withdrawal-request/advice PDF generation to authorized **on-demand** generation where legally/contractually permissible. This document records the proposal; it does not silently repeal R1.2 sections 8/10/15. Until ratified, R1.2's automatic request/advice baseline remains the planning authority for that behavior. Record an approval version/effective scope before any implementation.

| Event | Proposed R1.3 document policy | Qualification |
|---|---|---|
| Customer payment successful | Transaction record; PDF on authorized request where applicable | No new routine PDF mandate or payer receipt service inferred |
| Customer payment pending/failed | No routine PDF | Financial/event history still retained; applicable legal requirement overrides |
| Withdrawal requested | Advice PDF on authorized request | **D13-1 pending**, not current authorized replacement; always state not proof of payment |
| Withdrawal completed | One authoritative payout receipt automatically issued | Confirmed provider evidence + approved reconciliation + complete final components required |
| Withdrawal failed | No routine paid receipt | Preserve failure/status records; never paid document without real payout |
| Settlement completed | Document on request | Unless law/contract requires automatic issuance |
| Daily/weekly reports | On-demand PDF/XLSX/CSV export | R1.2 email digests continue without automatic PDF attachment |
| Monthly statement | On-demand | Unless applicable law/contract requires automatic issuance/delivery |

Generation, delivery and retention are separate. D13-1 also needs an explicit amendment to R1.2 advice-email behavior: proposed request status SMS/email continues, with an authenticated history/request-document link; no automatic advice attachment/email artifact until requested, unless mandatory. Approve whether an on-demand advice is emailed or only securely retrieved. Confirmed receipt's automatic verified-email delivery remains under R1.2's distinct financial-document delivery policy awaiting its outstanding approval; optional transaction email-off cannot silently suppress required document delivery. Do not infer on-demand generation changes SMS/email status policy or legal notices.

Reuse one immutable issued artifact per authorized issuance/version, with unique issuance key/number, template/locale/policy, frozen evidence payload, checksum and audit. Concurrent requests/callbacks cannot duplicate official numbers. Email retry/redelivery references the same receipt; reversal/correction creates linked artifact, not overwrite. Re-download is not re-issuance. Stored PDF objects live in private approved storage outside transactional DB; DB keeps protected metadata/evidence references. No public URLs; authenticated time-limited permission rechecks and private download audit.

Avoid redundant stored copies using canonical artifact references and safe storage deduplication scoped to access/retention boundaries; no cross-tenant object link that leaks existence/data. Temporary renderer/export output has bounded cleanup; issued artifacts/financial evidence follow approved archive/holds/retention. Never delete a legally required original merely because it can theoretically be regenerated. If an unissued on-demand PDF can be rendered from frozen facts/template version, retain enough reproducible evidence under approved policy; issued immutable bytes are preserved where required. Storage reduction cannot remove mandatory documents, alter ledger history or mask actual payouts. Missing receipt renderer creates an operational exception, not reversal of real paid status.

## 14. Chief Admin Dashboard specification

Separate platform-wide operations experience, not a copy of Merchant wallet. Default four to six cards: XLipa Fee Revenue Today (yesterday comparison), Total Confirmed Payment Volume Today (yesterday comparison), Pending KYC Applications, Pending Withdrawals, Provider Health and Critical Alerts. Authorized operational context may replace one card with Completed Payouts Today. Secondary details expose Active Merchants, Failed Transactions and Open Disputes plus yesterday amounts. Personalization cannot hide critical/security/compliance actions.

| Required KPI | Proposed definition and evidence boundary |
|---|---|
| Platform Revenue Today/Yesterday | Approved recognized XLipa commission/fee journals effective in local period, net linked commission refunds/reversals; distinguish revenue from provider costs/tax pass-through/operating expenses. Not gross merchant volume or profit |
| Total Payment Volume Today/Yesterday | Distinct eligible confirmed merchant captures in period, per currency/book, with refunds separately disclosed; same payment definition as Merchant reports |
| Active Merchants | Fresh count meeting explicitly approved operational/capability definition as-of cutoff; show active account, internally approved and payment-enabled counts separately if needed, not conflated |
| Pending KYC Applications | Count current submitted cases awaiting an authorized decision under actual lifecycle; draft registrations and optional missing documents are not automatically pending reviews |
| Pending Withdrawals | Count and currency-separated amount of defined unresolved requests, broken down requested/approved/processing/ambiguous; no payout inferred |
| Completed Payouts Today | Actual provider-confirmed and reconciled allocations/net paid under same Merchant definition; reversals disclosed |
| Failed Transactions | Defined failed terminal payment population/time basis; distinguish attempt failures from distinct order/payment failures and provider incidents |
| Provider Health | Approved health/latency/error/queue/reconciliation signals with timestamp/coverage; lack of data = unknown, not healthy |
| Open Disputes | Authorized unresolved dispute cases with lifecycle/priority/as-of; cannot count duplicate events as cases |
| Critical System Alerts | Active deduplicated actionable incidents under approved severity policy; include freshness/acknowledgement, not a fabricated zero |

Chief financial cards retain currency separation and revenue/custody distinctions. Gross merchant payment volume, merchant tracked/payable funds, refund liabilities, provider settlement evidence and XLipa expenses belong separate measures/books under the approved operating model. Do not assume XLipa holds customer funds or call gross volume profit. Sensitive card/drilldown fields require explicit platform policies, not unrestricted Chief access to NIN, messages or credentials.

Modules retained/expanded: Merchant Applications; KYC & Verification; Merchant Management; Transactions; Withdrawals; Settlements; Refunds & Disputes; Providers; Pricing & Fees (including tiers); Risk & Fraud; API Governance; Notification Operations; Financial Document Audit; Support; Staff & Permissions; Audit Logs; System Settings. R1.1 Auto-Settlement Settings, Refund Management, Real-Time Transaction Feed and R1.2 Delivery Monitoring/Messaging Costs remain contextual subviews, not removed modules. Every write requires capability, current assignment, step-up/conflict/dual-control rules as applicable and atomic audit; no self-review or platform-to-merchant bypass.

### Restrained activity overview

Show a bounded prioritized feed: new merchant applications, summarized verified payment events, withdrawal requests, confirmed payouts, provider incidents, security alerts and critical failures. Group low-severity/high-volume events and link to detailed transaction feed rather than flooding home. Filters by authorized merchant/type/severity/time; redacted allowlist and independent delivery/entity versions. Financial facts require authoritative backend evidence, not browser redirects.

Retain R1.1 bounded cursor polling initially (proposed 5–15 seconds visible-view only after capacity approval), with jitter/backoff, capped batches, server pagination and durable resume/deduplication; SSE later if measured need justifies proxy/auth/reconnect operations, WebSockets only for approved bidirectional need. Show freshness/ingestion lag/last successful update; connection failure = stale/degraded, not empty/no incidents. Apply backpressure, coalescing and reconnect snapshot after retention gap without dropping critical incidents or regressing entity version. Every read/reconnect checks fresh platform scope; no shared public cache. No transport implemented now.

## 15. Navigation architecture and personalization

| Merchant group | Concise destinations and contextual detail |
|---|---|
| Overview | Dashboard; next actions/secondary metrics |
| Payments | Transactions; Payment Links; Checkout with distinct QR Payments and Pay Buttons tabs/workspaces |
| Commerce | Products & Orders; physical/digital order and fulfillment detail (retains R1.1 responsibilities) |
| Finance | Balance/Wallet & Ledger; Withdrawals/history/documents; Settlements; Refunds & Disputes |
| Insights | Analytics → revenue, sources, methods, campaigns, Advanced Insights; Reports & Statements PDF/XLSX/CSV |
| Developers | API Keys; Webhooks; Documentation |
| Workspace | Team; Notifications/history; Support |
| Settings | Profile; Account & Login; Business/KYC & Verification; Payment Methods; Security; Preferences; Notifications (same destination, not duplicate state) |

Team's canonical settings destination is Settings → Team, with Workspace shortcut if useful. Keep one route/resource per responsibility; not two competing role systems. Payment Methods is merchant account/destination management, not customer rail analytics. All 23 R1.0 modules and R1.1 QR/buttons/physical-order workspaces remain mapped through these contextual locations, not deleted to shorten sidebar.

Chief groups: **Operations** (overview/activity, Transactions, Withdrawals, Settlements, Refunds/Disputes, incidents); **Merchants** (applications, KYC, management); **Commercial/Integrations** (Pricing/Fees, Providers, API Governance); **Risk/Assurance** (Risk/Fraud, Financial Document Audit, Audit Logs); **Administration** (Notifications/delivery/costs, Support, Staff/Permissions, System Settings). Sensitive subviews have explicit scopes and clear platform context to avoid accidental merchant impersonation.

Desktop collapsible left sidebar, grouped labels and contextual tabs; tablet compact/collapsible mode with visible accessible labels/tooltips; mobile accessible drawer with focus trap/restore, Escape/close, current-location and scroll behavior. Breakpoints are proposals to confirm against existing assets, not arbitrary framework migration. No hover-only controls; tables use accessible overflow or contextual summaries without losing reference/amount/actions. Browser back/deep links preserve only safe scoped filters and reauthorize. Do not put every module at top level.

Optional personalization: reorder approved cards, hide optional metrics, default date range, theme/language and saved Analytics views. Scope by user+merchant where contextual, user-only for personal language/theme; shared merchant report policy needs authorized merchant setting. Saved views store filter/metric versions, not financial results or secrets. Permission changes invalidate unauthorized saved views/cards/cache; preferences never grant access. Mandatory security/critical/compliance actions stay visible. New merchants receive usable truthful defaults. A “reset layout” action affects display only, never financial records.

## 16. Performance and accessibility acceptance direction

Financial correctness before visual speed: authorize and scope server-side queries first, use bounded pagination/keyset cursors and indexed period/tenant/provider facts, precomputed versioned aggregates/incremental projections where justified. Never scan full merchant history per page load or sum only current table page as total. Review query plans/load with synthetic large data; define approved volume, response-time/queue-lag budgets before implementation, not invented current benchmarks.

Safe aggregate caches keyed by merchant/book/currency/timezone/range/filter/metric/permission scope and data watermark; enforce tenant isolation and fresh permissions independently. Invalidate/version for financial adjustments and role changes, bound staleness and show cutoff. Point-in-time balance/withdrawal action always performs fresh authoritative checks, not cached card authority. Late callback/reconciliation refresh is traceable. Critical access revocation must not wait for a shared cache TTL. Avoid PII in cache keys/browser storage and unbounded saved-query payloads.

Acceptance targets: 375/390 mobile, 768 tablet and representative desktop widths with zoom/reflow; no clipped monetary amounts or unreachable actions; semantic headings/tables/labels, keyboard order/focus, screen-reader chart table, non-color statuses, reduced motion, layout-stable skeletons, clear unavailable/empty/error/retry states. Test both languages (long text), themes and high data volumes. Chart live updates are restrained and accessible. Nonessential widgets may fail independently while a visible banner explains missing/stale financial coverage; no fabricated zero. Reports/exports use the same approved definitions/snapshot and safe formula/precision controls as R1.1.

## 17. Implementation gates — separate approval at each stage

No gate is authorized by this document. Preserve B3.1–B3.5, bounded B3.2-B preparation, C working activation, D capture, F0–F5 and R1.1/R1.2 dependencies. Adding team/MFA/dashboard capabilities does not silently expand B3.2-B to unrelated schemas. Future sub-gates below are review slices.

| Stage | Dependencies / separate approval | Required exit evidence |
|---|---|---|
| 1 UX specification | Ratify R1.3 metric labels/IA/document amendment, priority screens, localizations | Screen responsibilities/states/data provenance, no code or mock financial truth |
| 2 Design-system approval | Existing XLipa brand/asset review; light/dark tokens, responsive/accessible components | Approved design artifacts and contrast/focus/reflow evidence; no frontend replacement implied |
| 3 Dashboard read-model specification | F0 finance definitions, F1 authoritative facts/ledger/reconciliation; B3 onboarding predicates | Exact KPI populations/time/currency/as-of/fee/attribution rules, query/index/cache contracts |
| 4 Permission specification | Current owner/manager/staff; separately approved team/catalog/MFA/privileged policies | Per-action/field/delegation/threshold/recovery matrix, last-owner/conflict/dual control protocol |
| 5 Backend implementation | Explicit concrete scope/diff; required schemas separately prepared/activated; approved providers only where used | Synthetic tenant/concurrency/failure/read-model/key/recovery tests; no working-data fixtures |
| 6 Frontend implementation | Approved components, working permitted endpoints, explicit UI scope | Brand-preserving responsive localized states and no deceptive unsupported controls |
| 7 Integration testing | Relevant B3.5/F gates, synthetic financial/contact fixtures, sandbox-only approved contracts | KPI-to-ledger/report/source/method reconciliation, auth/team/MFA/browser/performance acceptance |
| 8 Production activation | Explicit domain/HTTPS/proxy/hosts/secure cookies, production auth, strong operator MFA, keys/backups, official contracts/privacy/operations | Service-specific operational/financial/real messaging/deployment authorization; no activation from dashboard readiness alone |

Suggested dependency placement: security/team specification may be separately reviewed alongside onboarding, without modifying completed B3.1. Secure B3.2 identity/contact decisions remain prerequisites for their real capture/proofs; B3.3 submission/B3.4 restricted review/B3.5 acceptance retain their gates. F0 defines accounting/provider/pricing and F1 ensures consistent ledger/events; F2/F3 enable authorized checkout/link/API/QR/button facts; F4 reads/analytics/reports/dashboard; F5 withdrawals/settlements/refunds and their document/notification prerequisites. Features required to operate live money safely must precede launch, even if built in separately reviewed slices. R1.2 fake notification infrastructure and test-only document rendering can be separately approved without claiming live payouts.

## 18. Acceptance criteria — future synthetic tests only

| Area | Required scenario/evidence |
|---|---|
| Merchant KPIs | Distinct capture events, known/unknown fees, partial refunds/fee reversal, holds/reservations/settlements and withdrawal limits; no double-subtracted reserves or payouts counted as earning reduction |
| Time boundaries | Exact local midnight, yesterday current cohort versus historical cutoff, elapsed/full comparisons, timezone change/DST future zones, late evidence watermark and snapshot consistency |
| Gross/net/balance | Net negative/refund earlier-period cases; no pending intent in available; no custody/profit claims; unavailable fee/provider/reconciliation produces honest incomplete state |
| Currency | Independent TZS/other approved currencies/books, integer precision/rounding and exports; no mixed-currency sum or assumed FX |
| Attribution | Valid campaign/link versus UTM/referrer claims, copied/missing refs/Unknown/YouTube, approved last-touch and separate first-touch, no duplicate revenue or fake ROAS |
| Method | Actual provider mapping versus requested/unknown method, missing failure-method denominator, refund/settlement independent status; no prefix inference |
| Cross-analytics | Source×method row/column/grand totals match same snapshot/report; Unknown retained, row/overall denominators explicit, product join doesn't multiply payment |
| Team | Cross-tenant invite/accept/replay/expiry, role ceilings/self-escalation, revocation during jobs/key issuance/withdrawal, concurrent last-owner safety, no platform role inheritance |
| Login 2FA ON/OFF | Approved ordinary-login toggling works; disabling does not bypass action-bound step-up; unenrolled sensitive actor denied; privileged platform OFF denied |
| Recovery/security | Lost/compromised factor, replayed recovery code, stolen session/contact-change, verified target/version, session invalidation and safe independent operator recovery |
| Languages/settings | Kiswahili/English labels/errors/number/date, timezone/locale scope, immutable approved document-language template, unverified new email cannot receive financial docs |
| Notifications | One R1.2 preference service, no per-payment SMS, digest/off/mode changes, withdrawal default both/matrix constraints, mandatory notices preserved, no merchant notification fee |
| Documents | Before amendment ratification retain R1.2 baseline; after explicit D13-1 approval test on-demand advice and notification linkage, no routine pending/failed PDFs; legal exceptions override minimization |
| Payout receipt | Approval/HTTP/scheduler/redirect/message not proof; confirmed reconciled payout one issuance/number, callback/email retries reuse artifact, partial/reversal policy and private download revocation |
| Chief | Active explicit platform scope/field masking/no self-review, KYC/privacy restrictions, accurate fee revenue versus merchant volume, no invented provider health or transaction data |
| Customization | Card/layout/filter setting never grants access or hides critical actions, merchant/user scoping and permission revocation invalidate saved views |
| UI/accessibility | Mobile/tablet/desktop themes/languages/zoom, keyboard/drawer/focus/screen reader/chart table, reduced motion and safe empty/loading/error states |
| Performance/consistency | Large synthetic datasets, bounded queries/cache/event cursor recovery, fresh permission/withdraw checks, lag banner, no fake zero and same dashboard/report/ledger totals |

No tests are run in this documentation task. Later stages must record actual results/limits; historical suite numbers cannot stand in for new team, MFA, financial or responsive acceptance.

## 19. Outstanding design, policy and external decisions

**REQUIRES APPROVAL:** exact primary-card priorities/labels and Total Balance account membership; withdrawable principal/net/limits; event-period adjustments/fee/refund/chargeback semantics; timezones/week/range comparisons and metric freshness budgets; last eligible-touch policy and privacy/Unknown denominator labels; method evidence population; design tokens/dark palette/accessibility target/component continuity; locale/template ownership; team capability catalog/custom role/last-owner transfer/approval thresholds; strong factor choices/action recency/login OFF/recovery/device policy; settings scope and media uploads; mandatory notification/document policy.

**Explicit pending change D13-1:** ratify on-demand request/advice generation and corresponding advice-email behavior after legal/contract review; R1.2 remains unchanged until then. Confirm retention/archival/reuse and document classification, mandatory monthly/settlement documents and confirmed receipt delivery obligations. Storage saving alone cannot justify deleting required evidence.

**NOT VERIFIED:** official provider methods/callback/fee/payout/refund/currency contracts, financial custody/operating model/tax/accounting/legal document obligations, production engine concurrency, live messaging, secure key/independent recovery, current full NIN syntax/privacy processing prerequisites, MFA/passkey operational setup and future production domain. Obtain official contracts and qualified review before relevant activation; no financial or regulatory certification is asserted. Dashboard design cannot resolve these by adding a status badge.

## 20. GO / NO-GO, preservation and stop

**GO:** R1.3 is ready for owner review as a production-oriented specification proposal. It incorporates the required premium dashboards/analytics/settings/team/security experience while retaining existing roadmap responsibilities and explicitly flagging the document-policy amendment.

**B3.2-B readiness unchanged:** conditional recommendation only after owner approves exact bounded identity/contact/schema subset and resolves existing NIN/crypto/privacy/OTP/contact decisions. This task authorizes no migration or expansion into team/MFA/dashboard/financial schema, no working activation and no real data collection.

Only this requested Markdown file is added. Existing R1.0–R1.2, architecture/specification/historical documents, code/frontend/migrations/tests, other tracked projects, `.env` and working SQLite are preserved against the 185-file baseline; HEAD remains the existing checkpoint, verified before delivery. No runtime/tests, migrations, dependencies, accounts, messages, financial documents, provider integration, config changes, commit/push or deployment. Six pre-existing untracked documents remain separate from this one addition.

**NO-GO for implementation/production activation under this task. STOP — await explicit owner approval of the next concrete stage.**

## 12 November 2026 — Owner scope freeze and development status

The owner has frozen all new feature/service additions. Earlier proposed roadmap items are historical direction, not authorization to expand this task. Complete, integrate, test and stabilize existing work only.

Completed development milestones retained: PD-01 Merchant Dashboard; PD-02 Chief/Staff workspace foundation; PD-03 merchant verification; PD-04A/B/B.1/B.2/B.3 submission guard, evidence contract/schema, lifecycle/crypto and internal submission service; UI-01 premium light design. The confirmed PD-04B.3 baseline was 368 tests / 3,024 assertions, with public successful submission disabled.

Current milestone: **PD-05A read-only Chief application review foundation implemented; STOP FOR OWNER REVIEW.** Includes status filtering/pagination, scoped redacted detail, recorded verification/evidence indicators, submission timeline and read-only application audit history. Decision writes remain blocked: existing status columns/transitions do not establish an approved atomic decision/audit, idempotency, authority and current-evidence contract. No schema amendment or workaround was introduced.

Remaining work in this approved scope is owner/security resolution of that decision-contract blocker. Next required step is review of the concrete PD-05A diff and evidence, not automatic PD-05B/PD-06 implementation. See [PD-05A delivery report](XLIPA-PD-05A-CHIEF-ADMIN-APPLICATION-REVIEW-2026-11-12.md) for exact files, final actual tests, preservation checks and operational blockers. No completion percentages are assigned.

Development progress is separate from production readiness. Trusted admission/issuer trust, key custody/recovery, lawful identity/retention policy, time and authoritative restore admission, lifecycle/release decisions and production crash/concurrency evidence remain operational blockers. **Working B3.2-C activation: NO-GO. Working SE-1.0 activation: NO-GO. Public successful submission: DISABLED. Live payments: DISABLED.** No working migration, approval, real identity processing, commit, push or deployment is authorized by this update.
