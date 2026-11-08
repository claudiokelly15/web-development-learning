# XLipa — PD-03 Merchant Verification & Secure Identity Capture

**PD-03 · 8 November 2026 · Africa/Nairobi · Production-intended application layer; isolated fixture execution only**

## 1. Outcome and execution boundary

**IMPLEMENTED / VERIFIED:** an owner-only merchant verification page, private versioned phone and identity capture services, an OTP request/dispatch/verification lifecycle, persistent attempt/resend controls, context-bound encryption and R06 pending-audit reconciliation. These are application classes and Blade routes, integrated with PD-01/onboarding. Runtime code imports no test helper.

**Real collection and delivery remain unavailable by default.** The default application dependency supplies no operational authorization, keys, identity policy or transport. It cannot send messages or save identity/contact evidence. Tests inject a test-only environment with disposable keys, fake delivery and a deliberately fictional identity syntax. No real NIDA syntax or lawful-processing approval is invented.

Final focused evidence after the transaction fix: **53 tests / 316 assertions**, zero failures/errors. Final vetted regression: **209 tests / 1,906 assertions**, zero failures/errors, including all existing PD-01/PD-02 tests. Six offline responsive checks passed with zero HTTP requests/page errors before the transaction-only correction; their view/CSS/script revision is unchanged. Existing frontend checks passed 10 pages / 31 interactions / zero DOM script errors.

No working SQLite/sidecar, real dotenv, real identity record, operational key or provider account was accessed. No real SMS/email, payment, administrator provisioning, commit, push or deployment occurred. **Working B3.2-C activation remains NO-GO.** XLipa is a Tanzania-first payment gateway WEBSITE supporting TZS only.

**VERIFIED** = inspected source or newly executed evidence in this scope; **REPORTED** = historical results; **NOT TESTED** = no executed evidence here; **PROPOSED** = future work.

## 2. Inspection, plan and reuse

Read backend `AGENTS.md`; inspected current routes, auth/readiness/session checks, merchant membership/policies, onboarding summary, PD-01/PD-02 controllers/views, canonical assets, factories and isolated bootstrap. Reviewed ORG, PD-01/PD-02, B3.2-A, accepted B blocker/acceptance contracts, original guarded migrations, frozen C schema, selected activation mechanism and test-only crypto/verification/coordinator/reconciler implementations. Prior reports establish no current working state.

The concise plan was presented before edits: owner-only routes and redacted UI; application implementations over accepted schema/crypto/OTP contracts; default operational denial; isolated fixtures/fake adapters and explicit safe regression. No additional broad security prototype was built.

`.ai/rules` was absent at checked workspace/project/backend locations. No applicable testing-best-practices skill was available; none was installed. Existing local repository/frontend architecture was retained; no external website service, new framework, dependency or sub-agent was used. Composer with plugins/scripts disabled confirmed Laravel **v13.35.0**. Existing PHP **8.5.12** and Node **26.13.0** were reused. Direct file creation avoided an ordinary working Artisan boot.

Reused unchanged: foundation models/enums/factories, fresh member-scoped route resolution, authentication/CSRF/private headers, merchant summary, public/auth assets, PD-01 styles, all PD-02 implementation and accepted B/C schema assets. Test helpers are used only by tests to build generated memory fixtures and check crypto interoperability.

## 3. Implemented routes and interface

All routes inherit authentication/readiness, active account/auth-version, verified email and member-scoped merchant resolution:

| Route | Capability |
|---|---|
| `GET /merchants/{merchant}/verification` | Owner-only safe status and responsive private forms; HEAD follows framework behavior. |
| `POST .../verification/contact` | Confirm current password, validate/normalize supported Tanzania mobile number, capture a new encrypted version against expected context version. |
| `POST .../verification/identity` | Confirm current password; require approved syntax/declaration/notice policy, subject capacity and affirmative declaration; save encrypted version only. |
| `POST .../verification/request` | Request UUID, current bound contact, durable limits and approved delivery budget/adapter boundary. |
| `POST .../verification/code` | Check the bound active challenge; never return the code or raw proof data. |

The onboarding page links owners to private verification availability. The dashboard offers a direct verification action only when the current owner and dependencies are ready; otherwise it explains the operational restriction and points to onboarding status. Existing summaries and financial-disabled navigation remain intact.

The page shows safe states: no record, private contact recorded, declaration recorded but not independently verified, absent/expired proof, audit hold and operational unavailability. A simulated proof is explicitly **“Simulated test proof only; not real verification.”** Email status refers only to the current authenticated account, not a separate business contact or identity match.

