# Engineering Program Review #002: First Milestone Scenario

Date: 2026-08-14

==================================================

BPLS-RUNTIME

Engineering Program Review #002

Overall Health: YELLOW - first milestone evidence package complete, high policy risk remains

Architecture Health: HEALTHY

Current Phase: Early Implementation / First Milestone Verification

Recommendation: CONTINUE WITH NOTED RISK

==================================================

## 1. Executive Dashboard

`bpls-runtime` has reached its first true implementation milestone: a unified new-permit lifecycle scenario now executes through real Laravel domain actions, persists authoritative records, opens the exact prepared records in a real browser, compares browser evidence with canonical state, and produces a reviewable milestone evidence package.

This does not mean the Ipil BPLS replacement is complete. It means the rescue method is now proven beyond isolated vertical slices. The project can compose implemented capabilities into a larger business journey and can show the Board what evidence supports that claim.

The architecture remains healthy. The implementation is reinforcing the approved direction: a single Laravel application, Vue/Inertia staff surfaces, relational persistence, deterministic financial snapshots, explicit policy boundaries, generated document artifacts, browser evidence, and parity tracking. The highest risks remain business-policy and production-evidence risks, not architecture failure.

## 2. Current Phase

Current phase: Early Implementation / First Milestone Verification.

Why:

- Ground Zero, Discovery, and Architecture are complete and approved.
- Implementation has moved past isolated slices into a composed milestone scenario.
- The first milestone scenario covers intake, assessment, payment schedule, OTC collection, receipt issuance, clearance completion, permit artifact generation, public artifact verification, and the authority boundary.
- The project is not yet in Mid Implementation because major lifecycle areas remain unimplemented or unresolved: renewal, citizen portal, final permit issuance/release, online payment, reconciliation, complete Revenue Code catalog, reporting, migration, production configuration, and deployment readiness.

## 3. Capability Progress

Discovery identified 116 externally or contractually meaningful capabilities. The implementation parity ledger currently tracks 23 capabilities with active Laravel status, evidence, gaps, or explicit blocking conditions.

The weighted business-area picture is:

| Area | Current State | Evidence / Remarks |
| --- | --- | --- |
| Registry | Foundational / partial | Staff-side owner, business, and line-of-business intake support the first new-permit path. Full registry parity and citizen-owned flows remain ahead. |
| Permitting | Foundational with first milestone | New permit lifecycle is browser verified through authority boundary. Issuance/release remains blocked by policy. Renewal, amendment, transfer, and retirement remain unresolved. |
| Assessment | Foundational / partial | Constant and range-based assessment snapshots are implemented and browser verified. Formula semantics, full ordinance catalog, PIL, surcharge/interest, and rounding remain high-risk. |
| Treasury | Partial with explicit policy seams | OTC collection and manual receipt issuance are browser verified. Receipt void/reversal, automatic numbering, online payment, and reconciliation remain blocked or not started. |
| Reporting | Mostly untouched | Operational document artifacts exist, but statutory/reporting parity is still ahead. |
| Citizen Portal | Mostly untouched | Current verified progress is staff-side. Citizen-facing application and tracking remain future slices. |
| Administration | Foundational | Local/test roles and authorization are used. Production role and permission migration remain ahead. |
| Migration | Not started | Production data/configuration is still required before migration parity can be proven. |
| Verification | Strong and improving | Milestone scenario, lifecycle evidence package, browser screenshots, audit report, storyboard, and parity ledger now form a navigable evidence chain. |

## 4. Architecture Health

Architecture health: healthy.

The approved architecture remains valid:

- A single Laravel monolith is still the correct rescue shape.
- Vue/Inertia continues to support practical staff workflows.
- Relational persistence is supporting the recovered domain without reproducing Convex topology.
- Domain actions/services remain the execution boundary for business behavior.
- Storyboards and lifecycle scenarios are verification infrastructure, not workflow engines.
- Generated documents render authoritative state and do not perform lifecycle transitions.
- The authority boundary is explicit and testable.
- Financial behavior is still protected by deterministic persisted snapshots and explicit unsupported-policy failures.

