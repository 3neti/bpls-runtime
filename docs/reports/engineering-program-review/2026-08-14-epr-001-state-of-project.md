# Engineering Program Review #001: State of the Project

Date: 2026-08-14

==================================================

BPLS-RUNTIME

Engineering Program Review #001

Overall Health: YELLOW - disciplined progress, high unresolved business risk

Architecture Health: HEALTHY WITH ADJUSTMENTS

Current Phase: Early Implementation

Recommendation: CONTINUE WITH NOTED RISK

==================================================

## 1. Executive Dashboard

`bpls-runtime` has moved from evidence recovery into Early Implementation. Ground Zero, Discovery, and Architecture are complete and approved. The project is now implementing Laravel vertical slices under the approved rescue direction: preserve observable BPLS behavior and business meaning while replacing accidental legacy architecture.

The Laravel application is not yet a complete replacement for the Municipality of Ipil BPLS. It has, however, proven several important foundations: staff-side permit intake, deterministic assessment snapshots, payment schedule preparation, over-the-counter collection recording, manual receipt issuance, receipt detail/PDF evidence, preliminary permit/application/assessment documents, and executable Storyboard/lifecycle verification.

The project remains directionally healthy. The main reason for caution is not architecture failure; it is unresolved business risk. The highest-risk areas are still ahead: full Revenue Code fee extraction, formula semantics, PIL, surcharge/interest policy, retirement/transfer behavior, online payment/reconciliation, receipt-numbering authority, reporting parity, migration, and production data/configuration reconciliation.

## 2. Current Phase

Current phase: Early Implementation.

Why:

- Ground Zero evidence has been persisted and registered.
- Discovery produced the capability ledger, surface inventory, lifecycle map, contradiction/gap register, and synthesis report.
- Architecture was approved as `READY FOR IMPLEMENTATION WITH CONDITIONS`.
- Implementation has begun through vertical slices that cross domain behavior, persistence, authorization, Vue/Inertia UI, tests, documents, browser verification, and parity tracking.
- The project is not yet in Mid Implementation because large BPLS areas remain incomplete or unverified: full permit lifecycle, citizen portal, clearance completion, permit issuance, Treasury modules, reporting parity, migration, production configuration, and deployment readiness.

## 3. Capability Progress

Discovery identified 116 externally or contractually meaningful capabilities. The Laravel parity ledger currently tracks 16 capabilities that have been implemented, tested, browser verified, blocked, or explicitly called out as critical. Raw percentages are useful only as a guardrail: about 7% of discovered capabilities are browser verified, and about 13% have been touched or partially represented.

The weighted business-area view is more meaningful:

| Area | Current State | Notes |
| --- | --- | --- |
| Registry | Foundational / partial | Staff-side owner, business, and line-of-business intake are in place for the first permit slice. Full registry parity and citizen-owned business flows remain ahead. |
| Permitting | Foundational / partial | Staff new-application intake exists. Renewal, amendment, transfer, retirement, clearances, release, and issuance remain incomplete or unresolved. |
| Assessment | Foundational / partial | Deterministic assessment snapshots, constant fees, and range fees exist. Formula semantics, full ordinance catalog, PIL, surcharge/interest, and rounding remain high-risk. |
| Treasury | Partial with explicit seams | Payment schedule preparation, OTC collection recording, manual receipt issuance, and receipt detail exist. Online payment, reconciliation, official numbering, voids, and broader Treasury modules remain unresolved. |
| Reporting | Mostly untouched in Laravel | Discovery is strong, but Laravel report parity is not yet implemented beyond document/artifact foundations. |
| Citizen Portal | Mostly untouched in Laravel | Source evidence exists, but current implementation has focused on staff-side rescue foundations. |
| Administration | Foundational | Roles/permissions are used and seeded for local/testing; full production role migration and admin parity remain ahead. |
| Migration | Not started | Production data/configuration export is still required before migration parity can be proven. |
| Verification | Strong foundation | Browser verification, parity ledger discipline, document artifacts, and lifecycle scenario artifacts are now part of the implementation rhythm. |

The Storyboard and Lifecycle Scenario Runner are not counted as BPLS business capabilities. They are verification and communication infrastructure.

## 4. Architecture Health

Architecture health: healthy with adjustments.

The approved architecture remains valid:

- A single Laravel monolith remains the right rescue shape.
- Vue/Inertia is working for staff-facing slices.
- Relational persistence is fitting the recovered domain better than a mechanical Convex reproduction.
- Application actions/services are proving useful as execution boundaries.
- Server-side authorization is being enforced through policies and permissions.
- Financial behavior is being held behind deterministic snapshots and explicit policy gaps.
- PDF/document rendering is behind dedicated actions rather than scattered through controllers or Vue pages.

Assumptions that have sharpened since Discovery:

- Manual receipt capture can safely proceed as an interim Treasury boundary while automatic official numbering remains unresolved.
- Generated documents can be introduced early as deterministic evidence, but they must not imply issuance or release unless the domain lifecycle actually performed that action.
- Executable acceptance evidence is not optional. Browser verification and artifact generation should accompany meaningful vertical slices.
- Storyboards and lifecycle scenarios need governance: they describe and verify domain behavior; they must never become a second workflow engine.

No redesign is recommended.

## 5. Project Risks

1. Financial and ordinance correctness.

   The highest risk remains tax, fee, surcharge, interest, exemption, PIL, rounding, and complete Revenue Code catalog parity.

2. Treasury and receipt policy.

   Manual receipt issuance exists, but official receipt-numbering authority, void/reversal rules, online payment reconciliation, and collection audit rules remain unresolved.

3. Production data and configuration availability.

   Full parity cannot be proven without production configuration and data export. Fee schedules, roles, reports, documents, and migration mappings all depend on production evidence.

4. Permit lifecycle semantics.

   Legacy `Released` semantics, clearance timing, actual permit issuance, renewal, retirement, transfer, amendment, and PIL handling still require reconciliation.

5. Reporting parity.

   Discovery showed a separate ad hoc reporting surface and many statutory reports. Laravel report parity and performance have not yet been proven.

6. Migration and cutover.

   No production data migration has been executed. Legacy identifiers, document provenance, financial snapshots, and audit trails must be preserved deterministically.

7. Schedule pressure.

   The three-week rescue horizon increases the temptation to hardcode uncertain policy. Current implementation has avoided that so far, but pressure will rise.

8. Browser parity and visual continuity.

   Browser verification exists, but broad visual parity across staff, citizen, reporting, and document surfaces remains mostly ahead.

## 6. Technical Debt

Deliberate technical debt accepted so far:

- Staff-side permit intake is narrower than full TOR/legacy field parity.
  - Reason: prove intake-to-assessment quickly.
  - Impact: citizen intake, renewal-specific fields, documentary requirements, and full regulatory fields remain incomplete.
  - Cleanup: expand from field-level discovery and parity ledger entries.

- Payment schedule support is intentionally narrow.
  - Reason: avoid inventing annual/semiannual/quarterly due-date and allocation policy.
  - Impact: current schedule path proves the boundary, not complete Treasury behavior.
  - Cleanup: implement characterized schedule policies after ordinance and production configuration reconciliation.

- Manual receipt issuance exists while automatic numbering is unresolved.
  - Reason: continue collection/receipt progress without fabricating numbering authority.
  - Impact: not complete Treasury parity.
  - Cleanup: implement official numbering only after owner/production rule decision.

- Preliminary document artifacts exist before final municipal layout/signatory parity.
  - Reason: establish deterministic document-generation boundaries and evidence flow.
  - Impact: documents are useful for testing and review, not final official parity.
  - Cleanup: apply Ipil branding, seals, signatories, QR verification, and exact layouts after document discovery.

- Playwright was introduced as a dev dependency.
  - Reason: the repository had no existing browser runner, and real browser verification is required.
  - Impact: additional development dependency and browser runtime assumption.
  - Cleanup: standardize lifecycle/browser execution scripts and CI/runtime requirements.

No hidden business-rule debt has been intentionally accepted. Unknown policy is currently blocked or represented as an explicit seam.

## 7. Major Discoveries

Implementation since Discovery has clarified these project-level points:

- Laravel-native vertical slices are viable. Permit intake, assessment, collection, receipt, documents, and verification can proceed without preserving Next.js, Convex, ClickHouse, Airbyte, or Vercel topology.

- Manual receipt capture is a practical interim Treasury boundary. It allows evidence preservation without inventing official receipt-number policy.

- Documents should be evidence-bound. Rendering a permit, assessment, application, or receipt artifact must not imply that a lifecycle action occurred unless the domain actually executed it.

- Executable review artifacts are valuable beyond engineering. Manifests, screenshots, PDFs, HTML summaries, and audit reports make the rescue easier to review by the Board, project owner, municipal users, and future agents.

