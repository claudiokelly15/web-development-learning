[CmdletBinding()]
param(
    [ValidateSet('Start','Stop','Status')][string]$Operation = 'Status',
    [string]$PhpPath = "$env:USERPROFILE/.config/herd/bin/php85/php.exe",
    [ValidateSet('foundation','onboarding','payments')][string]$Profile = 'foundation'
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$anchor = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '.staging'))
$statePath = Join-Path $anchor 'server.json'
$router = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot 'staging-router.php'))
$control = Join-Path $PSScriptRoot 'staging-control.php'
$phpOptions = @('-d','auto_prepend_file=','-d','auto_append_file=','-d','allow_url_fopen=0','-d','allow_url_include=0',
    '-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,socket_sendto,socket_sendmsg,mail,exec,shell_exec,system,passthru,popen,proc_open')

function Assert-PlainPath([string]$Target) {
    $cursor = [IO.Path]::GetFullPath($Target)
    while ($cursor) {
        if (Test-Path -LiteralPath $cursor) {
            $item = Get-Item -LiteralPath $cursor -Force
            if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Reparse point denied.' }
        }
        $cursor = Split-Path -Parent $cursor
    }
}
Assert-PlainPath $anchor

if ($Operation -eq 'Stop') {
    if (!(Test-Path -LiteralPath $statePath)) { throw 'No owned staging server state. No process stopped.' }
    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    if ($state.runtime -notmatch '^run-[a-f0-9]{32}$') { throw 'Invalid staging runtime.' }
    $root = [IO.Path]::GetFullPath((Join-Path $anchor $state.runtime))
    if ((Split-Path -Parent $root) -ne $anchor) { throw 'Unsafe retirement target.' }
    Assert-PlainPath $root
    $owner = Get-Content -LiteralPath (Join-Path $root 'owner.json') -Raw | ConvertFrom-Json
    if ($owner.runtime -ne $state.runtime -or $owner.generation -ne $state.runtime -or $owner.namespace -ne 'xlipa-fictional-r21') { throw 'Ownership mismatch.' }
    # Retire first, even if the process already exited. No source or store deletion.
    Set-Content -LiteralPath (Join-Path $root 'retired') -Value 'retired fictional generation'
    $process = Get-CimInstance Win32_Process -Filter "ProcessId=$([int]$state.pid)"
    if ($process) {
        $started = (Get-Process -Id $state.pid).StartTime.ToUniversalTime().ToString('o')
        if ($started -ne $state.startTime -or $process.ExecutablePath -ne $state.executable -or !$process.CommandLine.Contains($router) -or !$process.CommandLine.Contains($root)) { throw 'Process ownership uncertain; runtime retired, process retained.' }
        Stop-Process -Id $state.pid
    }
    Move-Item -LiteralPath $statePath -Destination (Join-Path $root 'stopped-server.json')
    Write-Host 'Staging stopped and retired. Stores/evidence retained; startup creates a fresh generation.'
    exit
}
if ($Operation -eq 'Status') {
    Invoke-RestMethod -Uri 'http://127.0.0.2:18762/staging/status' -Proxy $null -TimeoutSec 8 | ConvertTo-Json
    exit
}
if (Test-Path -LiteralPath $statePath) { throw 'Staging state exists. Verify and stop the owned runtime before starting a new generation.' }
$probe = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Parse('127.0.0.2'),18762)
try { $probe.Start() } finally { $probe.Stop() }
if (!(Test-Path -LiteralPath $anchor)) { $null = New-Item -ItemType Directory -Path $anchor }
# Establish the local custodian boundary before generating any staging key.
$sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
& icacls.exe $anchor '/inheritance:r' '/grant:r' "*$($sid):(OI)(CI)F" '*S-1-5-18:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Staging ACL setup failed.' }
$output = & $PhpPath @phpOptions $control provision $Profile
if ($LASTEXITCODE -ne 0) { throw 'Disposable provisioning failed. No server started.' }
$identity = $output | ConvertFrom-Json
if ($identity.runtime -notmatch '^run-[a-f0-9]{32}$') { throw 'Invalid provisioned identity.' }
$root = [IO.Path]::GetFullPath((Join-Path $anchor $identity.runtime))
Assert-PlainPath $root
$emptyPublic = Join-Path $root 'empty-public'
$null = New-Item -ItemType Directory -Path $emptyPublic
$previousRun = $env:XLIPA_STAGING_RUN
$process = $null
try {
    $env:XLIPA_STAGING_RUN = $identity.runtime
    $arguments = $phpOptions + @('-S','127.0.0.2:18762','-t',"`"$emptyPublic`"","`"$router`"")
    $process = Start-Process -FilePath $PhpPath -ArgumentList $arguments -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $root 'server.log') -RedirectStandardError (Join-Path $root 'server-error.log')
    @{ runtime=$identity.runtime; pid=$process.Id; startTime=$process.StartTime.ToUniversalTime().ToString('o'); executable=[IO.Path]::GetFullPath($PhpPath) } | ConvertTo-Json | Set-Content -LiteralPath $statePath
    $ready = $false
    for ($attempt=0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Milliseconds 200
        try {
            $health = Invoke-RestMethod -Uri 'http://127.0.0.2:18762/staging/status' -Proxy $null -TimeoutSec 2
            if ($health.runtime -eq $identity.runtime -and $health.sapi -eq 'cli-server' -and !$health.console -and !$health.testing) { $ready=$true; break }
        } catch { }
    }
    if (!$ready) { throw 'Staging health failed; retiring the owned runtime.' }
    Write-Host "VERIFIED fictional Laravel staging: http://127.0.0.2:18762/ ($($identity.runtime))"
} catch {
    Set-Content -LiteralPath (Join-Path $root 'retired') -Value 'startup failed; fictional generation retired'
    if ($process -and !$process.HasExited) { Stop-Process -Id $process.Id }
    if (Test-Path -LiteralPath $statePath) { Move-Item -LiteralPath $statePath -Destination (Join-Path $root 'failed-server.json') }
    throw
} finally { $env:XLIPA_STAGING_RUN = $previousRun }
