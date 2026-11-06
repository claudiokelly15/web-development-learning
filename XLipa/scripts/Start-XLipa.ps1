param([switch]$NoBrowser)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
& (Join-Path $PSScriptRoot 'Sync-Frontend.ps1')
$siteLink = Join-Path $env:USERPROFILE '.config/herd/config/valet/Sites/xlipa'
$backendPath = [IO.Path]::GetFullPath((Join-Path $projectRoot 'backend'))
$linkItem = Get-Item -LiteralPath $siteLink -ErrorAction SilentlyContinue
if (!$linkItem -or !($linkItem.Target | Where-Object { $_.TrimEnd('\', '/') -eq $backendPath.TrimEnd('\', '/') })) {
    throw 'The xlipa Herd link does not point to this backend. Review Herd mapping before continuing.'
}
Write-Host 'Herd serves XLipa at http://xlipa.test. No artisan serve process is needed.'
if (!$NoBrowser) {
    Start-Process -FilePath 'http://xlipa.test'
}
