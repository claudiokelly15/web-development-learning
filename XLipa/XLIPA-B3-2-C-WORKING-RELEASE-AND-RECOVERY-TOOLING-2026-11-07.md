# XLipa — B3.2-C Working Release and Recovery Tooling

**RRT-1.0 · 7 November 2026 · Africa/Nairobi · ISOLATED SYNTHETIC TOOLING ONLY**

## 1. Executive summary

**VERIFIED / IMPLEMENTED:** explicit release selection with approved artifact/contract pins; independently scoped signed simulated actions; reuse of the accepted single-use run register; SQLite backup-API snapshots into exclusively new destinations; independently authorized restore rehearsal; typed-content/schema/ledger verification; and durable receipt evidence outside the application database. Six real subprocess termination tests exercise backup/restore interruption and restart refusal.

The final targeted suite passes **47 tests / 193 assertions**; full combined regression passes **358 tests / 3,656 assertions**, zero failures/errors in both runs. Existing B/C migrations, activation artifact, working-disabled manifest, offline permit/selector/register/classifier and historical reports are unchanged. No real database/environment was used as a fixture.

**Working execution remains DISABLED.** The five action names are supported as **signed simulation scopes**, not operational working permissions. `working_migrate` and `runtime_activate` only classify a synthetic source and return `execution=disabled`; they dispatch no migration or service. No working backup/restore/migration/runtime executor exists in this package.

Kwa Kiswahili: release na recovery tooling imejaribiwa kwenye fixtures pekee. Backup na restore kila moja zinahitaji synthetic permit yake. Destination iliyopo haiandikwi juu; interrupted/ambiguous runs zinabaki consumed na quarantine. Hakuna working SQLite, `.env` au secrets zilizofikiwa.

**GO for owner/technical review of this isolated preparation package. Working activation remains NO-GO.** Synthetic success is not production readiness. XLipa remains a WEBSITE supporting TZS only.

Labels: **VERIFIED** = directly inspected/tested within the stated fixture scope; **REPORTED** = prior evidence; **PROPOSED** = unexecuted future work; **NOT TESTED** = no executed evidence.

## 2. Reviewed evidence and exact file delta

Reviewed backend AGENTS.md, accepted B decision (FOD), CAR isolated rehearsal, WSR design, OSG guard implementation, OAD authorizer diagnosis, FRP readiness and completed FRR-2.0, plus actual activation PHP/SQL/manifest, Laravel migrator, permit/register/selector/classifier, isolated bootstrap and existing tests. Prior target observations remain **REPORTED** in this task; no working target access/hash was repeated. `.ai/rules` was absent at checked workspace/project/backend locations. No applicable testing-best-practices skill was available; none was installed. No sub-agent or dependency was introduced.

**No pre-existing project file changed.** New paths relative to `XLipa/`:

| File | Purpose |
|---|---|
| `backend/tests/Support/ScopedRecoveryAuthorization.php` | Bind one simulated operation/environment/source/destination/hash/provenance scope to an accepted signed offline permit; no working path authority. |
| `backend/tests/Support/SyntheticRecoveryPackage.php` | CLI-only fixture orchestration, pinned release selection, state gate, backup/restore API, provenance and independent result evidence. |
| `backend/tests/Support/recovery-crash-worker.php` | Isolated subprocess with controlled crash barriers; takes public synthetic permit metadata, never a signing secret. |
| `backend/tests/Feature/SyntheticRecoveryPackageTest.php` | Independent fixture construction, authorization/release/backup/restore/corruption/lock/crash tests. |
| `XLIPA-B3-2-C-WORKING-RELEASE-AND-RECOVERY-TOOLING-2026-11-07.md` | This report. |

SHA-256 pins for the tested new PHP revision:

| File | SHA-256 |
|---|---|
| ScopedRecoveryAuthorization.php | `f58feb35e59dfe7eac30e91eedacfa0781b76cd947b5bf4995243b0b44c7aa2c` |
| SyntheticRecoveryPackage.php | `2f8140f820ab05d9f6257a696e9707a65c446ebec171e876a60c9af33aae93a3` |
| recovery-crash-worker.php | `b9ba1534a8946eec3c5050f0c5b30fadbb532599e18c5c3f348d67078b81e148` |
| SyntheticRecoveryPackageTest.php | `1165b7d4b4db7e269887f731c4d29333a8c98daa01fa69c112d2a79d3ad897a7` |

All classes remain under Tests support, with no application route/provider/Artisan registration. Direct creation avoided a working Artisan boot. Accepted guards were reused, not widened.

## 3. Release-selection controls

