# TFD Credit Application — Auto-Fill Script

Paste this into the browser console on the credit application form page. It fills all fields with realistic mock data.

## How to Use

1. Open the credit application form in your browser
2. Open DevTools → Console (`Cmd+Option+J` on Mac)
3. **Do NOT type `javascript:`** — just paste the script directly
4. Press Enter
5. Review the filled form — all fields populate instantly

## Step 1: Discover Field IDs

First, run this to see all fields on the page:

```javascript
// Discovery script — run this first
document.querySelectorAll('input, textarea, select').forEach(el => {
  const label = el.closest('.nf-field-container')?.querySelector('.nf-field-label')?.textContent?.trim() || '';
  const ph = el.placeholder || '';
  const type = el.type || el.tagName;
  console.log(`${el.id || 'no-id'} | ${type} | "${ph}" | "${label}"`);
});
```

## Step 2: Auto-Fill Script

Once you see the field IDs, paste this script:

```javascript
(() => {
  let filled = 0;

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

  // ── Fill ALL inputs by placeholder match ────────────────────
  const mockByPlaceholder = {
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

  // Fill by placeholder
  document.querySelectorAll('input, textarea').forEach(input => {
    const ph = input.placeholder || '';
    const value = mockByPlaceholder[ph];
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
  });

  // ── Radio buttons ──────────────────────────────────────────
  document.querySelectorAll('.nf-field-radio').forEach(field => {
    const labelText = field.closest('.nf-field-container')?.querySelector('.nf-field-label')?.textContent || '';
    const options = field.querySelectorAll('input[type="radio"]');

    if (labelText.includes('Applicant is a') && options[0]) {
      options[0].click(); filled++;
    }
    if (labelText.includes('registered business name') && options[0]) {
      options[0].click(); filled++;
    }
  });

  // ── Checkboxes ─────────────────────────────────────────────
  document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    if (!cb.checked) { cb.click(); filled++; }
  });

  // ── Signatures — draw mock cursive ─────────────────────────
  document.querySelectorAll('canvas').forEach(canvas => {
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const w = canvas.width, h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(0.05*w, 0.60*h);
    [[0.10,0.30],[0.18,0.55],[0.25,0.25],[0.32,0.50],[0.38,0.20],
     [0.45,0.45],[0.52,0.35],[0.58,0.55],[0.65,0.30],[0.72,0.50],
     [0.78,0.38],[0.85,0.48],[0.92,0.35]].forEach(([x,y]) => ctx.lineTo(x*w, y*h));
    ctx.stroke();
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0.10*w, 0.70*h);
    ctx.quadraticCurveTo(0.50*w, 0.60*h, 0.92*w, 0.68*h);
    ctx.stroke();
    canvas.dispatchEvent(new Event('change', { bubbles: true }));
    filled++;
  });

  console.log(`[TFCAP] ✅ Filled ${filled} fields with mock data.`);
})();
```

## Troubleshooting

| Error | Fix |
|---|---|
| `javascript is not defined` | You pasted `javascript:` prefix. Clear console, paste raw script only. |
| `Filled 0 fields` | Run the discovery script first to see what fields exist. |
| Some fields didn't fill | The placeholder text might differ. Check the discovery output. |

## Notes

- Signatures are drawn as mock cursive on the canvas pads
- All checkboxes are checked by default
- Script is non-destructive — refresh the page to clear everything
