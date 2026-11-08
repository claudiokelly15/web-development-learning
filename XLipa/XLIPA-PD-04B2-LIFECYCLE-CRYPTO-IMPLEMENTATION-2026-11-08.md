# XLipa PD-04B.2 — Lifecycle and Evidence Crypto Implementation

8 November 2026 · Africa/Nairobi · Isolated synthetic execution only

## Outcome

Continued the interrupted working tree without restarting it. Email verification, initial registration/profile preparation, version-aware membership revocation, internal evidence encryption and isolated tests are implemented and verified within the boundaries below. **Password reset on SE-1.0 remains deliberately BLOCKED:** its approved account lifecycle hold is retained. Existing foundation password reset remains functional in the regression suite.

Final executed evidence: **45 focused tests / 226 assertions** and **280 regression tests / 2,391 assertions**, all passed, zero failures/errors. These are disjoint selections: **325 tests / 2,617 assertions total**. Required Pint and `git diff --check` passed.

**Successful merchant submission remains blocked. Evidence encryption has no operational authorization or keys by default. Working B3.2-C and SE-1.0 activation remain NO-GO. PD-04B.3 was not begun. STOP FOR OWNER REVIEW.**

## Inspection and interrupted state

Inspected Git status/diff, backend AGENTS.md, PD-04A/PD-04B/PD-04B.1 context, lifecycle/cipher implementations, SE-1.0 definitions/manifest, isolated bootstrap/TestCase and selected test support/storage paths. `.ai/rules` was absent at workspace, XLipa and backend locations. No applicable testing-best-practices skill was available. Installed metadata: Laravel v13.35.0, PHPUnit 12.5.38, Pint v1.32.1. No dependencies or agents were added.

At resumption, Git showed 13 tracked modified files plus untracked earlier PD-04A, PD-04B.1 and PD-04B.2 work. Existing PD-04A routes/controllers/views and prior reports were treated as baseline. Lifecycle hooks, versioned notification/action, cipher, disabled environment, test fake and two test classes were already present.

Incomplete work found:

- The SE-1.0 source had an unfinished reset exception in `se1_account_lifecycle_hold`, while its manifest still pinned the prior source. Lifecycle setup failed closed with `SE1 source manifest mismatch`.
- New tests expected successful SE-1.0 reset despite the previously documented hold.
- The registration test expected the verification-notice redirect; existing behavior deliberately leaves the registrant a guest and redirects to login.
- Final verification and implementation reporting were missing.

Only genuine corrections were made. Existing implementation was retained. No security guard was removed to obtain passing tests.

## Lifecycle behavior and compatibility

`LifecycleMutation` detects either epoch column or the evidence table. Any partial/unready SE profile returns 503 instead of falling back to unversioned writes. Complete SE operations use the existing SQLite writer reservation before workflow reads; legacy profiles retain a database transaction. Version increments reject nonpositive values and integer overflow.

Email verification uses the existing signed route with a signed `binding_version`. The action rechecks active account, actor auth version, exact email and expected epoch under the writer; verification advances the email epoch once. Repeated current verification is a no-op. Stale links and email A→B→A links fail, even after the original address returns. The ABA email edits in tests are explicit SQL fixture mutations, not a newly exposed email-change service. No email-change route was added.

Registration inserts the normalized business phone with the initial merchant row, preserving merchant/application profile version 1 and membership authority version 1. It sends the versioned notification on SE-1.0, preserves the legacy notification elsewhere, and does not log the registrant in. Unversioned profile content edits remain denied.

Membership revocation requires current active owner authority, current actor auth version, tenant-scoped membership and explicit expected authority epoch on SE-1.0. It increments once, revokes applicable available/consumed proofs and active challenges, and writes bounded audit metadata atomically. A repeated current revocation is a no-op; stale epochs, foreign authority and last-owner removal fail. Injected audit failure rolls back membership epoch and proof revocation. No grant/regrant/downgrade service was introduced. SQL epoch ABA enforcement remains covered by the SE schema tests.

**Reset compatibility decision:** the interrupted reset exception relaxed the documented SE-1.0 hold. It was replaced with unconditional denial of account-status/password/auth-version changes, matching the approved hold behavior. The controller now returns controlled 503 on SE-1.0 before token consumption or credential mutation. Tests assert unchanged account bytes, proof bytes, token count and zero transaction level, and also deny a raw password/token/auth-version reset tuple. Foundation reset still exercises success, single-use token and session invalidation through AuthenticationTest. Successful reset and proof invalidation on SE-1.0 are **not implemented or claimed**; they require a separately reviewed schema/lifecycle amendment.

