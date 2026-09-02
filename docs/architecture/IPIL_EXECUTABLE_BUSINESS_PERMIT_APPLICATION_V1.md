# Ipil Executable Business Permit Application V1

## Decision

BPLS presents one evolving **Application Form for Business Permit** while keeping three kinds of truth separate:

1. **Applicant Declaration** — the submitted Page 1 payload is frozen once at lodging in `permit_application_declarations`. Its normalized JSON, schema version, declarant, declaration time, and SHA-256 digest are immutable. Later evaluation, assessment, Treasury, or approval actions cannot update or delete it through the model.
2. **Canonical municipal lifecycle truth** — applications, clearances, immutable assessments and assessment lines, Treasury counter-checks, and Municipal Treasurer decisions remain the authoritative workflow records. Submission does not seed later states.
3. **Executable-document projection** — `BuildExecutablePermitApplicationDocument` reads the declaration and canonical lifecycle records into the recognizable two-page municipal document. It performs no fee calculation and creates no lifecycle fact.

The declaration row is a historical assertion, not a replacement for Citizen, BusinessOwner, Business, application, clearance, assessment, or decision records. The document is a projection, not a giant mutable record.

## Lodging boundary

Draft saves may replace `metadata.applicant_declaration_draft`. The legitimate submit/lodge action changes the canonical application to `submitted` and, in the same transaction, freezes exactly one declaration snapshot. Staff-created and scenario applications use the same freezing action at their existing lodged boundary. A database uniqueness constraint permits one declaration per application; the model rejects updates and deletes.

Line-of-business declarations preserve the selected canonical catalog identifier and freeze the displayed code/name, number of units, capitalization, Essential Gross Sales, Non-Essential Gross Sales, and accepted aggregate. Catalog selection excludes scenario-scoped reference data, preserving reference/scenario isolation.

## Page 2 projection rules

- Document verification is projected from canonical clearances where a supported match exists; unavailable facts are shown as unavailable, not fabricated.
- **Ipil does not use the Application Form Page 2 Assessment area.** It is always rendered as unused and is never populated from the canonical Assessment.
- The authoritative financial artifact is the separate executable **Computation/Assessment Slip**, projected only from current immutable Assessment and AssessmentLine records. Neither document contains a second calculator.
- Concerned-office receipt checking, certification, and Page 2 signatures occur after payment in the accepted Nelson walkthrough. They remain explicitly unavailable because post-payment office completion is outside this slice.
- Treasury counter-check is projected from the canonical counter-check record.
- Municipal Treasurer approval is exact only when the canonical decision action is `approve`.
- Until a canonical permit exists, the projection explicitly says **Permit not yet issued**.
- Quarterly formulas, permit issuance/release, Mayor-signature authority, collection/OR certification, and unresolved Page 2 signature semantics are not implemented.

## Browser Lifecycle Laboratory contract

The human application milestone opens the real executable Page 1 form. Saving preserves a draft; submitting freezes the declaration. Reopening the same document preserves Page 1 and exposes the unused Page 2 Assessment boundary; the separate slip carries Assessment and Treasurer truth. The persisted chronology remains one Citizen → BusinessOwner → Business with a 2025 New application followed by its 2026 Renewal.

## AES precedent applied

The AES voter ballot (`election/role-demo/voter/ballot`) demonstrates how to retain an official paper document's institutional header, vocabulary, grouping, borders, and selection grammar while translating dense desktop columns into labeled mobile blocks. BPLS applies those techniques through a document-shaped surface, explicit section nouns, strong row/column relationships, responsive stacking, validation, review, and a server-backed finalization boundary. Election styling, ballot timing marks, and election-specific interactions were not copied.

## Scope boundary

This wave does not implement registry import, account claiming, juridical representation, fee maintenance, quarterly-payment execution, QR settlement, Collection/OR certification, Treasury-added LOB, permit issuance/release, Mayor-signature semantics, or cloud deployment.
