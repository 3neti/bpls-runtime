# Nelson Operational Feedback Reconciliation

Status: **EVIDENCE PRESERVED — APPROVAL STAGE RESOLVED FOR IMPLEMENTATION; OTHER AUTHORITY QUESTIONS PENDING**

Date received: 2026-08-19

Source: Nelson operational feedback supplied for reconciliation against the canonical Laravel domain, Discovery/legacy parity, and the frozen Stakeholder Preview / UAT.

This is an additive operational-evidence record. It does not overwrite Nelson Cycle 1 evidence, Stakeholder Preview evidence, migration evidence, the 407-application campaign, the TOR, the Revenue Code, production evidence, or any earlier parity finding. It authorizes no application status, route, action, queue, report row, export, permit issuance, release, validity, or legal effect.

## Reconciliation Matrix

The category labels are intentionally cumulative.

| Nelson evidence / statement | Reconciliation categories | Current Laravel behavior | Prior legacy / parity evidence | Proposed disposition | Engineering autonomy | Authority or confirmation still needed |
| --- | --- | --- | --- | --- | --- | --- |
| 1. Operational flow is application -> one-time assessment and approval -> one-time payment -> clearances -> permit release. | **CONFIRMS CURRENT MODEL** for application, assessment, one full-assessment schedule, payment, clearances, and the broad ordering; **REFINES PRESENTATION/PARITY**; **EXPOSES MISSING DOMAIN BEHAVIOR** for approval; **REQUIRES ADDITIONAL MUNICIPAL AUTHORITY/EVIDENCE BEFORE IMPLEMENTATION**. | Submission enters `assessment`; a computed assessment can immediately produce a `single` full-assessment payment schedule and move the application to `pending_payment`. There is no operational approval action, approval fact, approval queue, rejection/return path, or renewed-approval behavior. Paid/receipted evidence creates clearance work; completed clearances can produce `ready_for_authority_review`, while `can_release=false`. | TOR and Discovery describe Assessment -> Evaluation -> Approval -> Payment. Legacy source preserves an `Approval` status and projected `approvedAt`/`approvedBy` evidence. Legacy `Released` could be asserted after first payment and before clearance completion; actual permit issuance was separately clearance-gated. CAP-018 was therefore a known parity capability, but current operational Laravel did not implement it. | Preserve Nelson's sequence as operational evidence. Treat approval as a genuine domain decision boundary and use the dedicated decision packet before implementation. Treat “one-time” as Nelson's description of practice, not universal fiscal authority for every application type, amendment, or reassessment. | **NO** for approval, payment-policy, clearance gating, or release semantics. Presentation work may be scoped later only after wording is accepted and cannot conceal the missing approval fact. | Answers in `APPROVAL_STAGE_DECISION_PACKET_2026-08-19.md`; accepted payment-mode/applicability evidence; exact clearance prerequisites; release/issuance authority. |
| 2. Staff need immediate visibility of Barangay Business Clearance, DTI, SEC, and CDA evidence while processing applications. | **CONFIRMS CURRENT MODEL** for private supporting-evidence visibility; **REFINES PRESENTATION/PARITY**; **REQUIRES ADDITIONAL MUNICIPAL AUTHORITY/EVIDENCE BEFORE IMPLEMENTATION** for applicability and sufficiency. | Staff application detail loads and displays private supporting documents with free-text label, file, remarks, uploader, and timestamp. The canonical model classifies them generically as `supporting_evidence`; there is no first-class requirement/type catalog, applicability engine, completeness decision, or four-document summary. Business registration number and ownership form are separate structured facts, not proof of DTI/SEC/CDA evidence. | CAP-024 confirms legacy business documents and TOR upload/checklist expectations, but not a complete statutory checklist. Migration evidence has observed labels such as Barangay Clearance and DTI Certificate without making them universal requirements. | Mark all four as **confirmed high-priority staff evidence visibility**, not an exhaustive checklist. Prepare a presentation-only packet to make exact recorded evidence easier to find. Do not infer a type from filenames, treat a registration number as the document, or mark documentary sufficiency. | **YES, LATER** for ordering, prominence, search, and neutral grouping of already-recorded exact facts, provided the change remains presentation-only and the frozen UAT is not patched in place. **NO** for new requirement types, mandatory rules, sufficiency, approval, expiry, or applicability. | Which legal/business forms require DTI, SEC, CDA, or combinations; the exact meaning and issuer of “Barangay Business Clearance”; accepted aliases; version/expiry rules; documentary sufficiency and decision authority. |
| 3. All reports are important to day-to-day operations. | **CONFIRMS CURRENT MODEL**; **REFINES PRESENTATION/PARITY**; **REQUIRES ADDITIONAL MUNICIPAL AUTHORITY/EVIDENCE BEFORE IMPLEMENTATION** for official outputs and permissions. | Cycle 3 exposes one catalog with ten working operational/management reports and five navigable authority-pending report contracts; Billing Group Abstract is reached from its billing group. Working exports are permission-gated by the current broad `reports.view` boundary. Authority-pending families refuse official rows/exports. | TOR and legacy evidence show broad reporting. CAP-085 and CAP-093–096 preserve authority-heavy gaps; CAP-120/121 and the other working reports project persisted evidence without recalculating liability. | Preserve Nelson's exact answer, **“ALL REPORTS,”** as strong priority evidence for completeness, discoverability, and day-to-day access. Retain the separation between useful operational projections and authority-bearing registers/returns. | **YES, LATER** for catalog discoverability, navigation, labels, and presentation of existing safe report contracts. **NO** for new official rows, exports, generation, certification, or broadened permissions. | For every family: who may see, export, print, generate, and certify; accepted layout/cutoff/grain; fiscal/classification/signatory authority. All Abstract remains blocked by complete Treasury scope, revenue-account/fund mappings, reversal/reconciliation, and official-format authority. CMCI, PLDS, BSP, and ANNEX C remain blocked by permit issuance/release plus their respective official numbering, dates, classifications, LGU metadata, scope, and certification authority. |
| 4. After payment and clearances, BPLO personnel release the permit; the Municipal Mayor signs it. | **CONFIRMS CURRENT MODEL** for payment/clearance evidence and the deliberately separated signatory/release concepts; **REFINES PRESENTATION/PARITY**; **EXPOSES MISSING DOMAIN BEHAVIOR**; **REQUIRES ADDITIONAL MUNICIPAL AUTHORITY/EVIDENCE BEFORE IMPLEMENTATION**. | Laravel records paid schedule, receipt, and completed clearances, then stops at `ready_for_authority_review`; `can_issue`, `can_release`, and legal effect remain false. Mayor and BPLO document associations/configuration are presentation evidence only. No lawful signature, issuance, or release event exists. | Legacy `Released` could occur after first payment, before clearances, while the issue-permit control was clearance-gated. CAP-020, CAP-061, CAP-072, and CAP-115 explicitly refuse to equate that status or a generated artifact with lawful release. | Record separately: **Mayor = permit signatory (Nelson operational evidence)** and **BPLO personnel = operational release actor after payment and clearances (Nelson operational evidence)**. Nelson clarifies likely intended operations and contradicts the legacy pre-clearance `Released` label, but does not by itself establish legal authority or the complete gate. | **NO** for `can_release`, issuance, release, signature, validity, legal effect, role grants, or audit-event implementation. | What constitutes authority to release; exact prerequisites; whether signature precedes release; whether approval/recommendation/issuance is also required; which BPLO roles/persons may act; Mayor signature form/delegation; and the evidence/audit event that establishes lawful issuance and release. |
| 5. Corrections, adjustments, and process gaps discovered beyond the questionnaire should be accommodated so the replacement matches actual municipal workflow. | **CONFIRMS CURRENT MODEL** for evidence-led reconciliation; **REFINES PRESENTATION/PARITY**; **REQUIRES ADDITIONAL MUNICIPAL AUTHORITY/EVIDENCE BEFORE IMPLEMENTATION** whenever meaning or authority changes. | The intake already preserves observations independently, compares Laravel/legacy/TOR/Revenue Code/production evidence, and routes safe presentation corrections separately from municipal or Board decisions. | Discovery treats legacy and production behavior as evidence rather than automatic authority; frozen records are additive and immutable. | Adopt this as an elicitation/process principle, not blanket semantic authority. Use: observation -> evidence classification -> current Laravel comparison -> TOR/ordinance/production evidence -> proposed disposition -> presentation-only autonomous change or municipal/Board acceptance boundary. | **YES** for intake, evidence organization, typo/layout/discoverability work that preserves meaning. **NO** whenever a correction changes workflow, status, liability, documentary sufficiency, payment, clearance, report authority, signing, issuance, release, identity, migration, or legal effect. | The office/authority appropriate to the affected fact; Board acceptance only when the issue crosses a Board-controlled boundary. |

