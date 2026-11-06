# XLIPA — Pre-B3.1 Safety Hardening

Tarehe: 6 Novemba 2026; Africa/Nairobi. Wigo: backup/restore verification, domain preparation, visual-QA attempt, documentation reconciliation na isolated regressions. **B3.1 haijaanza.**

## A. Executive Summary — Muhtasari

**CONDITIONAL GO kwa kuandaa B3.1; hakuna ruhusa ya kuanza implementation iliyotolewa katika kazi hii.** Consistent SQLite backup na restore rehearsal kwenye database tofauti zimekamilika. Integrity, schema na counts zote zinalingana na source; original working database hash haijabadilika. Backup imehifadhiwa nje ya serving paths, ndani ya ignored `.recovery`, bila ku-overwrite backup yoyote.

Fresh PHPUnit regression: **64 tests / 543 assertions**, zote zimepita. Frontend: **31 interaction checks**, pages 10, errors 0. Existing domain behavior imepita **32 isolated checks** kwenye local URL na synthetic alternate HTTPS URL, bila kubadili active local configuration. Hizi 32 ni process-level probes tofauti na PHPUnit totals; hakuna permanent test class mpya iliyoongezwa.

**Hakuna application-code, security-configuration, frontend au business-logic change iliyofanywa.** README pekee imehaririwa kwa status reconciliation; historical reports zimehifadhiwa. Trusted-host patch imeandaliwa kama artifact isiyoapplyiwa. Browser automation imezuiwa na environment policy/capability; visual QA yote ya required widths ni **NOT VERIFIED**, si passed.

## B. Initial Git State — Hali ya mwanzo

Canonical project: `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa`; Laravel: `XLipa\backend`; authoritative frontend: `XLipa\frontend`; Git root: `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT`. Paths zimethibitishwa kabla ya work.

HEAD: `da596994e6b25f21e8db2a1c04190e71bd77ec5e`, branch `main`. Initial `git status --short` ilikuwa:

```text
?? XLipa/XLIPA-POST-REINSTALL-RECOVERY-AUDIT-2026-11-06.md
```

Hiyo ni deliverable ya audit iliyotangulia; haijafutwa, kuandikwa upya au kucommitwa. Hakukuwa na staged/modified tracked files mwanzoni. Hakuna unrelated parent-directory project iliyohaririwa. Hakuna commit, push, reset au history rewrite.

Nyaraka za context zimesomwa: post-reinstall recovery audit, B3 onboarding/authorization plan, B2 report na Stage A architecture. Backend AGENTS.md pia imesomwa; `.ai/rules` haipo. Historical statements hazijachukuliwa kama current data bila verification.

## C. Database Backup and Restore Verification — Backup na rehearsal

Working source: `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite`.

Backup directory mpya, iliyoundwa 6 Novemba 2026 saa **14:04:09 Africa/Nairobi** (11:04:09 UTC):

```text
C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\.recovery\pre-b3-1-20261106-110409-6436dcc9\
  working-backup.sqlite
  restore-rehearsal.sqlite
  verification.json
  README.before.md
  README-change.diff
  domain-local.json
  domain-alternate.json
```

### Njia iliyotumika

1. SQLite target configuration imehakikiwa: driver SQLite, hakuna DB_URL override wala unexpected explicit DB_DATABASE.
2. Source imefunguliwa kwa `SQLite3(..., SQLITE3_OPEN_READONLY)`. `PRAGMA integrity_check`, foreign_key_check, schema inventory na table counts zimesomwa.
3. `SQLite3::backup()` imetumika kuunda **working-backup.sqlite**, si raw live file copy. Directory ina unique timestamp/random suffix; previous backups hazijaoverwriteiwa.
4. Backup imefunguliwa read-only, kisha backup API imetumika kuirestore kwenye **restore-rehearsal.sqlite**, target mpya tofauti. Working database haikutumika kama restore target.
5. Source, backup na restore zimefananishwa kwa integrity, FK violations, schema hash na counts za tables zote. Source file hash imehakikiwa tena baada ya tests na HTTP checks.

| Check | Source | Backup | Restore rehearsal |
|---|---|---|---|
| Integrity | ok | ok | ok |
| Foreign-key violations | 0 | 0 | 0 |
| Application tables | 14 | 14 | 14 |
| Migration records | 9 | 9 | 9 |
| Users | 1 | 1 | 1 |
| Merchants | 1 | 1 | 1 |
| Memberships | 1 | 1 | 1 |
| Applications | 1 | 1 | 1 |
| Audit logs | 1 | 1 | 1 |
| Platform role assignments | 0 | 0 | 0 |
| Password reset tokens | 0 | 0 | 0 |

