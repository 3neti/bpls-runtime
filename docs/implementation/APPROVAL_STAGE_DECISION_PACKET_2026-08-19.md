# Approval Stage Municipal Decision Packet

Status: **MUNICIPAL DECISION REQUIRED — DO NOT IMPLEMENT**

Raised by: Nelson operational feedback received 2026-08-19

Related evidence: `docs/implementation/NELSON_OPERATIONAL_FEEDBACK_RECONCILIATION_2026-08-19.md`; `NFI-2026-001`

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

## Engineering Hold

Until an accepted decision record exists, do not create approval statuses beyond preserved parity shape, actions, queues, routes, permissions, notifications, payment gates, rejection paths, or UAT walkthrough steps. The existing direct assessment-to-payment-schedule behavior remains visible as a known semantic gap; it is not silently declared correct.