- Storyboards are durable business assets only if they stay business-oriented. They should describe journeys in municipal language and execute domain behavior through the scenario runner.

## 8. Evolution

What became simpler:

- Infrastructure: the target remains one Laravel application with relational data, queues, storage, documents, and browser verification. No legacy infrastructure is being retained merely for parity.

- Financial behavior: one authoritative calculation path is being protected. Unsupported formula semantics fail explicitly instead of being guessed.

- Receipt behavior: collection, manual receipt issuance, and unresolved automatic numbering are now separate concepts.

- Documents: generated artifacts are renderers over authoritative state, not workflow engines.

- Acceptance: future progress can be judged by domain state, browser state, generated artifacts, and parity ledger updates rather than subjective claims.

- Verification: Storyboard, lifecycle runner, browser evidence, and audit reports now form one downstream chain instead of separate manual review habits.

## 9. Slice Summary

Implementation completed so far includes staff permit intake, assessment snapshotting, payment schedule preparation, OTC collection, manual receipt issuance, receipt detail, preliminary PDF artifacts, and a Storyboard/Lifecycle Scenario Runner.

The latest slice matters because it established the verification pattern:

`Business Story -> Executable Scenario -> Browser Verification -> Audit Evidence`

This is supporting infrastructure. It is not a second workflow engine.

Latest committed implementation:

`c8a9d2c Add storyboard lifecycle evidence runner`

First executable scenario artifact root:

`storage/app/private/lifecycle-scenarios/storyboard_terminal_state_visibility/storyboard-terminal-20260814-001`

## 10. Recommendation

CONTINUE WITH NOTED RISK

Autonomous implementation should continue.

The architecture is healthy enough to proceed, and the implementation discipline is surfacing uncertainty rather than hiding it. The noted risk is that the next domains are more business-critical than the verification infrastructure just completed. Financial, Treasury, migration, reporting, and permit lifecycle questions must continue to trigger explicit seams or Board review when they block correctness.

## 11. Next Vertical Slice

Recommended next slice: staff-facing permit application terminal status visibility.

Why it is next:

- It builds directly on implemented intake and assessment foundations.
- It starts turning the lifecycle from disconnected screens into an auditable business progression.
- It is meaningful to users and safe to verify locally.
- It gives the Storyboard/Lifecycle Scenario Runner a real BPLS domain action to execute.
- It exposes status semantics before more dangerous payment, release, and issuance complexity grows.

Expected architectural value:

- Prove that domain actions, policies, UI state, audit evidence, browser verification, and parity ledger updates can move together for a real BPLS capability.
- Reduce risk before receipt voiding, online reconciliation, clearance completion, or permit issuance.

## 12. Constitution Check

- Evidence before design: compliant.
- Design before implementation: compliant.
- Production treated as evidence, not a playground: compliant.
- Unknown policy remains explicit: compliant.
- Laravel-native direction preserved: compliant.
- No premature GNE abstraction: compliant.
- Observable parity remains the primary goal: compliant.
- Storyboards remain verification and communication artifacts: compliant, requires continued attention.
- Domain remains the source of business truth: compliant.

## 13. Standing Board Decisions

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
- The Lifecycle Scenario Runner is not a workflow engine.
- Production is evidence, not a playground.
- Billing Groups remain provisional as the likely Treasury collection mechanism pending acceptance.
- Unknown business policy remains an explicit seam, not invented behavior.
- GNE concepts must not enter the Ipil rescue implementation prematurely.

## 14. If I Were Starting Today

If I were beginning this rescue today with the current knowledge, I would still start with Ground Zero and Discovery. That sequence was correct.

The adjustment I would make is to introduce the parity ledger and executable evidence pattern slightly earlier in implementation. The project benefits from having every slice tied to a business capability, browser evidence, document evidence, and an audit trail from the beginning. That makes progress more measurable for stakeholders and safer for future engineers.

I would also continue resisting complete Treasury implementation until receipt numbering, reconciliation, and production configuration are clarified. The project is moving faster because uncertain policy is being made visible instead of being guessed.

## 15. Coffee with Arti

Should executable Storyboards eventually become municipality-facing acceptance artifacts that officials can review and sign off on, or should they remain primarily internal engineering evidence?

This matters because if Storyboards become acceptance artifacts, their language, screenshots, lifecycle summaries, and review workflow should be shaped for municipal users from the beginning. If they remain internal, they can stay more compact and engineering-oriented.

