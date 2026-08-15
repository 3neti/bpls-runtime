# Engineering Program Review #005: Revenue Code Reconciliation Boundary

Date: 2026-08-15

==================================================

BPLS-RUNTIME

Engineering Program Review #005

Overall Health: YELLOW - architecture and implementation remain healthy; executable financial policy requires reconciliation

Architecture Health: HEALTHY

Current Phase: Early Implementation / Financial Reconciliation Boundary

Recommendation: DECISION REQUIRED

==================================================

## 1. Executive Dashboard

`bpls-runtime` has completed and browser verified the bounded citizen existing-business registry-safety slice authorized after Engineering Program Review #004. The project then moved as directed to its highest-risk frontier: Revenue Code and assessment correctness.

The first reconciliation pass found a genuine financial Board Trigger. The current Laravel fee catalog contains a useful ordinance-backed foundation, but several entries marked executable depend on silent interpretations that have not been accepted as municipal policy. Section 2A.02 contains malformed and overlapping bracket text. The current seed catalog normalizes some of those defects into non-overlapping amounts without retaining a formal resolution. A new-business Mayor's Permit fee is also applied as the micro-industry amount without an implemented and accepted enterprise-classification decision, while the registration-plate rule charges the ordinance ceiling as though it were the confirmed fee.

The architecture is not under stress. Its explicit assessment boundary, immutable line snapshots, legal provenance, and policy exceptions are the correct controls for this situation. The problem is evidence reconciliation, not software structure. Autonomous work outside the disputed financial rules can continue, but expanding or relying on the affected financial catalog requires a conscious decision.

## 2. Current Phase

Current phase: Early Implementation / Financial Reconciliation Boundary.

- Ground Zero, Discovery, and Architecture are complete and approved.
- Staff and citizen new-permit milestone journeys reach the authority boundary.
- Citizen identity, submission, existing-business reuse, and registry safety are implemented and browser verified.
- Assessment snapshots and selected fixed/range calculations are operational.
- The financial frontier has moved from implementation mechanics to rule-by-rule legal and operational reconciliation.
- Migration, production configuration, full Treasury policy, legal issuance/release, and deployment readiness remain ahead.

This is not stabilization. The project is still implementing business capabilities, but financial expansion is paused at the point where further code would convert interpretation into municipal liability.

## 3. Capability Progress

Discovery identified 116 externally or contractually meaningful capabilities. The implementation ledger currently contains 49 active capability rows:

- 38 browser verified;
- 2 partially implemented;
- 9 explicitly blocked;
- 67 discovered capabilities not yet active in the implementation ledger;
- no capability claimed as fully production-parity verified.

These counts are navigational indicators, not weighted progress percentages.

| Area | Current State | Evidence / Remarks |
| --- | --- | --- |
| Registry | Foundational and locally verified | Durable citizen-to-owner identity, owned-business reuse, cross-owner refusal, and registry-safe draft editing are browser/audit verified. Production reconciliation remains unverified. |
| Permitting | Substantial new-permit foundation | Staff and citizen journeys reach authority review. Legal issuance, release, validity, and complete lifecycle variants remain incomplete. |
| Assessment | Foundational; decision required for catalog expansion | Immutable snapshots, one calculation path, selected fixed brackets, explicit refusals, and provenance work. Several seeded rules require reconciliation before they can be trusted as executable municipal policy. |
| Treasury | Partial with explicit seams | OTC collection, allocations, manual receipt, payment evidence, and selected reports work. Numbering, reversal, online payment, reconciliation, and broader Treasury modules remain unresolved. |
| Reporting | Foundational / partial | Several relational operational reports are browser verified. Official formats and the full TOR report inventory remain ahead. |
| Citizen Portal | Substantial first milestone | Identity, drafts, documents, submission, tracking, payments, authority review, artifact verification, and registry safety are demonstrated. |
| Administration | Foundational | Local roles and permissions support implemented journeys. Production roles, officials, signatories, and configuration migration remain ahead. |
| Migration | Not started | Mapping semantics are clearer, but production data and fee configuration are unavailable for reconciliation. |
| Verification | Strong | Exact-record terminal, browser, audit, document, storyboard, and parity evidence cover staff and citizen milestones plus the registry boundary. |

