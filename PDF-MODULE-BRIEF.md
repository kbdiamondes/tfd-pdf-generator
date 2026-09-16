# TFD Credit Application PDF Generator — Module Brief

## What It Does

WordPress plugin that generates a branded A4 PDF from Ninja Forms credit application submissions. When a user submits the form, the plugin hooks into Ninja Forms, generates a PDF, saves it to disk, and attaches it to email notifications.

**Repo:** https://github.com/kbdiamondes/tfd-pdf-generator  
**Current version:** 1.19.6  
**Requires:** PHP 7.0+, WordPress, Ninja Forms

---

## Architecture

### File Structure
```
tfd-pdf-generator.php          — Main plugin (everything in one file)
tfd-credit-application.nff     — Ninja Forms import template (81 fields)
AUTO-FILL.md                   — Browser console script for testing
CHANGELOG.md                   — Version history
README.md                      — Docs + release workflow
```

### Core Class: `TFCAP_PDF` (lines ~471–1043)

A **from-scratch PDF generator** — no TCPDF, no FPDF, no DOMPDF. It builds raw PDF byte streams manually using PDF operators.

```php
class TFCAP_PDF {
    private $pages = [];        // Array of page content strings + Y positions
    private $current_page = -1;
    private $images = [];       // Embedded PNG images

    const PAGE_W = 595.28;      // A4 width in PDF points
    const PAGE_H = 841.89;      // A4 height in PDF points
    const MARGIN = 56.69;       // 20mm margins
}
```

### How PDF Content Is Built

Each page is a string of raw PDF operators. The class methods append PDF instructions to the current page's content string:

```php
// Text: BT = begin text, /F1 = font, Tf = set font, rg = set color, Td = position, Tj = show text, ET = end text
function text($x, $y, $text, $size, $color, $style) {
    $this->raw("BT /F1 {$size} Tf {$r} {$g} {$b} rg {$x} {$y} Td ({$text}) Tj ET\n");
}

// Line: q = save state, m = moveto, l = lineto, S = stroke, Q = restore
function line($x1, $y1, $x2, $y2, $width, $color) {
    $this->raw("q {$r} {$g} {$b} RG {$width} w {$x1} {$y1} m {$x2} {$y2} l S Q\n");
}
```

The `build()` method wraps all pages into a valid PDF document with headers, resources, fonts, images, and cross-reference table.

### Key Layout Methods

| Method | Purpose | Signature |
|--------|---------|-----------|
| `fieldRow()` | Full-width label + value on one line | `($label, $value, $label_w=0)` |
| `twoColField()` | Two-column layout, label + value per column | `($label1, $value1, $label2, $value2)` |
| `sectionHeader()` | Bold section title with green underline | `($text, $size=13)` |
| `subHeader()` | Smaller sub-section header | `($text)` |
| `note()` | Grey paragraph text, word-wrapped | `($text)` |
| `checkbox()` | Checkbox with label | `($label, $checked)` |
| `signatureBox()` | Dashed box with embedded signature image | `($label, $sig_data)` |

### Text Measurement: `measureBold()`

Since there's no font metrics library, the plugin estimates Helvetica Bold character widths using an AFM-derived lookup table:

```php
private function measureBold($text, $size) {
    $widths = ['A'=>6.67, 'B'=>6.67, 'C'=>7.22, ...]; // ~90 entries
    $w = 0;
    foreach characters: $w += $widths[$ch] ?? 5.56;
    return $w * $size / 10; // widths are at 10pt scale
}
```

