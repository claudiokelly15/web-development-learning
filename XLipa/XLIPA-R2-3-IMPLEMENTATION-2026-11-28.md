# XLipa R2.3 — Merchant Onboarding, Submission and Chief Decision Integration

Completed 28 November 2026 · Africa/Nairobi · Owner directive and controlled amendments authorized 27 November 2026

**R2.3 STATUS: PASS within the fictional, disposable ordinary-web integration scope described below. Operational identity, SMS, financial activation, Selcom and deployment remain NO-GO. R2.4 has not started. STOP FOR OWNER REVIEW.**

## Result and repository state

The same fictional merchant can now access the initial dashboard without OTP or identity prerequisites, complete a business name, record an encrypted fictional contact, request and verify a locally captured OTP, record an encrypted synthetic identity declaration, and submit through the existing `ApplicationSubmissionService`. A separate Chief can inspect the persisted application and execute the existing `ApplicationDecisionService` with separately issued fictional assurance. Ordinary merchant pages show the persisted approval or rejection. These decisions do not change the merchant's operational status or grant financial authority.

Account creation in the demonstrated journey uses controlled fictional CLI provisioning through the existing `RegisterMerchantOwner` and `CreateMerchant` services. Ordinary login, signed version-bound email verification, dashboard, verification, completion, submission, review, decisions and logout use actual Laravel HTTP execution. **Browser POST `/register` remains disabled in staging; this report does not claim fully integrated browser registration.** The directive explicitly permits this disclosed controlled-provisioning alternative.

Continued the existing interrupted implementation; no earlier milestone was restarted. Branch remains `main`, HEAD `12dddbe2cd59949956a82d24d7b5c294f6cfc036`. Existing uncommitted/untracked R2.1/R2.2 work, reports, recovery material and accepted exports were retained. No commit, push, dependency update or deployment occurred. Applicable backend instructions were followed; `.ai/rules` and an available `testing-best-practices` skill were absent. Existing PHP 8.5.13, Laravel 13.35.0, PHPUnit 12.5.38, Pint 1.32.1, Node and installed Chrome were reused.

## Integration and exact changed files

Paths below are relative to `XLipa/`. The protected sources/manifests are listed separately in the amendment table.

| New file | Purpose |
| --- | --- |
| `backend/app/Staging/WorkflowProvisioner.php` | Signed custodian-only, atomic versioned workflow composition |
| `backend/app/Staging/StagingWorkflowAuthority.php` | Independent signed schema/runtime and current command verification |
| `backend/app/Staging/StagingWorkflowMiddleware.php` | Exact action/resource admission and command claiming |
| `backend/app/Staging/StagingCommandState.php` | Durable dispatch/outcome quarantine across the independent admission/workflow boundary |
| `backend/app/Staging/StagingBusinessNameAdmission.php` | Existing completion/resolution contract adapter |
| `backend/app/Staging/StagingBusinessNameController.php` | Ordinary completion and resolution endpoints |
| `backend/app/Staging/StagingVerificationEnvironment.php` | Dedicated fictional keys, synthetic-only syntax and private local OTP capture |
| `backend/app/Staging/StagingEvidenceEnvironment.php` | Separate fictional evidence/binding custody |
| `backend/app/Staging/StagingSubmissionAdmission.php` | Exact submission command and independently signed proof-lineage attestation |
| `backend/app/Staging/StagingDecisionAdmission.php` | Exact decision scope and separate short-lived Chief assurance |
| `backend/app/Staging/StagingApplicationController.php` | Ordinary submission/resolution and Chief decision adapters |
| `backend/resources/views/merchants/staging-completion.blade.php` | Existing design patterns for completion/resolution |
| `backend/resources/views/merchants/staging-application.blade.php` | Persisted fictional application status and submission form |
| `backend/resources/views/admin/staging-decision.blade.php` | Explicit fictional decision form |
| `backend/tests/Feature/StagingWorkflowSchemaTest.php` | Composition, failure, target and authority tests |
| `backend/tests/Feature/StagingFictionalEvidenceTest.php` | Original live DDL preservation, scope/signature/replay/revocation and quarantine tests |
| `backend/tests/Support/submission-evidence-live-v1-hashes.json` | Independent object hashes from preserved pre-amendment live schema source |
| `scripts/Test-Onboarding.cjs` | Actual HTTP approval/rejection, resolution and denial acceptance |
| `scripts/Test-Onboarding-Browser.cjs` | Owned headless Chrome/CDP authenticated responsive acceptance |

