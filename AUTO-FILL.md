# TFD Credit Application — Auto-Fill Script

Paste this into the browser console on the credit application form page. It fills all fields with realistic mock data.

## How to Use

1. Open the credit application form in your browser
2. Open DevTools → Console (`Cmd+Option+J` on Mac)
3. **Do NOT type `javascript:`** — just paste the script directly
4. Press Enter
5. Review the filled form — all fields populate instantly

> **Tip:** If you see a `>` prompt in the console, you're in the right spot. Just paste and go.

## The Script

Paste everything below into the browser console on the form page:

```javascript
(() => {
  // ── Helper: set value + fire NF3 events ────────────────────
  function setVal(id, value) {
    const el = document.getElementById(id);
    if (!el) return false;
    const setter = Object.getOwnPropertyDescriptor(
      el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype,
      'value'
    )?.set;
    if (setter) setter.call(el, value);
    else el.value = value;
    el.dispatchEvent(new Event('input',  { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    el.dispatchEvent(new Event('blur',   { bubbles: true }));
    return true;
  }

  // ── Find field ID by NF key name ───────────────────────────
  // NF3 renders fields as nf-field-XXX where XXX matches the
  // field's internal ID. We find them by looking for the
  // nf-field-wrap element with data-field-id matching our key.
  function findFieldId(keyName) {
    // Method 1: Look for nf-field elements by their label text
    const wraps = document.querySelectorAll('.nf-field-container');
    for (const wrap of wraps) {
      const input = wrap.querySelector('input, textarea, select');
      if (input && input.id && input.id.includes(keyName.replace(/[a-z_]/g, ''))) {
        return input.id;
      }
    }
    // Method 2: Try common NF3 ID patterns
    // NF3 assigns sequential IDs starting from a base.
    // We can't predict them, so we'll scan all inputs.
    return null;
  }

  // ── Auto-discover all NF3 field IDs ────────────────────────
  // Scans the DOM for all nf-field-* elements and maps them
  const allFields = {};
  document.querySelectorAll('[id^="nf-field-"]').forEach(el => {
    const match = el.id.match(/^nf-field-(\d+)$/);
    if (match) {
      allFields[match[1]] = el;
    }
  });

  // ── Mock Data (by field key → value) ───────────────────────
  // We'll match by placeholder or label text since NF3 IDs are dynamic
  const mockData = {
    // Text inputs — match by placeholder text
    'Full Name or Company Name': 'Perth Party Supplies Pty Ltd',
    'A.C.N.': '123 456 789',
    'A.B.N.': '63 667 911 944',
    'Please specify': 'Special Event Hire',
    'Trading Name': 'Perth Party Co',
    'Mr/Mrs/Ms': 'Sarah Mitchell',
    'Position': 'Accounts Manager',
    'accounts@company.com.au': 'accounts@perthpartysupplies.com.au',
    '0400 000 000': '0412 345 678',
    'Street Address, Suburb': '42 Wellington Street, Perth WA 6000',
    '6000': '6000',
    '08 9000 0000': '08 9234 5678',
    'e.g. Commonwealth Bank': 'Commonwealth Bank of Australia',
    'Branch name or BSB': 'Morley Branch — BSB 066 102',
    'Company Name': 'Party World Australia',
    'Phone Number': '08 9200 1234',
    'Company or Applicant Name': 'Perth Party Supplies Pty Ltd',
    'Full Name': 'James Mitchell',
    'Director / Secretary': 'Director',
    'Guarantor Name': 'James Mitchell',
    'e.g. Director': 'Director',
    'Residential Address': '8 Banksia Crescent, Joondalup WA 6027',
    'Guarantor 2 Name': 'Karen Mitchell',
    'dd/mm/yyyy': '16/09/2026',
    'Name': 'Keith',
    'Signature': 'Approved',
    'Account Name': 'Perth Party Supplies Pty Ltd',
    'e.g. Email 16/09/2026': 'Email 16/09/2026',
  };

  // ── Fill text/email/phone/date fields ─────────────────────
  let filled = 0;
  for (const [id, el] of Object.entries(allFields)) {
    const input = el.querySelector('input, textarea, select');
    if (!input) continue;

    const placeholder = input.getAttribute('placeholder') || '';
    const value = mockData[placeholder];

    if (value && input.value !== value) {
      const setter = Object.getOwnPropertyDescriptor(
        input.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype,
        'value'
      )?.set;
      if (setter) setter.call(input, value);
      else input.value = value;
      input.dispatchEvent(new Event('input',  { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      input.dispatchEvent(new Event('blur',   { bubbles: true }));
      filled++;
    }
  }

  // ── Radio buttons ──────────────────────────────────────────
  // Find radio groups by label text and click the right option
  document.querySelectorAll('.nf-field-radio').forEach(field => {
    const label = field.querySelector('.nf-option-label')?.textContent?.trim();
    const options = field.querySelectorAll('input[type="radio"]');

    if (field.closest('.nf-field-container')?.querySelector('.nf-field-label')?.textContent?.includes('Applicant is a')) {
      // Click "Pty Ltd Company" (first option)
      if (options[0]) { options[0].click(); filled++; }
    }
    if (field.closest('.nf-field-container')?.querySelector('.nf-field-label')?.textContent?.includes('registered business name')) {
      // Click "Yes" (first option)
      if (options[0]) { options[0].click(); filled++; }
    }
  });

  // ── Checkboxes ─────────────────────────────────────────────
  document.querySelectorAll('.nf-field-checkbox').forEach(field => {
    const checkbox = field.querySelector('input[type="checkbox"]');
    if (checkbox && !checkbox.checked) {
      checkbox.click();
      filled++;
    }
  });

  // ── Signatures — draw mock cursive on canvas ──────────────
  function drawSig(canvas) {
    if (!canvas) return false;
    const ctx = canvas.getContext('2d');
    if (!ctx) return false;

    const w = canvas.width, h = canvas.height;
    ctx.clearRect(0, 0, w, h);

    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.globalAlpha = 1;

    // Cursive wave
    ctx.beginPath();
    ctx.moveTo(0.05 * w, 0.60 * h);
    const pts = [
      [0.10, 0.30], [0.18, 0.55], [0.25, 0.25], [0.32, 0.50],
      [0.38, 0.20], [0.45, 0.45], [0.52, 0.35], [0.58, 0.55],
      [0.65, 0.30], [0.72, 0.50], [0.78, 0.38], [0.85, 0.48],
      [0.92, 0.35],
    ];
    for (const [px, py] of pts) {
      ctx.lineTo(px * w, py * h);
    }
    ctx.stroke();

    // Underline flourish
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0.10 * w, 0.70 * h);
    ctx.quadraticCurveTo(0.50 * w, 0.60 * h, 0.92 * w, 0.68 * h);
    ctx.stroke();

    // Trigger NF3 signature save
    canvas.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }

  document.querySelectorAll('canvas[id^="nf-field-"]').forEach(canvas => {
    if (drawSig(canvas)) filled++;
  });

  // ── Summary ────────────────────────────────────────────────
  console.log(`[TFCAP] ✅ Filled ${filled} fields with mock data.`);
  if (filled === 0) {
    console.warn('[TFCAP] No fields found. Make sure you are on the credit application form page.');
    console.log('[TFCAP] Found NF3 fields:', Object.keys(allFields).length);
    console.log('[TFCAP] All nf-field elements:', document.querySelectorAll('[id^="nf-field-"]').length);
  }
})();
```

*(end of script)*

## How It Works

The script auto-discovers NF3 field IDs by scanning the DOM for all `[id^="nf-field-"]` elements. It then matches fields by their **placeholder text** (which is stable across imports) rather than hardcoded NF3 IDs.

| Match Method | Examples |
|---|---|
| Placeholder text | `"Full Name or Company Name"` → fills with `"Perth Party Supplies Pty Ltd"` |
| Radio group labels | `"Applicant is a"` → clicks `"Pty Ltd Company"` |
| Checkboxes | All checkboxes → checked |
| Canvas signatures | All signature canvases → draws mock cursive |

## Troubleshooting

| Error | Fix |
|---|---|
| `javascript is not defined` | You pasted `javascript:` prefix. Clear console, paste raw script only. |
| `Filled 0 fields` | You're not on the credit application page. Navigate to the form first. |
| Some fields didn't fill | The placeholder text might have changed. Check the form's input placeholders. |

## Notes

- Signatures are drawn as mock cursive on the canvas pads
- Checkboxes (Terms + Guarantee) are checked by default
- Script is non-destructive — refresh the page to clear everything
- No hardcoded NF3 IDs — works after any re-import
