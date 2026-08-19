# Approval Stage Municipal Decision Packet

Status: **RESOLVED FOR IMPLEMENTATION — Nelson municipal evidence accepted 2026-08-19**

Raised by: Nelson operational feedback received 2026-08-19

Related evidence: `docs/implementation/NELSON_OPERATIONAL_FEEDBACK_RECONCILIATION_2026-08-19.md`; `NFI-2026-001`

## Follow-up Source Evidence — Preserved Verbatim

1. Who approves before payment? `Municipal Treasurer`
2. What is approved? `Assessment/amount`
3. Assessment and approval one step or separate? `Yes one step, but it needs to be approved by the Municipal Treasurer. Assessment Officer is different from the Treasurer.`
4. If something is wrong/incomplete? `Returned for correction`
5. Does approval clear the applicant to proceed to payment? `Yes`

These answers are Nelson's source evidence. The domain interpretation below is an engineering disposition and does not rewrite his wording.

## Resolution

The evidence is sufficient to implement the missing pre-payment approval behavior without a Board return. Operationally, assessment and approval are experienced as one municipal workflow step, but Nelson identifies two actors and two authoritative facts:

1. the Assessment Officer prepares/computes the persisted assessment snapshot;
2. the Municipal Treasurer approves that exact assessment/amount before payment becomes available, or returns that assessment for correction.

The accepted consequence is permission to proceed to payment scheduling. Approval does not collect payment, issue a receipt, approve permit issuance or release, establish documentary sufficiency, or create legal effect.

## Accepted Domain And Audit Contract

- One immutable `assessment_decision` belongs to one exact assessment snapshot and records `approved` or `returned_for_correction`.
- The decision preserves assessment ID/sequence, amount, a SHA-256 fingerprint of the persisted assessment and line snapshots, decision actor, actor-role snapshot, timestamp, optional correction reason, before/after application state, and whether the decision authorizes payment scheduling.
- The Assessment Officer remains `assessed_by`; the Treasurer decision never overwrites the preparer.
- Payment schedule creation requires an `approved` decision whose amount and fingerprint still match the persisted assessment.
- A returned assessment remains in assessment processing and cannot produce a payment schedule. The existing assessment action may prepare a corrected assessment as a new sequence; that new snapshot has no inherited decision.
- Normal municipal operation is one approval per payable assessment snapshot. A material correction or recomputation creates a fresh snapshot and therefore requires fresh approval. This is a fail-closed audit-integrity inference, not an invented reassessment procedure.
- The narrow permission `assessments.approve` separates server authorization from `permit_applications.assess`. Its presence in the synthetic Treasury UAT bundle is preview projection only; no production user or final municipal role assignment is made autonomously.
- The gate applies to every operational assessment that uses the canonical payment-schedule action. Any application-type exception requires later municipal evidence; no exception is inferred.

## Remaining Narrow Questions — Non-blocking For This Implementation

- Whether correction reasons become mandatory, and whether a formal appeal/escalation path exists.
- Whether any application type has an approved exception to the canonical pre-payment Treasurer decision.
- How historical `Approval`, `approvedAt`, and `approvedBy` map to the new decision fact; no historical approval is fabricated or backfilled.
- Which actual production roles/users receive `assessments.approve`; the current implementation defines capability, not municipal staffing policy.

## Decision Boundary

Nelson describes “one-time assessment and approval” before one-time payment. TOR/Discovery also identify evaluation and approval, and the legacy source contains an `Approval` status plus historical approval fields. Current Laravel has an `approval` enum value for parity/migration shape, but its operational path computes an assessment and can immediately prepare a payment schedule. It has no accepted approval fact, action, queue, actor, permission, outcome, or audit contract.

This is a missing domain behavior, not a terminology-only correction. Historical fields and a status label do not establish current municipal meaning or authority.

## Questions Requiring an Authoritative Answer

1. **Who approves?** Name the office, role, and accountable position. Distinguish the authorized official from a staff member who records the decision.
2. **What exactly is approved?** Select or define the municipal act: application completeness, documentary sufficiency, assessment, business permit application, recommendation for permit issuance, permission to proceed to payment, or another act.
3. **When does approval occur relative to assessment?** Before assessment, simultaneous with assessment, after assessment but before payment, or another order?
4. **One operation or distinct facts?** Is “assessment and approval” one combined operational step with two independently auditable facts, or one single authoritative fact?
5. **What are the outcomes?** Define approved, rejected/denied, returned for correction, documentary deficiency, cancelled, and any other outcome without collapsing them into one status.
6. **What happens after return or rejection?** Who may correct/resubmit, what remains immutable, whether reasons are mandatory, and whether appeal/review exists?
7. **What happens on reassessment?** Does any material amendment, fee override, documentary change, corrected declaration, or reassessment revoke or require renewed approval?
8. **How often is approval required?** Once per application, once per assessment version, or according to another municipal rule?
9. **What evidence proves approval?** Required timestamp, approver identity/office, signature or attestation, decision basis, assessment version, document set/version, remarks/reason, and retention/audit requirements.
10. **What does approval authorize?** Payment schedule preparation, Treasury collection, application continuation only, a recommendation for issuance, or another consequence?
11. **Does approval apply to every application type?** New, renewal, additional, amendment, transfer, retirement/closure, and reassessment may differ.
12. **How does legacy evidence map?** Do legacy `Approval`, `approvedAt`, and `approvedBy` represent the same accepted act, a queue position, or historical evidence requiring a separate mapping rule?

## Requested Municipal Decision Record

An accepted answer should identify:

- authoritative office and approving role;
- approved object and exact municipal meaning;
- ordering relative to assessment, payment scheduling, and collection;
- outcome vocabulary and correction/rejection procedure;
- recurrence rule for reassessment/material change;
- minimum audit/documentary evidence;
- operational consequence of approval;
- application types covered and exceptions;
- legacy mapping disposition;
- source of authority: current written procedure, ordinance/TOR interpretation accepted by the Municipality, production form/register, office order, or signed decision record.

## Explicit Non-Decisions

Acceptance of an approval stage would not by itself decide:

- assessment formulas, liability, installments, due dates, receipt authority, or Treasury reconciliation;
- documentary applicability or sufficiency rules unless expressly included;
- clearance catalog or completion authority;
- Mayor signatory authority or signature procedure;
- BPLO issuance/release authority, lawful release prerequisites, validity, or legal effect;
- official report inclusion, generation, export, printing, or certification.

## Engineering Disposition

The former engineering hold is lifted only for: prepared assessment -> Treasurer approve/return -> exact-snapshot payment gate, its audit trail, the existing assessment workspace presentation, and deterministic synthetic UAT projection. A broad approval queue, applicant rejection, documentary-deficiency workflow, permit signing/issuance/release, production role assignment, historical backfill, and production migration remain outside this decision.
