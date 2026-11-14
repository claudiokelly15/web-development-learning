# XLIPA P2 status verification

2026-11-14. Source-only recovery audit. **Final P2 classification — NOT STARTED.** Existing submission and review components predate P2. The complete requested P2 journey has no located implementation or execution evidence.

## A. Current repository state

Git root is `C:/Users/hp/OneDrive/Desktop/WEB-DEVELOPMENT`, branch `main`, HEAD `576ad6f20552b9af835c2c1b69540e2d58efda02`. The existing unstaged change is `backend/tests/Feature/FunctionalMerchantOnboardingTest.php`, with 49 additions and two removals. Existing untracked work comprises the INT-07A report, P0 report and four P0 script/document/template files. The index is empty.

Read `backend/AGENTS.md`. No additional AGENTS.md or applicable `.ai/rules` directory was found. Inspected the milestone reports, submission/review controllers, service/guard, routes, provider bindings, relevant feature tests and fictional support implementations. No application, migration, PHPUnit or verification-script execution occurred.

## B. Whether the P2 report exists

No P2 completion report or P2-specific functional submission test/harness was found among the tracked and nonignored project files. The searched Git history supplies no P2 report. Older appearances of “P2” in audit findings and contract discussions refer to other numbering contexts.

The latest local P0 report is [XLIPA-P0-REPOSITORY-CI-BASELINE-2026-11-14.md](XLIPA-P0-REPOSITORY-CI-BASELINE-2026-11-14.md). It explicitly records that P2 work did not occur and recommends separately scoped P2 work after review. This report was restored in the conversation after its deletion at the owner's request. Restoration added no implementation or test execution.

The onboarding report is [XLIPA-BNC-INT-07A-FUNCTIONAL-MERCHANT-ONBOARDING-2026-11-14.md](XLIPA-BNC-INT-07A-FUNCTIONAL-MERCHANT-ONBOARDING-2026-11-14.md). It demonstrates registration through saved business name and refreshed dashboard. It leaves application submission disabled.

## C. Actual implementation status

`FunctionalMerchantOnboardingTest::journey()` performs registration, login and signed email verification. Its completion test calls `BusinessNameCompletionService` through `MerchantOnboardingHttpHarness`. The test ends with a public submission request expected to return HTTP 503. The harness calls only business-name completion/resolution services.

The older `ApplicationSubmissionServiceTest` creates its user/merchant with factories and constructs identity/contact/challenge/proof records directly inside its isolated fixture. Its helper calls the actual internal `ApplicationSubmissionService::submit()`. It includes a genuine submitted-bundle-to-Chief-detail HTTP check. It never joins registration and email verification to actual business-name completion and application submission. Its BNC-profile compatibility test explicitly asserts zero business-name completion records.

The public `MerchantApplicationController::submit()` still calls `MerchantApplicationSubmission::submit(): never`, whose final branch unconditionally denies successful submission. `AdminApplicationController` exposes read-only list/detail operations. No successful submission HTTP harness joining the requested stages was found.

Repository evidence and the visible session show completed INT-07A and P0 work, followed by report removal/restoration. They show P2 as a future instruction/recommendation. No prior P2 execution was recovered. This classification concerns the P2 milestone, while the prerequisite service and review implementations remain present.

## D. P2 acceptance checklist

PASS below denotes existing component or earlier-milestone evidence with the stated scope. It supplies no passing P2 execution result. The complete P2 journey is **NOT TESTED**. No tests were rerun for this audit.

