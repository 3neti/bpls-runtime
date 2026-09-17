# Ipil UI terminology register — Gate 10A

Date: 2026-09-16. Presentation proposals only. No class, enum, schema, action, report contract or stored evidence is renamed.

The [surface register](IPIL_PRODUCT_SURFACE_REGISTER.json) contains 18 reviewed concept groups, including 15 competing-label/meaning findings. Read with the [audit](IPIL_GATE_10A_PRODUCT_SURFACE_AUDIT_2026_09_16.md), [role map](IPIL_ROLE_JOURNEY_MAP.md) and [implementation plan](IPIL_GATE_10_IMPLEMENTATION_PLAN.md).

## Municipal nouns

| Existing terms | Recommended ordinary wording | Meaning that must survive |
| --- | --- | --- |
| Application / Business Permit Application | Application; full name at entry | Tracking reference, database ID and official application number remain different. |
| Concerned Office / Routed Office | Concerned office | Routing is assignment, not office approval. “Routed” remains useful as a fact. |
| Payment Order / paperless Payment Order / PPO / Payable | Payment Order | A Payment Order is not the final payable, Assessment or receipt. Do not globally replace Payable. |
| Treasury Classification | Treasury determination | Heading for work, not a new domain object. |
| LOB / Line of Business | Line of Business | Official Treasury assignment is not the applicant's activity description. |
| Enterprise Classification | Enterprise classification | Separate from LOB; provisional UAT officer determination, not inferred from business facts. |
| Business Permit Evaluator / Evaluation | Evaluation | Working determinations; not the immutable Assessment. Use assigned task heading where possible. |
| Assessment / Computation–Assessment Slip | Assessment | Prepared financial truth; approval and counter-check are separate. Preserve printed form nomenclature where required. |
| Schedule of Payment / Payment Schedule | Payment Schedule | Not the Schedule of Fees catalogue. |
| Payment / Collection | Payment for citizen; Collection for cashier | QR request is not payment confirmation, and collection is not OR issuance. |
| Receipts / Official Receipt / OR | Official Receipt (OR) | Historical OR claims are explicitly claims, not newly issued receipts. |
| Clearance / Certification | Office certification for current action | Preserve distinct historical clearance records and named municipal documents. |
| Mayoral Authorization | Mayoral Authorization | Authorization, issuance and release must not collapse. |
| Permit / Business Permit | Business Permit | Synthetic/UAT status and no production legal effect remain prominent. |
| Release | Release | Separate act after issuance. |
| Historical Ipil Record / rescued evidence | Historical Record | “Migrated from the previous Ipil BPLS. Read only.” Specific record can be Historical Application. |
| Report Templates / technical report IDs | Reports; meaningful report names | IDs remain in audit/support metadata. No new official/statutory authority. |
| Overview / Dashboard / My Work / Inbox | Inbox for staff work; My Applications for citizen | Dashboard is optional overview; lookup is not a task queue. |

Evidence: `AppSidebar.vue`, `Dashboard.vue`, `ApplicationDocumentNavigator.vue`, `ExecutableApplication.vue`, `business-permit-evaluations/Show.vue`, `permit-applications/Assessments/Show.vue`, `receipts/Show.vue`, and the separately pinned historical/report pages; exact baseline/path/line occurrences live in the JSON register. Domain distinctions above override cosmetic consistency.

## Status presentation

| Source-visible status/term | Classification | Proposed handling |
| --- | --- | --- |
| Draft; Submitted; Approved by Municipal Treasurer; Returned for correction | KEEP_AS_MUNICIPAL_TERM | Keep actor/action-specific meaning and current backend readiness. |
| Frozen (declaration) | RENAME_FOR_PRESENTATION | “Submitted declaration” only when submission evidence exists; otherwise retain accurate frozen snapshot description in details. |
| Living (Page 2) | RENAME_FOR_PRESENTATION | “Municipal processing”; do not imply an action is available. |
| Incomplete · Evaluation binding unavailable | RENAME_FOR_PRESENTATION | “Assessment incomplete — source review is unavailable. Contact the administrator.” Keep exact missing binding in audit evidence; do not repair or approve by inference. |
| Pending / Failed / Expired QR request | KEEP_AS_MUNICIPAL_TERM | Name the object: request expired does not mean Collection failed or money moved. |
| Released Synthetic; UAT Business Permit | KEEP_AS_MUNICIPAL_TERM | Keep synthetic/UAT qualification. Never normalize to a legally valid production permit. |
| Provisional UAT policy — pending Ipil Officer confirmation | KEEP_AS_MUNICIPAL_TERM | Concise but visible at Treasury determination; no band activation. |
| Historical evidence / Historical Application | KEEP_AS_MUNICIPAL_TERM | Read only; not operational or recalculated. |
| Evaluation version, PriceReport, input/report fingerprint, source identity, provenance | AUDIT_ONLY | Preserve complete exact values in audit details; not primary task headings. Version identity remains mandatory to the counter-check. |
| Cleanroom, retained specimen, lifecycle runner, test actor, start classic ceremony | ENGINEERING_ONLY | Protected testing context, never normal municipal labels. |
| Preview price calculation | KEEP_AS_MUNICIPAL_TERM | An ordinary draft calculation preview is not a laboratory control. Label “Not yet recorded” where needed. |

## Copy, warnings and help contract

The interface says what the object is, its state, and what the user can do next. Labels → short contextual hint → specific error/recovery → optional help. Move architecture explanation to audit details or engineering documentation; move procedural reference material to the turnover guide. Do not hide information needed to make a financial/signature decision behind Help.

Warnings:

- `CRITICAL`: synthetic/no legal effect; missing policy authority; signature/undertaking meaning; historical/current boundary; report amounts not current debt or revenue. Keep visible.
- `ACTIONABLE`: incomplete prerequisites, expired request, changed document invalidating signature, failed/uncertain submission. State what happened and the safe next step; avoid encouraging duplicate financial submission.
- `INFORMATIONAL`: useful once-per-context hints. Shorten or move to Help where safe.
- `ENGINEERING`: compiler, payload, canonical projection, manifest wiring. Move to protected details; keep evidence itself.

The 165 substantial static-copy blocks in the register have individual dispositions. Approximate reduction is deliberately conservative, not a target to delete safety text. No paragraph has been rewritten in the application. A label such as “Preview · Sample Data” is a disclosure, not proof of engineering control exposure. Do not erase it to make UAT look production-ready; later environment-aware wording must remain truthful.

## Historical and reports language

Use “Historical Record — Migrated from the previous Ipil BPLS. Read only.” Retain expanded prohibited-action details where useful. Missing records remain missing, not “none owed” or “no transaction ever existed.” Source barangay/LOB classifications remain unaccepted evidence, not accepted mappings.

Proposed ordinary names: **Historical Payment Events** (016), **Recorded Schedule Balances** (004), **Payment and Billing Transactions** (028). Existing report pages already use meaningful titles; directory links and metadata are the main remaining ID prominence. Preserve 016 OR-claim/status distinctions; 004 positive recorded differences, due-year and non-cent precision; 028 heterogeneous amounts, native statuses, source insertion order and missing/not-applicable distinction. Never replace these with one generic “total collected.” Draft versus frozen result/CSV explanations remain actionable. No report change or deployment is authorized here.