Modified existing staging files: `StagingAuthenticationReady.php`, `StagingBoundary.php`, `StagingCapability.php`, `StagingIdentity.php`, `StagingRuntime.php`, `StagingServiceProvider.php` under `backend/app/Staging/`; `scripts/staging-control.php`; and `scripts/staging.ps1`. The launcher adds explicit `-Profile onboarding`, retaining `foundation` as its default.

Modified views: `backend/resources/views/merchants/onboarding.blade.php`, `merchants/applications.blade.php`, `merchants/verification.blade.php`, and `admin/application.blade.php`. Their staging-only links/identity wording connect the integrated routes while preserving the ordinary operational-unavailable paths and premium layouts. No homepage, branding, existing CSS, dependency, operational bootstrap or default service-provider binding was changed. The `.staging/` ignore entry and other R2.1/R2.2 additions predate R2.3 and remain preserved.

Reused domain authorities include `BusinessNameCompletionService`, `BusinessNameCompletionResolver`, `MerchantVerification`, its cipher/store/audit reconciler, `ApplicationSubmissionService`, `ApplicationDecisionService`, `SubmissionTransaction`, `WorkflowSchemaReadiness`, `ComposedSubmissionSchema`, `LifecycleMutation`, ordinary authentication/email controllers, `AdminApplicationController`, membership resolution and existing Gate policies. No second submission, OTP, ledger or decision engine was created.

## Reviewed workflow profile and controlled amendment

The distinct physical staging profile is **`xlipa-onboarding-fictional-v1`**. Its selected workflow catalogue retains the reviewed foundation, C, SE and BNC objects and the exact **12-entry migration ledger**, in batches 1–5. The existing logical BNC readiness capability is retained for service compatibility; its fictional physical variant is selected only after independent signed local custody verification. An environment/configuration label cannot select it.

The custodian signs a purpose-specific workflow-provisioning document before DDL, binding the fixed disposable root/store, namespace, source release, runtime, generation, expiry and SQLite headers. Per-operation capabilities additionally bind actor, merchant, action, resource, authorization version and command where required. Versioned membership/email expectations and final policies remain authoritative. A durable exclusive-create provisioning claim precedes one reserved-writer transaction. Exact schema/ledger/integrity postflight must succeed before the ready runtime manifest is published. Partial or interrupted generations fail closed and are retired rather than repaired or silently reused. Foundation migrations and their original exact checker remain intact. Workflow metadata stays outside the workflow catalogue so extra marker tables cannot weaken exact readiness.

Two separate narrow owner approvals authorized the evidence-admission amendment and the distinct fictional schema variant. The original live trigger is **not** removed or weakened. `SubmissionEvidenceSchema::objects()` still returns byte-identical original live DDL, independently tested against hashes captured from preserved source `1fde336da8709ea7d052fe4fdb41262e47cea4aad5cd505e32d26c0ee769ee61`. The fictional variant changes only `se1_evidence_insert`, requiring simulated provenance consistently across proof, challenge and accepted delivery-intent lineage. All other object definitions remain equal. Existing FKs, tuple bindings, chronology, authority, audit, proof consumption and immutable-history checks remain in force.