| Requirement | Status | Exact evidence and scope |
|---|---|---|
| 1. Fictional merchant registration | PASS | `FunctionalMerchantOnboardingTest::test_real_http_journey_saves_name_once_and_refreshes_existing_dashboard_and_onboarding()`, via `journey()`. Implementation `backend/app/Http/Controllers/Auth/RegisteredUserController.php` and `backend/app/Actions/Auth/RegisterMerchantOwner.php`. Earlier INT-07A segment. |
| 2. Email verification | PASS | Same exact INT-07A test, via signed verification HTTP request in `journey()`. Implementation `backend/app/Http/Controllers/Auth/EmailVerificationController.php`. |
| 3. Business Name Completion | PASS | Same exact INT-07A test. Implementation `backend/app/Actions/Merchants/BusinessNameCompletionService.php`, invoked by `backend/tests/Support/MerchantOnboardingHttpHarness.php`. |
| 4. Application draft preparation | NOT TESTED | Factory-created current drafts exist. No located P2 test prepares required application/business information on the merchant produced by the completed onboarding journey. |
| 5. Valid synthetic verification evidence | PASS | `ApplicationSubmissionServiceTest::test_fictional_bundle_commits_once_with_canonical_audit_and_private_receipt()`, via its direct-record `fixture()`. Implementation `backend/app/Actions/Merchants/ApplicationSubmissionService.php` and `backend/app/Actions/Merchants/SubmissionEvidenceCipher.php`. Earlier internal-service component coverage. |
| 6. Actual internal submission-service execution | PASS | Same exact service test calls `ApplicationSubmissionService::submit()` through `submit()`. Implementation `backend/app/Actions/Merchants/ApplicationSubmissionService.php`. Successful HTTP dispatch within P2 remains untested. |
| 7. Persisted application/submission state | PASS | Same exact service test asserts `pending_review`, consumed proof, one evidence record and one submission audit, plus stable replay. Implementation `backend/app/Actions/Merchants/ApplicationSubmissionService.php` and frozen `backend/database/activation/se1/2026_11_08_210000_add_se1_submission_evidence.php`. Separate fixture journey. |
| 8. Chief read-only queue visibility for the P2 application | NOT TESTED | `ApplicationSubmissionServiceTest::test_chief_read_foundation_shows_fictional_bundle_without_crypto_or_decision_authority()` checks the actual service-created application's detail page. `AdminApplicationReviewTest::test_filtering_and_pagination_preserve_scope()` checks queue listing with factory records. Neither proves queue listing for an application from the full P2 journey. Implementation `backend/app/Http/Controllers/AdminApplicationController.php`. |
| 9. Tenant isolation and current-owner authorization | PASS | `ApplicationSubmissionServiceTest::test_guest_foreign_actor_and_cross_tenant_cannot_submit_or_resolve()` and `test_manager_and_revoked_owner_cannot_use_cached_actor_or_receipt()`. Implementation `backend/app/Actions/Merchants/ApplicationSubmissionService.php`, especially `authority()`. Earlier service coverage, with P2 HTTP coverage missing. |
| 10. Duplicate/replay/stale-evidence protection | PASS | `ApplicationSubmissionServiceTest::test_fictional_bundle_commits_once_with_canonical_audit_and_private_receipt()`, `test_each_stale_expectation_is_denied_without_writes()`, `test_revoked_and_exact_expiry_proofs_deny_new_submission()` and `test_historical_receipt_survives_proof_revocation_but_different_request_conflicts()`. Implementation `backend/app/Actions/Merchants/ApplicationSubmissionService.php`. Earlier service coverage. |
| 11. Public submission disabled | PASS | `ApplicationSubmissionServiceTest::test_default_admission_and_public_endpoint_deny_even_with_fixture_crypto()` and the named INT-07A completion test's final 503 assertion. Implementation `backend/app/Http/Controllers/MerchantApplicationController.php`, `backend/app/Actions/Merchants/MerchantApplicationSubmission.php`, `backend/app/Providers/AppServiceProvider.php`. Source still retains the denial. |
| 12. Existing operational holds preserved | PASS | Source-preservation assessment. `FunctionalMerchantOnboardingTest::test_ordinary_application_does_not_register_the_fixture_routes_or_admission()`, `test_denied_operational_admission_blocks_the_actual_service_even_through_the_fixture()`, and `AdminApplicationReviewTest::test_detail_is_redacted_scoped_paginated_and_read_only()` cover important boundaries. Implementation `backend/app/Providers/AppServiceProvider.php`, `backend/routes/web.php`, `backend/app/Http/Controllers/AdminApplicationController.php`; operational authentication hold remains in `backend/app/Http/Middleware/EnsureAuthenticationReady.php`. Deployment state was outside this audit. |

All methods above reside in the correspondingly named files under `backend/tests/Feature/`.

## E. Existing test evidence

