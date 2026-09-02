# Pre-Assessment Paperless Payment Order Decision Packet

Status: **PARTIALLY RESOLVED; BOUNDED V1 IMPLEMENTED 2026-09-02**

Sources: `OPERATIONAL-NELSON-001`; accepted 2026-09-01 Anaïs/Nelson Zoom walkthrough; actual Ipil Computation/Assessment Slip.

## Confirmed source fact

During Step 1, the applicant secures Paperless Payment Orders from the MPDO, Engineering Office, and Municipal Assessor’s Office, plus any `OTHER OFFICE`. Completion of all required payment orders precedes automatic forwarding to Step 2.

The later Zoom directly resolves the smallest safe semantic boundary: BPLO selects the situational office route; concerned offices determine amounts and issue “paperless payment orders”; the Assessment Officer under Treasury consolidates those inputs into the separate Computation/Assessment Slip; the Municipal Treasurer approves or returns it; payment follows; only then does the applicant return to concerned offices. A Payment Order is therefore not a post-payment Page 2 signature.

## Implemented V1 contract

- `BploRoutingDetermination` records the authorized BPLO actor/time, situational context, frozen application/LOB facts, selected offices, reasons, and required work after lodging. Scenario fixtures exercise this action but are not production routing rules.
- `PaperlessPaymentOrder` is an amount-bearing, application- and BPLO-work-scoped office determination. Its issuer, source Evaluation revision, office/LOB context, lines, amount, and issuance time are durable. Issued financial fields and lines are immutable.
- A corrected office determination creates a new immutable Evaluation revision and Payment Order; the prior order is marked superseded. If an Assessment exists, it must first be returned for correction. Cancellation/expiry/completion policy is not invented.
- Assessment admits only eligible current issued Payment Order lines plus governed canonical FeeRule projections. Each Payment Order line can enter a given Assessment only once. The separate slip reads Assessment truth.

## Decisions still required

1. What official numbering, if any, applies to Payment Orders and slips?
2. What exact authority and vocabulary governs cancellation, expiry, withdrawal, and completion beyond issuance/supersession?
3. Which office routes apply under which municipal circumstances, and may the system propose them? V1 records BPLO judgment and does not commission fixture-derived rules.
4. Is automatic forwarding mandatory, and what retry, failure, notification, audit, and override rules apply?
5. What are the authoritative office names/codes, including MPDO/MPDC and `OTHER OFFICE`?
6. What supporting documents, legal basis, validity periods, or additional approvals belong on an official Payment Order?

## Explicit non-decisions

This packet still does not authorize a second fee calculator, department billing subsystem, payment collection, documentary sufficiency decision, post-payment clearance/signature completion, automatic workflow transition, quarterly allocation, or permit issuance/release.
