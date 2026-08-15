# Engineering Program Review #004: Citizen-Originated Permit Milestone

Date: 2026-08-15

==================================================

BPLS-RUNTIME

Engineering Program Review #004

Overall Health: YELLOW - implementation is healthy; financial, migration, Treasury, and authority policy remain material risks

Architecture Health: HEALTHY

Current Phase: Early Implementation / First Citizen Milestone Complete

Recommendation: CONTINUE WITH NOTED RISK

==================================================

## 1. Executive Dashboard

`bpls-runtime` has completed its first citizen-originated new-permit milestone. An established citizen account now resolves to a durable legal `BusinessOwner`, creates an unnumbered draft, attaches supporting evidence, formally submits the application, and follows the same exact record after the municipality receives and processes it through assessment, Treasury collection, receipt, clearances, permit-artifact verification, and readiness for authority review.

The milestone does not cross unresolved business-policy boundaries. It does not assign an invented official application number, determine documentary sufficiency, imply that a generated permit artifact is issued or released, or perform an external or irreversible action. The terminal runner, citizen and staff browser phases, and final canonical audit all agree on the same application and downstream records.

The approved architecture remains healthy. The citizen journey reinforced the separation among account identity, legal owner identity, submission actor, municipal receipt, financial processing, generated artifact, and legal authority. Autonomous implementation should continue, but the rescue still carries high uncertainty in ordinance-complete financial rules, production migration, Treasury policy, and legal permit issuance.

## 2. Current Phase

Current phase: Early Implementation / First Citizen Milestone Complete.

- Ground Zero, Discovery, and Architecture are complete and approved.
- Staff and citizen new-permit journeys are executable through the authority boundary.
- The Board-approved citizen identity and formal-submission contract is implemented and verified.
- Assessment, Treasury, reporting, documents, authorization, audit, and browser-evidence foundations are operational.
- Renewal, amendment, transfer, and retirement have safe foundations but not complete policy parity.
- Migration, production configuration, full financial policy, legal issuance/release, and deployment readiness remain ahead.

The project is not yet in mid-implementation because the broad TOR scope, complete financial rules, migration, and production parity remain materially incomplete.

## 3. Capability Progress

Discovery identified 116 externally or contractually meaningful capabilities. The implementation ledger currently contains 49 active capability rows:

- 37 browser verified;
- 3 partially implemented;
- 9 explicitly blocked;
- 67 discovered capabilities not yet active in the implementation ledger;
- no capability yet claimed as fully production-parity verified.

These counts are navigational indicators, not weighted progress percentages.

| Area | Current State | Evidence / Remarks |
| --- | --- | --- |
| Registry | Foundational and locally verified | `User -> BusinessOwner -> Businesses` is implemented; submitter identity remains separate. Production identity reconciliation is unverified. |
| Permitting | Substantial new-permit foundation | Staff and citizen new-permit journeys reach authority review. Legal issuance, release, validity, and lifecycle variants remain incomplete. |
| Assessment | Foundational / partial | Deterministic snapshots, selected fixed/range rules, assessment UI, and PDF evidence work. Ordinance completeness and advanced rules remain high-risk. |
| Treasury | Partial with explicit seams | OTC collection, allocations, manual receipt, payment evidence, and selected reports work. Numbering, reversal, online payment, reconciliation, and broader Treasury modules remain unresolved. |
| Reporting | Foundational / partial | Daily collections, revenue-source, paid/unpaid establishment, and top-tax-due reports are browser verified. Official formats and full TOR coverage remain ahead. |
| Citizen Portal | Substantial first milestone | Identity, drafts, editing, documents, formal submission, tracking, timeline, payment detail, authority review, and artifact-only verification are demonstrated. |
| Administration | Foundational | Local roles and permissions support the implemented journeys. Production roles, officials, signatories, and configuration migration remain ahead. |
| Migration | Not started | Mapping rules are clearer, but production data and configuration have not been supplied for migration execution or reconciliation. |
| Verification | Strong | Exact-record terminal, browser, audit, document, storyboard, and parity evidence now cover both staff and citizen milestone journeys. |

## 4. Architecture Health

Architecture health: healthy.

The Laravel monolith, relational source of truth, Vue/Inertia frontend, domain actions, policy-based authorization, deterministic financial snapshots, internal reporting, document boundary, and lifecycle evidence architecture remain valid. No redesign is recommended.

Two assumptions changed constructively since Discovery:

1. Citizen ownership can no longer be inferred from application history or `submitted_by_id`. The explicit nullable user-to-`BusinessOwner` relationship now represents durable registry identity, while `submitted_by_id` remains an actor/audit fact.
2. An official application number is not required as technical identity. The application can remain unnumbered through submission and municipal processing while internal identity and carefully labelled display references keep every relation deterministic.

The milestone runner invokes the same citizen, assessment, Treasury, clearance, document, and authority-boundary actions used by the application. It has not become a second workflow engine.