Used by `fieldRow()` and `twoColField()` to calculate where the value text should start (so it doesn't overlap the label).

### Image Embedding: `embedImage()`

Handles PNG images (signatures from Ninja Forms signature pads). Two code paths:

1. **No alpha (RGB/Gray):** Decompress IDAT → crop whitespace → resize to fit box → recompress → embed
2. **Has alpha (RGBA/Gray+Alpha):** Decompress IDAT → reverse PNG filters → alpha-blend onto white → crop whitespace → resize → recompress → embed

Both paths use `cropWhitespace()` to find the bounding box of non-white pixels and trim the image before scaling.

Signature images from NF3 are typically 1000×400 RGBA PNGs. The `signatureBox()` method renders them into a 500×200pt dashed box with 8pt padding.

---

## Known Issues (As of v1.19.6)

### 1. Text Overlap in PDF
**Symptom:** Label text bleeds into value text area (e.g., "Contact Name (Mr/Mrs/Ms)arah Mitchell")  
**Root cause:** The `measureBold()` function estimates character widths. If the estimate is slightly off for a label, the value starts too early and visually overlaps.  
**Current fix:** AFM width table with `/10` scaling, capped at 58% of available width.  
**Remaining issue:** Very long labels in `twoColField` (e.g., "Accounts Email (for invoices/statements):") may still have the value wrap mid-word because the column is too narrow for both.

### 2. Value Word-Wrapping in Two-Column Layout
**Symptom:** Long values like email addresses break mid-word across lines  
**Root cause:** Column width is ~240pt. Label takes 55%, leaving ~108pt for value. A 33-char email at 10pt needs ~170pt.  
**Possible fix:** Use `wrapText()` to break at word boundaries, or reduce label allocation for long labels.

### 3. Signature Image Quality
**Symptom:** Signatures can appear blurry or thin  
**Root cause:** Canvas pad captures at high resolution (1000×400), then the plugin crops whitespace and scales down. The `cropWhitespace()` function uses a white threshold of 250 — thin strokes near white may be cropped.  
**Note:** Nearest-neighbor resize preserves sharpness but doesn't anti-alias.

### 4. PDF Text Encoding
**Symptom:** Special characters (™, —, ', ') break PDF rendering  
**Root cause:** Raw PDF text operators can't handle UTF-8. The `text()` function replaces these with ASCII equivalents.  
**Workaround:** Already implemented in `text()` method.

---

## PDF Generation Flow

```
1. ninja_forms_submit_data hook fires
2. tfcap_generate_pdf() is called with $form_data
3. Extract field values from $form_data['fields'] using tfcap_by_key()
4. Create new TFCAP_PDF instance
5. Render header (company name, ABN, intro text)
6. Render Section 1-9 (fieldRow/twoColField/signatureBox calls)
7. Render footer (divider line, contact info, timestamp)
8. Build PDF and save to wp-content/uploads/tfcap-pdfs/
9. Store path in $TFCAP_LAST_PDF global for email attachment
```

### Field Mapping

The PDF generation code (lines ~1400-1500) maps Ninja Forms field keys to PDF positions:

```php
// Section 1: Applicant Details
$pdf->fieldRow("Applicant's Full Name / Company Name:", tfcap_by_key($fields, 'textbox_2'));
$pdf->twoColField('A.C.N. (if a company):', tfcap_by_key($fields, 'textbox_3'), 'A.B.N.:', tfcap_by_key($fields, 'textbox_4'));
```

Field keys are fixed (textbox_2, textbox_3, etc.) — they come from the NFF template import.

---

## Key Functions Reference

| Function | Purpose |
|----------|---------|
| `tfcap_generate_pdf($form_data)` | Main hook — generates PDF from form submission |
| `tfcap_by_key($fields, $key)` | Extract field value by NF3 key name |
| `tfcap_radio_label($value)` | Map radio button value to display label |
| `tfcap_safe($fields, $id, $default)` | Get field value by numeric ID with fallback |
| `tfcap_attach_pdf($attachments, ...)` | Attach PDF to email notification |
| `tfcap_log($msg)` | Write to debug.log |

---

## Testing

Use the auto-fill script in `AUTO-FILL.md` — paste into browser console on the form page. It fills all fields with mock data using NF3 element IDs (currently 396–462).

To find new IDs after re-importing the NFF:
```javascript
document.querySelectorAll('[id^="nf-field-"]').forEach(el => {
    console.log(el.id, el.type, el.placeholder, el.closest('.nf-field-container')?.querySelector('label')?.textContent);
});
```
