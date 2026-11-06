# XLipa: Project Recovery, Audit na Mpango wa Maendeleo

Tarehe: 6 Novemba 2026, Africa/Nairobi. Scope: audit pekee; hakuna utekelezaji wa phase inayofuata.

**Hitimisho:** consolidation ipo na frontend imehifadhiwa. Laravel inaweza kurudisha kurasa, lakini XLipa bado ni frontend pamoja na Laravel skeleton, si payment gateway inayofanya miamala. Tatizo kuu la mazingira ni kuwa Herd inaelekeza kwenye nakala ya zamani yenye MySQL configuration, huku hakuna server inayojibu kwenye port 3306. Nakala mpya inatumia SQLite na file sessions, lakini bado haijaunganishwa kwenye Herd.

“Imethibitishwa” hapa ina maana ya ukaguzi wa mafaili, hashes, command output au request iliyofanywa katika audit hii. “Haijahakikiwa” si uthibitisho wa kutokuwepo au kutofanya kazi. Hakuna SQL backup iliyo-importiwa, migrations zilizoendeshwa, dependencies zilizobadilishwa, deployment, redesign au application-source fixes katika audit hii.

## A. Current Project Structure

Canonical working project inayopendekezwa ni `C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT/XLipa`.

```text
XLipa/
  frontend/                  HTML 11; CSS 3; SVG 1; PNG 5
  backend/
    app/                     User model; base Controller; AppServiceProvider
    routes/                  web.php na console.php
    database/                migrations 3 na database.sqlite
    resources/views/xlipa/    Blade copies zinazotengenezwa na sync script
    public/assets/           asset copies zinazotengenezwa na sync script
    tests/                   example feature test na example unit test
    vendor/                  Composer dependencies zilizopo
  database/
    mkulbzij_xlipa_site.sql   hosting dump, bytes 7,150
  scripts/
    Sync-Frontend.ps1
    Start-XLipa.ps1
  XLipa.code-workspace
  README.md
  .gitignore
```

**Authoritative files:** frontend ihaririwe kwenye `frontend/`; backend logic kwenye `backend/`; `resources/views/xlipa/` na `public/assets/` ni generated copies. Backup ya SQL ni historical input, si schema ya mwisho ya gateway. SQLite iliyopo ni local database tofauti na dump ya MariaDB.

Root ya `WEB-DEVELOPMENT` na Downloads ni original recovery sources. Zisifutwe wala kutumika sambamba kama sehemu nyingine ya maendeleo. Active IDE file ya sasa bado ni homepage ya root, hivyo kuna hatari ya kuhariri nakala isiyo canonical. Ushahidi: `XLipa.code-workspace`, `README.md`, `scripts/Sync-Frontend.ps1`, hash comparison.

Git ipo kwenye parent `WEB-DEVELOPMENT`, yenye mafaili 21 tracked. `git status --short` inaonyesha `M xlipahomepage.html` na `?? XLipa/`: consolidated project bado haijawekwa kwenye commit. Hivyo Git history ya sasa haitoshi kurejesha consolidation yote. ZIP na Downloads zipo, lakini restore ya backup ya nje haijajaribiwa.

## B. Existing Functional Features

| Kilichopo | Hali iliyothibitishwa | Ushahidi |
|---|---|---|
| Homepage, About, Contact, Developers, legal pages, sign-in na registration screens | Mafaili yapo na Laravel inayarudisha | `frontend/*.html`, `backend/routes/web.php` |
| Routing | Routes 12: root pamoja na HTML pages 11, GET/HEAD | `php artisan route:list --except-vendor` |
| Blade integration | Kurasa zote 12 zimerudisha 200 kupitia HTTP kernel; CSRF placeholders zimerenderiwa | kernel requests kwenye local environment |
| Assets | Assets 9 zinafanana na originals na copies za `public/assets/` | SHA-256 comparisons |
| Navigation references | Hakuna local href/src target au HTML fragment iliyokosekana katika static scan | HTML zote 11 |
| Password visibility na confirm-password check | Event handlers na client validation zipo; browser interaction haijajaribiwa | `frontend/xlipasignin.html:232`, `frontend/xlipacreateaccount.html:218` |
| Contact form | Inatengeneza `mailto:`; haina backend submission | `frontend/contact.html`, mwisho wa script |
| Local session/cache isolation | `file` sessions, `file` cache, `sync` queue | local `.env`, runtime configuration |
| Password-hashing foundation | User model ina `password => hashed`, hidden password/remember token | `backend/app/Models/User.php` |

