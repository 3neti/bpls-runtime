# Engineering Program Review #006: MTOP Regulatory Boundary

Date: 2026-08-15

==================================================

BPLS-RUNTIME

Engineering Program Review #006

Overall Health: YELLOW - implementation and architecture are healthy; municipal policy reconciliation remains the dominant risk

Architecture Health: HEALTHY

Current Phase: Mid Implementation / Revenue Code and Regulatory Recovery

Recommendation: CONTINUE WITH NOTED RISK

==================================================

## 1. Executive Dashboard

`bpls-runtime` has moved from proving the new-permit rescue architecture to systematically recovering the Municipality's executable revenue and regulatory knowledge. The application now has a browser- and audit-verifiable Revenue Code evidence catalog covering Chapter II Articles A-F and Chapter III Articles A-L. One exact annual business inspection fee is authorized through the single assessment path; every other unresolved amount, formula, eligibility rule, authority decision, sanction, and procedure is visible but refused execution.

Article L materially expanded the understood BPLS boundary. MTOP is not merely another fee. It is a municipal transport-regulation subsystem spanning operator and vehicle identity, franchise and permit authority, LTO evidence, eligibility, fares, routes, operating conduct, apprehension, escalating penalties, impoundment, and Treasury disposition. The architecture absorbed this discovery without redesign because evidence, executable policy, transactional behavior, and authority decisions remain separate.

Autonomous implementation should continue. The principal threat is no longer architectural uncertainty; it is delayed municipal reconciliation of legal text, operational schedules, production configuration, authority procedure, and migration data.

## 2. Current Phase

Current phase: Mid Implementation / Revenue Code and Regulatory Recovery.

- Ground Zero, Discovery, and Architecture are complete and approved.
- Staff and citizen new-permit milestone journeys reach the authority-review boundary.
- Assessment snapshots, OTC collection, receipts, clearances, documents, selected reports, authorization, audit, and lifecycle evidence are operational.
- Revenue Code extraction now covers Articles A-L within the implemented catalog boundary.
- MTOP legal evidence is characterized but no MTOP workflow or enforcement behavior has been invented.
- Production data migration, municipal financial reconciliation, legal permit issuance, and deployment preparation remain incomplete.

The project is beyond early implementation because complete business journeys and a broad legal evidence catalog now coexist. It is not yet in stabilization because major financial, Treasury, migration, and authority policies remain unresolved.

## 3. Capability Progress

Discovery established 116 baseline capabilities. Two additional implementation-era boundaries, citizen formal submission and MTOP regulation, have been recorded separately as they emerged. The implementation parity ledger currently contains 50 active rows:

- 38 browser verified;
- 2 UI partial;
- 1 backend partial;
- 9 explicitly blocked;
- no capability claimed as fully production-parity verified.

These counts are navigational indicators. Capabilities have unequal business weight.

| Area | Current State | Evidence / Remarks |
| --- | --- | --- |
| Registry | Foundational and locally verified | Citizen, legal owner, business, and submitter identities are distinct. Production identity reconciliation remains unavailable. |
| Permitting | Substantial new-permit foundation | Citizen and staff journeys reach authority review. Legal issuance, release, validity, renewal policy, and lifecycle variants remain incomplete. |
| Assessment | Strong foundation, incomplete policy | One authoritative path and immutable snapshots work. The catalog records 102 provisions, 82 schedule rows, and 498 clauses; only one Revenue Code fee is accepted for execution. |
| Treasury | Partial with explicit seams | OTC collection, allocations, receipt evidence, and selected reports work. Numbering, reversal, online payment, reconciliation, fine collection, and broader modules remain unresolved. |
| MTOP / Regulation | Evidence foundation | All 21 Article L sections and 73 clauses are visible and browser/audit verified. No transactional MTOP, fare, citation, impoundment, or fine behavior exists yet. |
| Reporting | Foundational / partial | Selected operational reports are verified. Official formats, full TOR scope, and municipal acceptance remain ahead. |
| Citizen Portal | Substantial first milestone | A citizen-originated application shares the canonical staff domain path through authority review. Corrections, notifications, downloads, and production parity remain incomplete. |
| Administration | Foundational | Roles and permissions support implemented journeys. Production users, officials, signatories, regulatory boards, and configuration migration remain ahead. |
| Migration | Not started | Provenance requirements and identity boundaries are clearer; production data and configuration have not been supplied. |
| Verification | Strong | Terminal, browser, audit, document, storyboard, milestone, and parity evidence are deterministic and exact-record based. |

## 4. Architecture Health

Architecture health: healthy.

The single Laravel application, relational source of truth, Vue/Inertia frontend, domain actions, policy authorization, immutable financial snapshots, internal reporting, document boundary, and lifecycle evidence architecture remain valid. No redesign is recommended.

Implementation has reinforced three architectural decisions:

1. Legal extraction and executable municipal policy are different records. Candidate values can remain useful evidence without becoming collectible amounts.
2. Regulatory authority is a domain fact. MTRB recommendations, Sangguniang Bayan decisions, licensing operations, LTO evidence, apprehension, and Treasury custody cannot be collapsed into a generic status transition.
3. A regulatory subsystem may share identity, payment, receipt, document, authorization, and audit infrastructure without being forced into the business-permit lifecycle.

