# XLipa — Phase 3 B3: Merchant Onboarding na Authorization Plan

Tarehe: 6 Novemba 2026, Africa/Nairobi.

Canonical root: `C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT/XLipa`.

**Status: planning proposal tu. B3 implementation haijaanza.** File mpya pekee katika kazi hii ni report hii. Hakuna application code, frontend, dependency, configuration, account, migration au working database record iliyobadilishwa. Owner amethibitisha manually registration, email verification, logout na login; read-only inspection sasa pia imeona verified user mmoja. Huo si madai ya fresh agent browser verification.

**Pendekezo kuu:** reuse native Laravel authentication, existing merchants/memberships/application attempts na audit foundation. Anza kwa merchant context, policies na read-only onboarding status bila migration. Profile editing, submission, review na future documents ziwe stages tofauti. Usiongeze financial dashboard, provider dependency au generic RBAC framework katika B3.

## A. Existing implementation assessment

### A1. Reports na actual code zilizokaguliwa

Reports: Stage A Architecture, B0/B1 Completion, B2 Completion, Local Database Activation Pre-flight, Local Database Activation, Local Authentication Verification na POST Registration 429 Investigation. Historical reports hazijaandikwa upya: statements za “migrations pending”, “users 0” na “B2 not started” zinaeleza checkpoints zao za zamani, si current state.

Actual sources: `backend/routes/web.php`, `bootstrap/app.php`, auth controllers/requests/middleware, `AppServiceProvider`, models zote sita, enums, B1 migrations sita, `CreateMerchant`, `RevokeMembership`, `RegisterMerchantOwner`, account/auth Blade views, canonical registration form, frontend sync/DOM script na authentication/database foundation tests. Hakuna `app/Policies`, profile/submission/review controller au merchant review action iliyopatikana katika application inventory.

| Eneo | Kilichopo sasa | Kinachokosekana kwa B3 |
|---|---|---|
| Identity | Native sessions; registration/login/logout/reset; signed email verification; active-account/auth-version checks; CSRF na rate limits | Hakuna haja ya auth implementation nyingine; recent password confirmation/MFA readiness kwa privileged review bado havipo |
| Registration | Atomic user + merchant + active owner + draft application + audit; notification baada ya transaction | Complete business/legal profile; hakuna taarifa za biashara zitakazobuniwa |
| Merchant context | `Merchant::forMember()` filters fresh active user na active membership; `/account` ina scoped summary | Resource policies, explicit merchant route context, scoped nested application reads na selectors |
| Membership | `owner/manager/staff`, active/revoked, unique merchant+user; many-to-many | Invite/team UI, owner transfer, role changes na specialized finance/developer permissions |
| Platform roles | Separate `chief_admin/admin` assignment model/table | Provisioning command, review routes/UI, permission management, reauthentication na MFA enforcement workflows |
| Applications | ULID, attempts, one current slot, encrypted snapshot/notes; model huzuia identity/evidence rewrite na history deletion | Profile validation, submit/resubmit transitions, reviewed-state CAS, notifications za decisions |
| Audit | Encrypted application data tofauti na bounded audit metadata; append-only model, composite application/merchant FK | Profile-edit/draft-created event types, review start events na reusable audited transition actions |
| Ownership helpers | Atomic creation; last-owner revoke guard katika `RevokeMembership` | Hizi si full team-management protocol; future mutations zote lazima zishiriki concurrency rules |
| Frontend | Existing branding/design, canonical auth HTML na generated Blade; duplicate-submit guard | Necessary onboarding screens tu; hakuna redesign inayohitajika |

`CreateMerchant` inaruhusu active unverified user kwa registration draft; usibadilishe behavior hii blindly. Future additional-merchant endpoint ihitaji verified identity na policy, lakini si sehemu ya first stage. `RevokeMembership` haina HTTP endpoint; kuwepo kwake si ruhusa ya exposing team management.

### A2. Current working database — read-only evidence

Target: `backend/database/database.sqlite`, imefunguliwa kwa PDO SQLite URI `mode=ro`; effective environment `local`, driver SQLite, canonical path imelinganishwa kabla ya read.

| Check | Result |
|---|---|
| Tables / migration records | **14 / 9** |
| Users / verified users | **1 / 1** |
| Merchants / memberships / applications / audit logs | **1 / 1 / 1 / 1** |
| Platform role assignments | **0** |
| Merchant projection | **onboarding / unverified** |
| Application | **draft** |
| Integrity / foreign-key violations | **ok / 0** |

Snapshot SHA-256: `214ae83a31f53e80d3068d1aeb1366008bb5accb2df212dd2c534ae4121b6d8f`. Historical SQL SHA-256: `33d48ec8c9e9ee07a143390e8b7d1bed6c893382ae758057cc9226c5c5a2fb8e`. Hakuna PII, token au verification URL iliyochapishwa. Working file haijaandikiwa katika planning hii. Future preflight lazima irecheck data; owner anaweza kuendelea kutumia app baada ya snapshot hii.

