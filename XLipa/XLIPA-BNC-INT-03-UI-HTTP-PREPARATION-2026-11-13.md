# XLIPA — BNC-INT-03 UI and HTTP Security Preparation

13 November 2026 · Africa/Nairobi · Read-only preparation for owner review

## Initial state and recovered implementation

Initial `git status --porcelain=v1` was empty at HEAD `70b2d1a` (`MODIFIED`). Unlike the historical INT-02 report's dirty working tree, this continuation began with the prior implementation tracked and clean. Read backend AGENTS.md and the INT-01 strict-readiness and INT-02 completion reports. The file search found no other AGENTS.md; backend `.ai/rules` is absent. The referenced testing-best-practices skill is unavailable in this session.

Already present: MerchantOnboardingController's completionPreview gate, the complete merchants/onboarding Blade layout, and identical frontend/public xlipa-onboarding.css copies. No INT-03 report or dedicated onboarding preparation suite was found. All eight requested selectors already existed. Preserved those files without refactoring or duplicate declarations.

Captured 313 tracked source/asset/test/config/report files in temporary `xlipa-bnc-int03-baseline.json`, excluding environment, dependency, storage, and database/sidecar paths. Verified existing C/SE/BNC source pins, INT-01 and INT-02 pins and all nine foundation migration pins before testing.

## Exact files added by this continuation

- `backend/tests/Feature/MerchantOnboardingPreparationTest.php`: dedicated fictional UI/authorization/read-only regression coverage.
- `XLIPA-BNC-INT-03-UI-HTTP-PREPARATION-2026-11-13.md`: this report and future HTTP contract.

No existing application, Blade, CSS, route, provider, SQL, migration or manifest file required changes.

## UI, visibility and authorization

The existing workspace has overview/account navigation, heading, read-only badge, conditional completion card, availability status, onboarding status and policy-controlled related links. Input and button have native disabled attributes inside a disabled fieldset; the button is type=button and there is no completion form, action, input name or hidden expectations. Availability text explicitly says saving is unavailable and adding a name does not submit an application.

The route preserves authentication/readiness/session-epoch middleware, verified email and fresh tenant membership resolution. MerchantPolicy freshly checks active verified account and active unrevoked owner membership for manageVerification. Platform assignment provides no bypass. Preview additionally requires exactly empty stored name, non-suspended/non-closed merchant, current draft, matching merchant/draft version and null submission/review projections. Missing/inconsistent drafts are hidden without repair. Manager/staff retain existing summary redaction and cannot see the preparation. No phone OTP, NIN or KYC prerequisite was added.

All rendered dynamic text uses escaped Blade expressions. Private contact, snapshot and internal notes are absent from the view. This GET visibility check is informational; it is not transaction admission and cannot authorize a future write.

## Future HTTP integration contract — requirements only

No HTTP command, parser, request-context store, resolution endpoint, throttle or operational adapter is activated by this document.

