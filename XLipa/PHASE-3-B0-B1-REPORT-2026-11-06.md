# XLipa — Phase 3 Stage B: B0 na B1 Report

Tarehe: 6 Novemba 2026, Africa/Nairobi.

Canonical root: `C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT/XLipa`.

**B0 na B1 zimekamilika katika mipaka iliyoidhinishwa.** Six migrations, five new models, enums, factories na foundation tests zimeandaliwa. **Hazija-run kwenye working SQLite.** Test migrations/up/down zimefanyika kwenye isolated `:memory:` SQLite na disposable test-only copy yake kwa competing-connection test.

Final suite: **tests 35 passed, assertions 264, failures 0**. Hizi ni existing tests 16 pamoja na foundation tests mpya 19. Hakuna auth controller, frontend integration, administrator provisioning, KYC upload au financial feature iliyoongezwa. B2 haijaanza.

## 1. B0: Preflight, rollback baseline na files changed

### Current state iliyohakikiwa

- Existing Laravel 13.35.0/PHP 8.5.12 na native modular-monolith source zimehifadhiwa. Direct Composer dependencies zimehakikiwa; hakuna package iliyoongezwa/ku-upgraded katika B0/B1.
- Working database ilikuwa na tables 9, migrations 3 na users 0. Existing applied migrations zote tatu za `0001_*` hazijabadilishwa.
- Canonical frontend, assets, scripts, routes, configuration na Composer files zimescaniwa/ulinganishwa na source snapshot. Existing files zilizobadilishwa katika application ni **User.php na UserFactory.php pekee**; nyingine ni new files zilizoorodheshwa hapa chini. README ina documentation update tu.
- Parent Git bado ina pre-existing `M xlipahomepage.html` na untracked `XLipa/`. Hakuna commit iliyofanywa, wala existing user change iliyorevertiwa.
- `git ls-files` scan haikuonyesha tracked `.env`, SQLite, SQL backup au reference materials; `git check-ignore` imethibitisha ignore coverage ya local `.env`, working SQLite, historical SQL, references na recovery directory. Huu si full Git-history secret audit.

### Rollback backup

Ignored `.recovery/phase3-b0/` ina:

- `source/`: independent pre-change copy ya relevant app, configuration, routes, original migrations/factories/tests, frontend/assets/scripts na manifests/docs.
- `source-manifest.json`: SHA-256 za backed-up source files.
- `working-database.sqlite`: consistent SQLite backup iliyoundwa kwa **SQLite backup API**, kutoka read-only connection ya working database.
- `database-hashes.json`: preflight hashes za working database na historical SQL.

Backup imefunguliwa read-only na `PRAGMA integrity_check` imerudisha **ok**. Backup directory iko ignored; hakuna backup/secret iliyoongezwa kwenye Git. Hii ni rollback copy tofauti na files zinazohaririwa, lakini iko kwenye disk/workspace ileile: **si off-device disaster-recovery backup**. Local `.env` haijabadilishwa wala kuchapishwa; hakuna production credential iliyotumika.

### Exact files created

Paths hapa ni relative kwa canonical root iliyo juu.

**Migrations:**

1. `backend/database/migrations/2026_11_06_000001_add_account_controls_to_users.php`
2. `backend/database/migrations/2026_11_06_000002_create_merchants.php`
3. `backend/database/migrations/2026_11_06_000003_create_merchant_memberships.php`
4. `backend/database/migrations/2026_11_06_000004_create_platform_role_assignments.php`
5. `backend/database/migrations/2026_11_06_000005_create_merchant_applications.php`
6. `backend/database/migrations/2026_11_06_000006_create_audit_logs.php`

**Models:**

- `backend/app/Models/Merchant.php`
- `backend/app/Models/MerchantMembership.php`
- `backend/app/Models/PlatformRoleAssignment.php`
- `backend/app/Models/MerchantApplication.php`
- `backend/app/Models/AuditLog.php`

**Enums:**

- `backend/app/Enums/AccountStatus.php`
- `backend/app/Enums/MerchantStatus.php`
- `backend/app/Enums/VerificationStatus.php`
- `backend/app/Enums/MerchantRole.php`
- `backend/app/Enums/MembershipStatus.php`
- `backend/app/Enums/PlatformRole.php`
- `backend/app/Enums/ApplicationStatus.php`
- `backend/app/Enums/AuditEvent.php`

