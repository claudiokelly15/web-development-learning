# XLIPA — BNC-INT-05 Isolated HTTP Input and Private Context Preparation

13 November 2026 · Africa/Nairobi · Isolated preparation only; owner review required

## 1. Baseline and exact files created

HEAD remains `70b2d1a4078923df99c9509522f52d4b4481e73c` (`MODIFIED`). Initial tracked/index diffs were empty. These three existing untracked files were preserved:

```text
XLipa/XLIPA-BNC-INT-03-UI-HTTP-PREPARATION-2026-11-13.md
XLipa/XLIPA-BNC-INT-04-TRUSTED-ADMISSION-HTTP-READINESS-REVIEW-2026-11-13.md
XLipa/backend/tests/Feature/MerchantOnboardingPreparationTest.php
```

Read backend AGENTS.md, INT-01 through INT-04 reports, existing internal input/admission contract, fixture adapter, isolated bootstrap and tests. No additional AGENTS.md was found; workspace/XLipa/backend `.ai/rules` directories are absent. The AGENTS-referenced testing-best-practices skill is unavailable in this session. Installed metadata confirms Laravel 13.35.0, Pint 1.32.1 and PHPUnit 12.5.38.

Exactly five persistent files are created, relative to XLipa:

| File | Purpose |
|---|---|
| backend/app/Actions/Merchants/BusinessNameCompletionHttpInput.php | Pure bounded raw request and original-context type validation |
| backend/app/Actions/Merchants/BusinessNameCompletionRequestContext.php | Unbound interface for future private server custody |
| backend/tests/Support/FakeBusinessNameCompletionRequestContext.php | Fictional single-process state model, used only by tests |
| backend/tests/Feature/BusinessNameCompletionHttpPreparationTest.php | Focused input/state/privacy acceptance coverage |
| XLIPA-BNC-INT-05-HTTP-CONTEXT-PREPARATION-2026-11-13.md | This report |

No existing application/test/UI/configuration/route/provider/manifest/SQL file was edited. No existing work was overwritten. A fresh preservation inventory captured **316 existing Git-selected tracked/untracked nonignored source, asset, test, config and report files** before scaffolding, excluding environment/secrets/credentials/private-key, dependencies/storage and database/sidecar paths. It is current-session preservation evidence, not a reconstruction of earlier history.

## 2. Input-validation contract

`BusinessNameCompletionHttpInput::parse(contentType, body, query = '', resolving = false)` is side-effect-free and uses neither request globals, sessions, container, logging nor a database. Its result is private request data, not a public response. Every invalid input raises the same InvalidArgumentException message without a chained exception or rejected field values.

- Accept only application/x-www-form-urlencoded, case-insensitive media type, optionally a single UTF-8 charset parameter (quoted or unquoted); bytes must decode as UTF-8. No alternate charset, multipart, JSON or extra parameters.
- Query must be exactly empty. Completion requires exactly `_token`, `context_ref`, `business_name`; resolution requires exactly `_token`, `context_ref`. Order is not significant. No browser epoch/UUID/actor/tenant assertions are accepted.
- Validate raw key occurrences before decoding or PHP parameter collapse. Keys must be literal canonical ASCII names: percent-encoded aliases are rejected even when unique. Reject duplicates, unknowns, bracket/nested/dotted/space aliases, `_method`, empty pairs/keys, trailing separators and ambiguous raw equals. Values require exactly one raw key/value separator; a literal equals in a value must be percent-encoded. Plus decodes to U+0020; percent decoding occurs exactly once. Invalid percent sequences and invalid decoded UTF-8 fail.
- Inclusive byte caps: raw body 4,096; raw encoded name value 3,072; decoded name value 1,024. Tests exercise exact and over-boundary bodies, including a valid body of exactly 4,096 bytes and a valid encoded name of exactly 3,072 bytes. The body cap is checked before parsing; no Content-Length assertion is trusted or required by this pure function.
- Opaque references are restricted to 1–128 ASCII base64url/hex-compatible characters `[A-Za-z0-9_-]`; punctuation outside that set is rejected. `_token` is 1–256 visible ASCII characters. These are format bounds only: reference existence/authority and CSRF validity are not established by parsing.
- Decode name bytes without global trimming/null conversion. Apply the frozen internal rule: valid UTF-8 string; reject Cc/Cf/Zl/Zp before trimming; trim only ASCII U+0020; reject empty result or a remaining boundary Unicode separator; maximum 200 Unicode code points. Preserve case, canonical-equivalent Unicode variants, punctuation and permitted interior spacing exactly. No NFC/NFKC or case normalization. HTTP caps are additional transport bounds, not a change to the internal name rule or signup.

