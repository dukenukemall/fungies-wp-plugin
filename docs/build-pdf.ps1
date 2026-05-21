#!/usr/bin/env pwsh
# Render docs/creating-products-and-offers.md -> ...html -> ...pdf
# using Microsoft Edge headless (preinstalled on Windows 10/11).
#
# Why this exists:
#   We don't want a Node/Python toolchain just to ship a PDF. Edge's
#   Chromium-based `--headless --print-to-pdf` is good enough and is
#   already on every dev machine.

[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$docsDir = $PSScriptRoot
$mdPath  = Join-Path $docsDir 'creating-products-and-offers.md'
$htmlPath = Join-Path $docsDir 'creating-products-and-offers.html'
$pdfPath  = Join-Path $docsDir 'creating-products-and-offers.pdf'

if (-not (Test-Path $mdPath)) {
    throw "Missing source: $mdPath"
}

$md = Get-Content -Raw -LiteralPath $mdPath

# --- Minimal markdown -> HTML converter -------------------------------------
# Handles: headings (#..######), fenced code blocks (```lang ... ```), pipe
# tables (| a | b |), unordered/ordered lists, bold/italic/inline code, links,
# and paragraphs. Good enough for our own guide -- not a general md engine.

function ConvertFrom-MarkdownLite {
    param([string]$src)

    $lines = $src -split "`r?`n"
    $html  = New-Object System.Text.StringBuilder

    $inCode  = $false
    $codeLang = ''
    $inList  = $false
    $listKind = ''
    $inTable = $false
    $tableHeader = $null
    $paragraph = New-Object System.Collections.Generic.List[string]

    function Flush-Paragraph {
        param($html, $paragraph)
        if ($paragraph.Count -gt 0) {
            $text = ($paragraph -join ' ').Trim()
            if ($text) {
                $text = Format-Inline $text
                [void]$html.AppendLine("<p>$text</p>")
            }
            $paragraph.Clear()
        }
    }

    function Format-Inline {
        param([string]$s)
        # HTML-escape first (but keep already-encoded entities like &mdash; intact).
        $s = $s -replace '&(?!(?:[a-zA-Z]+|#\d+);)', '&amp;'
        $s = $s -replace '<', '&lt;'
        $s = $s -replace '>', '&gt;'
        # Inline code `...`
        $s = [regex]::Replace($s, '`([^`]+)`', '<code>$1</code>')
        # Bold **...**
        $s = [regex]::Replace($s, '\*\*([^\*]+)\*\*', '<strong>$1</strong>')
        # Italic *...*  (avoid matching inside <strong>)
        $s = [regex]::Replace($s, '(?<![\*])\*([^\*\n]+)\*(?![\*])', '<em>$1</em>')
        # Links [text](url)
        $s = [regex]::Replace($s, '\[([^\]]+)\]\(([^)]+)\)', '<a href="$2">$1</a>')
        return $s
    }

    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = $lines[$i]

        # Code fence toggle
        if ($line -match '^```(\w*)\s*$') {
            if ($inCode) {
                [void]$html.AppendLine('</code></pre>')
                $inCode = $false
                $codeLang = ''
            } else {
                Flush-Paragraph $html $paragraph
                if ($inList)  { [void]$html.AppendLine("</$listKind>"); $inList = $false }
                if ($inTable) { [void]$html.AppendLine('</tbody></table>'); $inTable = $false }
                $codeLang = $Matches[1]
                $cls = if ($codeLang) { " class=`"lang-$codeLang`"" } else { '' }
                [void]$html.AppendLine("<pre><code$cls>")
                $inCode = $true
            }
            continue
        }
        if ($inCode) {
            $escaped = $line -replace '&', '&amp;' -replace '<', '&lt;' -replace '>', '&gt;'
            [void]$html.AppendLine($escaped)
            continue
        }

        # Horizontal rule
        if ($line -match '^---+\s*$') {
            Flush-Paragraph $html $paragraph
            if ($inList)  { [void]$html.AppendLine("</$listKind>"); $inList = $false }
            if ($inTable) { [void]$html.AppendLine('</tbody></table>'); $inTable = $false }
            [void]$html.AppendLine('<hr>')
            continue
        }

        # Headings
        if ($line -match '^(#{1,6})\s+(.+?)\s*$') {
            Flush-Paragraph $html $paragraph
            if ($inList)  { [void]$html.AppendLine("</$listKind>"); $inList = $false }
            if ($inTable) { [void]$html.AppendLine('</tbody></table>'); $inTable = $false }
            $level = $Matches[1].Length
            $text  = Format-Inline $Matches[2]
            [void]$html.AppendLine("<h$level>$text</h$level>")
            continue
        }

        # Table header (| h1 | h2 |) followed by separator (|---|---|)
        if (-not $inTable -and $line -match '^\s*\|.+\|\s*$' -and ($i + 1) -lt $lines.Count -and $lines[$i+1] -match '^\s*\|[\s\-:|]+\|\s*$') {
            Flush-Paragraph $html $paragraph
            if ($inList) { [void]$html.AppendLine("</$listKind>"); $inList = $false }
            $cells = ($line.Trim().Trim('|') -split '\|') | ForEach-Object { Format-Inline $_.Trim() }
            [void]$html.AppendLine('<table><thead><tr>')
            foreach ($c in $cells) { [void]$html.AppendLine("<th>$c</th>") }
            [void]$html.AppendLine('</tr></thead><tbody>')
            $inTable = $true
            $i++ # skip separator
            continue
        }
        if ($inTable) {
            if ($line -match '^\s*\|.+\|\s*$') {
                $cells = ($line.Trim().Trim('|') -split '\|') | ForEach-Object { Format-Inline $_.Trim() }
                [void]$html.AppendLine('<tr>')
                foreach ($c in $cells) { [void]$html.AppendLine("<td>$c</td>") }
                [void]$html.AppendLine('</tr>')
                continue
            } else {
                [void]$html.AppendLine('</tbody></table>')
                $inTable = $false
            }
        }

        # Unordered list
        if ($line -match '^\s*[-*]\s+(.+)$') {
            Flush-Paragraph $html $paragraph
            if (-not $inList -or $listKind -ne 'ul') {
                if ($inList) { [void]$html.AppendLine("</$listKind>") }
                [void]$html.AppendLine('<ul>')
                $inList = $true; $listKind = 'ul'
            }
            [void]$html.AppendLine("<li>$(Format-Inline $Matches[1])</li>")
            continue
        }

        # Ordered list
        if ($line -match '^\s*\d+\.\s+(.+)$') {
            Flush-Paragraph $html $paragraph
            if (-not $inList -or $listKind -ne 'ol') {
                if ($inList) { [void]$html.AppendLine("</$listKind>") }
                [void]$html.AppendLine('<ol>')
                $inList = $true; $listKind = 'ol'
            }
            [void]$html.AppendLine("<li>$(Format-Inline $Matches[1])</li>")
            continue
        }

        # Blank line
        if ($line -match '^\s*$') {
            Flush-Paragraph $html $paragraph
            if ($inList)  { [void]$html.AppendLine("</$listKind>"); $inList = $false }
            continue
        }

        # Regular paragraph line
        $paragraph.Add($line.Trim()) | Out-Null
    }

    Flush-Paragraph $html $paragraph
    if ($inList)  { [void]$html.AppendLine("</$listKind>") }
    if ($inTable) { [void]$html.AppendLine('</tbody></table>') }
    if ($inCode)  { [void]$html.AppendLine('</code></pre>') }

    return $html.ToString()
}

$body = ConvertFrom-MarkdownLite -src $md

$css = @'
:root {
  --fg: #1a1a1a;
  --muted: #5b6470;
  --border: #d9dde4;
  --bg-code: #f5f6f8;
  --accent: #4f46e5;
}
* { box-sizing: border-box; }
html, body {
  font-family: -apple-system, "Segoe UI", system-ui, "Helvetica Neue", Arial, sans-serif;
  color: var(--fg);
  line-height: 1.55;
  font-size: 11pt;
  margin: 0;
  padding: 0;
}
body { padding: 2.4rem 2.8rem; }
h1 { font-size: 22pt; margin: 0 0 0.6rem; border-bottom: 2px solid var(--border); padding-bottom: 0.4rem; }
h2 { font-size: 15pt; margin: 1.6rem 0 0.5rem; color: var(--accent); }
h3 { font-size: 12.5pt; margin: 1.1rem 0 0.4rem; }
p  { margin: 0.5rem 0; }
hr { border: none; border-top: 1px dashed var(--border); margin: 1.4rem 0; }
ul, ol { margin: 0.4rem 0 0.6rem 1.4rem; padding: 0; }
li { margin: 0.18rem 0; }
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }
code {
  font-family: "JetBrains Mono", Consolas, "Liberation Mono", monospace;
  background: var(--bg-code);
  border: 1px solid var(--border);
  border-radius: 3px;
  padding: 0.05rem 0.32rem;
  font-size: 9.5pt;
}
pre {
  background: var(--bg-code);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 0.7rem 0.9rem;
  overflow-x: auto;
  font-size: 9pt;
  page-break-inside: avoid;
}
pre code {
  background: none;
  border: none;
  padding: 0;
  font-size: 9pt;
  white-space: pre;
}
table {
  border-collapse: collapse;
  width: 100%;
  margin: 0.6rem 0;
  font-size: 10pt;
  page-break-inside: avoid;
}
thead th {
  background: #eef0f5;
  text-align: left;
  font-weight: 600;
}
th, td {
  border: 1px solid var(--border);
  padding: 0.4rem 0.55rem;
  vertical-align: top;
}
strong { font-weight: 600; }
em { font-style: italic; }

/* Print tuning */
@page {
  size: A4;
  margin: 14mm 14mm 18mm 14mm;
}
@media print {
  body { padding: 0; }
  h2, h3 { page-break-after: avoid; }
  pre, table { page-break-inside: avoid; }
}

.footer {
  margin-top: 2.4rem;
  padding-top: 0.8rem;
  border-top: 1px solid var(--border);
  font-size: 8.5pt;
  color: var(--muted);
}
'@

$generated = (Get-Date).ToString('yyyy-MM-dd')

$html = @"
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Creating Products & Offers via the Fungies API</title>
<style>
$css
</style>
</head>
<body>
$body
<div class="footer">
  Fungies for WooCommerce &middot; Developer guide &middot; Generated $generated &middot; Source: docs/creating-products-and-offers.md
</div>
</body>
</html>
"@

Set-Content -LiteralPath $htmlPath -Value $html -Encoding UTF8
Write-Host "Wrote HTML : $htmlPath"

$edge = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
if (-not (Test-Path $edge)) {
    $edge = 'C:\Program Files\Microsoft\Edge\Application\msedge.exe'
}
if (-not (Test-Path $edge)) {
    throw "Microsoft Edge not found. Install Edge or extend this script with another headless renderer."
}

if (Test-Path $pdfPath) { Remove-Item $pdfPath -Force }

# Edge requires a file:// URI and an absolute output path. The user data dir
# flag isolates the headless run from your normal browser session.
$userData = Join-Path $env:TEMP "fungies-pdf-$([guid]::NewGuid().ToString('N'))"
$fileUri  = ([uri]$htmlPath).AbsoluteUri

& $edge `
    --headless=new `
    --disable-gpu `
    --no-pdf-header-footer `
    "--user-data-dir=$userData" `
    "--print-to-pdf=$pdfPath" `
    $fileUri | Out-Null

if (Test-Path $userData) { Remove-Item -Recurse -Force $userData -ErrorAction SilentlyContinue }

if (-not (Test-Path $pdfPath)) {
    throw "Edge headless did not produce a PDF at $pdfPath"
}

$size = (Get-Item $pdfPath).Length
Write-Host ("Wrote PDF  : {0} ({1:N0} bytes)" -f $pdfPath, $size)
