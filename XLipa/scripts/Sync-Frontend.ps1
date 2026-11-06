$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$viewsPath = Join-Path $projectRoot 'backend/resources/views/xlipa'
$assetsPath = Join-Path $projectRoot 'backend/public/assets'
New-Item -ItemType Directory -Path $viewsPath, $assetsPath -Force | Out-Null
$referencePaths = @(
    (Join-Path $projectRoot 'frontend/bongopay1.html'),
    (Join-Path $viewsPath 'bongopay1.blade.php')
)
if ($referencePaths | Where-Object { Test-Path -LiteralPath $_ }) {
    throw 'External reference files must be archived outside frontend and Blade views before syncing.'
}
Get-ChildItem -LiteralPath (Join-Path $projectRoot 'frontend') -Filter '*.html' -File | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $viewsPath ($_.BaseName + '.blade.php')) -Force
}
Copy-Item -Path (Join-Path $projectRoot 'frontend/assets/*') -Destination $assetsPath -Recurse -Force
Write-Host 'Frontend synced. Open the canonical Herd URL: http://xlipa.test'
