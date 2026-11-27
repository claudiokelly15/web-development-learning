# XLipa R2.2 — Reviewed Persistence and Schema Composition

27 November 2026 · Owner-authorized continuation · Core gateway only

**R2.2 STATUS: PASS for the fictional, disposable persistence and schema-composition scope. Financial business bindings, operational financial activation, Selcom and deployment remain disabled/NO-GO. R2.3 has not begun. STOP FOR OWNER REVIEW.**

## Recovery and scope

Resumed the existing R2.2 implementation without resetting, discarding or rebuilding it. Git HEAD remains `12dddbe2cd59949956a82d24d7b5c294f6cfc036`. R1 and accepted R2.1 work were preserved. The R2.1 report is historical evidence; its statement that R2.2 had not started describes that earlier milestone.

The interrupted financial regression **had completed successfully**, rather than remaining unfinished: `.recovery/r22-financial-final.log` records **190 tests / 8,792 assertions PASS**, duration 274,751 ms. The recovered authentication regression records **194 tests / 1,869 assertions PASS**, and the recovered ordinary-web log records **78 checks PASS** (superseding the earlier reported 77). These are recovered completed results, not newly rerun totals. No failed financial run required repair or repetition.

Initial sandbox process inspection could not establish host process state. Subsequent permitted host inspection showed no remaining interrupted PHPUnit process and no live prior staging server PID. The prior owned generation `run-a884854862931f49e8c3d3611379df27` was retired through the established launcher, with its stores and evidence retained. A new generation was used for fresh web acceptance and then stopped and retired too.

Reviewed applicable `backend/AGENTS.md`, existing implementation, schema tests, compatibility tests, staging control/runtime boundaries and the R1/R2.1 milestone requirements. No `backend/.ai/rules` exists. The available skill catalogue has no `testing-best-practices` skill. The full earlier conversational R2.2 directive was not recovered as a standalone file; acceptance here follows the owner's continuation checklist and the persisted R2.2 scope and tests, without inventing additional authorization.

## Implemented persistence foundation

`xlipa-financial-schema-v1` composes the reviewed financial schema into a separate, fixed, disposable `financial.sqlite`. Fresh custodian verification confirms **38 tables, 77 indexes and 109 triggers**, including SQLite autoindexes and staging metadata. These are catalogue object counts, not counts of business functionality or operational readiness.

The dependency ledger contains eight ordered stages: `p4`, `p5`, `p6`, `analytics`, `p8`, `p9`, `p10`, `p10-completion`. P7 services consume the composed financial stores without a separate schema stage. Existing foundation workflow and admission stores remain separate; no distributed transaction or rollback across stores is claimed.

| Source | Responsibility |
| --- | --- |
| `backend/app/Staging/financial-schema-v1.json` | Versioned reviewed P4–P9 DDL, source hashes and dependency order |
| `backend/app/Staging/FinancialSchema.php` | Definition digest, exact catalogue/ledger verification, atomic DDL, constraints and lineage |
| `backend/app/Staging/FinancialProvisioner.php` | Custodian-only signed-target initialization, exclusive lock, durable claim and fail-closed repetition |
| `backend/app/Staging/StagingIdentity.php` | Signed provisioning purpose, fixed target, source release, generation/store identity and ready-store admission |
| `scripts/staging-control.php` | Provision before publishing the ready runtime manifest; explicit status/provision/retirement commands |
| `backend/app/Staging/StagingServiceProvider.php` | Non-secret schema status; financial actions remain denied |
| `backend/tests/Feature/StagingFinancialSchemaTest.php` | Catalogue parity, preservation, corruption, failure, interruption, concurrency and lineage tests |
| `backend/tests/Feature/StagingFinancialCompatibilityTest.php` | Existing P4–P10 services exercised against the composed schema in isolation |
| `scripts/Test-Staging.cjs` | Actual ordinary-web acceptance including ready-schema status and denied financial surfaces |

The ordinary boot path does **not** import test installers. The versioned DDL is compared in isolated tests with the unchanged reviewed fixture installers and P10 notification/completion statements. The prior missing analytics trigger was corrected in the versioned composition; exact parity assertions remain intact. This resumed run found no additional implementation defect requiring a source fix.