Architecture assumptions that have sharpened since EPR #001:

- Milestone scenarios are the natural next layer above vertical slices.
- Engineering Program Reviews are becoming evidence navigation artifacts, not just status reports.
- The Golden Path should remain emergent. It now has a placeholder, but not an executable specification.
- Permit artifact, permit issuance, permit release, and legal effect must remain distinct until policy is accepted.

No redesign is recommended.

## 5. Project Risks

1. Financial and ordinance correctness.

   The full Revenue Code catalog, formula semantics, rounding, surcharge, interest, PIL, exemption, and renewal tax rules remain the highest correctness risk.

2. Treasury policy and reconciliation.

   Manual receipt issuance is proven, but official numbering authority, void/reversal rules, online payment, reconciliation, and broader Treasury behavior remain unresolved.

3. Permit legal authority.

   The system can prove readiness for authority review, but permit issuance, release, signatory authority, QR meaning, and legal effect remain policy boundaries.

4. Production data and configuration availability.

   Migration, fee catalog parity, roles, clearances, reports, and document layout cannot be fully proven without production data and configuration evidence.

5. Reporting parity.

   The legacy system includes reporting surfaces beyond the current Laravel implementation. Reports remain a major future domain.

6. Citizen-facing parity.

   The current milestone is staff-side. Citizen registration, application submission, tracking, and public-facing flows remain largely unimplemented.

7. Schedule pressure.

   The project is moving quickly, but the remaining domains are policy-heavy. The main schedule danger is implementing assumptions that later need reversal.

8. Browser parity breadth.

   Browser verification is now strong for the milestone path, but broad UI parity across all roles and surfaces remains ahead.

## 6. Technical Debt

Deliberate technical debt accepted so far:

- Staff-side permit intake remains narrower than full legacy/TOR field parity.
  - Reason: prove intake-to-lifecycle progression quickly.
  - Impact: citizen intake, documentary requirements, renewal-specific data, and production field parity remain incomplete.
  - Cleanup: expand fields from parity ledger and production evidence.

- Payment schedule behavior is intentionally narrow.
  - Reason: avoid inventing installment, due-date, surcharge, and allocation policy.
  - Impact: current schedule path proves the lifecycle boundary, not complete Treasury parity.
  - Cleanup: implement characterized payment policy after ordinance and production configuration reconciliation.

- Manual receipt issuance precedes automatic receipt numbering.
  - Reason: continue Treasury evidence without fabricating numbering authority.
  - Impact: manual receipt flow is useful but not complete Treasury policy.
  - Cleanup: implement official numbering only after authority and production rules are accepted.

- Document artifacts are not final municipal forms.
  - Reason: establish deterministic document generation and evidence boundaries early.
  - Impact: current PDFs support review and verification, not final official layout parity.
  - Cleanup: apply final municipal branding, seals, signatories, QR semantics, and layouts after policy acceptance.

- Milestone evidence artifacts are generated in local storage and indexed in documentation.
  - Reason: artifacts are large/run-specific and should not be committed wholesale.
  - Impact: reviewers need local artifact access for full inspection.
  - Cleanup: later standardize evidence packaging/export when stakeholder demonstrations require portability.

No known hidden financial or issuance-policy debt has been accepted. Unknown policy remains explicit.

## 7. Major Discoveries

- Completed slices now compose into a meaningful business lifecycle. The project has moved from "can this screen work?" to "can the system perform and prove this business journey?"

- The authority boundary is not an implementation inconvenience. It is a core government workflow concept: software can prove readiness, but human/legal authority decides issuance and release.

- Generated permit artifacts have value before legal issuance if their meaning is explicit. They can support review without claiming the permit is issued, released, valid, or legally effective.