`name()` provides that same canonical name validation without HTTP transport caps. `expectations()` validates server-custodied original context, using the unchanged internal expectation-key constant and exact ULID/UUID rules. Its seven version/attempt/membership fields must already be positive PHP integers; PHP_INT_MAX is accepted as an input value without arithmetic. Decimal strings, overflow strings, floats, booleans, arrays, zero and negative values fail rather than coerce. UUIDs are lowercase hyphenated hexadecimal; no new UUID variant/version constraint is invented. Missing/extra expectation keys fail. No browser integer parser is needed because those fields are not browser inputs in this stage.

No controller, route or middleware invokes this validator. A future boundary must feed the capped original body before parameter collapse and global normalization, enforce actual POST/origin/CSRF/cookie/headers/quotas and safe errors, and use the returned allowlisted fields rather than reparsing raw input. Header-level method overrides, stream/ingress limits and real browser/CSRF behavior are outside this pure validator. Existing TrimStrings/ConvertEmptyStringsToNull, dontFlash and signup behavior remain unchanged.

## 3. Private context contract

`BusinessNameCompletionRequestContext` is an interface, deliberately unbound in the application container. No operational implementation is supplied. Preparation takes exact server-derived actor ID, merchant ID, originating session and restore/cutover generation; the original nine typed expectations; original validated canonical name bytes; preparation time, preparation expiry and retention deadline.

The original expectations include application/attempt, membership/authority, merchant/draft profile versions, auth/email-binding epochs and canonical completion UUID. Preparation/dispatch timestamps and explicit state remain in private custody. The contract requires independent time/policy/retention, encrypted durable storage, atomic transitions and actual restore/store fencing before operational use. Caller-provided timestamps or a matching generation string are not trust.

State model: **prepared → dispatching → completed / unchanged / unresolved**. `dispatch()` must persist the once-only transition before returning original private command input and a one-use internal token. A crash/lost acknowledgement after dispatch may have occurred before or after command invocation; neither permits dispatch again or UUID replacement. The private token is not a browser reference or completion admission grant.

`finish()` accepts only acknowledged trusted internal completed/unchanged results with the matching dispatch token. `unresolved()` records uncertainty without a success/rollback claim. `beginResolution()` requires a deliberate, independently authorized request and returns the original private context with a new one-use resolution token. `finishResolution()` accepts a conservative trusted classification. Resolution never invokes the command. Status responses contain only state and optional resolution classification, never name, UUID, expectations, session, tokens or event details.

Expiration denies new dispatch. Retention expiry denies releasing private command/resolution input, but cannot erase or discharge an unresolved hold. An operational adapter still needs an approved reconciliation and retention-conflict procedure. This stage does not select production windows, authorize deletion, implement session rebinding after logout or authorize resolution against a store.

## 4. Fictional adapter behavior and limits

The fake constructor requires the established synthetic/testing environment. It is never provider-bound. It holds private arrays in memory, performs synchronous state checks before updating and returning a token, and has no file/database/network operations. This demonstrates **single-process state transitions only**; it is not evidence of encryption, multi-process atomicity, production durability or authoritative restore fencing.

