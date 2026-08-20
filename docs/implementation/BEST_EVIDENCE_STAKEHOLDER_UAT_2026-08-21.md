# Best-Evidence Stakeholder UAT Decision Record

Status: **IMPLEMENTED LOCALLY; CLOUD FREEZE PENDING**

Date: 2026-08-21

## Operating decision

The Stakeholder UAT is the Municipality of Ipil BPLS team's best current executable hypothesis. It remains non-production, synthetic, reversible, and intended to elicit correction. Implementation in this profile does not accept municipal policy, fiscal authority, legal effect, identity, official numbering, Mayor credential custody, permit issuance authority, permit release authority, historical migration truth, or taxpayer liability.

The profile is mechanically identified as `stakeholder_preview_weekend_v1`. Every newly inferred semantic is recorded as `provisional_uat`.

## Accepted semantics retained

- A citizen or staff-originated application uses the real permit-application domain and actions.
- The Assessment Officer prepares the persisted consolidated assessment.
- The Municipal Treasurer approves or returns the exact immutable assessment snapshot.
- Payment scheduling remains blocked until that exact snapshot is approved.
- Recomputed assessment snapshots require a fresh Treasurer decision.
- Existing partial/installment boundaries remain intact; the golden scenario merely chooses a full consolidated payment.
- Reports continue to project persisted evidence and authority-bearing families remain authority-bound.
- The production permit issuance/release action remains fail-closed.

## `provisional_uat` semantics

1. The golden New Permit scenario selects Engineering, MPDO/MPDC, Assessor, Health, and MENRO as applicable offices. This is scenario applicability, not universal policy.
2. Each office records and approves only its own staff/manual charge. The scenario supplies replaceable sample amounts; no fee formula or taxpayer liability is inferred.
3. Approved office contributions become immutable assessment lines through the existing assessment snapshot path. Treasury does not re-encode them.
4. The Business Permit Portal is presented as office-specific queues within the same BPLS.
5. After confirmed full payment and completed scenario clearances, a Mayor Office preview perspective records Go/No-Go.
6. Go assigns a deterministic `UAT-IPIL-{year}-{application-id}` preview number and the literal non-secret `SYNTHETIC-UAT-MAYOR-SIGNATURE` presentation reference.
7. A separate BPLO Releasing Officer preview perspective may then mark the sample lifecycle `released_in_preview`.
8. The preview completion record does not change `permit_applications.status`, `application_number`, real permit authority, the public release-verification boundary, or any production behavior.

## Golden walkthrough

Application → supporting evidence → five concerned-office workspaces and charges → Assessment Officer consolidation → Municipal Treasurer exact-snapshot approval → full consolidated payable/payment → receipt/payment confirmation → clearances → Mayor Office preview Go/No-Go → sample e-signature and preview number → BPLO Releasing Officer preview release → public artifact verification with a separately labeled preview lifecycle result.

## Synthetic documents in the golden scenario

- Proof of Registration · DTI (sole-proprietorship scenario)
- Barangay Business Clearance
- Income Tax Return (ITR)
- Community Tax Certificate (CTC)
- Sworn Statement

These are explicit sample-scenario requirements. SEC and CDA remain visible stakeholder questions for their corresponding entity types; the UAT does not universalize documentary applicability or sufficiency.

## Municipal validation questions

1. Are Paperless Payment Orders correctly represented as office-entered charge contributions, and what is their official object/name/status?
2. Which offices determine applicability, and does each office both enter and approve its own amount?
3. Are any office computations legitimately system-suggested; which remain manual?
4. Does the Treasurer counter-check/finalize the same exact assessment approval fact already modeled?
5. When are full, quarterly, and partial payments legally and operationally available?
6. What exact post-payment and post-clearance act authorizes final permit processing?
7. Does the Mayor/Mayor Office decide each permit, or is the e-signature applied under another custody/delegation procedure?
8. What official permit-number format, allocator, collision control, and acceptance event apply?
9. Are issuance, portal availability, printing, and BPLO release separate events, and who records each?
10. Which parts differ for New and Renewal permits?
11. Which roles may view, print, export, generate, or certify each report family?

## Board-controlled boundaries

No production environment or data was changed. The 407-member production migration campaign remains unauthorized and unexecuted. No real Mayor credential/key, official permit number, fiscal policy, taxpayer liability, identity acceptance, permit authority, or legal effect is represented.

## Freeze record

To be completed only after canonical publication and actual HTTPS UAT verification:

- canonical SHA: pending
- Laravel Cloud deployment: pending
- UAT URL: `https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud/`
- immutable evidence run: pending
- desktop/mobile screenshots and check counts: pending
