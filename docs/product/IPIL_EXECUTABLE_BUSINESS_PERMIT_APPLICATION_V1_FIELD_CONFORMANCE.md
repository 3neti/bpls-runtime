# Ipil Application Form — V1 Field-Conformance Matrix

Source: Municipality of Ipil, two-page **Application Form for Business Permit** supplied by the product owner. Every meaningful field, checkbox, column, signature, instruction, and section has an explicit disposition.

Disposition vocabulary:

- **CANONICAL NOW** — represented by authoritative BPLS truth and projected now.
- **CANONICAL — PROJECTION MISSING** — authoritative truth exists, but this document projection does not yet show it.
- **DECLARATION FACT REQUIRED** — preserved in the immutable lodged applicant declaration.
- **MUNICIPAL SEMANTICS UNRESOLVED** — the source meaning or authority must be confirmed before execution.
- **FUTURE LIFECYCLE** — intentionally belongs to a later canonical workflow.

## Page 1 — Application / applicant declaration

| Source element | Disposition | V1 treatment |
|---|---|---|
| Application Form for Business Permit; Tax Year | CANONICAL NOW | Recognizable document header; application year from canonical application. |
| Application No. | CANONICAL NOW | Canonical application number, or “Not yet assigned.” |
| Application made to the Mayor / municipal preamble | DECLARATION FACT REQUIRED | Institutional wording retained by the executable document. |
| New; Renewal; Additional | CANONICAL NOW | New/Renewal use canonical application type. Additional remains visible but unavailable until its type is governed. |
| Transfer of Ownership; Transfer of Location | MUNICIPAL SEMANTICS UNRESOLVED | Visible, disabled, and not silently folded into application type. |
| Amendment: Single→Partnership; Single→Corporation; Partnership→Single; Partnership→Corporation; Corporation→Single; Corporation→Partnership | MUNICIPAL SEMANTICS UNRESOLVED | Six source choices remain explicitly visible; no registry mutation semantics invented. |
| Mode of Payment: Annually; Semi-Annually; Quarterly | DECLARATION FACT REQUIRED | Preserved as applicant choice; no installment formula or execution added. |
| Date of Application | DECLARATION FACT REQUIRED | Frozen declaration date, distinct from system timestamps. |
| DTI/SEC/CDA Registration No. | DECLARATION FACT REQUIRED | Explicit registration number; not collapsed into “Registration Details.” |
| Reference No. | DECLARATION FACT REQUIRED | Separate declaration fact. |
| DTI/SEC/CDA Date of Registration | DECLARATION FACT REQUIRED | Separate registration date. |
| Type of Organization: Single; Partnership; Corporation; Cooperative; Religious/Non-Profit | DECLARATION FACT REQUIRED | Source choices preserved; maps to canonical ownership type where accepted. |
| CTC No.; TIN | DECLARATION FACT REQUIRED | Two distinct declaration facts. |
| Tax incentive from Government Entity: Yes; No; entity name | DECLARATION FACT REQUIRED | Boolean and granting entity frozen separately. |
| Name of Taxpayer: Last Name; First Name; Middle Name | DECLARATION FACT REQUIRED | Three explicit fields; never replaced by Full Name. |
| Business Name; Business Plate No.; Trade Name / Franchise | DECLARATION FACT REQUIRED | Three separate facts; canonical registry values may prefill without collapsing them. |
| For corporations: President/Treasurer Last Name; First Name; Middle Name | DECLARATION FACT REQUIRED | Three explicit officer-name fields; applicant declaration only in V1. |
| Business Address: House/Bldg No.; Building Name; Unit No.; Street; Barangay; Subdivision; City/Municipality; Province; Telephone No.; Email Address | DECLARATION FACT REQUIRED | Ten explicit business-address/contact facts, separate from owner address. |
| Owner Address: House/Bldg No.; Building Name; Unit No.; Street; Barangay; Subdivision; City/Municipality; Province; Telephone No.; Email Address | DECLARATION FACT REQUIRED | Ten explicit owner-address/contact facts, even when identical to business address. |
| Property Index Number (PIN) | DECLARATION FACT REQUIRED | Explicit field; canonical registry value may prefill. |
| Business Area (sq. m.); Total No. of Employees; No. of Employees Residing in LGU | DECLARATION FACT REQUIRED | Three separate quantitative declarations. |
| Rented premises; Monthly Rental | DECLARATION FACT REQUIRED | Occupancy and monthly rent preserved separately. |
| Lessor: Last Name; First Name; Middle Name | DECLARATION FACT REQUIRED | Three explicit conditional fields. |
| Lessor address: House/Bldg No.; Street; Barangay; Subdivision; City/Municipality; Province; Telephone No.; Email Address | DECLARATION FACT REQUIRED | Eight explicit conditional address/contact fields. |
| Emergency contact person; Telephone; Mobile; Email | DECLARATION FACT REQUIRED | Four facts, distinct from owner and business contacts. |
| Line of Business — Code | DECLARATION FACT REQUIRED | Canonical catalog code is frozen with the lodged line. |
| Line of Business — Name / Line of Business | DECLARATION FACT REQUIRED | Canonical catalog name is frozen; catalog/scenario isolation is enforced. |
| Line of Business — No. of Units | DECLARATION FACT REQUIRED | Explicit per-row quantity. |
| Line of Business — Capitalization (New) | DECLARATION FACT REQUIRED | Explicit per-row capitalization. |
| Line of Business — Gross Sales Essential | DECLARATION FACT REQUIRED | Explicit per-row amount. |
| Line of Business — Gross Sales Non-Essential | DECLARATION FACT REQUIRED | Explicit per-row amount; accepted aggregate remains available for existing assessment semantics. |
| Oath/undertaking and truthfulness statement | DECLARATION FACT REQUIRED | Positive acceptance is required at lodging. |
| Signature over Printed Name | DECLARATION FACT REQUIRED | Printed name is frozen; electronic-signature sufficiency remains outside this wave. |
| Position/Title | DECLARATION FACT REQUIRED | Explicit and frozen, not inferred after lodging. |