No generic policy engine, generalized lifecycle framework, or separate MTOP service has been introduced.

## 5. Project Risks

1. **Municipal financial reconciliation:** most extracted values remain unaccepted; production fee configuration, formulas, rounding, PIL, surcharge, interest, deficiency tax, and operational schedules are still required.
2. **Production migration:** no production dataset has been supplied for deterministic mapping, reconciliation, exception analysis, or volume testing.
3. **Treasury authority:** official receipt numbering, reversals, reconciliation, online payment, fine collection, General Fund account mapping, and generic billing-group acceptance remain unresolved.
4. **Regulatory authority and due process:** MTOP board procedure, permit effect, routes, tariff, citations, offense counting, impoundment, appeal, and current legal validity require municipal and legal acceptance.
5. **Permit legal authority:** signatory, issuance, release, QR meaning, legal effect, and citizen download remain intentionally blocked.
6. **Contractual breadth:** non-permit Treasury modules, notifications, official reporting acceptance, and remaining Revenue Code articles exceed the completed implementation surface.
7. **Schedule and cutover:** late arrival of production configuration, authority decisions, and migration data can compress reconciliation and acceptance even when software structure is ready.

## 6. Technical Debt

- Revenue Code policy boundaries are displayed in one long evidence page.
  - Reason: the current read-only surface made rapid extraction and exact browser verification possible without creating another catalog interface.
  - Impact: the page is increasingly difficult for human review as the catalog grows.
  - Cleanup: add section/article filters and bounded pagination before municipality-facing reconciliation sessions.

- Scenario expectations contain explicit catalog totals.
  - Reason: hard totals make accidental evidence loss fail immediately and keep manifests deterministic.
  - Impact: each intentional catalog expansion requires coordinated test and scenario updates.
  - Cleanup: retain invariant totals but derive article-level expectations from a committed catalog definition if maintenance becomes error-prone.

- Generated lifecycle evidence remains local under `storage/app/private/**`.
  - Reason: local portable packages are sufficient during rescue implementation.
  - Impact: retention and shared review are manual.
  - Cleanup: establish durable artifact storage and retention during deployment preparation.

No financial rule, MTOP authority, enforcement action, or legal effect has been accepted as technical debt.

## 7. Major Discoveries

- MTOP is a complete regulatory subsystem, not a fee category. Its lifecycle depends on board authority, applicant and family eligibility, vehicle identity, external LTO status, operating policy, and enforcement evidence.
- The printed MTOP fee totals reconcile arithmetically, but arithmetic agreement does not establish current operational authority, payer, cadence, receipt treatment, or collectibility.
- Article L contains an internal day-off defect: it maps a supposed last digit of `9 and 10` to Friday. A decimal last digit cannot be 10, so execution must await authoritative correction.
- Fare values and discounts are embedded in the ordinance while tariff adjustment authority is split between MTRB recommendation and Sangguniang Bayan approval. Current fare authority therefore cannot be inferred from the PDF alone.
- Enforcement requires a durable chain from allegation and citation through investigation, final finding, offense counting, sanction, collection, disposition, and appeal. A fine amount alone is not an executable penalty policy.
- No MTOP implementation was found in the studied legacy archive. Municipality and production evidence will be primary for operational recovery.

## 8. Evolution

What became simpler:

- Revenue Code recovery now uses one repeatable distinction: source evidence, reconciliation, executable policy, and transaction snapshot.
- MTOP fees, fare rules, operating restrictions, and penalties no longer risk being mistaken for ordinary business-permit fee lines.
- Authority is represented as an explicit boundary rather than hidden inside application status or controller access.
- Candidate legal values can be inspected in the same UI while one calculation path continues to refuse unauthorized execution.
- The evidence package demonstrates the whole catalog contract, replacing isolated claims about each extracted section.

## 9. Current Slice Summary

Three coordinated Article L slices were completed:

1. **MTOP Foundation** recovered scope, definitions, MTRB authority, eligibility, documents, annual renewal, fee candidates, and LTO conversion evidence.
2. **Tricycle Operating Policy** recovered markings, stoppage, non-transferability, day-off, fares, routes, lane use, and association policy.
3. **Enforcement And Evidence** recovered prohibited conduct, apprehending authority, escalating sanctions, impoundment, Treasury disposition, and legal-effect clauses.

The slices added 21 provisions and 73 clauses. Every clause is reconciliation-required and non-executable. No external or irreversible operation occurred.

## 10. Evidence Produced

Primary evidence package:

- scenario: `revenue_code_fee_catalog_visibility`;
- run ID: `revenue-code-tricycle-article-l-20260815-001`;
- artifact location: `storage/app/private/lifecycle-scenarios/revenue_code_fee_catalog_visibility/revenue-code-tricycle-article-l-20260815-001`;
- manifest: `manifest.json`;
- human summary: `summary.html`;
- browser report and screenshots: `browser/`;
- canonical audit: `terminal/audit.json`;
- storyboard: `storyboard/`;
- parity evidence: `docs/implementation/PARITY_LEDGER.md`, CAP-118.

