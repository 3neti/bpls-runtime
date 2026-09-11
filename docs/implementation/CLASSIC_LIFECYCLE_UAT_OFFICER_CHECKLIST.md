# Classic Lifecycle UAT Officer Checklist

Status: **One-page field checklist**

Use the same cleanroom ID, Application ID, and `SUB-...` reference throughout. All accounts are synthetic UAT identities; obtain credentials through the approved secure channel.

| Actor / turn | Sign-in identity | Navigate | One material action | Expected status / handoff | Stop and report if |
| --- | --- | --- | --- | --- | --- |
| Citizen — lodging | Fresh invited Citizen | My Permit Applications | **Sign & Submit** the final reviewed Draft once | Lodged; frozen Page 1/manifest; BPLO routing | Draft remains, tracking is absent, or evidence duplicates |
| BPLO Intake | `intake@bpls-runtime.test` | Inbox | **Confirm Routing** once | Routed Payment Orders | Wrong/duplicate office work appears |
| Municipal Assessor | `assessor@bpls-runtime.test` | Inbox | **Sign & Confirm Payment Order** once | Engineering next | Wrong-office items are accepted or order duplicates |
| Engineering | `engineering@bpls-runtime.test` | Inbox | **Sign & Confirm Payment Order** once | Health next | Cross-office action succeeds or order duplicates |
| Health | `health@bpls-runtime.test` | Inbox | **Sign & Confirm Payment Order** once | MENRO next | Wrong subtotal, failed confirmation, or duplicate evidence |
| MENRO | `menro@bpls-runtime.test` | Inbox | **Sign & Confirm Payment Order** once | Treasury classification | Office totals do not reconcile |
| Treasury Counter-checker — classification | `treasury@bpls-runtime.test` | Inbox / Application | Confirm displayed Line(s) of Business and payment items once | Assessment preparation | Applicant truth is relabeled or configured totals drift |
| Assessment Officer — assessment | `assessment-officer@bpls-runtime.test` | Inbox / Assessment | **Prepare Assessment** once | Treasury counter-check | Immutable Assessment differs from source totals |
| Treasury Counter-checker — review | `treasury@bpls-runtime.test` | Inbox / Assessment | Record counter-check once | Municipal Treasurer | Review duplicates or changes the Assessment amount |
| Municipal Treasurer | `municipal-treasurer@bpls-runtime.test` | Inbox / Assessment | **Approve for payment** once | Payment scheduling | Approval applies to a different amount/fingerprint |
| Assessment Officer — schedule | `assessment-officer@bpls-runtime.test` | Inbox / Assessment | **Prepare Payment Schedule** once | Citizen QR Ph | Total, paid, or balance disagrees with Assessment |
| Citizen — payment request | Same invited Citizen | Application / Payment details | **Pay with QR Ph** once | Cashier receives current request; no Collection | Repeat generation appears while request is current |
| Cashier — collection | `cashier@bpls-runtime.test` | Inbox / Collect Payment | **Simulate QR Ph payment** once | One Collection; receipts required | Amount, rail, paid, balance, or count disagrees |
| Cashier — receipts | `cashier@bpls-runtime.test` | Payment Schedule | Issue each displayed synthetic AF No. 51 receipt once | Complete receipt coverage | A group duplicates, disappears, or totals fail parity |
| Municipal Assessor — certification | `assessor@bpls-runtime.test` | Inbox | Certify bound office evidence once | Engineering certification | Another office’s certification can be recorded |
| Engineering — certification | `engineering@bpls-runtime.test` | Inbox | Certify bound office evidence once | Health certification | Another office’s certification can be recorded |
| Health — certification | `health@bpls-runtime.test` | Inbox | Certify bound office evidence once | MENRO certification | Another office’s certification can be recorded |
| MENRO — certification | `menro@bpls-runtime.test` | Inbox | Certify bound office evidence once | Permit readiness | Readiness advances without every routed office |
| Permit Issuer / Mayoral Authorization | `permit-issuer@bpls-runtime.test` | Inbox / Permit | **Issue Permit** once | Issued, not yet released | Readiness is incomplete or issuance duplicates |
| BPLO Releasing Officer | `releasing-officer@bpls-runtime.test` | Inbox / Permit | **Release Permit** once | Public verification | Release precedes issuance or duplicates |
| Citizen / public check | Public Permit reference | Permit QR/reference | Open verification once | Exact released synthetic identity | Reference fails or exposes non-public evidence |

Mandatory controls:

- No yellow preview-role substitution.
- No real funds or real private documents.
- Click material actions once.
- Stop at the first genuine discrepancy and preserve the specimen.
- Exclude browser-extension warnings; record BPLS-originated errors and failed requests.
- Check desktop and `390×844` at Citizen lodging, one Payment Order, Cashier payment/receipts, Permit, and public verification. Confirm no horizontal overflow and all primary controls are reachable.
