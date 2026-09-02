# Stakeholder Preview Ready Package

Status: **HISTORICAL APPROVAL-STAGE EVIDENCE PRESERVED — SEMANTIC FREEZE AND WARP REVIEW PAUSED**

As of: 2026-08-19

This package hands the existing BPLS application to the Board for internal operation. It does not authorize an external municipal presentation, production deployment, production migration, or any unresolved municipal, fiscal, numbering, issuance, release, or legal-effect decision.

`OPERATIONAL-NELSON-001`, registered on 2026-08-20, exposes workflow semantics not represented in this deployment: Paperless Payment Orders before assessment, additional step-specific documents, named post-payment office clearances, a second post-clearance approval, and a Business Permit Portal push. Its one-transaction statement also requires fiscal reconciliation with Revenue Code installment provisions. The URL remains available as historical approval-stage evidence, but this package no longer declares it semantically frozen or ready for independent product review.

## Open The Preview

- Verified environment: sole non-production Laravel Cloud `uat` environment
- Verified URL: `https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud`
- Canonical application commit: `045d33799269a7166d92f41181e060393088e6a1`
- Cloud deployment: `depl-a289dc3d-85c6-4840-a7fa-e6df5da293af` — succeeded 2026-08-19
- Deterministic preparation: `stakeholder-preview-approval-cloud-20260819-001` — domain passed; no external calls or irreversible actions
- Data classification: synthetic UAT data only

Run the deterministic preparation command with `STAKEHOLDER_PREVIEW_PASSWORD` supplied in the operator's runtime environment. The password must be at least 16 characters and must be delivered through an approved secret channel; it is not stored in Git, the manifest, screenshots, or this package.

For the secured local Herd site, run `composer preview:ready` after pulling application changes or rebuilding the local database. The command clears stale configuration, applies pending migrations non-destructively, synchronizes the canonical synthetic preview personas, and rebuilds the stable `local-browser-testing` specimen at `https://bpls-runtime.test`. It fails closed when the preview safety configuration or runtime password is absent.

Live browser verification covered Treasury/BPLO capability separation, the exact approved assessment snapshot, paid payment schedule, OTC collection and issued receipt, completed clearances, citizen timeline, and the post-clearance release refusal boundary. Desktop and 390px mobile views had zero application console errors and no horizontal overflow.

## Preview Accounts

| View | Username | Effective access | Credential delivery |
| --- | --- | --- | --- |
| Citizen | `stakeholder.preview.citizen@example.test` | Existing citizen permissions | Runtime secret channel |
| BPLO operator | `stakeholder.preview.bplo@example.test` | Existing permit intake, Assessment Officer preparation, payment-schedule preparation, and clearance permissions selected for preview | Runtime secret channel |
| Treasury operator | `stakeholder.preview.treasury@example.test` | Narrow assessment-approval plus existing schedule, collection, receipt, and report permissions selected for preview | Runtime secret channel |
| Municipal management | `stakeholder.preview.management@example.test` | Existing reporting, directory, role, municipality, fee-rule, and billing-group view permissions selected for preview | Runtime secret channel |

The `preview_*` role labels are synthetic permission bundles for this local composition. They are not proposed municipal job classifications and do not change the application's authorization model.

## Recommended Walkthrough

1. Sign in as Citizen. Open **My Permit Applications**, inspect the submitted synthetic application, current processing state, payment evidence, and the explicit online-payment and permit-authority boundaries.
2. Sign in as the BPLO operator. Open **All Applications** and **Assessment Work**. Inspect the same application, the Assessment Officer-prepared snapshot, supporting evidence, completed clearances, authority-review readiness, permit artifact, and unavailable release action.
3. Sign in as the Treasury operator. Open **Assessment Work** and inspect the immutable Municipal Treasurer approval of the exact amount, distinct from the preparer. Then open **Payment Schedules**, the paid schedule, collection, issued manual receipt, Daily Collections, and Revenue Sources. Inspect the unresolved numbering, installment, surcharge, interest, deficiency-tax, online-payment, and reversal boundaries.
4. Sign in as Municipal Management. Open **Report Catalog** and move through operational, management, and authority-pending report families. Then inspect Users, Roles & Permissions, Municipality & Officials, Taxes & Fees, and Billing Groups.
5. Open the public permit-verification link from the manifest. Confirm that it verifies the generated artifact reference while explicitly refusing release and legal-effect claims.

The real shell and real application pages are the walkthrough. Scenario tooling only prepares and audits state.

## Working And Constrained Matrix