## Page 2 — Verification, assessment, approval, and permit

| Source element | Disposition | V1 treatment |
|---|---|---|
| Assessments section | CANONICAL NOW | Projected from the current immutable canonical Assessment only. |
| Assessment columns: Local Taxes/Fees; Reference; Amount Due; Penalty/Surcharge; Total; Assessed By | CANONICAL NOW | Description/reference/amount/total are projected from assessment lines; penalty is shown only when canonically recorded; assessor is canonical where available. |
| Business Tax; Mayor's Permit; Solid Waste Fee; Sanitary Inspection Fee; Health Certificate Fee; Occupational/Calling Fee; Laminated ID | CANONICAL NOW | Canonical assessment line descriptions are projected when present; no missing row or price is invented. |
| Quarterly assessment/payment computations printed on source | MUNICIPAL SEMANTICS UNRESOLVED | No quarterly formula or execution. |
| Verification section and columns: Description; Issuing Office; Recommending Approval; Date Issued; Verified By | CANONICAL NOW | Canonical clearance facts are projected where available; recommending-approval stays blank without canonical truth. |
| Barangay Clearance; Zoning Clearance (New/Renew); Building/Occupancy Permit (New/Renew) | CANONICAL NOW | Matched to canonical clearances; otherwise marked unavailable. |
| Other verification rows | CANONICAL — PROJECTION MISSING | Existing canonical clearances beyond the three source rows are not added to the source-shaped table in V1. |
| Assessment Reviewed By | MUNICIPAL SEMANTICS UNRESOLVED | No separate review authority invented. |
| Approval Recommended By | MUNICIPAL SEMANTICS UNRESOLVED | No recommendation authority invented. |
| Instruction 1: present form to BPLO for verification | CANONICAL NOW | Document lifecycle conveys verification state; source instruction remains institutional context. |
| Instruction 2: payment based on schedule | MUNICIPAL SEMANTICS UNRESOLVED | No quarterly payment execution or formula. |
| Treasury counter-check | CANONICAL NOW | Exact canonical counter-check result and time projected. |
| Municipal Treasurer approval | CANONICAL NOW | “Approved” only for the exact canonical Treasurer `approve` action; other decisions are not relabeled. |
| Permit-grant paragraph / authority to operate | FUTURE LIFECYCLE | Explicitly **Permit not yet issued** in V1. |
| Permit granted date; CTC/OR/payment particulars | FUTURE LIFECYCLE | No permit release, Collection/OR certification, or payment particulars invented. |
| Municipal Mayor signature/name/authority | MUNICIPAL SEMANTICS UNRESOLVED | Projection explicitly records Mayor-signature authority as unresolved. |

## Totals and chronology proof

The accepted scenario continues to use one Citizen → BusinessOwner → Business across the 2025 New application and 2026 Renewal. Where the scenario assessment applies, canonical lines remain Retail Trading ₱330, Food Service ₱540, and governed Business Inspection Fee ₱350, totaling ₱1,220. The executable document only renders those immutable assessment facts.