- Exact actor/tenant/session/generation binding is required for dispatch, result recording, status and resolution. Mismatches fail generically and reveal no context. Time regression or invalid time bounds deny.
- Preparation requires already canonical name bytes, valid typed expectations and preparation < expiry ≤ retention. It stores original bytes/UUID and does not normalize them again into a different value.
- Dispatch returns private input only once. A second click, stale token or wrong-state finish fails. Competing prepared contexts for the same merchant cannot bypass a dispatching/unresolved winner. Preparing a replacement while that merchant is uncertain is denied even with a new UUID. Recorded UUID reuse for the same actor/tenant is denied.
- Unacknowledged dispatch is displayed as unresolved; original private custody remains. Preparation expiry does not prevent explicit resolution before the retention boundary. At/after retention boundary, private resolution access is denied; state-only inspection still shows the unresolved hold. The fake never deletes custody on expiry.
- Explicit resolution uses an independently injected fictional authorization callback, checked at both begin and finish. Callback denial or exception fails closed with a generic error and no chained private failure. A later explicit resolution invalidates an older resolution token; it retains original UUID/name. No background/automatic resolution or command retry is implemented.
- Completed/confirmed results require the expected V+1 integer and valid completion ID; unknown/extra result keys fail. Safe results omit these internals. Confirmed becomes completed; absent/historical/held/unknown remain unresolved, so **even an absent classification never reopens dispatch**. This deliberately conservative fake provides no workflow for authorizing a subsequent new command; a later stage must separately design that transition.
- Debug output is redacted, JSON encoding exposes no private properties, serialization is prohibited and sensitive boundary arguments use SensitiveParameter. Private arrays explicitly returned by dispatch/resolution remain sensitive to the trusted caller; these protections are not a substitute for that caller's custody/redaction obligations.

No fake method calls BusinessNameCompletionService or BusinessNameCompletionResolver, modifies a merchant, supplies live admission or proves current owner authority. Real ownership/session/admission checks remain independently mandatory in the existing backend and future HTTP boundary.

## 5. Execution, focused tests and formatting

Runtime: established `C:/Users/hp/.config/herd/bin/php85/php.exe`, using approved execution escalation. Three files were scaffolded via Laravel generators through a temporary wrapper which required tests/bootstrap-isolated.php, selected tests/__no_real_environment **before kernel bootstrap**, then called make:class, make:interface and make:test with --no-interaction (and --phpunit for the test). Exit 0. The wrapper ran with `PHP $env:TEMP/xlipa-int05-scaffold.php` and was removed afterward. The fake was added in the existing tests/Support directory. No ordinary Artisan bootstrap or real dotenv was used. Read-only existence checks confirm that the selected absent environment filename, its testing variant and the two synthetic cache paths do not exist.

PHPUnit uses the established tests/bootstrap-isolated.php and Tests/TestCase: synthetic keys, memory SQLite configuration, absent environment/cache paths, array mail/cache/session and blocked stray HTTP. New focused tests never open a PDO connection or run a migration; they assert that Laravel's lazy connection object remains unopened and unchanged. Existing combined suites run only their established guarded fictional fixtures and owned generated temporary recovery databases.

Commands below run from backend; `PHP` denotes the exact executable above with `-d auto_prepend_file=`. `$files` is exactly:

```powershell
$files=@(
 'app/Actions/Merchants/BusinessNameCompletionHttpInput.php',
 'app/Actions/Merchants/BusinessNameCompletionRequestContext.php',
 'tests/Support/FakeBusinessNameCompletionRequestContext.php',
 'tests/Feature/BusinessNameCompletionHttpPreparationTest.php'
)
foreach($p in $files){ PHP -l $p; if($LASTEXITCODE -ne 0){exit $LASTEXITCODE} }
PHP vendor/bin/pint --dirty --format agent @files
PHP vendor/bin/pint --format agent @files
PHP vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/BusinessNameCompletionHttpPreparationTest.php
```

Final syntax checks: **4/4 passed, each exit 0**. Both final bounded Pint invocations passed, exit 0. The initial formatter changed only the new feature test's import/brace/whitespace style; executable source was reviewed and final tests ran after formatting.

Initial focused result: 82 tests, 81 passed, 234 assertions, one failure, 6,019ms, exit 1. The new privacy test incorrectly expected null for Laravel's lazy PDO slot; the actual unopened value was a Closure. Corrected that assertion to reject an opened PDO and verify the original lazy value remains unchanged. Added four uncertainty/privacy edge cases, and made fictional resolution callback outages fail closed. No existing contract/test or database behavior was changed.

**Final focused result: 86 passed / 273 assertions / 6,202ms, exit 0.**

```json
{"tool":"phpunit","result":"passed","tests":86,"passed":86,"assertions":273,"duration_ms":6202}
```

Coverage includes minimal completion/resolution; duplicate/encoded aliases/brackets/arrays/overrides/unknown fields; malformed percent and UTF-8; forbidden control/format/boundary separators; preserved interior Unicode and combining bytes; transport/reference limits; typed UUID/integer bounds; wrong actor/tenant/session/generation; repeated/competing dispatch; lost dispatch/resolution acknowledgement; expiry/retention/time regression; authority withdrawal/outage; conservative classifications; invalid or extra outcomes; unbound interface; redacted errors/debug/JSON and serialization denial. Differential name cases check the unchanged internal validator too.

