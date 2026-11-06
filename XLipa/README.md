# XLipa — mazingira ya kazi

**Hali ya sasa — 6 Novemba 2026:** B1 imekamilika na local database imeactivateiwa (application tables 14, migration records 9). B2 authentication na duplicate-submit correction zimekamilika; recovery audit imehakiki tests 64 / assertions 543 na frontend interaction checks 31. Working database ina user mmoja aliyethibitisha email, merchant mmoja wa onboarding/unverified, active owner membership moja, draft application moja, audit record moja na administrator assignments 0. Counts ni snapshot, si thamani za kudumu. [Recovery audit na mipaka ya verification](XLIPA-POST-REINSTALL-RECOVERY-AUDIT-2026-11-06.md).

**Current milestone — B3.1:** merchant context, read-only authorization policies na onboarding status vimetekelezwa; isolated full suite **108 tests / 847 assertions**, pamoja na frontend checks **31**, zimepita. `/account` ina links za active memberships; `/merchants/{merchant}/onboarding` ina status summary yenye tenant isolation na role-based redaction. Hakuna profile editing, submission/history au admin workflow. [B3.1 implementation report](XLIPA-PHASE-3-B3-1-IMPLEMENTATION-REPORT-2026-11-06.md).

**Hatua inayofuata:** B3.2 profile foundation inahitaji approval tofauti kulingana na [mpango wa B3](PHASE-3-B3-ONBOARDING-AUTHORIZATION-PLAN-2026-11-06.md); haijaanza. Production domain bado **haijachaguliwa**; `http://xlipa.test` ni local URL pekee. Selcom integration, public payment API na real payment processing havijatekelezwa. [Pre-B3.1 safety report na backup/restore evidence](XLIPA-PRE-B3-1-SAFETY-HARDENING-REPORT-2026-11-06.md), [trusted-host implementation na limitations](XLIPA-TRUSTED-HOST-IMPLEMENTATION-REPORT-2026-11-06.md). Historical reports zinabaki checkpoints zao za wakati huo.

Fungua `XLipa.code-workspace` kwenye VS Code.

- `frontend/`: HTML, CSS, picha na JavaScript (iliyomo ndani ya HTML). Hapa ndipo pa kubadilisha muonekano.
- `backend/`: Laravel iliyonakiliwa kutoka Downloads/xlipa-system/xlipa-system, pamoja na dependencies zake.
- `database/mkulbzij_xlipa_site.sql`: backup halisi ya hosting. Imehifadhiwa bila kubadilishwa na haijawekwa kwenye Git.
- `scripts/Sync-Frontend.ps1`: hunakili frontend kwenda Blade views na public assets za Laravel.

## Kuendesha

Unahitaji PHP 8.3 au zaidi na Composer. Herd iliyopo kwenye kompyuta ina PHP 8.5. Fungua terminal mpya ikiwa `php` haionekani.

Kutoka folder hii:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/Start-XLipa.ps1
```

Tembelea **http://xlipa.test**. Herd inaelekeza kwenye `XLipa/backend`; PHP built-in server haihitajiki. Task ya VS Code ni `XLipa: Open Herd site`. Script husync frontend, huhakiki Herd link na kufungua URL; tumia `-NoBrowser` kwa diagnostic bila kufungua browser.

Baada ya kuhariri `frontend/`, endesha `XLipa: Sync frontend` au script ya Sync-Frontend. Usihariri generated copies kwenye `backend/resources/views/xlipa/` au `backend/public/assets/`. Active file ya zamani ya root `WEB-DEVELOPMENT/xlipahomepage.html` si canonical.

## Database ya hosting

Backup imetoka MariaDB 11.4 na imehifadhiwa bila import. **Usi-import SQL au kuendesha migrations bila approval tofauti.** Usitumie `composer run setup`: ina `artisan migrate --force`. Usitumie hosting credentials kwenye local development.

Working database ni `backend/database/database.sqlite`; baada ya approved B1 activation ina application tables 14 na migration records 9. File sessions/cache na sync queue ziko active. `.env` ni local, `APP_DEBUG=false`, na `APP_URL=http://xlipa.test`. `.env.example` ina local defaults bila secrets. Historical SQL ya hosting haija-importiwa.

Old `xlipa-system.test` bado inaelekeza Downloads kwa recovery; si canonical XLipa na inaweza bado kushindwa kwa old MySQL configuration. Haijabadilishwa.

## Hali ya programu

