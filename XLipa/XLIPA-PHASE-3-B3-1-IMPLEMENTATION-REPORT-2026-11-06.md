# XLipa Website — Phase 3 B3.1 Implementation

Tarehe: 6 Novemba 2026, Africa/Nairobi. **Milestone: Merchant Context, Authorization Policies and Read-only Onboarding Status.**

**B3.1 IMPLEMENTED AND VERIFIED katika local/isolated scope.** Full suite: **108 tests / 847 assertions**; frontend interaction checks **31**. Working business database haijabadilika. Hakuna B3.2, payment processing, complete dashboard au deployment iliyoongezwa.

Alama: **VERIFIED** = fresh source/command/test/database evidence; **NOT VERIFIED** = haijajaribiwa; **REQUIRES APPROVAL** = scope inayofuata, si automatic continuation.

## A. Git Safety Checkpoint

Canonical project `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`; Laravel root `XLipa\backend`; authoritative frontend `XLipa\frontend`; Git root ni parent `WEB-DEVELOPMENT`. Branch main; initial HEAD `da596994e6b25f21e8db2a1c04190e71bd77ec5e`.

Initial changes zilikuwa completed trusted-host/bootstrap/test work, README reconciliation, proposed patch na recovery/safety/review/implementation reports pekee. Staged area ilikuwa tupu. Contents/diffs zilikaguliwa, sensitive-pattern scan ya files zote 8 ikapata matches 0; references ni synthetic fixtures/public support address, aggregate counts na hashes, bila working-account PII/credentials. Huo ni bounded current-file review, si historic repository secret certification. `.env`, working SQLite, backups na recovery artifacts zimehakikiwa ignored na hazikuwekwa kwenye checkpoint.

Fresh preflight baseline ilipita **84 tests / 603 assertions** kabla ya B3.1. Local checkpoint imeundwa kwa explicit file-path staging, bila push:

**`d195d108de1ea0ba48ec628129942f241d7020ec`** — `chore(xlipa): checkpoint recovery audit and trusted-host hardening`.

Files 8 zilizojumuishwa:

- `XLipa/README.md`
- `XLipa/backend/bootstrap/app.php`
- `XLipa/backend/tests/Feature/TrustedHostsTest.php`
- `XLipa/PRE-B3-1-TRUSTED-HOSTS-PROPOSED.patch`
- `XLipa/XLIPA-POST-REINSTALL-RECOVERY-AUDIT-2026-11-06.md`
- `XLipa/XLIPA-PRE-B3-1-SAFETY-HARDENING-REPORT-2026-11-06.md`
- `XLipa/XLIPA-TRUSTED-HOST-PATCH-REVIEW-2026-11-06.md`
- `XLipa/XLIPA-TRUSTED-HOST-IMPLEMENTATION-REPORT-2026-11-06.md`

Automatic approval review ilikataa initial commit attempt kwa kudhani commit authorization haipo. Current user attachment section 4, “You may create a local Git commit…”, ilisomwa tena pamoja na staged file list; retry yenye ushahidi huo iliruhusiwa. Hakuna bypass au history rewrite. Checkpoint ilifanikiwa kabla ya B3.1 kuanza.

**B3.1 changes hazijacommitiwa au kustageiwa.** HEAD bado ni checkpoint hapo juu; hakuna remote push.

## B. Existing Architecture and Plan Alignment

Authoritative B3 plan sections D, G3, H na K zimefuatwa: native session auth; fresh user/account/auth-version/verified-email chain; `Merchant::forMember()` scoped root resolution; explicit read policies; safe summary view model; new onboarding status view; account selector links; private/redacted merchant errors. Stage A/B2/recovery/safety/trusted-host reports na actual source zimelinganishwa na schema/factories zilizopo.

Hakuna conflict ya scope iliyobainika. Multi-merchant behavior imeelezwa kwenye plan: `/account` huonyesha active memberships; user achague merchant kwa explicit route. Hakuna auto-select ya first merchant, session-based authority au new switching/session-write endpoint. Existing schema na owner/manager/staff roles zimetumika; hakuna RBAC dependency au schema change.