| Recorded milestone | Historical result | Meaning |
|---|---|---|
| P0, 2026-11-14 | Functional 33 passed / 964 assertions; regression 546 passed / 4,824 assertions | Latest recorded local verification. The regression selection includes public-submission denial and admin review tests, but excludes `ApplicationSubmissionServiceTest`. |
| INT-07A, 2026-11-14 | Functional 33 / 964; regression 546 / 4,824 | Onboarding completion and bounded preservation. |
| INT-06A, 2026-11-13 | Combined 770 / 6,848 | Most recent located report of a combined run explicitly including `ApplicationSubmissionServiceTest`. |
| PD-05A, 2026-11-12 | Focused 8 / 75; combined 376 / 3,099 | Read-only review foundation, including the service-created bundle detail case. |
| PD-04B.3, report dated 2026-11-08 | Focused service 43 / 407; combined 368 / 3,024 | Earlier internal submission service evidence. Later methods expanded this class. |
| P2 | No recorded execution/count | No trustworthy full-journey P2 execution evidence recovered. |

The PD-04B.3, INT-06A and INT-07A reports describe intermediate fixture/assertion failures followed by passing final runs. These records supply no current P2 failure result. No P2 test case, incomplete P2 test body or P2 runner output was found. Current tests were not executed, so their present runtime result remains unverified by this audit.

## F. Files associated with P2 prerequisites

Existing infrastructure includes `backend/app/Actions/Merchants/ApplicationSubmissionService.php`, `MerchantApplicationSubmission.php`, `SubmissionEvidenceCipher.php`, `SubmissionTransaction.php` and the corresponding admission/environment contracts. HTTP/read foundations are `backend/app/Http/Controllers/MerchantApplicationController.php`, `AdminApplicationController.php` and `backend/routes/web.php`.

Existing tests are `backend/tests/Feature/ApplicationSubmissionServiceTest.php`, `MerchantApplicationSubmissionTest.php`, `AdminApplicationReviewTest.php` and `FunctionalMerchantOnboardingTest.php`. Support files include `backend/tests/Support/FakeSubmissionAdmission.php`, `FakeSubmissionEvidenceEnvironment.php`, `FakeSubmissionVerificationEnvironment.php` and `MerchantOnboardingHttpHarness.php`. These are predecessor components. No newly attributable P2 implementation file was found.

## G. Exact missing functionality and evidence

The missing integration is one isolated functional journey retaining the same registered, email-verified merchant through actual business-name completion, application preparation, contract-valid fictional verification evidence, successful HTTP dispatch to the existing internal submission service, persisted submission assertions and Chief queue visibility. Current factory-built service evidence and separate HTTP tests leave that connection unproven.

Fictional evidence must respect existing trusted-issuance contracts. The current submission fixture uses live-shaped rows and test-only issuance custody. `test_simulated_database_lineage_denies_even_with_live_mode_and_injected_issuer()` explicitly rejects simulated provenance. A future integration must preserve that distinction and the public denial branch.

## H. Preservation and security status

Only this status report was created. A source-only SHA-256 inventory captured 324 existing source/report/configuration/metadata/template files. Final comparison found all 324 unchanged, zero changed and zero missing, with this report as the sole addition. Working databases, environment files, credentials, private keys, storage and dependencies were excluded.

Read-only hash checks matched all 29 frozen foundation/C/SE/BNC/INT-01/INT-02 source/artifact pins, all three historical BNC manifest digests and all four INT-05 protected hashes. No mismatch was found. No PHP execution, bootstrap, migrations, PHPUnit, Pint, database/sidecar access, credential access, reset, revert, commit, push or deployment occurred.

## I. Recommended next action

Owner review of this recovered status, followed by a separate bounded P2 implementation instruction if desired. Reuse the existing service, evidence and read-only review infrastructure and demonstrate the full isolated journey while preserving default runtime denial. No P2 implementation, P3 work or new architecture contract was started here.

## J. Final P2 classification

**NOT STARTED.** Earlier reusable components and their historical passing tests are present. No evidence establishes that the P2 milestone was implemented or executed, and no full P2 HTTP journey test was found.

**STOP FOR OWNER REVIEW.** Operational Business Name Completion, public application submission and Chief Admin decision writes remain DISABLED. B3.2-C and SE-1.0 remain NO-GO. Live payments and existing live provider integrations remain DISABLED.
