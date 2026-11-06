# XLipa — Trusted-host patch review

6 Novemba 2026, Africa/Nairobi. **REVIEW ONLY — PATCH NOT APPLIED. B3.1 NOT STARTED.**

## Uamuzi

Proposed patch inatumia API sahihi ya installed **Laravel 13.35.0**: `Middleware::trustHosts(at: callable, subdomains: false)`. Hakuna application-code improvement ya lazima iliyobainika kwa scope iliyoelezwa: exact configured hostname enforcement katika nonlocal/non-testing environments, huku framework local/testing bypass ikihifadhiwa. Original patch imeachwa bila kuandikwa upya; hakuna updated implementation patch inayohitajika kwa scope hiyo.

**Sharti muhimu:** hii si all-environment host enforcement. Ikiwa hitaji ni kukataa unknown hosts hata kwenye local/testing, original patch haitimizi hitaji hilo na itahitaji proposal tofauti kabla ya implementation approval.

Nimekagua proposed patch, pre-B3.1 safety report, bootstrap, AppServiceProvider, routes, session config, phpunit.xml, AuthenticationTest na installed framework middleware/request handling. Hakuna permanent domain-specific test class iliyopo; existing auth suite ina canonical notification-host, signed-link, CSRF, session na redirect tests.

## 1. Files ambazo zingebadilika

| File | Proposed change |
|---|---|
| `backend/bootstrap/app.php` | Ongeza lines 4 za supported trusted-host registration kabla ya existing redirect setup |
| `backend/tests/Feature/TrustedHostsTest.php` — mpya, planned | Isolated middleware-registration, host-enforcement, bypass na alternate-domain tests; domain regression cases zinaweza kuwekwa kwenye class hii |

Exact application addition bado ni:

```php
$middleware->trustHosts(
    at: fn (): array => ['^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '/').'$'],
    subdomains: false,
);
```

`preg_quote` hufanya hostname kuwa literal regex value; anchors huzuia suffix/lookalike matching. `subdomains: false` huzuia Laravel kuongeza automatic subdomain wildcard. Closure husoma configuration wakati middleware inapohitaji host patterns. Hakuna dependency/package mpya, migration, model/business action, frontend au `.env` edit inayohitajika.

Test file ni scope ya future implementation, bado haijaandikwa. Original diff ina bootstrap addition pekee; test coverage hapa ni acceptance specification, si implemented test patch.

## 2. Security behavior kabla na baada

| Eneo | Kabla | Baada ya proposed implementation |
|---|---|---|
| Inbound host allowlist | App bootstrap hai-enable TrustHosts | Nonlocal/non-testing: APP_URL hostname pekee; unknown/subdomain/lookalike rejected |
| Local/testing | Hakuna configured host allowlist | Framework bypass inaendelea; valid unknown host haikataliwi na allowlist katika environments hizi |
| Generated auth URLs | AppServiceProvider forceRootUrl/forceScheme kutoka APP_URL | Inabaki hivyo; host rejection ni control ya ziada, si replacement |
| CSRF/signed verification | Existing framework middleware, signed routes na auth tests | Hakuna exemption au signature-validation change |
| Sessions/auth | Existing guards, regeneration, auth_version, limits na cookie configuration | Hakuna intended behavior change kwa accepted hosts; untrusted nonlocal request itakataliwa |

Supported APIs zimethibitishwa kwenye installed `Illuminate/Foundation/Configuration/Middleware.php` (`trustHosts`, global-stack registration) na `Illuminate/Http/Middleware/TrustHosts.php` (`hosts`, `handle`, `shouldSpecifyTrustedHosts`). Underlying Symfony Request hufanya host validation na request-host normalization. ForceRootUrl peke yake si host rejection.

## 3. Local development impact

`http://xlipa.test`, Herd routing na active APP_URL vitabaki. Laravel `shouldSpecifyTrustedHosts()` huruka local environment na `runningUnitTests()`; haitaweka production-only allowlist restrictions kwenye normal Herd requests. Hii ni deliberate compatibility boundary, si unrestricted wildcard kwenye enforced environments.

APP_KEY, CSRF/session settings, frontend wording/design na working SQLite hazihusishwi na diff. Existing local-only authentication readiness gate itabaki; patch haitaruhusu production authentication iliyozuiwa kwa milestone ya sasa.

## 4. Future domain migration

Hakuna production domain hardcoded. Synthetic alternate-domain probe imeonyesha allowlist ikifuata changed in-process `config('app.url')`; old fixture host imekataliwa. Future actual move itahitaji APP_URL na deployed configuration/cache/workers kusasishwa kwa approved procedure, pamoja na DNS/TLS/webserver/provider callbacks pale zitakapokuwepo.

