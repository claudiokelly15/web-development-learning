# XLipa — B3.2-B Synthetic Implementation Report

**Updated 7 November 2026 · Africa/Nairobi · ULID correction complete within isolated test scope · SI PRODUCTION-READY**

## 1. Executive summary — Muhtasari

**B32-ID-CASE-1 RESOLVED AND TESTED:** marekebisho yaliyoidhinishwa ya ULID yamekamilika. Existing merchant/application/audit IDs hazijabadilishwa. Database storage na composite foreign keys zinabaki binary; uppercase normalization hufanyika kwenye nakala ya ULID wakati wa encoding ya cryptographic AAD pekee, baada ya structural validation. Hakuna NOCASE collation.

**Matokeo ya mwisho: tests 135/135 passed, assertions 1,177, failures 0, errors 0.** Hizi zinajumuisha B3.2-B tests 27/assertions 330 na existing regression tests 108/assertions 847. Full combined run ilifanyika baada ya correction, new cases na Pint. Hakuna working database iliyotumika.

Hii ni completion ya correction na suite ya synthetic iliyopo, si production certification au ruhusa ya B3.2-C. Broader live lifecycle/security/operational readiness haijathibitishwa na tests hizi.

Historical run ya 6 November: regression 108/847 ilipita lakini B3.2 suite ilikuwa tests 22, passed 2, errors 20, assertions 26. Blocker ilikuwa uppercase-only binding/storage contract dhidi ya Laravel HasUlids lowercase IDs. Owner ameidhinisha correction tarehe 7 November; matokeo hayo ya awali hayajafichwa wala kutumika kama matokeo ya sasa.

## 2. Owner authorization boundary — Mipaka ya ruhusa

Owner authorization ya sasa inaruhusu kusahihisha specification, draft B3.2-B implementation na fixtures/tests ili kuhifadhi existing ID bytes, kuruhusu valid lowercase parent refs, na kutumia uppercase ULIDs kwenye AAD pekee. Lowercase/uppercase/mixed-case za ULID ileile ni AAD-equivalent; si database-identity-equivalent.

Encryption domains, OTP six digits/five minutes/five guesses/60-second resend/ten failures in strict rolling 30 minutes, proof ya dakika 30/no grace/single use na seven-table scope hazijabadilishwa. Working SQLite, secrets, applied migrations, frontend, live SMS/email, real NIN collection, Selcom/payments, public endpoints, production activation na B3.2-C bado haziruhusiwi. Hakuna commit/push/deploy.

## 3. Inspected source evidence — Ushahidi uliokaguliwa

- Git root `C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT`; project `XLipa`; HEAD unchanged `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`.
- Current SBR specification, previous implementation report, draft seven-table/audit migrations, crypto boundary, test coordinator/bootstrap/base class, synthetic services/worker, feature/unit cases and backend AGENTS.md reviewed. No `.ai/rules` directory at workspace/project/backend paths checked.
- Installed framework `HasUlids::newUniqueId()` explicitly returns lowercase. Merchant/MerchantApplication use it; existing parent migrations do not mandate uppercase. No vendor or existing model edited.
- Framework installed manifest: Laravel v13.35.0. Read-only PHP capability check: PHP 8.5.12, Sodium available, SQLite 3.53.4. No dependencies installed or updated.
- All ULID uses in the seven-table draft reviewed: seven merchant_id fields, consumed_application_id, all new independent PKs and identity/contact/challenge links. Existing audit identifiers/references are not normalized. Composite FK definitions and default binary collation remain intact.
- Test isolation rechecked from source before execution: tests/bootstrap-isolated.php and TestCase disable real environment-file loading, enforce testing/memory SQLite with empty DB_URL and array mail, use synthetic fixture key and prevent stray Laravel HTTP calls. No real provider adapter or worker is registered.

## 4. Exact files created — Faili mpya

**No new files in the 7 November correction.** Existing draft files from 6 November retained:

1. backend/database/migrations/2026_11_06_100000_create_synthetic_verification_contract.php
2. backend/database/migrations/2026_11_06_100001_extend_synthetic_audit_contract.php
3. backend/tests/bootstrap-isolated.php
4. backend/tests/Support/SyntheticKeys.php
5. backend/tests/Support/SensitiveFixtureValue.php
6. backend/tests/Support/SyntheticCipher.php
7. backend/tests/Support/SyntheticCoordinator.php
8. backend/tests/Support/SyntheticVerification.php
9. backend/tests/Support/concurrent-worker.php
10. backend/tests/Feature/B32SyntheticContractTest.php
11. backend/tests/Unit/B32SyntheticCipherTest.php
12. This existing implementation report.

