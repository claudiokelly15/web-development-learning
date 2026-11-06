# XLipa — Local Database Activation Pre-flight

Tarehe: 6 Novemba 2026, Africa/Nairobi.

Canonical project: `C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT/XLipa`.

**Recommendation: GO ya kiufundi kwa migrations sita kwenye local SQLite iliyoainishwa, lakini execution bado ni HOLD mpaka owner atoe separate explicit approval.** Hakuna working-database migration iliyotekelezwa katika pre-flight hii. B3 haijaanza.

Reports za Stage A, B0/B1 na B2 zimesomwa pamoja na current code. B0/B1 report ni historical checkpoint: B2 code sasa ipo. Hali halisi iliyohakikiwa, si report ya zamani pekee, ndiyo msingi wa recommendation hii.

## A. Confirmed cause of HTTP 503

**503 inatokana na intentional authentication-readiness gate; current schema haijakidhi requirements.**

Evidence:

1. `backend/routes/web.php`: POST `/register` na `/login` zote zina `EnsureAuthenticationReady` kabla ya controller execution. Verbose route inventory imethibitisha middleware hiyo kwenye routes zote mbili.
2. `backend/app/Http/Middleware/EnsureAuthenticationReady.php` huhitaji user columns nne, required tables, environment local/testing na mailer log/array. Condition ikishindwa hutumia `abort_unless(..., 503, ...)`.
3. Runtime conditions zimepimwa kando:

| Condition | Actual result |
|---|---|
| Environment local/testing | **Pass: local** |
| Mailer log/array | **Pass: log** |
| User columns `account_status`, `auth_version`, `terms_version`, `terms_accepted_at` | **Fail: zote nne hazipo** |
| Required merchant/membership/application/audit tables | **Fail: hazipo** |
| Existing password-reset table | **Ipo** |
| Gate call kwenye current database | **503; next handler haifikiwi** |

`platform_role_assignments` pia haipo kama sehemu ya B1 foundation, ingawa gate ya B2 haiitesti moja kwa moja kwa sababu current auth flow haitumii platform privileges.

4. Actual HTTP reproduction: GET login/create-account pages ni **200**; POST zote mbili zenye valid CSRF lakini bila registration payload ni **503**, bila Location redirect. Requests zisipofikia gate zingehitaji validation, si kuunda records; hakuna account iliyoundwa. Kwa usahihi, hii ni direct HTTP 503 response, si HTTP 302 redirect.
5. Visible response ni standard generic Laravel Service Unavailable page; custom internal gate message haionyeshwi kama literal text. Hakuna SQLSTATE/stack details zilizoonekana katika response.
6. **Controlled comparison:** migrations zilezile sita zimewekwa kwenye disposable copy pekee; gate ikaruhusu next handler na kurudisha test response **204**. Huu si actual login success claim; ni uthibitisho kwamba schema deficiency ikiondolewa, same readiness code inapita kwa current local/mail settings.

### Laravel logs

`backend/storage/logs/laravel.log` imekaguliwa kwa sanitized categories na timestamps, bila kutoa raw messages, SQL bindings, credentials au tokens. Kuna historical/test SQL error entries na redacted authentication-failure entries; test suite ina deliberate audit-failure scenario. Hakuna direct evidence iliyopatikana kwamba current 503 ni fresh SQL connection error. Expected HTTP 503 exceptions hazihitaji kuonekana kama Laravel error-log entry, hivyo kutokuwepo kwa 503 log line si proof kwamba request haikufika Laravel.

Root-cause conclusion imetegemea route wiring, runtime condition matrix, direct gate test na disposable-clone comparison; si kukisia kutokana na logs au UI pekee. Hakuna gate, CSRF, limiter au account protection iliyolegezwa.

## B. Exact database target na environment

**Target pekee ya future approval:**

```text
C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite
```

Runtime configured SQLite path ime-resolvewa na kulinganishwa na canonical target kabla ya backup. Driver ni `sqlite`; database URL override ni empty. Hakuna MySQL/production connection iliyofunguliwa.