The package delegates complete staged inventory/canonical manifest/request validation to **OfflineReleaseSelector**, then independently pins the reviewed activation PHP (`be27fdee…337e97`) and SQL contract (`09d12df3…e44a0`). It accepts exactly one relative artifact request, never an unfiltered list, directory, absolute bypass path or arbitrary shell string. Selection is checked before claim, after claim and after the operation. No selected PHP is included or executed by this tooling.

The staged fixture inventory permits only the named activation PHP and SQL asset. Additional/unknown migrations, duplicate/unexpected files and the three synthetic-only B migrations cannot enter it. Even repinning a tampered artifact into a newly signed fixture manifest does not bypass the approved revision pins. Existing OSG tests retain more selection permutations, including duplicates/aliases.

The existing manifest remains **isolated-rehearsal-only / working execution DISABLED**. Its reviewed policy to exclude the three `2026_11_06_100000/100001/100002` files from future working discovery is unchanged. The new fixture selection enforces the two-asset allowlist; it is not a deployed release build. No synthetic ledger entry is fabricated as successful deployment.

Before recovery, the accepted **OfflineStateClassifier** verifies exact foundation/final schema and genuine expected ledger shapes through a read-only PDO connection. Both schema-without-ledger and ledger-without-schema quarantine. No migrator is invoked to resolve them. The existing CAR subprocess test for DDL commit before Laravel's subsequent repository logging is rerun in the full suite; framework source still logs after `runMigration`, so this gap is not silently treated as atomic or repaired.

## 4. Scoped authorization and replay controls

Supported operation scopes are exactly `read_only_revalidate`, `backup`, `restore_rehearsal`, `working_migrate`, `runtime_activate`. Each canonical scope includes the simulated environment, generated temp root, selected source/destination basenames, full source SHA-256 and restore provenance digest where applicable. The scope must equal independently supplied trusted fixture expectations.

To preserve the existing permit contract unchanged, its core action stays **offline_classify**, target stays **:memory: / synthetic-only**, and environment stays offline-synthetic. The **signed approval_ref** is `simulate:<operation>:<SHA-256 of canonical scope>`. Consequently the requested operation and complete target/provenance binding are cryptographically included in the existing exact-byte Ed25519 signature. This is an explicit scope adapter, not an assertion that the old permit gained working authority.

Actual execution requires CLI plus the existing testing/synthetic bootstrap, and the scope environment must be **working-local-simulation**. Pretending that testing is a real working environment is unsupported; actual production environment substitution rejects. Disk paths are restricted lexically before target access to independently generated `xlipa-recovery-<20hex>` temp roots and fixed source/backup/restore basenames. The accepted original permit remains incapable of authorizing arbitrary disk paths.

Each fixture uses a disposable random signing keypair; secrets remain in memory and are zeroized during teardown. No real signing key, owner trust root, service or authority is provisioned. Operator/host/time/release/identity/revocation checks are delegated to the existing permit validator, with fresh checks after claim and before access/reservation/API/receipt plus final return. Invalid signatures/scopes/releases invoke **zero target-access hooks**; actual source hash/schema failures necessarily occur after an authorized fixture access boundary.

The unchanged **OfflineRunRegister** durably claims both permit and run UUID before source access. Interruption never refunds the claim. The adapter introduces a separate fixture **result-evidence journal and anchor** in that same external register directory, not a business table. Explicit fixture initialization is separate from execution; constructors/restarts never recreate missing evidence. Its locked/fsynced, checksum-linked records pin the exact receipt digest to permit/run IDs.

A restorable backup requires **both** a matching sealed receipt digest and the original run's **completed** terminal claim/digest. A crash after receipt creation but before sealing or run completion leaves a visible artifact that is not accepted as completed. Missing/corrupt evidence, changed receipt origin/content, replay or incomplete states deny. Hash chains detect corruption, not a privileged actor rewriting all custody material. No operational ACL, anti-rollback trust or production journal is claimed.

## 5. Synthetic backup and restore results

### Independently generated source

Tests run the original nine migrations only on the existing guarded memory foundation builder. Two independent synthetic tenant/user/membership/application sets, fixture-encrypted evidence and all eight original audit meanings are populated. A disposable source file is constructed from those memory schema/rows using typed SQL bindings with FK checking; no working database or real record is copied. Final-schema fixtures use the accepted memory-only C activation mechanism before export.

### Backup sequence — VERIFIED in fixtures