- Evidence packages are becoming the project's navigation layer. The Board can now inspect scenario manifests, browser reports, screenshots, audit reports, generated documents, storyboards, and parity updates instead of relying only on implementation summaries.

- The Golden Path is best treated as emergent. The placeholder now points to the destination without forcing unaccepted business policy into software.

## 8. Evolution

What became simpler:

- The permit lifecycle is no longer a set of disconnected slices. It now has one milestone scenario through the authority boundary.

- "Permit release" has been decomposed into clearer concepts: artifact generation, readiness for authority review, issuance, release, and legal effect.

- Storyboards, lifecycle runner, browser evidence, and audits now form one verification chain.

- Engineering reports can point to curated evidence instead of repeating artifact contents.

- The Golden Path no longer needs to be designed up front. It can emerge from milestone scenarios.

- Unknown policy is easier to manage because it is represented as visible boundaries rather than hidden assumptions.

## 9. Slice Summary

The completed milestone slice is `MS-001: Unified New Permit Lifecycle To Authority Boundary`.

It executes:

1. new permit application intake;
2. assessment computation;
3. payment schedule preparation;
4. full OTC collection;
5. manual receipt issuance;
6. receipt void boundary check;
7. clearance completion;
8. permit artifact generation;
9. public artifact verification;
10. permit release boundary check.

It matters because it proves the application can perform and verify a business journey across permit, assessment, Treasury, document, browser, and audit surfaces without collapsing unresolved legal authority into software behavior.

## 10. Evidence Produced

Business Journey:

- Milestone index: `docs/implementation/MILESTONE_SCENARIOS.md`
- Golden Path placeholder: `storyboards/GOLDEN_PATH.storyboard`
- Scenario key: `new_permit_lifecycle_authority_boundary`
- Run ID: `new-permit-lifecycle-20260814-001`

Generated Documents:

- Application form PDF evidence: referenced from `browser/report.json`
- Assessment sheet PDF evidence: referenced from `browser/report.json`
- Mayor's Permit artifact evidence: referenced from `browser/report.json`

Browser Evidence:

- Browser report: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001/browser/report.json`
- Screenshots:
  - `browser/screenshots/01-payment-schedule-queue.png`
  - `browser/screenshots/02-receipt-queue.png`
  - `browser/screenshots/01-payment-schedule.png`
  - `browser/screenshots/02-receipt-detail.png`
  - `browser/screenshots/03-permit-release-boundary.png`
  - `browser/screenshots/04-permit-verification-boundary.png`
  - `browser/screenshots/05-mobile-receipt.png`

Audit Evidence:

- Manifest: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001/manifest.json`
- Terminal audit: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001/terminal/audit.json`
- Browser failed requests: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001/browser/failed-requests.json`
- Browser console errors: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001/browser/console-errors.json`

Review Notes:

- Summary: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001/summary.html`
- Review note: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001/review.md`
- Storyboard: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001/storyboard/storyboard.html`

Artifact Location:

`storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001`

## 11. Recommendation

CONTINUE WITH NOTED RISK

Autonomous implementation should continue.

The first milestone scenario proves the engineering method is working. The noted risk is that the next areas contain unresolved business authority and financial policy. Implementation should continue only by preserving explicit seams where policy remains unknown.

## 12. Next Vertical Slice

Recommended next slice: renewal permit lifecycle foundation.

Why it is next:

- Renewal is a core BPLS lifecycle, not an edge case.
- It introduces the next major financial-risk surface: gross receipts from prior period, renewal-specific tax basis, late-payment behavior, surcharge/interest, and possibly deficiency/PIL questions.
- It will test whether the new-permit milestone architecture generalizes to a second major permit lifecycle without becoming generic or speculative.
- It will expose whether current assessment, payment, receipt, document, and scenario boundaries remain healthy under a different application type.

Expected architectural value:

