# TFD Credit Application PDF Generator — Changelog

## v1.17.1 (2026-09-16)
- Fixed broken WP native updater — removed Update URI header that caused silent failures
- "Update Now" button now runs a reliable manual update via AJAX (download → extract → replace)
- Added backup of current plugin before replacing (auto-restores if update fails)
- Removed `pre_set_site_transient_update_plugins` and `plugins_api` filters (replaced by manual update)

## v1.17.0 (2026-09-16)
- Fixed text overlap in PDF — twoColField now truncates values that exceed column width
- Fixed signature rendering — added white background behind signature images to prevent fading on transparent PNGs
- Fixed radio field labels — "Applicant is a" and "Registered Business Name" now show proper labels instead of raw values
- Added truncate() helper to prevent any text from exceeding its allocated column space
- Applied truncation to readOnlyField and readOnlyTwoCol in Office Use Only section

## v1.16.1 (2026-09-16)
- Lowered PHP requirement from 7.4 to 7.0 for wider compatibility

## v1.16.0 (2026-09-16)
- Added "From Email" setting to fix email delivery failures (SPF)
- Forces Ninja Forms emails to send from a domain email instead of Gmail
- Settings page now has: Admin Email (attachment filter) + From Email (sender address)

## v1.15.0 (2026-09-16)
- Added visual version checker on settings page
- Shows current version, latest version, and release notes
- "Check for Updates" button with spinner animation
- Color-coded status: green (up to date), orange (update available), blue (checking), red (error)
- AJAX endpoint for live version checking without page reload

## v1.14.0 (2026-09-16)
- Added GitHub auto-updater — pushes to GitHub releases auto-appear in WP admin
- Works with public repos out of the box
- For private repos: define `TFCAP_GITHUB_TOKEN` in wp-config.php
- Added `Plugin URI` and `Update URI` headers

## v1.13.0 (2026-09-16)
- Increased signature box from 250×140 to 400×150 points
- Signatures now render at ~314×134 pixels (readable, professional size)
- Fixed alpha blending formula for correct signature rendering
- Fixed PNG row filter reversal for all 5 filter types
- Correct stride-based resize offset (no more flat array bug)
- Private GitHub repo created: https://github.com/kbdiamondes/tfd-pdf-generator

## v1.12.0 (2026-08-11)
- Signatures render at full size (936×400)
- All 60 fields populate correctly
- Email attachment working
- OFFICE USE section added
- NFF template embedded in plugin
- Debug logging to wp-content/uploads/tfcap-pdfs/debug.log

## v1.11.0 (2026-08-10)
- Initial plugin creation
- Pure PHP PDF generator (zero dependencies)
- Ninja Forms integration
- Configurable email attachment mode

---

## Future Enhancements
- [ ] "Resend PDF" button in WordPress admin
- [ ] "Regenerate PDF" for existing submissions
- [ ] PDF preview before sending
- [ ] Custom PDF templates