Cache, cache_locks, jobs, job_batches, failed_jobs na database sessions counts ni 0 kwa nakala zote. Internal sqlite_sequence ina rows 3; hii si application record count. Schema hash ya zote:

`33b1a138c95e80f1b0d26aa1fe2b4b2aec213bb2a8f74ea6e817e62f8ddb84c8`.

Source SHA-256 kabla/baada:

`214ae83a31f53e80d3068d1aeb1366008bb5accb2df212dd2c534ae4121b6d8f`.

Backup na restored-file SHA-256, kila moja:

`21cbef77e4d76db1b44cb37003e980e160c315713ed09ad5779e69a8b7e9f75a`.

Backup file hash tofauti na source si failure: SQLite backup API inaweza kubadili physical database header; schema na aggregate state zinalingana, na restored file inalingana na backup. Rehearsal hii haijafanya application-level encrypted-field decryption recovery au full machine disaster recovery.

### Privacy na preservation

`git check-ignore` imethibitisha backup path imeignoreiwa; `git ls-files` haijaonyesha backup, `.env` au working SQLite kama tracked files. Directory ipo nje ya backend/public. Live request kwa `/.recovery/.../working-backup.sqlite` ni **404**; `/.env` pia 404. Path yenye `/../` kupitia fetch ilinormalizeiwa na client, hivyo si independent path-traversal penetration test.

Backup/rehearsal zimeachwa zipo. ACL za workspace zimerithiwa, zina owner/SYSTEM/Administrators na sandbox identities; hakuna ACL/security-settings change iliyofanywa. “Private” hapa ina maana nonpublic local artifact iliyotengwa na Git, si encryption-at-rest certification au exclusive-owner ACL guarantee. Usipublish `.recovery`; backup ina actual account data. Iko kwenye same OneDrive workspace/disk, hivyo **off-device independent backup bado NOT VERIFIED**. APP_KEY haija-exportiwa; restore ya encrypted application data itahitaji key iliyohifadhiwa salama kupitia separate recovery procedure.

Initial invocation ya backup ilisimama kwa PHP method-call error (`enableExceptions` kuitwa statically), kabla ya database yoyote kufunguliwa au target kuundwa. Inline invocation ilisahihishwa kuwa instance call na backup nzima ikakamilika. Hakuna project tooling/application file iliyobadilishwa kwa correction hiyo.

## D. Domain Independence Findings — Hali ya portability

**PARTIALLY READY.** Future production domain bado UNKNOWN; `http://xlipa.test` haijabadilishwa. `https://alternate-fixture.example.test` ni synthetic in-process test fixture tu: haijasajiliwa, haijadeployiwa, haijasanidiwa kwenye Herd/DNS au `.env`.

