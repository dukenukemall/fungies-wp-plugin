# WordPress Plugin ZIP Build Rules

## Zip Filename Convention

Always name the zip: `fungies-checkout-{version}.zip` (e.g., `fungies-checkout-1.9.6.zip`).

## CRITICAL: FLAT ZIP Structure

The zip must be **FLAT** — files directly at the root of the zip, NO wrapper folder. WordPress creates the plugin folder automatically on extraction.

### Correct structure (inside zip):
```
fungies-wp-plugin.php          <- main plugin file at zip root (MUST have valid Plugin Name header)
includes/class-*.php           <- subfolder OK
assets/js/*.js                 <- subfolder OK
assets/css/*.css               <- subfolder OK
templates/*.php                <- subfolder OK
README.md                      <- OK
```

### WRONG — has wrapper folder:
```
fungies-checkout/              <- BAD! no wrapper folder allowed
  fungies-wp-plugin.php
  includes/...
```

### WRONG — duplicate plugin directories:
```
fungies-wp-plugin.php          <- main file
fungies-wp-plugin/             <- BAD! stale subdirectory with second plugin header
  fungies-wp-plugin.php        <- causes "plugin does not have a valid header" error
```

## Files that must NOT be in the zip

- `.gitignore`, `.gitattributes`
- `.env`
- `.cursor/` directory
- `agent-transcripts/` directory
- Any stale subdirectories containing duplicate `fungies-wp-plugin.php`

## Valid Plugin Header

The main `fungies-wp-plugin.php` MUST start with a valid WordPress plugin header:
```php
<?php
/**
 * Plugin Name: Fungies for WooCommerce
 * Version: X.Y.Z
 * ...
 */
```
Without this header, WordPress shows "The plugin does not have a valid header."

## Build Command

Use `git archive` with `.gitattributes` export-ignore rules:
```powershell
git archive --format=zip HEAD -o fungies-checkout-VERSION.zip
```

The `.gitattributes` file controls what gets excluded via `export-ignore`.

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

Confirm:
1. `fungies-wp-plugin.php` is at the zip root (NOT inside a subdirectory)
2. There is only ONE `fungies-wp-plugin.php` in the entire zip
3. No `.env`, `.gitignore`, `.cursor/` or stale directories are present