HTTP 200 si uthibitisho wa JavaScript, visual layout, authentication au malipo. Local asset requests kupitia canonical Herd domain bado hazijahakikiwa kwa sababu domain haijaunganishwa.

## C. Incomplete or Simulated Features

1. **Login:** submit handler inazuia submission na kusema account service haijaunganishwa. Hakuna login POST route. Ushahidi: `frontend/xlipasignin.html:235`, `backend/routes/web.php`.
2. **Registration:** password confirmation ni client-side; submit inaonyesha ujumbe wa kuunganisha registration. Haiundi user/merchant. `frontend/xlipacreateaccount.html:219`.
3. **Password reset:** button inafungua email kwa support; si token-based password reset. `frontend/xlipasignin.html:234`.
4. **Developer page:** ni maelezo ya payment links/integration na mfano wa TZS 50,000; si API specification inayoweza kutumika. `frontend/developers.html`.
5. **Payment links, collections, withdrawals na balances:** maandishi yanaelezea uwezo unaokusudiwa; hakuna backend records, endpoints au services zinazoufanya. `frontend/xlipahomepage.html`, `frontend/developers.html`, routes na `app/`.
6. **BongoPay reference:** `frontend/bongopay1.html` ina branding/content ya BongoPay, links za nje na scripts za nje. Si merchant dashboard iliyojengwa kwa XLipa, wala si uthibitisho wa functionality ya XLipa.
7. **Test coverage:** feature test inakagua homepage status 200 pekee; unit test inakagua `true`. Hizi hazithibitishi biashara, malipo au security. `backend/tests/Feature/ExampleTest.php`, `backend/tests/Unit/ExampleTest.php`.

## D. Missing Features

Ukaguzi wa `backend/app/`, `routes/`, migrations na tests haukupata implementation ya:

- Registration/login/logout/reset/verification, role authorization, policies, merchant isolation na account restrictions.
- Merchants, business profiles, KYC storage/review, approval/rejection, Chief Admin dashboard au audit trail ya vitendo hivyo.
- Payment adapter/interface, MockPaymentProvider, Selcom integration, transaction state machine au server-side payment verification.
- Idempotency, signed callbacks, replay protection, retries, reconciliation, financial ledger, fees na settlements.
- Merchant dashboard yenye actual records, payment links, customers, withdrawals na reporting.
- API routes/controllers, API credential hashing/rotation/revocation, sandbox/live boundaries na webhook delivery.
- Business-specific queues/jobs, notifications, financial monitoring, alerting na restoration verification.

Haya ni mapengo ya implementation katika code iliyokaguliwa. Hayafanyi kazi kwa sababu tu Laravel ina framework features za msingi.

## E. Frontend Comparison Results

### Original dhidi ya consolidated na served copies

HTML zote **11/11** na assets zote **9/9** zina hashes zinazofanana kati ya original `WEB-DEVELOPMENT` na `XLipa/frontend`. HTML zote 11 zinafanana pia na Blade copies, na assets zote 9 zinafanana na `backend/public/assets`.

Frontend integration imekamilika kwa **kunakili na kurender kurasa**. Haijakamilika kwa **kuunganisha forms, business logic au payments**.

### Preview ZIP

`xlipa-preview-site.zip` imefunguliwa kwa read-only archive APIs bila extraction au execution. Ina files 20: HTML 11 na assets 9. Hakuna Laravel backend, SQL au functionality nyingine iliyopatikana ndani yake. Hakuna entry ya path traversal iliyobainika katika inventory ya majina.

Files **19/20** zinafanana byte-for-byte na consolidated frontend. Tofauti pekee ni `xlipahomepage.html:33`: current copy ina maandishi `Downloads → xlipa-system → xlipa-system` baada ya Pricing link; ZIP haina maandishi hayo. ZIP metadata ni ya 7–10 Oktoba 2026, lakini timestamps pekee hazithibitishi version lineage. Tofauti iliyothibitishwa haileti feature mpya yenye thamani; hakuna sababu ya ku-overwrite frontend kwa ZIP yote.

