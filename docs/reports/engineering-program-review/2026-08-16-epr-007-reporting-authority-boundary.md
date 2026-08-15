# Engineering Program Review #007: Reporting And Authority-Bearing Outputs

Date: 2026-08-16

==================================================

BPLS-RUNTIME

Engineering Program Review #007

Overall Health: YELLOW - engineering execution is healthy; municipal policy and production evidence remain the dominant constraints

Architecture Health: HEALTHY

Current Phase: Mid Implementation / Operational Reporting Recovery

Recommendation: CONTINUE WITH NOTED RISK

==================================================

## 1. Executive Dashboard

`bpls-runtime` now supports one coherent citizen-to-municipality new-permit journey through assessment, payment scheduling, OTC collection, receipt, clearances, permit artifact generation, and authority review. Since Engineering Program Review #006, implementation has also recovered the occupational-calling regulatory boundary and moved decisively into operational reporting.

Five additional operational reports now project persisted assessment, schedule, declaration, allocation, collection, and receipt evidence through Laravel without ClickHouse or a separate reporting application. A sixth frontier, CMCI LDCS Annex B, produced a more important result: the system correctly refuses to create an official row because that row would assert legal permit issuance and release facts that the rescue is not yet authorized to establish.

Architecture remains healthy. The principal project risk is no longer whether Laravel can reproduce the system. It is whether municipal financial, Treasury, reporting, permit-authority, and migration decisions arrive in time for production reconciliation and acceptance. Autonomous implementation should continue on capabilities whose governing facts are sufficiently known, while authority-bearing outputs remain blocked rather than fabricated.

## 2. Current Phase

Current phase: Mid Implementation / Operational Reporting Recovery.

- Ground Zero, Discovery, and Architecture remain complete and approved.
- Citizen and staff new-permit journeys share one domain path and reach authority review.
- Assessment snapshots, OTC collection, receipts, clearances, documents, authorization, audit, and lifecycle evidence are operational.
- Revenue Code evidence currently covers 108 provisions, 82 schedule rows, and 593 source-backed clauses through Article M.
- Assessment Summary, Payment Summary, Breakdown of Collectibles, Business Tax by Major Type, and Total Capital/Gross Summary are browser and audit verified.
- CMCI LDCS is contract-characterized and safely blocked at the permit-authority boundary.
- Production migration, municipality-approved financial execution, legal permit issuance, official report acceptance, and deployment preparation remain incomplete.

The project is not yet in stabilization. Too many high-weight capabilities still depend on municipal decisions and production evidence, even though the implementation and verification method is stable.

## 3. Capability Progress

The capability ledger currently records 118 capabilities. The implementation parity ledger contains 57 active rows:

- 43 browser verified;
- 3 UI partial;
- 1 backend partial;
- 10 explicitly blocked;
- no capability claimed as fully production-parity verified.

These counts are navigational indicators, not percent complete. Capabilities differ materially in business weight and unresolved risk.

| Area | Current State | Evidence / Remarks |
| --- | --- | --- |
| Registry | Foundational and locally verified | User, legal owner, business, and submitting actor are distinct. Production identity reconciliation remains unavailable. |
| Permitting | Substantial new-permit foundation | Citizen and staff journeys reach authority review. Issuance, release, official numbering, legal effect, renewal completion, transfer, and retirement remain incomplete or policy-bound. |
| Assessment | Strong execution boundary, incomplete policy | One authoritative path and immutable snapshots work. One exact inspection fee is accepted; the broader Revenue Code remains reconciliation-required. |
| Treasury | Partial with useful operational evidence | OTC collection, allocation, manual receipt evidence, schedules, queues, and selected reports work. Official numbering, reversal, online payment, reconciliation, and broader Treasury modules remain unresolved. |
| Reporting | Material foundation, not municipality-accepted | Five report capabilities are browser verified. CMCI proves that official reporting can depend on legal authority, not merely query correctness. PLDS, BSP, DNFBP, official formats, and production parity remain ahead. |
| Citizen Portal | Substantial first milestone | Citizen draft, formal submission, ownership safety, tracking, financial visibility, and authority-review visibility share the canonical domain. Corrections, notifications, and permit release remain incomplete. |
| Regulation / MTOP / Occupations | Evidence foundation | MTOP and occupational-calling concepts are characterized and non-executable. No product expansion beyond the Ipil rescue is authorized. |
| Administration | Foundational | Roles and permissions support implemented journeys. Production users, officials, signatories, report metadata, and configuration migration remain ahead. |
| Migration | Not started | Provenance and identity rules are clearer, but production data and configuration have not been supplied. |
| Verification | Strong | Exact-record terminal, browser, audit, document, storyboard, milestone, and parity evidence continue to agree. |