1. **CSRF and transport:** any future write must be an explicitly authorized same-origin web POST with Laravel CSRF validation, secure session cookies and existing private/no-store, no-referrer, nosniff and frame-denial headers. Never accept completion via GET. Review browser-origin/session behavior and real CSRF failure tests separately; current test requests do not certify production CSRF enforcement.
2. **Throttling:** separately approve bounded per-authenticated-actor/per-merchant and network-abuse limits, including explicit resolution limits. Apply before expensive work, use trusted proxy/IP configuration, avoid raw names in limiter keys and fail closed on limiter outage. Limits and expiry policy need owner review; none are invented here.
3. **Identity and fresh authority:** derive actor from authenticated verified active account and tenant from authorized route scope. Validate session auth epoch and current email binding. Reload active unrevoked owner membership, tenant, authority epoch, merchant state and current draft at command acquisition and inside the reserved writer through INT-02. Platform roles, cached membership, request role flags, schema readiness or UI visibility cannot substitute for authority.
4. **Strict allowlist:** future HTTP schema must reject unknown keys, nested arrays, booleans, floats, duplicate/ambiguous parameters and excessive bodies. Client may supply only separately approved name and version/context reference fields. Actor, role, trusted time, policy, expected profile, store identity and admission assertions are server custody. Map exactly the internal expectation tuple: application_id, attempt_number, merchant_profile_version, draft_profile_version, membership_id, authority_version, auth_version, email_binding_version, completion_request_id. No implementation or implicit string-to-int casting is approved. Review canonical bounded positive decimal parsing to PHP integers and overflow rejection separately.
5. **Unicode and whitespace:** preserve INT-02's UTF-8 validation and 200 Unicode-character bound; reject control/format and line/paragraph separators before trimming; trim only U+0020 and reject remaining boundary Unicode separators. Preserve valid interior bytes/case without normalization. Laravel's default TrimStrings/ConvertEmptyStringsToNull can change those bytes before command validation: a future endpoint must have reviewed scoped exemptions and raw-input tests. Never silently strip invisible characters, normalize names or broaden signup behavior. HTML maxlength alone is not authoritative server validation.
6. **Concurrency:** capture both profile versions, exact application/attempt/membership and all authority/auth/email epochs. Preserve the original canonical request UUID. No latest-version substitution, automatic conflict retry, command replay or UUID regeneration after an ambiguous result. The command owns its one-attempt writer/CAS/trigger/commit ordering. Do not insert readiness checks in the intentional deferred record/witness gap.
7. **Private original context:** before dispatch, retain the original expected tuple, UUID, tenant, actor and desired validated bytes in a separately reviewed bounded server-controlled store. Define encryption/access, retention/expiry, concurrency, logout/revocation and restore behavior. Expose at most an opaque bound reference. Do not put names or context in URLs, hidden inputs, signed-but-readable cookies, logs, analytics, exception chains or validation old-input flashes. Current global dontFlash does not establish name/context protection: a future boundary needs explicit exclusions and tests. No context store is supplied here.
8. **Safe responses:** generic validation/denial/conflict/unavailable responses must reveal no SQL, merchant existence across tenants, stored name, request tuple or receipt internals. Return only approved minimal outcomes. Never log body/name/context or chain private exceptions. Keep unauthorized views redacted and private responses uncached. Review unexpected exception paths with debug disabled and redacted logging.
9. **Unknown outcome:** BusinessNameCompletionOutcomeUnknown means neither success nor rollback is established. Preserve original private context and present an unresolved/held state; never report success, invoke complete again, or assume absence from a local query. Lost no-op acknowledgement has no durable completion proof.
10. **Explicit resolution:** only a separately authorized deliberate action may invoke BusinessNameCompletionResolver with original context. Preserve its fresh-connection, independent resolution admission and current-owner checks. confirmed permits its minimal existing result; absent is authoritative only under all store/restore fences and is not permission to retry automatically; historical proves only an event, held proves neither current success nor absence. Denials/expiry/unknown remain unresolved. Do not fabricate historical payload proof from the redacted record.
11. **Trusted admission and restore fencing:** retain DeniedBusinessNameCompletionAdmission until a separately reviewed independent adapter establishes expected profile/release authenticity, operation-specific authority, trusted time, approved policy/retention, authoritative connection/store lineage, restore/cutover/replay fences and reconciliation holds. Local rows, schema validity, wall clock or request assertions cannot implement trust. Keep the separate command/resolver and manifests unchanged. A temporary permissive adapter is prohibited.

## Responsive and accessibility verification

Existing CSS uses workspace --dash-* variables, min-width:0/minmax tracks, wrapping navigation and long headings/links. Desktop uses two card columns; at 850px it becomes one; at 580px heading and status definitions stack with smaller card padding. Native disabled controls cannot receive submission or editing actions. Labels, legend, description IDs, status region, semantic sections, skip link, focusable main and visible anchor focus outlines are present in rendered markup. Frontend/public CSS identity and referenced variable definitions are tested.

Verification is rendered-HTML/DOM and CSS-structure based; no browser viewport screenshots, assistive-technology session or automated contrast audit were performed. Do not interpret structural checks as full visual certification.

## Execution and evidence

All PHPUnit runs use `tests/bootstrap-isolated.php` and Tests/TestCase's absent environment filename, memory SQLite, fictional keys, array mail/cache/session and blocked stray HTTP. Existing regression fault tests may use their owned generated temporary SQLite fixtures. No working migration command or working database query/copy/hash was issued.

