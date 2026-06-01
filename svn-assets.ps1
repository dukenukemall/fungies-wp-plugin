#!/usr/bin/env pwsh
# Sync plugin marketing assets (screenshots, banners, icons) from this git
# repo into the WP.org SVN /assets/ directory (the top-level one, NOT inside
# /trunk or /tags). Assets are independent of plugin code — no version bump
# needed.
#
# Source layout (in the git repo):
#   assets/screenshots/01 Some Title.png  ->  <svn>/assets/screenshot-1.png
#   assets/screenshots/02 Other Title.png ->  <svn>/assets/screenshot-2.png
#   ...
#   assets/branding/icon-128x128.png      ->  <svn>/assets/icon-128x128.png
#   assets/branding/banner-772x250.png    ->  <svn>/assets/banner-772x250.png
#
# Screenshots are renamed by their leading "NN " ordinal (lowercase
# screenshot-N.png as required by WP.org). Banner/icon files in
# assets/branding/ are copied verbatim (their filenames already match the
# WP.org contract).
#
# Usage:
#   ./svn-assets.ps1            # DRY RUN — preview copies + MIME types
#   ./svn-assets.ps1 -Commit    # copy, propset MIME, svn add, svn ci

[CmdletBinding()]
param([switch]$Commit)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command svn -ErrorAction SilentlyContinue)) {
    throw "'svn' is not on PATH. See .kb/wordpress/plugin-dev/wp-org-tortoisesvn.qmd."
}

$gitRoot = $PSScriptRoot
$svnDir  = (Resolve-Path (Join-Path $gitRoot '..')).Path
$svnDir  = Join-Path $svnDir ((Split-Path $gitRoot -Leaf) + '-svn')
if (-not (Test-Path (Join-Path $svnDir '.svn'))) {
    throw "SVN checkout not found at $svnDir. Run ./svn-bootstrap.ps1 first."
}

$svnAssets = Join-Path $svnDir 'assets'
if (-not (Test-Path $svnAssets)) { New-Item -ItemType Directory -Path $svnAssets | Out-Null }

$plan = @()

$shotsDir = Join-Path $gitRoot 'assets/screenshots'
if (Test-Path $shotsDir) {
    $i = 0
    Get-ChildItem -Path $shotsDir -File | Where-Object { $_.Extension -match '\.(png|jpe?g)$' } | Sort-Object Name | ForEach-Object {
        $i++
        $ext = $_.Extension.ToLower().TrimStart('.')
        if ($ext -eq 'jpeg') { $ext = 'jpg' }
        $plan += [pscustomobject]@{ Src = $_.FullName; Dst = "screenshot-$i.$ext"; Ext = $ext }
    }
}

$brandDir = Join-Path $gitRoot 'assets/branding'
if (Test-Path $brandDir) {
    Get-ChildItem -Path $brandDir -File | Where-Object { $_.Name -match '^(banner|icon)' } | ForEach-Object {
        $ext = $_.Extension.ToLower().TrimStart('.')
        if ($ext -eq 'jpeg') { $ext = 'jpg' }
        $plan += [pscustomobject]@{ Src = $_.FullName; Dst = $_.Name; Ext = $ext }
    }
}

if ($plan.Count -eq 0) { Write-Host "No source assets found in assets/screenshots or assets/branding."; return }

Write-Host "Planned copies into $svnAssets :"
$plan | ForEach-Object { Write-Host ("  {0,-30}  <-  {1}" -f $_.Dst, (Split-Path $_.Src -Leaf)) }

if (-not $Commit) { Write-Host ""; Write-Host "DRY RUN. Re-run with -Commit to copy + svn add + svn ci."; return }

foreach ($p in $plan) {
    Copy-Item -Path $p.Src -Destination (Join-Path $svnAssets $p.Dst) -Force
}

Push-Location $svnDir
try {
    & svn add assets --force | Out-Null
    foreach ($p in $plan) {
        $mime = if ($p.Ext -eq 'png') { 'image/png' } elseif ($p.Ext -eq 'svg') { 'image/svg+xml' } else { 'image/jpeg' }
        & svn propset svn:mime-type $mime "assets/$($p.Dst)" | Out-Null
    }
    & svn ci -m "Update plugin marketing assets ($($plan.Count) file(s))"
    if ($LASTEXITCODE -ne 0) { throw "svn ci failed (exit $LASTEXITCODE)." }
    Write-Host "Done. CDN may take up to ~6 hours to refresh on the plugin page."
} finally {
    Pop-Location
}