## 4. Architecture Health

Architecture health: healthy.

The approved Laravel monolith, relational source of truth, Vue/Inertia frontend, concrete domain actions, policy authorization, immutable financial snapshots, internal reporting, document boundary, and lifecycle evidence approach remain valid. No redesign is recommended.

Implementation is reinforcing the architecture in two ways:

1. The assessment calculator already refuses formula and rate behavior whose semantics or rounding are uncharacterized.
2. Fee rules preserve legal basis and snapshots, allowing the project to distinguish ordinance evidence from an operationally accepted configuration.

One assumption must be corrected: an extracted ordinance row is not automatically an executable fee rule. Extraction status, reconciliation status, and execution status are separate facts. The approved architecture can represent that distinction without a new rules engine or generic policy framework.

## 5. Project Risks

1. **Financial correctness:** malformed or overlapping Revenue Code text, unaccepted normalizations, enterprise classification, rounding, PIL, surcharge, interest, deficiency tax, and production fee configuration can materially change assessed liability.
2. **Production configuration absence:** the project cannot determine whether the deployed municipality configuration already resolves ordinance typographical defects or establishes exact fee amounts.
3. **Production migration:** financial history, identity, documents, and legacy provenance cannot be reconciled without production data.
4. **Treasury policy:** official receipt numbering, reversals, reconciliation, online payment, and non-permit Treasury scope remain unresolved.
5. **Authority policy:** permit issuance, signatories, release, QR meaning, and legal effect remain intentionally blocked.
6. **Schedule:** financial reconciliation can become the critical path if accepted schedules and production configuration arrive late.
7. **Production parity:** deterministic local evidence does not yet prove production fee, role, data, or edge-case parity.

## 6. Technical Debt

No new deliberate technical debt was accepted during this frontier investigation.

Existing deliberate debt remains visible:

- milestone orchestration still resides in a class whose original name is narrower than its composed responsibility;
- lifecycle evidence remains local and its retention policy is not automated;
- project-wide ESLint retains 16 known errors in untouched pages;
- the local migration ledger has known drift that will be reconciled transparently without rewriting migration history.

The ambiguous executable fee entries are not being classified as acceptable technical debt. They are a financial correctness issue requiring correction or explicit acceptance.

## 7. Major Discoveries

- Section 2A.02(b) has an overlapping wholesale/dealer interval: one row ends below PHP 7,500 while the next begins at PHP 7,000. The current seed silently starts the second row at PHP 7,500.
- The same schedule contains malformed figures such as `150,0000.00` and `5000,000.00`; the current seed normalizes them to PHP 150,000 and PHP 500,000.
- Section 2A.02(a) contains `4,000,0000.00` inside an otherwise sequential manufacturer schedule.
- Section 2A.02(e) contains overlapping contractor rows for PHP 300,000 to below PHP 500,000 and PHP 400,000 to below PHP 500,000 with different annual taxes.
- Section 3A.02(b) establishes a PHP 200 new-business Mayor's Permit fee specifically for micro-industry, but the current executable rule applies it to every new application because enterprise-scale eligibility is not evaluated.
- Section 3A.05 states that the registration-plate charge must not exceed PHP 300. The current executable rule uses PHP 300 as the exact amount while its own metadata says production configuration must confirm that amount.
- The legacy evaluator executes JavaScript floating-point formulas and formats results to two decimals for display; it does not provide a reliable legal rounding contract for the Laravel replacement.
- Section 3A.04's PHP 350 annual business inspection fee is an example of an exact rule that can remain executable without interpretation.

## 8. Evolution

What became simpler:

