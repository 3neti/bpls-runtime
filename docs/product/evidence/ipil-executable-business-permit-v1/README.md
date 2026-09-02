# Ipil executable-document visual evidence

This bundle supports a side-by-side Board review of the supplied municipal artifact and the V1 executable document.

| Evidence | Purpose |
|---|---|
| `source-ipil-1.png`, `source-ipil-2.png` | Rendered pages of the supplied Municipality of Ipil PDF. |
| `rendered-desktop-new-entry.png` | Real Browser Lifecycle Laboratory 2025 New entry form at the desktop viewport. |
| `rendered-mobile-390x844-new-entry.png` | The same real form at exactly 390 × 844 CSS pixels. |
| `rendered-desktop-entry-top.png`, `rendered-mobile-390x844-viewport.png` | Review-sized crops of the actual captures; the mobile crop is exactly one 390 × 844 viewport. |
| `rendered-desktop-frozen.png`, `rendered-mobile-390x844-frozen.png` | The same lodged document after its SHA-256 declaration snapshot was frozen. |
| `rendered-desktop-2025-lifecycle.png` | Reopened 2025 document showing Assessment, Treasury counter-check, exact Treasurer approval, and “Permit not yet issued.” |
| `rendered-desktop-2026-renewal.png` | Reopened 2026 Renewal document for the same canonical Business chronology. |

Browser observations:

- Desktop: 1280 CSS pixels; document width equaled viewport width; no horizontal overflow.
- Mobile: exactly 390 × 844 CSS pixels; document width equaled viewport width; no horizontal overflow.
- The mobile document retained Last/First/Middle Name; distinct Business and Owner address sections; and per-LOB Code, Line of Business, No. of Units, Capitalization, Gross Sales Essential, and Gross Sales Non-Essential nouns.
- The real create/save/submit flow produced application 30 in the synthetic local Laboratory. Before submit the projection said `DRAFT`; after submit it said `FROZEN`, displayed a SHA-256 digest, and retained both Retail Trading and Food Service rows.
- Reopened Page 2 displayed the canonical ₱1,220 immutable Assessment, Treasury `no_correction`, exact Municipal Treasurer approval, and explicit `Permit not yet issued`.
- The 2025 New and 2026 Renewal documents both rendered without horizontal overflow and with zero captured application-console errors.

Full-page screenshots are intentionally retained as review evidence. Browser DOM checks, rather than screenshot stitching, are the authority for the zero-overflow and single-field-count assertions.