### Genuine defects na quality gaps

| Finding | Athari | Ushahidi |
|---|---|---|
| `</script>` haipo kwenye About na Developers | Script inameza closing HTML; JavaScript parser inatoa `Unexpected token '<'`; handlers zake hazitegemeki | `frontend/about.html:108`, `frontend/about.html:127`; `frontend/developers.html:77`, `frontend/developers.html:96` |
| Maandishi ya file path ndani ya navigation | Content isiyokusudiwa inaweza kuonekana/kubadilisha spacing | `frontend/xlipahomepage.html:33`; ZIP comparison |
| Public BongoPay reference | Branding ya nje na external scripts zinawekwa kwenye XLipa origin ikifunguliwa | `frontend/bongopay1.html:497`, `:523`, `:524`; route katika `backend/routes/web.php` |
| Open Graph metadata haipo kwenye HTML zote 11 | Social sharing preview haijaandaliwa | static metadata scan |
| Repeated CSS overrides | Maintenance inakuwa ngumu na visual regression risk inaongezeka | `frontend/assets/xlipa-site.css`; inline styles kwenye auth pages |

Responsive breakpoints, focus-visible styling na reduced-motion rules zipo kwenye CSS. Hii haijathibitisha contrast ratios, keyboard/focus order, screen-reader behavior, mobile overflow, tablet/desktop appearance, browser compatibility au Core Web Vitals. Hakuna browser screenshots, visual regression suite, external link checks au network performance tests zilizofanywa. UTF-8 ilitumika kuthibitisha content; mojibake ya baadhi ya PowerShell outputs haikuchukuliwa kuwa file corruption.

## F. Backend and Database Assessment

### Laravel

PHP **8.5.12**, Laravel **13.35.0**, PHPUnit **12.5.38**, Boost **2.10.4** vimethibitishwa kwa runtime/Composer. `composer.json` inaruhusu PHP `^8.3` na Laravel `^13.17`; installed versions zinathibitishwa na Composer na lockfile, si version constraints pekee.

`app/` ina model moja ya User, base Controller na AppServiceProvider yenye register/boot zisizo na business configuration. Hakuna custom auth/payment controller, middleware, policy au service. `bootstrap/app.php` ina Laravel routing na API exception JSON preference, lakini hakuna `routes/api.php` iliyosajiliwa. Default web middleware/CSRF si sawa na endpoint ya authentication iliyotekelezwa.

Katika maeneo ya original backend yaliyolinganishwa—`app`, `routes`, `config`, migrations, `bootstrap/app.php` na tests—tofauti pekee ni `routes/web.php`. Hivyo hakuna ushahidi wa merchant/payment backend iliyopotea katika consolidation hiyo. Composer/Boost na local `.env` zina mabadiliko ya setup ya awali, nje ya ulinganisho huu.

### SQL backup

Dump ya bytes **7,150** inafanana kwa SHA-256 na download. Header inasema phpMyAdmin 5.2.3, MariaDB **11.4.13**, PHP 8.4.27, export ya **5 Novemba 2026**. Hii ni metadata ya dump, si independent confirmation ya hosting server au utimilifu wa hosting backup.

Tables **9** ni `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`. Primary keys, unique email/failed-job UUID na indexes za sessions/cache/jobs zipo. Hakuna foreign-key constraints zilizoonekana; `sessions.user_id` ina index lakini haina FK. Hakuna merchant, payment, KYC, ledger au settlement tables.

Dump ina INSERTs za migration history na sessions; hakuna INSERT ya users iliyoonekana. Session payload inaweza kuwa sensitive: backup isichapishwe wala kuhamishwa kiholela. Hakuna trigger, procedure, DEFINER, CREATE DATABASE, USE au DROP statement iliyobainika katika scan. CREATE TABLE na ALTER TABLE bado hubadilisha database; audit haijazi-execute.

Migration history ina filenames zilezile tatu zilizopo kwenye `backend/database/migrations/`. Schema inaonekana kuendana kimuundo na Laravel foundation hiyo. Compatibility ya restore bado haijathibitishwa kwa execution. Dump si SQLite-compatible moja kwa moja: ina MySQL/MariaDB DDL, collations na ALTER conventions.