| Setting/check | Confirmed value |
|---|---|
| APP_ENV | local |
| APP_DEBUG | false |
| APP_URL | http://xlipa.test |
| DB driver | sqlite |
| Database URL override | empty |
| Sessions/cache | file / file |
| Queue | sync |
| Mail | log; no external delivery |
| Config cached | false |
| Working `PRAGMA integrity_check` | **ok** |
| Working `PRAGMA foreign_key_check` | **0 violations** |
| APP_KEY | Present; value haijaonyeshwa |
| Encryption/decryption probe | **Passed**, bila kuchapisha key au ciphertext |

Cipher configuration ni AES-256-CBC iliyopo. DDL yenyewe haihitaji kuencrypt records, lakini B2 na encrypted application casts zinahitaji existing app key. **Usi-run `key:generate` au kubadilisha key kama hatua ya activation.** Key/config backup ishughulikiwe privately kabla ya future encrypted data kuongezeka.

## C. Existing/pending migration status na dependency order

Working database bado ina tables **9**:

`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`.

Existing user/queue/cache/session/password-reset tables zina rows 0; new business tables bado hazipo. `migrations` pekee ina rows 3. Applied migrations zote ni batch 1:

```text
0001_01_01_000000_create_users_table
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_table
```

`artisan migrate:status --database=sqlite --no-interaction` imethibitisha pending files sita kwa order hii:

| Order | File katika `backend/database/migrations/` | Dependency/review |
|---|---|---|
| 1 | `2026_11_06_000001_add_account_controls_to_users.php` | Existing users; additive columns/defaults na numeric-version triggers |
| 2 | `2026_11_06_000002_create_merchants.php` | Creator FK kwenda users |
| 3 | `2026_11_06_000003_create_merchant_memberships.php` | Merchant/user/creator FKs |
| 4 | `2026_11_06_000004_create_platform_role_assignments.php` | User PK/FK na grantor FK |
| 5 | `2026_11_06_000005_create_merchant_applications.php` | Merchant na submitter/reviewer FKs; version/slot constraints |
| 6 | `2026_11_06_000006_create_audit_logs.php` | Users, merchants na composite application+merchant FK |

New table/column names hazina conflict na current working schema. Each file ina explicit SQLite driver guard. Application `(id, merchant_id)` unique key ipo kabla ya audit composite FK, hivyo dependency order inaendana.

Existing `sessions.user_id` ina index lakini haina FK, kama baseline ya Laravel iliyokaguliwa awali. Hilo halijabadilishwa na activation proposal na si blocker ya current file-session configuration.

### Rehearsal evidence

Exact source files zimehashiwa na zilezile zimeendeshwa kwenye **`.recovery/activation-preflight-2026-11-06/activation-rehearsal.sqlite` pekee**. Clone ilitengenezwa kutoka fresh backup, si guessed empty schema.

Results:

- Migration exit code **0**.
- Tables **14**, migration records **9**, users **0**.
- Integrity **ok**, foreign-key violations **0**.
- Same B2 readiness gate **passed**.
- Original working DB na immutable backup hashes **unchanged**.
- Full existing isolated test suite rerun: **63 passed / 521 assertions**.

Hakuna real administrator, merchant au user aliyeundwa kwenye working DB au activation clone. Test factories/notifications ndani ya PHPUnit ni isolated synthetic fixtures.

## D. Fresh backup na recovery verification

Fresh backup folder:

```text
XLipa/.recovery/activation-preflight-2026-11-06/
```

| Artifact | Hali |
|---|---|
| `working-before.sqlite` | Consistent SQLite Backup API snapshot kutoka read-only working connection; haijamigrateiwa |
| `runtime-and-backup.json` | Sanitized runtime checks, source/backup hashes, row counts, pending-file fingerprints |
| `activation-rehearsal.sqlite` | Disposable migrated clone, si working target |
| `clone-rehearsal.json` | Migration/gate/recovery results |
| `recovery-rehearsal.sqlite` | Restore-to-new-file rehearsal; original schema/data counts zimehakikiwa |

Backup integrity: **ok**. Backup schema inafanana na working schema; table row counts zinafanana. Recovery rehearsal imeunda **new separate SQLite file**, ikapita integrity/schema checks, migration count 3 na user count 0. Hakuna working file iliyo-overwriteiwa, kufutwa au kurestoreiwa.

### SHA-256

