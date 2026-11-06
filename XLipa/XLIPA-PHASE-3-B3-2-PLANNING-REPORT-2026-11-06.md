# XLipa Website — B3.1 checkpoint na B3.2 Merchant Profile Foundation Plan

Tarehe: 6 Novemba 2026, Africa/Nairobi. **PLANNING ONLY. B3.2 code, endpoints, forms na migrations HAZIJAUNDWA.** XLipa ni browser-based payment gateway website; Tanzania ndiyo initial market, si automatic approval ya every legal structure, jurisdiction, currency au payment capability.

Alama: **VERIFIED** = source/schema/test/command evidence ya sasa; **PROPOSED** = design recommendation; **NOT VERIFIED** = evidence haijapatikana; **REQUIRES APPROVAL** = uamuzi/utekelezaji haujaidhinishwa na report hii. “Required” katika field matrix ni **proposed product completeness rule**, si madai ya legal/KYC requirement.

## A. B3.1 Git Checkpoint Result

Paths VERIFIED: canonical `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`, Laravel `XLipa\backend`, frontend `XLipa\frontend`, Git root `WEB-DEVELOPMENT`. Branch main; initial HEAD ilikuwa expected previous checkpoint `d195d108de1ea0ba48ec628129942f241d7020ec`. Initial stage area ilikuwa tupu; modified/untracked files zote zilikuwa documented B3.1 work. Complete tracked diff na contents za new files zilikaguliwa; hakuna unrelated project changes zilizopatikana.

### Fresh verification kabla ya commit

| Required check | Actual result |
|---|---|
| MerchantAccessTest | 24 passed / assertions 244 |
| AuthenticationTest | 29 passed / assertions 279 |
| TrustedHostsTest | 20 passed / assertions 60 |
| Complete isolated PHPUnit | **108 passed / assertions 847**, takriban sekunde 9.6 |
| Offline frontend | **31 interaction checks**, pages 10, DOM errors 0 |
| PHP syntax ya changed/new PHP/Blade files | 10/10 passed |
| Git diff validation | passed |
| SQLite READONLY integrity / foreign-key violations | ok / 0 |
| Sensitive-pattern scan ya explicit checkpoint files | 12 files, matches 0; contents pia zilikaguliwa |

Tests zimeanza testing + SQLite `:memory:` + empty DB_URL + array mail/cache/session + sync queue; existing guards zinatangulia schema work. Working DB haikutumika kwa factories/migrations. Scan hii ni bounded review ya current checkpoint, si historic secret-scan certification. Synthetic names/tokens kwenye tests si live credentials; report ina counts/hashes bila owner-account PII. `.env`, SQLite na `.recovery` backups zimeachwa nje ya Git.

**Local commit moja imeundwa:** `7c3e4743384e0ca0acd7b029c944569c84304541`.

Message: `feat(xlipa): complete B3.1 merchant context and onboarding status`.

Exact files 12, staged kwa explicit paths:

1. `XLipa/README.md`
2. `XLipa/XLIPA-PHASE-3-B3-1-IMPLEMENTATION-REPORT-2026-11-06.md`
3. `XLipa/backend/app/Http/Controllers/MerchantOnboardingController.php`
4. `XLipa/backend/app/Http/Middleware/ResolveMerchantContext.php`
5. `XLipa/backend/app/Policies/MerchantApplicationPolicy.php`
6. `XLipa/backend/app/Policies/MerchantPolicy.php`
7. `XLipa/backend/app/ViewModels/MerchantOnboardingSummary.php`
8. `XLipa/backend/bootstrap/app.php`
9. `XLipa/backend/resources/views/account/show.blade.php`
10. `XLipa/backend/resources/views/merchants/onboarding.blade.php`
11. `XLipa/backend/routes/web.php`
12. `XLipa/backend/tests/Feature/MerchantAccessTest.php`

Baada ya checkpoint, working tree ilikuwa clean. Hakuna amend/reset/history rewrite/push. Planning report hii ni subsequent uncommitted documentation pekee; existing B3.1 report haijaandikwa upya kufuta historical “uncommitted” statement yake.

## B. Existing Architecture and Schema Findings

Sources: Stage A architecture, approved B3 plan sections B–D/G/H/K, B2 report, B3.1 implementation report, README, migrations, models, middleware, policies, status controller/view-model na auth/merchant tests. Historical reports za users 0/migrations pending si current state.

### Current database — VERIFIED, READONLY

`backend/database/database.sqlite`: application tables 14 (internal sqlite_sequence ikiongezwa ni 15); migration records 9. Users/merchants/memberships/applications/audit logs ni **1/1/1/1/1**; platform role assignments **0**. Integrity `ok`, FK violations `0`. Source hash kabla/baada ya checks/tests:

`214ae83a31f53e80d3068d1aeb1366008bb5accb2df212dd2c534ae4121b6d8f`.

`.env` full-file hash pia unchanged; APP_KEY/local URL hazijabadilishwa. No row contents/PII zilizoexportiwa.

### Verified reusable foundation

