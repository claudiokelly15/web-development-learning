# XLIPA BNC-INT-07A functional merchant onboarding

Date 2026-11-14. Continuation of the interrupted isolated integration. Owner review is required before further work.

## 1. Exact changes recovered

Recovery started at Git HEAD `576ad6f20552b9af835c2c1b69540e2d58efda02` with a clean working tree and empty index. The two interrupted files were already present in that commit. Its path-scoped statistics show 339 added lines in `backend/tests/Feature/FunctionalMerchantOnboardingTest.php` and 172 in `backend/tests/Support/MerchantOnboardingHttpHarness.php`, totaling 511 additions. The reported approximately 497 additions and eight removals could not be reconstructed as an outstanding diff. Both recovered files were inspected in full and preserved.

Read `backend/AGENTS.md`, the test bootstrap and base class, existing completion service/resolver/context/admission contracts, fixture custody/admission implementations, manifests, relevant existing feature tests and INT-02, INT-05 and INT-06B Part 1 evidence. The repository search found no separate INT-07A requirements document. The owner's continuation request supplies the acceptance requirements. No additional AGENTS.md or applicable `.ai/rules` directory was found. The AGENTS-referenced `testing-best-practices` skill was unavailable in the accessible skill catalog/search.

The recovered harness explicitly registers test-only routes with Laravel middleware and calls the existing completion service and resolver. Its guard requires CLI, the synthetic-only flag, testing environment, SQLite memory configuration without a connection URL, and the exact fixture-owned PDO. Ordinary application bindings retain denied admission.

## 2. Work completed

Only `backend/tests/Feature/FunctionalMerchantOnboardingTest.php` was changed during continuation, with 49 additions and two removals. The recovered harness retains its original bytes.

- Save the synthetic session and pass Laravel-prepared encrypted cookies to raw HTTP calls, preserving original body bytes and session-bound custody.
- Expect HTTP 419 for the malformed JSON case because real CSRF middleware rejects it before the fixture parser.
- Use a valid membership revocation and authority-version transition between HTTP authorization and service transaction execution. The original authority-version-only fixture mutation violated the frozen security contract.
- Add cross-tenant dashboard denial and completion isolation, with exact persisted foreign merchant/draft attribute comparisons.
- Exercise the actual completion service with denied operational admission, requiring HTTP 503, unresolved status and zero completion/witness writes.
- Prove the harness rejects a different memory PDO.

The foreign merchant comparison uses a freshly loaded persisted baseline so database-default null fields and attribute ordering are represented consistently.

## 3. Functional journey demonstrated

The passing Laravel HTTP feature suite exercises the existing GET registration page and POST registration without `businessName`. It checks an empty merchant name, unverified user and login redirect. It obtains the signed verification URL from the faked existing notification, performs actual login and verification HTTP requests, and checks the updated email-binding version.

The dashboard redirects the unverified user, then renders successfully after verification with “Business name not provided”. Server-side fixture preparation retains the expected actor, tenant, session, membership, versions, original name and request identity. The raw HTTP completion request invokes the real `BusinessNameCompletionService` against the fixture-owned memory database.

Success requires a single completion record and witness, the requested Unicode/spacing-preserved name after permitted outer ASCII-space trimming, matching merchant/draft profile version 2, matching occurrence/update timestamps, draft status, and absent submission snapshot/time. Existing dashboard and onboarding HTTP responses render the saved name and remove their incomplete-name indicators. Public submission continues to return HTTP 503.

The suite also covers guest, unverified, manager, staff, foreign, Chief Admin, revoked, stale-session and suspended denial cases. Context checks bind tenant, actor and session. Duplicate and competing prepared requests produce one winner, with stale rename attempts rejected. The authority revocation test proves the service checks authorization after the HTTP policy check.

## 4. Actual test results

Runtime was the existing Herd PHP 8.5.12 CLI, executed with approved sandbox escalation and `-d 'auto_prepend_file='`. Installed metadata identifies Laravel 13.35.0, PHPUnit 12.5.38 and Pint 1.32.1. No dependencies were installed.

Both task files passed PHP syntax checks, exit 0. Pint's required `--dirty --format agent` invocation passed, restricted to the two task files. An explicit-path `--format agent` invocation also passed. The final dirty formatter and syntax checks preceded the final focused run.

Final focused command, run from `backend` with the existing PHP executable and auto-prepend disabled, follows.

```powershell
vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/FunctionalMerchantOnboardingTest.php
```

**33 tests passed, 964 assertions, 20,422 ms, exit 0.**

The bounded regression used the same executable, bootstrap and runner options, with the following selection.

```powershell
--filter 'BusinessNameCompletionTest|BusinessNameCompletionSchemaTest|BusinessNameCompletionHttpPreparationTest|BusinessNameCompletionUiTest|TrustedCompletionContractsTest|WorkflowSchemaReadinessTest|MerchantOnboardingPreparationTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|MerchantApplicationSubmissionTest|AdminApplicationReviewTest|TrustedHostsTest'
```

