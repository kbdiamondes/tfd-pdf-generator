# TFD Credit Application — Auto-Fill Script

Paste this into the browser console on the credit application form page. It fills all fields with realistic mock data.

## How to Use

1. Open the credit application form in your browser
2. Open DevTools → Console (`Cmd+Option+J` on Mac)
3. **Do NOT type `javascript:`** — just paste the script directly
4. Press Enter

## The Script

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

  // ── 1. APPLICANT DETAILS ───────────────────────────────────
  setVal('nf-field-316', 'Perth Party Supplies Pty Ltd');  filled++;  // Company Name
  setVal('nf-field-317', '123 456 789');                   filled++;  // ACN
  setVal('nf-field-318', '63 667 911 944');                filled++;  // ABN
  // Radio: Applicant is a → Pty Ltd Company (index 0)
  const r319 = document.getElementById('nf-field-319-0');
  if (r319) { r319.click(); filled++; }
  setVal('nf-field-321', 'Perth Party Co');                filled++;  // Trading Name
  // Radio: Registered biz name → Yes (index 0)
  const r322 = document.getElementById('nf-field-322-0');
  if (r322) { r322.click(); filled++; }

  // ── 2. ACCOUNTS CONTACT DETAILS ────────────────────────────
  setVal('nf-field-324', 'Sarah Mitchell');                filled++;  // Contact Name
  setVal('nf-field-325', 'Accounts Manager');              filled++;  // Position
  setVal('nf-field-326', 'accounts@perthpartysupplies.com.au'); filled++; // Email
  setVal('nf-field-327', '0412 345 678');                  filled++;  // Phone
  setVal('nf-field-328', '42 Wellington Street, Perth WA 6000'); filled++; // Postal Address
  setVal('nf-field-329', '6000');                          filled++;  // Postcode

  // ── 3. BUSINESS ADDRESS ────────────────────────────────────
  setVal('nf-field-331', '15 Industrial Drive, Malaga WA 6090'); filled++; // Business Address
  setVal('nf-field-332', '6090');                          filled++;  // Business Postcode
  setVal('nf-field-333', '08 9234 5678');                  filled++;  // Landline
  setVal('nf-field-334', '0401 234 567');                  filled++;  // Mobile

  // ── 4. DIRECTORS ───────────────────────────────────────────
  // Director 1
  setVal('nf-field-338', 'James Mitchell');                filled++;  // D1 Name
  setVal('nf-field-339', '0423 456 789');                  filled++;  // D1 Phone
  setVal('nf-field-340', '8 Banksia Crescent, Joondalup WA 6027'); filled++; // D1 Address
  setVal('nf-field-341', '6027');                          filled++;  // D1 Postcode
  // Director 2
  setVal('nf-field-343', 'Karen Mitchell');                filled++;  // D2 Name
  setVal('nf-field-344', '0434 567 890');                  filled++;  // D2 Phone
  setVal('nf-field-345', '22 Palm Drive, Dianella WA 6059'); filled++; // D2 Address
  setVal('nf-field-346', '6059');                          filled++;  // D2 Postcode

  // ── 5. BANKING ─────────────────────────────────────────────
  setVal('nf-field-348', 'Commonwealth Bank of Australia'); filled++; // Bank
  setVal('nf-field-349', 'Morley Branch — BSB 066 102');  filled++;  // Branch

  // ── 6. TRADE REFERENCES ────────────────────────────────────
  setVal('nf-field-353', 'Party World Australia');         filled++;  // Ref 1 Company
  setVal('nf-field-354', '08 9200 1234');                  filled++;  // Ref 1 Phone
  setVal('nf-field-355', 'Bounce House Rentals WA');       filled++;  // Ref 2 Company
  setVal('nf-field-356', '08 9300 5678');                  filled++;  // Ref 2 Phone
  setVal('nf-field-357', 'Jumping Castles Direct');        filled++;  // Ref 3 Company
  setVal('nf-field-358', '08 9400 9012');                  filled++;  // Ref 3 Phone

  // ── 7. TERMS ───────────────────────────────────────────────
  const cb361 = document.getElementById('nf-field-361');
  if (cb361 && !cb361.checked) { cb361.click(); filled++; }

  // ── 8. ENDORSEMENT ─────────────────────────────────────────
  setVal('nf-field-363', 'Perth Party Supplies Pty Ltd');  filled++;  // Company
  setVal('nf-field-364', 'James Mitchell');                filled++;  // Full Name
  setVal('nf-field-365', 'Director');                      filled++;  // Position
  // Date — find the visible date input (not the hidden one)
  const dateInputs = document.querySelectorAll('input[placeholder="dd/mm/yyyy"]');
  if (dateInputs[0]) {
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
    if (setter) setter.call(dateInputs[0], '16/09/2026');
    else dateInputs[0].value = '16/09/2026';
    dateInputs[0].dispatchEvent(new Event('input',  { bubbles: true }));
    dateInputs[0].dispatchEvent(new Event('change', { bubbles: true }));
    filled++;
  }

  // ── 9. GUARANTEE — GUARANTOR 1 ─────────────────────────────
  setVal('nf-field-371', 'James Mitchell');                filled++;  // G1 Name
  setVal('nf-field-372', 'Director');                      filled++;  // G1 Relationship
  setVal('nf-field-373', '8 Banksia Crescent, Joondalup WA 6027'); filled++; // G1 Address

  // ── 9. GUARANTEE — GUARANTOR 2 ─────────────────────────────
  setVal('nf-field-377', 'Karen Mitchell');                filled++;  // G2 Name
  setVal('nf-field-378', 'Director');                      filled++;  // G2 Relationship
  setVal('nf-field-379', '22 Palm Drive, Dianella WA 6059'); filled++; // G2 Address

  // Guarantee checkbox
  const cb382 = document.getElementById('nf-field-382');
  if (cb382 && !cb382.checked) { cb382.click(); filled++; }

  // ── SIGNATURES — draw mock cursive ─────────────────────────
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
    return true;
  }

  // Signatures — find by canvas parent field ID
  document.querySelectorAll('canvas').forEach(canvas => {
    if (drawSig(canvas.id)) filled++;
  });

  console.log(`[TFCAP] ✅ Filled ${filled} fields with mock data.`);
})();
```

## Notes

- NF3 IDs: 316–392 (may shift after re-import)
- If IDs change, run the discovery script in Step 1 to find new IDs
- Signatures are drawn as mock cursive
- All checkboxes are checked
- Script is non-destructive — refresh to clear