The definition digest is `b6a3de0d8e494c4f77f7c5b0bd9090fae430c08d6b065b11591f3f575ec88d2e`. Fresh combined schema-source digest is `d5d2ca7ef0af373b065eb4c18ddbf423c99aff0e3b30100bc9a1a8f4cba8b825`.

## Admission, failure and recovery behavior

Provisioning verifies the locally signed purpose, namespace, origin, release, runtime, generation, store identity, fixed root, ownership and SQLite headers before initialization. It accepts only the exact unprovisioned marker schema. It does not discover or accept an arbitrary database path.

An exclusive nonblocking file lock serializes custodians. A durable exclusive-create intent records lineage before schema work. DDL and the dependency/profile records use one `BEGIN IMMEDIATE` transaction. Readiness requires exact catalogue definitions, all ledger contents, matching profile identity, enabled foreign keys/recursive triggers, active CHECK enforcement, no writable schema, no unexpected attached/temp schema, and successful integrity/foreign-key checks.

Successful repetition independently verifies the committed result and leaves the database bytes unchanged. A partial, interrupted, incompatible or mismatched generation is denied; it is not silently repaired or overwritten. A lost commit acknowledgement reports `PaymentOutcomeUnknown`; no financial operation is automatically retried. Tests separately demonstrate interrupted DDL rollback, competing writers, competing provisioners, persistent interrupted claims and a real child-process interruption. Independent workflow effects cannot be rolled back by the financial transaction.

Rollback is retirement and creation of a fresh fictional generation, retaining the old stores and diagnostics. Do not run destructive schema rollback, replace manifests manually, or reuse retired generations. The existing `staging.ps1 Start/Status/Stop` workflow remains the supported launcher. Start provisions the schema before serving; Stop authenticates owned process identity before termination. No server remains intentionally running after this report.

## Verification and acceptance

| Check | Final evidence |
| --- | --- |
| Financial regression recovered from interrupted execution | **190 tests / 8,792 assertions PASS**, `.recovery/r22-financial-final.log` |
| R2.1/authentication regression recovered | **194 tests / 1,869 assertions PASS**, `.recovery/r22-auth-final.log` |
| Fresh resumed persistence + composed compatibility filter | **27 tests / 115 assertions PASS**, `.recovery/r22-persistence-resumed-final.log` |
| Fresh ordinary-web acceptance | **78 checks PASS**, `.recovery/r22-web-resumed-final.log` |
| Fresh schema status | **38 tables / 77 indexes / 109 triggers; eight stages; ready**, `.recovery/r22-schema-status-final.json` |
| Fresh transport/binding safety | Provider/webhook/OTP denied; array-only mail; financial and identity bindings unbound, `.recovery/r22-safety-final.json` |
| Final established isolated verifier | **PASS, exit 0**: 29 frozen pins, 3 manifest digests, 4 INT-05 pins, 4 isolation guards; 262 PHP syntax checks; 15 non-mutating formatting checks; 33 functional tests / 971 assertions; 546 bounded regressions / 4,910 assertions; source preservation |
| Explicit R2.2 PHP formatting | **PASS**, `.recovery/r22-format-final.log` |
| Export evidence restoration | **All seven files match pre-regression SHA-256**, `.recovery/r22-restored-evidence-final.json` |
| Final retirement | Owned server stopped; retired admission denied; stores retained, `.recovery/r22-retirement-final.log` |

Suites overlap and are not summed. The 27-case fresh filter includes the composed compatibility test; it is not 27 additional persistence cases plus another compatibility total. Its services use isolated fake grants/providers; this does not establish an ordinary-web financial journey or enable business bindings.

Compatibility exercises payment/event replay, balanced immutable ledger postings, integer TZS, payment links, developer credentials/widgets, webhook receipt/delivery constraints, settlement, withdrawal reserve/idempotency/insufficient funds, payout reconciliation, notification document timing, and CSV/PDF/XLSX export content. The test still requires operational available funds to remain zero. Exact schema parity and original installer source hashes pass without weakening assertions or existing service guards.