Historical latest full suite ni **64 tests / 543 assertions**, DOM **31 checks** baada ya 429 investigation. Latest focused auth run ni **29 / 279**. Hizo ni recorded evidence, si fresh runs katika planning hii; hakuna B3 test iliyoandikwa au inayodaiwa kupita.

## B. Merchant onboarding architecture

### B1. Journey na responsibility

1. Existing registration huanzisha draft reservation; identity email verification na login hutumia B2.
2. `/account` huonyesha active memberships; merchant mmoja anaweza kuingia status yake, wengi wachague merchant. Session-selected merchant ni convenience tu; route context na fresh membership ndiyo authorization source.
3. Owner/manager hujaza business profile; browser inaonyesha draft progress lakini server ndiyo huhesabu completeness. Save draft huruhusu incomplete fields bila kuruhusu invalid supplied values.
4. Owner huthibitisha representation/authority declaration inayotokana na policy version ya server. Manager anaweza kuandaa details, lakini hawezi kuattest au kusubmit badala ya owner.
5. Owner husubmit complete profile; server hufreeze allowlisted encrypted snapshot na kupeleka current attempt `pending_review`.
6. Merchant status page huonyesha timestamps, attempt number na public feedback; hakuna internal notes, platform audit au other-tenant data.
7. Independent authorized platform reviewer anaweza kuomba information, kuapprove au kureject. Manual review haifanyi automated legal-compliance claim.
8. Information request hufunga attempt ya awali. Owner huanzisha next draft, hujibu public feedback, husave na kusubmit attempt mpya. Old snapshot/decision haibadilishwi.
9. Rejected attempt ina appeal/reapply eligibility policy ya kuidhinishwa; default hakuna automatic reapply mpaka policy ihakikiwe. Closed/suspended restriction haiwezi kuondolewa kwa resubmission.
10. Approved merchant hupata permitted **nonfinancial** profile/status access. Financial/API capabilities hazifunguki kwa approval ya B3.

### B2. Proposed field matrix — product policy, si regulatory checklist

Business-type allowlist inayopendekezwa: `individual`, `sole_proprietor`, `registered_business`. Hakuna unsupported entity type itakayogeuzwa kimyakimya kuwa individual. Tax IDs, beneficial-owner thresholds, licences, identity documents na country-specific legal definitions bado hazijaverifyiwa; zisiwe mandatory kwa kukisia. Required fields hapa ni proposed application completeness policy tu, linalohitaji owner approval.

| Field | Individual | Sole proprietor | Registered business |
|---|---|---|---|
| `business_name` ≤200 | Required service/display name | Required trading name | Required trading/display name |
| Legal name ≤200, new profile field | Required individual legal name, supplied explicitly | Required proprietor legal name | Required registered entity legal name |
| `business_type` | Required approved value | Required approved value | Required approved value |
| Activity description ≤1000, new profile field | Required | Required | Required |
| `business_email` ≤254, valid conventional email | Required explicit contact | Required | Required |
| `business_phone` ≤16, E.164-shaped | Required | Required | Required |
| `country_code`, `address_line1`, `city` | Required operating/contact address | Required | Required |
| `region`, `postal_code` | Required only by approved country profile; otherwise optional | Same | Same |
| `address_line2` | Optional ≤255 | Optional | Optional |
| `registration_country`, `registration_number` ≤100 | Not normally requested | Conditional on declared registration and country policy | Required entity identifier/country; exceptions need explicit policy |
| Representative name/capacity, new profile fields | Self; explicitly confirmed | Proprietor or declared authorized representative | Named authorized representative; not assumed from membership |
| Authority declaration | Owner attests own capacity | Owner attests representation | Owner attests authority to act for entity |
| Website ≤255, new profile field | Optional HTTPS URL | Optional | Optional |

Country values zitumie maintained ISO alpha-2 allowlist iliyo approved/configured, si regex pekee. Supported-country list si automatically countries zote. Registration numbers ziwe bounded, trimmed na country-aware; usiondoe punctuation/case indiscriminately au kuweka global uniqueness ambayo inaweza kuzuia branches halali. Duplicate legal entities ziende review, si guessed identity merge.

B2 identity email na `users.name` si proof ya business contact/legal name; hakuna automatic legal-name backfill. Existing business name/phone zionyeshwe kama draft values, owner azihakiki. Phone format validation si ownership verification. Country-unknown national phone input ikataliwe/ielezwe badala ya kuforce +255 kwa kila nchi; existing registration normalization ibaki kama B2 behavior mpaka separate change approval.

Ownership information ya B3 limited scope ni declared legal person/proprietor/entity na representative authority. Membership `owner` ni application permission, **si evidence ya legal/beneficial ownership**. Full directors/beneficial-owners list, percentage interests, identity proof na jurisdiction-specific thresholds ni deferred due-diligence design. Ikiwa owner anataka KYC-ready approval badala ya nonfinancial profile review, collection policy, legal/privacy review na secure document stage vitangulie; usiite incomplete profile review “KYC approved”.