`backend/database/database.sqlite` ipo, bytes **98,304**. Read-only query imethibitisha tables zilezile 9. Haijathibitishwa kuwa data yake inalingana na SQL backup. Hakuna migration au data mutation iliyofanywa.

### Kwa nini database haipatikani?

1. `herd sites` inaonyesha `xlipa-system.test` inaelekeza **Downloads/xlipa-system/xlipa-system**, si consolidated backend. Junction ya Herd inathibitisha hilo.
2. `.env` ya original ina `mysql`, `localhost:3306` na database name inayolingana na dump ya hosting, pamoja na nonempty username/password. Thamani za secrets hazijaonyeshwa. Hili halithibitishi kuwa credentials bado ni valid au production iko active.
3. TCP connection ya `127.0.0.1:3306` imekataliwa. Hakuna MySQL/MariaDB Windows service iliyopatikana kwa matching name; HerdHelper ipo running.
4. PHP ina `pdo_mysql`, `mysqli`, `pdo_sqlite` na `sqlite3`: kukosa PHP driver si chanzo kilichobainika.
5. `herd services:list` imesema **Herd Pro is required to use services**. Hii haithibitishi kutokuwepo kwa database installation nyingine; inathibitisha kuwa management ya services kupitia command hiyo haipatikani hapa. Registry/common installation-path checks hazikuleta matching installation; portable/WSL/custom-port server bado haijaondolewa kama uwezekano.
6. Original ina database sessions/cache/queue, hivyo hata homepage inaweza kuhitaji MySQL. Consolidated `.env` ni `local`, `sqlite`, file sessions/cache, sync queue; haina hosting DB credentials.

**Confirmed root issue:** hakuna database endpoint inayopatikana kwenye port iliyoconfigurewa na original site. **Strong supporting diagnosis:** domain bado inatumia old configuration/copy. HTTP 500 ya zamani haikurudiwa kwa mafanikio katika audit hii: local direct request ya old host ilifikia timeout/HTTP 000; host `xlipa.test` kupitia loopback ilirudisha 404. Hakuna 200 ya canonical domain iliyothibitishwa.

Herd Nginx hutumia Valet `server.php` kama dispatcher; `root /` katika generated config yenyewe si proof ya wrong Laravel document root. `backend/public/index.php` ipo na kernel inafanya kazi. Mapping ya canonical backend na behavior ya public assets kupitia Herd bado lazima ihakikiwe.

### README na workspace tasks

Workspace JSON ni valid. Run task huita `Start-XLipa.ps1`, ambayo husync frontend na kuendesha `artisan serve` kwenye localhost:8000. Hii **si** Herd site registration. Sync ni manual snapshot, haina watcher, na haiondoi generated files zilizoondolewa kwenye source. Kurekebisha Blade copy moja kwa moja kunaweza kupotezwa na sync inayofuata.

README ni sahihi kuhusu demo forms na pending SQL import, lakini haielezi old Herd mapping, syntax defects na BongoPay exposure. `composer run setup` si hatua salama ya audit: script yake ina `artisan migrate --force`. Haijaendeshwa.

## G. Security Findings

Priorities hapa ni mpangilio wa remediation, si CVSS rating wala uthibitisho wa exploitation.

