# XLipa R2.1 — Ordinary-Web Staging and Admission Foundation

27 November 2026 · Owner-authorized implementation · Core gateway only

**R2.1 STATUS: PASS for the fictional, disposable local foundation. Operational financial activation, Selcom and deployment remain NO-GO. R2.2 has not started.**

## Result and repository state

Recovered a clean `main` at `12dddbe2cd59949956a82d24d7b5c294f6cfc036` (`R1`). P10 was already complete in its documented implementation/isolated scope; R1 was the last completed assignment. The selected reconciliation's statement that R1 had not started was superseded by the committed R1 audit and matrix. No accepted feature was rebuilt.

The subsequent owner directive explicitly accepted R1 and authorized R2.1. Reviewed the current P10/R1 requirements, reconciliation, applicable backend instructions, relevant prior implementation/security/recovery report sections and the affected source. No applicable `.ai/rules` directory or additional ancestor `AGENTS.md` was found. The catalog has no `testing-best-practices` skill. Existing locked PHP/Laravel/PHPUnit/Pint installations were reused; no dependency changed.

Implemented actual Laravel web execution at **http://127.0.0.2:18762/**. Fresh HTTP evidence reports `PHP_SAPI=cli-server`, `runningInConsole=false`, `runningUnitTests=false`, `APP_ENV=staging`, and synthetic-only flag `0`. The existing operational Herd site/configuration, isolated test bootstrap and static demo were not repurposed.

## Boundary and implemented code

The concise design used a dedicated pre-boot router, OS-custodian signed runtime manifest, additive staging provider and fixed disposable targets. The only initial actions are dashboard/Chief/Staff reads and a non-financial admission/replay probe. Existing authentication, policies and controllers remain the final application authorities.

| New file, relative to project | Responsibility |
| --- | --- |
| `backend/app/Staging/StagingIdentity.php` | Fixed trust anchor, canonical paths, signature/issuer/runtime/origin/release/expiry/ownership checks; store headers, schema and generation checks |
| `backend/app/Staging/StagingCapability.php` | Signed actor/tenant/action/resource/auth-version grants, current revocation, durable time high-water mark and atomic one-shot command consumption |
| `backend/app/Staging/StagingRuntime.php` | Dedicated environment, connection, cache, storage, session, cookie, mail, queue and filesystem configuration before providers boot |
| `backend/app/Staging/StagingServiceProvider.php` | Staging-only middleware binding, transport denials, fictional notification policy and three staging verification endpoints |
| `backend/app/Staging/StagingAuthenticationReady.php` | Web-only readiness using the unchanged exact foundation schema contract |
| `backend/app/Staging/StagingBoundary.php` | Exact origin, write-Origin requirement, limited surfaces, fictional contact input, identity denial, CSP and staging indicator |
| `backend/app/Staging/StagingReadAdmission.php` | Independent capability checks in addition to existing account, merchant and platform policies |
| `backend/tests/Feature/StagingFoundationTest.php` | New isolated runtime/path/store/capability/operational-separation tests |
| `scripts/staging-router.php` | Pre-boot admission, allowlisted local assets and actual ordinary Laravel HTTP handling; empty document root prevents filesystem fallback |
| `scripts/staging-control.php` | CLI custodian provisioning, fictional accounts/roles, local signed verification links/reset-token capture, narrow grants, revocation and retirement |
| `scripts/staging.ps1` | Loopback-only startup, restrictive Windows ACL, ownership-checked shutdown, retirement and failed-start handling |
| `scripts/Test-Staging.cjs` | Actual cookie/CSRF/session/controller HTTP acceptance journey and Windows junction/canary verification |
| This report | Implementation evidence and handoff |

**Only existing tracked file modified:** `scripts/.gitignore`, adding `.staging/`. All existing application/test/template/asset/activation files remain unchanged. Seven application classes, one test and four runtime/verification scripts are additive. Scaffold generation used Artisan through the existing synthetic bootstrap; that temporary scaffold script was removed.

### Runtime, target and authority selection

The fixed anchor is `XLipa/scripts/.staging/`; each provision creates a unique `run-<32 hex>` directory. `XLIPA_STAGING_RUN` selects only a candidate ID. It cannot admit a runtime without the locally trusted issuer signature, fixed namespace/origin/root, source-release digest, ownership marker, current generation, expiry and matching stores. Request fields, cookies, forwarded context and environment labels are not issuer authority. Operational bootstrap does not register the staging provider or endpoints.

The local Windows custodian and SYSTEM have the staging directory ACL. The CLI signer retains a staging-only Ed25519 secret outside application configuration; web verification uses the public key. Each runtime has a new application encryption key and signed store identity. Keys, credentials and generated stores/evidence are ignored and were not printed in reports. The supported launcher applies ACL restrictions before new key generation.

Before loading Laravel's environment/configuration, the router validates the raw Host/loopback peer and signed target. Only fixed filenames beneath the signed root can be selected. Checks reject traversal, alternate separators/streams, trailing dot/space aliases, reserved Windows names, missing paths, symlinks/junction resolution changes, multiple hard links, mismatched ownership and retirement. No operational path is inspected to derive these rules. A synthetic canary and a Windows junction were used for denial evidence.