Phone/NIN/code inputs have no stored-value autofill. NIN is write-only and never displayed, including a suffix. Declaration capacity is explicit; merchant membership does not prove representation. Optional documents/registration/bank fields were not made mandatory. No submission, reapplication, Chief decision, full-identity reveal, payment eligibility or financial service was added.

## 4. Authorization, validation and privacy

`MerchantPolicy::manageVerification` requires existing fresh active/verified account and tenant access plus an active owner membership with no revocation marker. Managers/staff cannot read this sensitive workflow. Platform Chief/Admin assignments provide no merchant bypass. Every service action rechecks authority; mutation/delivery/proof creation additionally require a non-suspended/non-closed merchant and a current draft application. Missing draft, pending review and restricted merchants are read-only.

The SQLite writer is acquired before reading mutation state. Capture compares the expected context version; stale requests return conflict without partial changes. Contact replacement retires the prior version and revokes its available/consumed proofs and active challenges, preserving immutable historical evidence and aggregate failure history. A new owner contact also retires the previous selected contact. Identity replacement supersedes the prior declaration instead of rewriting it. No foundation phone is silently adopted as verified evidence.

FormRequests reject unsupported fields, invalid structure, missing declaration/password and unsupported phone input. The approved policy boundary must supply actual identity normalization; its default supplies none. The test-only syntax accepts fictional `FIXTURE-` identifiers, not a purported NIDA format. Incomplete/invalid requests roll back, create no substitute identity and do not flash input. Exact NIN/code bytes bypass default trimming so control characters cannot be silently normalized into acceptance.

Validation returns a generic message with no old input, including unexpected nested fields. Sensitive fields extend the existing don't-flash list. Verification exceptions render bounded generic responses even with application debug enabled; existing auth redirects remain intact. Existing merchant exception reporting records only exception type/category. No plaintext identity/phone/code is placed in logs, audit metadata, URLs, default model serialization, jobs or database code fields.

Capture requires current password on each change. All new writes require CSRF, and HTTP throttles bound capture/code/request flooding. Other HTTP methods are unavailable. Private/no-store, no-referrer, nosniff and frame denial apply. External request logging, analytics/session recording and operational observability controls still require deployment review.

## 5. Crypto and database compatibility

No competing schema or migration was created. Runtime readiness requires SQLite/FK enforcement, the exact frozen C table/index/trigger definitions, the expanded audit table and exactly one genuine C activation ledger record. Missing schema, schema drift, missing ledger or unavailable keys denies mutation. This validates capabilities; it does not migrate or adopt a ledger. Default unprovisioned dependencies deny before new C-table use, preserving the existing foundation dashboard path.

The six application services implement the accepted contracts:

- `VerificationCipher`: sodium XChaCha20-Poly1305, random 24-byte nonce, dedicated 32-byte domain keys, strict canonical envelope-v1 parsing and LP associated data binding deployment namespace, algorithm/version/key/revision/domain, canonical merchant/record ULIDs, purpose, content version and actor.
- Phone and NIN domains remain separate from OTP HMAC/contact fingerprint keys and APP_KEY. Readiness rejects aliasing the four domain keys. No key registry, real key custody or rotation is provisioned.
- Phone fingerprint uses `xlipa-contact-fp-v1`; rolling failure scope uses `xlipa-otp-fail-scope-v1` and excludes contact version/challenge so replacements cannot reset the same actor/merchant/target window.
- OTP digest uses the accepted `xlipa-otp-code-v1` binding, constant-time comparison, versioned key and CSPRNG-generated six digits. No plaintext code is stored.
- Bidirectional tests decrypt application envelopes with the unchanged accepted fixture cipher and vice versa, and reject a changed actor binding. Ciphertext tampering stops delivery.

The approved declaration/syntax/notice identifiers are stored server-side with the record. Syntax-valid declaration remains distinct from independently verified identity. No government checksum/API, extra identity field or regulatory requirement is guessed.

## 6. OTP, delivery and R06 behavior

The application preserves five-minute challenge expiry, five failed attempts per challenge, ten rolling failures per actor/merchant/target in thirty minutes, sixty-second resend minimum and thirty-minute proof expiry. It also enforces the specified bounded send policy of three reservations per fifteen minutes and five per hour. HTTP request/code/capture throttles supplement durable limits. Target/IP/global quotas, spend control and external-delivery fencing must be supplied by an approved budget adapter; the default denies.