SE-1.0 columns, evidence representation, 31-object catalogue, deferred bundle constraints, epoch/profile guards and working-disabled selector remain. The complete 46-case schema suite passes inside regression, including immutable history, raw SQL denials, epoch ABA, ledger gaps, partial installation, legacy row preservation and contention. Frozen C definitions remain checked by the composed readiness path.

The SE schema PHP source is **not byte-identical to the prior PD-04B.1 report pin**. The hold correction and required formatting produced SHA-256 `1fde336da8709ea7d052fe4fdb41262e47cea4aad5cd505e32d26c0ee769ee61`; the isolated-only SE manifest was explicitly repinned to this source. This is a reviewable source revision, not operational activation approval. C pins, SE migration artifact and source-pin verification logic were retained. No manifest bypass was added.

## Evidence cipher and operational denial

The internal cipher uses sodium XChaCha20-Poly1305 with a fresh random 24-byte nonce, canonical bounded JSON and length-prefixed AAD binding namespace, algorithm, key references and all 26 nonciphertext evidence fields. Dedicated binding-key HMACs commit to identity/contact logical content and tenant/actor/record/version context. The capsule contains normalized email/verification time, business name, TZ/TZS and content MACs; it does not copy NIN, phone or OTP.

Validation rejects extra payload/envelope fields, noncanonical encodings, invalid dates, unsupported algorithms, malformed base64url, tampering, wrong keys and substituted context. Both dedicated keys must be 32 bytes and distinct from one another, all four verification key domains and every configured APP_KEY generation. Encryption requires current key IDs; historical decryption requires separately available authorized historical keys. Restore, time and retention admission fail closed. Errors are generic; sensitive input parameters are marked sensitive.

The 35 cipher cases cover random nonces, round trip, independently constructed sodium envelope interoperability, private-value absence, changed-content MAC mismatch, 18 context substitutions, eight malformed/tampered envelope cases, six key-alias domains, canonical payload rejection and default/restore/time/retention/retired-key denial. They do not constitute an external cryptographic audit or proof of real custody, provider provenance, legal retention policy or operational recovery.

`SubmissionEvidenceEnvironment::authorized()` and `restoreReady()` return false; retention permission is false and time/key resolvers throw. Runtime source binds no test fake and imports no test custody. The cipher is not called by a submission success path and has no public decrypt route/accessor/job. No real key was loaded or configured.

`MerchantApplicationSubmission::submit(): never` still ends in unconditional denial. Its evidence blocker remains present even when fixture verification readiness exists. Regression verifies denial without submission/proof/audit success mutations. No payment or eligibility gate was enabled.

## Executed commands and exact results

From `XLipa/backend`, using `C:/Users/hp/.config/herd/bin/php85/php.exe`, with `-d auto_prepend_file=`:

```text
vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'SubmissionLifecycleTest|SubmissionEvidenceCipherTest'
vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter 'SubmissionEvidenceSchemaTest|MerchantApplicationSubmissionTest|MerchantVerificationTest|AdminWorkspaceTest|MerchantDashboardTest|MerchantAccessTest|AuthenticationTest|TrustedHostsTest|DatabaseFoundationTest'
vendor/bin/pint --dirty --format agent
```

| Final selection | Tests | Assertions | Runner duration | Exit |
|---|---:|---:|---:|---:|
| Lifecycle + evidence cipher | 45 | 226 | 3,316 ms | 0 |
| Vetted nine-class regression | 280 | 2,391 | 32,546 ms | 0 |
| Final Pint | — | — | — | 0, passed without changes |
| Git whitespace check | — | — | — | 0 |

Regression covers PD-01 merchant dashboard/access, PD-02 admin workspace, PD-03 verification, PD-04A blocked submission, PD-04B.1 schema, authentication, trusted hosts and foundation behavior. No unrestricted suite was run. Visual export flags were explicitly set to 0 for regression. No new browser/visual verification is claimed.

The inspected PHPUnit bootstrap fixes synthetic APP_KEY, memory SQLite, array mail/cache/session, null logs and sentinel dotenv/cache paths. TestCase rejects nonmemory/URL configurations and blocks stray Laravel HTTP. Selected contention cases export only generated memory fixtures to checked system-temp paths, then clean their own files/sidecars. Test migration calls operate within these fixtures, never against working databases.