Application-policy `view` katika milestone hii inaruhusu **current attempt tu**, owner/manager pekee. Historical attempt/detail routes hazijaongezwa: history ni B3.3. Policies za profile/update/submit/reapply/review hazijaongezwa mapema; undefined abilities hubaki denied. Current-attempt association policy inatest mismatched tenant IDs bila kuanzisha out-of-scope nested endpoint.

## C. Files Created or Modified

Paths zifuatazo ni relative kwa `XLipa/`:

| File | Kazi |
|---|---|
| `backend/app/Http/Middleware/ResolveMerchantContext.php` — new | Fresh member-scoped root merchant kutoka route |
| `backend/app/Policies/MerchantPolicy.php` — new | Verified active actor + fresh active membership kwa safe summary |
| `backend/app/Policies/MerchantApplicationPolicy.php` — new | Owner/manager + current application ya merchant aliyeruhusiwa |
| `backend/app/Http/Controllers/MerchantOnboardingController.php` — new | Server-side Gate checks, current-attempt projection na safe response |
| `backend/app/ViewModels/MerchantOnboardingSummary.php` — new | Explicit readonly display allowlist; no generic model serialization |
| `backend/resources/views/merchants/onboarding.blade.php` — new | Read-only page inayoreuse existing auth layout/assets |
| `backend/tests/Feature/MerchantAccessTest.php` — new | Isolated access/role/isolation/read-only/security tests |
| `backend/routes/web.php` | Single GET/HEAD onboarding route ndani ya existing protected chain |
| `backend/resources/views/account/show.blade.php` | Existing merchant names zimeongezewa named-route links |
| `backend/bootstrap/app.php` | Merchant exception/header scope imeongezwa; reviewed trusted-host block unchanged |
| `README.md` | Dated B3.1 result/next-stage developer status na report link |
| Report hii | Requested milestone evidence |

No migration/model/factory/dependency/public-frontend edits. Existing public HTML/CSS/JS, branding/logo/contact, auth controllers, trusted-host tests na historical reports hazijabadilishwa. New policy discovery hutumia Laravel conventions bila manual broad Gate bypass.

## D. Merchant Context Implementation

Named route **`merchants.onboarding`**, **GET|HEAD `/merchants/{merchant}/onboarding`**, ina ULID constraint na existing web/session controls. Auth, readiness, active-account/auth_version na verified middleware hutangulia context resolution. `ResolveMerchantContext` husoma **route parameter pekee** na kuquery `Merchant::forMember($request->user())`; not-found/foreign/revoked context ni 404. Controller haitegemei unscoped implicit merchant binding.

Query/body/session-supplied merchant/application/role IDs si authority. `/account` bado ni scoped list; user mwenye merchants wengi hupata explicit links zote zinazomruhusu. No active memberships huonyesha existing empty state bila kuunda records. Missing/invalid merchant haibuni context; missing current application huonyesha truthful unavailable state bila kuunda draft.

## E. Authorization Policy Implementation

`MerchantPolicy::viewSummary` huquery fresh persisted active account, nonnull verified email na active membership kupitia existing member scope. Cached actor/membership model relations si authorization source. Creator identity pekee halitoshi baada ya revocation.

`MerchantApplicationPolicy::view` kwanza inahitaji summary permission, kisha fresh owner/manager membership na persisted application inayotoka merchant relation na current_slot=1. Staff hupata safe status summary lakini si attempt details/feedback. Platform admin/chief assignment pekee haitoi merchant permission; hakuna Chief `Gate::before` override au administrator provisioning.

Suspended/closed **identity** hukataliwa na existing auth middleware. Suspended/closed **merchant** hubaki read-only kwa authorized members bila reset ya status, kulingana na plan. Direct-policy tests pia zinathibitisha fresh account/membership/tenant rules.

## F. Read-only Onboarding Status

Controller huquery only selected fields za merchant/current application, kisha hutuma `MerchantOnboardingSummary` pekee kwenye view, si raw merchant/application models.

Role-based response allowlist:

