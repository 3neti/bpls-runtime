# Migration Engineer Handoff

Role: **Migration Engineer**

Reports to: **Chief Architect and Integrator**

## Mission

Move the legacy estate toward faithful, reversible, observable migration while preserving certainty as certainty and uncertainty as uncertainty.

You do not make historical data clean. You preserve exact facts, quarantine unresolved semantics, and prove migration machinery through planning, audit, rollback, and restoration.

## Current Frontier

The exact historical-evidence class is complete:

- 407 applications;
- 696 schedules;
- 3,007 fee lines;
- 660 completed payments;
- 36 unpaid schedules;
- 412,770,810 scheduled centavos;
- 397,445,008 paid centavos;
- execute, source-to-target audit, rollback, and restoration audit all passed;
- zero operational financial mutation.

Stop scaling that class. The next major frontier is the 736 human-identity applications.

The current read-only characterization found:

- 708 unique owner proposals;
- 727 unique business proposals;
- 40 evidence shapes;
- 469 owner-collision groups;
- 80 business-collision groups;
- nine applications with additional deletion, override, or lifecycle semantics;
- a bounded 12-application subclass with nine unique collision-free owner proposals but unresolved business collisions.

Similarity has not established or merged any identity.

## First Assigned Packet

**Human Identity Frontier: Deterministic Subclass Characterization**

Objective:

Identify bounded subclasses in the 736-application population where exact source evidence can advance one semantic dependency without accepting a different unresolved dependency.

Begin with the 12-application subclass only if the private checksum-bound evidence reproduces the committed aggregate characterization. Determine whether the nine collision-free owner proposals can be prepared as exact owner-mapping proposals while business and application mappings remain unaccepted.

This packet is characterization and proposal preparation. It is not mapping acceptance or execution.

## Required Questions

1. What exact source facts establish each proposed owner identity?
2. Is each owner proposal free from source-ID collision, contradictory legal identity, deletion, blacklist, or Group-owner semantics?
3. Can owner mapping certainty be represented independently from unresolved business collisions?
4. Which business and application dependencies remain unresolved after that separation?
5. Does any record require similarity, name, contact, amount, adjacency, or heuristic matching?
6. What checksum-bound cohort and evidence-shape fingerprints reproduce the result?
7. Does the existing mapping architecture already represent this separation without modification?

## Allowed Work

- read-only replay against the immutable private production snapshot;
- deterministic source-ID and hierarchy traversal;
- aggregate and hashed characterization;
- proposal generation with explicit evidence states;
- focused migration planner, classifier, audit, and artifact tests;
- synthetic rehearsals when no production-derived execution occurs;
- performance improvements that preserve canonical projection, hash, and audit assertions exactly.

## Prohibited Work

- accepting owner, business, application, fee, location, or classification mappings without assigned authority;
- merging identities from similarity;
- inventing owners, businesses, applications, parents, receipts, permits, dates, or fee identity;
- recalculating historical liability;
- changing the operational assessment executor;
- materializing historical `Released` as current release authority;
- production-derived preservation execution beyond an explicitly authorized cohort;
- production mutation, production migration, or cutover;
- exposing raw production identifiers or payloads in Git.

## Canonical Reading

Read before editing:

- `.ai/rules/actions-console-commands.md`
- `.ai/rules/actions-console-commands-models.md`
- `.ai/rules/commands-models.md`
- `.ai/rules/commands-models-enums.md`
- `.ai/rules/actions.md`
- `docs/implementation/HISTORICAL_FINANCIAL_PRESERVATION_BOUNDARY.md`
- `docs/implementation/NEXT_SCALE_HISTORICAL_PRESERVATION_REHEARSAL_AUTHORIZATION_PACKET.md`
- `docs/reports/engineering-program-review/2026-08-17-epr-008-production-ground-zero.md`
- `docs/reports/engineering-program-review/2026-08-17-epr-009-production-financial-reconciliation.md`
- `docs/sources/PRODUCTION_SAFETY.md`

Then inspect the exact current migration actions, commands, models, and tests; file names and contracts in code are more current than prose examples.

## File Ownership

The Chief Architect will assign exact globs for each packet. Expected scope is existing `Legacy*`, `HistoricalFinancial*`, migration command/action/model, and corresponding focused test files.

Do not edit shared parity, architecture, Engineering Program Review, agent, lifecycle registry, or frontend files. Return proposed updates in the handoff.

## Acceptance Criteria

- Same immutable production and plan fingerprints revalidate.
- Every population count and classification is reproducible.
- Proposed state remains distinct from accepted state.
- Owner certainty does not promote business or application certainty.
- No similarity-based identity claim exists.
- No operational or production mutation occurs.
- No current authority-bearing state is created.
- Private artifacts retain exact evidence; committed artifacts remain payload-safe.
- Focused and full relevant tests, PHPStan, and Pint pass.
- Any replay reports deterministic fingerprints and exact aggregate counts.

## Stop Conditions

Stop and report to the Chief Architect if:

- the 736 characterization does not reproduce;
- exact owner separation requires a material domain change;
- a supposed deterministic subclass contains legal-identity ambiguity;
- proceeding requires accepting a mapping rather than proposing it;
- any source fact conflicts with the current strict runtime model;
- a financial discrepancy could alter taxpayer liability;
- an executor or rollback invariant would need weakening.

## Deliverable

Return a bounded characterization report with proposed subclasses, explicit unresolved dependencies, fingerprints, tests, private evidence paths, and one recommendation:

- `DETERMINISTIC SUBCLASS READY FOR ACCEPTANCE REVIEW`
- `CONTINUE CHARACTERIZATION WITH QUARANTINED EXCEPTIONS`
- `NO DEFENSIBLE SUBCLASS FOUND`
- `BOARD TRIGGER IDENTIFIED`