## 4. Architecture Health

Architecture health: healthy.

The approved single Laravel application, relational source of truth, Vue/Inertia frontend, business actions, policies, immutable financial snapshots, internal reporting, document boundary, audit trail, and lifecycle evidence architecture remain valid. No redesign is recommended.

Implementation has reinforced four architectural conclusions:

1. Operational reports should project persisted business evidence and must not recalculate financial liability.
2. A report row may itself be an authority-bearing statement. CMCI inclusion means more than matching a status value; it asserts a legally released permit with an official number and issue date.
3. Permit artifact, application status, issuance, release, legal effect, and report eligibility remain distinct facts.
4. Reporting can remain inside Laravel while preserving report-specific grain, qualification date, financial scope, and policy boundaries.

No reporting framework, analytical database, generic policy engine, or parallel workflow implementation has been introduced.

## 5. Project Risks

1. **Municipal policy reconciliation:** financial formulas, PIL, surcharge, interest, renewal basis, fee eligibility, operational schedules, and numerous Revenue Code interpretations remain unaccepted.
2. **Production migration:** no production dataset or configuration has been supplied for deterministic mapping, volume testing, exception analysis, and reconciliation.
3. **Permit authority:** issuance, release, official number allocation, issue date, signatories, QR meaning, legal effect, and citizen access remain unresolved and now block authority-bearing reports as well as permit release.
4. **Treasury authority:** official receipt numbering, void/reversal, reconciliation, online payment, account mapping, generic billing-group acceptance, and non-permit collection behavior remain incomplete.
5. **Official reporting acceptance:** current reports have explicit internal contracts but not accepted municipal layouts, cutoffs, classifications, certification rules, or production parity.
6. **Contractual breadth:** notifications, remaining citizen behavior, non-permit Treasury modules, additional reports, and permit lifecycle variants remain incomplete.
7. **Schedule and cutover:** software momentum remains strong, but late production data and authority decisions can compress migration, acceptance, and deployment regardless of implementation speed.

## 6. Technical Debt

- Report pages and controllers intentionally retain some repetition.
  - Reason: report grain, qualification, totals, and policy boundaries are still being recovered and should remain explicit.
  - Impact: shared filtering, summary, and CSV patterns are repeated.
  - Cleanup: extract only stable presentation helpers after the report family is sufficiently characterized.

- Revenue Code policy evidence remains one long catalog surface with explicit scenario totals.
  - Reason: this supports fast, deterministic recovery and makes evidence loss fail immediately.
  - Impact: human review becomes harder as the catalog grows.
  - Cleanup: add article filters and bounded navigation before municipality-facing reconciliation.

- Lifecycle evidence remains local under `storage/app/private/**`.
  - Reason: local portable packages are sufficient during rescue implementation.
  - Impact: retention and shared review are manual.
  - Cleanup: establish durable artifact storage and retention during deployment preparation.

- Local migration-ledger drift remains a known development-environment maintenance issue.
  - Reason: migration history has not been rewritten merely to make one local database appear clean.
  - Impact: local reconciliation requires care; fresh-database correctness remains authoritative.
  - Cleanup: reconcile transparently during stabilization without rewriting shared migration history.

No unauthorized financial calculation, report row, permit issuance, receipt authority, or legal effect has been accepted as technical debt.

## 7. Major Discoveries

- **Official reports are not all ordinary projections.** Some summarize recorded transactions; others certify legal or administrative facts. CMCI LDCS belongs to the latter category because each row asserts a released permit, official number, and issue date.
- **A raw `Released` application status is insufficient evidence.** Correct report eligibility requires the authority facts behind issuance and release, not a convenient enum value.
- **Reporting semantics need explicit grain and time authority.** Assessment Summary is assessment-snapshot based; Payment Summary is schedule based; Collectibles is application/outstanding-schedule based; other reports qualify by receipted collection date while retaining lifetime figures.
- **Persisted allocation evidence is sufficient for useful operational reporting.** Business Tax by Major Type can group receipted Tax allocations without recalculating tax or preserving the legacy analytical topology.
- **Occupational calling permits are a person-and-employer regulatory boundary, not another business fee.** The catalog now preserves identity, exemption, age, employer-advance, ID, registry, penalty, and cancellation questions without inventing a workflow.