| Priority | Finding na impact | Evidence / next requirement |
|---|---|---|
| P0 kabla ya live use | Hakuna merchant/admin authorization, payment verification au ledger. Hakuna live payment capability ya kufungulia | `app/`, routes, migrations; ijengwe na tests kabla ya matumizi ya fedha |
| P1 | BongoPay external application script kwenye public XLipa route inaweza kuendesha third-party JavaScript kwenye origin hiyo | `bongopay1.html:524`; pendekezo: reference itengwe nje ya public application, bila kufuta original |
| P1 | `composer audit --locked --format=json` imeripoti advisories **7 kwenye packages 4** | `composer.lock`: Symfony http-foundation (4), http-kernel (1), mime (1), routing (1); hakuna update iliyofanywa |
| P1 | Original local configuration ina hosting-derived DB identity na password iliyowekwa | Downloads `.env`; old site isitumike kama canonical dev target; hakiki/revoke credentials ikiwa kuna exposure iliyothibitishwa |
| P1 | Consolidated code bado untracked | `git status`; recovery baseline commit na independent backup zinahitajika baada ya secret review |
| P2 | `APP_DEBUG=true`, HTTPS haijasetiwa kwenye Herd site; runtime cookie secure ni null | local `.env`, `herd sites`, `config/session.php`; ni local settings, si production configuration inayokubalika |
| P2 | SQL ina session data; ignore rule si access-control/encryption | `database/*.sql`, `.gitignore`; backup lifecycle na access lazima zipangwe |
| P2 | Logging ni framework log, si immutable financial/admin audit | `config/logging.php`; retention, redaction, monitoring na audit integrity havijawekwa |
| P2 | Mail ni `log`; hakuna verified delivery ya reset/verification | `.env`, auth model; email verification contract haijawezeshwa |

Composer ilimaliza audit na exit code 1 kwa sababu ya advisories. Audit hiyo haithibitishi kuwa kila advisory ina exploit inayofikiwa na XLipa; applicability inahitaji review ya official advisory na code paths. Hakuna penetration test iliyofanywa.

Positive controls zilizopo: `.env`, SQL, vendor na SQLite zina ignore coverage; User password cast ni hashed; password/remember_token zimefichwa kwenye serialization; session ni HttpOnly na SameSite=lax; local filesystem default ina private root. Hakuna guarantee ya KYC safety: hakuna upload/reviewer workflow bado. Hakuna secrets zilizochapishwa au zilizopatikana kama tracked `.env`/SQL kupitia `git ls-files` scan; hii si full-history secret scan.

## H. Recommended Architecture — pendekezo, halijatekelezwa

Endelea na existing Laravel modular monolith. Tumia boundaries za Identity/Access, Merchants/KYC, Payments/Providers, Transactions/Ledger, Settlements, Developer API/Webhooks, Administration/Audit na Reporting. Usianze microservices au framework nyingine.

Kwa schema review ya kuidhinishwa baadaye: users zitenge identity na merchant membership; merchant status itenganishwe na verification status; kila merchant-owned record iwe na tenant boundary inayothibitishwa na policies na isolation tests. Admin roles na high-risk actions zipewe authorization, additional verification na audit entries.

Payments ziwe na explicit state-transition service, unique references, merchant-scoped idempotency na atomic database transactions. Ledger iwe append-only double-entry, yenye currency, integer minor units au fixed decimal policy, unique posting references na balanced entries. Fees zipewe rounding/versioning rules. Balance isitokane na editable frontend value au callback isiyothibitishwa.

Provider contract ianze na isolated MockPaymentProvider na callback inbox/event deduplication. Mock ihusishe success/failure/pending/cancelled/expired, delayed/duplicate/invalid callbacks, verification na timeout/retry. Selcom adapter isijazwe guessed endpoints au signatures. Official docs na authorized sandbox access ni prerequisites.

API v1 iwe na scopes, hashed API keys, rotation/revocation, throttling na sandbox/live separation. Merchant webhook delivery iwe queued na signed, yenye delivery records, retry limits na SSRF protections. KYC iwe private storage na authorized retrieval, upload validation na malware review plan. Dashboard isome verified ledger/transaction records pekee.

Schema, architecture na dependency changes hizi zinahitaji approval yako kabla ya implementation.

## I. Prioritized Development Roadmap

| Phase | Deliverable | Kigezo cha kukubali |
|---|---|---|
| 1 — Recovery/audit | Audit hii, source comparison na canonical recommendation | Hakuna frontend work iliyopotea; findings zina evidence |
| 2 — Local stabilization | Canonical Herd mapping, dedicated local DB plan, local secrets isolation, genuine HTML fixes, reference isolation, baseline backup/commit | Canonical URL/asset requests 200; browser console/layout checks; database connection verified bila SQL restore ya kujitolea |
| 3 — Foundation | Approved schema; users/merchants/auth/roles/isolation | Registration/reset/verification/session/rate-limit/tenant tests; migrations ziendeshwe tu baada ya explicit approval |
| 4 — Onboarding/admin | Business profile, private KYC, review na audit log | Unapproved merchants blocked; unauthorized reviewers denied; decisions audited |
| 5 — Payment engine | Mock provider, state machine, idempotency, callbacks, ledger/reconciliation | Duplicate/replay/timeout/concurrency tests; balanced entries; no double credit |
| 6 — Dashboard/API | Real records, payment links, reporting, API keys na merchant webhooks | No fabricated figures; tenant/API authorization tests; webhook retry/security tests |
| 7 — Selcom sandbox | Official documented provider adapter | Authorized credentials, verified callback security, sandbox end-to-end tests |
| 8 — Readiness | Security/performance review, monitoring, restore drill, deployment review | No unresolved launch blockers; explicit production launch approval |

