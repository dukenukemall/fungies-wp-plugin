# WordPress Plugin ZIP Build Rules

## CRITICAL: FLAT ZIP Structure

The zip must be **FLAT** — files directly at the root of the zip, NO wrapper folder. WordPress creates the plugin folder automatically on extraction.

### Correct structure (inside zip):
```
fungies-wp-plugin.php          ← main plugin file at zip root
includes/class-*.php           ← subfolder OK
assets/js/*.js                 ← subfolder OK
assets/css/*.css               ← subfolder OK
templates/*.php                ← subfolder OK
```

### WRONG — has wrapper folder:
```
fungies-checkout/              ← BAD! no wrapper folder allowed
  fungies-wp-plugin.php
  includes/...
```

### WRONG — double nested:
```
fungies-checkout/
  fungies-checkout/            ← BAD! extra folder level
    fungies-wp-plugin.php
```

## Build Script (PowerShell)

ALWAYS use .NET `System.IO.Compression.ZipFile` — never `Compress-Archive`.
Entry names are the relative file path with NO folder prefix.

```powershell
$zipName = "fungies-checkout-VERSION.zip"
$zipPath = "FULL_PATH\$zipName"
$srcDir  = "FULL_PATH_TO_PLUGIN_SOURCE"

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

# Entry names are the relative path — NO folder prefix
$entryName = $relativeFilePath -replace '\\','/'
[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $fullPath, $entryName)

$zip.Dispose()
```

## Version Bumping

With each new zip build, bump the version in:
1. `fungies-wp-plugin.php` — `Version:` header comment
2. `fungies-wp-plugin.php` — `FUNGIES_WP_VERSION` constant
3. Zip filename — `fungies-checkout-X.Y.Z.zip`

## Verification

After building, ALWAYS verify the zip contents:
```powershell
Add-Type -AssemblyName System.IO.Compression.FileSystem
$z = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
$z.Entries | Select-Object FullName
$z.Dispose()
```

Confirm the first entry is `fungies-wp-plugin.php` (NOT `fungies-checkout/fungies-wp-plugin.php`).