## 8. Evolution

What became simpler:

- Reporting now follows one rule: identify the authoritative persisted record, declare the row grain and qualification basis, and refuse unknown authority.
- Assessment, payment, collection, allocation, receipt, declaration, and report evidence share the same relational model; ClickHouse and Airbyte remain unnecessary.
- The full permit lifecycle scenario verifies new reports against records it already creates, avoiding duplicate fixtures or a second workflow path.
- CMCI is simpler as an explicit blocked contract than as a speculative query with misleading rows.
- Report-specific semantics remain concrete; no generic reporting engine is required to make progress.

## 9. Current Slice Summary

Since Engineering Program Review #006, implementation completed:

1. Occupational Calling Permit evidence recovery through Article M.
2. Assessment Summary reporting from current immutable assessment snapshots.
3. Payment Summary reporting at payment-schedule grain with collection and receipt evidence.
4. Breakdown of Collectibles with explicit unscheduled balances instead of invented due dates.
5. Business Tax by Major Type from receipted Tax allocations and first-activity classification.
6. Total Capital/Gross Summary with declaration values counted once and lifetime receipted payments.
7. CMCI LDCS Annex B contract recovery and authority-boundary refusal.

The completed reporting slices use real application services and records, server-side authorization, responsive Inertia surfaces, CSV only where authorized, lifecycle browser checks, canonical audit comparisons, and parity updates.

## 10. Evidence Produced

Primary evidence packages since the previous review:

- Occupational calling: `storage/app/private/lifecycle-scenarios/revenue_code_fee_catalog_visibility/revenue-code-occupational-article-m-20260815-001`
- Assessment Summary: `storage/app/private/lifecycle-scenarios/permit_application_pending_payment_visibility/assessment-summary-20260815-002`
- Payment Summary: `storage/app/private/lifecycle-scenarios/manual_collection_receipt_visibility/payment-summary-20260815-001`
- Breakdown of Collectibles: `storage/app/private/lifecycle-scenarios/permit_application_pending_payment_visibility/collectibles-20260815-001`
- Business Tax by Major Type: `storage/app/private/lifecycle-scenarios/manual_collection_receipt_visibility/business-tax-major-20260815-001`
- Total Capital/Gross: `storage/app/private/lifecycle-scenarios/manual_collection_receipt_visibility/capital-gross-summary-20260816-001`
- CMCI authority boundary: `storage/app/private/lifecycle-scenarios/manual_collection_receipt_visibility/cmci-ldcs-boundary-20260816-001`

Each package contains a manifest, human summary, browser report, screenshots, canonical audit, and storyboard evidence relevant to its claim. The capability and parity ledgers provide the durable navigation layer.

Latest verification baseline:

- full backend suite: 309 tests, 4,293 assertions, pass;
- PHPStan: pass;
- Vue TypeScript: pass;
- focused ESLint: pass;
- Pint: pass;
- production frontend build: pass;
- CMCI terminal/browser/audit lifecycle: pass;
- CMCI JavaScript errors and failed application requests: zero;
- external calls, notifications, or irreversible operations: none.

## 11. Recommendation

CONTINUE WITH NOTED RISK

Autonomous implementation should continue. Architecture, code quality, and verification remain healthy. The noted risk is project-wide: several high-value capabilities now terminate at municipal knowledge and authority boundaries that engineering cannot resolve from source code alone.

Implementation should continue where evidence supports correct behavior. It should not convert blocked authority-bearing outputs into apparent progress.

## 12. Next Vertical Slice

Recommended next slice: **PLDS Report Reconciliation And Safe Projection Boundary**.

Why it is next:

- it is the next adjacent legacy reporting capability after CMCI;
- Discovery already records explicit category-synchronization gaps in its principal economic activity and product/service fields;
- it will determine which PLDS fields can be projected from current registry and application evidence and which must remain visibly unavailable;
- it continues contractual reporting recovery while testing whether permit issuance authority is also a qualification dependency;
- it reduces reporting uncertainty without inventing municipal classifications or release policy.

