# Domain Model

Architecture date: 2026-08-13

This model starts from business meaning, not tables. `Confirmed` means supported by Discovery evidence. `Interpretation` means an architectural reading of that evidence.

## Core Concepts

| Concept | Meaning | Evidence status |
| --- | --- | --- |
| Business Owner | Natural or juridical owner/applicant/taxpayer identity tied to businesses and applications. | Confirmed |
| Business | Establishment or undertaking operating in Ipil, with registration, ownership, address, employees, occupancy, and documents. | Confirmed |
| Line of Business | A business activity/classification used for assessment and reporting. | Confirmed |
| Permit Application | Request for business permit processing through statuses such as Draft, Assessment, Approval, Pending Payment, Released. | Confirmed |
| Assessment | Deterministic calculation and explanation of taxes, fees, charges, overrides, exclusions, and basis values. | Confirmed |
| Fee / Tax / Charge | A billable item governed by ordinance, configuration, or Treasury policy. | Confirmed |
| Payment Schedule | One or more due sections for an assessed application. | Confirmed |
| Collection / Payment | Treasury receipt of money or recognized payment event against a schedule or billing record. | Confirmed |
| Receipt | Government payment evidence whose numbering authority is unresolved. | Confirmed, policy unresolved |
| Clearance | Required office/department clearance that gates permit issuance. | Confirmed |
| Permit | Issued Mayor's Permit / business permit document, QR-verifiable. | Confirmed |
| Billing Group | Configurable non-permit Treasury collection type with fields, line items, and receipt layout. | Confirmed legacy concept, acceptance unresolved |
| Report | Operational or statutory output derived from transactions and reference data. | Confirmed |
| Municipality Configuration | Name, address, offices, officials, signatories, permit numbering, expiry. | Confirmed |
| Municipality Theme | Seal, logo, colors, favicon, presentation identity. | Confirmed |

## Consistency Boundaries

These are useful consistency boundaries, not package boundaries.

### Business Owner / Business Registry

Owns identity, business profile, location, registration, ownership, blacklist flags, and migration provenance. It should not own fee calculation or permit lifecycle state.

Important invariants:

- a business has an owner;
- citizen access to businesses is ownership-scoped;
- blacklist/refusal signals must be visible to permit workflow.

### Permit Application

Owns application status, application type, submitted business snapshot where needed, LOB selections, assessment snapshot, payment schedule link, approval/release history, and source provenance.

Important invariants:

- status transitions are explicit and audited;
- changing assessment after payment requires special authority;
- application state cannot be inferred only from payments without recording lifecycle history.

### Assessment

Owns calculation results and explanatory basis. It should produce immutable or versioned assessment snapshots once used for payment.

Important invariants:

- monetary results use integer minor units or fixed decimal columns, never floats;
- every line item records formula/range/config provenance;
- overrides record actor, reason, and previous value;
- unknown rules such as PIL are modeled as policy seams, not invented defaults.

### Treasury Collection

Owns payment/collection events, receipt metadata, payment method, reference numbers, reversals/voids, and reconciliation state.

Important invariants:

- payments are append-only or reversal-based where possible;
- voiding does not erase the original collection event;
- receipt numbers cannot be silently duplicated once authority rules are known;
- online payment and OTC payment share collection semantics but differ by channel/reconciliation.

### Clearance / Permit Issuance

Owns required clearance checklist, completion, permit issuance, QR verification, and document generation request.

Important invariants:

- permit issuance is blocked until required clearances are complete;
- issued permits record who issued them and when;
- QR verification must not expose private application data.

### Billing Group

Owns configurable non-permit collection definitions, custom fields, fee library, line items, records, and receipts.

Important invariants:

- paid/completed records have restricted edit rules;
- dynamic fields are configuration, not schema churn;
- whether billing groups satisfy explicit Treasury modules remains an owner decision.

### Reporting

Owns query definitions, projections, generated artifacts, and export status. It reads operational data and assessment/payment snapshots; it does not mutate business workflow.

Important invariants:

- report totals trace back to transactions;
- generated artifacts record input filters and generation timestamp;
- projections are rebuildable from operational data.

## Lifecycle Ownership

- Application submission: Permit Application boundary.
- Assessment calculation: Assessment boundary.
- Approval: Permit Application boundary with authorization/audit.
- Payment/collection: Treasury Collection boundary.
- Clearance completion: Clearance boundary.
- Permit issuance: Permit boundary.
- Report generation: Reporting boundary.
- Configuration changes: owning configuration boundary plus audit.

## Confirmed vs Interpretation

Confirmed:

- BPLS needs staff operations, citizen operations, permits, clearances, assessment, payments, billing groups, reports, documents, settings, and audit.
- The legacy status vocabulary includes Draft, Assessment, Approval, Pending Payment, Released.
- The ordinance governs fee/tax behavior and includes rules not proven in source.

Architectural interpretation:

- `Released` should be treated carefully as legacy status evidence, not necessarily the final domain word for "permit issued".
- Assessment snapshots should become explicit records to support tests, audit, and migration.
- Billing groups are a configurable Treasury collection mechanism, pending owner acceptance.
