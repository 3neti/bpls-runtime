# Nelson Operational Workflow Artifact Reconciliation

> 2026-09-02 superseding disposition for routing and Payment Orders: the accepted 2026-09-01 Anaïs/Nelson Zoom walkthrough now establishes BPLO situational routing authority and amount-bearing concerned-office Paperless Payment Orders upstream of the Assessment Officer's consolidated Computation/Assessment Slip. The bounded implementation is documented in `PRE_ASSESSMENT_PAYMENT_ORDER_DECISION_PACKET_2026-08-20.md` and `ACTUAL_ASSESSMENT_PARITY.md`. Older “unmodeled/held” statements below remain the historical disposition of `OPERATIONAL-NELSON-001` before that new evidence; they no longer describe the implemented V1 boundary. Post-payment clearance/signature, portal, issuance, and release questions remain unresolved.

Status: **SOURCE VERIFIED — BOUNDED ASSESSMENT APPROVAL REMAINS RESOLVED; FISCAL AND AUTHORITY DECISIONS BLOCK UAT FREEZE**

Date reconciled: 2026-08-20

Source: `OPERATIONAL-NELSON-001`; original and verbatim transcription are registered under `docs/sources/operational/`.

This record is additive. It preserves rather than replaces Nelson's earlier direct answers in NFI-2026-008: Municipal Treasurer approval of the Assessment Officer-prepared assessment/amount before payment; return for correction; fresh approval after recomputation; BPLO personnel as operational release actor; and the Municipal Mayor as permit signatory.

## Source-specific layering

The process table names `Assessment Officer / Approving Officer`. Nelson's earlier direct answer identifies the generic Approving Officer as the Municipal Treasurer and explicitly distinguishes that person from the Assessment Officer. These sources are consistent, not contradictory. The canonical bounded model remains:

```text
Assessment Officer prepares/computes exact assessment snapshot
    -> Municipal Treasurer approves that assessment/amount or returns it for correction
    -> matching approval permits payment scheduling
```

The table does not mention return-for-correction, but omission does not contradict the earlier direct answer. The immutable return and fresh-approval behavior remains accepted.

## Semantic reconciliation matrix

