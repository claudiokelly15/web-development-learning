# XLipa — PD-04A Merchant Application Submission & Review Foundation

**8 November 2026 · Africa/Nairobi · Production-intended Laravel components; isolated generated fixtures only**

## 1. Outcome and essential blocker

**IMPLEMENTED:** owner-only application history/status, a server-side submission guard with explicit unavailable UI, and a Chief-only redacted application review preparation queue integrated with PD-02. **DISABLED / BLOCKED:** successful submission, proof consumption, immutable submitted evidence creation, review decisions and reapplication. No application can be submitted by this package, including with simulated proof or a live-looking database label.

This is a deliberately bounded implementation under the request's instruction to fail closed when an essential approved requirement is missing. It is **not a completed successful submission workflow**. The existing model's encrypted `profile_snapshot` and the proof's `consumed_application_id` do not establish an approved immutable email/identity/contact/version/proof evidence package. Reusing them as arbitrary JSON bindings would invent that contract. No competing table, migration, snapshot format or injectable successful-write bypass was added.

Source basis:

- B3.2-A final specification sections 3–7 require minimum fields, fresh verified owner contacts, immutable evidence and single-use proof consumption; optional broad profile fields are not blanket blockers.
- [Technical decisions](XLIPA-B3-2-TECHNICAL-DECISIONS-AND-SCHEMA-APPROVAL-2026-11-06.md), sections 5 and 10, reserve submission bindings for B3.3 and exclude them from the accepted B schema slice.
- [Final B acceptance review](XLIPA-B3-2-B-FINAL-ACCEPTANCE-REVIEW-2026-11-07.md), evidence/revocation row, explicitly says a proof reference on a draft is not a completed immutable submitted evidence package; frozen submission evidence remains absent.
- [PD-03](XLIPA-PD-03-MERCHANT-VERIFICATION-IMPLEMENTATION-2026-11-08.md), sections 6 and 10, leaves submission/proof consumption and lifecycle integration for separate review.

The new request authorizes reusable application work, but also requires reuse of accepted schema and fail-closed behavior where an essential contract is missing. Accordingly the missing evidence contract is reported instead of silently supplied. Real processing authority, official identity syntax, key custody, live delivery provenance, trustworthy time and working activation remain independent blockers.

**Working B3.2-C activation remains NO-GO. XLipa is a Tanzania-first payment gateway WEBSITE supporting TZS only.**

No working SQLite/sidecar, real dotenv, real identity record, operational key/provider account or external provider API was accessed. No real OTP/email, administrator provisioning, payment, working migration, commit, push or deployment occurred.

Labels: **VERIFIED** = newly inspected source or executed evidence in this package; **REPORTED** = historical evidence; **NOT TESTED** = no execution here; **PROPOSED** = future scope.

## 2. Inspection and implementation boundary

Read backend `AGENTS.md`; checked `.ai/rules` at workspace/project/backend locations (absent). No testing-best-practices skill was available or installed. Reviewed ORG-1.0 readiness review, PD-01/02/03 reports, B3.2-A/B acceptance/schema contracts, selected C artifact/readiness, R06 design/current reconciler, application models/statuses/policies, Chief/Staff authorization, routes, Blade layouts, factories and isolated bootstrap/helpers. Presented the concise implementation plan before edits.

Local installed metadata confirms Laravel **v13.35.0**; existing PHP **8.5.12** was used. Composer metadata was inspected with plugins/scripts disabled. No dependency, external website service, framework or sub-agent was introduced. Files were created directly to avoid an ordinary working Artisan boot. Fixture migration calls run only through the inspected testing bootstrap and guarded generated databases.

## 3. Implemented behavior

| Route | Behavior |
|---|---|
| `GET /merchants/{merchant}/applications` | Owner-only, paginated history and submission blockers; linked from onboarding. |
| `POST /merchants/{merchant}/applications/submit` | CSRF/throttled defensive command. Strict allowlist: application ID, profile version, context version. Never submits; stale state returns 409, missing readiness/evidence contract returns 503. |
| `GET /admin/applications` | Chief-only, paginated redacted non-draft history; linked from Chief navigation. No write methods or detail/reveal route. |

GET routes inherit framework HEAD behavior. Merchant routes reuse authenticated/readiness, active account/auth-version, verified-email and fresh member-scoped resolution. Service entry points reauthorize the existing fresh active owner ability. Managers/staff and platform assignments provide no merchant bypass. History is deliberately owner-only; the existing owner/manager current-summary policy is unchanged.

The submission guard rechecks authorization and readiness inside `VerificationStore`'s Laravel-managed writer transaction, acquired before workflow reads. It checks the exact current draft ID/profile version and onboarding context version, gathers bounded blockers and throws the unavailable response. No proof consumption, snapshot, application/merchant transition or success audit is written. There is no whole-operation retry, external I/O or nested transaction support. A current non-draft cannot be resubmitted. Unknown commit/success recovery is not implemented because there is no success write path.

