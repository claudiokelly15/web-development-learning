# XLipa — POST Registration HTTP 429 Investigation

Tarehe: 6 Novemba 2026, Africa/Nairobi.

**Cause imethibitishwa: local POST `/register` imefikia registration limit ya requests 5 kwa IP ndani ya saa moja. Hakuna backend registration defect iliyothibitishwa.** Hakuna account iliyoundwa; kinga ya frontend duplicate submit imeongezwa. B3 haijaanza.

## Request na redirect flow

Actual HTTP probe ilitumia GET registration kupata session/CSRF, kisha empty POST yenye CSRF halali; haikuweza kuunda account. Matokeo:

- GET `/xlipacreateaccount.html`: **200**.
- POST `/register`: **429**, `X-RateLimit-Limit: 5`, remaining **0**, `Retry-After: 470` seconds wakati wa probe, **hakuna Location/redirect**.

Route ina `web`, guest/auth-response headers, schema-readiness na `throttle:auth-register`. Named throttle middleware hukagua quota kabla ya FormRequest validation/controller. Successful registration huenda POST -> transaction -> 302 GET `/xlipasignin.html`; GET login haina registration throttle. Hivyo reproduced 429 si redirect ya login.

## Cache, session na logs

`AppServiceProvider` ina `Limit::perHour(5)->by('register:'.$request->ip())`. Laravel named middleware key ni `md5('auth-register'.'register:'.$ip)`; FileStore huweka key kwenye SHA-1 directory filename. Read-only cache-file inspection ya IPv4 loopback ilionyesha counter **5**, timer bado hai (takriban sekunde 491 wakati wa inspection). IPv6 loopback haikuwa na matching entry. Cache na sessions ni **file**.

GET page haihesabiwi. CSRF-valid POST zote zinazofikia throttle huhesabiwa, hata validation failure au duplicate email. Sessions/new browser tab hazibadilishi quota ya IP. Local requests hushiriki loopback IP; verification harness ya awali ilituma validation-only POST ambazo pia zilichangia quota. Exact attribution ya attempts zote tano haiwezi kupatikana kutokana na aggregate counter pekee.

Laravel log ilikaguliwa bila kuchapisha contents; hakuna `429`, `Too Many Requests` au `ThrottleRequestsException` mentions. Hakuna per-request access history iliyopatikana inayothibitisha clicks za owner au duplicate POST counts. Kutokuwepo kwa log entry hakupingi actual HTTP response/cache evidence. Frontend ilikuwa native submit bila auto-retry/redirect JS, lakini haikuwa na in-flight duplicate guard; double-click/Enter repetition ilikuwa possible, si proven origin ya attempts zote.

429 hii ni expected protection. **Limit/security middleware/cache hazijapunguzwa, kufutwa au kubypassiwa.** Subiri muda uliopo kwenye Retry-After kabla ya kujaribu tena; kubadilisha session au email hakureseti IP quota. Verification inayofuata itumie isolated tests badala ya kujaza local working quota.

## Changes

1. `frontend/xlipacreateaccount.html`: first valid submit hufunga submit button na kuweka aria-busy; subsequent submit inazuiwa. Invalid form haifungwi. `pageshow` hurejesha button/state baada ya Back navigation. Native POST, CSRF, names, wording, CSS na design zimehifadhiwa. No JS dependency; server protection inabaki hata JS ikiwa disabled.
2. `backend/resources/views/xlipa/xlipacreateaccount.blade.php`: generated kupitia established `scripts/Sync-Frontend.ps1`, si independent backend edit. Sync imenakili views/assets nyingine bila intentional content changes.
3. `scripts/Test-Frontend.cjs`: regression checks za first submit, duplicate suppression, invalid-form state na Back/pageshow restoration. Initial test typo ya `window` imesahihishwa kuwa `dom.window`; final rerun imepita.
4. `backend/tests/Feature/AuthenticationTest.php`: test mpya ya invalid-attempt quota exhaustion, no-write 429, GET pages, expiry, valid registration na login-page redirect.
5. Report hii. Hakuna rate-limit/application-authentication backend policy change.

Sync script ilikataliwa mwanzo na local PowerShell execution policy; imerun kwa process-only `-ExecutionPolicy Bypass`. Hakuna persistent execution-policy setting iliyobadilishwa.

## Verification

- Authentication tests pekee: **29 passed / 279 assertions**.
- Full isolated suite: **64 passed / 543 assertions**, failures **0**.
- Frontend DOM: **10 pages / 31 interaction checks / 0 script errors**.
- Pint: passed.

Tests zinahakiki valid/invalid/duplicate registration, atomic rollback, password/session security, login success/failure, login rate limits na expiry, registration sixth-request 429, no records before blocked registration, registration recovery baada ya simulated saa moja, na successful redirect GET login. Tests zina guarded `testing` SQLite `:memory:` na notification fakes; foundation tests zina disposable schema setup pekee. Hakuna working migration/reset/rollback/seed command iliyotumika.

Hakuna fresh screenshot/real-browser visual claim; existing design source/CSS haijabadilishwa. Duplicate handling imehakikiwa na DOM execution.

## Canonical working database

`C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT/XLipa/backend/database/database.sqlite`.

Read-only PDO URI `mode=ro`: integrity **ok**, FK violations **0**. Counts:

| Table | Rows |
|---|---:|
| users | 0 |
| merchants | 0 |
| merchant_memberships | 0 |
| merchant_applications | 0 |
| audit_logs | 0 |
| platform_role_assignments | 0 |
| migrations | 9 |

Database SHA-256 kabla na baada ya tests: `c6fcc50e9aa1313702bea046b3a5c42b07c779a5587f3d37ce4de86d6a712dc6`, inalingana na previously verified activated schema yenye tables 14. Hivyo hakuna account graph iliyobaki kabla ya reproduced 429, wala working record iliyobadilishwa katika investigation. Logs hazitoshi ku-prove historical attempts zote, lakini current database haina registration records.

## Outcome na remaining risks

429 iliyoreproduceiwa ni quota exhaustion kabla ya transaction, si partial registration au failed login redirect. Working database na existing accounts/backups zimehifadhiwa; hakuna delete/admin/production/provider/payment operation. Frontend guard inapunguza accidental duplicate submissions; si cross-tab/network idempotency guarantee. Server quota bado ndiyo authoritative protection.

Nimesimama baada ya verification. B3 haitaanza bila approval nyingine.