Execution caveat: the first generator attempt under ordinary sandbox access failed to locate the established PHP executable (exit 1). The approved escalation ran `php -d auto_prepend_file=tests/bootstrap-isolated.php artisan make:test MerchantOnboardingPreparationTest --phpunit --no-interaction` (exit 0). Although synthetic connection values were preloaded, this Artisan generator did not set Tests/TestCase's absent environment filename; framework bootstrap can therefore load local dotenv. No dotenv contents were printed or directly read, and no working-store access is evidenced, but this report cannot certify zero dotenv loading for that generator. Subsequent tests use the stronger established guard. No new bootstrap or dependency was introduced.

Commands below run from `XLipa/backend` using `C:/Users/hp/.config/herd/bin/php85/php.exe` with `-d auto_prepend_file=` (denoted `PHP`):

```powershell
PHP vendor/bin/pint --dirty --format agent tests/Feature/MerchantOnboardingPreparationTest.php
PHP vendor/bin/pint --format agent tests/Feature/MerchantOnboardingPreparationTest.php
PHP -l tests/Feature/MerchantOnboardingPreparationTest.php
PHP vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/MerchantOnboardingPreparationTest.php
```

Initial focused run: 16 tests, 12 passed, 113 assertions, two failures and two errors, duration 39,032ms, exit 1. New fixture corrections only: forceFill protected merchant/user state and provide the existing submission controller's valid input shape before asserting its 503 denial. No application contract was changed. The composed-BNC case was added after this initial runner loaded its suite.

Final focused result: **16 passed / 132 assertions / 8,542ms, exit 0**. Both bounded Pint invocations passed, exit 0; final PHP syntax check passed, exit 0. Formatting changed only the new test's whitespace. Focused output:

```json
{"tool":"phpunit","result":"passed","tests":16,"passed":16,"assertions":132,"duration_ms":8542}
```

Combined command (same PHP executable/options):

```powershell
PHP vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'MerchantOnboardingPreparationTest|BusinessNameCompletionTest|WorkflowSchemaReadinessTest|BusinessNameCompletionSchemaTest|AdminApplicationReviewTest|ApplicationSubmissionServiceTest|SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest|SubmissionLifecycleTest|SubmissionEvidenceCipherTest'
```

Final combined result: **616 passed / 5,011 assertions / 298,129ms, exit 0**, zero failures/errors reported. This is the authoritative combined run; focused and historical totals are not added to it.

```json
{"tool":"phpunit","result":"passed","tests":616,"passed":616,"assertions":5011,"duration_ms":298129}
```

## Preservation and dependencies

Original BNC manifest SHA-256: `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3`.
INT-01 manifest SHA-256: `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a`.
INT-02 manifest SHA-256: `423d7ec0c2242aa42eb6e33c02036096c12fc3ff5a3160bf828c612904395990`.

Post-implementation baseline comparison: **313 unchanged, zero changed, zero missing**. A fresh explicit hash check matched all **29** C/SE/BNC/INT-01/INT-02 source/artifact and foundation migration pins, exit 0. All three manifest digests above remain identical. Git status contains only the two new paths listed above; the index remains empty. `git diff --check` and explicit trailing-whitespace checks on both additions passed, exit 0. Registration, authentication, dashboard navigation, public submission denial and Chief read-only behavior are covered by the established bounded regression selection. No SQL object, migration or manifest is changed by this stage. No commit, reset, revert, push or deployment was performed.

Operational expected-profile provisioning, trusted admission/context custody, authoritative restore fencing, policy/retention/time authority, actual storage recovery evidence, target-bound migration/release approval and future HTTP implementation remain dependencies. Fixture success supplies no activation authority. Recommend owner review of this read-only stage and future HTTP requirements before separately authorizing a next milestone. BNC-INT-04 has not begun.

**STOP FOR OWNER REVIEW. B3.2-C activation: NO-GO. SE-1.0 activation: NO-GO. Public submission: DISABLED. Chief Admin decision writes: DISABLED. Live payments: DISABLED.**