| Eneo | Ushahidi wa current behavior | Hatua inayohitajika |
|---|---|---|
| APP_URL/named routes | config/app.php na AppServiceProvider forceRootUrl/forceScheme; local/alternate URL checks zimepita | Keep configuration-driven; validate deployment APP_URL kabla ya enablement |
| Authentication redirects | Guest `/account` redirects kwa named login chini ya configured origin; existing safe-intended-redirect tests zimepita | Hakuna current app change inayohitajika |
| Signed links | Valid signature accepted; modified path/host na expired link rejected kwenye fixtures zote mbili | Domain move itahitaji reissue ya old absolute signed links |
| Session/cookies | Host-only domain null, HttpOnly true, SameSite lax; secure false kwenye HTTP fixture, true kwenye HTTPS fixture | Production itahitaji real HTTPS na Secure cookie; active local HTTP iachwe |
| Assets/storage | asset() na public storage URL zimefuata APP_URL kwenye fixtures zote | Optional ASSET_URL kwa future CDN; hakuna CDN inayohitajika sasa |
| Mail | Synthetic reset notification URL imefuata APP_URL; hakuna mail iliyotumwa | Actual sending address/EHLO na transport ziwe approved deployment configuration |
| Trusted hosts | Bootstrap hai-enable TrustHosts; forceRootUrl haimaanishi inbound host rejection | Proposed exact patch kwenye section F, NOT APPLIED |
| APIs/CORS | Hakuna public payment API; framework default cors.php ina paths api/*/sanctum/csrf-cookie, wildcard origins, credentials false | Future API itahitaji explicit allowed origins kabla ya exposure; hakuna config change sasa |
| Proxies/HTTPS | Hakuna new custom proxy allowlist; deployment topology haijachaguliwa | Usitrust arbitrary proxies; decide trusted ingress addresses/header policy wakati wa approved deployment |
| Webhooks/callbacks | Hakuna provider integration/callback contract | Future provider config na callback registration strategy vifuate official contract; hakuna endpoint iliyobuniwa |

Framework default `app.asset_url` husoma ASSET_URL hata kama project config/app.php haijaorodhesha key hiyo; installed framework config/app.php na RoutingServiceProvider vimethibitisha wiring. Separate CDN override haijajaribiwa katika run hii.

Support address `support@xlipa.net` imeachwa kama ilivyo kwenye static canonical pages na mailto JavaScript. Ili kuiweka configuration-driven kikamilifu kutahitaji template/static-source convention na sync/browser tests. **Pendekezo tu:** server-rendered config value + context-safe escaping/encoding, displayed value ibaki sawa; kwanza idhinisha scope ya static frontend integration. Hakuna approved wording, address, color, logo au layout iliyobadilishwa.

Required future operational settings: APP_URL, optional ASSET_URL, SESSION_DOMAIN/SESSION_SECURE_COOKIE/SESSION_SAME_SITE, mail sender/EHLO/transport, explicit API origins pale API itakapokuwepo, deployment-specific trusted proxies na provider callback registration. Custom names za future provider/origin env variables hazijatungwa kama functionality iliyopo. APP_KEY ibaki; no security bypass.

## E. Changes Implemented — Kazi iliyokamilika

- Consistent private SQLite backup, separate restore rehearsal na sanitized verification manifest zimeundwa na kuhifadhiwa.
- README imerekebishwa kueleza B1/B2 complete, local DB activated, B3 planning complete, B3.1 not started, production domain unknown na Selcom not implemented. Original README imehifadhiwa byte-for-byte kwenye backup directory kabla ya edit.
- Historical README checkpoints zimewekewa headings/maelezo ya historia; historical reports hazijaoverwriteiwa.
- Local/alternate domain verification artifacts, proposed patch na report hii zimeandaliwa.

**Hakuna application PHP, test class, security config, migration, `.env`, APP_KEY, frontend, generated source, route au business record iliyohaririwa.** Framework test execution inaweza kutengeneza compiled views/manifests na synthetic session/temporary test files; working database haijaandikiwa. Hakuna cache-clear, owner-session clearing, migration dhidi ya working DB, administrator creation, integration au deployment.

## F. Changes Requiring Owner Approval — Proposed patch

Artifact ya review: **`PRE-B3-1-TRUSTED-HOSTS-PROPOSED.patch`**, kwenye canonical XLipa root. Hii ni diff text tu; **haijaapplyiwa**. `git apply --check --verbose XLipa/PRE-B3-1-TRUSTED-HOSTS-PROPOSED.patch` kutoka repository root imepita; hiyo ni applicability check, si behavioral test pass.

Exact addition inayopendekezwa ndani ya `backend/bootstrap/app.php`, kabla ya redirects:

```php
$middleware->trustHosts(
    at: fn (): array => ['^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '/').'$'],
    subdomains: false,
);
```

Mechanism imethibitishwa kwenye installed Laravel 13.35.0 source: Foundation Configuration Middleware::trustHosts registers supported middleware; Http Middleware TrustHosts::hosts uses supplied callable; shouldSpecifyTrustedHosts bypasses **local environment na runningUnitTests**. Exact escaped, anchored APP_URL hostname ndiyo proposed allowlist; hakuna wildcard/subdomain acceptance. Invalid/missing configured host itatoa pattern isiyoruhusu valid hostname, hivyo deployment lazima ihakiki APP_URL. Hii si port allowlist au substitute ya webserver Host validation.

**Scope muhimu ya proposal:** preserve framework local/testing bypass kwa minimal change; production/staging requests pekee zitafanyiwa host filtering. Hii haitaanzisha unknown-host rejection kwenye local Herd. Ikiwa owner anataka host rejection kwenye local pia, hiyo ni scope tofauti yenye custom middleware/design review; haijapendekezwa kama automatic patch. No production domain hardcoded; changing APP_URL changes expected host. Old-domain overlap itahitaji separately approved exact-host list/redirect plan badala ya wildcard.

### Test coverage inayopendekezwa baada ya approval

Permanent tests zitumie isolated config na no working database connection. End-to-end auth fixtures ziendelee na guarded SQLite `:memory:`, Notification fakes na array sessions/cache/mail. Hakuna test ya proposal inayodaiwa kupita sasa.

| Proposed coverage | Acceptance |
|---|---|
| Registration ya middleware kwenye actual global stack | Trusted-host middleware ipo baada ya bootstrap; test isiwe standalone regex check tu |
| Production-like enforcement | Isolated request lifecycle isimulate nonlocal/non-testing host guard, DB bado memory; canonical public page 200, unknown host 400 |
| Exact host matching | Reject sibling/subdomain, suffix/lookalike, malformed host; accept canonical hostname case normalization |
| Alternate APP_URL | Synthetic alternate HTTPS host accepted, previous fixture host rejected, generated auth/mail/assets canonical kwa new config |
| Local compatibility | Actual local xlipa.test/Herd public pages na 64-test auth/foundation regression ziendelee; framework local/testing bypass ithibitishwe kwa uwazi |
| Signatures/redirects/cookies | Preserve valid/expired/tampered signature checks, auth redirects, host-only/HttpOnly/lax/Secure policy kwa fixtures sahihi |
| Test isolation | Restore application environment, Request::setTrustedHosts state na TrustHosts::flushState baada ya kila scenario; no cross-test state leak |

Production-like test lazima ipite Laravel default unit-test bypass kwa explicit isolated fixture; `withServerVariables(HTTP_HOST)` pekee ndani ya normal testing env haitathibitisha guard. Vipimo vya existing behavior 32 katika section I havithibitishi inbound rejection ya proposed patch.

Approval inaombwa kwa bootstrap addition hii pamoja na permanent isolated coverage iliyotajwa, kisha focused/full regressions. Haihusishi production activation, proxy/CORS reconfiguration, frontend contact rewrite au B3.1.

## G. Browser Visual QA — NOT VERIFIED

Skill ya computer-use imesomwa. Browser provider attempt ilirudisha **“The browser access host is unavailable”**; inventory haikuwa na connected browsers. Native Firefox fallback ilizinduliwa, lakini capture/control ikasimamishwa kwa ujumbe: **“browser URL policy enforcement is not yet supported for the current Windows browser.”** Hakuna further browser input iliyofanywa baada ya stop hiyo. Hakuna screenshots, verified viewport size au browser console observation iliyopatikana. Tool completion summary imefanywa.

Hivyo **375, 390, 768, 1280 na 1920px zote NOT VERIFIED**. Hakuna madai ya browser pass kutoka DOM tests. Firefox inaweza kuwa imeachwa wazi na fallback launch; hakuna account login/form submit iliyofanywa.

### Manual checklist ya owner

Kwa kila viewport width 375/390/768/1280/1920px, browser zoom 100%, fungua `http://xlipa.test` kupitia responsive-mode viewport yenye width inayothibitishwa:

- Home, About, Contact, Developers, Sign In, Create Account na legal page moja: inspect header/footer, typography, images na content yote kwa vertical scroll.
- Hakikisha hakuna horizontal page scrolling, text clipping, overlaps au buttons zilizofichika; linganisha logo/colors/layout na approved design.
- Mobile/tablet menu: open/close, Escape, link navigation; desktop navigation na auth links zifunguke kwa expected pages.
- Sign-in/registration: labels, password reveal, keyboard focus/tab order, required-field feedback na button visibility. Usisubmit valid working registration kwa QA fixture mpya; duplicate-submit behavior tayari ina isolated DOM coverage.
- Contact: inspect layout, links na validation tu; usitume email ya QA. Angalia assets zimepakia bila broken images.
- Browser DevTools Console/Network: record JS errors, failed assets na response statuses bila kunakili auth cookies/tokens. Evidence iwe screenshot per viewport/page inayohitajika na issue list.

Hakuna design correction itafanywa kutokana na hypothetical issue; verified defect ihitaji proposed safe patch na approval chini ya scope ya ombi hili.

## H. Documentation Status — README reconciliation

Original README SHA-256: `8f3b3f38b68191cc86ff00b0d2622c9c8092716145e183c7ea0b82bd6b456162`; preserved `README.before.md` imefanana. Current README ina accurate dated summary na links za recovery/B3/pre-B3.1 reports. Madai ya zamani users 0/migrations pending/B2 not started yameachwa kama historical checkpoints zilizoelezwa wazi, si active status.

Nyaraka nne za context na historical completion reports hazijabadilishwa. Report hii ni mpya; hakuna previous report iliyooverwriteiwa. README ni developer documentation, si approved frontend content; edit yake iko ndani ya Stage 5 documentation authorization.

## I. Regression Test Results — Fresh verification

Kabla ya suite, test guards na phpunit.xml zimehakikiwa tena. Child process imelazimisha APP_ENV testing, DB_CONNECTION sqlite, DB_DATABASE `:memory:`, empty DB_URL, mail/cache/session array, queue sync. Auth/foundation guards zinakataa target nyingine kabla ya migration; concurrency DB ni disposable temporary fixture pekee. Read-only working-DB test na synthetic file-session test zimeachwa kama existing suite.

| Check | Matokeo |
|---|---|
| PHPUnit existing full suite | **64 tests, 64 passed, 543 assertions**, takriban sekunde 11.8 |
| Offline frontend | **31 checks**, pages 10, DOM errors 0 |
| Existing-domain process fixture | **16/16 passed**, APP_URL http://xlipa.test |
| Alternate-domain process fixture | **16/16 passed**, synthetic HTTPS APP_URL |
| Proposed patch applicability | git apply --check passed; patch NOT applied |
| Generated Blade/assets vs canonical | mismatches 0 |
| Working SQLite source hash | unchanged baada ya backup, probes na regressions |
| Backup/rehearsal files | retained; hashes unchanged baada ya verification |
| Private-path HTTP probes | backup path na .env 404; root 200 |
| Git scope | tracked modification README pekee; no backend/frontend/scripts diff, no staged changes |

Domain process probes 16 kila moja: process APP_URL loading, login/register named URLs, asset URL, storage URL, valid signature, tampered path rejection, tampered host rejection, expiry rejection, reset-mail origin, host-only session, HttpOnly, SameSite lax, Secure-cookie fixture, guest auth redirect na configured test DB bado memory. Hizi zimetumia actual Laravel bootstrap/providers, synthetic unsaved user na array transport; hakuna mail dispatch, external HTTP request au model persistence. HTTPS fixture ni URL/config verification, si TLS certificate/browser transport test. Hakuna migrations katika probes hizo.

Existing PHPUnit authentication tests zimehakiki registration atomicity, reset/verification, account/auth_version, session/CSRF, limits na safe redirects. Merchant tests zimehakiki relationships, ownership/platform-role separation, constraints na cross-merchant denial. Hakuna tests zilizopunguzwa/controls zilizolegezwa. Hakuna permanent test file iliyobadilishwa; future host enforcement coverage inasubiri approval.

## J. Remaining Risks — Mipaka iliyobaki

1. **MEDIUM:** Visual QA/browser console katika widths zote haijathibitishwa; manual checklist ipo.
2. **MEDIUM:** Trusted-host change imependekezwa tu; inbound host filtering haijaenableiwa. Framework CORS defaults lazima zipitiwe kabla ya future API exposure; hakuna current public API iliyoongezwa.
3. **MEDIUM:** Backup/rehearsal ni same-workspace recovery, si independent/off-device disaster-recovery guarantee; key recovery na full encrypted-data restore drill bado vinahitaji operational plan.
4. **LOW:** Support email bado hardcoded kwa kutii frontend-content scope; portability ya contact content inahitaji approved template proposal.
5. Existing local-only auth gate, HTTP-only Herd environment, SQLite target-engine concurrency limitations na historical log-error attribution bado vina mipaka ya recovery audit; run hii haijatoa production/payment certification.

Hakuna working data loss, new CRITICAL/HIGH regression au secret commitment iliyothibitishwa. No secrets committed ni scope check: hakuna commit/staging iliyofanywa na private files hazifuatiliwi; si exhaustive historic Git-secret scan.

## K. B3.1 Readiness Decision — Uamuzi

**CONDITIONAL GO kwa next approved preparation/implementation step.** Current-data backup/restore verification imekamilika, B2/foundation regressions ziko intact na documentation status imesawazishwa. Domain hardening iko proposal stage; visual acceptance bado NOT VERIFIED.

Exact milestone haijabadilika: B1 activated, B2 complete, B3 planning complete, **B3.1 NOT STARTED**. Verified email moja haimaanishi merchant approval; onboarding draft graph imehifadhiwa bila mutation. Hakuna migrations dhidi ya working DB au additional product functionality.

## L. Recommended Next Step — Hatua inayofuata

Owner apitie proposed trusted-host diff na coverage ya section F, kisha atoe idhini ya wazi ikiwa anataka iapplyiwe na permanent tests ziongezwe. Approval requirement inatoka kwenye maelekezo ya ombi hili: “For application-code or security-configuration changes, prepare a proposed patch and request approval before applying it.”

Kamilisha manual visual checklist au rudisha browser automation capability ili remaining visual status ithibitishwe. Baada ya review ya evidence/limitations na explicit approval tofauti, B3.1 ifuate existing B3 plan: merchant context, authorization policies na read-only onboarding status, bila migration au business-data write. **Nimesimama kabla ya application/security patch na kabla ya B3.1.**
