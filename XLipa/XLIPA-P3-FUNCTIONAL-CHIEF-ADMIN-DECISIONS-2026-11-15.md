# XLIPA P3 functional Chief Admin decisions

2026-11-15. **PASS for isolated Chief Admin approval and rejection.**

## A. Recovered starting state

Recovered branch `main`, HEAD `b670fdb12aa65e64e977bb2b677c392028fc0bf2`, with a clean working tree and empty index. This differs from the earlier P2 HEAD. The interrupted P3 preflight service, extracted `MerchantApplicationSubmissionJourney` trait and P2 test refactor were already committed by the time of this continuation. They were inspected and continued. The preflight returned only denial and supplied no completed approval/rejection evidence.

Read the applicable backend AGENTS.md, actual P2 report, decision specification, models, policies, statuses, frozen catalogue/ledger checks, audit constraints, services, fixtures and verification tooling. No applicable `.ai/rules` directory was present. Installed metadata identifies Laravel 13.35.0, PHPUnit 12.5.38 and Pint 1.32.1. The referenced testing-best-practices skill is unavailable in the session catalog.

The earlier assumption that a new decision table was required was corrected. The existing schema permits pending-review transitions to approved/rejected and supplies an append-only `application.reviewed` audit. This implementation uses that existing audit as its authoritative decision record. No additive schema or catalogue exception is needed.

## B. Files created or changed

Paths below are relative to `backend`.

| File | Work |
|---|---|
| `app/Actions/Merchants/ApplicationDecisionService.php` | Extended the recovered preflight into the actual transactional decision writer |
| `app/Actions/Merchants/ApplicationDecisionAdmission.php` | New concrete default-denied operation-specific authority boundary |
| `tests/Support/ApplicationDecisionHttpHarness.php` | New explicitly registered, guarded HTTP boundary with private server context |
| `tests/Support/FakeApplicationDecisionAdmission.php` | New fictional scoped grants, authority version and trusted time |
| `tests/Feature/FunctionalChiefAdminDecisionTest.php` | New P3 functional/security/failure suite |
| `tests/Support/MerchantApplicationSubmissionJourney.php` | Formatter cleanup of recovered shared P2 helpers |
| `tests/Feature/FunctionalMerchantApplicationSubmissionTest.php` | Formatter/import cleanup of recovered P2 trait usage |

This report is the only additional document. Existing business services, routes/controllers, read views, provider bindings, frozen definitions and operational configuration retain their bytes.

## C. Approval functionality

The approval case in `test_same_p2_application_is_decided_atomically_and_visible_to_owner_and_chief()` registers the merchant through HTTP, verifies email through HTTP, saves its name through the original completion service/harness, prepares the original application and fictional evidence, and submits it through the real submission service. The existing P2 shared journey supplies the same merchant, owner, membership and application identities throughout.

An independent fictional Chief reads that application, then submits a private context reference to the guarded test-only decision route. The real decision service commits `approved` and returns the safe outcome. Existing Chief queue/detail reads show the exact application and recorded review. The owner-facing applications page shows Approved. The merchant row remains byte-identical, including onboarding/unverified statuses and disabled financial capabilities.

## D. Rejection functionality

A separate isolated run of the same full P2 journey supplies the rejection case. The same real service commits `rejected` with a bounded machine-readable reason code, and the owner-facing page shows Rejected. Rejection reasons require 1–50 ASCII lowercase/alphanumeric/underscore characters starting with a letter. Empty, missing, oversized and markup reasons are rejected. Admission binds the exact outcome and reason, so syntax alone grants no policy authority. No free-form feedback or new legal/provider policy is introduced.

Approval after rejection, rejection after approval and repeated contexts cannot overwrite the decision or append a second review audit.

## E. Database and audit behavior

The existing `SubmissionTransaction` reserves the SQLite writer, attempts commit once and preserves its uncertainty behavior. The decision service revalidates schema, actor, role, session, independent authority version and operation-specific grant inside the transaction and before commit. It checks the exact pending application, attempt/profile/submission identity, conflict exclusions and absence of another review event.

The real submission cipher validates the retained encrypted capsule and logical identity/contact commitments. The service checks the original submission audit, consumed-proof lineage, current account/email/membership/context/profile bindings, declaration policy, issuance, retention and custody. It never impersonates the submitting owner. Natural expiry of a previously valid consumed proof preserves historical validity; current decision policy remains independently admitted. Revoked evidence and withdrawn authority/custody are held.

One canonical `application.reviewed` event records actor, merchant, application, request UUID, occurrence time and ordered redacted metadata containing from/to/profile_version/reason_code. A compare-and-set updates only application status, reviewer and review time. Final checks require exact event/application agreement, unchanged submission evidence and exactly one review event. Existing frozen audit triggers reject history mutation. No new table, trigger, migration or manifest was created.

## F. Security verification

The suite covers owner/self-Chief conflicts, managers, merchant staff, platform staff, revoked Chief authority, suspended/unverified accounts, stale sessions and stale reviewer/authority/email/application expectations. Authorization is checked within the real writer, including revocation at writer acquisition and after the application update.