## Approval Is a Genuine Domain Boundary

The enum contains `approval`, and migration projection preserves historical `approvedAt`/`approvedBy`, but current operational actions move from a computed assessment directly to preparation of a pending payment schedule. That is historical/parity shape, not an implemented or accepted approval act.

Nelson's follow-up answers now resolve the bounded operational act: the Assessment Officer prepares/computes the assessment, the Municipal Treasurer approves the exact assessment/amount or returns it for correction, and approval permits payment to proceed. His answers are preserved verbatim in NFI-2026-008 and the decision packet.

The accepted engineering representation is one assessment workflow with two independently auditable facts. A Treasurer decision is immutable and fingerprint-bound to one persisted assessment snapshot. Payment scheduling fails closed without matching approval. Return applies to the assessment, not the application; an existing recomputation creates a new sequence that requires a fresh decision. This recurrence rule is an audit-safety inference and does not define a broader reassessment, rejection, appeal, or documentary-deficiency procedure.

## Permit Signing and Release Are Separate Facts

- **Mayor = permit signatory:** Nelson operational evidence; not yet accepted signatory authority or signature procedure.
- **BPLO personnel = operational release actor:** Nelson operational evidence after payment and clearances; not yet lawful release authority or a role grant.
- **Remaining boundary:** authority source, prerequisites, ordering of signature/issuance/release, eligible actor, and audit proof remain unanswered.

