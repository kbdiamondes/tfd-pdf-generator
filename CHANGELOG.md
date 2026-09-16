# TFD Credit Application PDF Generator — Changelog

## v1.18.1 (2026-09-16)
- Fixed text overlap — fieldRow now uses dynamic label width based on actual label length
- Fixed twoColField — both columns now use dynamic label widths
- Fixed Terms wrapping — numbered terms now wrap properly using wrapText()
- Terms now display correctly even with long legal text

## v1.18.0 (2026-09-16)
- Restored ALL content from Word document to match original form exactly
- Section 1: Full labels ("Applicant's Full Name / Company Name", "A.C.N. (if a company)")
- Section 2: Added "(Mr/Mrs/Ms)" and "(for invoices/statements)" to labels
- Section 3: Changed to "Registered / Business Street Address:"
- Section 4: Added note about Director's Guarantee in Section 9, "Phone / Mobile" label
- Section 5: Added Privacy Act 1988 (Cth) reference to authorization note
- Section 6: Added intro text "Please provide three (3) current trade references.", full labels
- Section 7: Restored ALL 7 terms with full legal language, Privacy Act, URL references
- Section 8: Added "(Company / Applicant Name)" and "(Director / Company Secretary)" to labels
- Section 9: Restored full guarantee text with indemnity clause, "Residential Address" label
- Added header intro text about payment terms and application instructions
- Added footer instructional text about emailing and N/A
- Added "Yes"/"No" to radio label mapper

## v1.17.7 (2026-09-16)
- Fixed signature overflow — reverted to embedImage() which scales to fit box
- Signature now scales 2x down from 1000×400 to fit 500×200 box
- Box aspect ratio (2.5:1) matches canvas aspect ratio (2.5:1) perfectly

## v1.17.6 (2026-09-16)
- Fixed signature quality — embed at native 1000×400 resolution with no resize
- Added embedImageNoResize() method for signatures — preserves every pixel of thin strokes
- PDF reader scales the image to fit the box — no quality loss from our code

## v1.17.5 (2026-09-16)
- Fixed word wrapping for long words (emails, addresses) — splits at character boundaries
- Increased signature box to 500×200 to match 1000×400 canvas ratio exactly
- Reverted to nearest-neighbor resize for signatures — preserves sharp stroke edges
- Signature now scales down only 2x (was 2.5x) for better detail

## v1.17.4 (2026-09-16)
- Increased signature box size from 400×150 to 450×180 to better match 1000×400 canvas
- Box now centered better on the page (25px margin from left edge)
- Preserves more detail from signature pad capture

## v1.17.3 (2026-09-16)
- Added word wrapping to twoColField — long values now wrap instead of overlapping
- Added word wrapping to fieldRow — consistent handling across all field types
- Wrapped lines properly adjust Y position to prevent vertical overlap

## v1.17.2 (2026-09-16)
- Fixed header overlap — reduced title font size from 18pt to 15pt, adjusted right column position
- Removed text truncation — emails and addresses now display in full
- Improved signature quality — replaced nearest-neighbor resize with bilinear interpolation for smoother rendering

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