Verification results:

- Article L focused regression: pass;
- relevant backend suite: 82 tests, 1,749 assertions, pass;
- lifecycle prepare/browser/audit: pass;
- desktop and mobile screenshots: inspected, nonblank, and complete;
- JavaScript errors: zero;
- failed application requests: zero;
- TypeScript: pass;
- production frontend build: pass;
- external calls, notifications, or irreversible actions: none.

## 11. Recommendation

CONTINUE WITH NOTED RISK

Autonomous implementation should continue. Architecture and verification remain healthy. The noted risk is that implementation can characterize and safely refuse unknown policy faster than the Municipality may reconcile it; executable parity will eventually depend on authoritative operational decisions and production evidence.

## 12. Next Vertical Slice

Recommended next slice: **Article M Occupational Calling Permit Foundation**.

Why it is next:

- it is the next contiguous Revenue Code boundary;
- it covers a municipality-facing annual permit catalog for occupations not requiring government examination;
- it can reuse proven evidence, reconciliation, authorization, browser, and audit patterns without introducing a new calculation path;
- it will test classification, person identity, employer relationship, annual validity, and occupational ID boundaries before amounts are accepted for collection.

## 13. Coffee with Arti

Should MTOP ultimately be presented as part of the BPLS product because it shares municipal licensing and Treasury infrastructure, or as a distinct transport-regulation workspace that happens to reuse the same runtime?

The current architecture supports either answer without a service split. The product decision matters because BPLO staff, MTRB members, traffic enforcers, Treasury personnel, operators, and citizens do not share the same operational journey even though they share identity, payments, receipts, evidence, and audit.

## 14. Constitution Check

- Evidence before design: compliant.
- Design before implementation: compliant.
- Production treated as evidence, not a playground: compliant.
- Unknown policy remains explicit: compliant.
- Laravel-native direction preserved: compliant.
- No premature GNE abstraction: compliant.
- Observable parity remains primary: compliant.
- Financial calculations remain on one authoritative path: compliant.
- Storyboards remain verification and communication artifacts: compliant.
- Lifecycle runner remains verification infrastructure, not a workflow engine: compliant.
- Domain remains the source of business truth: compliant.
- Authority boundaries remain explicit: compliant.

## 15. Standing Board Decisions

- Target deployment direction is Laravel Cloud.
- Replacement shape is a single Laravel 13 application with Vue/Inertia and a relational source of truth.
- Convex, ClickHouse, Airbyte, and Vercel topology will not be retained merely for parity.
- Reporting belongs inside Laravel unless measured requirements prove otherwise.
- Assessment calculations use one authoritative path and immutable snapshots.
- Legal extraction is not executable municipal policy.
- Unknown formula, amount, eligibility, rounding, penalty, receipt, and reconciliation behavior must be refused rather than guessed.
- Enacted ordinance and operational fee schedule are distinct, versioned authorities connected by reconciliation evidence.
- Storyboards and lifecycle scenarios are verification infrastructure, not workflow engines.
- Permit artifact, issuance, release, legal effect, and observed validity remain distinct.
- Production is evidence, not a playground.
- GNE concepts must not enter the rescue prematurely.

## 16. If I Were Starting Today

I would establish the legal-evidence catalog before implementing the first assessment fee. The current sequence still protected correctness, but the complete catalog makes it much easier to distinguish a deterministic amount from a deceptively simple amount whose eligibility, authority, or collection procedure is unresolved.

I would also identify regulatory subsystems such as MTOP during Discovery as capability clusters rather than allowing their fee headings to imply they were ordinary assessment configuration. Article L shows that a heading can conceal an entire authority and enforcement domain.

## 17. Confidence Index

This measures confidence in sufficient understanding, not implementation progress.

| Area | Confidence | Remarks |
| --- | --- | --- |
| Architecture | [#########-] 94% | Article L expanded domain breadth without stressing the approved architecture. |
| Registry | [########--] 78% | Core identity boundaries are stable; production and regulatory actor mapping remain unknown. |
| Permitting | [########--] 81% | New permit reaches authority review; legal issuance and lifecycle variants remain incomplete. |
| Assessment | [#######---] 72% | Execution safety is strong and catalog breadth has grown; most municipal policy remains unreconciled. |
| Treasury | [#####-----] 52% | OTC and receipt foundations work; numbering, reversal, reconciliation, fines, and broader scope remain uncertain. |
| MTOP / Regulation | [######----] 61% | Legal scope is now explicit; operational workflow and current policy are not yet evidenced. |
| Reporting | [#####-----] 51% | Selected reports work; official acceptance and full TOR coverage remain ahead. |
| Citizen Portal | [########--] 83% | First milestone is stable; correction, notifications, downloads, and production parity remain. |
| Migration | [#---------] 14% | Semantics are clearer, but production data remains unavailable. |
| Verification | [#########-] 95% | Exact-record terminal, browser, audit, document, and parity evidence consistently agree. |
| Overall Rescue | [#######---] 74% | Uncertainty is increasingly explicit and bounded, but remaining policy and migration risks are disproportionately difficult. |