Development evidence: initial focused run was 46 cases, 35 passed, 136 assertions, 11 source-pin setup errors. During correction an incorrectly escaped manifest replacement caused 10 JSON setup errors in a 45-case run (35 passed, 136 assertions); that edit was corrected. The next run had 44/45 passed, 217 assertions, with the registration redirect assertion failing. Final corrections preserve the original guest redirect and approved reset hold. Two success-reset-oriented cases were replaced with one explicit reset-hold case; this reduction is disclosed, not represented as successful reset coverage. Initial unprivileged PHP lookup failed; the existing binary subsequently ran with reviewed command access. No automatic approval rejection occurred.

## Changed files and preservation

Paths below are relative to `XLipa/`. PD-04B.2 files already present at resumption, retained unless stated:

| Files | Purpose |
|---|---|
| `backend/app/Actions/Auth/RegisterMerchantOwner.php` | Writer integration and atomic initial profile/contact |
| `backend/app/Actions/Merchants/CreateMerchant.php` | Initial optional bounded business phone |
| `backend/app/Actions/Merchants/RevokeMembership.php` | Versioned revocation, authorization and invalidation |
| `backend/app/Http/Controllers/Auth/EmailVerificationController.php` | Signed epoch admission |
| `backend/app/Http/Controllers/Auth/PasswordResetController.php` | Profile detection, controlled SE hold and legacy CAS reset |
| `backend/app/Models/User.php` | Profile-aware notification |
| `backend/app/Actions/Auth/VerifyAccountEmail.php` | Current-state verification CAS |
| `backend/app/Actions/Merchants/LifecycleMutation.php` | Writer, readiness, increment and invalidation helper |
| `backend/app/Actions/Merchants/SubmissionEvidenceCipher.php` | Internal contextual AEAD and commitments |
| `backend/app/Actions/Merchants/SubmissionEvidenceEnvironment.php` | Default-disabled custody/admission boundary |
| `backend/app/Notifications/VersionedVerifyEmail.php` | Existing route plus signed epoch |
| `backend/tests/Feature/SubmissionLifecycleTest.php` | Final 10 lifecycle cases |
| `backend/tests/Feature/SubmissionEvidenceCipherTest.php` | 35 cipher cases |
| `backend/tests/Support/FakeSubmissionEvidenceEnvironment.php` | Disposable test-only custody |
| `backend/app/Actions/Merchants/SubmissionEvidenceSchema.php` | Restored unconditional lifecycle hold and formatting |
| `backend/database/activation/se1/manifest.json` | Explicit revised source pin; isolated-only mode retained |

This resumption changed only **four pre-existing source/test/manifest files**: PasswordResetController, SubmissionLifecycleTest, SubmissionEvidenceSchema and the SE manifest. This report is the only new file. A bounded pre-edit SHA-256 inventory covered **160 existing PHP/JSON/source files** under app/bootstrap/routes/resources/tests and activation/migration directories: **156 unchanged, four intended changes, zero missing**. The inventory excludes working databases, sidecars, dotenv, secrets, dependency/storage trees and protected metadata; it is not a whole-workspace certificate. Earlier PD-04A routes/controllers/views and existing functionality were preserved within this scope. Prior reports were not rewritten.

Frozen B3.2-C file hashes remain:

| File under `backend/database/activation/b32c/` | SHA-256 |
|---|---|
| `2026_11_07_200000_activate_b32_security_contract.php` | `be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97` |
| `security-contract.json` | `09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0` |
| `selection-manifest.json` | `20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873` |

HEAD remains `e2e746a60580d5d4adbf50511a45b3084c38587c`; staged diff is empty. No commit, push or deployment occurred.

## Remaining blockers and owner disposition

- Review the corrected SE source pin and explicit password-reset hold. Successful SE reset/account-status/session lifecycle integration remains unfinished by design; no operational compatibility claim is made for it.
- Real custody, approved operational keys, namespace, retention, trusted time/restore admission, lawful identity-processing policy and authenticated provider lineage remain unavailable.
- Successful submission, idempotent receipt/unknown-outcome handling, operational migration/recovery, review decisions and payments remain blocked. No PD-04B.3 work was started.
- No working SQLite or sidecar was opened, read, copied, hashed or modified. No real dotenv or operational secret was accessed. No working migration, real identity processing, OTP/email delivery or payment was activated.

**Ready for owner review of this bounded implementation and its documented reset blocker. Working B3.2-C and SE-1.0 activation remain NO-GO. STOP FOR OWNER REVIEW.**