A send is reserved durably with a unique actor/merchant/request UUID. Duplicate requests do not resend, including after ambiguous delivery. Replacement revokes/expires the predecessor without clearing failures. Dispatch commits a digest and a bounded claimed intent before calling the injected adapter outside the transaction. Definitive acceptance, current authority/context and an unexpired dispatch lease are required to activate a challenge. Unknown/exception/late outcomes cannot create proof; no automatic external retry occurs. Authority or contact changes during the callback prevent activation.

Verification checks current owner/context/contact, pending audit, exact challenge binding, state/expiry, accepted intent and limits before comparing. One successful transaction consumes the challenge and creates one provenance-bound proof plus redacted audit. Replay, expired, foreign-context and old-contact challenges fail. Status checks proof expiry; nothing sets a global phone-verified, identity-verified or payment-ready flag.

At the fifth/tenth failure, the failed count, rolling timestamps, terminal state as applicable and five-field R06 obligation commit together. Reconciliation happens afterwards. Audit failure therefore cannot roll counts back to four/nine. Pending obligations gate issue/verify/capture and status readiness with no TTL release.

The internal keyless reconciler reloads saved lineage, inserts or exactly matches every audit payload field and CAS-clears only that obligation in one writer transaction. Conflicting evidence stops discharge; retry is idempotent. No overwrite, fabricated completion, counter reset or force-clear exists. No reconciliation endpoint, scheduler, worker or operational scanner is registered; deployment/integration remains a separate gate. Future submission still needs its separately reviewed proof-consumption/readiness transaction.

`VerificationStore` uses Laravel-managed transaction bookkeeping and acquires SQLite writer ownership with a zero-row UPDATE before workflow state reads. This preserves Laravel's in-transaction prohibition on query reconnect/retry, which a raw PDO transaction alone did not establish. Nested transactions are rejected. A two-connection test on a newly generated disposable database proves a competing writer is refused before the operation callback and succeeds after release. Another test proves rollback, framework transaction levels and dispatch outside the transaction. There is no whole-operation retry after work begins or commit ambiguity. Incomplete/unknown delivery remains unusable until a safe later lifecycle action; no background resume or repair authority is installed. Process/power-loss tests were not executed in PD-03.

## 7. Exact task delta

Paths are relative to `XLipa/`. Earlier uncommitted/untracked work is baseline content, not this task's addition.

| Added file | Purpose |
|---|---|
| `backend/app/Actions/Merchants/VerificationEnvironment.php` | Unprovisioned trusted authority/key/clock/policy/budget/delivery integration boundary; default deny. |
| `backend/app/Actions/Merchants/VerificationCipher.php` | Application envelope-v1 crypto boundary. |
| `backend/app/Actions/Merchants/VerificationReadiness.php` | Keys/environment/exact schema/ledger capability checks. |
| `backend/app/Actions/Merchants/VerificationStore.php` | Serialized SQLite writer and CAS changes. |
| `backend/app/Actions/Merchants/VerificationAuditReconciler.php` | Exact, keyless R06 obligation discharge. |
| `backend/app/Actions/Merchants/MerchantVerification.php` | Owner capture, status and OTP orchestration. |
| `backend/app/Http/Controllers/MerchantVerificationController.php` | New GET/four POST actions and safe redirects. |
| `backend/app/Http/Requests/MerchantVerificationRequest.php` | Authorization, validation and no-input-flash failure handling. |
| `backend/resources/views/merchants/verification.blade.php` | Responsive private workflow and honest unavailable states. |
| `frontend/assets/xlipa-verification.css` | Scoped styling over existing XLipa/PD-01 assets. |
| `backend/tests/Support/FakeMerchantVerificationEnvironment.php` | Test-only disposable keys, fictional syntax and fake delivery. |
| `backend/tests/Feature/MerchantVerificationTest.php` | 53 isolated cases, including transaction/contention checks and optional synthetic Blade export. |
| `scripts/Test-Merchant-Verification.cjs` | Offline browser checks of actual fixture-rendered responses. |
| `XLIPA-PD-03-MERCHANT-VERIFICATION-IMPLEMENTATION-2026-11-08.md` | This report. |

