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
  setVal('nf-field-396', 'Perth Party Supplies Pty Ltd');  filled++;
  setVal('nf-field-397', '123 456 789');                   filled++;
  setVal('nf-field-398', '63 667 911 944');                filled++;
  document.getElementById('nf-field-399-0')?.click();       filled++;  // Pty Ltd Company
  setVal('nf-field-401', 'Perth Party Co');                filled++;
  document.getElementById('nf-field-402-0')?.click();       filled++;  // Yes

  // ── 2. ACCOUNTS CONTACT DETAILS ────────────────────────────
  setVal('nf-field-404', 'Sarah Mitchell');                filled++;
  setVal('nf-field-405', 'Accounts Manager');              filled++;
  setVal('nf-field-406', 'accounts@perthpartysupplies.com.au'); filled++;
  setVal('nf-field-407', '0412 345 678');                  filled++;
  setVal('nf-field-408', '42 Wellington Street, Perth WA 6000'); filled++;
  setVal('nf-field-409', '6000');                          filled++;

  // ── 3. BUSINESS ADDRESS ────────────────────────────────────
  setVal('nf-field-411', '15 Industrial Drive, Malaga WA 6090'); filled++;
  setVal('nf-field-412', '6090');                          filled++;
  setVal('nf-field-413', '08 9234 5678');                  filled++;
  setVal('nf-field-414', '0401 234 567');                  filled++;

  // ── 4. DIRECTORS ───────────────────────────────────────────
  setVal('nf-field-418', 'James Mitchell');                filled++;
  setVal('nf-field-419', '0423 456 789');                  filled++;
  setVal('nf-field-420', '8 Banksia Crescent, Joondalup WA 6027'); filled++;
  setVal('nf-field-421', '6027');                          filled++;
  setVal('nf-field-423', 'Karen Mitchell');                filled++;
  setVal('nf-field-424', '0434 567 890');                  filled++;
  setVal('nf-field-425', '22 Palm Drive, Dianella WA 6059'); filled++;
  setVal('nf-field-426', '6059');                          filled++;

  // ── 5. BANKING ─────────────────────────────────────────────
  setVal('nf-field-428', 'Commonwealth Bank of Australia'); filled++;
  setVal('nf-field-429', 'Morley Branch — BSB 066 102');  filled++;

  // ── 6. TRADE REFERENCES ────────────────────────────────────
  setVal('nf-field-433', 'Party World Australia');         filled++;
  setVal('nf-field-434', '08 9200 1234');                  filled++;
  setVal('nf-field-435', 'Bounce House Rentals WA');       filled++;
  setVal('nf-field-436', '08 9300 5678');                  filled++;
  setVal('nf-field-437', 'Jumping Castles Direct');        filled++;
  setVal('nf-field-438', '08 9400 9012');                  filled++;

  // ── 7. TERMS ───────────────────────────────────────────────
  document.getElementById('nf-field-441')?.click();         filled++;

  // ── 8. ENDORSEMENT ─────────────────────────────────────────
  setVal('nf-field-443', 'Perth Party Supplies Pty Ltd');  filled++;
  setVal('nf-field-444', 'James Mitchell');                filled++;
  setVal('nf-field-445', 'Director');                      filled++;

  // ── 9. GUARANTEE — GUARANTOR 1 ─────────────────────────────
  setVal('nf-field-451', 'James Mitchell');                filled++;
  setVal('nf-field-452', 'Director');                      filled++;
  setVal('nf-field-453', '8 Banksia Crescent, Joondalup WA 6027'); filled++;

  // ── 9. GUARANTEE — GUARANTOR 2 ─────────────────────────────
  setVal('nf-field-457', 'Karen Mitchell');                filled++;
  setVal('nf-field-458', 'Director');                      filled++;
  setVal('nf-field-459', '22 Palm Drive, Dianella WA 6059'); filled++;

  // Guarantee checkbox
  document.getElementById('nf-field-462')?.click();         filled++;

  // ── SIGNATURES — draw mock cursive ─────────────────────────
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

## Notes

- NF3 IDs: 396–462 (shift after each re-import)
- If IDs change, run the discovery script to find new IDs
- Signatures are drawn as mock cursive
- All checkboxes are checked
- Script is non-destructive — refresh to clear