**Factories:**

- `backend/database/factories/MerchantFactory.php`
- `backend/database/factories/MerchantMembershipFactory.php`
- `backend/database/factories/PlatformRoleAssignmentFactory.php`
- `backend/database/factories/MerchantApplicationFactory.php`
- `backend/database/factories/AuditLogFactory.php`

**Ownership invariant helpers na tests:**

- `backend/app/Actions/Merchants/CreateMerchant.php`
- `backend/app/Actions/Merchants/RevokeMembership.php`
- `backend/tests/Feature/DatabaseFoundationTest.php`
- `PHASE-3-B0-B1-REPORT-2026-11-06.md` — report hii.

### Existing files modified

- `backend/app/Models/User.php`: account-control casts na membership/merchant/platform-role relationships. Existing bigint IDs, name/email/password fillable allowlist na password hashing vimehifadhiwa. Hakuna authentication endpoint au verification workflow iliyoongezwa.
- `backend/database/factories/UserFactory.php`: active account/auth-version defaults kwa fixtures za schema mpya. Factories zimetumika kwenye disposable tests tu; hakuna real account au administrator aliyeundwa.
- `README.md`: B1 status, tests, pending migrations na approval boundary.

Artisan ilitumika kuscaffold migrations/models/factories/test/action classes; migration filenames zimewekwa kwa client date ya 6 Novemba na deterministic dependency order. Original `0001_*` files hazijarenamiwa.

## 2. Database schema prepared

| Eneo | Prepared schema |
|---|---|
| `users` | Additive `account_status`, positive integer `auth_version`, nullable `terms_version`, nullable `terms_accepted_at`; existing identity/password/email constraints zinabaki |
| `merchants` | ULID PK, structured business/registration/contact/address fields, separate operational/verification status, positive `profile_version`, creator FK, status/registration indexes |
| `merchant_memberships` | Bigint PK, merchant/user FKs, owner/manager/staff role, active/revoked status, nullable grantor/revocation timestamp, unique merchant+user na membership lookup indexes |
| `platform_role_assignments` | User PK/FK, chief_admin/admin role, nullable grantor FK, grant/revoke timestamps; separate from merchant membership |
| `merchant_applications` | ULID PK, merchant FK, positive attempt/profile versions, nullable current slot, application status, encrypted snapshot/notes TEXT columns, submitter/reviewer FKs/timestamps, merchant-visible feedback |
| `audit_logs` | ULID PK, constrained event type, nullable actor/target/merchant/application references, request correlation UUID, bounded application metadata, occurred_at, audit lookup indexes |

Five new tables tu; hakuna verification-documents, permissions catalog, team invitations, payment, ledger, balance au settlement tables.

### Confirmations na implementation details dhidi ya Stage A

1. **Current slot:** imetekelezwa kama nullable constrained value `'1'` kwa schema, na integer cast kwenye Eloquent. Hii ni database enum/check representation badala ya physical small-integer column iliyokuwa proposal. Semantic invariant ni ileile: slot `1` moja kwa merchant, historical rows nyingi zenye NULL. Chaguo hili lilielezwa kwenye progress update kabla ya migration contents; linafanya invalid slot values zikataliwe na SQLite.
2. **Positive numeric versions:** SQLite haitumii unsigned declarations kama enforcement. Named insert/update triggers zina-enforce positive **integer** auth/profile/attempt versions, ikiwemo kuzuia zero, negative, fractional na invalid-text values zilizojaribiwa. User-control rollback huondoa triggers zake kabla ya kuondoa columns; dropping new tables huondoa associated triggers.
3. **Target engine:** kila new migration ina guard inayokataa non-SQLite engine kabla ya kufanya DDL. SQLite-specific triggers si portable MariaDB migration implementation. Hii ni explicit local safety boundary; future target-engine support inahitaji approved review/port na tests, si kudhani SQLite syntax inafanya kazi kila mahali.
4. **Audit tenant consistency:** application table ina additional unique `(id, merchant_id)` ili composite FK ya audit `(application_id, merchant_id)` izuie references kwa application ya merchant mwingine. Model pia inakataa application event bila merchant context. NULL semantics za composite FK zina maana kwamba raw SQL isiyopitia model bado inahitaji trusted-write discipline.
5. **Ownership:** FK haiwezi yenyewe kuhakikisha merchant ana child owner ndani ya same atomic creation. B1 imeongeza limited transaction helpers za kuthibitisha invariant hiyo; hakuna controller, route au team-management UI iliyounganishwa.