| File | Hash |
|---|---|
| Working SQLite, before/after | `0b18ddb07875c2dbaaa2f8c49909fc73abecfc2f3ce217c0a8defacb5a837c6d` |
| Fresh `working-before.sqlite` | `d0569e468ad2db34706bddb9f64887692d4381a4020ce2ec0337e600c3b2ea46` |
| Historical SQL, unchanged | `33d48ec8c9e9ee07a143390e8b7d1bed6c893382ae758057cc9226c5c5a2fb8e` |

Backup file hash si lazima ifanane na source byte-for-byte baada ya SQLite Backup API snapshot. Logical schema/count comparison, integrity checks na actual restore rehearsal ndizo additional verification; hash tofauti hapa haijachukuliwa kimakosa kuwa corruption.

Recovery inawezekana kwa kuhifadhi failed/partial state, kurejesha backup kwenye **alternate file**, na kuihakiki kabla ya owner-approved cutover. Hii haimaanishi ruhusa ya ku-overwrite working database automatically. Baada ya new registrations, kurestore old snapshot kunaweza kupoteza new data, hivyo recovery decision lazima ihakikiwe upya.

Backup/rehearsals zote ziko kwenye ignored `.recovery/`. SQL ya hosting imehifadhiwa bila import. Hizi ni same-disk backups, si off-device disaster recovery; off-device protected copy ni recommendation ya ziada.

## E. Migration risks na safeguards

- **Forward data loss:** reviewed `up()` definitions zinaongeza user columns na tables mpya; hakuna delete/drop/truncate ya existing data katika forward path. Existing users ni 0 kwa snapshot hii. Ikiwa state itabadilika kabla ya execution, pre-flight/backup zirudiwe.
- **Backward risk:** `down()` zinaondoa new schema/data; passing disposable rollback tests si approval ya kufanya working rollback. Usitumie `migrate:fresh`, `refresh`, `reset`, auto-rollback au database replacement kama shortcut.
- **Partial batch:** command ikifeli baada ya baadhi ya files, baadhi zinaweza kuwa committed. Simama, hifadhi error/state privately na usi-run destructive recovery. Rehearsal success haiondoi disk/permissions/process interruption risks.
- **Foreign keys:** types/order/composite uniqueness zimekaguliwa; cloned database na existing constraint tests zimepita. Foreign-key enforcement ibaki enabled.
- **SQLite-specific behavior:** numeric checks hutumia triggers; current_slot ni constrained `'1'`/NULL. Hii si portability clearance kwa MariaDB/PostgreSQL, wala proof ya production concurrency safety.
- **Keys:** existing APP_KEY ibaki unchanged. Encrypted snapshot/internal-note casts zinategemea key hiyo; hakuna key generation/rotation kwenye proposed commands.
- **Security gate:** gate ibaki. Baada ya schema kukamilika na existing environment/mail settings kubaki, imethibitishwa kupita kwenye clone. No bypass/fallback user creation inahitajika.
- **No implicit privileges:** migrations hazina seeders, administrator provisioning, merchant approvals, KYC au financial writes. Post-migration expected users bado 0 mpaka separately authorized/manual registration.
- **Local-only:** verified driver/path/env/mail conditions lazima zilinganishwe tena immediately before command. Usitumie `--force`, `--seed`, `composer run setup` au historical SQL import.

## F. Exact proposed activation commands — HAZIJATEKELEZWA

**Block hii ni review proposal ya baada ya separate explicit owner approval tu.** Inaweka exact six-file scope na kukataa drift ya source/database/config. Hakuna script ya activation iliyoundwa/kuendeshwa katika pre-flight.

