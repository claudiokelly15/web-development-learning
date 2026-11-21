[CmdletBinding()]
param()
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$statePath = Join-Path $PSScriptRoot 'demo-state.json'
if (!(Test-Path -LiteralPath $statePath)) { Write-Host 'No owned demo is recorded.'; return }
$state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
$ownedRoot = [IO.Path]::GetFullPath($state.export)
if ([IO.Path]::GetDirectoryName($ownedRoot) -ne $PSScriptRoot -or [IO.Path]::GetFileName($ownedRoot) -cne "demo-export-$($state.token)" -or $state.token -notmatch '^[a-f0-9]{32}$') { throw 'Invalid owned export path.' }
$process = Get-Process -Id $state.pid -ErrorAction SilentlyContinue
if ($process) {
    $command = Get-CimInstance Win32_Process -Filter "ProcessId = $($state.pid)"
    if ($process.StartTime.ToUniversalTime().ToString('o') -ne $state.startTime -or $process.ProcessName -ne 'node' -or !$command.CommandLine.Contains($state.token) -or !$command.CommandLine.Contains($state.server)) { throw 'Process ownership mismatch; no process stopped.' }
    Stop-Process -Id $process.Id
    $process.WaitForExit(5000) | Out-Null
}
if (Test-Path -LiteralPath $ownedRoot) {
    $item = Get-Item -LiteralPath $ownedRoot
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Refusing reparse-point cleanup.' }
    $children = @(Get-ChildItem -LiteralPath $ownedRoot -Force)
    foreach ($child in $children) {
        if ($child.PSIsContainer -or ($child.Attributes -band [IO.FileAttributes]::ReparsePoint) -or $child.Name -notmatch '^([a-z-]+\.html|pages\.json|server(-error)?\.log)$') { throw 'Unexpected export resource; cleanup stopped.' }
    }
    foreach ($child in $children) { Remove-Item -LiteralPath $child.FullName }
    Remove-Item -LiteralPath $ownedRoot
}
Remove-Item -LiteralPath $statePath
Write-Host 'Stopped owned XLipa demo and removed its disposable snapshots.'