- Owner/manager/staff: business display name, operational status, verification status na current application status.
- Owner/manager pekee: current attempt number, actual submitted/reviewed timestamps inapokuwepo na current public feedback.
- Hakuna address/business contacts, profile snapshots, internal review notes, reviewer identity, audit/risk records, document URLs, secrets au other-tenant data.

Labels zinatumia existing enum: Draft, Awaiting review (`pending_review`), More information required, Approved, Rejected. Hakuna invented Under-review activity, submitted state mpya au merchant approval inayotokana na email verification. Blade escaping inazuia raw HTML kwenye business name/feedback; stored feedback XSS test imepita.

View inareuse existing `auth.layout`, logo na website CSS. Account wording imehifadhiwa; names zimekuwa links. Hakuna edit/submit/dead financial controls. Non-GET methods kwenye onboarding path ni 405. GET inaweza kutumia ordinary session/framework cache files, lakini **haifanyi business/database mutations**.

Merchant success/error responses zina no-store/private, no-referrer, nosniff na DENY. Unknown failures hutoa generic response; logging inahifadhi exception type pekee, si payload/token/PII. Existing authentication error behavior na trusted-host registration vimehifadhiwa.

## G. Security and Tenant Isolation Tests

**Fresh `MerchantAccessTest`: 24 passed / assertions 244.** Coverage:

1. Guest na unverified identity denial.
2. Owner/manager allowed details; staff-safe status-only rendering; direct Gate boundaries na undefined write abilities denied.
3. Nonmember/cross-merchant 404 bila business-name disclosure.
4. Membership revocation na role downgrade zinatumika next request, hata cached relations zikiwa loaded; creator haibypass revocation.
5. Suspended/closed identity na stale/missing auth_version invalidation.
6. Missing/invalid merchant IDs; empty selector bila record creation.
7. Multiple memberships zina explicit selector links; forged query/session merchant/role/application IDs hazibadili authorized context.
8. Synthetic platform admin/chief assignments katika memory fixtures hazitoi tenant access; hakuna real administrator aliyoundwa kwenye working data.
9. Mismatched nested application association na historical-attempt reads denied na policy; no new history/detail endpoint.
10. Existing application states zote tano, escaped feedback, missing current attempt na suspended/closed merchant safe reads.
11. Before/after fingerprints za raw isolated user/merchant/membership/application/audit/platform-role/reset-token/session records zinafanana baada ya read-only requests; no notifications sent.
12. Write-method rejection, no dead out-of-scope links, privacy headers kwenye success/404/405 na generic redacted 500/log behavior.

Test guards zinahitaji testing, SQLite `:memory:` na empty DB_URL kabla ya migrations/factories; suite haitarget working SQLite. Production-like host fixtures ni za existing TrustedHostsTest pekee. Fingerprints hazichapishi actual row data. B3.1 tests hazitumii external services.

## H. Authentication and Trusted-Host Regression

Fresh focused runs baada ya B3.1:

| Suite | Matokeo |
|---|---|
| Existing `AuthenticationTest.php` | **29 passed, assertions 279** |
| Existing `TrustedHostsTest.php` | **20 passed, assertions 60** |

Existing registration/reset/verification, session regeneration, CSRF, rate limits, auth_version, safe redirects na host/alternate-origin behavior zimepita. Hakuna tests zilizolegezwa au existing auth/host test files zilizobadilishwa.

## I. Complete Laravel Test Results

**108 passed / assertions 847**, takriban sekunde 9.5. Hizi ni checkpoint baseline 84/603 pamoja na new B3.1 cases 24/244. Hakuna final test failure/skipped test iliyoripotiwa.

Pint `--dirty --format agent`: passed. PHP syntax validation ya changed/new PHP/Blade files **10/10**: no syntax errors. Initial syntax-command path construction haikupata untracked relative paths; command ilirekebishwa kutumia repository-root-relative inventory na validation zote 10 zikapita. Hakuna code correction au test weakening iliyohitajika kwa syntax-tooling issue hiyo.