Paths are relative to XLipa. Existing security specification was already present. No replacement report or extra table artifact was added.

## 5. Exact files modified — Correction delta

| File relative to XLipa | Change in this correction |
|---|---|
| XLIPA-B3-2-SECURITY-BLOCKERS-RESOLUTION-2026-11-06.md | SBR-1.1 amendment; sections 3.2 and 8.1 distinguish binary storage and AAD-only canonicalization. |
| XLIPA-B3-2-B-SYNTHETIC-IMPLEMENTATION-REPORT-2026-11-06.md | Updated this report with authorization, actual tests, file delta and preservation evidence. |
| backend/database/migrations/2026_11_06_100000_create_synthetic_verification_contract.php | Only parent-ref ULID alphabet expanded to valid upper/lowercase for merchant_id and consumed_application_id; new record IDs/links stay uppercase. This is the existing unexecuted-on-working-DB draft, not an applied migration. Pint formatting. |
| backend/tests/Support/SyntheticCipher.php | Validate case-insensitive ASCII ULID structure, uppercase local AAD copy for both encrypt/decrypt, preserve caller/database representation. Pint formatting. |
| backend/tests/Feature/B32SyntheticContractTest.php | Three explicit compatibility/isolation/preservation tests added; original 20 scenarios retained. Pint formatting. |
| backend/tests/Unit/B32SyntheticCipherTest.php | Two explicit AAD-case/invalid-ULID tests added; existing two retained. Pint formatting. |
| backend/tests/Support/concurrent-worker.php | Required Pint formatting only. |
| backend/tests/Support/SyntheticKeys.php | Required Pint formatting only. |
| backend/tests/Support/SyntheticVerification.php | Required Pint formatting only, no OTP/proof policy or behavior change. |
| backend/tests/TestCase.php | Required Pint imports/formatting only; isolation behavior unchanged from correction baseline. |

Existing changes from the previous B3.2-B draft remain in AuditEvent.php, phpunit.xml and DatabaseFoundationTest.php; **these three files were unchanged in this correction**. Audit migration, SyntheticCoordinator, SensitiveFixtureValue and bootstrap-isolated.php also unchanged from correction baseline. No existing applied migration, frontend, route, vendor dependency or production action changed.

## 6. Seven-table implementation status

| Table | Correction/verified synthetic coverage |
|---|---|
| merchant_identity_records | merchant_id now accepts valid existing lowercase parent representation; content/envelope/state contracts retained; rotation/immutability exercised. |
| merchant_contact_versions | Lowercase parent fixture now inserts using exact parent bytes; invalid mutations/current uniqueness/retirement tested. |
| merchant_onboarding_contexts | merchant_id PK/FK preserves parent case; nullable tuple, current-pointer checks and foreign-tenant contact rejection tested. |
| phone_otp_challenges | Parent compatibility corrected; code expiry, five guesses, resend/replay and separate-process races exercised. |
| otp_delivery_intents | Parent compatibility corrected; idempotency/fake rejection/unknown/duplicate outcome cases exercised. |
| contact_verification_proofs | merchant_id and consumed_application_id preserve existing lowercase parent keys; thirty-minute boundary/single use/revocation/history, case-altered/foreign application rejection tested. |
| verification_limit_state | Parent compatibility corrected; strict rolling boundary, cooldown, snapshot/restart persistence and synthetic concurrency paths exercised. |

Exactly seven new persistent business tables; no eighth scope table. New independent IDs and their references retain uppercase constraints. Migration up, direct down/reapply and populated old-row/index preservation tests now pass. “Passed” is limited to actual assertions in the suite, not proof that every conceivable raw-SQL attack/state edge is covered.

## 7. Supporting code and exact specification correction

SBR-1.1 records owner approval for this correction. Section 3.2: validate each ULID as 26 ASCII Crockford characters, first 0–7; reject I/L/O/U, invalid length/whitespace/non-ASCII/overflow. Uppercase a local copy immediately before LP encoding. Encryption/decryption share the same aad() function. No caller binding, DB value or FK is normalized.

Section 8.1: merchant_id in every candidate table (including context PK) and consumed_application_id accept valid upper/lower/mixed-case ULID shape and must match the actual parent bytes under binary identity. New-record PKs, current_identity_id/current_contact_id/contact_id/challenge_id retain uppercase parent representation. No collation/index/parent-ID rewrite. The helper's case-compatible validation applies only to the two documented parent-reference column names, not every new ULID column.

