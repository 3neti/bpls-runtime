# Actual Assessment Parity — Evaluator Financial Working Paper

Status: Focused operational-evidence reconciliation  
Evidence: `OPERATIONAL-IPIL-ASSESSMENT-001` and `OPERATIONAL-IPIL-ASSESSMENT-002`  
Decision authority: Board corrective-execution packet, 2026-08-30

This record maps the recognizable grammar of the Municipality of Ipil Computation/Assessment Slip to existing canonical BPLS nouns. It records structure only. Real identities, addresses, assessment identifiers, signatures, handwriting, and monetary values are intentionally excluded.

| Observed paper concept | Canonical noun / projection | Conformance | Boundary or uncertainty |
| --- | --- | --- | --- |
| Transaction and business header | `PermitApplication`, `Business`, `BusinessOwner` | Existing | Paper values are personal evidence and are not UAT data. |
| One or many Lines of Business | applicant `PermitApplicationLine` declaration plus municipal LOB determination in the Evaluation | Existing | Treasury correction preserves the declaration and selects canonical `LineOfBusiness` records; it does not create a new legal Business. |
| Computations grouped beneath each LOB | Evaluation `Charge` items and FeeRule projections carrying explicit LOB scope | Enriched projection | Applicability remains rule/office-owned; the paper does not prove universal charge applicability. |
| Charge labels such as Business Tax, Health Certificate, Laminated ID, Mayor's Permit Fee, Occupation Fee, Solid Waste Management, Weight & Measure, and Sanitary Permit Fee | charge identity/label evidence | Observed only | Labels are not commissioned rules, defaults, or formulas. Synthetic UAT labels exercise the grammar without copying actual amounts. |
| LOB subtotal | backend `financial_working_paper.line_sections[].subtotal_amount_cents` | Implemented | Derived only from included canonical resolved charges. Vue may display but not recalculate it. |
| Charges applying once to the application | FeeRule/Evaluation charge with `scope=application` | Existing and exposed | The supplied slips do not conclusively classify every observed charge by scope. |
| Grand Total | canonical resolved Evaluation total, exposed as `grand_total_amount_cents` only when required charge work is resolved | Implemented | Withheld while required charges are unresolved; no speculative total. |
| Prepared By | immutable `Assessment.assessed_by_id` / Assessment Officer | Existing | Preparation does not grant Treasurer approval. |
| Approved By — Municipal Treasurer | exact-snapshot `AssessmentDecision` | Existing | Treasurer approves or returns the immutable Assessment and does not edit Evaluation amounts. |
| Assessment lines retain LOB context | `AssessmentLine.permit_application_line_id` and `line_of_business_id` | Existing and wired for Evaluation charges | Treasury-added LOB may have canonical LOB context without falsifying a new applicant-declared line. |
| Schedule of Payments / Q1–Q4 | downstream `PaymentSchedule` | Existing boundary | Operational evidence is strong; allocation formula remains uncommissioned and is not inferred here. |
| Paper Computation/Assessment Slip | future document projection of immutable Assessment truth | Architectural fit | No separate slip calculator is authorized or introduced. |

## Implementation decision

The existing architecture naturally supports the evidenced grammar. The smallest change is a read-only, backend-derived grouped projection plus explicit charge scope metadata for Evaluation-item-derived Assessment lines. `Fact | Determination | Charge`, FeeRule selection/calculation, Evaluation revisions, immutable Assessment, exact Treasurer decision, and PaymentSchedule remain the only financial/lifecycle paths.

## Genuine later municipal questions

- Which observed charge labels are current, universally available financial identities?
- For each charge, what exact LOB/application scope, applicability, responsible office, legal basis, rate, rounding, and effective period are authorized?
- Which charges recur per LOB and which apply once per application?
- How are quarterly installments allocated when the first quarter differs from later quarters?
- What is the authoritative printed-slip layout and signatory configuration once canonical Assessment truth is commissioned for document generation?