| Modified existing file | Bounded change |
|---|---|
| `backend/app/Policies/MerchantPolicy.php` | Add explicit owner-only verification ability; existing summary ability preserved. |
| `backend/routes/web.php` | Add verified member-scoped GET/four POST routes with throttles. |
| `backend/bootstrap/app.php` | Exact sensitive-input handling, no-flash fields and generic verification exception responses. |
| `backend/app/Http/Controllers/MerchantDashboardController.php` | Pass current owner/dependency availability for the new action. |
| `backend/resources/views/merchants/dashboard.blade.php` | Add guarded verification action/availability explanation. |
| `backend/app/Http/Controllers/MerchantOnboardingController.php` | Pass the authorized merchant to the view. |
| `backend/resources/views/merchants/onboarding.blade.php` | Owner-only verification availability entry. |

There are **seven intended existing-file modifications, thirteen new source/test/QA files and this report**, plus the ignored generated `backend/public/assets/xlipa-verification.css`, byte-identical to its canonical source. The existing frontend sync workflow already covers it; no sync script or full sync run was needed.

No accepted migration/activation/manifest, model/enum, existing test, PD-02 source/style, canonical public/auth page, or existing shared stylesheet/QA script changed. No application service imports `Tests\\` classes or fixture constants.

## 8. Executed tests and responsive evidence

Existing PHP from `C:\Users\hp\.config\herd\bin\php85\php.exe`; PHPUnit commands from `XLipa/backend`:

```text
<composer> --no-plugins --no-scripts show laravel/framework --no-interaction --format=json
<php85> vendor/bin/pint --dirty --format agent
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter MerchantVerificationTest
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest'
<node> XLipa/scripts/Test-Frontend.cjs
<node> XLipa/scripts/Test-Merchant-Verification.cjs <verified-synthetic-export-directory>
```

| Final execution | Actual result |
|---|---|
| Pint after final PHP edits | Passed; only intended new/modified PHP files formatted. |
| Focused PD-03 after transaction fix | **53 passed / 316 assertions / 12,887 ms**, exit 0; zero failures/errors. |
| Vetted seven-class regression after transaction fix | **209 passed / 1,906 assertions / 36,051 ms**, exit 0; zero failures/errors. Includes all 156 prior safe cases and 53 new cases. |
| Existing frontend DOM suite | **10 pages / 31 interactions / zero DOM script errors**, exit 0; external resources disabled. |
| Final offline browser | **Six combinations passed**, zero HTTP requests and page exceptions. |

The optional visual-export focused run used `XLIPA_PD03_VISUAL=1` only in the test process before the transaction-only correction. It changes no runtime behavior. Final focused/regression processes did not enable export. Tests build original foundation and selected C schema on guarded memory databases; the final contention case exports only that generated fixture into a newly created temporary database and removes it afterwards. Tests use fake notifications and test-only transport and block stray Laravel HTTP. The accepted isolated bootstrap avoids real dotenv/cache configuration. No working migration command ran.

The seven selected classes and their fixture helpers were inspected for direct storage behavior. Existing foundation concurrency tests use a new temporary file exported from memory. The unrestricted suite was not run because the historical direct working-file probe remains outside this safe scope. No unrelated/historical test was modified or weakened. Old aggregate B/C/SOB passes remain REPORTED; this is no all-suite isolation certification.

Coverage includes guests, inactive/unverified/stale accounts, fresh owner revocation/downgrade, manager/staff/platform denial, cross-tenant routes/challenge IDs, unsupported fields, current-password/CAS requirements, missing policy/schema/ledger/keys, encryption interoperability/tampering, malformed identity and no flashing, OTP HTTP success marked simulated, invalid/expired/replayed codes, cooldown/rolling/hourly limits and clock rollback, durable fifth/tenth failures, persistent pending audit and exact reconciliation conflicts, delivery failure/unknown/lease expiry/authority loss, CSRF/method denial, private headers and PD-01/PD-02 compatibility.

Development evidence: the first new run failed fixture setup because MerchantFactory already creates owner membership/draft; duplicate setup was removed. The next run passed 40/43, exposing the custom error renderer intercepting guest redirects, a test resetting away its own account while checking missing schema, and default trimming accepting a control-suffixed value. Guest handling, fixture setup and exact sensitive-field validation were corrected. Clean intermediate runs passed 43/241 and 48/292; an earlier 204/1,880 regression passed before the final three adversarial cases. These are not substituted for final results above. An initial Composer metadata invocation used the workspace directory; a later proper backend invocation succeeded. No guard was weakened.

