# Nelson Feedback Intake

Status: **Operational feedback received — semantic reconciliation in progress**

Baseline: **Nelson Visual Walkthrough / UI/UX Cycle 1 frozen; 2026-08-19 operational feedback additive**

As of: 2026-08-19

## Purpose

This register converts Nelson's review of the frozen, browser-evidenced Laravel walkthrough into traceable program evidence. It does not authorize UI Cycle 2 by itself and does not replace the Terms of Reference, Revenue Code, production evidence, or the decision of an authorized municipal official.

Nelson feedback is operational evidence. It is not automatically legal, fiscal, Treasury, numbering, signatory, issuance, release, or legal-effect authority.

## Intake Rule

Create one observation for one independently decidable point. Preserve Nelson's words before interpretation. Assign exactly one primary classification from this closed list:

1. UI presentation correction
2. Terminology correction
3. Workflow/parity correction
4. Missing capability
5. Reference/configuration data
6. Financial-policy evidence
7. Treasury procedure
8. Permit/signatory/authority evidence
9. Migration/reconciliation evidence
10. Legacy behavior that should NOT be reproduced
11. Requires further municipal evidence
12. Board Trigger

Do not use a new category to avoid a difficult disposition. If one comment contains multiple independently decidable points, split it into separate observations and cross-reference them.

## Observation Template

```text
Observation ID: NFI-YYYY-NNN
Date received:
Source and review context:
Frozen Cycle 1 scene or screen:
Primary classification:

What Nelson said:

Current Laravel behavior:

Known legacy behavior:

Applicable TOR / Revenue Code / production evidence:

Proposed disposition:

Engineering may act autonomously: YES / NO
Reason:

Municipal confirmation required: YES / NO
Required authority or evidence, if YES:

Related observations or decision packets:
Disposition state: RECEIVED / EVIDENCE CHECK / READY FOR ENGINEERING / MUNICIPAL CONFIRMATION REQUIRED / BOARD REVIEW REQUIRED / CLOSED
Decision or implementation reference:
```

## Triage Rules

- `Engineering may act autonomously: YES` is permitted only when the correction preserves domain meaning, authority, liability, and the frozen evidence record.
- Terminology that changes a legal, fiscal, clearance, payment, permit, issuance, release, or status meaning requires municipal confirmation even if the requested wording appears simple.
- Workflow feedback may describe actual municipal practice without authorizing software to enforce it. Compare it with the TOR, Revenue Code, production evidence, and current Laravel behavior before disposition.
- A legacy behavior may be retained for familiarity, corrected as a defect, or refused because it violates an approved boundary. Legacy behavior is evidence, not automatic parity authority.
- Financial-policy, Treasury, numbering, signatory, issuance, release, legal-effect, identity-policy, taxpayer-liability, architecture, production-mutation, and cutover questions follow their existing acceptance or Board path.
- A closed observation retains its original statement, evidence, disposition, and decision reference. Do not overwrite history when later evidence changes the conclusion.

## Register

### NFI-2026-001 — One-time assessment and approval flow

- **Date received:** 2026-08-19
- **Source and review context:** Nelson operational feedback, reconciled after the frozen Stakeholder Preview / UAT baseline.
- **Frozen Cycle 1 scene or screen:** Cross-role application journey; no frozen scene is modified.
- **Primary classification:** Missing capability
- **What Nelson said:** Operational flow is application -> one-time assessment and approval -> one-time payment -> clearances -> permit release.
- **Current Laravel behavior:** Application -> assessment -> `single` full-assessment payment schedule -> collection/receipt -> clearances -> `ready_for_authority_review`; there is no operational approval fact/action/queue, and release remains disabled.
- **Known legacy behavior:** TOR/Discovery include evaluation and approval; legacy preserves `Approval`, `approvedAt`, and `approvedBy`. Historical `Released` could precede clearance completion.
- **Applicable TOR / Revenue Code / production evidence:** TOR sequence and Discovery CAP-018; no new fiscal, signatory, issuance, or production-mutation authority supplied.
- **Proposed disposition:** Preserve the sequence as evidence; treat approval as a genuine decision boundary; do not interpret “one-time” as universal payment-policy authority.
- **Engineering may act autonomously:** NO
- **Reason:** Approval meaning, actor, audit proof, outcomes, recurrence, and consequence are unknown.
- **Municipal confirmation required:** YES
- **Required authority or evidence:** Complete `docs/implementation/APPROVAL_STAGE_DECISION_PACKET_2026-08-19.md`; separately confirm payment applicability and release prerequisites.
- **Related observations or decision packets:** NFI-2026-004, NFI-2026-005; `docs/implementation/NELSON_OPERATIONAL_FEEDBACK_RECONCILIATION_2026-08-19.md`
- **Disposition state:** MUNICIPAL CONFIRMATION REQUIRED
- **Decision or implementation reference:** None; no implementation authorized.

### NFI-2026-002 — High-priority documentary evidence visibility