Live service checks retain their live path. The additional simulated path requires the signed current local runtime and scoped command; outside that boundary it is rejected. The original default operational admissions remain denied. Existing tests specifically rejecting simulated lineage with injected live-mode/issuer claims were preserved and passed. The inherited `sms` channel token is a schema classification; **simulated provenance/local capture is never genuine SMS possession or identity verification**.

Historical protected files are retained in `.recovery/r23-protected-before/`. Only the approved semantic sources and necessary transitive pin constants/manifests changed. `BusinessNameCompletionContext.php` changed only its integration digest. The verifier changed only its three affected manifest digests. Complete original/replacement SHA-256 values follow; paths are relative to `backend/` except the final verifier row.

| Protected file | Original SHA-256 | Replacement SHA-256 |
| --- | --- | --- |
| `app/Actions/Merchants/VerificationReadiness.php` | `66730dad1bf9c09b73169f13c496d6c76c0fb82e4758c1b1df4f52d75b7728f5` | `8823a951281e4f5f56973cd2fbfba5f896bd97e0bfc1007223c8297d059e9664` |
| `app/Actions/Merchants/ApplicationSubmissionService.php` | `b37821531cd0447bece877daca388b4e076d6bbb3e08f62456108211735ffe8b` | `2f2211ed7f76ebe609f2a8468df0bc0377a23d5ff806fb92c3f2173139f2724d` |
| `app/Actions/Merchants/ApplicationDecisionService.php` | `f72de084fa43588ea76128cbb43a75308d79ab5b05a8e634d618e5cdcc419fbd` | `413bcb1e939679cef923e872757e7b0931d1a42698e19c28079f8dc29a375640` |
| `app/Actions/Merchants/WorkflowSchemaReadiness.php` | `b5a1eb748d7512a2537d6200a786fbeab1000d4ff4df0bf645990958951af7f7` | `9399ca6f4d134594829be93ed30f61e44b81566cc094cbf3a896d5fe53045401` |
| `app/Actions/Merchants/ComposedSubmissionSchema.php` | `5320c1d623873d0d8b0da525c2074c56b74e5b563cf0d464690d82f1b91a2b76` | `da890c5d79692b41c15b7302f5619b45d2873e211bf54a5d397579ae81f81a4e` |
| `app/Actions/Merchants/SubmissionEvidenceSchema.php` | `1fde336da8709ea7d052fe4fdb41262e47cea4aad5cd505e32d26c0ee769ee61` | `8d43aae3a98ecdf08ac11e9651567e36d807cae3f7dededa4602f63d6775656d` |
| `app/Actions/Merchants/BusinessNameCompletionContext.php` | `e82d1bff179f3f9bfd018d52d8cf732d50b60b190daca719da1e73d191923328` | `7be334a4eda35ade4db8ebc17effdcf3ab871467851ca29976792e2ff04f28a9` |
| `database/activation/bnc1/manifest.json` | `2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3` | `f8c071e83f9e8568c00b79c176a55c59f63ff51abcaa89f098d43d1fb2746747` |
| `database/activation/bnc1/integration-manifest.json` | `f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a` | `69c6d7967ce4bd004e283b5800a5c930a267b9fab5e57b0228e28e6ad6079b40` |
| `database/activation/bnc1/backend-manifest.json` | `423d7ec0c2242aa42eb6e33c02036096c12fc3ff5a3160bf828c612904395990` | `688826601d9993427e478cf946cb82f09cbacfb749a201a0f10543f8e7b39a47` |
| `database/activation/se1/manifest.json` | `a44be8d2c4ff2cc228b25a55fd4c22ffb90912aebb393251c14a408cf5be7034` | `1c60b74e61def39a46d0674ad89a211d3aaca0aa1796f8020af52f062d489b7a` |
| `../scripts/verify-isolated.ps1` | `efd62438722c3b70e56d76f1fc65c5432cd56c73ca0fae111f3354c100da7810` | `0a93a54a2d8d150774344c7d52bc979f1572e589ac7a5c2f03d41ce88784710f` |

