# Isolated verification

Run from any directory with Windows PowerShell 5.1 or PowerShell 7. The script resolves XLipa from its own location. It requires Git, an installed PHP 8.5 CLI and the existing locked development dependencies. PHP can be found on PATH or at the standard Herd PHP 8.5 location. An explicit executable can be supplied.

```powershell
powershell.exe -NoProfile -File C:/path/to/XLipa/scripts/verify-isolated.ps1 -PhpPath C:/path/to/php.exe
```

On PowerShell 7 or future Linux CI, use `pwsh -NoProfile -File ./scripts/verify-isolated.ps1`. The script returns zero on success, propagates a failing PHP process exit code, and returns one for preflight or preservation failures. It stops at the first failed stage. It installs no dependencies.

The script verifies the reviewed isolation bootstrap, base test case, PHPUnit XML and rehearsal helper by SHA-256 before running PHP. It rejects unexpected synthetic environment/cache files and tracked sensitive filenames. Frozen manifest definitions and source pins are checked independently against the existing historical digests. Guard changes require review of their safety and an explicit update to the verification script's guard pins.

PHP syntax covers first-party PHP under app, bootstrap, config, routes, migrations, activation and tests. Pint runs with `--test --no-config` on the functional test/harness and the thirteen regression classes. It uses a disposable cache. PHPUnit explicitly selects the isolated XML/bootstrap, disables result caching and stops on errors/failures. The functional suite and the established thirteen-class bounded regression run separately. Memory databases and existing test-owned temporary SQLite fixtures are permitted by their existing protections. Laravel's stray HTTP guard and disabled PHP network functions apply during tests. This local process restriction is complemented by a network namespace in the CI template.

The startup/frontend scripts serve other purposes and are independent of this entry point. Composer's existing `test` and installation hooks can bootstrap Artisan, so this entry point invokes the installed PHPUnit and Pint executables directly.

`ci-isolated.yml.template` is inactive outside `.github/workflows`. It targets a future separated, private XLipa repository with XLipa as its root. Before activation, merge `repository.gitignore.template`, review all intended files and history for secrets, replace both action SHA placeholders with reviewed full commit SHAs, and confirm the runner's PHP, PowerShell and network-namespace support. The template installs locked dependencies with Composer hooks/plugins disabled and runs an advisory audit. Installation needs outbound package access. Test execution uses an isolated network namespace and synthetic credentials from the established bootstrap. No remote CI run has been demonstrated.

The current shared repository ignores the working environment, database, auth.json and storage key paths. Supplemental future rules address alternate environment names, keys and database/dump names. Ignore rules do not remove already tracked files or historical secrets. The verifier's tracked-path checks report names only and cannot certify the entire repository history or detect every embedded credential.

The verification script does not grant operational admission. All current activation, submission, Chief Admin, identity-provider and payment holds continue to apply.
