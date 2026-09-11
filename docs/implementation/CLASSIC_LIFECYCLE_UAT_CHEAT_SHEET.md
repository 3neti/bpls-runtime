# Classic Lifecycle UAT Cheat Sheet

Status: **UAT operator guidance**

This guide supersedes the earlier ad hoc Nelson Classic cheat sheet. It follows the current 25-stage Classic Lifecycle Laboratory ceremony and current product labels without defining production policy.

## Ground rules

- Use one fresh Classic cleanroom, one Application, and one `SUB-...` tracking reference.
- Use normal sign-in and sign-out at every actor boundary. Never substitute a yellow preview role.
- Use only synthetic identities, documents, signatures, payment attempts, and Official Receipt numbers.
- No real funds move. Generate QR Ph as the Citizen; confirm the synthetic payment as the Cashier.
- Click each material action once. Refresh and inspect authoritative state before considering any retry.
- Stop at the first genuine discrepancy, preserve the specimen, and record expected versus actual behavior.
- Ignore browser-extension warnings; record BPLS-originated console errors and failed requests.
- At representative Citizen, Payment Order, Cashier, Permit, and public-verification screens, check desktop and `390×844` for overflow and unreachable controls.

## Before starting

1. Confirm the intended UAT deployment is current.
2. Open **Lifecycle Laboratory**.
3. If a prior run is **Active**, do not replace it. Preserve or complete it under the approved test instruction.
4. If a prior run is **Completed**, use **Close and retain evidence**. Confirm it appears under **Retained cleanrooms** before starting again.
5. Click **Start classic ceremony** once. Record the new cleanroom ID.
6. Use the one-time invitation to register a unique synthetic Citizen. Do not record passwords in this guide or the test report.

## Exact 25-stage ceremony

| Stage | Actor | Navigate to | Material action | Expected handoff |
| ---: | --- | --- | --- | --- |
| 1 | Laboratory operator | Lifecycle Laboratory | **Start classic ceremony** | One-time Citizen registration |
| 2 | Citizen | My Permit Applications | Save Draft, finish documents, accept Oath, sign, then **Sign & Submit** once | BPLO routing; Page 1 and manifest frozen; one `SUB-...` reference |
| 3 | BPLO Intake | Inbox | Select the configured concerned offices and **Confirm Routing** | Office work creation |
| 4 | System | Laboratory projection | No tester action | First routed office Payment Order |
| 5 | Municipal Assessor | Inbox | Add configured item(s), sign, and **Sign & Confirm Payment Order** | Engineering Payment Order |
| 6 | Engineering | Inbox | Add configured item(s), sign, and **Sign & Confirm Payment Order** | Health Payment Order |
| 7 | Health | Inbox | Add configured item(s), sign, and **Sign & Confirm Payment Order** | MENRO Payment Order |
| 8 | MENRO | Inbox | Add configured item(s), sign, and **Sign & Confirm Payment Order** | Treasury classification |
| 9 | Treasury Counter-checker | Inbox / Application | Assign the displayed official Line(s) of Business and confirm configured payment items | Assessment preparation |
| 10 | Assessment Officer | Inbox / Assessment | **Prepare Assessment** once | Treasury counter-check |
| 11 | Treasury Counter-checker | Inbox / Assessment | Record the counter-check once | Municipal Treasurer review |
| 12 | Municipal Treasurer | Inbox / Assessment | **Approve for payment** once | Payment Schedule preparation |
| 13 | Assessment Officer | Inbox / Assessment | **Prepare Payment Schedule** once | Citizen payment request |
| 14 | Citizen | Application / Payment details | **Pay with QR Ph** once | Current QR request available to Cashier; no Collection yet |
| 15 | Cashier | Inbox / Collect Payment | **Simulate QR Ph payment** once | One Collection; zero balance; receipts required |
| 16 | Cashier | Payment Schedule | Issue one unique synthetic AF No. 51 receipt for every displayed receipt group | Full receipt coverage |
| 17 | System | Laboratory projection | No tester action | First routed post-payment certification |
| 18 | Municipal Assessor | Inbox | Certify the office’s bound payment and receipt evidence once | Engineering certification |
| 19 | Engineering | Inbox | Certify the office’s bound payment and receipt evidence once | Health certification |
| 20 | Health | Inbox | Certify the office’s bound payment and receipt evidence once | MENRO certification |
| 21 | MENRO | Inbox | Certify the office’s bound payment and receipt evidence once | Permit readiness |
| 22 | Permit Issuer / Mayoral Authorization | Inbox / Permit | Review readiness; no separate mutation beyond the displayed ceremony | Permit issuance |
| 23 | Permit Issuer / Mayoral Authorization | Permit | Record synthetic Mayoral Authorization and **Issue Permit** once | BPLO release |
| 24 | BPLO Releasing Officer | Inbox / Permit | **Release Permit** once | Public verification |
| 25 | Citizen or tester | Permit QR/reference | Open public verification | Exact released synthetic Permit identity resolves |

## Evidence to record

- Deployment SHA and deployment ID.
- Cleanroom ID, Application ID, Citizen identity, and `SUB-...` reference.
- Draft/document/Oath/signature/lodging behavior and idempotency counts.
- Routed offices and each configured Payment Order subtotal.
- Treasury-assigned Lines of Business and configured payment items.
- Assessment and Payment Schedule identities.
- Exact parity across Schedule of Payment, PriceReport, Assessment, Payment Schedule, Collection, and total receipted.
- QR attempt, Collection, every displayed receipt group, Permit number, release result, and public verification reference.
- Desktop/mobile overflow, console, and network observations.

When finished, use **Close and retain evidence** only if the approved test instruction authorizes it. Retaining must preserve the linked Application and make the run independently viewable under **Retained cleanrooms**.