Laravel ya chanzo ilikuwa skeleton yenye welcome route tu. B2 sasa ina native registration/login/logout/reset/verification na existing forms zimeunganishwa. Local schema imeactivateiwa. Authentication readiness gate bado huhitaji schema sahihi, environment local/testing na mailer log/array; si production enablement. Hakuna financial dashboard, payment API au miamala iliyounganishwa. Backup ya hosting bado imehifadhiwa bila import.

### Historia ya Phase 2

Takwimu na visual-QA limitations katika aya mbili zinazofuata ni checkpoint ya Phase 2; current verification ipo kwenye ripoti zilizounganishwa juu.

Phase 2: routes 11 (root + kurasa 10 za XLipa), PHP tests 16/80 assertions zimepita, HTTP assets 9 zimehakikiwa na DOM interaction checks 24 zimepita. BongoPay reference imehifadhiwa kwenye ignored `references/` nje ya serving paths; URL yake inarudisha 404. Haikuwa ukurasa wa XLipa. Application haina runtime dependency ya BongoPay.

Security patches zimebadilisha Symfony http-foundation/http-kernel/routing kwenda 8.1.11 na mime kwenda 8.1.10. `composer audit --locked` sasa haina advisories zilizoripotiwa. Huu si uthibitisho wa production readiness. Browser visual/mobile checks bado hazijathibitishwa kwa sababu computer-use ilizuiwa.

## Uhakiki wa maendeleo

Kutoka `backend/`: `php vendor/bin/phpunit --no-progress --do-not-cache-result`, `composer audit --locked`, na `composer check-platform-reqs`.

DOM test tooling imewekwa kwenye ignored recovery folder, si application dependencies. Kutoka `XLipa/`:

```powershell
npm install --prefix .recovery/phase2-tools --ignore-scripts --no-audit --no-fund jsdom@31.0.0
node scripts/Test-Frontend.cjs
```

Inahitaji Node version inayokubalika na jsdom 31: `^22.22.2 || ^24.15.0 || >=26.0.0`. DOM tests si browser visual tests. Usisubmit taarifa halisi kwenye demo forms.

Rollback snapshot: `.recovery/phase2-before/files/` pamoja na `manifest.json`; ina local `.env`, hivyo usi-upload/share folder hiyo. SQL na SQLite originals zimehifadhiwa. Ripoti ya utekelezaji na mipaka ya verification: [PHASE-2-REPORT-2026-11-06.md](PHASE-2-REPORT-2026-11-06.md). Audit ya awali ni historical baseline, si current-state report.

Mafaili ya awali ya WEB-DEVELOPMENT na Downloads yamehifadhiwa. Endelea kuhariri nakala ya frontend ndani ya XLipa ili kuepuka matoleo kutofautiana.

## Historia ya Phase 3 — B0/B1 kabla ya activation

Aya zinazofuata zimehifadhiwa kama historical checkpoints. Madai ya migrations pending/users 0/B2 not started hapa hayawakilishi hali ya sasa; soma current status juu na activation/recovery reports.

Six new migrations, models/enums/factories na isolated constraint tests zimeandaliwa. **Migrations hazija-run kwenye working database**; bado ina original tables 9 na migrations 3. Usitumie new factories/seeders humo mpaka upate approval ya schema execution. B2 authentication/frontend integration bado haijaanza.

Final B1 suite: tests 35 / assertions 264 passed; tests mpya hutumia guarded `:memory:` SQLite na disposable concurrency fixture pekee. Migrations hizi ni SQLite-targeted; engine nyingine inahitaji migration review. [B0/B1 report na exact pending migrations](PHASE-3-B0-B1-REPORT-2026-11-06.md).

## Historia ya Phase 3 — B2 kabla ya activation

Native registration/login/logout/reset/verification, account/auth-version enforcement, rate limits na original-form POST integration vimeandikwa na kuthibitishwa kwenye isolated tests. Final suite ni **63 tests / 521 assertions passed**, pamoja na DOM checks 24. [B2 report, files, risks na manual steps](PHASE-3-B2-REPORT-2026-11-06.md).

**Working SQLite bado haijafanyiwa migrations sita.** Public forms zinarender lakini auth POSTs hurudisha safe 503 mpaka upate approval ya schema execution. Usitumie factories/seeders au `composer run setup` kama shortcut. Hakuna real account/admin iliyoundwa. B3 haijaanza.

Local mailer lazima ibaki `log`/`array`, hakuna real email. Verification/reset messages ni private; usi-share logs/links/tokens. Native auth inaruhusiwa local/testing tu kwa milestone hii. Existing login/create-account submit handlers sasa zinatumia Laravel badala ya demo notices; earlier Phase 2/B1 status paragraphs ni historical milestones.