`git diff --check`: passed. `artisan route:list --path=merchants` ina **route moja tu**, GET|HEAD status; hakuna write/profile/history/admin/payment endpoints. Complete application route count imeongezeka kwa status endpoint pekee; hakuna schema/dependency changes.

## J. Frontend Regression Results

`node scripts/Test-Frontend.cjs`: **pages 10, interaction checks 31, DOM script errors 0**. Canonical-to-generated public-page comparison: mismatches 0. `git diff` ya public canonical frontend ni tupu; website branding/assets/navigation ya public pages haijabadilishwa. New account-to-status link na escaped status rendering zina HTTP feature-test coverage.

Live guest checks: homepage/sign-in/create-account 200; `/account` na new valid-format onboarding path 302 kwenda canonical sign-in. Hakuna live owner-account POST au business-data write iliyofanywa.

Real-browser visual QA ya new status page/mobile widths ni **NOT VERIFIED**; existing browser-tool limitation inaendelea. DOM na server-render tests si visual acceptance. Reused layout si proof ya pixel-perfect responsive verification; manual review bado inafaa, bila kuanzisha full B3.5 stage.

## K. Working Database Integrity

Exact source: `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite`. Post-implementation inspection imetumia SQLite3 **READONLY**.

| Check | Fresh result |
|---|---|
| Integrity | ok |
| Foreign-key violations | 0 |
| Users | 1 |
| Merchants / memberships / applications / audit logs | 1 / 1 / 1 / 1 |
| Platform role assignments | 0 |
| Migration records | 9 |

Database SHA-256 kabla/baada:

`214ae83a31f53e80d3068d1aeb1366008bb5accb2df212dd2c534ae4121b6d8f`.

Full-file equality inaunga mkono preservation ya existing verified-user/draft/onboarding state bila kuchapisha PII. `.env` hash pia unchanged; APP_KEY, local APP_URL, working schema na backups hazijabadilishwa. Existing private backups zimeachwa kama zilivyo. No working migrations, resets, seeders, account recreation au administrator creation.

## L. Remaining Risks

- **NOT VERIFIED:** live successful owner-browser status navigation na visual/responsive acceptance; automated rendering/isolation zina fresh evidence tofauti.
- SQLite fixtures haziprovi future production-engine locking/concurrency. B3.1 haina mutations; later stages zitahitaji approved CAS/transaction design na target-engine validation.
- Full deployment APP_URL validation, actual HTTPS/proxy/CORS/operational readiness, independent off-device/key recovery na historical log attribution bado vina limitations za previous reports. Trusted-host block haijapanuliwa nje ya approved scope.
- Policies/read models ni foundation ya current route, si authorization ya future profile/history/admin/team/finance endpoints. Future actions lazima ziweke own policies/fresh checks; no assumption ya owner=platform admin.
- Existing schema inaruhusu at most one current attempt; missing current attempt imehandled safely, haijarepairiwa automatically. Semantic/data-recovery action yoyote itahitaji separate approval.

Hakuna new confirmed CRITICAL/HIGH regression katika tested B3.1 scope. Huu si production/payment-security au regulatory-compliance certification.

## M. GO / NO-GO Recommendation

**GO — B3.1 implementation acceptance kwa local/isolated scope.** Context/policies/status route, role redaction, tenant isolation, read-only preservation na required regressions zimepita. Visual/live authenticated browser limits zimewekwa wazi; si madai ya completed Merchant Dashboard au production readiness.

Changes za B3.1 zimeachwa uncommitted na unstaged kwa owner review. No remote push/deployment. Checkpoint ya before-B3.1 ipo salama kwa code-history reference; rollback ya working changes haitafanywa automatically.

## N. Next Milestone

**REQUIRES APPROVAL: B3.2 — Profile foundation**, kufuata existing B3 plan: approved business types/countries/fields/privacy/attestation policy kwanza, kisha exact additive schema preparation/isolated rehearsal na **separate working-schema activation approval**. Hakuna B3.2 code/migration iliyoundwa sasa.

**STOP: B3.1 imekamilika katika scope hii. B3.1 commit, B3.2, push au deployment haitafanywa bila explicit owner authorization inayohusika.**