Blocker assessment inspects the fresh account version/current verified email, merchant business name/restriction, current draft, exact C schema/ledger/key readiness, clock high-water, unresolved merchant R06 obligations, current actor-bound identity declaration under the configured syntax/declaration/notice versions, and current actor/contact/purpose-bound unconsumed/unrevoked proof with valid expiry and policy. Current simulated proof is distinguished from missing/expired/revoked/old-contact proof. **Assessment is never a ready/eligibility result**: the evidence-contract blocker is unconditional. It does not authenticate a live provider label, revalidate/decrypt all identity evidence or complete a future submission protocol. Those checks belong in the reviewed successful transaction, not in a claim that this guard is complete.

History shows only attempt, supported status, application profile version, current-attempt marker and submission/review timestamps. It omits snapshots, internal notes, free-text feedback, account contacts, identity/phone values, ciphertext and OTP fields. Supported states are Draft, Submitted / awaiting review (`pending_review`), Additional information required, Approved and Rejected. There is no invented separate submitted/under-review state. Approval explicitly grants no financial services.

Chief preparation selects only business name, application ID, attempt, status and timestamps. It excludes drafts, merchant creators' own records, submitting actor conflicts and **any** membership-linked merchant, including historical/revoked membership. A dedicated action-specific policy resolves current Chief authority; Staff Admin is denied. Role downgrade/revocation is fresh. This is metadata preparation, not a protected-evidence review, final decision or broad merchant directory. Chief/Staff own-workspace reads still do not query merchant datasets. Existing PD-02 navigation expectations were updated explicitly for the one newly enabled Chief read route; all other unavailable controls and write denials remain asserted.

Views reuse existing escaped Blade and shared responsive auth/legal assets. No new CSS, script or external resource was introduced. No real identity requirement, extra document prerequisite, financial eligibility, provider integration or payment action was invented.

## 4. Privacy and security

Submission rejects unexpected/nested fields with a generic response and no input flashing. Application routes receive bounded exception rendering even in debug mode; existing authentication redirects are preserved. Existing type-only merchant/admin error reporting and private/no-store, no-referrer, nosniff and frame-denial headers apply. No identity values or key material are selected for history/review or placed in URLs, logs, sessions, jobs or evidence snapshots.

No schema, migration, activation manifest/selection, foundation model/enum, PD-03 cipher/store/reconciler/environment, PD-01 runtime or public asset changed. No runtime class imports a test helper. Existing R06 obligations are never discharged by submission. Existing historical evidence and proof states are preserved on rejected/stale/duplicate commands.

## 5. Executed evidence

| Final execution on the delivered source | Actual result |
|---|---|
| Required Pint formatter | Passed, exit 0. |
| Focused PD-04A | **25 passed / 122 assertions / 5,973 ms**, exit 0; zero failures/errors. |
| Vetted eight-class regression including PD-01/02/03 | **234 passed / 2,052 assertions / 40,644 ms**, exit 0; zero failures/errors. |
| Existing offline frontend DOM suite | **10 pages / 31 interactions / zero DOM script errors**, exit 0. |
| Tracked and eight new-file whitespace checks | Passed; report line-ending normalization notice only. |

Commands from `XLipa/backend` use the existing PHP executable at `C:/Users/hp/.config/herd/bin/php85/php.exe`:

```text
<php85> -d auto_prepend_file= vendor/bin/pint --dirty --format agent
<php85> -d auto_prepend_file= vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result tests/Feature/MerchantApplicationSubmissionTest.php
<php85> -d auto_prepend_file= vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest'
<node> XLipa/scripts/Test-Frontend.cjs
```

Before execution, inspected the bootstrap, TestCase, selected eight classes and fixture storage helpers. The bootstrap supplies only public fixture environment values, disables real dotenv/cache loading, uses memory SQLite, array notification transport and blocked stray Laravel HTTP. Foundation/C activation occurs only in newly generated fixture databases. The new contention case exports the generated memory database into a fresh random temporary path, opens only that disposable file through two connections, restores memory configuration and removes its own file/sidecars in `finally`. Existing foundation/PD-03 contention cases likewise use fresh disposable files. Optional old visual-export flags were not enabled.

Coverage: guests, owner versus manager/staff/platform roles, cross-tenant routes, cached membership followed by revocation, stale account/application/context, supported history statuses and sensitive-data redaction, current simulated versus expired/revoked/replaced-contact proof, unresolved real fixture R06 obligation after injected audit failure, default denial with C absent, duplicate denied command, already-submitted attempt, restricted merchant/policy absence, CSRF/method denial, Chief/Staff separation, fresh Chief downgrade/revocation, own/historical membership conflicts, managed writer contention and rollback/nested-transaction rejection. **Contention proves rejection before workflow mutation and unchanged state after release; it does not prove a successful double-submit winner.** Rollback checks exercise the reused store and nested denial, not a nonexistent successful submission transaction.

