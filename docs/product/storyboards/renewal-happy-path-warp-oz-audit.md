# Independent Warp/Oz audit — Scenario 01 baseline

Disposition before bounded correction: **ENGINE PASS / PRODUCT FAIL**.

## Findings

- **P1 — Work discovery:** Health, Assessment Officer, Treasury, and Municipal Treasurer land on generic work surfaces. The Health role saw mixed fixtures without a responsibility-derived count, reason, LOB, or ownership signal. The already-approved Scenario 01 still exposed an `Assess` affordance in the generic application list.
- **P1 — Prepared Assessment grammar:** Assessment `16` rendered a flat table ordered independently of the accepted Ipil grammar. It omitted LOB sections/subtotals, application-wide subtotal, the Treasury no-correction evidence, and an unambiguous approved/payable display status.
- **P1 — Mobile Assessment:** at 390 px the Assessment table hid basis and amount columns behind unmarked horizontal scrolling. [Baseline screenshot](assets/renewal-happy-path/audit-mobile-assessment-current.png).
- **P2 — Responsibility evidence:** the Evaluation working paper was financially excellent and mobile-usable, but its concerned-office evidence did not surface the six department completions and selection reasons. [Baseline screenshot](assets/renewal-happy-path/audit-mobile-evaluation-current.png).
- **P2 — Application/payable wording:** the application and payable were understandable, but `Assessment: computed` and `Authority review: not ready` could be misread after exact Treasurer approval.

Authority behaved correctly: Health could not open the Payment Schedule; Treasurer had no payment-schedule navigation. No BPLS-origin console errors, broken images, duplicate IDs, or unnamed visible controls were observed.

## Chief Architect disposition

Authorize only UI/view-model corrections: responsibility-derived work discovery, frozen Assessment grouping in Nelson/Ipil grammar, visible counter-check/approval evidence, responsive Assessment rows, and visible canonical completion evidence. Do not change Actions, pricing, applicability, office ownership, permissions, Treasury/Treasurer authority, QR/receipt semantics, or stored lifecycle state.