Foreign scope is tested both at the private HTTP-context boundary and inside the domain service using a deliberately mismatched server fixture. CSRF and unknown client claims are rejected. Default admission denies writes even with fixture crypto available. An ordinary application has no fixture decision route. Its existing operational POST/PUT/PATCH/DELETE detail requests remain 405.

The test boundary reuses the exact CLI/testing/synthetic flag/memory-SQLite/owned-PDO guard. Grants and crypto environments are installed only for scoped fixture dispatch and restored in `finally`. No provider delivery is enabled. The production-intended admission class defaults to denial and requires future independently reviewed final authority, policy/step-up, time and restore provisioning.

## G. Audit, concurrency and actual test results

Two prepared conflicting requests produce one winner and one review audit. Audit-insert failure, post-state-update failure and final authority loss roll back application and review audit together while preserving submission evidence. Both pre-commit and post-commit acknowledgement-loss cases return 202 unresolved. Owned fixture PDO reads distinguish actual states. The boundary holds all other prepared requests for that application, preventing automatic retries or opposite decisions. These are deterministic fault/competing-command tests, with no production crash-recovery certification.

Commands ran from `XLipa/backend` using the existing Herd PHP 8.5.12 CLI with approved sandbox escalation.

```powershell
$php = 'C:/Users/hp/.config/herd/bin/php85/php.exe'
& $php -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/FunctionalChiefAdminDecisionTest.php
& $php -d 'auto_prepend_file=' vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/FunctionalMerchantApplicationSubmissionTest.php tests/Feature/ApplicationSubmissionServiceTest.php tests/Feature/AdminApplicationReviewTest.php tests/Feature/MerchantAccessTest.php
```

Final P3 result passed **40 tests / 1,771 assertions / 35,981 ms, exit 0**. The preserved P2/internal-submission/Chief-read/merchant-authorization selection passed **103 tests / 1,771 assertions / 50,550 ms, exit 0**. Intermediate P3 runs passed 38/1,682 and 40/1,768 before the final foreign-domain assertion strengthening. Counts from overlapping runs must not be added together.

All seven task PHP files passed syntax checks, exit 0. Scoped required dirty/explicit formatting and final `vendor/bin/pint --test --no-config --format agent` checks passed. Only task-file style/imports changed. Final executable edits are complete.

The unchanged P0 entry point is invoked from the shared workspace root.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File XLipa/scripts/verify-isolated.ps1 -PhpPath C:/Users/hp/.config/herd/bin/php85/php.exe
```

P0 entry point passed, **exit 0**. Syntax passed for **190 PHP files**, non-mutating Pint passed for its **15 files**, and source preservation passed. INT-07A passed **33 tests / 964 assertions / 18,310 ms**. Established bounded regression passed **546 tests / 4,824 assertions / 81,054 ms**, exit 0. The P0 selection remains its established INT-07A and bounded scope. The P3 and preserved P2/service commands above supply their separate coverage.

## H. Source-preservation evidence

Final comparison of 331 recovery-inventoried source/report/configuration/metadata/template paths found **328 unchanged, three intentionally changed and zero missing**. Existing changes are limited to the recovered service and two recovered P2 helper/test files listed above. Four new PHP files and this report are the only additions. Real environment/credential/key files, databases/sidecars, storage and dependencies were excluded.

Final read-only comparisons matched **29 frozen source/artifact pins, three historical manifest digests and four INT-05 protected hashes**, with zero mismatches. P0 independently matched those plus all four isolation guard hashes. No manifest was regenerated. Git whitespace checking passed and the index remains empty.

No real `.env`, operational APP_KEY, credentials, private keys, working database or sidecars were opened, copied, hashed, queried or migrated. All schema installation occurred inside established guarded fixture databases. No dependency installation, commit, push, deployment or operational activation occurred.

## I. Remaining blockers

No isolated acceptance blocker remains. Operational final-decision grants, privileged step-up policy, approved reason catalogue, trusted time/retention/restore authority and durable unresolved-request custody remain unprovisioned. Default denial is retained. The fictional scoped authority version is independent of mutable role rows and is not a new database authority-history contract. Existing audit history is the decision record; current application columns remain the established read projection. Test scenarios retain the deterministic P2 clock on 2026-11-14, with review 100 ms after submission. Browser interaction and live-provider verification are outside this HTTP feature-test evidence.

## J. Final assessment

**PASS for P3 isolated functional implementation.** Actual approval and rejection commit through the real internal service on the same P2-created applications, preserve immutable submission evidence, create immutable review audits and update the authorized read surfaces. Focused, preserved-functionality and bounded regression runs, syntax, non-mutating Pint and preservation checks passed. Operational readiness remains subject to the dependencies and holds above.

**STOP FOR OWNER REVIEW.** B3.2-C and SE-1.0 operational activation remain NO-GO. Operational Business Name Completion, public submission, operational Chief Admin decision writes, live OTP/NIN, Selcom and live payments remain DISABLED. Working database migration remains unauthorized. No P4 work was started.