Draft validation: nullable/absent incomplete fields; supplied values lazima zipite types/lengths. Submit validation: full required matrix + recent owner authority declaration, active verified actor, current draft, merchant not suspended/closed. Reject client-supplied `merchant_id`, status, role, creator/reviewer IDs, slot, attempt number, snapshot au audit actor. Expected version/application ID ni concurrency input tu, si ownership grant.

## C. Application lifecycle na state transitions

**Default recommendation: hifadhi five existing database states.** Required user-facing labels saba zinaweza kuwakilishwa bila kuandika upya historical state enum:

| Product label | Persisted representation | Actor/evidence |
|---|---|---|
| Draft | `draft` | Owner/manager edits current profile; owner attests |
| Submitted | Submit event + `submitted_at`; state `pending_review` | Owner freezes snapshot |
| Under review | `pending_review`; optional future explicit review-start metadata | Platform reviewer; mpaka metadata iwepo label iwe “Awaiting review”, usibuni reviewer activity |
| More information required | `information_requested` | Reviewer decision + public feedback |
| Resubmitted | New attempt `pending_review` + submit event; prior submitted attempt exists | Owner; attempt>1 pekee si proof ikiwa earlier draft haikusubmitiwa |
| Approved | `approved` | Independent authorized reviewer decision |
| Rejected | `rejected` | Independent authorized reviewer + bounded public reason |

Ikiwa exact persisted `submitted`, `under_review`, `resubmitted` states zinahitajika, hiyo ni **alternative schema/workflow change requiring approval**, si silent replacement ya Stage A. Recommended optional review-start fields ziko G; hazihitajiki kwa first stage.

| From → to | Preconditions/actor | Transactional effects |
|---|---|---|
| Current draft → pending_review | Owner, complete profile, authority, expected version/attempt | Freeze snapshot; submitter/time; merchant verification pending; audit |
| Pending_review → information_requested | Independent reviewer, fresh state/version, recent reauth | Immutable old snapshot; reviewer/time/public feedback; needs_information |
| Pending_review → approved | Same; current profile equals submitted version | verified; onboarding→enabled only if not suspended/closed; audit |
| Pending_review → rejected | Same | rejected verification; onboarding stays onboarding; public feedback/audit |
| Information_requested → new draft | Owner, expected current attempt, no suspended/closed bypass | Old slot NULL, next attempt slot1; preserve old state; needs_information until next submit |
| Rejected → new draft | Owner only if separately approved reapply policy permits | Same replacement; verification rejected until resubmit |
| Approved → new draft after material edit | Owner/manager allowed edit, expected version | Old approved attempt becomes historical; fresh draft; unverified; enabled→onboarding |

Draft→draft changes do not create attempts per keystroke. Profile edits increment `merchants.profile_version` once per committed material change; sync draft version consistently. Pending-review identity/contact/address data is read-only. No direct approved→rejected rewrite; future suspension/closure is operational action with separate audit. Approval never reinstates suspended/closed merchant. Proposed operational override: suspended/closed are read-only for merchant users; platform exceptional profile correction would need separate reviewed action.

Every stable merchant workflow must retain one current attempt and an active owner; existing DB uniqueness enforces **at most one** current slot, not “at least one”. Actions enforce full invariant within transaction. No browser-delete application endpoint. No history snapshot overwrite, no reusing old attempt numbers, no unreviewed reopening.

## D. Merchant authorization na tenant isolation

### D1. Existing roles first

| Requested role | Mapping/recommendation |
|---|---|
| Owner | Existing `owner`; profile, authority attestation, submission/reapply |
| Merchant Administrator | UI description for existing `manager` in initial scope; never platform `admin` |
| Finance | Future narrow capability role; do not map to manager just to obtain access; no financial feature now |
| Developer | Future API/webhook capabilities; no keys or tokens in B3 |
| Support / Viewer | Existing `staff` limited safe summary; richer support read permissions require separate scope |

Hifadhi enum/table roles `owner/manager/staff` kwa initial B3. New roles zitahitaji explicit permission matrix, migration ya allowed-values, compatibility tests na owner approval. Hakuna generic role editor/permission catalogue package inayohitajika sasa.

| Action | Owner | Manager | Staff | Platform Admin/Chief bila membership |
|---|---|---|---|---|
| Read merchant safe status | Ndiyo | Ndiyo | Limited summary | Only separate authorized review path |
| Read full editable profile | Ndiyo | Ndiyo | Hapana | Review scope only |
| Save draft | Ndiyo | Ndiyo | Hapana | Hapana kupitia merchant route |
| Attest authority / submit / reapply | Ndiyo | Hapana | Hapana | Hapana |
| Read public application history | Ndiyo | Ndiyo | Status only | Review scope only |
| Read internal notes/platform audit | Hapana | Hapana | Hapana | Explicit policy scope |
| Invite staff / change roles / transfer owner | Deferred | Deferred | Hapana | Deferred controlled administration |
| Financial/API capabilities | None in B3 | None | None | None |

### D2. Enforcement chain

