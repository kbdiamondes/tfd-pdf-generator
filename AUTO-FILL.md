# TFD Credit Application — Auto-Fill Script

Paste this into the browser console on the credit application form page. It fills all 68 fields with realistic mock data.

> **⚠️ NF3 Field IDs change after import.** After re-importing the form, you must update the `nf-field-XXX` IDs in the script. Right-click each field → Inspect → find the `id` attribute on the input element.

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
    'nf-field-XXX': 'Perth Party Supplies Pty Ltd',   // textbox_2: Company Name
    'nf-field-XXX': '123 456 789',                     // textbox_3: ACN
    'nf-field-XXX': '63 667 911 944',                  // textbox_4: ABN
    'nf-field-XXX': 'Perth Party Co',                   // textbox_7: Trading Name

    // 2. Accounts Contact
    'nf-field-XXX': 'Sarah Mitchell',                  // textbox_10: Contact Name
    'nf-field-XXX': 'Accounts Manager',                // textbox_11: Position
    'nf-field-XXX': 'accounts@perthpartysupplies.com.au', // email_12: Email
    'nf-field-XXX': '0412 345 678',                    // phone_13: Phone
    'nf-field-XXX': '42 Wellington Street, Perth WA 6000', // textbox_14: Postal Address
    'nf-field-XXX': '6000',                            // textbox_15: Postcode

    // 3. Business Address
    'nf-field-XXX': '15 Industrial Drive, Malaga WA 6090', // textbox_17: Business Address
    'nf-field-XXX': '6090',                            // textbox_18: Postcode
    'nf-field-XXX': '08 9234 5678',                    // phone_19: Landline
    'nf-field-XXX': '0401 234 567',                    // phone_20: Mobile

    // 4. Directors
    'nf-field-XXX': 'James Mitchell',                  // textbox_24: Director 1 Name
    'nf-field-XXX': '0423 456 789',                    // phone_25: Director 1 Phone
    'nf-field-XXX': '8 Banksia Crescent, Joondalup WA 6027', // textbox_26: Director 1 Address
    'nf-field-XXX': '6027',                            // textbox_27: Director 1 Postcode
    'nf-field-XXX': 'Karen Mitchell',                  // textbox_29: Director 2 Name
    'nf-field-XXX': '0434 567 890',                    // phone_30: Director 2 Phone
    'nf-field-XXX': '22 Palm Drive, Dianella WA 6059', // textbox_31: Director 2 Address
    'nf-field-XXX': '6059',                            // textbox_32: Director 2 Postcode

    // 5. Banking
    'nf-field-XXX': 'Commonwealth Bank of Australia',  // textbox_34: Bank Name
    'nf-field-XXX': 'Morley Branch — BSB 066 102',    // textbox_35: Branch

    // 6. Trade References
    'nf-field-XXX': 'Party World Australia',           // textbox_39: Ref 1 Company
    'nf-field-XXX': '08 9200 1234',                    // phone_40: Ref 1 Phone
    'nf-field-XXX': 'Bounce House Rentals WA',         // textbox_41: Ref 2 Company
    'nf-field-XXX': '08 9300 5678',                    // phone_42: Ref 2 Phone
    'nf-field-XXX': 'Jumping Castles Direct',          // textbox_43: Ref 3 Company
    'nf-field-XXX': '08 9400 9012',                    // phone_44: Ref 3 Phone

    // 8. Endorsement
    'nf-field-XXX': 'Perth Party Supplies Pty Ltd',    // textbox_49: Company Name
    'nf-field-XXX': 'James Mitchell',                  // textbox_50: Full Name
    'nf-field-XXX': 'Director',                        // textbox_51: Position
    'nf-field-XXX': '16/09/2026',                      // date_52: Date

    // 9. Guarantee — Guarantor 1
    'nf-field-XXX': 'James Mitchell',                  // textbox_56: Full Name
    'nf-field-XXX': 'Director',                        // textbox_57: Relationship
    'nf-field-XXX': '8 Banksia Crescent, Joondalup WA 6027', // textbox_58: Address
    'nf-field-XXX': '16/09/2026',                      // date_60: Date

    // 9. Guarantee — Guarantor 2
    'nf-field-XXX': 'Karen Mitchell',                  // textbox_62: Full Name
    'nf-field-XXX': 'Director',                        // textbox_63: Relationship
    'nf-field-XXX': '22 Palm Drive, Dianella WA 6059', // textbox_64: Address
    'nf-field-XXX': '16/09/2026',                      // date_66: Date
  };

  // ── Fill text/email/phone/date fields ─────────────────────
  let filled = 0;
  for (const [id, val] of Object.entries(fields)) {
    if (setVal(id, val)) filled++;
  }

  // ── Radio buttons ──────────────────────────────────────────
  // Applicant type: "Pty Ltd Company" = index 0 (nf-field-XXX-0)
  // Registered biz name: "Yes" = index 0 (nf-field-XXX-0)
  // Update these IDs after import:
  // const radio189 = document.getElementById('nf-field-XXX-0');
  // if (radio189) { radio189.click(); filled++; }
  // const radio192 = document.getElementById('nf-field-XXX-0');
  // if (radio192) { radio192.click(); filled++; }

  // ── Checkboxes (Terms + Guarantee) ────────────────────────
  // Update these IDs after import:
  // const cbTerms = document.getElementById('nf-field-XXX');
  // if (cbTerms && !cbTerms.checked) { cbTerms.click(); filled++; }
  // const cbGuarantee = document.getElementById('nf-field-XXX');
  // if (cbGuarantee && !cbGuarantee.checked) { cbGuarantee.click(); filled++; }

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

  // Update these canvas IDs after import:
  // if (drawSig('nf-field-XXX')) filled++;  // Endorsement
  // if (drawSig('nf-field-XXX')) filled++;  // Guarantor 1
  // if (drawSig('nf-field-XXX')) filled++;  // Guarantor 2

  // ── Summary ────────────────────────────────────────────────
  console.log(`[TFCAP] ✅ Filled ${filled} fields with mock data.`);
})();
```

*(end of script)*

## Mock Data Summary

| Field | NF Key | Value |
|---|---|---|
| Company | textbox_2 | Perth Party Supplies Pty Ltd |
| ACN | textbox_3 | 123 456 789 |
| ABN | textbox_4 | 63 667 911 944 |
| Applicant type | listradio_5 | Pty Ltd Company (radio) |
| Trading Name | textbox_7 | Perth Party Co |
| Registered name | listradio_8 | Yes (radio) |
| Contact Name | textbox_10 | Sarah Mitchell |
| Position | textbox_11 | Accounts Manager |
| Email | email_12 | accounts@perthpartysupplies.com.au |
| Phone | phone_13 | 0412 345 678 |
| Postal Address | textbox_14 | 42 Wellington Street, Perth WA 6000 |
| Postcode | textbox_15 | 6000 |
| Business Address | textbox_17 | 15 Industrial Drive, Malaga WA 6090 |
| Business Postcode | textbox_18 | 6090 |
| Landline | phone_19 | 08 9234 5678 |
| Mobile | phone_20 | 0401 234 567 |
| Director 1 Name | textbox_24 | James Mitchell |
| Director 1 Phone | phone_25 | 0423 456 789 |
| Director 1 Address | textbox_26 | 8 Banksia Crescent, Joondalup WA 6027 |
| Director 1 Postcode | textbox_27 | 6027 |
| Director 2 Name | textbox_29 | Karen Mitchell |
| Director 2 Phone | phone_30 | 0434 567 890 |
| Director 2 Address | textbox_31 | 22 Palm Drive, Dianella WA 6059 |
| Director 2 Postcode | textbox_32 | 6059 |
| Bank | textbox_34 | Commonwealth Bank of Australia |
| Branch | textbox_35 | Morley Branch — BSB 066 102 |
| Ref 1 Company | textbox_39 | Party World Australia |
| Ref 1 Phone | phone_40 | 08 9200 1234 |
| Ref 2 Company | textbox_41 | Bounce House Rentals WA |
| Ref 2 Phone | phone_42 | 08 9300 5678 |
| Ref 3 Company | textbox_43 | Jumping Castles Direct |
| Ref 3 Phone | phone_44 | 08 9400 9012 |
| Terms checkbox | checkbox_47 | ✅ Checked |
| Endorsement Name | textbox_49 | Perth Party Supplies Pty Ltd |
| Endorsement Signer | textbox_50 | James Mitchell |
| Position | textbox_51 | Director |
| Endorsement Sig | signature_52 | Mock cursive drawn |
| Endorsement Date | date_52 | 16/09/2026 |
| Guarantor 1 Name | textbox_56 | James Mitchell |
| Relationship | textbox_57 | Director |
| Guarantor 1 Address | textbox_58 | 8 Banksia Crescent, Joondalup WA 6027 |
| Guarantor 1 Sig | signature_59 | Mock cursive drawn |
| Guarantor 1 Date | date_60 | 16/09/2026 |
| Guarantor 2 Name | textbox_62 | Karen Mitchell |
| Relationship | textbox_63 | Director |
| Guarantor 2 Address | textbox_64 | 22 Palm Drive, Dianella WA 6059 |
| Guarantor 2 Sig | signature_65 | Mock cursive drawn |
| Guarantor 2 Date | date_66 | 16/09/2026 |
| Guarantee checkbox | checkbox_67 | ✅ Checked |

## Troubleshooting

| Error | Fix |
|---|---|
| `javascript is not defined` | You pasted `javascript:` prefix. Clear console, paste raw script only. |
| `No Ninja Forms form found` | You're not on the credit application page. Navigate to the form first. |
| Fields didn't fill | The form might use different field IDs. Right-click field → Inspect → check `id`. |

## Notes

- Signatures are drawn as mock cursive on the canvas pads
- Checkboxes (Terms + Guarantee) are checked by default
- Optional fields (ACN, Director 2, Other) are left blank where typical
- Script is non-destructive — refresh the page to clear everything
- **NF3 IDs change after import** — update the `nf-field-XXX` placeholders before use
