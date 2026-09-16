# TFD Credit Application PDF Generator

WordPress plugin that generates branded PDFs from Ninja Forms credit application submissions. Zero external dependencies — pure PHP PDF generator.

## Features

- Generates professional A4 PDFs from Ninja Forms submissions
- TFD Green (#4CAF50) branding with Helvetica fonts
- Configurable PDF attachment mode (all emails, admin only, customer only, disabled)
- 60 form fields + 2 signature sections (Endorsement + Directors' Guarantee)
- OFFICE USE section with greyed-out read-only fields
- Built-in settings page — change form ID without editing code
- Debug logging to `wp-content/uploads/tfcap-pdfs/debug.log`

## Installation

1. Upload the `tfd-pdf-generator` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Go to Settings → Credit Application PDF
4. Enter your Ninja Forms form ID
5. Configure email attachment preferences

## Files

- `tfd-pdf-generator.php` — Main plugin file
- `tfd-credit-application.nff` — Ninja Forms import template (60 fields, 2 signatures)
- `tfd-signature-fix.js` — JS fix for Ninja Forms signature pad fading on fast strokes

## Requirements

- WordPress 5.0+
- PHP 7.0+
- Ninja Forms 3.0+
- No external dependencies (no mPDF, no GD, no Composer)

## Debug Mode

Logs are written to `wp-content/uploads/tfcap-pdfs/debug.log`. Check this file if PDFs aren't generating correctly.

## License

Private — The Fun Depot / KGO Enterprises Pty Ltd

## Release Workflow

When Keith says **"push to GitHub"**, do both steps:

1. **Git push** — commit changes and push to `master`
2. **Create a GitHub Release** — go to repo → Releases → Create new release
   - Tag: `v{version}` (match the `Version:` in plugin header)
   - Title: `v{version}` + short summary
   - Notes: copy from CHANGELOG.md
   - Publish release

The auto-updater (built into v1.14.0+) checks `releases/latest` daily. Publishing a release triggers WP to show "Update available" → one-click install.