The independent hash inventory is also retained at `.recovery/r23-protected-amendment-hashes-complete.json`. No unrelated manifest, frozen migration, C security contract, isolation guard or operational admission rule was regenerated.

## Ordinary routes and authority

| Route | Actual integration |
| --- | --- |
| Existing `/login`, signed `/email/verify/{id}/{hash}`, dashboard and `/logout` | Existing authentication; composed email binding version; session rotation/current auth_version; membership policies |
| GET/POST `/merchants/{merchant}/onboarding/complete` | Custodian-signed exact name/expected tuple and stable request identity; existing initial-completion service |
| POST `/merchants/{merchant}/onboarding/complete/resolve` | Separately scoped existing completion resolver |
| Existing `/merchants/{merchant}/verification`, `/contact`, `/identity`, `/request`, `/code` | Existing controllers/FormRequest and verification services under exact fictional action grants |
| GET `/merchants/{merchant}/applications/integrated` | Persisted actual application status and authorized form |
| POST `.../integrated/submit` | Existing submission service and immutable encrypted evidence |
| POST `.../integrated/resolve` | Separately issued original expected tuple; existing read/validate resolver; no mutation replay |
| Existing `/admin/applications` and `/{application}` | Existing Chief review filtering, redaction and conflict exclusions |
| GET/POST `/admin/applications/{application}/decision` | Scoped form/command, separate Chief assurance, existing approval/rejection service |

These additional adapters are registered only by the dedicated staging provider. The predecessor ordinary submission endpoint retains its fail-closed behavior; operational boot does not load staging routes or adapters. Individual/unregistered-business eligibility is preserved: blank initial name is permitted, company registration is not universally required, and initial dashboard access does not require OTP/NIN. Approval/rejection, verified email, contact proof, identity declaration and future financial activation remain separate states.

The local custodian issues a separate command per mutation. There are no wildcard financial grants or browser capability-issuance endpoints. Commands bind expected versions and stable request identities; consumption is serialized in the separate admission store. Durable dispatch and outcome files quarantine lost acknowledgements or process interruption. A new command cannot silently replace an unresolved dispatch. Known rejected operations can receive a fresh separately authorized command. The workflow and admission stores are **not** a distributed transaction.

Submission additionally requires a custodian-signed attestation of the actual simulated proof/challenge/delivery/identity lineage. Provenance labels alone are insufficient. A Chief needs both an exact outcome/reason command and a separately issued assurance bound to that command, application, current authorization and role-record fingerprint; its lifetime is at most 120 seconds. Historical membership/submitter conflicts, current role, tenancy and final domain authority checks remain independent. This is fictional privileged assurance, not operational MFA or independent production custody.

## Verification, identity and lifecycle

The existing six-digit/five-minute OTP, five attempts, 60-second resend and ten aggregate failures/30 minutes policies are retained. Existing policy, concurrency, replay, expiry and invalidation regression assertions passed. Delivery is only a private ACL-protected local capture, with the reserved fictional contact; no real SMS is sent. Each ordinary verification submission also requires its scoped one-shot admission.

Identity accepts only the explicit synthetic syntax, never real NIN input. Separate per-generation phone, identity, OTP, contact, evidence and binding keys are generated; operational APP_KEY/identity keys are not reused. Existing encryption context/AAD, actor/merchant binding, revisions, redaction, audit and retention restrictions remain. Rendered status and Chief review do not expose identity plaintext or OTPs; browser checks and screenshots verify their absence. No legal collection or production retention approval is inferred.

Versioned email verification and existing phone-change/proof invalidation semantics remain. **Composed password reset remains HTTP 503**, and the original `se1_account_lifecycle_hold` still restricts password/account-status/auth_version changes. Its broader lifecycle/recovery integration requires separate review; it was not relaxed to manufacture completion. Existing membership/current-account checks remain enforced. Some suspension, stale-session, historical-conflict, concurrency and uncertain-commit cases are covered by automated service/integration regressions rather than newly fault-injected ordinary-web requests; no broader HTTP coverage is claimed.