Development evidence: several initial runner attempts stayed silent and were interrupted without a completed result. A PHPUnit version check and guarded-bootstrap markers completed; subsequent runs used a process-local empty `auto_prepend_file` to omit Herd's dump-loader. No global PHP setting changed; no definitive cause is claimed. The first completed focused run had 21 passing cases and one test setup error from a nonexistent User relationship; the test now loads Merchant memberships. Corrected focused evidence passed 25/120 and an intermediate regression passed 234/2,050. A final strengthening distinguishes current simulated proof from invalid proof and checks historical Chief membership conflicts. Earlier passes do not substitute for the final revision. The first bare Composer invocation was unavailable on PATH; the installed phar invocation succeeded. No guard was weakened.

**NOT TESTED:** live authenticated browser, new-page visual/responsive/accessibility certification, actual provider/NIDA processing, operational concurrency/power loss/unknown commit, successful submission/evidence consumption or review decisions. The existing frontend DOM suite does not certify the new Blade page layout. No unrestricted full suite was run; historical unsafe working-file probes remain excluded. Old B/C/SOB results remain REPORTED.

## 6. Exact file delta and preservation

Paths below are relative to `XLipa/`.

| Added file | Purpose |
|---|---|
| `backend/app/Actions/Merchants/MerchantApplicationSubmission.php` | Fail-closed submission command and safe blocker assessment. |
| `backend/app/Http/Controllers/MerchantApplicationController.php` | Owner history and strict defensive submission request. |
| `backend/app/Http/Controllers/AdminApplicationController.php` | Chief-only redacted non-conflicting history query. |
| `backend/app/ViewModels/ApplicationHistoryEntry.php` | Shared supported-state labels. |
| `backend/resources/views/merchants/applications.blade.php` | Paginated history and disabled submission interface. |
| `backend/resources/views/admin/applications.blade.php` | Read-only review preparation and unavailable actions. |
| `backend/tests/Feature/MerchantApplicationSubmissionTest.php` | Focused isolated security/contention tests. |
| `XLIPA-PD-04A-MERCHANT-APPLICATION-SUBMISSION-2026-11-08.md` | This report. |

| Modified existing file | Change |
|---|---|
| `backend/routes/web.php` | Two GET routes and one throttled POST route under existing middleware. |
| `backend/bootstrap/app.php` | Bounded application-route exception rendering. |
| `backend/app/Policies/PlatformRoleAssignmentPolicy.php` | Explicit Chief-only preparation ability. |
| `backend/app/ViewModels/AdminWorkspaceOverview.php` | Accurate Chief redacted-read scope. |
| `backend/resources/views/admin/workspace.blade.php` | Chief preparation navigation and availability text. |
| `backend/resources/views/merchants/onboarding.blade.php` | Owner-only history entry. |
| `backend/tests/Feature/AdminWorkspaceTest.php` | Expect the Chief link/14 remaining disabled controls; assert no Staff link and all write methods denied on the new route. Replace old GET-unavailable expectation with an unsupported decision path. No unrelated assertion removed. |

Pre-edit in-memory SHA-256 inventory: **215 existing files**, selected from app/routes/resources/tests/config, migration/activation text, canonical frontend/scripts and root reports using an extension allowlist. It excludes working databases/sidecars, dotenv, protected metadata, dependency/storage trees and binary assets. Post-edit comparison: **209 unchanged, six intentional changes, zero missing** within that inventory. `backend/bootstrap/app.php` is the seventh intentional existing-file modification, outside those inventoried directories; its bounded diff was separately reviewed. No broader preservation certification is claimed.

Repository HEAD at inspection is `e2e746a60580d5d4adbf50511a45b3084c38587c`, superseding the pasted historical report's HEAD. Initial Git status was clean; staged diff remains empty. No commit, push or deployment occurred. The final expected delta is seven existing files, seven new source/test files and this report. No new generated CSS, database, preservation inventory or browser artifact is part of the delivery.

## 7. Next implementation scope and owner review

**PROPOSED next:** approve the exact immutable submission evidence contract and its representation in the existing application/evidence schema (or separately approve a bounded schema amendment). Specify frozen verified-email binding, identity declaration/version reference, contact/version/proof lineage, business/profile versions, retention/encryption/immutability and redacted audit payloads; prove they cannot be rewritten through raw SQL or lifecycle changes. Then implement one serialized successful transaction that revalidates all minimum requirements/provenance, consumes exactly one current live proof, freezes evidence, changes only the accepted draft → pending_review transition and appends its success audit, with rollback/duplicate/concurrent-winner/unknown-outcome evidence. No arbitrary snapshot format or live-label trust should be inferred from this guard.

Keep live identity/contact authorization, official policy/key custody, delivery provenance/budget, trusted time/restore holds, R06 scanning and working backup/activation gates independent. Chief decision authority additionally needs scoped evidence access, conflict and reauthentication rules, review/audit transition contracts and isolated decision tests. Staff delegation, reapplication and financial eligibility remain separate packages.

**GO for owner review of the implemented bounded history/guard/preparation source and fixture evidence. NO-GO for successful submission, review decisions, real processing/delivery and working B3.2-C activation. STOP FOR OWNER REVIEW.**
