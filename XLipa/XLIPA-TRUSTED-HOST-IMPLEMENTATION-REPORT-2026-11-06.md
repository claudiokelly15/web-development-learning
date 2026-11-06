# XLipa — Trusted-host security implementation

Tarehe: 6 Novemba 2026, Africa/Nairobi. Authorization: final limited security implementation. XLipa ni browser-based payment gateway **website**. **B3.1 NOT STARTED.**

## A. Implementation Summary

**VERIFIED:** reviewed trusted-host patch imewekwa kwenye Laravel bootstrap, pamoja na permanent isolated integration tests. Bootstrap change ni lines 4 zilezile zilizoreviewiwa; hakuna material patch alteration. `git apply --check` ilipita kabla ya application, na `git apply --reverse --check` imepita baada yake, ikithibitisha approved hunk ipo kama ilivyotarajiwa. Original proposed-patch artifact haijabadilishwa.

Fresh results: **trusted-host tests 20/20, assertions 60; existing auth tests 29/29, assertions 279; full suite 84/84, assertions 603; frontend checks 31**. PHP syntax na required Pint formatting zimepita. Working database, `.env` na frontend hashes hazijabadilika.

Hii ni local implementation/testing clearance, si production deployment approval. Reviewed local/testing bypass imehifadhiwa. Host allowlist si full deployment APP_URL validator; limitation hii imeelezwa kwenye section J.

## B. Files Changed

Implementation paths mbili pekee:

1. `backend/bootstrap/app.php`: framework `trustHosts` registration yenye escaped, anchored hostname kutoka `config('app.url')`; `subdomains: false`.
2. `backend/tests/Feature/TrustedHostsTest.php`: mpya, methods 10 zinazopanuka kupitia data providers kuwa test cases 20. Hakuna RefreshDatabase, migration, factory persistence au working-database access katika class hii.

Ripoti hii ni separately requested documentation deliverable. Hakuna existing report iliyooverwriteiwa.

Initial repository: `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT`; canonical website project `XLipa`; Laravel root `XLipa\backend`; authoritative frontend `XLipa\frontend`. HEAD bado `da596994e6b25f21e8db2a1c04190e71bd77ec5e`, branch main.

Initial changes zilizokuwepo kabla ya task hii zimehifadhiwa: modified README, untracked proposed patch na recovery/pre-B3.1/review reports. README haijahaririwa tena katika implementation hii. Final additions ni approved bootstrap change, TrustedHostsTest na ripoti hii; hakuna staging, commit, push au history change.

## C. Security Behavior Before and After

| Control | Kabla | Baada — VERIFIED |
|---|---|---|
| Nonlocal/non-testing Host allowlist | Haijaenableiwa kwenye bootstrap | Framework TrustHosts imeenableiwa, exact APP_URL hostname pekee |
| Unknown/subdomain/lookalike hosts | Hakuna application allowlist | HTTP 400 kwenye protected-by-global-stack requests zilizojaribiwa |
| Wildcard acceptance | Hakuna explicit allowlist | Hakuna unrestricted wildcard; automatic subdomain trust imezimwa |
| Local/testing | Existing behavior | Framework environment bypass imehifadhiwa |
| URL generation | AppServiceProvider forceRootUrl/forceScheme kupitia APP_URL | Haijabadilishwa; named routes/assets/mail origins zinafuata config |
| CSRF, signed links, auth, limits | Existing controls | Hazijabadilishwa; existing auth regressions zimepita |
| Sessions | Existing cookie/session behavior | Guest redirect/session continuity tests na existing login/logout tests zimepita |

Laravel installed version imethibitishwa kwa `composer show laravel/framework`: **13.35.0**. APIs na behavior zimekaguliwa kwenye installed Foundation Configuration Middleware na Http Middleware TrustHosts sources. Hakuna dependency mpya.

## D. Local Environment Verification

Fresh live unauthenticated GET checks kupitia Herd:

| Path | Response |
|---|---|
| `/` | 200 |
| `/xlipasignin.html` | 200 |
| `/xlipacreateaccount.html` | 200 |
| `/forgot-password` | 200 |
| `/account` | 302 kwenda `http://xlipa.test/xlipasignin.html` |
| `/.env` | 404 |
| `/bongopay1.html` | 404 |