- `users`: integer identity, normalized email, email_verified_at, account_status, auth_version, terms metadata; si legal business identity.
- `merchants`: ULID; business_name(200), nullable business_type(50), registration_country(2), registration_number(100), business_email(254), business_phone(16), address_line1/2(255), city(100), region(100), country_code(2), postal_code(20); operational/verification states, positive profile_version, creator FK na timestamps.
- `merchant_memberships`: many-to-many, owner/manager/staff, active/revoked; unique merchant+user. Historical creator si permission grant.
- `merchant_applications`: ULID, positive attempt/profile versions, current_slot nullable/1, five states, encrypted snapshot/internal notes, public feedback, submit/review actor/time. Unique merchant+attempt na merchant+current_slot; composite id+merchant key.
- `audit_logs`: bounded event enum/CHECK, append-only model, scalar metadata allowlist ≤2048 bytes, actor/context/request/time, composite application/merchant FK; indexes merchant/time, actor/time, event/time.
- Current B3.1: scoped route context; fresh active/verified membership policies; staff-safe summary; current-attempt owner/manager details tu; no full profile/history/write routes. Middleware/privacy/error chain na trusted-host block vipo.

`PRAGMA table_info`/sqlite_master zimehakiki actual columns/indexes/triggers, na migration files zimethibitisha declared bounds. **SQLite varchar declarations hazithibitishi length enforcement**; FormRequest/action validation na isolated constraints testing vitahitajika. Existing positive-version triggers na all existing FK/unique checks lazima zihifadhiwe.

## C. Merchant Type Recommendations

**PROPOSED:** preserve B3 plan families `individual`, `sole_proprietor`, `registered_business`; haya ni proposed allowlist values, si existing activated product options. Actual business_type ni nullable string, si enum ya legal structures. Business-name registration na legal entity form ni concepts tofauti; registration ya trading name peke yake isigeuzwe kuwa company.

| Candidate category ya owner review | Mapping iliyo ndani ya existing B3 proposal | Profile/evidence/representative recommendation | Eligibility na dependencies |
|---|---|---|---|
| Individual / unincorporated sole trader | `individual` au `sole_proprietor`, exact boundary iidhinishwe A | Explicit legal name ya mtu, display name, activity/contact/address; self/authorized representative declared; registration pair only if applicable to approved country/type policy | **NOT VERIFIED** kwamba unregistered trading inakubalika kisheria/provider; no automatic onboarding/payment eligibility. No blanket exemption ya licence/tax/KYC |
| Registered business name ya sole proprietor | Normally `sole_proprietor`; registered name si new entity type yenyewe | Proprietor legal name tofauti na trading name; applicable registry country+number; future registry evidence review; representative capacity/self declaration | Confirm registry/jurisdiction na ownership; membership owner si beneficial/legal-owner proof |
| Registered company | `registered_business` family, exact supported company forms zifungwe A | Registered legal name, trading name, registration pair; named representative/capacity/authority; later incorporation/registry evidence | Provider/KYC/legal requirements, directors/UBO collection na accepted company forms **NOT VERIFIED**; no invented document list |
| Partnership, foreign company/branch, NGO/trust/cooperative/other | **Deferred**; hakuna silent mapping kuwa individual/company | Purposeful legal-form/registry/representation/evidence specification kabla ya enabling | Separate owner/compliance/provider approval; existing three-value family lisitumike kukwepa unsupported structure |

Recommended A decision: bind explicit supported legal forms to each existing family; reject unsupported combinations. Usibuni fourth production option sasa. Ikiwa owner anahitaji normalized `legal_form`, `registration_authority` au separate registered-trading-name metadata, hiyo ni **alternative additive scope requiring approval**, si part ya eight-column default proposal below. Hakuna alternative iliyo-adopt au architecture conflict iliyofichwa; until approval, unsupported distinctions fail closed.