Final source review found the raw-PDO/Laravel transaction-counter mismatch after the earlier 51/307 and 207/1,895 passes. The framework-managed correction passed 52/311 before the final contention case was added. Its first attempted run was not executed because automatic approval review hit a usage limit; the review explicitly stated that this was a review failure, not a determination of unsafe behavior. No bypass was attempted. On the user's explicit continuation request, saved files/report were inspected and only unfinished verification/report work resumed. The final 53/316 and 209/1,906 results above cover the corrected source and all final cases. Runtime/formatter/browser escalation was otherwise allowed. No ordinary working Artisan, installer, service command or external API invocation was used.

The final browser loaded actual Blade exports with embedded canonical CSS and a disposable profile, blocked HTTP/HTTPS and never contacted Laravel. Widths 1440/820/390 px passed no overflow/clipped controls, keyboard-visible skip link, labeled inputs, empty sensitive values, no scripts, and appropriate disabled fieldsets for unavailable/fixture-ready states. Desktop uses two panels; tablet/mobile stack as needed. Final desktop ready and mobile blocked screenshots were visually inspected.

Private ignored synthetic artifacts:

- [Fixture-ready desktop](backend/storage/framework/testing/pd03-visual-3140fd9bfae039d09018/ready-desktop.png)
- [Fixture-ready tablet](backend/storage/framework/testing/pd03-visual-3140fd9bfae039d09018/ready-tablet.png)
- [Unavailable mobile](backend/storage/framework/testing/pd03-visual-3140fd9bfae039d09018/blocked-mobile.png)

The directory also contains both HTML responses, six screenshots, marker and browser-results.json. Older generated exports remain ignored synthetic QA material. Browser profiles were removed after each check. No full accessibility, assistive-technology, cross-browser/device or working authenticated-browser certification is claimed.

## 9. Preservation and source pins

Before edits, an in-memory SHA-256 baseline covered **226 pre-existing source/document files**, using an explicit extension allowlist and excluding database/SQLite/sidecar, dotenv, credential/secret/key-named and protected metadata paths before hashing. Existing PD-01/PD-02/ORG/OSD/SOB work was baseline content. Ignored dependencies/caches/storage, binary assets and excluded paths are not exhaustively preservation-certified.

**VERIFIED post-test comparison:** seven intentional modifications listed above; **219 baseline files unchanged**, zero missing. Existing PD-01/PD-02 tests, PD-02 runtime, foundation/security/migration/activation assets and old reports retain baseline bytes. Source/generated verification CSS hashes match. No preservation inventory file was created.

Final SHA-256 pins (the report has no self-referential hash):