## 6. Combined bounded regression

Exact command from backend, using the same PHP executable/options:

```powershell
PHP vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'BusinessNameCompletionHttpPreparationTest|MerchantOnboardingPreparationTest|BusinessNameCompletionTest|WorkflowSchemaReadinessTest|BusinessNameCompletionSchemaTest|AdminApplicationReviewTest|ApplicationSubmissionServiceTest|SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest|SubmissionLifecycleTest|SubmissionEvidenceCipherTest'
```

**Final combined result: 702 passed / 5,284 assertions / 128,431ms, exit 0**, zero failures/errors reported. This is the authoritative combined run on the final four-source set. Historical INT-03's 616/5,011 result is not substituted for this run; overlapping focused results are not added to it.

```json
{"tool":"phpunit","result":"passed","tests":702,"passed":702,"assertions":5284,"duration_ms":128431}
```

## 7. Preservation and security evidence

Before implementation and after source preparation, all **29** frozen C/SE/BNC/INT-01/INT-02 source/artifact/foundation migration pins matched, exit 0. Manifest digests remain:

| Manifest | SHA-256 |
|---|---|
| BNC manifest | `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3` |
| INT-01 integration manifest | `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a` |
| INT-02 backend manifest | `423d7ec0c2242aa42eb6e33c02036096c12fc3ff5a3160bf828c612904395990` |

These new files are deliberately outside existing frozen manifests; no manifest was regenerated or admission check weakened. They require independent successor-release review before any future runtime integration. Source search confirms no new use in routes/config/providers. The context interface has no binding; denied completion/submission bindings remain unchanged. Existing disabled onboarding controls, signup/authentication, Chief read-only routes, BNC schema/triggers/ledger and payment holds are preserved.

Final inventory comparison: **316 pre-existing files unchanged, zero changed, zero missing**, with exactly the five new paths listed in section 1; exit 0. The three existing untracked INT-03/04 paths remain byte-preserved. Final hash verification again matched all 29 existing pins and all three manifest digests, exit 0. Git index is empty; `git diff --check` and explicit trailing-whitespace checks on the new files passed, exit 0. No unexpected modification was found.

SHA-256 for the exact tested new PHP sources (relative to backend):

| File | SHA-256 |
|---|---|
| app/Actions/Merchants/BusinessNameCompletionHttpInput.php | `75acd1fde08dee93491c642b32a1f333b843cc004e18c3a9f8a3ea44a9819fc8` |
| app/Actions/Merchants/BusinessNameCompletionRequestContext.php | `4ec06db95abb7417953a857bdcb620bd54017b6105d82b7b7495bbfa7e5677a3` |
| tests/Support/FakeBusinessNameCompletionRequestContext.php | `b5e6553ff8d73a4d706791aa3909f2c0e64f0abc30b1d529eaf155a9893e2f57` |
| tests/Feature/BusinessNameCompletionHttpPreparationTest.php | `3666e5c816fb83714292b58f5996a2a7424a26592ba04f9f1d20ef568c720f89` |

Only this report was updated after the final combined run. No working database/sidecar was read, queried, copied, hashed, migrated or modified. No real dotenv, operational credential or key was read. No commit, reset, revert, push or deployment occurred.

## 8. Unresolved dependencies and next recommendation

Operational profile provisioning, descriptor/release authenticity, trusted owner/scope grants, durable encrypted atomic private custody, trusted time, approved retention/hold policy, authoritative actual-connection/store lineage, restore/cutover/old-writer fencing, external reconciliation and real recovery evidence remain missing or unverified. Runtime authentication/mail holds remain; no local/testing bypass or production configuration change was introduced. Future HTTP integration still needs actual raw ingress handling, scoped trim/null exclusions, CSRF/origin/cookie/header/limiter/error/old-input protections and browser/accessibility tests.

Recommend owner review of this bounded preparation, then separately approve the next trust/target-evidence contract stage described in INT-04. Any target preflight needs explicit target-bound read-only authorization; migration, backup/copy/hash/restore, operational adapters, release provisioning or HTTP/UI writes are not authorized by this result. Do not proceed automatically to INT-06 or a later stage.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**