- The financial problem is no longer “implement formulas.” It is now separated into extraction, reconciliation, eligibility, deterministic calculation, and immutable evidence.
- The application does not need a generic formula language to progress. Exact fixed rules and accepted brackets can remain ordinary persisted fee policy.
- Legacy floating-point behavior is no longer mistaken for governing rounding policy.
- Ambiguous ordinance rows can remain visible catalog evidence without becoming executable liability.
- The single assessment path remains intact; the required change is safer rule eligibility, not another calculator.

## 9. Current Slice Summary

The completed implementation slice since EPR #004 is **Citizen Existing-Business Reuse And Registry Safety**.

It proves that an established citizen can select an exact business belonging to the linked legal owner, that another owner's business is absent from the intake and rejected by server-side validation and the canonical action, and that application editing changes declarations without mutating shared registry identity. The same record was verified on desktop and mobile and audited against canonical owner/business/application state.

The slice is committed as `5e5d34c`. The Revenue Code frontier has produced research and this decision report, but no new financial behavior has been implemented.

## 10. Evidence Produced

Citizen registry evidence:

- scenario key: `citizen_existing_business_registry_safety`;
- run ID: `citizen-existing-business-20260815-003`;
- artifact location: `storage/app/private/lifecycle-scenarios/citizen_existing_business_registry_safety/citizen-existing-business-20260815-003`;
- browser result: 18 checks passed, zero JavaScript errors, zero failed requests;
- audit result: passed;
- external or irreversible actions: none.

Financial reconciliation evidence:

- canonical ordinance: `docs/sources/legislation/ORDINANCE-NO.-08-656-2023-REVISED-MUNICIPAL-REVENUE-CODE-OF-THE-MUNICIPALITY-OF-IPIL-ZS.pdf`, especially PDF pages 2-5 and 14-17, Sections 2A.02 and 3A.02-3A.05;
- current catalog: `database/seeders/RevenueCodeFeeCatalogSeeder.php`;
- current executable tests: `tests/Feature/RevenueCodeFeeCatalogSeederTest.php`;
- calculation boundary: `app/Assessment/AssessmentCalculator.php` and `app/Actions/CreateAssessmentForPermitApplication.php`;
- legacy evaluator: `bpls-system-main/apps/admin/lib/utils/fee-calculator.ts` and `bpls-system-main/packages/backend/convex/fees.ts` inside `docs/sources/legacy/bpls-system-main.zip`.

No production mutation or live financial action was performed.

## 11. Recommendation

DECISION REQUIRED

Autonomous implementation should continue after the Board establishes the financial reconciliation rule below. No architectural redesign is required. Until then, do not add percentage/rate calculations, normalize additional malformed brackets, or treat ambiguous extracted values as executable policy.

## 12. Proposed Decision Contract And Next Slice

Recommended financial reconciliation contract:

1. An ordinance extract may be recorded without being executable.
2. Exact, internally consistent ordinance rules may execute with legal provenance and deterministic cent amounts.
3. Malformed, overlapping, ceiling-only, or eligibility-dependent rules remain non-executable until accepted municipal policy or authoritative production configuration resolves them.
4. Every normalization must retain the original text, the accepted normalized value, the decision authority, and the effective date.
5. Legacy floating-point evaluation is implementation evidence, not the rounding authority.
6. Production fee configuration must be supplied and reconciled before financial parity or migration parity is claimed.

Decision requested from the Board:

- Accept or amend the reconciliation contract above.
- Decide whether the current normalized Section 2A.02(b) wholesale schedule is municipally accepted, or must remain non-executable pending a certified corrected schedule.
- Identify the authority for resolving malformed/overlapping Revenue Code rows.
- Confirm whether the registration-plate charge is currently PHP 300 or another amount below the statutory ceiling.
- Confirm how enterprise scale is determined when asset and workforce classifications differ.

Recommended next slice after decision: **Revenue Code Executability And Reconciliation Safety**.