| Source fact | Current Laravel / prior evidence | Disposition | Safe implementation authority |
| --- | --- | --- | --- |
| Barangay Clearance and Proof of Registration are submitted at application. | Laravel stores generic private supporting evidence. Nelson previously identified Barangay Business Clearance, DTI, SEC, and CDA as high-priority evidence. Legacy source contains an ownership-specific document shape, but its active validation makes documents optional. | Confirms document prominence, not universal sufficiency. Whether Proof of Registration is a DTI/SEC/CDA umbrella and whether `Barangay Clearance` equals `Barangay Business Clearance` remain unresolved. | Presentation of exact recorded labels only. No mandatory/applicability rule. |
| Applicant secures Paperless Payment Orders from MPDO, Engineering Office, and Municipal Assessor’s Office; all required orders complete before automatic forwarding to Step 2. | TOR and studied legacy source expose departmental fees and workflow configuration but no canonical payment-order object, lifecycle, or completion rule. Laravel has no pre-assessment payment-order fact. | Newly exposed missing domain boundary. Do not treat the orders as clearances, final assessment lines, documentary endorsements, or approvals without evidence. | Evidence and decision packet only. |
| ITR, CTC, and Sworn Statement are submitted during Step 2. | Laravel can preserve these as generic documents but has no accepted type catalog, applicability, validity, version, or sufficiency rule. | Confirms named Step 2 evidence and its placement in the described process. Applicability and decision authority remain unresolved. | Presentation of exact evidence only. |
| Assessment Officer / Approving Officer complete and approve assessment before payment. | NFI-2026-008 directly identifies the Municipal Treasurer, exact assessment/amount, return outcome, and payment consequence. CAP-018 implements distinct facts and actors. | Resolved and consistent. No source conflict. | Existing bounded implementation remains canonical. |
| All assessed business taxes, regulatory fees, and other applicable charges are paid in one transaction. | Preview prepares one `single` full-assessment schedule and demonstrates one full OTC collection. Operational domain still supports partial collections. Legacy supports annual, semiannual, and quarterly sections. Revenue Code Section 2E.03 permits payment once or in quarterly installments for covered taxes; contractor provisions also contain installment/recomputation rules. | Strong current-practice evidence but a genuine fiscal contradiction if interpreted universally. It cannot repeal or narrow ordinance rights or accepted exceptions. | No payment-policy change. Fiscal/Treasury acceptance required. |
| MPDC, Engineering, MENRO, Health, and FSIC clearances follow payment and are approved by respective offices. | Laravel creates clearance work only when the schedule becomes fully paid, so broad ordering agrees. Its current rescue checklist is `bplo_review`, `treasury_payment`, and `release_authority`, not the named office clearances. Legacy has Sanitary, Fire Safety, MPDC, MENRO/Environmental, and Engineering configurations. | Post-full-payment ordering is corroborated. Named set and responsible offices are stronger evidence, but applicability, aliases, exact approving positions, and universal versus conditional rules remain unresolved. | Do not replace the catalog or make the set universal until accepted. |
| After required clearances, the application is approved and pushed to the Business Permit Portal for release. | Laravel stops at `ready_for_authority_review`, with no accepted post-clearance approval or portal-push fact. | Newly exposed second approval boundary, distinct from Treasurer assessment approval. Actor, object, outcome, audit, and consequence are unknown. | No autonomous implementation. |
| Applicant retrieves approved permit from BPLO / Releasing Officer; permit is released/issued. | Nelson's direct evidence already identifies BPLO personnel as operational release actor and Mayor as signatory. Current Laravel separates generated artifact, authority review, issuance, release, and legal effect and refuses release. | BPLO operational actor is corroborated. `released/issued` does not safely collapse issuance and release or resolve Mayor signature sequence and authority. | No issuance/release activation. |
| Business Permit Portal is the destination before release. | Surface inventory contains staff BPLS, citizen portal, reporting app, and public verification. No separate accepted destination matches this label. | Surface identity unresolved. It may be an existing portal, external integration, or release queue. | Do not create a new application or integration. |
| `automatically forwarded` and `pushed` describe transitions. | Laravel uses explicit domain actions and manual operator flows at several boundaries. | The phrases may express required automation or process prose. Exact trigger, failure, retry, correction, and audit semantics are unknown. | No automatic transition change. |
| Payment row has a blank Step cell between STEP 2 and STEP 3. | No authoritative normalization exists. | Preserve exact source form. The likely sequence may be described, but no Step number is assigned. | Documentation only. |

## UAT impact

The deployed UAT remains useful historical evidence for the bounded Treasurer approval behavior, but it is not semantically current enough for independent product critique.

Definitely incomplete or misleading:

- submission reaches assessment without first-class Paperless Payment Order prerequisites;
- Step 1 and Step 2 named documentary evidence is not represented as an applicability-aware process;
- the clearance checklist uses three rescue abstractions rather than the source's office clearance set;
- `ready_for_authority_review` does not represent the newly exposed post-clearance approval and portal-push question;
- the generated-artifact/release boundary omits the unresolved relationship among post-clearance approval, Mayor signature, issuance, portal availability, and BPLO release;
- the synthetic one-transaction payment demonstrates one scenario but cannot be presented as universal fiscal policy.

Still correct and retained:

- Assessment Officer preparation and Municipal Treasurer exact-snapshot approval are distinct;
- returned assessment remains payment-blocked and a corrected snapshot requires fresh approval;
- payment scheduling requires matching Treasurer approval;
- clearance work follows full payment in the current demonstrated flow;
- collection and receipt remain separate facts;
- generated artifact, issuance, release, and legal effect remain separated and fail closed.

## Governance disposition

No new fiscal, documentary-sufficiency, clearance-applicability, automatic-forwarding, portal, post-clearance approval, signature, issuance, release, or legal-effect behavior is authorized. Warp Product/UI Critic review remains paused until the decision packets below are answered, safe corrections are integrated, the cloud UAT is redeployed, and a new semantic evidence run is frozen.

- `PRE_ASSESSMENT_PAYMENT_ORDER_DECISION_PACKET_2026-08-20.md`
- `DOCUMENTARY_AND_CLEARANCE_APPLICABILITY_DECISION_PACKET_2026-08-20.md`
- `ONE_TIME_PAYMENT_FISCAL_RECONCILIATION_2026-08-20.md`
- `POST_CLEARANCE_APPROVAL_RELEASE_DECISION_PACKET_2026-08-20.md`