Web session + CSRF → auth → existing readiness + fresh active/auth-version → verified email → member-scoped merchant resolution → scoped nested application → resource policy → action fresh checks/transaction. Authentication response privacy headers and redacted error handling must extend to future `merchants/*` and `admin/*`; current exception path allowlist covers auth/account only.

Proposed `ResolveMerchantContext` itumie `Merchant::forMember($actor)`; unknown/cross-tenant merchant returns 404. Known own merchant action without permission returns 403. Child application lazima itoke `$merchant->applications()`; scope bindings pekee hazichuji root merchant kwa membership. Mismatched route/query/body IDs cannot bypass scoping. ULID si secret.

Policies: `MerchantPolicy` (`viewSummary`, `viewProfile`, `updateProfile`), `MerchantApplicationPolicy` (`view`, `submit`, `startNextAttempt`), separate platform review/audit policies. Role/status/isolation rules zisitegemee frontend visibility. Explicit response resources/view models zisitumie generic `toArray()` ya merchant/application; staff response isitoe addresses/contact details au internal notes. No blanket Chief `Gate::before` true.

Direct actions/jobs ziwe na actor+merchant context na authorization; middleware si protection ya CLI/background calls. Fresh membership read kila request; revocation/status changes zisitumie cached role kwenye session. Privileged role changes zitahitaji auth-version revocation policy. Last-owner demotion/revocation/transfer paths zote zifuate parent merchant serialization protocol. Existing revoke helper alone si proof ya all concurrent membership scenarios.