Nelson's sequence is stronger evidence than the legacy label for intended current operations: it places release after clearances. The historical system's pre-clearance `Released` assertion remains preserved as historical evidence and must not be normalized, activated, or silently reinterpreted.

## Documentary Evidence Position

Barangay Business Clearance, DTI, SEC, and CDA are now confirmed high-priority evidence for staff visibility. They are not an exhaustive universal checklist. The current generic document model can display an exact recorded label and artifact, but it cannot reliably determine regulatory type, entity applicability, currency, completeness, sufficiency, or approval.

A later autonomous presentation packet may improve prominence and discovery of exact recorded facts. First-class documentary types, requirement rules, or sufficiency decisions require municipal/reference evidence and a separate accepted domain change.

## Reports Position

“ALL REPORTS” raises product priority; it does not answer authority. Keep the Board operating questions open for every report family:

1. Who may see it?
2. Who may export it?
3. Who may print it?
4. Who may generate it?
5. Who may certify it as official?

The current catalog remains correctly split between working evidence projections and authority-pending official outputs. Importance does not authorize official rows, export, generation, certification, new classifications, or a broader permission set.

### Authority-pending report families

| Family | Unresolved authority/evidence that keeps official output blocked |
| --- | --- |
| All Abstract of Collection | Complete permit and non-permit Treasury domains; accepted billing groups; revenue-account and fund mappings; receipt void/reversal and reconciliation; production configuration; official format |
| Billing Group Abstract | Accepted group collection semantics; completed/voided status authority; report date basis; receipt/payor mapping; accounting classification; final output acceptance |
| CMCI LDCS Annex B | Permit issuance/release; permit number and issue date; accepted signatory; CMCI classification; official LGU metadata; production reconciliation |
| PLDS | Permit issuance/release and issue date; category/subcategory mapping; UOM Assets meaning; missing fields; production configuration and municipal acceptance |
| BSP Non-Bank Entities | Permit issuance/release and number/date; BSP registration identity; regulated non-bank classification; adverse permit-status meaning; production reconciliation |
| ANNEX C — DNFBP | Permit issuance/release and number/date; accepted DNFBP classification; reporting-semester scope; production reconciliation and municipal acceptance |

## UAT Impact Assessment — New Evidence Cycle Completed

The prior frozen/cloud UAT evidence remains unchanged. The accepted approval decision authorizes a new deterministic evidence cycle and non-production UAT update after domain verification.

- The updated walkthrough must show Assessment Officer preparation, Municipal Treasurer approval of the exact amount, then payment availability. Synthetic preview personas remain simulated permission perspectives rather than final municipal role mapping.
- `Ready for Authority Review` is still a truthful refusal boundary for post-payment, post-clearance release/issuance authority. It could nevertheless be misunderstood as the earlier application/assessment approval Nelson described. Any future UAT wording must distinguish **pre-payment approval** from **post-clearance issuance/release authority review**.
- Supporting documents are visible, but the deterministic UAT contains one generic **Business registration evidence** artifact rather than separately identified Barangay Business Clearance, DTI, SEC, and CDA evidence. The four high-priority types are not first-class or immediately summarized.
- The report catalog is discoverable and grouped, but Nelson's answer supports a later presentation review for completeness and day-to-day access.

The approval-stage packet completed domain, presentation, deterministic UAT, deployment, and live browser verification at commit `045d33799269a7166d92f41181e060393088e6a1`. Cloud deployment `depl-a289dc3d-85c6-4840-a7fa-e6df5da293af` and synthetic run `stakeholder-preview-approval-cloud-20260819-001` passed. Desktop and 390px views verified the distinct actors, exact-snapshot approval, payment/receipt/clearance continuation, and unchanged release refusal boundary without application console errors or horizontal overflow. Documentary prominence and report discoverability remain separate packets. Prior frozen evidence was not amended.

## Protected Baselines

No change from this reconciliation may alter or reinterpret:

- Nelson Cycle 1 or Stakeholder Preview frozen evidence;
- prior frozen cloud UAT evidence (the environment may advance only through a new verified evidence cycle);
- migration evidence or mappings;
- the 407-member production campaign;
- fiscal, numbering, receipt, signatory, issuance, release, validity, legal-effect, or official-report authority.
