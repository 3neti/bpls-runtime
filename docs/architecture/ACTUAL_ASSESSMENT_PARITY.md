# Actual Assessment Parity — Evaluator Financial Working Paper

Status: **COMPUTATION/ASSESSMENT SLIP V1 EXECUTABLE**
Evidence: `OPERATIONAL-IPIL-ASSESSMENT-001`, `OPERATIONAL-IPIL-ASSESSMENT-002`, and the accepted 2026-09-01 Anaïs/Nelson Zoom walkthrough
Decision authority: Board bounded implementation packet, 2026-09-02

This record maps the recognizable grammar of the Municipality of Ipil Computation/Assessment Slip to existing canonical BPLS nouns. It records structure only. Real identities, addresses, assessment identifiers, signatures, handwriting, and monetary values are intentionally excluded.

| Observed paper concept | Canonical noun / projection | Conformance | Boundary or uncertainty |
| --- | --- | --- | --- |
| Transaction and business header | `PermitApplication`, `Business`, `BusinessOwner` | Existing | Paper values are personal evidence and are not UAT data. |
| One or many Lines of Business | applicant `PermitApplicationLine` declaration plus municipal LOB determination in the Evaluation | Existing | Treasury correction preserves the declaration and selects canonical `LineOfBusiness` records; it does not create a new legal Business. |
| Computations grouped beneath each LOB | eligible issued `PaperlessPaymentOrderLine` records plus governed canonical FeeRule projections carrying explicit scope | Executable | Applicability remains office/rule-owned; the paper does not prove universal applicability. |
| Charge labels such as Business Tax, Health Certificate, Laminated ID, Mayor's Permit Fee, Occupation Fee, Solid Waste Management, Weight & Measure, and Sanitary Permit Fee | charge identity/label evidence | Observed only | Labels are not commissioned rules, defaults, or formulas. Synthetic UAT labels exercise the grammar without copying actual amounts. |
| LOB subtotal | backend `financial_working_paper.line_sections[].subtotal_amount_cents` | Implemented | Derived only from included canonical resolved charges. Vue may display but not recalculate it. |
| Charges applying once to the application | FeeRule/Evaluation charge with `scope=application` | Existing and exposed | The supplied slips do not conclusively classify every observed charge by scope. |
| Grand Total | canonical resolved Evaluation total, exposed as `grand_total_amount_cents` only when required charge work is resolved | Implemented | Withheld while required charges are unresolved; no speculative total. |
| Prepared By | immutable `Assessment.assessed_by_id` / Assessment Officer | Existing | Preparation does not grant Treasurer approval. |
| Approved By — Municipal Treasurer | exact-snapshot `AssessmentDecision` | Existing | Treasurer approves or returns the immutable Assessment and does not edit Evaluation amounts. |
| Assessment lines retain LOB context | `AssessmentLine.permit_application_line_id` and `line_of_business_id` | Existing and wired for Evaluation charges | Treasury-added LOB may have canonical LOB context without falsifying a new applicant-declared line. |
| Schedule of Payments / Q1–Q4 | downstream `PaymentSchedule` | Existing boundary | Operational evidence is strong; allocation formula remains uncommissioned and is not inferred here. |
| Paper Computation/Assessment Slip | `BuildComputationAssessmentSlip`, executable HTML, and PDF project immutable Assessment truth | Executable V1 | It has no separate calculator and does not invent an official slip number. |

## Implementation decision

The executable boundary is `BPLO route → concerned-office determination → issued Paperless Payment Order → Assessment consolidation → separate Computation/Assessment Slip → Municipal Treasurer exact decision`.

Only current issued Payment Order lines tied to eligible resolved Evaluation revisions enter Assessment, once each through a database uniqueness constraint. Governed canonical FeeRule pricing remains the other authorized input. The slip groups those already-persisted Assessment lines by LOB, preserves application-wide charges, and verifies `LOB subtotals + application-wide subtotal = Grand Total`; it never recalculates liability.

The synthetic two-LOB specimen reconciles `PHP 330 + PHP 540 + PHP 350 = PHP 1,220`. Six office Payment Orders contribute PHP 870; one governed application-wide Business Inspection Fee contributes PHP 350.

## Genuine later municipal questions

- Which observed charge labels are current, universally available financial identities?
- For each charge, what exact LOB/application scope, applicability, responsible office, legal basis, rate, rounding, and effective period are authorized?
- Which charges recur per LOB and which apply once per application?
- How are quarterly installments allocated when the first quarter differs from later quarters?
- What official slip numbering policy and acknowledgement fact should be implemented?
- What correction/cancellation vocabulary, authority, and timing applies to Paperless Payment Orders? V1 permits correction only through a new immutable office revision/order and supersession; cancellation is not implemented.