The release signature covers current application/bootstrap/config/route/migration PHP and the router/control implementation. Source changes invalidate a running generation; provision a new generation rather than editing its signed manifest. Runtime manifests expire after at most eight hours. Capability expiry cannot exceed runtime expiry. Revocation and command claims reside in a separate admission SQLite store; a high-water mark rejects observed wall-clock rollback. Read grants can be reused; command grants bind one exact ID and reject duplicate consumption under `BEGIN IMMEDIATE`.

Capabilities bind namespace, issuer, runtime, release, store, generation, actor, merchant, action, resource, auth_version, issue/expiry times and optional command. The issuer cannot grant payment/ledger/payout/API/webhook/notification actions through the implemented action allowlist. Browser users have no issuance endpoint. A Chief role is not merchant membership; Staff has no Chief grant. Existing policies independently recheck current membership/platform roles.

### Persistence and authentication

`workflow.sqlite` contains **only the nine existing foundation migrations**, selected explicitly from the frozen foundation definition in their original two batches. No unrestricted migration or test fixture installer is used. The unchanged `WorkflowSchemaReadiness` verifies the exact catalogue and migration ledger. Signed per-store SQLite application/user-version headers supply additional lineage without adding workflow tables that would violate that contract.

`financial.sqlite` contains only its runtime/generation marker with profile `unprovisioned-r22`; it has no P4–P9 schema and is not bound as `PaymentStore`. `admission.sqlite` contains identity, revocation, replay and clock records. These are separate stores; no distributed transaction is claimed. Sessions, compiled views and cache live under the generation's storage directory. Cookies use a generation-specific name, host-only scope, HttpOnly and SameSite Strict. Secure is false for this explicit local HTTP origin; this is not production TLS acceptance.

Controlled fictional merchant provisioning calls the existing `RegisterMerchantOwner` and `CreateMerchant`, with blank business name, declared fictional phone and a draft application. Chief/Staff are separate fictional accounts with explicitly provisioned read roles and independently signed staging grants. Ordinary `/login`, signed email-verification, `/account`, merchant dashboard, admin workspace and `/logout` controllers handle real HTTP requests and persisted records. No financial totals are injected.

Foundation password reset works through the existing broker/controller and changes `auth_version`, invalidating both old sessions and old staging grants. The composed SE/BNC reset restriction is unchanged: its proof/authority-invalidation integration still requires R2.3/R5 review. No claim of composed-profile reset completion or operational MFA is made.

## Fresh verification

| Check | Result |
| --- | --- |
| New staging suite | **35 tests / 44 assertions PASS** on disposable test stores |
| Combined staging + existing Authentication, MerchantDashboard, MerchantAccess, AdminWorkspace, TrustedHosts suites | **194 tests / 1,869 assertions PASS**; includes the 35 new tests, not an additional independent total |
| Actual ordinary-web journey | **73 checks PASS**, repeated after final launcher stop/start, on fresh generations |
| Existing P0 verifier | **PASS, exit 0**: 29 frozen pins, 3 manifest digests, 4 INT-05 pins, 4 isolation guards; 258 PHP syntax checks; 15 non-mutating formatting checks; 33 functional tests / 971 assertions; 546 bounded regression tests / 4,910 assertions; final source preservation |
| New-file formatting | Explicit-path Pint PASS for new staging PHP/test/router/control files |
| Git whitespace/index | `git diff --check` PASS; index empty; only the declared tracked modification and new implementation files |
| Shutdown/retirement | Owned generation stopped/retired; its subsequent custodian admission denied; replacement generation healthy with genuine web SAPI |

The automated suites overlap and are not summed. The combined 194-case run preceded the final lowercase-ULID allowlist correction; the final 73-check HTTP run exercises that correction, and the final P0 run checks the stabilized PHP source. No existing assertion was weakened.

HTTP checks include ordinary login and session rotation; unverified denial; tampered/valid signed fictional verification; actual unnamed merchant dashboard; Chief/Staff workspaces; merchant/Chief/Staff and cross-tenant denials; missing/revoked capabilities; one-shot replay; real CSRF and write-Origin denial; disabled identity/future financial/API surfaces; rejection of real contact input; wrong Host; missing ownership; tampered manifest and unchanged canary; Windows junction rejection; logout and old-cookie denial; auth_version change, suspension and membership revocation; foundation password reset, new-password login and stale-assurance denial. Native PHP runtime values establish that this is not a CLI/testing harness masquerading as web execution.

Application HTTP transport middleware rejected attempted fictional provider/webhook/OTP requests before network dispatch. Mail configuration exposes only the in-memory array transport; other notification channels are suppressed and financial/Chief P10 context bindings remain absent. The server command disables URL wrappers, curl/socket connection functions, mail and subprocess launch functions. No SMS/provider/webhook adapter was activated. No infrastructure firewall or complete host-wide network isolation is certified.