Active URL bado **http://xlipa.test**; `.env`/Herd/domain config hazijabadilishwa. Hakuna live registration/login/reset/verification POST iliyotumwa kwa working account. Successful authenticated browser flows ni **NOT VERIFIED upya kupitia live browser**; isolated auth suite ndiyo fresh behavioral evidence.

## E. Domain Independence Verification

Production hostname haijahardcodeiwa. `configured-host.example.test` na `alternate-fixture.example.test` ni synthetic test fixtures tu, si selected future production domain. Test helper inaload actual app/filesystem configuration kwa temporary process-only APP_URL values na kurejesha original `$_SERVER`/`$_ENV` values kupitia finally; `.env` file haiguswi.

Alternate-origin tests zimehakiki configured host accepted, old xlipa.test fixture rejected katika production-mode fixture, named login URL, asset URL, public-storage URL na reset-mail action URL zikifuata new configuration. Notification imejengwa kwa unsaved synthetic User; hakuna real mail dispatch au model persistence.

Changing domain in actual deployment bado itahitaji approved APP_URL/config-cache/worker updates, DNS/TLS/webserver setup, external callback registration pale providers watakapokuwepo na reissue ya old absolute signed links inapohitajika. Hakuna zero-downtime/session-continuity guarantee inayotolewa.

## F. Trusted-Host Test Results

`php vendor/bin/phpunit tests/Feature/TrustedHostsTest.php --no-progress --do-not-cache-result`:

**20 passed / 20 tests, assertions 60**, takriban sekunde 0.9.

Coverage:

- Actual bootstrap global middleware registration; hakuna manual prepend/mock ya TrustHosts kwenye permanent tests.
- Production/staging configured hostname acceptance kwenye health/home/sign-in.
- Unrelated, subdomain, suffix, regex-lookalike na malformed Host rejection kwenye health/home/sign-in. Raw Host headers hupitishwa kwenye actual HTTP kernel ili malformed URI isikataliwe na test-client URI builder kabla ya website middleware.
- Case-insensitive matching na port behavior: hostname accepted hata port ikibadilika; hii si port policy.
- Alternate configured origin, old-host rejection na URL generation.
- Invalid-hostname configuration cases: empty, relative path, missing hostname, malformed port, invalid hostname na wildcard configuration. Valid incoming fixture host imekataliwa kwa HTTP 400 katika cases hizi.
- Local/testing canonical URL na expected unknown-host bypass.
- Valid signed URL, query/path/host tampering, expiry na guest auth redirect kwa verification link.
- Guest account redirect, cookie-carrying session continuity, token continuity, host-only domain, HttpOnly na SameSite lax.

Isolation guard huanza kwa testing, SQLite `:memory:`, empty DB_URL na array session/cache/mail. Production/staging behavior husimulateiwa kwa test application environment baada ya guards; hakuna schema work. Teardown hurejesha testing environment na kusafisha Request trusted-host/TrustHosts static state. Actual local/production configuration files hazibadilishwi.

Initial new-test runs zilipata fixture errors, si confirmed website regression: service-provider lookup ilijaribu container construction badala ya existing registered provider; synthetic host ilioverrideiwa na URI builder; public-storage config ilihitaji configuration reload kwa alternate origin; session continuity ilihitaji test cookie carry-over; malformed host ilihitaji raw Request header badala ya invalid URI construction. Zote zimesahihishwa ndani ya approved new test file; hakuna assertion iliyoondolewa au security control iliyolegezwa.

## G. Full Laravel Regression Results

Required order ilifuatwa baada ya trusted-host tests kupita:

| Run | Actual fresh result |
|---|---|
| Existing `AuthenticationTest.php` | **29 passed, assertions 279**, takriban sekunde 5.9 |
| Complete PHPUnit suite | **84 passed, assertions 603**, takriban sekunde 10.7 |
| Syntax validation ya approved PHP files mbili | No syntax errors |
| `vendor/bin/pint --dirty --format agent` | passed |
| `git diff --check` | hakuna whitespace error |

Full suite ni historical tests 64 + new cases 20; assertions 543 + 60 = 603. Hakuna failure/skipped test iliyobaki kwenye final runner output. Existing auth suite inahakikisha registration graph, password reset, signed verification, CSRF, rate limits, auth_version, login/logout session regeneration na merchant-isolation foundations hazijaregress.