Laravel supports resource policies/gates; hapa ni proposed use ya native facilities, si package mpya. [Laravel 13 authorization](https://laravel.com/framework/docs/13.x/authorization).

## E. Chief Admin review architecture

Separate namespace `Admin/`, `/admin/merchant-applications/...`, review-specific policies/actions na UI baadaye. Merchant Dashboard haitaonyesha Chief Admin routes/notes au kujipa platform privileges kupitia registration.

Queue/detail: authorized platform reviewer aone only allowed review evidence; paginate/filter explicitly. Review decision ihitaji active verified platform assignment, recent password confirmation (proposed five minutes), current attempt/status/profile version na no conflict. Reviewer aliyesubmit au **aliyewahi kuwa merchant member**, hata revoked, asireview merchant huyo. No Chief override. Assignment si grant ya merchant owner powers.

Chief/Admin wanaweza kufanya independent profile review chini ya policies; Chief pekee apendekezwe suspension/reinstatement. Staff-role grants/last-Chief protection ni separate controlled process; hakuna public signup, seed credentials au real administrator provisioning katika planning/first stage. Platform staff permissions zisichanganywe na merchant team permissions.

Future review responsibilities: application decisions/public feedback, private notes, KYC evidence review once approved infrastructure exists, risk flag management na audit read. Risk flags zisiwe arbitrary booleans kutoka merchant form. Future `merchant_risk_flags` proposal: ULID, merchant FK, bounded code/severity/state, actor, encrypted rationale, opened/resolved timestamps and resolver; approval block/release rules zinahitaji risk policy. Hakuna legal/sanctions scoring inayobuniwa.

KYC review na document download hazifunguki kwa profile-review approval pekee. Full Chief Dashboard, staff UI, KYC/risk tables na actual admin account creation ziko nje ya first B3 implementation. Nonlocal privileged writes zifail closed mpaka approved MFA + HTTPS readiness ziwepo; current B2 local/testing gate isibypassiwe ili kudai production readiness. Password confirmation, MFA package decision na provisioning tests ni prerequisites za future operator writes, si existing features.

Notifications za submit/decision ziende kwa current authorized active owner contacts after commit, local log/fakes only. No internal notes in emails. SMTP/external delivery na production queues require separate approval. Durable notification outbox inaweza kuongezwa baadaye ikiwa delivery guarantees zinahitajika; message failure isirudishe already committed business transition au ku-trigger duplicate review.

## F. KYC na future document security plan

**Hakuna upload implementation au document collection katika B3 default scope.** Exact documents, legal basis, supported jurisdictions, beneficial-owner rules, retention periods na service-provider requirements lazima ziverifyiwe kabla ya collection; hakuna specific regulatory claim katika report hii.

Future architecture:

- Dedicated private disk/bucket outside web root, no public symlink; quarantine and clean areas, random object keys. Original filenames ni untrusted metadata, si storage paths. Managed encryption at rest na restricted key/service access; APP_KEY snapshots need separate recovery plan.
- Proposed allowlist for review: PDF, JPEG, PNG only, ≤10 MiB/file, ≤10 files/attempt and aggregate quota to approve; reject archives, SVG, executables na Office files unless separately reviewed. These limits are starting product proposals, si accepted legal standard.
- Validate extension, server-detected MIME, file signatures, dimensions/pages/resource limits; browser Content-Type si proof. Authenticated owner upload to tenant-bound application, CSRF, quotas na upload rate limits. Do not accept arbitrary remote URL fetches.
- Dedicated isolated scanner job/service with bounded CPU/memory/timeouts, updated antivirus, optional PDF/image sanitization/CDR. Pending scan/error/timeout fails closed: no reviewer download or evidence approval. Do not submit private KYC to public multi-tenant scanning services without privacy/vendor approval.
- DB state machine `quarantined → clean|rejected|scan_failed`; review only clean evidence. Object checksum binds scan result to exact bytes. Crash/orphan reconciliation required; object store and DB are not a single ACID transaction.
- Future authorized streaming controller rechecks actor/tenant/application/document association at every access, attachment disposition, nosniff/no-store. No permanent public URL; any short-lived download URL needs audience/scope/expiry policy. Audit sensitive reads without contents/URLs.
- Retention/deletion schedule approved per data category/jurisdiction; legal holds block deletion; controlled purge covers objects, metadata and backup expiry policy. No invented retention duration. Record deletion completion and retries; migration rollback is not retention enforcement.
- Collect minimum PII, encrypt sensitive metadata, redact logs, restrict support/ops access; record purpose/privacy notice version and consent/other applicable basis after review. Key rotation/recovery, breach response and data-subject request processes need operational ownership.

Future `verification_documents`: ULID; merchant/application FKs with enforced composite association; uploader/reviewer users; type, random storage key, server MIME/size/checksum; scan/review states; created/scanned/reviewed/retention/hold/deletion timestamps. No document bytes in audit/SQL snapshots. Corrected submission gets new evidence association/version; historical submitted evidence is not overwritten. A reviewer seeing stale documents must not approve newer attempt.

These controls adapt private storage, content checks, size limits and scanning guidance; they do not establish legal compliance. [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).

## G. Database na API change proposals

### G1. Reuse na missing data

Reuse `users` identity/status/version; `merchants` business/contact/address/status/version; `merchant_memberships`; `platform_role_assignments`; `merchant_applications`; `audit_logs`. Original Laravel auth/session/cache/job tables zibaki. Bigint user IDs na merchant/application/audit ULIDs hazibadilishwi.

Missing profile fields: legal name, activity description, optional website, representative name/capacity na versioned authority attestation. **Recommended small additive migration**, after separate schema approval: `add_onboarding_details_to_merchants`:

| Proposed nullable addition | Bound/behavior |
|---|---|
| `legal_name` | string(200), explicitly supplied; existing row stays NULL |
| `business_activity` | string(1000), plain text |
| `website_url` | string(255), HTTPS only, never fetched by server |
| `representative_name` | string(200); sensitive business/identity contact |
| `representative_capacity` | string(100), approved bounded choices/free-text policy |
| `authority_attestation_version` | string(50), server-chosen |
| `authority_attested_at` | nullable UTC timestamp |
| `authority_attested_by_user_id` | nullable matching users FK restrict delete |

No guessed backfill. Attestation acceptance action stamps actor/time/version; material legal/representative changes invalidate attestation and require owner to accept again. Submitted encrypted snapshot includes allowlisted profile fields + attestation evidence; metadata audit excludes names/contact values. Existing merchant columns contain contact PII in normal storage; apply access minimization and encrypted-at-rest deployment storage. Sensitive government IDs/UBO details must not be added to these plain fields; future dedicated encrypted design is separate.

`business_type` already exists as nullable bounded string; no new role/type table needed for fixed approved allowlist. `registration_country/number` and structured address already exist. Fields can remain incomplete for existing draft; no migration-created legal facts.

### G2. Proposed migration inventory — files NOT created

| Proposal | When needed / risk |
|---|---|
| `add_onboarding_details_to_merchants` | Before full profile/attestation writes; additive nullable fields and FK; preserve rows |
| `extend_audit_event_types_for_onboarding` | Before profile edits/new attempts: add `merchant.profile_updated`, `application.draft_created`; PHP enum and DB allowed-values must match |
| `add_review_start_metadata_to_merchant_applications` | Optional future real Under review stage: nullable `review_started_by_user_id`, `review_started_at`; separate from decision reviewer/time; audit event `application.review_started` requires matching allowlist extension |
| `create_verification_documents` | Deferred; only after F prerequisites/legal/privacy approval |
| `create_merchant_risk_flags` | Deferred Chief/risk policy scope |

No migration is required for recommended first read-only stage or basic submit states. Exact filenames get implementation-time timestamps; no old `2026_11_06_*` applied migration edit. Audit enum is an existing SQLite CHECK constraint: extension may require reviewed table rebuild/copy preserving every row/FK/index and application data, not merely adding a PHP enum case. Rehearse from disposable populated copy, compare IDs/counts/metadata/constraints and restore-to-new-file before activation approval. Do not weaken all allowed-value constraints to avoid migration review.

No generic idempotency table by default: conditional transitions, unique attempt/slot constraints and repeat-safe actions suffice for current server forms. External API replay guarantees/outbox can justify future tables with explicit requirements. No permissions/payments/ledger/balances/settlements/API-credential tables.

### G3. Proposed web route/controller surface — none exists yet

| Method/path | Handler and permission |
|---|---|
| GET `/account` | Existing AccountController; keep safe scoped selector |
| GET `/merchants/{merchant}/onboarding` | `MerchantOnboardingController@show`, summary policy |
| GET `/merchants/{merchant}/profile/edit` | `MerchantProfileController@edit`, owner/manager full profile |
| PATCH `/merchants/{merchant}/profile` | `update`, validated allowlist + expected profile version |
| POST `/merchants/{merchant}/authority-attestation` | Owner only, validated explicit acceptance + version |
| GET `/merchants/{merchant}/applications` | `MerchantApplicationController@index`, role-limited history |
| GET `/merchants/{merchant}/applications/{application}` | Scoped safe detail, redacted by role |
| POST `/merchants/{merchant}/applications/{application}/submit` | Owner, current draft, complete profile, expected version |
| POST `/merchants/{merchant}/applications/{application}/next-attempt` | Owner, eligible concluded current attempt, repeat-safe |
| GET `/admin/merchant-applications` and `/{application}` | Future separate review controller + platform review policy |
| POST `/admin/merchant-applications/{application}/decision` | Future authorized recent-reauth decision action |

All state mutations use web CSRF; no GET business mutations. No Sanctum/JWT/public merchant API is needed for this server-rendered milestone. Future versioned APIs should reuse actions/policies and explicit resources, not add duplicate business logic. Future document/risk/role routes are intentionally unspecified until their scope is approved.

Validation errors: HTML escaped errors + safe old input; JSON 422. Conflicts 409 with refresh/retry instruction; 404 inaccessible tenant; 403 known unauthorized action; 429 Retry-After; unknown exceptions generic/redacted. No new rows or success notification on denied/conflicting request. Proposed save limit 30/min user+merchant; submit 5/hour user+merchant; decision 10/min reviewer, to approve/tune with tests. Keep existing registration 5/hour/IP unchanged; live validation probes must not exhaust owner's working quota.

### G4. Transaction, audit, concurrency na idempotency design

Actions proposed: `UpdateMerchantProfile`, `AttestMerchantAuthority`, `SubmitMerchantApplication`, `StartNextMerchantApplication`, future `ReviewMerchantApplication`; shared `RecordAuditEvent` can extract existing behavior without duplicating controller logic.

Profile save transaction: fresh actor/membership/status → expected current attempt/profile_version → conditional profile-version increment → allowlisted field writes/attestation invalidation → draft projection or approved→new-draft reset → bounded audit → commit. Audit failure rolls back profile and attempts. Snapshot for pending/approved old evidence remains untouched.

Submit transaction: fresh owner/verified/status → current draft ID/version → completeness/attestation → encrypted snapshot and submitted actor/time → CAS draft→pending_review → merchant pending projection → audit. Repeat exact submission already pending at same profile version returns safe current result with no duplicate success audit; changed version/status returns 409. Any public result is authorized first.

Next-attempt transaction: expected current concluded attempt ID + eligibility → CAS old slot1→NULL → allocate max(attempt_number)+1 under parent protocol → insert new slot1 draft → audit. Duplicate stale request that already produced a matching next draft returns that result after authorization; never creates another draft blindly. Unique constraints and rollback cover races; draft identity is not client assigned.

Review transaction: recheck platform assignment, no-conflict and current snapshot/version → CAS pending_review→decision → reviewer/time/public/internal feedback → merchant verification/operational projection respecting suspension → audit. Exactly one conflicting decision can win; later attempt returns conflict, not second decision/notification. Notifications after commit; outbox delivery guarantees deferred.

Existing audit events `application.submitted` and `application.reviewed` cover submit/decision; proposed profile/new-draft/review-start require new allowed types. Metadata stays scalar ≤2048 bytes with current allowlist (`from`, `to`, `role`, `previous_role`, `profile_version`, `reason_code`); reason codes are approved codes, not PII/free-text notes. Actor, request correlation and timestamps server-side. Denials go to separate redacted security log, never success audit. Model append-only protections do not stop raw SQL/privileged DB tampering.

SQLite `lockForUpdate()` alone is insufficient. Proposed protocol: parent merchant version conditional UPDATE as serialization/CAS point inside transaction, then fresh reads; check affected rows and rollback on mismatch; bounded SQLITE_BUSY/deadlock retries rerun entire authorized action. Same protocol must cover profile/status/membership transitions; membership/account suspension freshness and decision races require explicit tests and engine-appropriate user/assignment locks or version guards. Unique attempt/slot constraints remain final backstop. Do not run scanner/network/mail inside lock-held transaction.

Future production engine has not been selected. Existing B1 migrations explicitly reject non-SQLite; numeric triggers and slot CHECK representation require deliberate port. Review actual InnoDB/PostgreSQL transaction isolation, FK/NULL uniqueness, lock ordering and concurrent tests before production claims. This plan does not grant engine setup or production access.

## H. B3 implementation milestones

Milestones hapa ni proposals, si instructions za kuanza sasa. Planning approval, implementation approval na working-schema activation ni gates tofauti.

| Stage | Scope / likely files | DB impact | Security, tests na acceptance | Dependency/risk |
|---|---|---|---|---|
| B3.1 — Context/status | New `Http/Middleware/ResolveMerchantContext.php`, `Policies/MerchantPolicy.php`, `MerchantApplicationPolicy.php`, onboarding controller, explicit safe summary view model, `views/merchants/onboarding.blade.php`; update routes/account links/header error scope; tests `MerchantAccessTest` | **None**; no merchant writes | Auth/verified/version, active scoped memberships, owner/manager/staff-safe summary, nested IDOR, no platform bypass; current draft visible, unauthorized tenant 404; no broken edit/submit links | Owner approves implementation only; preserve B2 account behavior and design |
| B3.2 — Profile foundation | Prepare two G2 migrations, requests/actions profile+attestation, update merchant casts/relationships, AuditEvent, factories; edit view/test classes | Nullable merchant fields + audit enum extension, **isolated tests only until activation approval** | Field matrix, draft/save vs submit rules, no mass assignment, CAS conflicts, same-transaction audit rollback, attestation invalidation; profile saves tested in disposable DB | Owner approves types/fields/privacy/countries first; then exact schema review and separate local activation |
| B3.3 — Submit/history | Submit/next-attempt actions/controller/requests, explicit serializers, history/status views, local notifications, policies/limiters, lifecycle tests | Reuse activated tables; no new schema if G2 ready | Immutable snapshot, current-slot/attempt uniqueness, owner-only submission, pending edit block, idempotency, rollback/retries, no email→merchant approval; status/history correct | Needs B3.2 activation and approved reapply/material-change rules; pending state legitimate without admin yet |
| B3.4 — Restricted review foundation | `Admin/MerchantApplicationReviewController`, Review action/policy, audit policy, reauth/readiness middleware + confirm-password flow, fake/local decision notices/tests; optional start metadata | Existing decisions schema; optional review-start migration separately approved | No self/past-member review, stale/double decision deny, admin/chief boundaries, no suspension bypass, private notes redaction, audit failure rollback; no real admin creation | Separate explicit approval; MFA/HTTPS prerequisite outside local/testing; operator dashboard/provisioning separate |
| B3.5 — Acceptance/hardening | Focused security/regression tests, manual browser QA, docs, accessibility fixes consistent with design | None planned | Full auth/foundation suite, all role/tenant/status combinations, profile/state race tests, browser mobile/desktop checks, no external provider resources; actual evidence recorded | Target-engine concurrency still needs separate environment approval; no production readiness claim |
| Deferred KYC/team/finance | Document infrastructure, risk flags, invites/owner transfer, specialized roles, financial/API sections | New reviewed proposals only | Dedicated threat/privacy/operational review, isolated constraints/integration tests and separate acceptance | Not automatic B3 continuation; provider docs/credentials absent |

Each implementation report must list exact files, isolated results, working hash/count preservation, remaining risks and next approval gate. No working DB mutation justified by code approval alone where schema activation/data seeding is not authorized.

## I. Security na testing strategy

### I1. Acceptance matrix to build later

- Access: guest, unverified, suspended/closed identity, stale/missing auth_version, revoked membership, another merchant, mismatched nested application, forged role/status/ID payload. Exercise HTTP and direct action paths.
- Roles: owner submit/attest; manager draft edit but no submit; staff safe fields only; platform role alone cannot use merchant path; no blanket Chief bypass; never source ownership from creator alone.
- Profile: every type matrix, partial draft, malformed fields, country/phone normalization, unsupported type/country, no guessed legal backfill; stale version and no-op saves; stored/rendered XSS; no sensitive old input/logs.
- Applications: complete snapshot, immutable historical data, pending read-only, information-request/new attempt, rejected reapply denied by default, approved material-edit re-review, suspended/closed restrictions. Repeat submit/resubmit doesn't duplicate attempts/audit/notices.
- Transactions: inject audit/constraint failure after each mutation step; assert full rollback. Concurrent duplicate submits, two next-attempt requests, save vs submit, review vs profile edit, review vs suspension, last-owner concurrent changes. Assert affected-row CAS result, one current slot and correct state projection.
- Privacy: resource allowlists, internal-note/PII redaction across views/JSON/email/logs, denied downloads, no tokens/request body in audit; headers on merchant/admin error responses.
- Auth regressions: B2 registration/login/logout/reset/verification, no auto-approval, rate-limit expiry/Retry-After/CSRF; no real email. Do not reset working quotas as test cleanup.
- Database: additive up/down on populated disposable copy, constraints/triggers/FK preservation, encrypted data readability under existing key, migration conflict guard, no original migration edit; target-engine tests later.
- Frontend: existing menu/reveal/duplicate guard, accessible fields/errors/focus, safe retry/back behavior, 375/390/768/desktop screenshots and console/network inspection. DOM/jsdom is not visual responsiveness proof.

Strict test connection checks precede all schema work: `testing`, SQLite, configured/effective `:memory:`, no URL override, FK enabled; any file-based concurrency fixture is random disposable test-only DB. Notification/mail fakes, no network/provider calls. Do not point RefreshDatabase/factories at canonical working DB. Plan-only work does not need new tests or a fresh suite; implementation stages must run focused tests then full required regressions with actual totals.

### I2. Dashboard foundation na deferred sections

Reuse XLipa assets, colors, typography and Single-X branding. New functional views belong outside generated `views/xlipa/`; any canonical HTML changes go through `Sync-Frontend.ps1`. No new frontend framework/design system. Owner/manager see completeness/status/public history; staff see safe summary. New form controls adopt existing XLipa button/field styles and keyboard focus; prior verification-button proposal is not implemented by this planning task.

| Future section | B3 treatment |
|---|---|
| Overview | Real onboarding status/completeness, no fake revenue/charts |
| Transactions | Deferred; no endpoint/data simulator |
| Payment Links | Deferred provider/payment capability |
| Wallet | Deferred; no fabricated balance or placeholder zero presented as financial truth |
| Withdrawals / Settlements | Deferred; require separate financial architecture |
| Reports | Onboarding status/history only; financial reporting deferred |
| API Keys / Webhooks | Deferred; no credentials or signing secrets created |
| Notifications | Local onboarding feedback/status only; inbox schema/external delivery separate |
| Support | Existing support navigation; no new ticket system assumed |
| Account Settings | Own permitted profile/status and existing logout; team/identity-change features separately approved |

Do not add clickable dead financial controls to suggest readiness. Merchant approval, capability eligibility and actual enabled feature remain separate gates; no Selcom API contract is invented. BongoPay remains external reference only, with no runtime/data/code dependency.

## J. Risks, unresolved decisions na owner approvals

1. **First-stage scope:** approve B3.1 only or a later explicitly named stage. This plan itself is not implementation approval.
2. **Business policy:** approved business types, supported countries, required address fields, definition of material change, representative authority wording/version and legal-name handling. Matrix B2 is proposed product validation, not verified law.
3. **KYC boundary:** approve profile-only nonfinancial review; otherwise obtain verified jurisdiction/provider requirements before collecting IDs/ownership evidence or claiming KYC approval. Privacy/retention/scanner/storage budgets need separate owners.
4. **Lifecycle:** approve five stored states with event-based Submitted/Resubmitted and truthful Awaiting review; optional explicit Under review metadata vs alternate state migration must be decided before review-stage implementation.
5. **Reapplication:** information-request allows next attempt; rejection defaults to no automatic reapply until eligibility/cooldown/reason policy approved. Material approved-profile edits invalidate verification and pause permitted enabled projection; no suspension bypass.
6. **Roles:** retain owner/manager/staff. Mapping manager UI to merchant administrator must not be confused with platform admin; finance/developer/support expansion needs separate capability matrix/migration, not broad manager grant.
7. **Schema gates:** approve exact proposed fields/audit events before generating migrations; review rehearsed concrete files; separate explicit canonical local execution approval with fresh consistent backup. Old pre-user backups are not current-data recovery snapshots. No auto-reset/rollback or SQL import.
8. **Privileged operations:** real Chief provisioning, staff permissions, MFA/HTTPS readiness, password confirmation and conflict recovery need separate operational decisions. No account is created now.
9. **Concurrency:** SQLite tests cannot prove production row-lock/deadlock behavior. All status/membership actions must use agreed CAS/serialization protocol; target engine and its test environment remain undecided.
10. **Privacy/recovery:** encrypted snapshots depend on key recovery; existing local email logs/private verification opener contain temporary secret material. Keep them private and excluded from commits; cloud-synced workspace needs a reviewed data-handling/backup policy. No secret files have been read into this report or deleted.
11. **Audit/delivery:** application append-only is not immutable DB storage; retention/external audit export and durable notification delivery are future operations. No compliance certification is claimed.
12. **Manual verification:** owner confirmed B2 flows, and fresh read-only DB confirms verified identity; current merchant approval is still unverified. Existing browser limitations/source tests do not establish new B3 visual correctness.

## K. Recommended first implementation stage

**Approve B3.1 — Merchant Context, Authorization Policies and Read-only Onboarding Status only.**

Concrete acceptance: verified active user navigates `/account` to own merchant status, sees real draft/unverified state, switches only among active memberships; manager/staff get their allowed summaries, unauthorized/cross-merchant requests are denied, no internal notes leak, existing auth/design/navigation remain intact. There is no profile save, submit/review, invite, KYC upload, administrator creation or financial feature in this first stage.

Database impact: **no migration and no business-data write**. Policies/tests use synthetic isolated fixtures. Working data/hash/counts must be rechecked read-only before and after implementation, without publishing PII. New schema preparation/activation remains separate for B3.2; workflow/review implementation approvals remain separate thereafter.

Planning verification: source/schema/report inspection and read-only database integrity/counts, no application/test/migration execution changes. No runtime registration/verification POST was sent during this task. Only this Markdown report has been created.

**STOP: B3 planning imekamilika. Ninasubiri owner approval kabla ya implementation.**