**546 tests passed, 4,824 assertions, 90,916 ms, exit 0.** This selection is separate from the 33-case focused suite. All executable edits were complete before both final runs.

The initial recovered run had 30 tests, 23 passes, seven failures and 829 assertions. Intermediate corrections exposed session-save behavior, a security-trigger-invalid fixture mutation, and the persisted/default attribute comparison issue. These were corrected within the functional test file. Intermediate counts are excluded from final passing totals.

## 5. Security checks

`tests/bootstrap-isolated.php` supplies public fictional keys and absent environment/config/route-cache paths before application creation. The base test case enforces memory SQLite without a URL and array mail. Synthetic cache/session storage is in memory, queue/logging are disabled, notifications are faked and stray HTTP is blocked. Foundation, C, SE and BNC installation occurs only through established fixture protections.

The harness enables real CSRF middleware for its HTTP tests. Missing/wrong tokens and the malformed JSON case are rejected with 419. Foreign Origin is rejected with 403. Raw duplicate, encoded-alias, array, unknown, malformed UTF-8/percent, forbidden-character, oversized, query and method-override cases are rejected before completion dispatch. Privacy assertions cover no-store/private cache headers, referrer policy, hidden context/name and absent flashed business-name input.

Lost acknowledgements are tested both before commit and after commit. HTTP returns 202 unresolved, without retry or private exception disclosure. Fixture-owned PDO reads establish the actual persisted outcome independently of the HTTP claim. Duplicate dispatch remains blocked and resolution requires explicit fixture approval. The real resolver purges the connection and opens a fresh memory database. Both cases return 503 unresolved when authoritative recovery cannot be established. The real resolver and its contracts retain their original bytes. A disposable persistent fixture database was unnecessary for the requested conservative recovery behavior.

An independently bootstrapped ordinary application retains `DeniedBusinessNameCompletionAdmission` and has no fixture routes. The denied-admission test also reaches the real service through the isolated harness and proves denial prevents writes. The harness remains confined to the test namespace and explicit suite registration.

## 6. Preservation evidence

A recovery SHA-256 inventory covered 313 tracked source, report, configuration, asset, frozen SQL and dependency-metadata files, excluding dependency directories, storage and working database/sidecar contents. Final comparison found 312 unchanged, one changed and zero missing. The sole changed baseline path is the functional test. The requested report is the sole new deliverable.

Read-only hash comparisons matched all 29 manifest/foundation source/artifact pins and all three historical BNC manifest digests, totaling 32 comparisons with zero mismatches. All four INT-05 protected source/test hashes also matched.

| Artifact | SHA-256 |
|---|---|
| BNC manifest | `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3` |
| INT-01 integration manifest | `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a` |
| INT-02 backend manifest | `423d7ec0c2242aa42eb6e33c02036096c12fc3ff5a3160bf828c612904395990` |
| Final functional test | `449d32cafa65ff5c87dafec3feef2ccd35755246c95371027ab66ed334df4534` |
| Preserved HTTP harness | `125d261e25bca5f12c1c94592eb90115821040ab630ff59f92a59ec01293f7e3` |

Git diff whitespace checking passed. The index is empty. Source inspection confirms denied operational completion/submission bindings, local/testing and log/array authentication restrictions, public submission denial and the existing operational holds.

The working database, sidecars, real dotenv, operational credentials and keys were outside the commands used. No working-store query, migration, copy, hash or mutation occurred. Frozen SQL/migrations, contracts, operational configuration, provider bindings, authentication controls, Chief decision-write controls and payment activation sources remain byte-preserved. No commit, push or deployment occurred.

## 7. Remaining blockers

The isolated journey is demonstrated through Laravel HTTP feature tests with server-side fictional custody preparation. Browser interaction, durable production custody, real email delivery, operational release authenticity/admission, trusted time and authoritative store recovery remain outside this isolated evidence. Operational profile provisioning, deployment/revocation authority, retention policy, restore/cutover fencing and reconciliation dependencies remain subject to existing owner-reviewed holds.

Fresh-connection recovery from SQLite memory fixtures intentionally remains unresolved. This report establishes the conservative failure behavior. Existing persistent-resolver coverage is included in the bounded regression selection through `BusinessNameCompletionTest`. That existing suite generates a uniquely named temporary file from its owned memory fixture, verifies its temporary directory, switches only the synthetic connection, and cleans its generated file/sidecars in `finally`. Those tests establish confirmed/absent resolution against disposable fixture persistence. The new functional suite stays memory-only.

## 8. Final assessment

**PASS for the requested isolated BNC-INT-07A functional journey.** The real completion service and updated dashboard are demonstrated by the passing focused suite. The bounded regression, syntax checks, formatting and preservation checks passed. No isolated acceptance blocker remains. Operational activation remains subject to every existing NO-GO restriction and the dependencies in section 7.

**STOP FOR OWNER REVIEW.** B3.2-C and SE-1.0 operational activation remain NO-GO. Public submission, Chief Admin decision writes and live payments remain disabled. No further architecture milestone was started.