Business-name and submission resolution reuse existing supported resolvers. Chief uncertain outcomes return a held/unresolved response and durable quarantine; there is no invented decision replay or new decision-resolution engine. Retain the command/history and obtain custodian review/retirement instead of blindly repeating. Information-request/resubmission actions remain unavailable because this accepted decision service supports approval/rejection only.

## Acceptance evidence

Fresh and recovered results are distinguished. Suites overlap and must not be summed.

| Evidence | Result |
| --- | --- |
| Recovered completed full R2.3 regression, `.recovery/r23-regression-final.log` | **852 tests / 9,148 assertions PASS** |
| Recovered existing-contract focused run, `r23-amendment-initial-tests.log` | **247 tests / 3,276 assertions PASS** |
| Recovered initial new-authority run, `r23-fictional-authority-tests.log` | **15 tests / 25 assertions PASS**; later quarantine additions are included in the full/affected runs |
| Fresh affected run after final browser-boundary correction, `r23-post-browser-fix-regression.log` | **226 tests / 2,061 assertions PASS**, including staging/R2.2 persistence and compatibility |
| Fresh ordinary HTTP acceptance, `r23-http-accepted-final.log` | **57 checks PASS** on `run-a4fbc1c80ce3ff2913a86de53a3a8d86` |
| Fresh isolated headless Chrome, `r23-headless-accepted-final.log` | **12 authenticated pages / 36 viewport checks PASS**, plus three missing/null/foreign-Origin denials |
| Fresh final established verifier, `r23-protected-accepted-final.log` | **PASS, exit 0**: 29 frozen pins, 3 approved manifest digests, 4 INT-05 pins, 4 isolation guards; 275 PHP syntax checks; 15 non-mutating formatting checks; 33 functional tests / 971 assertions; 546 bounded regressions / 4,910 assertions; source preservation |
| Scoped implementation formatting, `r23-staging-format-final.log` and `r23-browser-fix-format.log` | **PASS** for declared staging/new test paths; broad unrelated/protected formatting was avoided |
| Fresh financial status, `r23-financial-status-final.json` | Accepted profile unchanged: **38 tables, 77 indexes, 109 triggers, eight stages; ready** |
| Export preservation, `r23-export-preservation-final.json` | **All seven accepted PDF/XLSX files retain their accepted SHA-256 hashes** |
| Final whitespace/index | `git diff --check` passes; no staged changes |

The 57 HTTP checks demonstrate actual web SAPI with console/testing false, an unnamed initial dashboard, encrypted fictional contact, actual OTP request/verification through the existing controller and private capture, encrypted synthetic declaration, independently attested submission, original-receipt resolution, persisted submitted state, separate Chief review, denial without Chief assurance, approval and rejection on different applications, persisted merchant decision status, command replay denial, forged-attestation rollback, cross-tenant denial, Staff restriction, Chief/non-owner separation, capability revocation, financial denial after approval and logout. JSON unauthenticated access returns 401; HTML access redirects to ordinary login. No mutation outcome was injected into the UI or directly written to replace an essential HTTP step.

Responsive evidence: `.recovery/r23-browser-1795813969970/qa.json` and its PNGs. The existing isolated headless pattern was reused with a newly spawned Chrome, fresh temporary profile and its own ephemeral loopback CDP endpoint. It never attached to the user's Chrome or used the restricted Computer Use browser session. All intercepted page traffic was constrained to the exact staging origin; only ordinary login/logout POST was permitted by the browser checker. Current URL was checked after navigation. Mobile 390×844, tablet 768×1024 and desktop 1440×1000 checks cover Merchant Dashboard/onboarding/completion/verification/application, Chief workspace/review/decision, Staff workspace, and active draft forms. Assertions cover document width, loaded styles/images, labelled fields, staging indication, keyboard focus and sensitive-value absence. Representative PNGs were visually inspected. This is viewport emulation/basic accessibility checking, not real-device or full accessibility certification.