| Area | Working in the preview | Deliberate boundary |
| --- | --- | --- |
| Citizen services | Synthetic account, application list/detail, submission evidence, payment evidence, timeline | No online payment, documentary-sufficiency decision, issuance, release, or legal effect |
| BPLO processing | Intake, supporting evidence, Assessment Officer preparation, approved payment-schedule association, clearance evidence, authority-review readiness, generated artifact | No Treasurer-approval capability in the BPLO preview bundle; no official numbering or authority-bearing release/issuance action |
| Treasury | Exact-snapshot assessment approval/return, paid schedule, over-the-counter collection evidence, issued manual receipt, receipt PDF/print, working collection reports | Preview permission is not final municipal role policy; no automatic official numbering, void/reversal, online reconciliation, statutory schedule, surcharge, interest, PIL, or deficiency-tax policy |
| Clearances | Three deterministic completion records and timeline evidence | Completion does not grant permit authority or legal effect |
| Reports | Five operational and five management reports are discoverable and retain their implemented filters, calculations, and exports | Six authority-pending families expose contract scope but return no official rows or exports pending accepted authority |
| Users and roles | Read-only account, identity-link, role, and permission evidence | No provisioning, mutation, password reset, activation, or municipal role-policy decision |
| Municipality | Existing configuration and official/document association evidence | No signatory, numbering, issuance, release, or legal-effect authority |
| Taxes and fees | Accepted executable fee-rule evidence is distinguishable from reconciliation-required candidates | No new fiscal meaning, liability rule, policy acceptance, or execution of unresolved candidates |
| Billing groups | Provisional definition, draft preparation, and append-only evidence | No liability, collection, receipt, official numbering, or unresolved policy execution; financial effect remains none |
| Public verification | Artifact identity and reference verification | Explicitly artifact-only; release is not verified |

## Stakeholder Questions

### Nelson

- Does each screen state what the operator is looking at, the current record state, and the next legitimate action without engineering explanation?
- Which labels, groupings, or evidence summaries conflict with the municipality's language or working practice?
- Are any visible boundaries too weak, too prominent, or misleading?

### BPLO

- Can intake, evidence review, assessment, clearance status, and authority-review readiness be understood in the order staff actually work?
- What accepted evidence or municipal decision is still required before numbering, issuance, or release can become executable?
- Does the application artifact help review without being mistaken for an issued permit?

### Treasury

- Do the schedule, collection, receipt, and working reports preserve the separation Treasury needs?
- What authority controls official receipt numbering, void/reversal, installment dates, surcharge, interest, PIL, and deficiency-tax behavior?
- Which authority-pending report contracts match official output expectations, and who may accept them?

### Municipal Management

- Does the report catalog separate daily operations, management review, and authority-pending output clearly enough?
- Are Users, Roles, Municipality, Taxes & Fees, and Billing Groups correctly framed as evidence, configuration, or policy-bound workspaces?
- Which office owns each unresolved fiscal, numbering, signatory, report, issuance, release, and legal-effect decision?

## Evidence

### Cycle 3 — Reports, Administration & Visible Boundaries

- Run: `stakeholder-preview-cycle3-reports-administration-20260819-001`
- Root: `storage/app/private/lifecycle-scenarios/stakeholder_preview_cycle_1/stakeholder-preview-cycle3-reports-administration-20260819-001`
- Browser: 53 checks, 46 screenshots; zero application console warnings/errors, failed internal requests, unexpected application resources, or horizontal overflow
- Canonical audit: 21 checks passed
- Specialist packets: report normalization `5accaf18015a922d4c7a711b0a0d7b16c0f3ca22`; administration coherence `4c8c4cb672c5603a5808c2f9a901d73096ee132e`
- Canonical integration commits: `1aede6b`, `1fe7f88`

### Cycle 4 — Deterministic Preview Integration

- Run: `stakeholder-preview-cycle4-integrated-20260819-001`
- Root: `storage/app/private/lifecycle-scenarios/stakeholder_preview_cycle_1/stakeholder-preview-cycle4-integrated-20260819-001`
- Manifest: `manifest.json`
- Browser report: `browser/managed-report.json`
- Canonical audit: `terminal/managed-audit.json`
- Browser: 55 checks, 46 screenshots; zero application console warnings/errors, failed internal requests, unexpected application resources, or horizontal overflow
- Canonical audit: 21 checks passed
- Synthetic application: unnumbered, paid schedule, receipted collection, issued manual receipt, three completed clearances, ready for authority review
- Authority state: issuance false, release false, legal effect false, public verification artifact-only
- Billing-group state: provisional definition, draft record, financial effect none

## Exact Exclusions

- No production deployment or production environment mutation occurred; only the existing non-production `uat` environment advanced.
- No production data or production PII was used.
- No production system was changed.
- The 407-member production migration campaign remains unexecuted and Board-controlled.
- No historical identity was inferred, merged, split, or accepted.
- No municipal procedure, official numbering, fiscal meaning, liability, signatory, issuance, release, legal effect, authority-bearing report, role policy, or billing execution was invented or activated.
- No historical or existing UAT approval was fabricated. New deterministic synthetic runs create explicit approval evidence through the domain action.
- Nelson Cycle 1 and Nelson-driven UI Cycle 2 remain frozen feedback lanes; their baselines were not overwritten.

## Verdict

The existing non-production Laravel Cloud UAT remains available for historical approval-stage inspection. It is not the semantic baseline for final product review. Warp review remains paused until the 2026-08-20 decision packets are answered, safe corrections are integrated, the cloud UAT is redeployed, and a new evidence run is frozen.

**STAKEHOLDER PREVIEW SEMANTIC FREEZE PAUSED**