Case-only AAD equivalence is deliberate and tested. An AAD-only successful decrypt is not an authorization grant: authoritative scoped lookup still uses exact stored tenant/actor/contact identity. The feature test decrypts with a case variant, then proves that the same altered merchant cannot issue a challenge or insert a foreign-key row. A different merchant ULID still fails AEAD authentication.

Test-only services remain under tests/, unregistered in routes/jobs/providers. Public deterministic fixture keys and fixed synthetic code are not production keys/code generation. Sensitive serialization remains blocked/redacted. AAD normalization does not change OTP HMAC/contact-fingerprint scope encoding, which uses authoritative stored identifiers and distinct domain prefixes. No rate-window reset or key policy change.

## 8. Audit compatibility changes

**No audit migration/enum contract change in this correction.** The prior additive eight-event slice is retained. In isolated memory SQLite the existing suite now successfully exercises old/new event enum round-trips, unknown-event rejection, metadata redaction, audit-write failure rollback, original-row/index preservation through down/reapply and refusal to downgrade after new history.

Audit migration retains FK-on transactional replacement and unexpected dependency guards. No working audit table opened/reconstructed and no old event/ID rewritten. Tested failure injection is the suite's existing replacement-table collision and audit insertion failure; it is not exhaustive failure coverage at every reconstruction stage. Production/working rebuild is not authorized.

## 9. Synthetic test database identification

Main migrations and regression tests use SQLite **:memory:**, with synthetic factory records created independently. Real .env file loading disabled, effective DB_URL empty, mail array, queue/log null. No external provider calls.

Separate PHP-process race and restart/restore tests now executed against guarded temporary paths matching:

`C:\Users\hp\AppData\Local\Temp\xlipa-b32-synthetic-<random hex>.sqlite`

Names are randomly allocated at runtime and removed in test cleanup. Temporary files are built solely from that test's synthetic in-memory schema/rows, never working records. The tests exercise concurrent correct guesses, fifth-failure race, resends and proof consumption. Successful cleanup leaves no retained database artifact or replayable live proof. No claim that this is a working-database backup/recovery drill.

## 10. Commands executed — Amri

Read-only: Git root/HEAD/status/diff, source/rule/manifest reads, ULID/FK inventory with rg, pre/post SHA-256 inventories. No working SQLite SQL query or migration status command.

Explicit PHP runtime:

`C:\Users\hp\.config\herd\bin\php85\php.exe`

Commands from XLipa/backend:

```text
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter B32Synthetic
<php85> vendor/bin/pint --dirty --format agent
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter B32Synthetic
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result
```

The first filtered run contained the existing 22 cases; the second included all five new compatibility cases after formatting. Full run is final combined acceptance evidence. Runtime capability check via standard input printed only versions/Sodium capability. PHP/Pint/PHPUnit access required sandbox escalation and was allowed; no automatic approval rejection. No env edits, dependency installation, standalone working Artisan migration, account creation, real send, commit/push/deploy.

## 11. Tests executed — Actual pass/fail counts

| Run on 7 November | Actual result |
|---|---|
| Initial corrected existing B3.2 suite | **PASS 22/22**, assertions 232, duration 43,429 ms. |
| Pint | **PASS / formatted eight PHP files**, including previously pending draft formatting. |
| Complete B3.2 suite including new ULID cases, after Pint | **PASS 27/27**, assertions 330, duration 10,259 ms. |
| Final full suite: B3.2 + existing regressions | **PASS 135/135**, assertions 1,177, duration 48,751 ms. **Failures 0, errors 0.** |

Existing regression portion remains 108 tests/847 assertions (135 minus 27 and 1,177 minus 330), now rerun as part of final full suite. No planned assertions counted as passes. No further test failure or material specification conflict appeared in these runs.

New cases explicitly cover:

- Lowercase Laravel-generated merchant/application IDs preserved byte-for-byte through challenge/proof/application FK operations, and original audit row unchanged.
- Uppercase/lowercase/mixed-case valid ULIDs encrypt/decrypt interchangeably at AAD layer only; original binding arrays unchanged.
- Invalid alphabet/length/first-character/whitespace/non-ASCII ULIDs rejected for both merchant and record on encryption/decryption.
- Case-only merchant tampering rejected by scoped authority lookup and binary FK insertion despite AAD equivalence; case-altered application FK rejected with proof still available after rollback.
- A second actual synthetic merchant cannot adopt another tenant's contact/proof/application relation or decrypt its ciphertext.