| Path | SHA-256 |
|---|---|
| `backend/app/Actions/Merchants/MerchantVerification.php` | `bef6acb2b9ad0d27001311822556515c57adc248a782ed8b1616586209d2afe1` |
| `backend/app/Actions/Merchants/VerificationAuditReconciler.php` | `608deb38fd146657d9722f6a23489ee40f7c56996aa12414e7f560ad874434a3` |
| `backend/app/Actions/Merchants/VerificationCipher.php` | `9a45586c12a0acb3497d0b6ddd0e11e5988b4e211d19d2b5e61a59cf6ada5542` |
| `backend/app/Actions/Merchants/VerificationEnvironment.php` | `5a02cf9cc2bbe9a1b88daeac629abdd9c84a0b1676c202f76cf2820cd8d6e251` |
| `backend/app/Actions/Merchants/VerificationReadiness.php` | `5167fae3db9a85dbac723063e3d2899d8508601be60afa8f69e08d6a8d18009b` |
| `backend/app/Actions/Merchants/VerificationStore.php` | `670466f24b2a1502ea6d81cdf131da93b5c219e0e3683fadb8d15db6271ef3ed` |
| `backend/app/Http/Controllers/MerchantVerificationController.php` | `35abb487b1c75c6fb365d963c1b7ee46d53097021a44f0bdd3700fb0a503df00` |
| `backend/app/Http/Requests/MerchantVerificationRequest.php` | `a86a1cf657b3891820b445c18f993f1af383e554afa365adce6024dbb7775d44` |
| `backend/resources/views/merchants/verification.blade.php` | `9159562755891f7148c87037619780fefaa864cf946611733118faa4b7e56c89` |
| `frontend/assets/xlipa-verification.css` | `663c52bc5788018cd8611606b9ee67a64654364bc364d4baf247dc216f1d54e6` |
| `backend/tests/Feature/MerchantVerificationTest.php` | `687aa8d5c2b574458e5995fcfca2d4d02ac60a47a5b9ee121ce2f193cfa5143b` |
| `backend/tests/Support/FakeMerchantVerificationEnvironment.php` | `6afc8907644fd1f8a3e66d54cbe94a4b16b42f91929cbe7afdb2e5e40500369b` |
| `scripts/Test-Merchant-Verification.cjs` | `bd52caba2f9fdca549d09987adc919df733b39e0a3b9e3ba8c494f2157743a58` |
| `backend/app/Http/Controllers/MerchantDashboardController.php` | `5b235f1b13734f47977ce5e521da6cf9dc2b1cc018e642466677fb9b1d0296bc` |
| `backend/resources/views/merchants/dashboard.blade.php` | `8f857bb8c1b054644323e67eb0f2df4ac7e2efeca8f0df10b2bbe2f2d3b40fe8` |
| `backend/app/Http/Controllers/MerchantOnboardingController.php` | `a3be757804aeeabc65644408655ba04e0e22fe54581d4705549eb5da8116c7ab` |
| `backend/app/Policies/MerchantPolicy.php` | `de2be0e6063ed992168c4da429af49b559497545f2b8ef59dbadbbecaf72a525` |
| `backend/bootstrap/app.php` | `e0f4c43db52c8cd665d3da841a5a07618e6f350479cc6ae716f407cf4bac2aa8` |
| `backend/resources/views/merchants/onboarding.blade.php` | `9cc1e3dcc8cae2e1696946a6851a50a6bfa6fd656e40a676dad6d497f6b316b3` |
| `backend/routes/web.php` | `d5dd58926c4d9287bbc7b0658bcbd4bae420c2b2026d2092758beb86579002e7` |
| `backend/public/assets/xlipa-verification.css` | `663c52bc5788018cd8611606b9ee67a64654364bc364d4baf247dc216f1d54e6` |

HEAD remains `d78797cf2cceb8d4c41b4fe375cee0f8cdff9975`; staged diff is empty. Git's tracked delta includes earlier uncommitted work, so task preservation is measured against the pre-task baseline. No working data/secret hash or current target state is claimed.

**VERIFIED continuation checks:** the original 226-file baseline still shows exactly seven intended modifications, 219 unchanged and zero missing. Of the 21 source/generated pins, only the transaction store and new test file changed during final hardening; their pins above now name the tested revision. Repository and all 22 feature/report/generated-file whitespace checks passed, with normalization notices only. All three screenshot links resolve. No completed feature was restarted or replaced on continuation.

## 10. Outstanding blockers and next package

**NOT PROVISIONED / NOT TESTED operationally:** actual owner processing authorization, lawful purpose/notices/retention/data-subject procedures, authoritative NIN syntax/capacity/declaration wording, protected independent domain-key registry/custody/recovery/rotation, trustworthy time/status/restore hold, operational delivery adapter, target/IP/global quota/spend/fence facility, TLS and request-log/analytics controls, maintained reconciliation scanner/alerts and concurrency/power-loss/unknown-commit deployment evidence. The dependency's authorized/clock/key/delivery methods are trusted integration contracts, not a provisioned permission system.

Live identity/contact processing and message delivery remain independently gated even after future C activation. Synthetic mode is refused outside testing. Default authority/budget/policy/key/transport methods cannot be enabled by browser fields or an environment toggle in this package. No APP_KEY fallback, fixture-key promotion or real provider protocol was invented.

No future membership/account/contact/identity lifecycle mutation may silently revive old readiness. Current uses recheck owner/account/context and current contact; capture revokes old contact proofs. A future grant/email-change/restore/submission service must integrate the accepted invalidation and pending-obligation rules transactionally. This package provides no grant, email-change, submission/proof-consumption, identity matching, Chief approval or financial workflow.

**PROPOSED next:** owner review of the exact identity/privacy/key/delivery decisions, then a narrowly approved integration/readiness package with official provider requirements and isolated tests. Merchant submission/history should follow separately and consume exact current proofs atomically, preserve immutable evidence and enforce R06/readiness. Neither this report nor fixture proof success grants real processing, delivery or working access. OP-01/OP-02 and later working backup/restore/migration gates remain separate.

**GO for owner review of PD-03 application source and bounded fixture evidence. Working B3.2-C activation remains NO-GO. STOP FOR OWNER REVIEW.** XLipa is a WEBSITE supporting TZS only.