## 5. Project Risks

1. **Financial correctness:** the full Revenue Code catalog, formula behavior, rounding, PIL, surcharge, interest, deficiency tax, and production fee configuration remain incomplete.
2. **Production migration:** production data is unavailable, so identity reconciliation, legacy provenance mapping, financial totals, history, documents, and exception handling are designed but not verified.
3. **Treasury policy:** official receipt numbering, void/reversal, reconciliation, online payment, and the accepted meaning of generic billing groups remain unresolved.
4. **Authority policy:** permit issuance, signatory authority, release, QR meaning, and legal effect remain intentionally blocked.
5. **Contractual breadth:** official reporting acceptance, non-permit Treasury work, notifications, and remaining TOR capabilities exceed the currently completed milestone surface.
6. **Schedule:** the rescue can lose time if unresolved policy is implemented speculatively or if production data/configuration arrives late.
7. **Production parity:** current browser evidence is local and deterministic; live role, data, configuration, scale, and edge-case parity remain unverified.

## 6. Technical Debt

Deliberate technical debt remains visible:

- `ManualCollectionReceiptVisibilityScenario` now orchestrates both its original Treasury scenario and the composed new-permit milestones.
  - Reason: reuse proven orchestration without introducing another workflow implementation.
  - Impact: the class name and size are narrower than its current responsibility.
  - Cleanup: extract a neutrally named composition service only when a second materially different milestone requires it.

- Unnumbered applications use explicit internal-record display fallbacks such as `Application #68` or `Application record #68`.
  - Reason: official numbering authority and format are unresolved.
  - Impact: local/staff surfaces remain usable, but the fallback is not a municipal reference.
  - Cleanup: replace only the presentation reference after numbering policy is accepted; preserve internal identity.

- Lifecycle evidence is stored locally under `storage/app/private/**` and indexed manually in committed milestone documentation.
  - Reason: this is sufficient and recoverable for the rescue stage.
  - Impact: artifact retention and cross-environment review are not automated.
  - Cleanup: define retention and durable artifact storage during deployment preparation.

- Project-wide ESLint still reports 16 known errors in untouched payment, permit, receipt, and storyboard pages.
  - Reason: milestone changes were kept scoped; changed frontend files pass targeted linting.
  - Impact: the repository-wide lint gate is not yet clean.
  - Cleanup: resolve as a bounded quality slice before stabilization.

No hidden financial, numbering, issuance, or external-integration behavior was accepted as technical debt.

## 7. Major Discoveries

- A submitted citizen application can remain unnumbered and still flow deterministically through municipal processing. Official numbering is presentation/business authority, not persistence identity.
- Treasury and reporting surfaces contained an implicit assumption that every application had an official number. Supporting nullable numbers required explicit labels, not fabricated values.
- The same canonical timeline and financial records can support citizen and staff views while authorization removes staff-only actions and sensitive actor detail.
- Citizen submission and municipal receipt can occur atomically while remaining separate recorded business facts.
- Composing the citizen milestone required orchestration only. No alternate permit, assessment, payment, clearance, or release logic was necessary.
- The authority boundary remains stable even after every technically observable prerequisite is satisfied: artifact generation still does not imply issuance, release, or legal effect.

## 8. Evolution

What became simpler:

- Citizen account identity, legal business-owner identity, and submission actor are no longer overloaded into one relationship.
- Draft, submitted, municipally received, assessed, paid, clearance-complete, and ready-for-authority-review facts are explicit without introducing a generalized lifecycle framework.
- Official application numbering is no longer coupled to database relationships, reports, or scenario idempotency.
- Citizen and staff journeys share one domain path; their differences are authorization and presentation, not competing workflow implementations.
- The milestone evidence package now replaces a collection of isolated claims with one exact, reviewable business journey.

## 9. Current Slice Summary

Two completed slices moved the program from EPR #003 to this milestone:

1. **Citizen Registry Identity And Formal Submission** implemented the Board-approved `User -> BusinessOwner -> Businesses` relationship, corrected draft timestamp/number semantics, and recorded citizen submission and municipal receipt as distinct facts.
2. **Citizen-Originated New Permit Lifecycle To Authority Boundary** composed the exact submitted record through assessment, Treasury, receipt, clearances, artifact verification, and authority review in terminal, citizen browser, staff browser, and audit phases.

The milestone is committed as `87fc101`. It matters because the application now demonstrates continuity between the citizen portal and municipal operations without duplicating business behavior.

## 10. Evidence Produced

Business journey:

- Milestone index: `docs/implementation/MILESTONE_SCENARIOS.md`, MS-002.
- Parity evidence: `docs/implementation/PARITY_LEDGER.md`, especially CAP-010, CAP-112, and CAP-117.
- Scenario key: `citizen_new_permit_lifecycle_authority_boundary`.
- Run ID: `citizen-new-permit-20260815-001`.

