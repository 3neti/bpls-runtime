# Post-Clearance Approval, Portal, Issuance And Release Decision Packet

Status: **MUNICIPAL / AUTHORITY DECISION REQUIRED — RELEASE REMAINS FAIL-CLOSED**

Sources: `OPERATIONAL-NELSON-001`; NFI-2026-004; NFI-2026-005; CAP-020, CAP-061, CAP-063, CAP-072, and CAP-115.

## Confirmed operational evidence

- After required clearances are approved by their respective offices, the table says the application is `approved and pushed to the Business Permit Portal for release`.
- The applicant then proceeds to BPLO; the responsible actor is `BPLO / Releasing Officer`.
- The table says the permit is `released/issued` to the applicant.
- Nelson's direct evidence separately identifies BPLO personnel as the operational release actor and the Municipal Mayor as permit signatory.

This exposes an approval after clearances that is distinct from Municipal Treasurer approval of the assessment/amount.

## Decisions required

1. What exactly is approved after clearances: application completion, recommendation, permit issuance, portal availability, release readiness, or another municipal act?
2. Who or what records that approval: BPLO officer, BPLO head, Mayor, automated system rule, or another authority?
3. Is the approval discretionary, an automatic consequence of completed applicable clearances, or a human attestation?
4. What evidence, timestamp, actor/office, reason, document set, clearance versions, signature, and retention are required?
5. What is the Business Permit Portal: the current citizen portal, legacy staff portal, an external system, or an internal release queue?
6. What does `pushed` mean, and what are the integration, acknowledgement, retry, failure, duplicate, correction, and audit semantics?
7. Who has legal issuance authority, and is issuance a distinct event before release?
8. Must the Municipal Mayor sign before issuance, portal availability, printing, or physical/electronic release? What signature method, delegation, effective term, and audit evidence apply?
9. Does `BPLO / Releasing Officer` identify a job position, permission-bearing role, designated person, or office queue? Who is eligible?
10. Which event changes application/permit status, allocates official permit identity, establishes issuance time, records release time and recipient, and creates legal effect?
11. May an approved permit be retrieved electronically, physically, or both, and what acknowledgement proves release?
12. Does source wording `released/issued` intentionally combine the acts, or is it shorthand for two separate facts?

## Refusal boundary

`ready_for_authority_review` may remain an internal safety abstraction while these questions are pending, but it must not be presented as the municipality's accepted post-clearance approval. Generated artifact identity, public verification, Mayor configuration, clearance completion, and BPLO actor evidence do not authorize issuance, release, validity, or legal effect.
