[CmdletBinding()]
param(
    [string]$PhpPath
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$verificationExitCode = 1
$stage = 'preflight'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$backendRoot = Join-Path $projectRoot 'backend'
$temporaryDirectory = $null
$sourceBefore = @{}

function Assert-Hash([string]$RelativePath, [string]$ExpectedHash) {
    $path = [IO.Path]::GetFullPath((Join-Path $backendRoot $RelativePath))
    if (!$path.StartsWith($backendRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Source pin escapes backend scope: $RelativePath"
    }
    if (!(Test-Path -LiteralPath $path -PathType Leaf) -or (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant() -ne $ExpectedHash) {
        throw "Source pin mismatch: $RelativePath"
    }
}

function Invoke-CheckedPhp([string[]]$PhpArguments) {
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = @(& $script:phpExecutable @script:phpOptions @PhpArguments 2>&1)
        $nativeExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousPreference
    }
    if ($nativeExitCode -ne 0) {
        $output | ForEach-Object { Write-Host "$_" }
        $script:verificationExitCode = $nativeExitCode
        throw "PHP command failed with exit $nativeExitCode."
    }
    return $output
}

try {
    if (!(Test-Path -LiteralPath (Join-Path $backendRoot 'composer.lock') -PathType Leaf)) {
        throw 'Run this script from its XLipa/scripts location with the backend present.'
    }
    if (!(Get-Command git -CommandType Application -ErrorAction SilentlyContinue)) {
        throw 'Git is required for project-scoped preservation and tracked-path checks.'
    }
    # These are reviewed isolation guards, not a generated replacement manifest.
    $isolationPins = @{
        'tests/bootstrap-isolated.php' = '59b4cf51b41db2e04cb4fa182f6c70ddfe2fe0b679950b0be6b36f6602fce2c7'
        'tests/TestCase.php' = '074e45bedd5b26a24499199ff40496d16c5b6d3a23374ef636704676aac5829f'
        'phpunit.xml' = 'd543b9dc5c383f067500469c80ba45550c59de0b4eb9568cddd1d45c0e7bf749'
        'tests/Support/B32CRehearsal.php' = '911957b563ccbdfa13d9d9676d104c1ce236f266b9792c8c5e4ecb944d0776cf'
    }
    foreach ($pin in $isolationPins.GetEnumerator()) { Assert-Hash $pin.Key $pin.Value }
    foreach ($sentinel in @('tests/__no_real_environment', 'tests/__no_real_environment.testing', 'tests/__absent_synthetic_config.php', 'tests/__absent_synthetic_routes.php')) {
        if (Test-Path -LiteralPath (Join-Path $backendRoot $sentinel)) { throw "Unexpected synthetic environment/cache path: $sentinel" }
    }
    $tracked = @(& git -C $projectRoot ls-files --full-name -- .)
    if ($LASTEXITCODE -ne 0 -or $tracked.Count -eq 0) { throw 'Cannot inspect project-scoped Git paths.' }
    $unsafeTracked = @($tracked | Where-Object {
        $_ -match '(?i)(^|/)(\.env($|\.(?!example$))|auth\.json$|credentials($|\.)|id_rsa($|\.)|id_ed25519($|\.))|\.(pem|key|p12|pfx|sqlite[^/]*|db|sql|sql\.gz|dump)$'
    })
    if ($unsafeTracked.Count -gt 0) {
        throw ('Review tracked sensitive paths before verification: ' + ($unsafeTracked -join ', '))
    }

    $manifestPins = @{
        'database/activation/bnc1/manifest.json' = 'f8c071e83f9e8568c00b79c176a55c59f63ff51abcaa89f098d43d1fb2746747'
        'database/activation/bnc1/integration-manifest.json' = '69c6d7967ce4bd004e283b5800a5c930a267b9fab5e57b0228e28e6ad6079b40'
        'database/activation/bnc1/backend-manifest.json' = '688826601d9993427e478cf946cb82f09cbacfb749a201a0f10543f8e7b39a47'
    }
    $frozenPins = @{}
    foreach ($pin in $manifestPins.GetEnumerator()) {
        Assert-Hash $pin.Key $pin.Value
        $manifest = Get-Content -LiteralPath (Join-Path $backendRoot $pin.Key) -Raw | ConvertFrom-Json
        foreach ($group in @('base_pins', 'source_pins')) {
            if ($manifest.PSObject.Properties.Name -contains $group) {
                foreach ($source in $manifest.$group.PSObject.Properties) { $frozenPins[$source.Name] = $source.Value }
            }
        }
    }
    # Verify the C manifest before trusting its foundation map.
    foreach ($pin in $frozenPins.GetEnumerator()) { Assert-Hash $pin.Key $pin.Value }
    $selection = Get-Content -LiteralPath (Join-Path $backendRoot 'database/activation/b32c/selection-manifest.json') -Raw | ConvertFrom-Json
    foreach ($pin in $selection.foundation.PSObject.Properties) {
        $frozenPins['database/migrations/' + $pin.Name + '.php'] = $pin.Value
    }
    foreach ($pin in $frozenPins.GetEnumerator()) { Assert-Hash $pin.Key $pin.Value }
    $int05Pins = @{
        'app/Actions/Merchants/BusinessNameCompletionHttpInput.php' = '75acd1fde08dee93491c642b32a1f333b843cc004e18c3a9f8a3ea44a9819fc8'
        'app/Actions/Merchants/BusinessNameCompletionRequestContext.php' = '4ec06db95abb7417953a857bdcb620bd54017b6105d82b7b7495bbfa7e5677a3'
        'tests/Support/FakeBusinessNameCompletionRequestContext.php' = 'b5e6553ff8d73a4d706791aa3909f2c0e64f0abc30b1d529eaf155a9893e2f57'
        'tests/Feature/BusinessNameCompletionHttpPreparationTest.php' = '3666e5c816fb83714292b58f5996a2a7424a26592ba04f9f1d20ef568c720f89'
    }
    foreach ($pin in $int05Pins.GetEnumerator()) { Assert-Hash $pin.Key $pin.Value }
    Write-Host "PASS frozen pins ($($frozenPins.Count)), manifest digests (3), INT-05 pins (4), isolation guards (4)"

    if ($PhpPath) {
        $phpExecutable = (Get-Item -LiteralPath $PhpPath).FullName
    } else {
        $phpCommand = Get-Command php -CommandType Application -ErrorAction SilentlyContinue
        if ($phpCommand) {
            $phpExecutable = $phpCommand.Source
        } else {
            $phpExecutable = Join-Path $env:USERPROFILE '.config/herd/bin/php85/php.exe'
        }
    }
    if (!(Test-Path -LiteralPath $phpExecutable -PathType Leaf)) { throw 'PHP is unavailable. Supply -PhpPath with an existing PHP 8.5 CLI executable.' }
    foreach ($required in @('vendor/autoload.php', 'vendor/phpunit/phpunit/phpunit', 'vendor/bin/pint')) {
        if (!(Test-Path -LiteralPath (Join-Path $backendRoot $required) -PathType Leaf)) { throw "Missing development dependency: $required. Install reviewed locked dependencies separately." }
    }
    $phpOptions = @('-d', 'auto_prepend_file=', '-d', 'auto_append_file=', '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0',
        '-d', 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,socket_sendto,socket_sendmsg')
    $runtime = (Invoke-CheckedPhp @('-v')) -join "`n"
    if ($runtime -notmatch '^PHP 8\.5\.\d+.*\(cli\)') { throw 'Verification requires PHP 8.5 CLI, matching the reviewed local runtime.' }
    $extensions = @(Invoke-CheckedPhp @('-m'))
    foreach ($extension in @('pdo_sqlite', 'sqlite3', 'mbstring', 'dom', 'xml', 'xmlwriter', 'tokenizer', 'fileinfo')) {
        if ($extensions -notcontains $extension) { throw "Missing PHP extension: $extension" }
    }
    Write-Host "PASS $($runtime.Split([Environment]::NewLine)[0]) ($phpExecutable)"

    $regressionClasses = @('BusinessNameCompletionTest', 'BusinessNameCompletionSchemaTest', 'BusinessNameCompletionHttpPreparationTest',
        'BusinessNameCompletionUiTest', 'TrustedCompletionContractsTest', 'WorkflowSchemaReadinessTest', 'MerchantOnboardingPreparationTest',
        'MerchantDashboardTest', 'MerchantAccessTest', 'AuthenticationTest', 'MerchantApplicationSubmissionTest', 'AdminApplicationReviewTest', 'TrustedHostsTest')
    $functionalFile = 'tests/Feature/FunctionalMerchantOnboardingTest.php'
    $regressionFiles = @($regressionClasses | ForEach-Object { "tests/Feature/$_.php" })
    $formatFiles = @($functionalFile, 'tests/Support/MerchantOnboardingHttpHarness.php') + $regressionFiles
    $syntaxFiles = @()
    foreach ($directory in @('app', 'bootstrap', 'config', 'routes', 'database/migrations', 'database/activation', 'tests')) {
        $syntaxFiles += @(Get-ChildItem -LiteralPath (Join-Path $backendRoot $directory) -Filter '*.php' -Recurse -File)
    }
    foreach ($file in $syntaxFiles) { $sourceBefore[$file.FullName] = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash }
    foreach ($path in @('composer.json', 'composer.lock', 'phpunit.xml') + @($manifestPins.Keys) + @($frozenPins.Keys)) {
        $fullPath = Join-Path $backendRoot $path
        $sourceBefore[$fullPath] = (Get-FileHash -LiteralPath $fullPath -Algorithm SHA256).Hash
    }

    Push-Location $backendRoot
    try {
        $stage = 'syntax'
        foreach ($file in $syntaxFiles) { $null = Invoke-CheckedPhp @('-l', $file.FullName) }
        Write-Host "PASS PHP syntax ($($syntaxFiles.Count) files)"
        $stage = 'Pint'
        $temporaryDirectory = Join-Path ([IO.Path]::GetTempPath()) ('xlipa-p0-' + [Guid]::NewGuid().ToString('N'))
        $null = New-Item -Path $temporaryDirectory -ItemType Directory
        $pintCache = Join-Path $temporaryDirectory 'pint.cache'
        Invoke-CheckedPhp (@('vendor/bin/pint', '--test', '--no-config', '--format', 'agent', '--cache-file', $pintCache) + $formatFiles) | ForEach-Object { Write-Host "$_" }
        Write-Host "PASS non-mutating Pint ($($formatFiles.Count) files)"
        $runner = @('vendor/phpunit/phpunit/phpunit', '--configuration', 'phpunit.xml', '--bootstrap', 'tests/bootstrap-isolated.php', '--no-progress', '--do-not-cache-result', '--stop-on-failure', '--stop-on-error')
        $stage = 'functional suite'
        Invoke-CheckedPhp ($runner + @($functionalFile)) | ForEach-Object { Write-Host "$_" }
        Write-Host 'PASS functional suite'
        $stage = 'bounded regression'
        Invoke-CheckedPhp ($runner + $regressionFiles) | ForEach-Object { Write-Host "$_" }
        Write-Host 'PASS bounded regression'
        $verificationExitCode = 0
    } finally {
        Pop-Location
    }
} catch {
    Write-Host "FAIL $stage - $($_.Exception.Message)"
} finally {
    foreach ($entry in $sourceBefore.GetEnumerator()) {
        if (!(Test-Path -LiteralPath $entry.Key) -or (Get-FileHash -LiteralPath $entry.Key -Algorithm SHA256).Hash -ne $entry.Value) {
            Write-Host "FAIL source preservation - $($entry.Key)"
            $verificationExitCode = 1
        }
    }
    if ($temporaryDirectory) {
        # Only known cache files inside this invocation's unique owned directory.
        foreach ($cacheName in @('pint.cache', 'pint.cache.lock')) {
            $cachePath = Join-Path $temporaryDirectory $cacheName
            if (Test-Path -LiteralPath $cachePath -PathType Leaf) { Remove-Item -LiteralPath $cachePath }
        }
        if (@(Get-ChildItem -LiteralPath $temporaryDirectory -Force).Count -eq 0) { Remove-Item -LiteralPath $temporaryDirectory }
    }
}
if ($verificationExitCode -eq 0) { Write-Host 'PASS isolated verification and source preservation' }
else { Write-Host "FAIL isolated verification (exit $verificationExitCode)" }
exit $verificationExitCode