Intermediate diagnostics were preserved. The earlier HTTP logout assertion expected an HTML redirect despite explicitly requesting JSON; it was corrected to assert both JSON 401 and HTML redirect. Browser testing found that the original authentication `no-referrer` response causes Chrome to send `Origin: null` on form POST. A **staging-only `Referrer-Policy: same-origin`** response fixes actual browser forms without accepting null/foreign Origins or modifying operational `AuthenticationResponseHeaders.php`. The browser checker proves those denials remain 403 and never spoofs Origin. A missing custodian issuance branch for the existing submission resolver was completed and verified against the original immutable receipt. No operational test assertion was weakened.

## Preservation, operational safety and OneDrive

The accepted financial profile and separate admission store remain intact. Financial `PaymentStore` and P10 Chief context remain unbound; financial actions are absent from the explicit capability allowlist and blocked after approval. The decision only changes the fictional application's recorded state. Actual Chief review still shows merchant verification **unverified** and operational status **onboarding** after application approval.

Provider/webhook/OTP HTTP dispatch remains denied by application middleware; array-only mail and disabled external transport functions remain. The transport safety JSON's historical `identity: unbound` label is not used as evidence that no fictional identity adapter exists: R2.3 intentionally binds its tightly scoped fictional adapter. No operational identity adapter or real verification authority was activated. No host-wide firewall isolation is certified.

Original protected files and staging snapshots are preserved in `.recovery/r23-protected-before/` and `.recovery/r23-before/`. Accepted export evidence and all diagnostic/retired generation material remain. Financial source/schema and existing tests were retained; current source inventories and controlled hash records accompany the report. Final verification confirmed the approved replacement hashes, unrelated frozen pins and source preservation. The working tree remains intentionally uncommitted.

Final declared-source hashes are in `.recovery/r23-final-source-hashes.json`; `.recovery/r23-final-preservation.log` records the approved replacement-hash, accepted-export, whitespace and empty-index checks. The owned headless Chrome process was confirmed closed; the separate local owner-review server is the intentionally retained runtime.

Working SQLite/WAL/SHM were not opened, copied, hashed or changed. No operational dotenv, secret/key/credential source, real merchant/identity record or real provider was accessed. No real NIN collection, SMS/email/OTP/webhook delivery, payment, withdrawal, settlement, public API activation, Selcom connection, commit, push or deployment occurred. Only signed fictional staging and owned isolated test/browser material were used.

**OneDrive synchronization risk remains unresolved.** The staging anchor is still under the synchronized workspace. ACLs/signatures and fail-closed generation checks do not certify exclusion from synchronization, resistance to rollback/replacement, independent custody or operational durability. OneDrive configuration and trust-anchor location were not changed. All generated material remains fictional/non-operational; production custody and encrypted recovery remain R5 work.

## Owner review and next milestone

A fresh, verified review generation is running at **http://127.0.0.2:18762/**: `run-36530e1596e87fc2d4b08ccd338f2afa`, expiring **28 November 2026 at 08:15 EAT (UTC+03:00)**. Its local-only private handoff is `scripts/.staging/run-36530e1596e87fc2d4b08ccd338f2afa/owner-review.json`. Open that file locally; its account-file references and verification links are not published here.

The private handoff lists separate fresh Merchant, Chief and Staff account files and signed email-verification links. Passwords, signing/evidence keys and OTP values are not included here. The acceptance generation was stopped/retired and its subsequent admission denied; its test accounts are not the owner-review accounts.

