#!/usr/bin/env pwsh
# One-time setup: check out the WordPress.org SVN repo as a sibling of this
# git repo so the two version-control systems never share metadata.
#
# Layout produced:
#   <git repo>/                        ← this folder
#   <git repo>-svn/                    ← created here
#       ├── trunk/
#       ├── tags/
#       └── assets/
#
# Usage:
#   ./svn-bootstrap.ps1                          # uses default WP.org slug
#   ./svn-bootstrap.ps1 -Username MyExactCase    # cache creds on first run
#
# After this, use ./svn-release.ps1 to publish releases. See
# .cursor/rules/wp-org-svn-release.mdc and
# .kb/wordpress/plugin-dev/wp-org-svn-publishing.qmd for the full workflow.

[CmdletBinding()]
param(
    [string]$Slug = 'fungies-for-woocommerce',
    [string]$Username
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command svn -ErrorAction SilentlyContinue)) {
    throw @"
'svn' is not on PATH. Install TortoiseSVN with the 'command line client tools'
component enabled, or Apache Subversion binaries. See
.kb/wordpress/plugin-dev/wp-org-tortoisesvn.qmd.
"@
}

$gitRoot  = $PSScriptRoot
$svnRoot  = (Resolve-Path (Join-Path $gitRoot '..')).Path
$svnDir   = Join-Path $svnRoot ((Split-Path $gitRoot -Leaf) + '-svn')
$repoUrl  = "https://plugins.svn.wordpress.org/$Slug"

if (Test-Path $svnDir) {
    Write-Host "Already exists: $svnDir"
    Write-Host "Running 'svn up' to refresh..."
    Push-Location $svnDir
    try { & svn up } finally { Pop-Location }
    return
}

Write-Host "Checking out $repoUrl"
Write-Host "  -> $svnDir"
$args = @('co', $repoUrl, $svnDir)
if ($Username) { $args += @('--username', $Username) }
& svn @args
if ($LASTEXITCODE -ne 0) { throw "svn co failed (exit $LASTEXITCODE)." }

Write-Host ""
Write-Host "OK. Next steps:"
Write-Host "  1. Drop banners/icons/screenshots into $svnDir\assets\ when ready."
Write-Host "  2. Run ./svn-release.ps1 after a GitHub release to publish to WP.org."