Fresh HTTP evidence is retained at `scripts/.staging/run-aa0361576aa2602a77b1e3eb22d4a0f2/http-acceptance.json`. It demonstrates genuine `cli-server` execution with console/testing false, ready financial schema with financial actions denied, repeat provisioning, invalid signed-provisioning denial without database change, ordinary fictional authentication/role reads, tenancy and assurance denial, replay/CSRF/Origin controls and unavailable future financial/identity surfaces. The generation was subsequently retired; its admission now fails as intended.

The first attempt to invoke the verifier directly encountered Windows script execution policy. Running the established script with process-local `-ExecutionPolicy Bypass` completed successfully; no persistent execution policy changed. Host inspection and existing PHP/staging ACL access used scoped permitted execution. No unresolved automatic approval rejection remains in this continuation.

## Previously accepted export evidence

Compared the current accepted files under `.recovery/` with all seven files in `r22-financial-evidence-af637349a6a841e3a2877592a3969447/before/`. Every SHA-256 and size matches. The accepted PDFs are advice (1,423 bytes), multipage (3,185), payment (1,147), receipt (1,419), report (2,781), settlement (1,141), and the safety XLSX is 2,500 bytes. Their complete hashes are recorded in the final JSON evidence.

The `before/` and `after/` directories remain intact. This is byte-preservation verification of previously accepted artifacts; no new visual acceptance is claimed or needed for unchanged bytes. No accepted export was overwritten in this continuation.

## Protected contracts, source preservation and operational safety

The final verifier passed all frozen contract/migration/manifest/isolation pins and source preservation. Git's tracked implementation remains unchanged except the previously authorized `.staging/` ignore entry in `scripts/.gitignore`; the additive staging source, tests and reports remain untracked and preserved. The index remains empty. No existing tracked service, fixture installer, protected contract, dependency or operational bootstrap was changed. Final source hashes are retained in `.recovery/r22-source-final.json`; final verification details are in `.recovery/r22-preservation-final.log`.

No operational database, working SQLite/WAL/SHM, operational environment file, credential source or real financial service was accessed. Only signed fictional staging stores and isolated owned test stores were used. Staging-only generated keys/account handoff files remain inside the ignored ACL-protected generation directories and are not printed here. No real identity, OTP, email/SMS delivery, webhook, payment, withdrawal, settlement or Selcom connection occurred. No dependency update, commit, push or deployment occurred.

Financial readiness describes persistence only. `PaymentStore` and financial business authority remain unbound, action grants remain restricted to the foundation allowlist, and existing final application policies/guards remain authoritative. The staging server disables established external transport functions; application HTTP rejects external communication, notifications remain constrained and mail uses only its array transport. These checks do not certify host-wide firewall isolation or production custody.

**Remaining OneDrive risk:** the workspace and fictional runtime artifacts still reside under a OneDrive-synchronized parent. Local ACLs, signatures, exact catalogue checks and generation retirement do not establish synchronization exclusion, resistance to synchronized rollback/replacement, independent custody or operational durability. OneDrive configuration was not changed or certified. Keep this foundation fictional-only; operational storage/custody/recovery approval remains outstanding.

## Outcome and project estimate

**PASS within R2.2's reviewed persistence/schema-composition scope.** The resumed work confirms successful completed financial regressions, fresh persistence/compatibility and ordinary-web checks, intact prior exports, protected contracts, preserved source and retired fictional runtime. Ordinary financial orchestration and provider-independent end-to-end staging remain incomplete.

**Core development estimate: approximately 62%, from the accepted 61% baseline (+1 percentage point).** This conservative provisional judgment credits versioned reviewed provisioning and demonstrated schema/service compatibility. There is no agreed weighted denominator; this is not an operational-readiness percentage. Security/operational readiness, Selcom readiness and production go-live readiness remain **NO-GO**, without unsupported numerical estimates. XLipa Store remains excluded, post-launch Phase 2.

**STOP FOR OWNER REVIEW. R2.3 has not started.** R2.3–R2.8 and R3–R7 retain their approved order and remain pending. This report grants no authority to begin them or amend frozen contracts.