Original feature scenarios for FK/type/state/immutability, strict rolling boundaries, cooldown/idempotency, code/proof expiry/replay, revocation/history, audit compatibility/failure rollback, migration down/reapply, separate-process races and synthetic restore/restart also executed successfully.

## 12. Security findings — Usalama

**B32-ID-CASE-1: RESOLVED within approved correction scope.** No parent rewriting, NOCASE workaround, binary-identity bypass or unreviewed OTP/proof threshold change.

Valid case-only AAD changes intentionally authenticate as the same ULID; they do not hide a different merchant because full validated ULID content remains bound. Binding must always be constructed from authoritative scoped rows; callers cannot treat crypto normalization as permission. Tests demonstrate the distinction rather than claiming case-only differences are cryptographic tampering failures.

No new material specification conflict or unsafe migration requirement was encountered. All raw database constraints not affected by the approved parent alphabet correction remain as drafted. No production security certification is asserted. Model/audit guards, cryptography and trusted-time/authority checks remain different controls.

## 13. Remaining limitations and blockers

- No blocker remains for the **ULID compatibility correction or the existing 27-case synthetic suite**.
- Synthetic test helpers are not complete production lifecycle services. Provider timeouts/lease reconciliation, full state-edge/failure-injection matrix, key-epoch drain, clock-health enforcement, operational retention/recovery and production load need further stage-specific evidence. Passing this suite does not certify those unimplemented operational paths.
- Production key custody/recovery, lawful real-NIN collection and issuer syntax evidence, real-phone normalization/delivery, wider send/IP/actor/target/global quotas and cost ceilings remain separate future gates.
- Working SQLite activation, live messaging/real identity data and B3.2-C remain explicitly prohibited. No public endpoints, mobile application or payment processing introduced; XLipa remains a web-based payment gateway website.
- No testing-best-practices skill was available in the provided catalog/local skill search; existing project PHPUnit instructions and authorized isolation rules were followed without installing skills.

## 14. Working database and protected-file preservation

Before this correction, **201 unique existing files** were inventoried and SHA-256 hashed in memory: Git tracked/nonignored-untracked workspace files plus explicit .env variants and working database files. Baseline includes prior draft changes; they were not discarded or mistaken for new edits. No baseline file was created on disk.

Final correction delta: **10 existing files changed**, exactly section 5; **191 unchanged, zero missing, zero new files**. Applied migrations (original 0001_* and 2026_11_06_00000*), frontend, existing production identity/model code, unrelated project files and working SQLite match this correction baseline. Formatting-only changes are explicitly listed. Ignored dependency/cache/recovery trees are not claimed exhaustively inventoried.

Working SQLite SHA-256 before/after:

`214AE83A31F53E80D3068D1AEB1366008BB5ACCB2DF212DD2C534AE4121B6D8F`

Working database was hashed only, never used as a fixture/template. Parent-ID preservation additionally tested on independent populated synthetic records. The post-report hash comparison is checked again before delivery; only report text records its results.

## 15. .env and secret preservation

.env and .env.example match baseline full-file hashes; no .env.testing created/modified. Real APP_KEY and credentials unchanged and not printed. In-process fixture APP_KEY/Sodium keys are public deterministic test material only. No production key generated, no provider secret/account used, no real NIN/phone captured or sent.

## 16. Git status and HEAD

HEAD unchanged `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`; no staging/commit/push/deployment. Four tracked modified paths remain from prior draft: AuditEvent.php, phpunit.xml, DatabaseFoundationTest.php, TestCase.php. Only TestCase.php receives formatting changes in this correction; other substantive correction files were already untracked draft artifacts at start. Untracked file inventory is unchanged; no unrelated project modified. `git diff --check` passed.

## 17. GO/NO-GO — Hatua inayofuata

| Area | Decision |
|---|---|
| ULID correction and complete existing isolated synthetic suite | **GO for owner review:** implemented and tested, 135 combined tests passing. |
| Claim all future production/security behavior is complete | **NO-GO:** outside synthetic evidence and bounded scope. |
| Proceed automatically to B3.2-C or working DB activation | **NO-GO:** separate explicit owner authorization required. |
| Real NIN/contact collection, live SMS/email, payments/production | **NO-GO:** separate technical/legal/provider/operational gates. |

**Nimesimama baada ya correction na tests zilizoidhinishwa.** Specification na ripoti zimesasishwa; working data/secrets/frontend zimehifadhiwa. Owner review ndiyo hatua inayofuata, bila kuanza B3.2-C.