1. Validate permit, complete release selection and durable claim; reject missing source, aliases, wrong hash, sidecars or non-1/1 SQLite header.
2. Open existing source with **SQLite3 OPEN_READONLY**, query-only, memory temp and FK enforcement; source/classification PDO also uses explicit read-only open flags and **zero timeout**. Reject ambiguous foundation/final state or integrity/FK errors.
3. Hold a read transaction for a logical snapshot. Build private schema plus typed-row digests in deterministic primary-key order, including sqlite_sequence; no row values are logged.
4. Reserve destination with **x+b create-new**, reject any existing destination/receipt/sidecar, then open only that reserved file read/write without CREATE. Invoke **SQLite3::backup(source → destination, main/main)** and require success. There is no raw database copy or unsafe fallback. PHP documents that the API overwrites its destination; exclusive new-file reservation is therefore essential. [PHP SQLite3::backup](https://www.php.net/manual/en/sqlite3.backup.php).
5. Close/flush/fsync destination, reopen it read-only, require exact logical digest/state/ledger equivalence, integrity ok and zero FK violations. Recheck source hash unchanged. Publish a new receipt, seal its digest externally and finish the claimed run. On failure retain an incomplete/quarantined artifact; do not overwrite or auto-repair it.

The receipt contains source/origin/output hashes, logical digest, state, operation, permit/run/digest and source receipt digest. It contains no signing secret or real record. New receipt/evidence files are synthetic operational metadata outside the application DB, not an eighth business table.

### Restore sequence — VERIFIED in fixtures

A separate restore permit pins the completed backup file and exact receipt hash. The package requires matching sealed result evidence, completed original claim, logical content/state and file hash. It restores through the same engine API into an exclusively new `restore.sqlite`, verifies all schema/data/ledger/integrity/FK evidence, and chains the new receipt to the original source and backup receipt. Original backup/source hashes remain unchanged. Missing, corrupted, modified, partially published or mismatched backups/receipts are rejected.

Restore never dispatches a migration. A separately authorized simulated working_migrate or runtime_activate request still returns **disabled** metadata. Successful API calls are evidence for these fixtures, not proof of safe real backup custody, source quiescence or physical storage durability.

## 6. Negative and crash/recovery evidence

| Requirement | Executed synthetic evidence |
|---|---|
| Missing/invalid/expired authorization, operator/target/action/hash mismatch, environment substitution | New denial matrix asserts zero target access/no destination; original OSG signature/replay/time tests retained. |
| Replay and register failure | Reused permit/run consumed; torn/missing register and lost result evidence refuse; no implicit initialization/reset. |
| Release mismatch / unknown migration / synthetic leakage / direct path | Rejected before target access; repinned unreviewed source still refused. Original CAR pre-access direct-artifact guard tests retained. |
| Schema/ledger drift and ambiguous migration completion | New foundation/index/ledger/gap negative fixtures quarantine; no ledger adoption or repair. CAR actual post-DDL/pre-log subprocess crash retained. |
| Existing backup/restore destinations | Sentinel bytes unchanged; new authorization still cannot overwrite a previous incomplete destination. |
| Corrupt backup / receipt / provenance | Wrong header, changed file/hash, changed receipt content/origin or missing completion evidence deny restore. |
| Interrupted backup/restore | Eight controlled exception boundaries cover reservation, API completion, before receipt and receipt publication for both operations; claims consumed, incomplete files held. |
| Real process termination | **Six subprocess kills**: reservation, API completion and receipt publication for backup and restore. Restart rejects old claims; no automatic overwrite/recovery. Receipt-publication crash cannot authorize restore. |
| SQLite busy/locked | Exclusive fixture writer causes classification quarantine before destination creation. PDO/SQLite3 timeouts are zero; no whole-operation retries or permit refunds. |
| Integrity/FK | Valid copies checked independently; deliberate orphan fixture rejected before backup; no FK disabling in tooling. |
| Accepted contracts | Existing B/C tenant, IDs/AAD/crypto, OTP/proof, R06 pending debt, audit append-only and migrator safety assertions rerun unchanged in full regression. |

These process-kill tests validate application/process recovery states, **not power-loss durability**. No checkpoint, journal recovery or automatic incident repair is part of this tooling.

## 7. Actual commands, results and development failures

Existing `C:\Users\hp\.config\herd\bin\php85\php.exe`, from `XLipa/backend`:

```text
<php85> vendor/bin/pint --dirty --format agent
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result --filter SyntheticRecoveryPackageTest
<php85> vendor/phpunit/phpunit/phpunit --no-progress --do-not-cache-result
```

| Final execution | Tests / assertions | Failures / errors | Duration |
|---|---|---|---|
| Targeted recovery after final formatting | **47 / 193** | **0 / 0** | **21,438 ms** |
| Full combined regression | **358 / 3,656** | **0 / 0** | **150,270 ms** |

The full command re-executed all previous 311 cases plus 47 new recovery cases, including existing B/C and authorizer/guard regressions. It includes the original 108 application regressions; no separate run is invented and no historical pass is relabeled as a fresh execution.

Final totals are actual executions, not forecasts. No existing test was weakened, removed or skipped. Full tests use the established isolated bootstrap with no working dotenv, public fixture keys, memory/guarded disposable SQLite, inert mail/queue and blocked stray Laravel HTTP calls. No generic application setup/install or working Artisan operation ran.

Development history is retained: an initial long-running suite was interrupted while diagnosing lock waits; a small disposable API probe returned true, and explicit zero-timeout PDO handling was added. A subsequent 36-case/150-assertion development run passed. Expanded 46-case run passed 45 with one fixture error: updating both applications to one orphan merchant first violated their UNIQUE key; the fixture was corrected to update one row, preserving the intended independent FK case and all constraints. Another development run overlapped formatting/source initialization edits and failed with missing result-evidence fixture initialization; it is discarded as a mixed revision, not counted as acceptance evidence. Runs were then made sequential after formatter completion. The final provenance workflow control passed 1/20, followed by final targeted results above.

Pint formatted only new PHP files. Runtime/test/formatter escalation was allowed; no automatic approval rejection occurred. A read-only process-command-line diagnostic was denied by Windows CIM permissions; no permissions/service changes were made. Interrupted synthetic leftovers were removed only after checking their exact resolved temp targets. No working file was involved.

## 8. Preservation evidence

Source-only in-memory SHA-256 baseline: **230 pre-existing tracked/nonignored-untracked files**, excluding all SQLite paths/sidecars and `.env` variants before hashing. No working SQLite/sidecar or real environment/secret file was opened, hashed, copied, backed up or restored. Earlier FRR-2.0 observations are historical here; no new working preservation hash is claimed.

Only the four new PHP files and this report are intended additions. Original migration history, accepted synthetic migrations, C artifact/frozen SQL/working-disabled manifest, accepted permit/register/selector/classifier, old tests/reports, frontend and unrelated inventoried files are preserved. No preservation inventory artifact was written. Ignored dependency/cache/recovery trees are not exhaustively certified.

**VERIFIED final post-report comparison:** all **230 source-baseline files unchanged**, zero changed/missing. Exactly five additions were found: the four PHP files and this report. No matching recovery, offline register/release or backup-probe temporary directory remained. `git diff --check` passed; HEAD remains `b8bec62ee52fd8fa610c5289fd99692b88b75f6d`. This final report-only edit records those results. No stage/commit/push/deploy or real provider action occurred.

## 9. Remaining operational blockers and recommendation

**NOT TESTED / NOT PROVISIONED:** real signer/public-key custody and rotation, authenticated real operator/host identity, trusted production clock/revocation, Windows file-ID/directory leases and malicious alias/race resistance, restrictive custody ACLs/encryption/retention, source write quiescence, OneDrive/filesystem controls, physical disk-full/I/O/power-loss behavior, real WAL/hot-journal handling and independently protected anti-rollback register storage. Fixture path restrictions and hash chains are not an OS sandbox against privileged PHP/filesystem actors.

The PHP backup API can complete consistently in tested quiescent fixtures; its runtime capability is established here. This is **not** approval to apply it to real storage. A future working tool needs its separately reviewed target identity lease, immutable staging, custody/maintenance scope, exact selected command and independent evidence handling. No live daemon, production command, scheduler or working execution flag is installed.

**GO for owner/technical review of this bounded isolated package.** No change to accepted B OTP/proof/encryption/tenant/audit policies or seven-table scope was needed. Do not reopen accepted B merely because later operational gates remain.

**Next proposed approval:** accept this isolated result and separately finalize named operator/custodian, protected backup/restore destinations, vetted working-only tool/identity protections, quiescence/sync policy, retention/RPO/RTO and exact per-action execution packages. Any further implementation should remain synthetic until explicitly reviewed. Do not repeat successful historical reviews without new evidence; revalidate target freshness only when a future authorized action depends on it.

Separate authorization is still required for **working read-only revalidation, working backup, real-data restore rehearsal, working migration, runtime activation, real identity/contact processing and each external provider/payment action**. None follows automatically from another. This package does not provide real operational signing authority or an executable working migration.

**WORKING ACTIVATION REMAINS NO-GO. STOP FOR OWNER REVIEW.** XLipa is a WEBSITE supporting TZS only.