If PLDS also requires unresolved legal release facts, the slice should preserve its contract and refuse only the unsupported portions or the entire official output, according to the recovered legacy and contractual semantics.

## 13. Coffee with Arti

Should the product explicitly distinguish an **operational report** from an **authority-bearing register or return**?

The difference is now material. An operational report can summarize persisted transactions with disclosed scope. An authority-bearing output such as CMCI may communicate that the Municipality has legally issued or released permits. Treating both as generic reports risks turning a technically correct query into an unauthorized public or regulatory statement.

## 14. Constitution Check

- Evidence before design: compliant.
- Design before implementation: compliant.
- Production treated as evidence, not a playground: compliant.
- Unknown policy remains explicit: compliant.
- Laravel-native direction preserved: compliant.
- No premature GNE abstraction: compliant.
- Observable parity remains primary: compliant.
- Financial calculations remain on one authoritative path: compliant.
- Reports project persisted evidence and do not recalculate liability: compliant.
- Storyboards remain verification and communication artifacts: compliant.
- Lifecycle runner remains verification infrastructure, not a workflow engine: compliant.
- Domain remains the source of business truth: compliant.
- Authority boundaries remain explicit: compliant.

## 15. Standing Board Decisions

- Target deployment direction is Laravel Cloud.
- Replacement shape is a single Laravel 13 application with Vue/Inertia and a relational source of truth.
- Convex, ClickHouse, Airbyte, and Vercel topology will not be retained merely for parity.
- Reporting belongs inside Laravel unless measured requirements prove otherwise.
- Reports must declare grain, qualification basis, and financial scope.
- Reports must project persisted financial evidence and must not recalculate liability.
- Authority-bearing report rows must not be inferred from artifacts or raw status values.
- Assessment calculations use one authoritative path and immutable snapshots.
- Legal extraction is not executable municipal policy.
- Unknown formula, amount, eligibility, rounding, penalty, receipt, release, and reconciliation behavior must be refused rather than guessed.
- Enacted ordinance and operational fee schedule are distinct, versioned authorities connected by reconciliation evidence.
- Storyboards and lifecycle scenarios are verification infrastructure, not workflow engines.
- Permit artifact, issuance, release, legal effect, and observed validity remain distinct.
- Production is evidence, not a playground.
- GNE concepts must not enter the rescue prematurely.

## 16. If I Were Starting Today

I would classify discovered reports during Discovery as either operational projections or authority-bearing outputs. The current evidence-first implementation still surfaced the distinction safely, but recognizing it earlier would have made permit-number, issue-date, signatory, and certification dependencies more visible in the initial risk map.

I would also record the row grain and qualification date for every report during characterization. Those two facts have proven more important to parity and financial correctness than superficial column similarity.

## 17. Confidence Index

This measures confidence in sufficient understanding, not implementation progress.

| Area | Confidence | Remarks |
| --- | --- | --- |
| Architecture | [#########-] 95% | Reporting and authority boundaries fit the approved architecture without redesign. |
| Registry | [########--] 79% | Core identity and declaration sources are stable; production and classification mapping remain unknown. |
| Permitting | [########--] 82% | The new-permit journey is coherent through authority review; issuance and lifecycle variants remain unresolved. |
| Assessment | [#######---] 74% | Snapshot and execution safety are strong; broad policy acceptance remains absent. |
| Treasury | [######----] 58% | Operational evidence and reports improved understanding; authority-heavy behavior remains unresolved. |
| Reporting | [#######---] 68% | Five reports are verified and CMCI is safely characterized; official acceptance and remaining outputs remain ahead. |
| MTOP / Regulation | [######----] 62% | MTOP and occupational legal boundaries are explicit; operational procedures are not evidenced. |
| Citizen Portal | [########--] 83% | The first citizen milestone remains stable; corrections, notifications, and release remain incomplete. |
| Migration | [#---------] 14% | Semantic boundaries are clearer, but production data remains unavailable. |
| Verification | [##########] 96% | Exact-record terminal, browser, audit, document, and parity evidence consistently agree. |
| Overall Rescue | [########--] 76% | Most uncertainty is visible and bounded; the remaining policy, migration, and authority risks are disproportionately difficult. |