Mabadiliko haya hayabadilishi frontend, PK ya existing user au approved role/state boundaries. Exact migration source files ndizo concrete review artifact kabla ya working-database approval.

## 3. Models na relationships implemented

| Model | Relationships/casts |
|---|---|
| User | HasMany memberships, BelongsToMany merchants, HasOne platform assignment; account enum, integer auth version, immutable terms timestamp; existing hashed password |
| Merchant | Creator, memberships, users with role/status pivot, applications, audit logs; operational/verification enums; integer profile version; ULID |
| MerchantMembership | Merchant, user, creator; separate role/status enums na revocation timestamp |
| PlatformRoleAssignment | User, grantor; platform enum, grant/revoke dates; user_id PK |
| MerchantApplication | Merchant, submitter, reviewer, audit logs; encrypted array snapshot, encrypted internal notes, hidden sensitive attributes, state enum, integer versions/slot, immutable dates |
| AuditLog | Actor, target user, merchant, application; event enum, metadata array, occurred_at; ULID, no update timestamp |

New models zina guarded attributes; role, tenant IDs, reviewer/state na audit controls si public mass-assignment inputs. Sensitive application fields hazitokei katika default model serialization.

`Merchant::forMember($user)` hufanya active membership lookup na fresh active-user condition. Platform-role assignment haitoi tenant access. Ordinary relationship accessors ni association/history queries, **si authorization grant**; future controllers lazima zitumie scoped queries na policies. HTTP policies/middleware/admin permission workflows hazijatekelezwa kwa B1.

Application model inakataa reassignment ya merchant/attempt identity na mabadiliko ya submitted snapshot/version/submitter/timestamp baada ya draft. Model deletion ya application history inakataliwa. Audit model inakataa ordinary model update/delete, nested/unknown/oversized metadata payloads, na application event isiyo na merchant context. Metadata key allowlist si permission ya ku-log arbitrary PII kwenye permitted values; future actions lazima zitumie server-generated, redacted payloads.

### Ownership helpers

`CreateMerchant` ni low-level B1 invariant helper: fresh active owner lookup → transaction → merchant → active owner membership → current draft attempt → audit event. Audit insert ikishindwa, records zote mpya zinarollback. Haina registration/session handling au external notifications.

`RevokeMembership` ni limited invariant helper, si public team-management feature. Huresolve target chini ya merchant, hurecheck active owner actor, hukataa last-owner revocation, na huandika revocation+audit katika transaction. Repeat revocation ni idempotent. User-supplied status/role payloads hazichukuliwi. Parent merchant lock inatumika kwa future engines na transaction retry kwa concurrency errors; other mutation paths za baadaye lazima zifuate invariant protocol hiyo.

Factories zinatengeneza synthetic relationship fixtures. Merchant factory huweka owner na initial draft kwa fixtures; factories si production provisioning API na hazijaitwa kwenye working database.

## 4. Database constraints tested

Tests mpya zinahakiki:

- Merchant creator FK na deletion restriction ya identities zenye referenced records.
- Membership user/merchant/creator FKs, unique merchant+user, allowed role/status values.
- Platform assignment user/grantor FKs, user PK uniqueness na role separation.
- Application merchant/submitter/reviewer FKs, unique merchant+attempt, at-most-one current slot, invalid slot rejection na multiple historical NULLs.
- Audit actor/target/merchant/application FKs, pamoja na cross-merchant application-reference rejection kupitia composite FK.
- User account state, merchant operational/verification state na application-state allowlists.
- Positive auth/profile/attempt versions kwa insert/update, zero/negative rejection na tested malformed numeric values.
- Model mass-assignment defenses, encrypted/hidden snapshots/notes, immutable submitted evidence na append-only ordinary audit-model operations.
- Multiple merchants per user; different merchant roles; revoked or suspended user access exclusion; platform-admin identity kutopata automatic membership access.
- Last active owner retention, wrong-merchant target denial, unauthorized actor denial, idempotent membership revocation na audited owner changes.

Hizi si tests za login, HTTP policy enforcement, full application-state transition workflow au production authorization: features hizo bado hazijaunganishwa.

## 5. Migration/rollback na concurrency results

### Test isolation

Before schema operations, `DatabaseFoundationTest` inakataa execution isipokuwa:

- Environment ni `testing`.
- Default driver ni SQLite.
- Configured na effective database ni exactly `:memory:`.
- SQLite URL override ni empty.
- Foreign-key enforcement imewashwa.

Original migrations zinarun kama batch ya kwanza kwenye disposable memory DB; prepared B1 migrations ni batch ya pili. Hakuna RefreshDatabase inayorun dhidi ya canonical database. Existing Phase 2 canonical SQLite inspection imebaki read-only.

### Results

| Check | Result |
|---|---|
| Foundation migrations up | Passed; disposable DB ina tables 14 (original 9 + new 5) |
| B1 down kwenye populated disposable fixture | Passed; new tables/control columns zinaondoka, original tables 9 zinabaki |
| Existing identity/password preservation during down | Passed; original user email/password hash hazijapotea |
| Reapply baada ya down | Passed; existing user inapata compatible active/auth-version defaults |
| Audit failure during merchant creation | Passed; merchant/membership/application/audit records hazibaki nusu |
| Failed current-attempt replacement | Passed; transaction rollback inarejesha original current slot |
| Competing SQLite writers | Passed; second write transaction inapata expected SQLITE_BUSY wakati writer wa kwanza ana lock |
| Stale compare-and-set baada ya first writer commit | Passed; old current-slot update ina affected rows 0 na new current attempt inaendelea kubaki |
| Full suite | **35 passed / 264 assertions / failures 0** |
| Pint | Passed baada ya formatting |

Competing-connection test inaexport **test-memory database pekee** kwenda random temporary SQLite file, inafungua PDO connections mbili, kisha huondoa named temporary fixtures. Haisomi/ku-export working database. Up/down/drop operations zote ni sehemu ya explicitly approved disposable-test workflow.

**Concurrency limit:** test hii inathibitisha SQLite storage serialization na stale-write behavior iliyojaribiwa, si MariaDB row-lock semantics au production concurrent last-owner/reviewer correctness. Unique constraints ni DB backstop; future application transitions zinahitaji fresh reads, conditional updates, transaction retries na target-engine race tests.

## 6. Security findings na safeguards

- `composer audit --locked --format=json` imerudisha **advisories [] / abandoned []**. Hakuna dependency update katika milestone hii; hii si penetration-test result.
- New role/status controls hazijaongezwa kwenye User fillable list. New models zimeguardiwa; trusted helpers/factories pekee zinatumia explicit force-create/fill.
- Profile snapshots na internal notes zinahifadhiwa encrypted na hidden by default; hakuna real KYC data iliyokusanywa. Existing local app key haijabadilishwa/kuchapishwa.
- No auth/admin/payment routes zimeongezwa; route inventory bado ni public GET/HEAD routes 11 zilezile.
- BongoPay archive/public separation, Selcom future-only boundary na original frontend zimehifadhiwa.
- No seeders zimeendeshwa; platform assignments zilizotengenezwa ni synthetic disposable test rows tu, si real administrators.
- Ordinary model protections hazizuii raw SQL/query-builder bypass au filesystem/DB administrator. Audit si tamper-proof store; tenant policy na business-transition services bado zinahitaji later implementation.
- Pending-review snapshot requirements, reviewer permissions, application resubmission decisions, email normalization na session auth_version enforcement si complete workflows bado. Migrations/models kuwepo hakumaanishi authentication au merchant approval inafanya kazi.

## 7. Working database integrity verification

Preflight na post-test SHA-256 zinafanana:

| File | SHA-256, kabla na baada |
|---|---|
| `backend/database/database.sqlite` | `0B18DDB07875C2DBAAA2F8C49909FC73ABECFC2F3CE217C0A8DEFACB5A837C6D` |
| `database/mkulbzij_xlipa_site.sql` | `33D48EC8C9E9EE07A143390E8B7D1BED6C893382AE758057CC9226C5C5A2FB8E` |

Final read-only working-state inspection: **tables 9; migration records 3; users 0; users.account_status haipo**. Huo ni uthibitisho kwamba B1 DDL haijarun kwenye working database. Original migration source hashes na frontend source/assets hashes pia zimebaki sawa na B0 baseline.

Hakuna SQL import, working-database migration/rollback/reset, production connection/configuration change au frontend redesign iliyofanywa.

## 8. Remaining architectural risks