Testing process imelazimisha APP_ENV testing, DB_CONNECTION sqlite, DB_DATABASE `:memory:`, DB_URL empty, CACHE_STORE/SESSION_DRIVER/MAIL_MAILER array na QUEUE_CONNECTION sync. Existing auth/foundation migrations zimeendeshwa kwenye guarded disposable databases pekee, si working data. Existing read-only working-SQLite check na synthetic file-session test zimehifadhiwa.

## H. Frontend Regression Results

`node scripts/Test-Frontend.cjs`: **pages 10, interaction checks 31, DOM script errors 0**. Frontend files zote 19 (HTML 10/assets 9) zimefananishwa na pre-implementation hashes: **mismatches 0**. Hakuna HTML/CSS/JS, branding/contact/design change.

Browser visual/responsive QA katika 375/390/768/1280/1920px bado **NOT VERIFIED** kutokana na limitation ya browser tooling iliyoripotiwa awali. DOM pass haijatangazwa kama visual pass. Hakuna further browser attempt au redesign katika scope hii.

## I. Working Database Integrity

Source: `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite`.

Read-only SQLite3 inspection baada ya tests: integrity **ok**, foreign-key violations **0**; users 1, merchants 1, memberships 1, applications 1, audit logs 1, platform role assignments 0, migrations 9.

Before/after SHA-256:

`214ae83a31f53e80d3068d1aeb1366008bb5accb2df212dd2c534ae4121b6d8f`.

`.env` full-file hash pia unchanged; APP_KEY haijabadilishwa/haijachapishwa. Original proposed patch na pre-existing README hashes unchanged. Hakuna working DB migration, seed, user/merchant mutation, administrator creation au database reset. Backup/rehearsal ya previous safety work haijaoverwriteiwa.

## J. Remaining Risks

1. **Environment bypass:** Laravel huruka host allowlist katika local/testing. Production lazima ihakiki APP_ENV sahihi; patch haiwezi kulinda deployment iliyosanidiwa kimakosa kama local.
2. **Full APP_URL validation:** reviewed patch huchukua hostname kwa parse_url na kuifunga kwenye exact regex. Tested missing/malformed/wildcard-host configurations hufail closed. Hii si complete HTTP(S)-URL configuration validator; URL yenye valid hostname lakini unsupported scheme au unwanted credentials/path inaweza bado kutoa hostname inayoruhusiwa. Deployment preflight lazima ivalidate intended absolute HTTP(S) origin. Missing environment value inaweza kutumia existing localhost default. Hakuna extra validator iliyoongezwa nje ya reviewed patch; broader validation inahitaji separate review/approval.
3. **Infrastructure:** trusted proxies/forwarded-host headers, allowed ports, TLS na webserver host rules bado ni deployment decisions. Hakuna arbitrary proxy trust iliyoanzishwa.
4. **One-host policy:** automatic aliases/www/subdomains/old-domain overlap haziruhusiwi. Zikihitajika, panga explicit reviewed host list na migration runbook.
5. **Readiness limits:** visual QA, production-engine concurrency, independent off-device backup/key recovery na full production/payment enablement hazijathibitishwa na task hii. Selcom/public payment APIs hazijaongezwa.

Hakuna new confirmed CRITICAL/HIGH vulnerability au regression iliyobainika katika approved implementation scope. Hakuna exhaustive security certification inayotolewa.

## K. GO / NO-GO Recommendation

**GO kwa reviewed trusted-host implementation na local development regression clearance. CONDITIONAL GO kwa future development planning; NO production/deployment clearance inayotolewa.** All required executed checks zimepita, scope/preservation checks zimepita na behavior inalingana na reviewed patch. Wider URL validation na deployment prerequisites za section J zisichanganywe na completed exact-host filtering.

## L. Exact Next Milestone

Trusted-host security implementation/testing imekamilika. Website bado iko B1 activated + B2 authentication complete + B3 planning complete. **B3.1 — Merchant Context, Authorization Policies and Read-only Onboarding Status: NOT STARTED.**

**REQUIRES APPROVAL:** explicit owner authorization kabla ya B3.1 au broader configuration-validation work. Nimesimama; hakuna payment/provider integration, deployment, commit au push iliyofanywa.