Existing absolute signed links za old origin zinaweza kuhitaji reissue; browser sessions haziwezi kuahidiwa kuendelea kwenye domain nyingine. `www`/subdomains/old-domain overlap haziruhusiwi automatically: zikitakiwa, panga explicit exact-host list/redirect strategy tofauti. Hii si port, HTTPS, CORS au trusted-proxy policy; settings hizo hazibadilishwi na patch.

## 5. Verification iliyofanywa na tests zitakazoongezwa

**Fresh read-only review evidence:**

- `git apply --check --verbose` imepita; hunk ina harmless line offset -3. Hakuna apply iliyofanywa.
- In-memory HTTP-kernel simulation imetumia existing Laravel TrustHosts na proposed callable, huku APP_ENV/test data configuration ikianza testing, SQLite `:memory:`, mail/cache/session array. Baadaye application environment ya fixture pekee ilisimulate staging/local/testing; hakuna DB connection, migration, model persistence au real mail iliyohitajika.
- Host matrix **10/10 passed**: staging canonical/case-normalized canonical 200; unknown, subdomain na suffix-lookalike 400; alternate configured host 200 na old host 400; local canonical 200; local/testing unknown-host bypass 200.
- Separate staging simulation: unknown-host `/up`, `/`, `/xlipasignin.html` zote **400** (3/3).
- Initial probe ilishindwa kabla ya request dispatch kwa missing initial Request binding wakati wa HTTP-kernel bootstrap. Binding ilisahihishwa kwenye inline probe pekee; hakuna application fix iliyofanywa.

Simulation imeprepend middleware kwenye kernel ya process tu. Hivyo si proof ya final bootstrap integration/full regression; permanent test lazima ihakiki actual registered global stack baada ya approved patch.

**Permanent coverage inayopendekezwa:**

1. Actual bootstrap registers TrustHosts kwenye global stack; normal local Herd URL/pages zinaendelea.
2. Isolated production-like enforcement: canonical accepted, unknown/subdomain/suffix/malformed host rejected; case normalization na explicit port behavior documented.
3. Synthetic alternate APP_URL: accepted host hubadilika, previous host rejected; named auth routes/mail/assets zinatumia configured origin.
4. Framework local/testing bypass imethibitishwa wazi; normal testing-env request pekee isihesabiwe kama enforcement test.
5. Signed-link valid/tampered/expired checks, canonical auth redirects, host-only/HttpOnly/SameSite na HTTPS Secure-cookie fixture.
6. Restore environment na `Request::setTrustedHosts([])`/`TrustHosts::flushState()` baada ya scenarios, ili static state isileak kwenda auth tests.
7. Fresh existing full auth/foundation suite na frontend DOM regressions; verify working DB/config hashes kabla/baada. Guarded memory DB na fakes pekee kwa auth fixtures; production-like host tests hazifanyi auth writes.

Full regression suite haijarun tena katika review hii; previous **64 tests/543 assertions na frontend checks 31** ni recorded pre-B3.1 results, si fresh patch-integration results.

## 6. Remaining risks na preservation

- Production ikiwa imesanidiwa kimakosa APP_ENV=local/testing, framework enforcement itabypassiwa. Deployment validation lazima ihakiki environment na APP_URL; hii ni material limitation ya original minimal proposal.
- APP_URL missing inaweza kutumia existing localhost fallback; invalid/empty hostname pattern haitaruhusu valid hostname. Validate a complete intended absolute URL kabla ya deployment; usidai fallback ni production-ready configuration.
- Trusted proxies/forwarded-host handling na webserver host restrictions vinahitaji infrastructure review kabla ya deployment. Patch haiongezi proxy trust wala security bypass.
- Single-host policy haitoshi kwa planned multi-host/CDN/health-probe aliases bila operational configuration review. Preserve explicit allowlists; hakuna wildcard shortcut.
- Visual/browser QA bado NOT VERIFIED kutoka previous task; host review haijabadilisha status hiyo.

Working SQLite SHA-256 kabla/baada: `214ae83a31f53e80d3068d1aeb1366008bb5accb2df212dd2c534ae4121b6d8f` — unchanged. `.env` full-file hash pia imefanana kabla/baada bila kuchapisha content yake; APP_KEY haijabadilishwa. `git diff -- backend/frontend` haina application/frontend changes. Existing README modification na earlier untracked reports/proposed patch zimehifadhiwa.

**Ninasubiri final implementation approval. Hakuna patch iliyowekwa, commit/push/deployment au B3.1 implementation.**