```powershell
Set-Location -LiteralPath 'C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend'
$phpExe = 'C:\Users\hp\.config\herd\bin\php85\php.exe'
$evidence = Get-Content -LiteralPath '..\.recovery\activation-preflight-2026-11-06\runtime-and-backup.json' -Raw | ConvertFrom-Json
$target = 'C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite'
if ((Get-FileHash -LiteralPath $target).Hash.ToLowerInvariant() -ne $evidence.source_hash_before) {
    throw 'Working database changed. Repeat pre-flight and backup.'
}
$migrations = @(
    '2026_11_06_000001_add_account_controls_to_users.php',
    '2026_11_06_000002_create_merchants.php',
    '2026_11_06_000003_create_merchant_memberships.php',
    '2026_11_06_000004_create_platform_role_assignments.php',
    '2026_11_06_000005_create_merchant_applications.php',
    '2026_11_06_000006_create_audit_logs.php'
)
foreach ($name in $migrations) {
    $expected = $evidence.pending_file_hashes.PSObject.Properties[$name].Value
    if (!$expected -or (Get-FileHash -LiteralPath (Join-Path 'database\migrations' $name)).Hash.ToLowerInvariant() -ne $expected) {
        throw 'Migration source changed. Repeat review.'
    }
}
@'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$expected = realpath('C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT/XLipa/backend/database/database.sqlite');
$db = config('database.connections.sqlite');
if (!app()->environment('local') || config('database.default') !== 'sqlite'
    || !empty($db['url']) || realpath($db['database']) !== $expected
    || !in_array(config('mail.default'), ['log', 'array'], true)) {
    throw new RuntimeException('Refusing unexpected target/environment/mail configuration.');
}
echo "Canonical local SQLite target verified.\n";
'@ | & $phpExe
if ($LASTEXITCODE -ne 0) { throw 'Pre-activation target check failed.' }

& $phpExe artisan migrate:status --database=sqlite --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Migration status check failed.' }
$migrationArguments = @('artisan', 'migrate', '--database=sqlite', '--no-interaction')
foreach ($name in $migrations) {
    $migrationArguments += "--path=database/migrations/$name"
}
& $phpExe @migrationArguments
if ($LASTEXITCODE -ne 0) { throw 'Activation failed. Stop and review; do not auto-rollback.' }
& $phpExe artisan migrate:status --database=sqlite --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Post-migration status check failed.' }
```

Before executing an approved block, pause conflicting local writes and ensure fresh backup evidence still applies. Baada ya command: read-only integrity/FK/schema checks, expected tables 14/migration records 9, readiness check na local HTTP validation. No seeding/admin provisioning au automatic B3 work. Hakuna working-table deletion/rollback command iliyopendekezwa.

## G. GO / NO-GO recommendation

**GO ya kiufundi — conditional on separate owner approval, kwa exact six migrations na canonical local SQLite pekee.**

Sababu: exact source reviewed; no current naming conflict; integrity/FK checks pass; current data counts known; consistent backup verified; restore-to-new-file rehearsal passed; exact migration clone passed; B2 gate passes after clone migration; full isolated suite **63/521** passes; working/hash state haijabadilika.

**Execution status: HOLD.** Pre-flight authorization si migration authorization. Hii si GO ya production, SQL import, real admin provisioning, payment integration au B3.

## H. Decisions requiring owner approval

Owner anahitaji kuidhinisha wazi **ku-run migrations sita zilizoorodheshwa kwenye target ya local SQLite iliyo section B**, baada ya recheck ya saved evidence.

Approvals tofauti zitaendelea kuhitajika kwa B3, any data-destructive recovery/cutover, production/other database engine, real administrator provisioning, external email delivery, KYC au provider integration. Selcom documentation/credentials bado hazipo; hakuna provider behavior iliyobuniwa.

### Scope/evidence summary

Files mpya pekee: report hii na ignored backup/rehearsal/evidence artifacts ndani ya canonical `.recovery/activation-preflight-2026-11-06/`. Application code, routes, middleware, migration definitions, frontend, APP_KEY na production configuration **hazijabadilishwa**. Runtime test logs/cache/sessions zinaweza kuandikwa na existing test/HTTP requests; si working schema/data changes.

Executed: source/report reads, verbose auth route checks, `migrate --help`, **read-only** `migrate:status`, runtime/read-only SQLite diagnostics, SQLite Backup API, disposable recovery/migration rehearsal, isolated PHPUnit suite, CSRF-valid HTTP diagnostics zisizo na registration fields, sanitized log-category review, hashes na Git ignore/status checks. Hakuna working `migrate`, SQL import, record deletion, administrator creation au security bypass iliyofanywa.

**STOP: pre-flight imekamilika. Working-database migrations hazitaendeshwa bila separate explicit approval.**