Authoritative local resources:

- permit application #68, with no official application number;
- assessment #54;
- payment schedule #51;
- collection #33;
- receipt #33;
- public artifact verification reference `PVA-68-8d01ccfe76f898ed`.

Artifact location:

`storage/app/private/lifecycle-scenarios/citizen_new_permit_lifecycle_authority_boundary/citizen-new-permit-20260815-001`

Curated evidence:

- `manifest.json` and `summary.html`;
- `terminal/prepare.json`, `terminal/execution.json`, and `terminal/audit.json`;
- `browser/report.json`, with 124 checks, zero failed checks, zero JavaScript errors, and zero failed application requests;
- citizen and staff desktop/mobile screenshots under `browser/screenshots/`;
- `storyboard/storyboard.html` and `storyboard/storyboard.json`;
- reviewer placeholder `review.md`.

Verification results:

- focused backend: 90 tests, 940 assertions, pass;
- full backend: 255 tests, 2,608 assertions, pass;
- Pint: pass;
- PHPStan: pass;
- TypeScript: pass;
- changed-file ESLint: pass;
- production frontend build: pass;
- external integrations: none;
- irreversible actions: none.

## 11. Recommendation

CONTINUE WITH NOTED RISK

Autonomous implementation should continue. The architecture and engineering method are producing deterministic, reviewable business evidence. The noted risks are significant but are explicit and can be reduced through bounded vertical slices without redesign.

## 12. Next Vertical Slice

Recommended next slice: **Citizen Existing-Business Reuse And Registry-Safety Evidence**.

Why it is next:

- it completes browser evidence for CAP-111, the remaining partial citizen registry capability;
- it directly validates the Board-approved durable identity contract against an existing owned business;
- it proves cross-owner selection is rejected and application editing cannot mutate shared legal registry facts;
- it reduces authorization and future migration risk before broader citizen lifecycle variants are added.

After that bounded slice, the next program priority should move to an ordinance-backed assessment expansion that exercises representative new-permit tax and fee rules end to end, because financial correctness remains the highest project risk.

## 13. Coffee with Arti

When a citizen formally submits an application, should the municipality expose an immediate non-official tracking reference until BPLO assigns the official application number, or does municipal receipt itself authorize official numbering?

The software no longer needs an official number for identity, processing, or evidence. The remaining question is what reference the Municipality intends citizens and staff to use operationally between submission and any later receiving/numbering authority.

## 14. Constitution Check

- Evidence before design: compliant.
- Design before implementation: compliant.
- Production treated as evidence, not a playground: compliant.
- Unknown policy remains explicit: compliant.
- Laravel-native direction preserved: compliant.
- No premature GNE abstraction: compliant.
- Observable parity remains primary: compliant.
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
- `User -> BusinessOwner -> Businesses` is the current citizen registry identity contract.
- `submitted_by_id` is an actor/audit fact, not legal ownership.
- Citizen drafts are unsubmitted, unnumbered, and have `submitted_at=null`.
- Citizen submission and municipal receipt are distinct business facts that may occur atomically.
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

I would make official application identifiers nullable across every initial read model and report rather than discovering presentation assumptions when the citizen milestone reached Treasury. Internal identity and municipal numbering are different concerns, and treating them separately from the beginning would have reduced small downstream corrections.

I would also establish the citizen-to-owner relationship before the first citizen draft slice. The temporary submitter-based ownership model was safe and reversible, but the durable registry contract now makes authorization, migration exceptions, and existing-business reuse substantially clearer.

## 17. Confidence Index

This measures confidence in sufficient understanding, not implementation progress.

| Area | Confidence | Remarks |
| --- | --- | --- |
| Architecture | [#########-] 92% | The approved shape is reinforced by two composed milestone journeys. |
| Registry | [#######---] 72% | Citizen-to-owner identity is explicit; production reconciliation and delegation remain unknown. |
| Permitting | [########--] 78% | New permit is proven from citizen draft through authority review; legal issuance and lifecycle variants remain incomplete. |
| Assessment | [######----] 58% | Snapshot behavior is stable; ordinance breadth and advanced financial policy remain high-risk. |
| Treasury | [#####-----] 47% | OTC and receipt foundations work; policy-heavy and non-permit scope remain uncertain. |
| Reporting | [#####-----] 48% | Several relational reports work; official acceptance and broader TOR coverage remain ahead. |
| Citizen Portal | [########--] 82% | The first complete citizen milestone is verified; correction, notifications, downloads, and production parity remain. |
| Migration | [#---------] 12% | Mapping semantics improved, but production data remains unavailable. |
| Verification | [#########-] 94% | Exact-record terminal, dual-role browser, audit, document, and milestone evidence agree. |
| Overall Rescue | [#######---] 71% | Understanding is increasing and major unknowns remain explicit rather than encoded as assumptions. |
