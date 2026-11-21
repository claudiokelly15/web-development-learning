[CmdletBinding()]
param([int]$Port = 18761, [string]$PhpPath = "$env:USERPROFILE/.config/herd/bin/php85/php.exe", [string]$NodePath = 'C:/Program Files/nodejs/node.exe')
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$statePath = Join-Path $PSScriptRoot 'demo-state.json'
if (Test-Path -LiteralPath $statePath) { throw 'Demo state already exists. Run stop-demo.ps1 first.' }
if ($Port -lt 1024 -or $Port -gt 65535) { throw 'Invalid demo port.' }
if (!(Test-Path -LiteralPath $PhpPath) -or !(Test-Path -LiteralPath $NodePath)) { throw 'PHP 8.5 and Node are required. Supply -PhpPath / -NodePath if needed.' }
$probe = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $Port)
try { $probe.Start() } finally { $probe.Stop() }
$backend = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../backend'))
$guards = @{
    'tests/bootstrap-isolated.php' = '59b4cf51b41db2e04cb4fa182f6c70ddfe2fe0b679950b0be6b36f6602fce2c7'
    'tests/TestCase.php' = '074e45bedd5b26a24499199ff40496d16c5b6d3a23374ef636704676aac5829f'
    'phpunit.xml' = 'd543b9dc5c383f067500469c80ba45550c59de0b4eb9568cddd1d45c0e7bf749'
}
foreach ($guard in $guards.GetEnumerator()) {
    if ((Get-FileHash -LiteralPath (Join-Path $backend $guard.Key) -Algorithm SHA256).Hash.ToLowerInvariant() -ne $guard.Value) { throw "Isolation guard mismatch: $($guard.Key)" }
}
$runtime = (& $PhpPath -d auto_prepend_file= -d auto_append_file= -v) -join "`n"
if ($LASTEXITCODE -ne 0 -or $runtime -notmatch '^PHP 8\.5\.') { throw 'PHP 8.5 CLI is required.' }
foreach ($sentinel in @('__no_real_environment','__no_real_environment.testing','__absent_synthetic_config.php','__absent_synthetic_routes.php')) {
    if (Test-Path -LiteralPath (Join-Path $backend "tests/$sentinel")) { throw "Unsafe synthetic sentinel exists: $sentinel" }
}
$token = [Guid]::NewGuid().ToString('N')
$export = Join-Path $PSScriptRoot "demo-export-$token"
$null = New-Item -ItemType Directory -Path $export
$previousExport = $env:XLIPA_DEMO_EXPORT
$process = $null
try {
    $env:XLIPA_DEMO_EXPORT = $export
    Push-Location $backend
    try {
        & $PhpPath -d auto_prepend_file= -d auto_append_file= -d allow_url_fopen=0 -d allow_url_include=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,socket_sendto,socket_sendmsg vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --bootstrap tests/bootstrap-isolated.php --no-progress --do-not-cache-result --filter test_export_safe_browser_demo tests/Feature/SafeBrowserDemoExportTest.php
        if ($LASTEXITCODE -ne 0) { throw 'Isolated demo export failed. Server was not started.' }
    } finally { Pop-Location }
    $server = Join-Path $PSScriptRoot 'demo-server.cjs'
    $process = Start-Process -FilePath $NodePath -ArgumentList @("`"$server`"", "`"$export`"", "$Port", $token) -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $export 'server.log') -RedirectStandardError (Join-Path $export 'server-error.log')
    $ready = $false
    for ($attempt = 0; $attempt -lt 40; $attempt++) {
        Start-Sleep -Milliseconds 250
        if ($process.HasExited) { throw 'Demo server exited. Inspect the owned export server-error.log.' }
        try { $health = Invoke-RestMethod "http://127.0.0.1:$Port/__demo-health"; if ($health.token -eq $token) { $ready = $true; break } } catch { }
    }
    if (!$ready) { throw 'Demo server did not pass its ownership health check.' }
    @{ pid = $process.Id; startTime = $process.StartTime.ToUniversalTime().ToString('o'); export = $export; token = $token; port = $Port; server = $server } | ConvertTo-Json | Set-Content -LiteralPath $statePath
    Write-Host "VERIFIED static visual demo: http://127.0.0.1:$Port/ ($($health.pages) pages). No login required."
    Write-Host 'Stop: powershell -ExecutionPolicy Bypass -File XLipa/scripts/stop-demo.ps1'
} catch {
    if ($process -and !$process.HasExited) { Stop-Process -Id $process.Id }
    throw
} finally { $env:XLIPA_DEMO_EXPORT = $previousExport }
