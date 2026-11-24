param(
    [Parameter(Mandatory = $true)][string]$Src,
    [Parameter(Mandatory = $true)][string]$Zip
)

if (-not (Test-Path -LiteralPath $Src)) {
    throw "No existe la carpeta: $Src"
}

$items = Get-ChildItem -LiteralPath $Src -Force | Where-Object {
    $_.Name -ne 'state.json' -and $_.Extension -ne '.log' -and $_.Name -notlike '*.log.*'
}

if (-not $items) {
    throw 'dist_instalador vacio'
}

if (Test-Path -LiteralPath $Zip) {
    Remove-Item -LiteralPath $Zip -Force
}

Compress-Archive -Path ($items.FullName) -DestinationPath $Zip -Force
Write-Host "ZIP OK: $Zip"
