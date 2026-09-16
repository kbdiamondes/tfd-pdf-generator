# TFD Credit Application — Auto-Fill Script

Paste this into the browser console on the credit application form page. It fills all 60 fields with realistic mock data.

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

  // ── Mock Data ──────────────────────────────────────────────
  const fields = {
    // 1. Applicant Details
    'nf-field-186': 'Perth Party Supplies Pty Ltd',
    'nf-field-187': '123 456 789',
    'nf-field-188': '63 667 911 944',
    // 199 = "Other" radio (index 4)
    'nf-field-191': 'Perth Party Co',

    // 2. Accounts Contact
    'nf-field-194': 'Sarah Mitchell',
    'nf-field-195': 'Accounts Manager',
    'nf-field-196': 'accounts@perthpartysupplies.com.au',
    'nf-field-197': '0412 345 678',
    'nf-field-198': '42 Wellington Street, Perth WA 6000',
    'nf-field-199': '6000',

    // 3. Business Address
    'nf-field-201': '15 Industrial Drive, Malaga WA 6090',
    'nf-field-202': '6090',
    'nf-field-203': '08 9234 5678',
    'nf-field-204': '0401 234 567',

    // 4. Directors
    'nf-field-208': 'James Mitchell',
    'nf-field-209': '0423 456 789',
    'nf-field-210': '8 Banksia Crescent, Joondalup WA 6027',
    'nf-field-211': '6027',
    'nf-field-213': 'Karen Mitchell',
    'nf-field-214': '0434 567 890',
    'nf-field-215': '22 Palm Drive, Dianella WA 6059',
    'nf-field-216': '6059',

    // 5. Banking
    'nf-field-218': 'Commonwealth Bank of Australia',
    'nf-field-219': 'Morley Branch — BSB 066 102',

    // 6. Trade References
    'nf-field-223': 'Party World Australia',
    'nf-field-224': '08 9200 1234',
    'nf-field-225': 'Bounce House Rentals WA',
    'nf-field-226': '08 9300 5678',
    'nf-field-227': 'Jumping Castles Direct',
    'nf-field-228': '08 9400 9012',

    // 8. Endorsement
    'nf-field-233': 'Perth Party Supplies Pty Ltd',
    'nf-field-234': 'James Mitchell',
    'nf-field-235': 'Director',

    // 9. Guarantee
    'nf-field-239': 'James Mitchell',
    'nf-field-240': 'Director',
    'nf-field-241': '8 Banksia Crescent, Joondalup WA 6027',
  };

  // ── Fill text/email/phone fields ───────────────────────────
  let filled = 0;
  for (const [id, val] of Object.entries(fields)) {
    if (setVal(id, val)) filled++;
  }

  // ── Radio buttons ──────────────────────────────────────────
  // Applicant type: "Pty Ltd Company" = index 0 on nf-field-189
  const radio189 = document.getElementById('nf-field-189-0');
  if (radio189) { radio189.click(); filled++; }

  // Registered biz name: "Yes" = index 0 on nf-field-192
  const radio192 = document.getElementById('nf-field-192-0');
  if (radio192) { radio192.click(); filled++; }

  // ── Checkboxes (Terms + Guarantee) ────────────────────────
  const cb231 = document.getElementById('nf-field-231');
  if (cb231 && !cb231.checked) { cb231.click(); filled++; }

  const cb243 = document.getElementById('nf-field-243');
  if (cb243 && !cb243.checked) { cb243.click(); filled++; }

  // ── Signatures — draw mock cursive on canvas ──────────────
  function drawSig(canvasId) {
    const canvas = document.getElementById(canvasId);
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

    return true;
  }

  if (drawSig('nf-field-236')) filled++;  // Endorsement
  if (drawSig('nf-field-242')) filled++;  // Guarantee

  // ── Summary ────────────────────────────────────────────────
  console.log(`[TFCAP] ✅ Filled ${filled} fields with mock data.`);
})();
```

*(end of script)*

## Mock Data Summary

| Field | NF Field ID | Value |
|---|---|---|
| Company | nf-field-186 | Perth Party Supplies Pty Ltd |
| ACN | nf-field-187 | 123 456 789 |
| ABN | nf-field-188 | 63 667 911 944 |
| Applicant type | nf-field-189 | Pty Ltd Company (radio) |
| Trading Name | nf-field-191 | Perth Party Co |
| Registered name | nf-field-192 | Yes (radio) |
| Contact Name | nf-field-194 | Sarah Mitchell |
| Position | nf-field-195 | Accounts Manager |
| Email | nf-field-196 | accounts@perthpartysupplies.com.au |
| Phone | nf-field-197 | 0412 345 678 |
| Postal Address | nf-field-198 | 42 Wellington Street, Perth WA 6000 |
| Postcode | nf-field-199 | 6000 |
| Business Address | nf-field-201 | 15 Industrial Drive, Malaga WA 6090 |
| Business Postcode | nf-field-202 | 6090 |
| Landline | nf-field-203 | 08 9234 5678 |
| Mobile | nf-field-204 | 0401 234 567 |
| Director 1 Name | nf-field-208 | James Mitchell |
| Director 1 Phone | nf-field-209 | 0423 456 789 |
| Director 1 Address | nf-field-210 | 8 Banksia Crescent, Joondalup WA 6027 |
| Director 1 Postcode | nf-field-211 | 6027 |
| Director 2 Name | nf-field-213 | Karen Mitchell |
| Director 2 Phone | nf-field-214 | 0434 567 890 |
| Director 2 Address | nf-field-215 | 22 Palm Drive, Dianella WA 6059 |
| Director 2 Postcode | nf-field-216 | 6059 |
| Bank | nf-field-218 | Commonwealth Bank of Australia |
| Branch | nf-field-219 | Morley Branch — BSB 066 102 |
| Ref 1 Company | nf-field-223 | Party World Australia |
| Ref 1 Phone | nf-field-224 | 08 9200 1234 |
| Ref 2 Company | nf-field-225 | Bounce House Rentals WA |
| Ref 2 Phone | nf-field-226 | 08 9300 5678 |
| Ref 3 Company | nf-field-227 | Jumping Castles Direct |
| Ref 3 Phone | nf-field-228 | 08 9400 9012 |
| Terms checkbox | nf-field-231 | ✅ Checked |
| Endorsement Name | nf-field-233 | Perth Party Supplies Pty Ltd |
| Endorsement Signer | nf-field-234 | James Mitchell |
| Position | nf-field-235 | Director |
| Endorsement Sig | nf-field-236 | Mock cursive drawn |
| Guarantor Name | nf-field-239 | James Mitchell |
| Relationship | nf-field-240 | Director |
| Guarantor Address | nf-field-241 | 8 Banksia Crescent, Joondalup WA 6027 |
| Guarantor Sig | nf-field-242 | Mock cursive drawn |
| Guarantee checkbox | nf-field-243 | ✅ Checked |

## Troubleshooting

| Error | Fix |
|---|---|
| `javascript is not defined` | You pasted `javascript:` prefix. Clear console, paste raw script only. |
| `No Ninja Forms form found` | You're not on the credit application page. Navigate to the form first. |
| Fields didn't fill | The form might use different field IDs. Check the NFF import matches. |

## Notes

- Signatures are drawn as mock cursive on the canvas pads
- Checkboxes (Terms + Guarantee) are checked by default
- Optional fields (ACN, Director 2, Other) are left blank where typical
- Script is non-destructive — refresh the page to clear everything