The slice should separate extracted from accepted executable rules, preserve explicit resolution provenance, keep the exact PHP 350 inspection fee executable, and refuse ambiguous or eligibility-dependent rules before any assessment snapshot is created. It should then browser/audit verify one exact accepted assessment and one visible policy refusal.

## 13. Coffee with Arti

Should the long-lived product treat an LGU's enacted ordinance and its operational fee schedule as two versioned authorities that must be reconciled before execution, rather than assuming the ordinance PDF alone is directly executable policy?

Ipil's Revenue Code shows why this distinction may matter: legal text can govern the ceiling and intent while an accepted operational schedule resolves typographical defects, classification practice, and exact configured amounts. The question is whether that reconciliation record is merely rescue evidence or a durable BPLS concept.

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
- Authority boundary remains explicit: compliant.

## 15. Standing Board Decisions

- Target deployment direction is Laravel Cloud.
- Replacement shape is a single Laravel 13 application.
- Vue/Inertia is the frontend path.
- Relational data is the application source of truth.
- Convex storage topology will not be mechanically reproduced.
- ClickHouse, Airbyte, and Vercel deployment topology will not be retained merely for parity.
- Reporting belongs inside Laravel unless measured requirements prove otherwise.
- Assessment calculations use one authoritative path and immutable snapshots.
- Unknown formula, rate, rounding, PIL, surcharge, interest, receipt, and reconciliation policy must be refused rather than guessed.
- `User -> BusinessOwner -> Businesses` is the current citizen registry identity contract.
- `submitted_by_id` is an actor/audit fact, not legal ownership.
- Citizen drafts are unsubmitted, unnumbered, and have `submitted_at=null`.
- No official application-number format or allocation point is authorized.
- Supporting documents do not establish sufficiency without accepted policy.
- Storyboards and Lifecycle Scenario Runner are verification infrastructure, not workflow engines.
- Production is evidence, not a playground.
- Unknown business policy remains an explicit seam.
- Billing Groups remain provisional pending Treasury acceptance.
- Permit artifact, issuance, release, legal effect, and observed validity remain distinct.
- The Golden Path is emergent.
- GNE concepts must not enter the rescue prematurely.

## 16. If I Were Starting Today

I would extract the Revenue Code into a reconciliation matrix before marking any seeded rule active. Each row would separately record the source text, mathematical interpretation, eligibility inputs, exact/ceiling status, ambiguity, municipal acceptance, and execution status.

The initial catalog correctly preserved legal provenance and policy-boundary metadata, but `is_active` allowed some useful representative rows to look more authoritative than the evidence supports. Establishing executability as an explicit reviewed fact from the first seed would have prevented that overstatement.

## 17. Confidence Index

This measures confidence in sufficient understanding, not implementation progress.

| Area | Confidence | Remarks |
| --- | --- | --- |
| Architecture | [#########-] 92% | The architecture exposed and contained the financial conflict without redesign. |
| Registry | [########--] 78% | Existing-business ownership and mutation boundaries are now browser/audit verified; production reconciliation remains unknown. |
| Permitting | [########--] 78% | New permit reaches authority review; legal issuance and complete variants remain incomplete. |
| Assessment | [#####-----] 49% | Understanding improved, but confidence in catalog correctness dropped after identifying unaccepted normalizations and eligibility gaps. |
| Treasury | [#####-----] 47% | OTC and receipt foundations work; policy-heavy and non-permit scope remain uncertain. |
| Reporting | [#####-----] 48% | Several relational reports work; official acceptance and broader TOR coverage remain ahead. |
| Citizen Portal | [########--] 83% | Registry safety closes the remaining bounded citizen identity gap; correction and production parity remain. |
| Migration | [#---------] 12% | Production data and fee configuration remain unavailable. |
| Verification | [#########-] 94% | Exact-record lifecycle evidence remains strong; financial reconciliation evidence is now explicit. |
| Overall Rescue | [#######---] 69% | Most uncertainty is visible and bounded, but the remaining financial decisions carry disproportionate legal and fiscal weight. |