- Validate that the architecture supports multiple permit lifecycle types while keeping policy differences explicit.
- Surface ordinance and production-data dependencies early, before migration and reporting work harden around incomplete assumptions.

## 13. Coffee with Arti

Should the rescue treat "permit validity" as a separate lifecycle statement from "permit release" in the long term?

This matters because a released permit may later become suspended, expired, revoked, retired, amended, or superseded. The current authority boundary already separates artifact, issuance, release, and legal effect. The product question is whether future BPLS grammar should make validity its own durable concept rather than treating it as a derived label on a permit record.

## 14. Constitution Check

- Evidence before design: compliant.
- Design before implementation: compliant.
- Production treated as evidence, not a playground: compliant.
- Unknown policy remains explicit: compliant.
- Laravel-native direction preserved: compliant.
- No premature GNE abstraction: compliant.
- Observable parity remains the primary goal: compliant.
- Storyboards remain verification and communication artifacts: compliant.
- Lifecycle runner remains verification infrastructure, not a workflow engine: compliant.
- Domain remains the source of business truth: compliant.
- Reports point to curated evidence instead of becoming artifact dumps: compliant.

## 15. Standing Board Decisions

- Target deployment direction is Laravel Cloud.
- Replacement shape is a single Laravel 13 application.
- Vue/Inertia is the frontend path.
- Relational database is the application source of truth.
- Convex storage topology will not be mechanically reproduced.
- ClickHouse will not be retained merely for reports.
- Airbyte will not be retained merely for legacy topology.
- Vercel will not remain the application deployment platform.
- Reporting should live inside Laravel unless measured requirements prove otherwise.
- Storyboards are verification and communication infrastructure.
- Lifecycle Scenario Runner is not a workflow engine.
- Production is evidence, not a playground.
- Billing Groups remain provisional as the likely Treasury collection mechanism pending acceptance.
- Unknown business policy remains an explicit seam, not invented behavior.
- Permit artifact, issuance, release, and legal effect remain distinct.
- The Golden Path is emergent, not the starting implementation specification.
- GNE concepts must not enter the Ipil rescue implementation prematurely.

## 16. If I Were Starting Today

If I were beginning today, I would still start with Ground Zero, Discovery, and Architecture. That sequence prevented the project from copying legacy infrastructure or inventing policy.

The adjustment I would make is to introduce milestone-scenario thinking immediately after the first two vertical slices. The project benefits from knowing early that slices should eventually compose into evidence-backed business journeys.

I would also keep the Golden Path as a placeholder rather than a specification. The current evidence shows that forcing the full permit lifecycle too early would have collapsed unresolved authority and Treasury questions into code.

## 17. Confidence Index

This is a confidence indicator, not a progress indicator. It measures how well each area is understood well enough to complete correctly.

| Area | Confidence | Remarks |
| --- | --- | --- |
| Architecture | [#########-] 90% | Approved direction remains reinforced by implementation. |
| Registry | [#######---] 70% | Staff-side basics are clear; full production/citizen parity remains ahead. |
| Permitting | [######----] 60% | New-permit lifecycle is verified through authority boundary; issuance/release/renewal remain unresolved. |
| Assessment | [#####-----] 50% | Snapshot structure works; full ordinance catalog and formula/rounding policy remain high-risk. |
| Treasury | [####------] 40% | OTC/manual receipt path is proven; numbering, voids, online payment, and reconciliation remain uncertain. |
| Reporting | [##--------] 20% | Discovery is strong, Laravel implementation is still mostly ahead. |
| Citizen Portal | [##--------] 20% | Source evidence exists, implementation parity is mostly untouched. |
| Migration | [#---------] 10% | Architecture has migration seams, but production data/configuration is not yet available. |
| Verification | [########--] 80% | Milestone evidence method is now proven locally with browser/audit artifacts. |
| Overall Rescue | [######----] 60% | Confidence is rising because uncertainty is being isolated instead of hidden. |