Run commands from `XLipa/` with the existing PHP path. If the review generation has expired, first use Stop, then Start to obtain a new generation; retired generations cannot be reused:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging.ps1 -Operation Status
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging.ps1 -Operation Stop
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging.ps1 -Operation Start -Profile onboarding
$php = 'C:/Users/hp/.config/herd/bin/php85/php.exe'
$runtime = (Get-Content scripts/.staging/server.json -Raw | ConvertFrom-Json).runtime
```

Use `& $php -d auto_prepend_file= -d auto_append_file= -d allow_url_fopen=0 scripts/staging-control.php <operation> $runtime <arguments>` for the narrowly scoped custodian operations below. These are controlled fictional issuer steps, not browser self-issued authority.

1. Read the ACL-protected handoff locally. Sign in as the Merchant, then visit its private signed verification link. If expired, regenerate with `verify-link <email>` after login. Open the Merchant Dashboard before OTP/identity entry.
2. Issue `prepare-name <email> '<fictional business name>'`; open `/merchants/{merchant}/onboarding/complete` and submit the actual form. General name editing is not enabled.
3. Issue `grant-workflow <email> onboarding.verification.read`; open the ordinary verification page. Issue a separate `onboarding.contact`, `onboarding.otp.request`, `onboarding.otp.verify` or `onboarding.identity` grant with `grant-workflow` immediately before each corresponding form submission. Use only reserved fictional contact `+255600000000`, the local handoff password when requested, and synthetic syntax consisting of the prefix `FICTIONAL-` followed by 8–32 uppercase letters. OTP capture is private `otp-<request-id>.json` beneath the current generation; never send it through chat or real SMS. Codes expire after five minutes and each admission is one-use.
4. After contact/OTP and synthetic declaration, issue `attest-proof <email>`, `prepare-submission <email>` and `grant-workflow <email> onboarding.application.read`. Open `/merchants/{merchant}/applications/integrated`, submit, and confirm `pending_review`. For receipt-only resolution, issue `prepare-submission-resolution <email>` and use the separate `/resolve` POST with the current CSRF token; never replay the submission command.
5. Sign in separately as the Chief, verify its fictional email, and open the existing application queue/detail. Obtain the application ID from its ordinary review URL. Issue `grant-decision-read <chief-email> <application-id>`, then `prepare-decision <chief-email> <application-id> approved` (or `rejected <reason-code>` for a separate eligible application). Immediately issue `assure-chief <chief-email> <application-id>` and submit the decision form within 120 seconds. These are two distinct fictional issuer actions, not operational MFA.
6. Return to the Merchant to confirm the persisted decision and disabled financial services. Check Staff separately at `/admin/staff`; Staff cannot decide applications. Logout invalidates the session. Use Stop when review ends; stores/evidence are retained.

Read grants/commands expire after at most ten minutes and never beyond runtime expiry. Refresh them through the custodian rather than editing signed JSON. Short verification links may expire before owner review; regenerate them. Do not change signed source or manifests beneath a running generation. Failure/unknown outcome requires retaining evidence and explicit review/retirement, not destructive reset or blind retry.

**Project progress: approximately 65%, from the accepted 62% baseline (+3 percentage points).** This provisional estimate credits verified ordinary onboarding/submission/decision integration, not the already accepted P0–P10 implementations or test/document counts. There is no agreed weighted denominator. Provider-independent onboarding staging is demonstrated; ordinary financial integration remains incomplete. Security/operational readiness, Selcom readiness and production go-live readiness remain **NO-GO**, without invented readiness percentages. XLipa Store remains excluded, post-launch Phase 2.

Next milestone is **R2.4 — Products, Checkout, Mock Confirmation and Ledger Integration**, only after owner review and explicit authorization. Its prerequisites include retaining the signed persistence boundary, scoped financial admission and existing financial guards, with separate approval for any additional frozen-contract amendment. R2.4 and later milestones were not begun.

**STOP FOR OWNER REVIEW.**