1. **Working schema bado old:** new models/factories si authorization ya kuanza kuandika merchant/user-control records humo. App pages zilizopo zinaendelea na tests zake; usi-run new factories/seeders kwenye working DB kabla ya migration approval.
2. **SQLite-only migrations:** numeric triggers na current-slot representation zinahitaji explicit target-engine port/review kabla ya MariaDB/PostgreSQL. Guards zinazuia accidental execution kwenye engine isiyoreviewiwa.
3. **At least one owner/current attempt:** unique/FK constraints hutoa referential integrity na at-most-one current slot; raw SQL bado inaweza kuunda ownerless merchant au kuacha no current slot. Atomic creation helper na future approved mutation services lazima zitumike. B1 haiadai cross-table deferred ownership constraint ambayo SQLite haina kwa FK pekee.
4. **All mutations must share invariants:** raw query updates zinaweza kubypass model events; new policies/actions zisiruhusu role/status rewrites kiholela. Last-owner revocation helper si exhaustive team-management/ownership-transfer system.
5. **History/audit:** encrypted casts zinahitaji key backup/recovery. Model append-only protections si DB-admin tamper resistance. Future audit payloads lazima zifanye data minimization na actor authorization.
6. **Production concurrency:** actual concurrent ownership changes, attempt numbering, approval races na target-engine deadlock handling bado vinahitaji future integration tests. SQLITE_BUSY test si production clearance.
7. **Source recovery:** rollback snapshot iko local disk. Parent Git bado untracked; hakuna forced commit ya secrets/references iliyofanywa. Off-device backup na deliberate reviewed baseline commit bado zinapendekezwa.

Hakuna claim ya regulatory compliance, live payments au production readiness.

## 9. Exact migrations awaiting working-database approval

Migrations zifuatazo **pending**, zote kwenye `backend/database/migrations/`:

```text
2026_11_06_000001_add_account_controls_to_users.php
2026_11_06_000002_create_merchants.php
2026_11_06_000003_create_merchant_memberships.php
2026_11_06_000004_create_platform_role_assignments.php
2026_11_06_000005_create_merchant_applications.php
2026_11_06_000006_create_audit_logs.php
```

Target ya kuidhinishwa, ikihitajika baadaye, ni **canonical local SQLite pekee**: `C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT/XLipa/backend/database/database.sqlite`. Before execution lazima preflight/backup zirudiwe kama working state imebadilika. Approval ya B0/B1 iliyopo haijatumika kama ruhusa ya ku-run migrations humo.

Down migrations zinaweza kupoteza data ya new tables ikiwa zitarun baada ya real use. Passing disposable rollback test si approval ya ku-rollback existing business data. Hakuna rollback ya working data iliyofanywa.

## 10. Recommended next milestone

Review exact migration files, implementation details za SQLite na report hii. Baada ya hapo toa approval zilizo wazi kwa:

1. **Ku-execute migrations sita kwenye working local SQLite**, ikiwa unataka schema ipatikane humo, baada ya refreshed preflight/backup.
2. **B2 authentication implementation** kama milestone tofauti: native session registration/login/logout/reset/verification, rate limits, active-account/auth-version enforcement na minimal existing-form wiring kulingana na Stage A.

Hakuna mojawapo iliyoanza kwa implication. Hakuna real admin provisioning, external mail delivery, KYC, Selcom au financial feature itakayofanyika kama sehemu ya B2 default scope bila approval yake.

### Commands/evidence

Successful tools/checks: `composer show --direct --no-ansi`, `composer audit --locked --format=json`, `artisan make:model --factory --no-interaction`, `make:migration`, `make:class`, `make:test --phpunit`, `php vendor/bin/pint --dirty --format agent`, `php vendor/bin/phpunit --no-progress --do-not-cache-result`, `artisan route:list --except-vendor`, read-only SQLite inventory/counts/integrity checks, SQLite backup API, source/database hash comparisons, `git ls-files`, `git check-ignore`, `git status`.

Migration commands zinazoonekana ndani ya test class zina-run baada ya strict `:memory:` guard pekee. Hakuna standalone migrate command iliyotekelezwa kwenye normal local environment. Optional testing-skill search haikupata skill hiyo kwenye available local path; existing PHPUnit conventions na framework implementation zimetumika. Hakuna new test iliyofeli katika verification runs zilizofanywa.

**STOP: B1 imekamilika. Ninasubiri explicit approval kabla ya working-database migrations au B2.**
