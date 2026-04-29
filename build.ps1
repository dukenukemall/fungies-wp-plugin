#!/usr/bin/env pwsh
# Build a WordPress-ready plugin zip via `git archive`.
#
# Why git archive (and NOT Compress-Archive):
#   PowerShell's Compress-Archive writes Windows-style backslash path
#   separators inside zip entries. WordPress on Linux hosts can't extract
#   such zips and shows "Plugin file does not exist" on activation.
#   `git archive` writes standard forward-slash entries that work on every
#   host. This regression already bit us in v2.1.8 -- never use
#   Compress-Archive for the plugin zip.
#
# Usage:
#   ./build.ps1                 # auto-reads version from fungies-wp-plugin.php
#   ./build.ps1 -Version 2.2.0  # override
#
# Output:
#   ./fungies-checkout-<version>.zip   (top-level folder = fungies-checkout-<version>/)
#
# Requires:
#   * git on PATH
#   * working tree committed to HEAD (git archive reads HEAD, not the
#     working copy, so commit your changes first)

[CmdletBinding()]
param(
    [string]$Version
)

$ErrorActionPreference = 'Stop'

$pluginFile = Join-Path $PSScriptRoot 'fungies-wp-plugin.php'
if (-not (Test-Path $pluginFile)) {
    throw "Cannot find fungies-wp-plugin.php next to build.ps1."
}

if ([string]::IsNullOrWhiteSpace($Version)) {
    $headerVersion = (Select-String -Path $pluginFile -Pattern '^\s*\*\s*Version:\s*([\d\.]+)' | Select-Object -First 1)
    if (-not $headerVersion) {
        throw "Could not parse Version header from fungies-wp-plugin.php. Pass -Version explicitly."
    }
    $Version = $headerVersion.Matches[0].Groups[1].Value
}

$constLine = Select-String -Path $pluginFile -Pattern "FUNGIES_WP_VERSION',\s*'([\d\.]+)'" | Select-Object -First 1
if ($constLine) {
    $constVersion = $constLine.Matches[0].Groups[1].Value
    if ($constVersion -ne $Version) {
        throw "Version mismatch: header=$Version, FUNGIES_WP_VERSION constant=$constVersion. Sync them first."
    }
}

$readmeFile = Join-Path $PSScriptRoot 'readme.txt'
if (Test-Path $readmeFile) {
    $stable = Select-String -Path $readmeFile -Pattern '^Stable tag:\s*([\d\.]+)' | Select-Object -First 1
    if ($stable -and $stable.Matches[0].Groups[1].Value -ne $Version) {
        throw "readme.txt 'Stable tag' is $($stable.Matches[0].Groups[1].Value) but plugin Version is $Version. Sync them first."
    }
}

$zipName = "fungies-checkout-$Version.zip"
$zipPath = Join-Path $PSScriptRoot $zipName
$prefix  = "fungies-checkout-$Version/"

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Push-Location $PSScriptRoot
try {
    & git archive --format=zip --prefix=$prefix -o $zipName HEAD
    if ($LASTEXITCODE -ne 0) {
        throw "git archive failed with exit code $LASTEXITCODE."
    }
} finally {
    Pop-Location
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $bad = $zip.Entries | Where-Object { $_.FullName -match '\\' } | Select-Object -First 1
    if ($bad) {
        throw "Zip entry uses backslash separator: $($bad.FullName). Refusing to ship."
    }
    $entryCount = $zip.Entries.Count
} finally {
    $zip.Dispose()
}

$size = (Get-Item $zipPath).Length
Write-Host "OK  $zipName  ($entryCount entries, $size bytes)"
Write-Host "    All paths use forward slashes."