## J. Immediate Next Actions — zinasubiri approval

1. Kubali `XLipa/` iwe canonical, kisha fungua `XLipa.code-workspace`; originals zibaki recovery sources.
2. Kubali Phase 2 pekee: kuunganisha Herd kwenye **`XLipa/backend`** kwa domain mpya ya local ili original mapping ihifadhiwe; kusahihisha local APP_URL na kuhakiki public assets/health/HTTP behavior. Hakuna production change.
3. Rekebisha closing script tags mbili na accidental navigation text kwa minimal diff; tenga BongoPay reference kwenye sehemu isiyo public bila kufuta original au kutumia branding/code yake kwa XLipa.
4. Chagua dedicated local MariaDB-compatible database environment na credentials zake. Herd service management haipatikani bila Pro hapa; installation nyingine ya local ni chaguo la kupanga. Usibadilishe SQLite kwa guessed hosting credentials.
5. SQL restore iwe approval tofauti baada ya target DB, privileges, version compatibility, backup na contents review. Migrations nazo ziwe explicit approval tofauti; usitumie `composer run setup`, `migrate:fresh` au auto-import.
6. Panga dependency remediation kwa dry-run/review na regression tests, bila broad upgrade isiyoeleweka. Kagua secrets kabla ya baseline Git commit na independent backup.
7. Baada ya local/browser verification, leta schema na auth/isolation implementation plan ya Phase 3 kwa approval.

### Verification na commands zilizofanywa

- `Get-Content`, `rg`, `Get-ChildItem`, `Get-FileHash`, read-only ZIP APIs, `git status --short`, `git log`, `git ls-files`, `git check-ignore`: inventory, history na integrity.
- PHP `-v`, Composer `show --direct --no-ansi`: runtime/dependency versions.
- `php artisan route:list --except-vendor`: routes 12.
- `php vendor/bin/phpunit --no-progress --do-not-cache-result`: **tests 2, assertions 2, zote passed**. Hakuna new business tests zilizoandikwa.
- Inline Laravel HTTP-kernel GET requests: **12/12 status 200**, CSRF placeholders hakuna iliyobaki raw. Requests zinaweza kuandika local runtime sessions/log/cache; si source au schema changes.
- Read-only SQLite query: table names 9; hakuna row data iliyochapishwa.
- Inline Node static scans: local links/fragments hakuna missing target; syntax defects mbili zilizothibitishwa baada ya kuhesabu unclosed scripts. External scripts hazikupakuliwa/kutekelezwa.
- `composer audit --locked --format=json`: advisories 7, packages 4; hakuna dependency mutation.
- `herd sites`, `herd list --raw`, `herd services:list`, service/registry/port checks na local curl requests: old mapping confirmed, 3306 refused, Pro requirement confirmed; canonical Herd delivery haijathibitishwa.

Python command haikupatikana kwenye execution context, hivyo static comparisons zilitumia PowerShell/.NET na Node. Baadhi ya tool accesses zilihitaji read-only sandbox escalation; hakuna rejection iliyozuia audit. Hakuna browser automation, SQL import, migration, installer, new server launch au production request iliyofanywa.

**Files changed katika audit hii:** report hii pekee, `XLipa/AUDIT-AND-RECOVERY-2026-11-06.md`. Existing source changes/untracked files zilikuwepo kabla ya audit na hazijafichwa kwa commit.

**STOP:** audit imekamilika. Phase 2 na phases zinazofuata hazijaanza; zinasubiri approval yako.