- **Date received:** 2026-08-19
- **Source and review context:** Nelson operational feedback.
- **Frozen Cycle 1 scene or screen:** Staff application detail / supporting documents; no frozen scene is modified.
- **Primary classification:** Workflow/parity correction
- **What Nelson said:** Staff need immediate visibility of Barangay Business Clearance, DTI, SEC, and CDA evidence while processing applications.
- **Current Laravel behavior:** Staff can inspect private generic supporting documents by exact free-text label; no first-class requirement/type catalog, four-document summary, applicability rule, or sufficiency decision exists.
- **Known legacy behavior:** Legacy business documents and selected labels exist; evidence does not prove an exhaustive universal checklist.
- **Applicable TOR / Revenue Code / production evidence:** CAP-024 and TOR upload/checklist scope; entity-specific applicability remains unresolved.
- **Proposed disposition:** Mark the four types as confirmed high-priority visibility, not mandatory universal requirements. Prepare later presentation-only prominence/search work over exact recorded facts.
- **Engineering may act autonomously:** YES, presentation only and only in a new accepted work packet
- **Reason:** Prominence and discoverability can preserve domain meaning; classification, applicability, and sufficiency cannot.
- **Municipal confirmation required:** YES for first-class types, mandatory rules, aliases, expiry/versioning, applicability, and sufficiency.
- **Required authority or evidence:** DTI/SEC/CDA rules by legal/business form; Barangay Business Clearance definition; documentary decision authority.
- **Related observations or decision packets:** NFI-2026-001; reconciliation document above.
- **Disposition state:** EVIDENCE CHECK
- **Decision or implementation reference:** No UAT or UI change in this intake.

### NFI-2026-003 — “ALL REPORTS” operational priority

- **Date received:** 2026-08-19
- **Source and review context:** Nelson operational feedback.
- **Frozen Cycle 1 scene or screen:** Cycle 3 report catalog and management perspective; no frozen evidence is modified.
- **Primary classification:** Workflow/parity correction
- **What Nelson said:** “ALL REPORTS” are important to day-to-day operations.
- **Current Laravel behavior:** Ten working operational/management reports and five navigable authority-pending contracts are grouped in one catalog; official rows/exports remain refused where authority is unresolved.
- **Known legacy behavior:** Broad report families exist; several legacy outputs carry classification, privacy, completeness, or legal-release risk.
- **Applicable TOR / Revenue Code / production evidence:** CAP-085, CAP-093–097, CAP-120, CAP-121 and the reporting-authority boundary.
- **Proposed disposition:** Raise completeness/discoverability/day-to-day access priority without activating official output or broadening authority.
- **Engineering may act autonomously:** YES for later presentation-only catalog/navigation improvements; NO for report semantics, permissions, rows, exports, printing, generation, or certification.
- **Municipal confirmation required:** YES
- **Required authority or evidence:** For each family, who may see, export, print, generate, and certify; official scope/layout/cutoff/classification/signatory acceptance.
- **Related observations or decision packets:** Reconciliation document above.
- **Disposition state:** MUNICIPAL CONFIRMATION REQUIRED
- **Decision or implementation reference:** Existing authority-pending refusals retained.

### NFI-2026-004 — BPLO personnel as operational release actor

- **Date received:** 2026-08-19
- **Source and review context:** Nelson operational feedback.
- **Frozen Cycle 1 scene or screen:** Post-clearance authority-review boundary; no frozen evidence is modified.
- **Primary classification:** Permit/signatory/authority evidence
- **What Nelson said:** After payment and clearances, BPLO personnel release the permit.
- **Current Laravel behavior:** Payment, receipt, and clearance completion can reach `ready_for_authority_review`; `can_release=false`, with no release action or lawful release audit event.
- **Known legacy behavior:** Historical `Released` could occur after first payment and before clearances, contradicting Nelson's described operational ordering; permit issuance was separately clearance-gated.
- **Applicable TOR / Revenue Code / production evidence:** CAP-020, CAP-061, CAP-115; current release refusal boundary.
- **Proposed disposition:** Preserve BPLO personnel as the reported operational actor after payment and clearances. Do not equate actor with legal authority.
- **Engineering may act autonomously:** NO
- **Reason:** Exact prerequisites, role/person eligibility, signature/issuance ordering, and lawful authority are unresolved.
- **Municipal confirmation required:** YES
- **Required authority or evidence:** Authority source, complete release gate, eligible BPLO actors, required preceding act, and audit proof.
- **Related observations or decision packets:** NFI-2026-001, NFI-2026-005.
- **Disposition state:** MUNICIPAL CONFIRMATION REQUIRED
- **Decision or implementation reference:** `can_release` remains false.

### NFI-2026-005 — Mayor as permit signatory

