$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$staging = Join-Path $root '.package-staging'
$package = Join-Path $root 'websky_analyticspro.ocmod.zip'

if (Test-Path -LiteralPath $staging) { Remove-Item -LiteralPath $staging -Recurse -Force }
New-Item -ItemType Directory -Path $staging | Out-Null
Copy-Item -LiteralPath (Join-Path $root 'install.json') -Destination $staging
Copy-Item -LiteralPath (Join-Path $root 'extension\websky_analyticspro\admin') -Destination (Join-Path $staging 'admin') -Recurse
Copy-Item -LiteralPath (Join-Path $root 'extension\websky_analyticspro\catalog') -Destination (Join-Path $staging 'catalog') -Recurse

if (Test-Path -LiteralPath $package) { Remove-Item -LiteralPath $package -Force }
Compress-Archive -Path (Join-Path $staging '*') -DestinationPath $package -CompressionLevel Optimal
Remove-Item -LiteralPath $staging -Recurse -Force
Write-Output $package