Evidence: `.recovery/r21-preservation.log`; final web evidence `scripts/.staging/run-48835efc4248d78bf5629cefc07a8c69/http-acceptance.json`. Earlier disposable generations and diagnostics were retained. PHPUnit uses the unchanged `tests/bootstrap-isolated.php`, in-memory Laravel DB and separate owned temporary test stores; it never targets staging or working data. Runner flags clear PHP auto-prepend/append, disable URL access and disable the established network functions.

Intermediate failures were corrected: Windows drive cache-prefix handling, pre-bootstrap request binding, dedicated migrations configuration, closure middleware registration and lowercase Laravel ULIDs. A source edit during an early HTTP run correctly invalidated that runtime's signed release; fresh final generations resolved the verification race. Early attempts are not counted as acceptance passes. Automatic review rejected broad `pint --dirty` because it could affect unrelated/protected files; the accepted explicit-path formatter completed safely. No unresolved approval block remains.

## Startup, use, shutdown and rollback

Run from `XLipa/` using the existing PHP 8.5 and Node installations:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging.ps1 -Operation Start
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging.ps1 -Operation Status
& 'C:/Program Files/nodejs/node.exe' scripts/Test-Staging.cjs
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging.ps1 -Operation Stop
```

Start creates a fresh generation and refuses an existing server state or occupied port. The final accepted generation is left running for local review at **http://127.0.0.2:18762/**. Do not run Start again until Stop. The HTTP acceptance script creates fictional accounts and captures its non-secret result; it intentionally invalidates some test accounts. Fixture passwords remain only in the ACL-protected `account-<id>.json` files. No credentials are embedded here.

For manual use, the CLI custodian can run `scripts/staging-control.php account <runtime> owner@example.test merchant` (or separate `chief_admin` / `admin`), using PHP with auto-prepend/append cleared. Obtain `<runtime>` from the non-secret `scripts/.staging/server.json`. Login using the private fictional account handoff; `verify-link <runtime> <fictional email>` generates a short-lived local link to visit in that authenticated session. This establishes fictional email assurance only. `grant-probe` and `revoke` affect only the limited staging capability namespace. Never supply real identities or reuse operational credentials.

Stop first writes retirement, verifies PID/start-time/executable/router/root ownership, then stops only that process and archives its state. Retired generations cannot be admitted again through the application/control tools. New starts have different store identities, keys, cookies and sessions. Failed starts retire their new generation and stop the freshly owned process. No rollback runs `down()`, resets Git or deletes store evidence. If process ownership is uncertain, retirement remains in force and the process/resources are retained for inspection. This additive implementation is absent from ordinary bootstrap, so stopping it preserves existing operational behavior.

## Limits, next milestone and progress

This is a same-user local fictional trust boundary, not independent production custody, a secure enclave or operational MFA. Windows ACLs and canonical/reparse checks do not certify resistance to a compromised custodian/SYSTEM, hostile filesystem replacement races, OS clock compromise, disk rollback or OneDrive synchronization. Generated local data remains under the workspace's synchronized parent; it contains fictional material only. No operational key/storage/recovery approval follows from this arrangement. Those stronger guarantees remain R5/operational work.

R1-H03 is resolved for this limited genuine web profile; H01 now has a working ordinary auth/read staging entry. H02 has explicit verified disposable targets but financial schema composition remains unimplemented. H04 has a separate signed fictional issuer/store/release/time/revocation boundary, not operational custody. H06 remains fictional scoped assurance, with real privileged MFA still required. API/worker principals remain unimplemented and denied. No full onboarding/submission/decision, payments, ledger, wallet, API, P10 worker or provider integration began.

P10's accepted implementation is preserved; this session does not upgrade its gated operational routes to working financial integration. The Safe Browser Demo at `127.0.0.1:18761` was unreachable on recovery. Its source/state/exports were preserved, not restarted, and it supplied no R2.1 acceptance evidence.

**Next milestone: R2.2 — Reviewed Persistence and Schema Composition, after owner review and explicit authorization.** Entry point: review the existing financial fixture schema contracts, add a versioned ordinary disposable financial provisioning profile and compose dependent P10 schema against the signed store/generation boundary. Do not import test installers into web boot or relax existing service guards. Any required frozen-contract amendment still needs its separate narrow approval. R2.3–R2.8 and R3–R7 retain their approved order and remain pending.

**Core development estimate: approximately 61%, from approximately 60% (+1 percentage point).** This conservative provisional judgment reflects the newly demonstrated real web runtime, persisted fictional authentication/role reads and enforced admission boundary; it does not recount P0–P10. There is no agreed weighted denominator. Ordinary core financial integration and provider-independent end-to-end staging remain incomplete. Security/operational readiness, Selcom readiness and production go-live readiness remain NO-GO without unsupported numerical percentages. XLipa Store remains excluded, post-launch Phase 2.

Working SQLite/WAL/SHM were not accessed, copied, hashed or modified. No operational environment/key/credential source was opened. No real identity/OTP/email/SMS/webhook delivery, payment, withdrawal, settlement, Selcom connection, protected-contract change, dependency update, commit, push or deployment occurred.

**STOP FOR OWNER REVIEW. R2.2 is not authorized by this report.**