- **Date received:** 2026-08-19
- **Source and review context:** Nelson operational feedback.
- **Frozen Cycle 1 scene or screen:** Permit artifact / Municipality officials; no frozen evidence is modified.
- **Primary classification:** Permit/signatory/authority evidence
- **What Nelson said:** The Municipal Mayor signs the permit.
- **Current Laravel behavior:** Mayor document association/configuration is visible as presentation evidence; it does not establish an authorized signatory, signature, issuance, release, or legal effect.
- **Known legacy behavior:** Mayor/Treasurer platform-setting shape exists; production values and effective authority were not imported or accepted.
- **Applicable TOR / Revenue Code / production evidence:** CAP-063 and CAP-072 signatory/permit-artifact boundaries.
- **Proposed disposition:** Preserve Mayor = permit signatory as Nelson operational evidence, separate from BPLO release actor and from lawful release authority.
- **Engineering may act autonomously:** NO
- **Reason:** Appointment/designation, effective terms, signature method, delegation, ordering, and audit evidence are unresolved.
- **Municipal confirmation required:** YES
- **Required authority or evidence:** Accepted signatory authority and procedure, including whether signature must precede issuance/release.
- **Related observations or decision packets:** NFI-2026-004.
- **Disposition state:** MUNICIPAL CONFIRMATION REQUIRED
- **Decision or implementation reference:** No signing or issuance behavior authorized.

### NFI-2026-006 — Open-ended corrections and discovered gaps

- **Date received:** 2026-08-19
- **Source and review context:** Nelson operational feedback beyond the questionnaire.
- **Frozen Cycle 1 scene or screen:** Cross-cutting elicitation/process principle; no frozen evidence is modified.
- **Primary classification:** Requires further municipal evidence
- **What Nelson said:** Corrections, adjustments, or process gaps discovered beyond the questionnaire should be accommodated so the replacement aligns with actual municipal workflow.
- **Current Laravel behavior:** Evidence-led intake separates safe presentation corrections from semantic, fiscal, Treasury, authority, migration, and Board decisions.
- **Known legacy behavior:** Legacy/production behavior is evidence, not automatic replacement authority.
- **Applicable TOR / Revenue Code / production evidence:** Depends on each observation; no blanket evidence source is created by this request.
- **Proposed disposition:** Adopt the elicitation principle and route every new point through the disposition flow below.
- **Engineering may act autonomously:** YES for evidence intake and meaning-preserving presentation corrections; otherwise NO until the relevant authority accepts the semantic change.
- **Municipal confirmation required:** CONDITIONAL on the affected fact.
- **Required authority or evidence:** The office and source appropriate to each observation; Board acceptance only for a Board-controlled boundary.
- **Related observations or decision packets:** All later NFI observations.
- **Disposition state:** READY FOR ENGINEERING for process documentation only
- **Decision or implementation reference:** This additive intake update.

### NFI-2026-007 — Meaning and applicability of “one-time payment”

- **Date received:** 2026-08-19
- **Source and review context:** Nelson operational feedback; independently decidable Treasury/fiscal point split from NFI-2026-001.
- **Frozen Cycle 1 scene or screen:** Treasury schedule/collection journey; no frozen evidence is modified.
- **Primary classification:** Treasury procedure
- **What Nelson said:** One-time payment follows the one-time assessment and approval.
- **Current Laravel behavior:** The Stakeholder UAT prepares one `single` full-assessment payment schedule and records one full over-the-counter collection. The wider Laravel domain preserves versioned assessments and separately unresolved installment, due-date, surcharge, interest, PIL, deficiency-tax, receipt, and reconciliation boundaries.
- **Known legacy behavior:** Legacy supports annual, semiannual, and quarterly schedule sections. Its section/payment behavior does not prove that the intended current procedure universally permits only one schedule, collection transaction, or payment opportunity.
- **Applicable TOR / Revenue Code / production evidence:** TOR payment and Treasury requirements; Revenue Code payment-timing/installment evidence; CAL-2026-001 fiscal acceptance boundary.
- **Proposed disposition:** Preserve “one-time payment” as Nelson's description of current operations. Determine whether it means one full-assessment obligation, one collection transaction, one permitted payment event, or simply no repeated assessment/approval cycle. Do not change payment policy from the phrase alone.
- **Engineering may act autonomously:** NO
- **Reason:** The interpretation could alter installment, partial-payment, reassessment, amendment, collection, and taxpayer-liability behavior.
- **Municipal confirmation required:** YES
- **Required authority or evidence:** Accepted BPLO/Treasury procedure and fiscal authority defining payment mode, partial payment, exceptions, and the effect of reassessment or material amendment.
- **Related observations or decision packets:** NFI-2026-001; `docs/implementation/APPROVAL_STAGE_DECISION_PACKET_2026-08-19.md`; CAL-2026-001 acceptance path.
- **Disposition state:** MUNICIPAL CONFIRMATION REQUIRED
- **Decision or implementation reference:** None; current UAT behavior remains unchanged.

## Discovered-Gap Disposition Flow

`observation -> evidence classification -> current Laravel comparison -> TOR / ordinance / production evidence -> proposed disposition -> presentation-only autonomous change OR municipal / Board acceptance boundary`

This flow accommodates learning without treating stakeholder feedback, legacy behavior, or an open-ended request as blanket authority to change semantics.
