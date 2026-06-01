#!/usr/bin/env pwsh
# Publish a release of fungies-for-woocommerce to the WordPress.org SVN repo.
#
# Prerequisites (see .cursor/rules/wp-org-svn-release.mdc):
#   * Version already bumped in fungies-wp-plugin.php + readme.txt
#   * git push to main is done
#   * gh release create v<ver> ./fungies-checkout-<ver>.zip is done
#   * SVN checkout exists at the sibling dir (./svn-bootstrap.ps1 once)
#
# Flow:
#   1. Read version from fungies-wp-plugin.php (must match readme.txt)
#   2. git archive HEAD -> staging zip (honors .gitattributes export-ignore)
#   3. Wipe SVN trunk/ contents (keeping .svn metadata)
#   4. Unpack archive into trunk/
#   5. svn add/rm to sync
#   6. svn cp trunk tags/<version>
#   7. svn ci with a single commit (minimizes WP.org zip-rebuild churn)
#
# Usage:
#   ./svn-release.ps1                 # auto-detect version, dry-run preview
#   ./svn-release.ps1 -Commit         # actually commit to SVN

[CmdletBinding()]
param(
    [switch]$Commit
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command svn -ErrorAction SilentlyContinue)) {
    throw "'svn' is not on PATH. See .kb/wordpress/plugin-dev/wp-org-tortoisesvn.qmd."
}
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { throw "'git' not on PATH." }

$gitRoot = $PSScriptRoot
$svnDir  = (Resolve-Path (Join-Path $gitRoot '..')).Path
$svnDir  = Join-Path $svnDir ((Split-Path $gitRoot -Leaf) + '-svn')
if (-not (Test-Path (Join-Path $svnDir '.svn'))) {
    throw "SVN checkout not found at $svnDir. Run ./svn-bootstrap.ps1 first."
}

$pluginFile = Join-Path $gitRoot 'fungies-wp-plugin.php'
$ver = (Select-String -Path $pluginFile -Pattern '^\s*\*\s*Version:\s*([\d\.]+)' | Select-Object -First 1).Matches[0].Groups[1].Value
if (-not $ver) { throw "Could not parse Version header from fungies-wp-plugin.php." }

$readmeStable = (Select-String -Path (Join-Path $gitRoot 'readme.txt') -Pattern '^Stable tag:\s*([\d\.]+)').Matches[0].Groups[1].Value
if ($readmeStable -ne $ver) { throw "readme.txt Stable tag ($readmeStable) != plugin Version ($ver). Sync first." }

$tagDir = Join-Path $svnDir "tags/$ver"
if (Test-Path $tagDir) { throw "tags/$ver already exists in $svnDir. Bump the version or delete the stale tag dir." }

$tmpZip = Join-Path $env:TEMP "fungies-trunk-$ver.zip"
Write-Host "1/5  git archive HEAD -> $tmpZip"
Push-Location $gitRoot
try { & git archive --format=zip --prefix='' HEAD -o $tmpZip } finally { Pop-Location }

$trunk = Join-Path $svnDir 'trunk'
Write-Host "2/5  Wipe $trunk (preserving .svn)"
Get-ChildItem -Path $trunk -Force | Where-Object { $_.Name -ne '.svn' } | Remove-Item -Recurse -Force

Write-Host "3/5  Unpack archive into trunk/"
Expand-Archive -Path $tmpZip -DestinationPath $trunk -Force
Remove-Item $tmpZip -Force

Write-Host "4/5  svn add/rm to reconcile"
Push-Location $svnDir
try {
    & svn add trunk --force | Out-Null
    & svn status | Where-Object { $_ -match '^!' } | ForEach-Object {
        $missing = ($_ -split '\s+', 2)[1]
        & svn rm $missing | Out-Null
    }

    Write-Host "5/5  svn cp trunk tags/$ver"
    & svn cp trunk "tags/$ver" | Out-Null

    Write-Host ""
    Write-Host "Staged changes:"
    & svn status | Select-Object -First 30
    Write-Host "  (showing first 30 lines)"

    if ($Commit) {
        Write-Host ""
        Write-Host "Committing as 'Release $ver'..."
        & svn ci -m "Release $ver"
        if ($LASTEXITCODE -ne 0) { throw "svn ci failed (exit $LASTEXITCODE)." }
        Write-Host "Done. Check https://wordpress.org/plugins/fungies-for-woocommerce/ in ~30 min."
    } else {
        Write-Host ""
        Write-Host "DRY RUN complete. Re-run with -Commit to publish."
        Write-Host "To abort: svn revert -R . (inside $svnDir)"
    }
} finally {
    Pop-Location
}