Official context: BRELA ina tofauti ya company na business-name registration; TNBP pia hutofautisha incorporation/compliance/business-name certificates. Hii si XLipa merchant-eligibility determination. [BRELA functions](https://www.brela.go.tz/pages/core-functions), [TNBP registration guidance](https://www.business.go.tz/register-a-business?inheritRedirect=true).

BPRA ina business-name process yake Zanzibar; kwa hiyo `TZ` pekee haitoshi kukisia registry/legal rules. Proposed initial jurisdiction coverage—Mainland, Zanzibar au zote—ihitaji explicit A decision na verified matching policy. No blanket Mainland rules kwa every TZ merchant. [BPRA business-name registration](https://www.bpra.go.tz/services/firm-registration/).

Privacy kwa individual/proprietor: legal/business contact/address inaweza kuwa personal/home data. Usifanye profile public au kukusanya residential/identity documents kwa kunakili registry application checklist. Company profile pia inaweza kuwa na personal representative data. Official registry document requirements si automatically payment-provider requirements.

## D. Country and Currency Strategy

**Current owner-approved market intent:** Tanzania. **PROPOSED initial profile allowlist:** `TZ` pekee baada ya legal-form/jurisdiction rules kuidhinishwa; si current code/config change wala payment launch. Other countries remain disabled. Exact eligible merchants/currencies/provider channels bado hazijaidhinishwa.

1. Country codes zihifadhiwe uppercase ISO 3166-1 alpha-2, labels zitafsiriwe separately. ISO code dictionary na **enabled market allowlist** ni vitu tofauti; unknown/valid-but-disabled code ikataliwe. Membership, IP, phone, language au domain visiwe source ya country authority. [ISO country-code standard](https://www.iso.org/iso-3166-country-codes.html).
2. `country_code` ni operating/contact address country; `registration_country` ni registry country. Zisiwe interchangeable. Initial unsupported cross-border combination iende explicit unsupported path, si automatic enablement. Existing NULL draft rows zibaki NULL mpaka owner ajaze; hakuna guessed backfill.
3. Validation policy keyed by enabled country + approved merchant family + jurisdiction; require address_line1/city, then country-specific region/postal rules. Usihardcode US ZIP/state au Tanzania region lists kwenye every model. District/city semantics: existing city field iwe locality display value; distinct district hierarchy/dependent selectors ni later schema/policy proposal, si silently overwritten city.
4. Registration IDs ziwe bounded strings; preserve meaningful leading zeros, punctuation na case. No guessed global format/length/checksum. Registry-specific normalization ije baada ya authoritative evidence; country+number existing index ni review aid, si proof ya unique legal person.
5. Business phone ihifadhiwe international E.164-shaped `+` na digits, ≤15 digits (column capacity 16 with plus). Country-aware national parsing ihitaji explicit selected country na maintained numbering policy; ambiguous national numbers zikatalike. Existing B2 Tanzanian registration normalization haibadilishwi na B3.2 kama implicit side effect. Format si ownership proof. [ITU E.164](https://www.itu.int/rec/dologin_pub.asp?id=T-REC-E.164-202602-I%21%21PDF-E&lang=e&type=items).
6. **Currency is financial capability, si profile-country inference.** Future catalog itumie ISO 4217 codes na supported currency/provider allowlists tofauti; each monetary record iwe na explicit currency na approved precision/integer-minor-unit strategy, no binary floating-point money. TZS ni candidate ya initial future payment design tu; hakuna currency column, balance, FX/conversion au settlement capability inayoongezwa B3.2. [ISO currency-code standard](https://www.iso.org/iso-4217-currency-codes.html).

Configuration design ya later implementation: versioned server policy (country/type/jurisdiction/field rules), initial choices approved at A; no large runtime policy-editor database. Country validity pekee lisitoe regulatory/provider support. URLs/assets/route helpers ziendelee config-driven; future production domain UNKNOWN, local http://xlipa.test unchanged.

## E. Proposed Merchant Profile Fields

### Reading the field inventory

All rows are **PROPOSED future collection/authorization rules**; “exists” tu ndiyo VERIFIED schema fact. `I/S/R` = proposed individual/sole-proprietor/registered-business families. `All` = all enabled approved families. `Ready` = required for profile completeness (draft may remain incomplete); `Cond` = conditional after country/type rule approval; `Opt` = optional. Existing business_name remains nonempty because original schema is NOT NULL.

Privacy/access codes apply **per row**:

- **B** restricted business information (may also be personal); **P** personal/representative information; **S** sensitive identity/tax/evidence; **M** server metadata. None is public by default.
- **OM** = fresh active verified owner/manager of this merchant reads/updates permitted draft fields; staff denied. **O** = owner-only attestation. **SYS** = server writes, owner/manager may read allowlisted derived evidence, no client assignment. Platform reviewers get no profile permission in B3.2; separate B3.4 scope later.
- **Public N** means no public exposure even if field is a “display” name/website/support contact. Existing B3.1 business-name/status-only summary remains its own limited read exception; staff does not receive full-profile/representative/readiness diagnostics.
- Verification column distinguishes declared data from independently verified data. No business_email/phone ownership-verification flag exists now; do not imply verification from format or identity-email verification.

### Identity, contacts and address

| Field | Exists/proposed storage | Required/type applicability | Supplied-value validation | Privacy / read / update / public | Verification / workflow |
|---|---|---|---|---|---|
| business_name, trading/display name | Exists, merchants string200 | Ready All; nonempty when supplied | Trim, 1–200, plain text, preserve Unicode; not globally unique | B / OM + B3.1 summary / OM / N | Declared; separate from legal name; profile |
| legal_name | **New** string200 | Ready All; individual's/proprietor's explicit legal name or entity legal name | Trim, 1–200, plain text; no auto-copy from users.name | P / OM / OM / N | Later identity/registry check, not automatically verified; profile |
| business_type | Exists string50 | Ready All | Exact approved allowlist and supported legal-form mapping; unknown denied | B / OM / OM / N | Eligibility policy gate; profile |
| registration_country | Exists char2 | Cond I/S, Ready R | ISO + enabled registration policy; paired with number, not domain-derived | B / OM / OM / N | Registry jurisdiction check later; profile |
| registration_number | Exists string100 | Cond I/S, Ready R | Bounded string, no control chars; registry-specific rule only when confirmed; preserve zeros | B / OM / OM / N | Future registry evidence; not globally unique; profile |
| business_email | Exists string254 | Ready All, explicitly supplied | Conventional email syntax/bounds, approved normalization; no guess from users.email | P / OM / OM / N | Declared contact; later proof if required; profile |
| business_phone | Exists string16 | Ready All | International +digits, country-aware shape/length; no universal +255 rewrite | P / OM / OM / N | No phone ownership proof yet; profile |
| website_url | **New** string255 | Opt All | HTTPS absolute URL; no userinfo/controls, bounded; never server-fetch | B / OM / OM / N | Declared sales channel, no remote scrape/verification; profile |
| country_code, operating address country | Exists char2 | Ready All | ISO/enabled address-country policy, independent of registry country | B / OM / OM / N | Later address verification if required; profile |
| address_line1 | Exists string255 | Ready All | Trim, bounded plain text; no forced national template | P / OM / OM / N | Declared operating/contact address; profile |
| address_line2 | Exists string255 | Opt All | Nullable, bounded plain text | P / OM / OM / N | Same, optional; profile |
| city, locality | Exists string100 | Ready All under existing B3 proposal | Country-aware locality meaning; bounded text, no fabricated administrative IDs | P / OM / OM / N | Declared; profile |
| region, state/province | Exists string100 | Cond All | Required only by approved country profile; allowlisted region IDs only with maintained data | P / OM / OM / N | Declared; profile |
| postal_code | Exists string20 | Cond All, otherwise Opt | Approved country-specific pattern/length; no universal numeric-only rule | P / OM / OM / N | Declared; profile |

Conditional registration policy is an explicit A blocker: recommend required country+number for any supported registered/trading-name path; unregistered individual/S path must not be enabled until legal/provider eligibility is confirmed. No fake “N/A” values to satisfy required fields. Draft saves may accept incomplete pairs, but supplied parts must be valid and readiness reports the missing counterpart.

### Representative, activity and readiness

| Field | Exists/proposed storage | Required/type applicability | Supplied-value validation | Privacy / read / update / public | Verification / workflow |
|---|---|---|---|---|---|
| representative_name | **New** string200 | Ready All: explicit self/representative | Bounded Unicode name; not inferred from membership owner/account name | P / OM / OM / N | Declaration is not identity proof; profile |
| representative_capacity | **New** string100 | Ready All | Approved bounded values/description by type: self/proprietor/authorized representative; exact options at A | P / OM / OM / N | Authority evidence later; profile |
| authority acceptance input | New request input, **not** a DB boolean | Ready All, explicit owner action | Accepted declaration + expected profile version; server-chosen declaration version | P / O / O via action / N | Attestation, not legal/KYC verification; workflow |
| authority_attestation_version | **New** string50 nullable | Ready All after attestation | Server-approved wording/version; client cannot assign | M / OM / SYS / N | Evidence/version; profile metadata |
| authority_attested_at | **New** timestamp nullable | Same | UTC server time only | M / OM / SYS / N | Evidence; no guessed legacy timestamp |
| authority_attested_by_user_id | **New** users FK nullable | Same | Current verified active owner; FK restrict; no browser actor ID | P / OM allowlisted attribution / SYS / N | Permission evidence, not beneficial ownership |
| business_activity | **New** string1000 | Ready All | Plain text 1–1000 when supplied; describe products/services + operating model + sales channel; no IDs/secrets | B / OM / OM / N | Future risk/provider assessment; not an MCC assignment |
| missing_fields | Computed only | Diagnostics, All | Server derives from policy/type/country, not client field list | M / OM / SYS / N | Profile readiness, not approval |
| validation_errors | Computed only | Diagnostics, All | Safe field/error codes; no raw sensitive values in logs/messages | M / OM / SYS / N | Validation output only |
| profile_complete | Computed only | All | Deterministic required-field/semantic rules; not trusted browser percentage | M / OM / SYS / N | Completeness only |
| submission readiness/eligibility | Computed only, no new state | Future All | Complete + eligible country/type + valid owner authority + current draft + non-suspended context; actual B3.3 capability still unavailable | M / OM (action later O) / SYS / N | Profile-ready is not permission to submit/collect payments |

Representative contact: reuse **acting user's existing verified identity email for authentication/attestation notifications only**. It does not verify a different representative or business contact. Do not add duplicate representative email/phone by default; if representative is someone else, name/capacity remain declared and evidence/contact-proof workflow requires separate approved specification. Do not call the representative “verified” based on owner login.

### Evaluated examples deliberately deferred

| Field/category | Schema/requirement recommendation | Validation/privacy/access/public/verification/workflow |
|---|---|---|
| Tax ID / TIN / VAT ID | **Absent; do not add/collect by default.** Legal/provider necessity and entity applicability NOT VERIFIED | Future authority-specific bounded identifier; S, restricted authorized parties only, N public; separate compliance-approved tax/KYC metadata design |
| Industry/category code / MCC | Absent; activity text suffices for initial proposal; no guessed MCC | Future reviewed taxonomy and prohibited/unsupported activities policy; B, OM declared / platform assignment separate, N public; provider classification not user-selected authorization |
| Public support email/phone | Separate fields absent; do not expose or repurpose private business contact | Future explicit merchant publication consent and validation; P, OM, public only after separate approval; no change to XLipa support@xlipa.net |
| Distinct district hierarchy | Absent; not added in default schema | Future country-specific maintained reference data; P, OM, N public; address workflow only if approved |
| Representative email/phone proof | Absent, deferred until purpose/identity workflow approved | P, strict syntax + independent proof; restricted OM, N public; separate contact-verification design |
| Government IDs, date of birth, biometrics, directors/UBOs/ownership percentages | Absent, **not generic profile fields** | S, purpose-specific requiredness/verification/legal basis; dedicated future KYC model, no staff/public access; no collection now |
| Registration/incorporation certificates, authority letters, licences/address proofs | No document table/upload; no blanket document checklist | Future type/jurisdiction/provider-specific evidence; S, private authorized streaming/quarantine/scanning/retention gates; independent verification |
| Expected volumes, bank/settlement details, currency preferences | Absent; defer financial/provider design | Sensitive commercial/financial data, not profile-completeness shortcuts; separate permissions/verification, N public |

All optional/deferred examples remain **unapproved for collection**. Business_activity must not become a free-text dumping ground for deferred sensitive data; future form hints/validation should reject unintended payload categories where practical and warn against pasting IDs/documents/secrets.

### Input contract for future D implementation

PATCH allowlist is the approved merchant profile fields above only. Missing key preserves value; explicit null clears only nullable draft fields. Supplied values always validate, even when draft completeness permits missing values. `business_name` cannot be cleared. Reject id/merchant_id, creator/actor IDs, account/platform roles, operational/verification/application state, current_slot, snapshot, versions assigned by client or audit metadata. `expected_profile_version` is a validated concurrency token, never an authority grant. Attestation has its own owner-only action. No broad model fill of request payload.

## F. Privacy and Data Protection

**PROPOSED safeguards:** purpose-limited profile collection, minimum personal data, fresh tenant/role authorization, response allowlists, escaped UI, no-store/referrer protection, generic safe errors and no raw body/value logging. Business data of a sole trader may be personal data even when called “business”.

PDPC's published notice describes purpose limitation, minimization, accuracy, storage limitation, confidentiality and cross-border safeguards. These principles inform this proposal; the notice is PDPC's own processing notice, not proof of XLipa's legal basis/compliance. Exact XLipa controller/processor obligations, registration, notices, rights handling, retention and transfer grounds require qualified legal review. [PDPC privacy notice](https://pdpc.go.tz/privacy-notice/).

- Reuse existing encrypted application snapshots/internal notes; protect APP_KEY and recovery separately. New ordinary profile strings follow existing model storage convention, with access minimization and required approved encryption-at-rest/access-controlled backup strategy before real sensitive use. They are **not automatically application-encrypted**. If field-level encryption is required for representative/legal names, propose TEXT/cast/key/search impacts as a separately approved schema variation; do not claim plaintext varchar is encrypted.
- Do not store sensitive IDs/UBO/documents in ordinary profile columns. No document functionality in B3.2; future private storage, quarantine, scanning, access checks, retention/legal-hold/deletion rules precede collection.
- Success audit in same DB transaction: server actor/merchant/current draft/version/request ID + bounded approved event/reason codes only; no before/after PII dump, documents, URLs with tokens or notes. Logs for denied/error requests similarly redacted. Existing append-only model is not immutable privileged-DB protection.
- Representative information needs a transparent collection purpose and approved lawful basis; owner authority declaration is not a universal substitute for representative consent or privacy notice. If another person's details are supplied, approve the notification/authorization process first; do not send real email/SMS as an implementation side effect.
- Retention is purpose/jurisdiction dependent; define schedules for live drafts, closed accounts, applications, audit and backups through legal/operational review, including holds and data-subject requests. No invented “keep for N years” rule. No automatic deletion during schema rollback. Review OneDrive/cloud backup transfers and authorized operators; same-disk backup is not independent disaster recovery.

## G. Proposed Role-Based Permissions

Current B3.1 policies stay intact until implementation approval. Future permissions below require fresh persisted active/verified user + membership; identifiers and cached session roles never suffice.

| Future action | Owner | Manager | Staff | Platform admin/chief without membership |
|---|---|---|---|---|
| Existing safe status summary | Yes | Yes | Existing limited summary | No merchant-route bypass |
| View full profile / field readiness | Yes | Yes | No | No; separate B3.4 policy later |
| Update approved draft profile fields | Yes | Yes | No | No merchant-route permission |
| Prepare representative name/capacity | Yes | Yes | No | No |
| Attest authority | Yes, explicit declaration | No | No | No |
| Submit / next attempt in B3.3 | Owner-only after later approval | No | No | No merchant-route permission |
| Internal review notes/audit/KYC | No via merchant profile | No | No | Separate explicit reviewer permissions later |

Proposed MerchantPolicy abilities `viewProfile` and `updateProfile`; use existing `viewSummary` for status only. Application `view` remains B3.1 current-attempt scope; history/submit/startNextAttempt abilities belong B3.3. Controller calls policies; action paths recheck actor/membership/state in transaction. No generic Gate::before Chief grant. Manager-prepared authority fields never imply owner attestation.

Future surface should reuse approved B3 G3 routes: GET `/merchants/{merchant}/profile/edit`, PATCH `/merchants/{merchant}/profile`, POST `/merchants/{merchant}/authority-attestation`; no public profile API. Readiness can be embedded in authorized profile/onboarding views rather than a new endpoint. **No route/form was created in this planning task.**

## H. Profile Lifecycle

Keep existing application states **draft, pending_review, information_requested, approved, rejected**, and existing merchant operational/verification enums. Completeness is computed data, not another persisted application state. A complete profile never auto-approves merchant or enables payments.

| Situation | Proposed behavior / stage boundary |
|---|---|
| Initial incomplete profile | Reuse registration-created merchant/current draft; no guessed legal-name/country backfill. Existing business_name/phone shown for owner confirmation |
| Draft edits | Owner/manager, verified identity, current draft and allowed merchant status; validate supplied data; CAS expected profile version; audit atomic |
| Validation/readiness | Safe missing-field/error list for OM. Optional absent field does not block completeness; unsupported country/type does. Readiness not reviewed/verified status |
| Authority attestation | Owner explicitly accepts server wording/version for current profile; actor/time server-side. Changes to material profile fields invalidate attestation atomically |
| Submission | B3.3 only: fresh complete/eligible current draft + current valid authority; frozen encrypted snapshot; pending_review, not auto-approval |
| During pending review | Full profile read-only; no snapshot/version rewrite, no manager draft edits while review evidence is pending |
| Information requested | Preserve concluded attempt/feedback. B3.3 authorized owner creates next draft/current slot; then normal profile edits on that draft. No auto-reopen |
| Rejected | Read-only until separately approved reapply eligibility/cooldown policy and later owner-controlled next attempt; no automatic reapplication |
| Approved | Material changes eventually require new draft and unverified/re-review projection per parent plan; do not overwrite approved snapshot. Until that audited lifecycle action is separately implemented, B3.2-D must remain read-only for approved profiles |
| Suspended/closed merchant | Authorized reads only; profile edits cannot unsuspend/re-enable or bypass operational restrictions |

Material field set recommendation for A: all submitted legal/trading/type/registry/contact/address/representative/activity/website profile values. No silent “cosmetic” exception initially; no-op save changes neither version nor audit. Future special correction policies need approval.

Attestation binding without a ninth column: treat the stored actor/time/declaration version as valid only while no material change has occurred; same transaction clears all three on material edit. Attest action uses expected profile_version, stamps resulting version in bounded audit metadata and keeps current draft version consistent. Proposed submit requires the submitting owner to match current attester, otherwise reattest; final wording, attribution/read exposure and invalidation policy need A approval. Repeating the same valid attestation is idempotent; no duplicate success audit.

B3.2-D implements **existing current-draft edits/attestation only**. Next-attempt creation, submitted history and approved-material-change lifecycle remain later gated work. This is staged delivery of the parent lifecycle, not an alternative state machine.

## I. Additive Database Architecture Proposal

### Default minimum: two later migrations, zero now

Reuse merchants, memberships, users, applications and audit logs. No separate duplicate merchant_profiles/owners/countries/permissions/payment tables are needed. Default proposal follows approved B3 plan G1/G2 exactly:

| Proposed new merchant column | SQL/Laravel definition recommendation | Defaults/index/FK |
|---|---|---|
| legal_name | nullable string(200) | NULL; no unique/index initially |
| business_activity | nullable string(1000) | NULL; no index |
| website_url | nullable string(255) | NULL; no index |
| representative_name | nullable string(200) | NULL; no name-search index |
| representative_capacity | nullable string(100) | NULL; no index |
| authority_attestation_version | nullable string(50) | NULL; server-generated |
| authority_attested_at | nullable timestamp, UTC convention | NULL |
| authority_attested_by_user_id | nullable integer/bigint-compatible FK users.id, restrictOnDelete | NULL; explicit named lookup index proposed, preserve existing user identity |

All existing merchant rows survive with NULL new values. Requiredness at readiness is application policy, not NOT NULL backfill. Existing profile_version positive invariant stays; no revision-history table, broad PII audit snapshots or global registration-number uniqueness. `business_type` existing string handles approved values; don't change it to a new database enum without a separate reason.

Proposed future migration basenames: `add_onboarding_details_to_merchants`; `extend_audit_event_types_for_onboarding`. Timestamps assigned only during approved B stage; old applied migrations remain untouched. Audit extension adds **merchant.profile_updated** and **application.draft_created** to PHP enum and SQLite CHECK together. First event can record bounded reason `authority_attested`; second is reserved for later approved next-draft transitions, not permission to create attempts in B3.2-D.

SQLite audit CHECK extension may require controlled table rebuild/copy. Preserve all old event values, columns/types, IDs, nullable semantics, FK delete rules, composite application/merchant FK, indexes, scalar metadata and every row. No blanket dropping constraints. No new authority table and no government-document/risk/beneficial-owner schema. Alternative legal-form/registry metadata or app-encrypted TEXT needs a revised owner-approved schema before generation.

### Later isolated migration rehearsal — PROPOSED, not executed

1. Review concrete generated migration/SQL/enum changes and foreign-key dependency map; confirm SQLite engine guard and current schema fingerprint. Enumerate all indexes/triggers, including positive-version constraints.
2. Use disposable populated **synthetic** SQLite fixtures with existing account/owner/draft/historical/audit graphs. Test up/down/reapply, unique/FK/CHECK enforcement, old enum casts, new events, null/default compatibility and data preservation. No test may target working path; assert effective PDO database and testing mode first.
3. For any audit table rebuild, rehearse copy/count/ID/metadata/FK/index equality, rollback on injected failure, and independent `integrity_check`/`foreign_key_check`. Any temporary FK handling must be reviewed, connection-bounded, re-enabled and checked before use; never use constraints-off as a permanent fix.
4. Test B3.1/auth/host/DOM regressions against the migrated disposable schema and current old baseline where relevant. Synthetic encrypted snapshots must remain readable with the same test key.
5. Before approved working activation: fresh integrity/schema/count/hash preflight, no active writes, consistent private SQLite backup API, restore-to-new-file rehearsal, protected unchanged APP_KEY; stop on unexpected drift. Existing pre-user backups are not current-data recovery proof.
6. C applies only explicitly approved additive migrations to named local target; no migrate:fresh/refresh/reset/seed/import. Record exact migration IDs, counts, FK/integrity and unchanged existing-row fingerprints.
7. Rollback is not automatic: nullable-column/table down can lose new data; old audit CHECK cannot accept newly introduced event rows. Prefer forward correction; after real use, approve archive/compatibility strategy or consistent backup recovery with reconciliation of subsequent writes. Never silently delete audit events to make down pass.

### Future D write/concurrency protocol

Profile action arguments: actor, server-resolved merchant, validated explicit fields, expected profile version/current-attempt identity. Recheck fresh authorization, operational state and current draft **inside** transaction; serialize using reviewed parent merchant CAS/engine-appropriate locks. Conditional version update must affect exactly one expected row; otherwise 409, no overwrite. Audit failure rolls back profile/version/attestation/current-draft-version changes. Bounded whole-transaction retries handle SQLite busy/deadlock errors and rerun authorization/state checks; stale client version remains a conflict.

Ensure concurrent submit/review/membership/status actions eventually share the serialization protocol; existing SQLite lockForUpdate alone is insufficient. B3.3/B3.4 synchronization is an explicit later dependency, not proven here. Readiness stays side-effect-free. No scanner/network/provider/email call inside the transaction. No-op repeat saves/attests must be harmless; expected_version is not a globally persisted idempotency token.

## J. Security Threat Review

All safeguards below are **PROPOSED**, not implemented during planning.

| Threat | Required safeguard / future acceptance test |
|---|---|
| Cross-merchant profile reads | Member-scoped root context + viewProfile policy + explicit response allowlist; unrelated IDs yield 404 without data |
| Unauthorized editing | Fresh verified active owner/manager, updateProfile policy, draft/status conditions; staff denied HTTP and direct action |
| Mass assignment | FormRequest/DTO allowlist, reject privileged/unknown actor/state fields, no request all() to models |
| Role escalation | Separate membership/platform models; no Chief bypass, no role/profile field mixing; forged role input cannot change permissions |
| Forged merchant/application IDs | Server route context and scoped child relation; query/body/session IDs never select another tenant; mismatch tests |
| Stale membership | Fresh persisted role/account/version checks, including within transaction; revocation/demotion race tests; no cached role authority |
| Duplicate business identity | Existing country+number nonunique lookup + controlled human review; no automatic merging, no uniqueness assumption for branches/registries |
| Sensitive leakage / XSS | No generic model serialization; escaped plain text, no sensitive old-input/log values, no-store/referrer headers; staff/other-tenant negative assertions |
| Unsafe upload assumptions | No uploads/doc URLs/server fetch in profile stage; later private quarantine/scan/authorization policy before collecting evidence |
| Profile changes during review | Pending profile immutable; submitted snapshots append-only; approved changes require later new-draft/re-review action, no status bypass |
| Concurrent updates | Expected version CAS, whole transaction rollback/retry, affected-row check, no last-writer-wins; simultaneous save/attest/submit/revoke tests |
| Insecure audit | Same-transaction bounded event metadata; server actor/time; no PII snapshots/secrets; audit failure injection; no claim of tamper-proof DB |
| Multi-country gaps | Valid ISO code distinct from supported market; policy version/country/type tests, disabled countries denied, no universal address/phone regex |
| False contact/representative verification | Declared vs verified data separated; owner email/membership not legal authority proof; no verification badge without evidence |
| Malicious website values | HTTPS/no userinfo/control chars, length bound, no SSRF fetch; encoded/escaped rendering and no credential propagation |
| Repeated/forged attestation | Owner-only accepted server wording, version/CAS binding, invalidation on material edit, no browser actor/time, idempotency tests |

## K. Proposed B3.2 Implementation Stages

| Stage | Scope and dependencies | Acceptance/security/database safeguards | Owner approval gate |
|---|---|---|---|
| **B3.2-A — Field and policy specification** | Resolve section L; approve exact families/legal-form mapping, TZ jurisdiction, matrix bounds/requiredness, representative/attestation wording, privacy, material fields and write-state scope | Written versioned rules, complete tests/permission specification, no code/schema/data write; no invented legal/provider requirements | **Next decision only**; approval of report is not blanket B–D authorization |
| **B3.2-B — Additive schema preparation and isolated tests** | A signed off; generate only reviewed eight-column migration + audit CHECK/enum extension, fixture/factory support and tests | Rehearsal I passes; preserve rows/FKs/indexes/triggers/encryption; current B3.1/auth/host regressions; working DB untouched; present exact diff and SQL | Explicit schema-preparation/code scope approval; no working activation |
| **B3.2-C — Working-schema activation** | B reviewed/passed; fresh target preflight and consistent private backup/restore; owner confirms concrete target/files | Apply approved migrations only, no seed/reset; compare existing-row identities/data and integrity/FKs; stop drift; recovery plan agreed | **Separate explicit execution approval** for canonical working SQLite |
| **B3.2-D — Authorized profile read/write and tests** | A policy final + C activation verified; new UI/endpoints/actions separately authorized | Extend MerchantPolicy viewProfile/updateProfile; scoped requests/actions/readiness, owner-only attestation, version/CAS/audit; no mutations outside current draft; array/fake transports; no provider/KYC/payment/admin scope; full regression and unchanged working records during agent tests | Explicit D implementation approval; code/UI scope does not authorize synthetic writes to working records |

Proposed D files, not created: MerchantProfileController, UpdateMerchantProfileRequest, AttestMerchantAuthorityRequest, focused UpdateMerchantProfile/AttestMerchantAuthority actions, pure completeness service/read model, extension of MerchantPolicy, new profile views and MerchantProfileTest; existing models/casts/enums/routes updated only as approved. Do not introduce package/framework/RBAC rewrite. A route-local profile-schema readiness check should fail closed if required columns/events are absent after rollback; do not weaken existing B2 readiness gate.

Later B3.3 submission/history and B3.4 review remain separate approvals. Full Merchant/Chief dashboards, documents, payments, wallet/settlement/refund/API capability work remain outside B3.2.

## L. Open Decisions Requiring Owner Approval

1. Exact legal forms per `individual/sole_proprietor/registered_business`; eligibility of unregistered individuals/sole traders. **No automatic approval recommendation without confirmed legal/provider eligibility.**
2. Initial TZ jurisdiction/registry coverage, and whether separate legal_form/registration_authority schema is necessary. Avoid collapsing registered business names/partnerships into companies.
3. Required registration pairs and country/address rules for each enabled form; reject unsupported paths until specified. Approve server policy versioning/country allowlist maintenance owner.
4. Approve every new field/bound and draft-vs-ready requirement; decide whether legal/representative names need field-level encryption or current storage convention + stronger at-rest controls.
5. Approve representative capacity options, lawful collection/notice process for other-person information, owner declaration wording/version, attester attribution and submission actor matching.
6. Approve material field set and attestation invalidation, no-op/idempotency/CAS rules. Confirm B3.2-D current-draft-only writes while later submitted/approved transitions await B3.3.
7. Approve two-migration scope and audit event names; B preparation and C execution require separate concrete approvals. Determine safe down/forward strategy for new audit event rows.
8. Confirm TIN/VAT/IDs/UBO/documents/category codes/support-publication/financial fields stay deferred; any request to collect them needs purpose/legal/provider/privacy specification first.
9. Define operational privacy ownership, retention/access/backup/key recovery and jurisdiction-specific compliance review before collection expands. No blanket consent/compliance claim.
10. Obtain official Selcom merchant eligibility, KYC/document/currency/callback specification before provider-dependent validation or capabilities; no inferred API contract.

These are **PROPOSED decision requests**, not evidence of previously granted implementation approval. Where expanded legal-form/registry/encryption design would differ from parent B3 G1/G2, owner must explicitly approve a revised specification; default proposal has not adopted those alternatives.

## M. Risks and Dependencies

- Regulatory/provider eligibility and full legal requirements **NOT VERIFIED**. Source checks provide context only. BRELA/TNBP pages had fetch timeouts; their search-index summaries were used only to confirm high-level category distinctions, not statutory requirements. PDPC's law PDF also timed out; the successfully read official privacy notice supports general principles, not a section-by-section legal opinion. BPRA and ISO pages were read directly; ITU official result supports numbering limits. Check authoritative current rules with qualified reviewers before activation.
- Official documents must distinguish identity verification, tax registration, business registration, authority, provider underwriting and product completeness; none implies all the others.
- Existing drafts will have new NULL fields. No legal/contact facts can be inferred from registration name/email/phone; user confirmation and approved validation remain necessary.
- SQLite engine-specific migration/constraint/concurrency behavior and audit CHECK rebuild need isolated proof. No schema files or migrations were created/executed in planning; tests above exercised existing schema only.
- App-level encrypted snapshots rely on key recovery; OneDrive/private same-workspace backup is not independent disaster-recovery proof. Do not publish working DB/logs/backups.
- Browser visual/live-owner navigation remains NOT VERIFIED from B3.1. Checkpoint tests are server/DOM evidence, not responsive design certification. No frontend/public wording was changed here.
- Single-host validation and full deployment APP_URL/proxy/HTTPS/CORS prerequisites retain prior report limitations. No domain/infrastructure change or production domain selection.

## N. GO / NO-GO Recommendation

**GO — B3.1 local checkpoint completed and reverified. GO — B3.2 architecture proposal ready for owner review. NO-GO for automatic B3.2 code/schema execution or production enablement.**

Only authorized Git write was the single B3.1 checkpoint. Only new B3.2 artifact is this planning report. Existing source/migrations/frontend/config/data have not been modified during planning; no dependencies, accounts, provider calls, payments, push or deployment.

## O. Exact Next Authorized Step

Current authorized work is complete. Next **owner decision** is B3.2-A: review/approve the field/type/country/privacy/permission/attestation specification and resolve section L. No B3.2-B migration generation, C activation, D profile endpoint/form work or B3.3 is authorized by this report.

**STOP. Ninasubiri explicit approval kabla ya B3.2 implementation yoyote.**
