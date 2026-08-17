# Engineering Program Review #008: Production Ground Zero

Date: 2026-08-17

==================================================

BPLS-RUNTIME

Engineering Program Review #008

Overall Health: YELLOW - implementation remains healthy; production reconciliation has exposed material data exceptions

Architecture Health: HEALTHY

Current Phase: Mid Implementation / Production Reconciliation

Recommendation: CONTINUE WITH NOTED RISK

==================================================

## 1. Executive Dashboard

The rescue has reached Production Ground Zero. One authenticated, read-only Convex production backup, including file storage, has been acquired from the exact authorized deployment and bound to immutable private evidence. Its checksum, capture time, deployment, operator, tooling, table inventory, row inventory, and object integrity are now known.

This resolves the largest evidence gap reported since Discovery. It also replaces broad migration uncertainty with concrete exceptions. All 308,038 source rows staged successfully, but the source contains 258 unresolved declared references. Registry planning identified substantial owner and business collision signals and correctly accepted no identity mappings.

Architecture remains healthy. The system refused to convert historical inconsistencies into domain records, financial facts, or legal identity. The engineering lane can continue, but production migration execution must remain blocked until exception dispositions and identity decisions are authorized.

## 2. Current Phase

Current phase: Mid Implementation / Production Reconciliation.

- Ground Zero, Discovery, and Architecture remain complete and approved.
- The engineering lane continues to deliver Laravel capabilities and evidence.
- The production reconciliation lane is no longer waiting on access.
- Production snapshot intake, inventory, file validation, staging, registry planning, and readiness classification have run.
- No production mutation, domain migration, financial calculation, or cutover action occurred.
- Rehearsal and cutover readiness are both blocked by explicit evidence.

## 3. Capability Progress

The Discovery ledger contains 118 capabilities. The implementation parity ledger currently tracks 73 active rows: 45 browser verified, 9 UI partial, 2 backend partial, 15 blocked, and 2 not started. These are navigation counts, not equal-weight completion percentages.

| Area | Current State | Evidence / Remarks |
| --- | --- | --- |
| Registry | Implemented locally; production reconciliation blocked | 3,174 owners and 3,192 businesses inventoried. Collision signals require review; no identity mappings accepted. |
| Permitting | Substantial through authority review | 3,112 production applications and 2,731 permits are now inventoried. Ten permits have unresolved owner and business references. |
| Assessment | Strong boundary; policy incomplete | Financial calculations remain deterministic and separate from migration. Production rules and historical snapshots still require reconciliation. |
| Treasury | Partial; production topology now visible | 7,601 schedules and 5,806 payments are inventoried. Schedule/application and payment/schedule discontinuities are explicit. |
| Reporting | Material operational foundation | Reporting remains downstream of persisted evidence. Production parity and authority-bearing outputs remain conditional. |
| Citizen Portal | Substantial first milestone | Production identity linkage exists in the source but deterministic citizen/legal-owner migration remains unresolved. |
| Administration | Foundational | Production roles, permissions, users, layouts, and platform configuration are present for later reconciliation. |
| Migration | Production evidence acquired; execution blocked | 53 tables, 308,038 rows, and 34 stored files validated. Staging and planning produced no domain writes. |
| Verification | Strong | Archive, table, file, staging, planning, and readiness evidence are checksum-bound and payload-free for review. |

## 4. Architecture Health

Architecture health: healthy.

The approved migration architecture behaved as designed:

1. The authenticated snapshot entered immutable private intake.
2. Tables and stored files were fingerprinted before interpretation.
3. Staging preserved every source row without creating BPLS domain records.
4. Declared relationships failed visibly instead of being inferred.
5. Registry similarity produced review signals, not accepted identity.
6. The readiness gate blocked rehearsal and cutover.

No Convex compatibility layer, alternate financial engine, generic migration framework, or production-data shortcut is required. The domain model has not been contradicted; the data requires reconciliation before it can safely enter that model.

## 5. Project Risks

1. **Production identity reconciliation:** 1,953 owner and 473 business collision signals require deterministic review. Similarity cannot establish legal identity.
2. **Historical referential integrity:** 258 source references do not resolve within the canonical snapshot, including financial, clearance, and permit evidence.
3. **Financial history:** orphan schedules and payments may affect balances, receipt interpretation, and historical audit. Missing parents must not be fabricated.
4. **Permit authority:** permits with absent owner/business references may represent deleted history, stale data, or authority claims requiring municipal disposition.
5. **Reference-data policy:** every business proposal requires accepted reference-data mapping, and group-owner semantics remain unresolved.
6. **Object reconciliation:** all stored files passed integrity checks, but application scope and document authority remain unaccepted.
7. **Municipal policy and cutover:** production contents prove historical facts, not accepted current policy or migration authority.

## 6. Technical Debt

- The local SQLite migration ledger still reports one older index migration as pending even though the index exists.
  - Reason: migration history was not rewritten to hide local drift.
  - Impact: an ordinary full local migration run stops on the duplicate index.
  - Cleanup: reconcile the local development database transparently during stabilization; fresh-database correctness remains authoritative.

- The snapshot reference catalog currently validates 11 high-value declared relationships rather than every source field ending in an apparent identifier.
  - Reason: relationships are declared from evidence and never inferred from field names.
  - Impact: additional source-backed relationships require deliberate catalog expansion.
  - Cleanup: add only relationships proven by the exact legacy schema and usage before later reconciliation passes.

No broken reference, identity collision, financial assumption, or permit-authority claim has been accepted as technical debt.

## 7. Major Discoveries

- The canonical production source is materially larger and broader than the prior UI observations: 53 tables and 308,038 records, including authentication, reporting, billing-group, configuration, and audit data.
- Historical application, payment, clearance, and permit graphs are not fully referentially closed inside the production snapshot.
- Registry duplication is not an edge case. Potential source collisions affect a material portion of legal-owner records and must be treated as migration work, not automatic deduplication.
- The live UI totals and snapshot counts differ because the UI applies current visibility/deletion semantics while the backup preserves broader historical state. Neither count should silently replace the other.
- Production file storage is small in object count but material in bytes; integrity is proven, while document scope and authority remain separate questions.

## 8. Evolution

What became simpler:

- The production-data dependency has moved from unknown to an exact checksum-bound source.
- Migration risk is now expressed as concrete exception classes rather than a general fear of Convex data.
- Identity reconciliation is one explicit review boundary; it is not hidden inside data import.
- Financial and permit discontinuities are quarantined by the same staging/readiness path rather than handled by special scripts.
- The two program lanes are clearer: engineering continues, while production execution waits on reconciliation and authority.

## 9. Current Slice Summary

This milestone completed:

1. Authenticated download of one existing Convex production backup with file storage.
2. Private mode-`0600` archive retention outside Git.
3. SHA-256, size, deployment, capture, operator, and tooling provenance.
4. Validation of 53 table streams and 34 stored files against Convex metadata.
5. Private staging of all 308,038 rows with aggregate exception evidence.
6. Registry planning without accepted mappings or domain writes.
7. A non-mutating readiness assessment that correctly blocked rehearsal and cutover.

## 10. Evidence Produced

Private artifact roots:

- Intake: `storage/app/private/legacy-migrations/convex-snapshots/prod-convex-20260816-224400`
- Staging: `storage/app/private/legacy-migrations/IPIL-CONVEX-SNAPSHOT-56FAD41ABBDEAE8D/prod-convex-stage-20260816-224400`
- Registry plan: `storage/app/private/legacy-migrations/IPIL-CONVEX-SNAPSHOT-56FAD41ABBDEAE8D/prod-convex-stage-20260816-224400/mapping-plans/prod-registry-plan-20260816-224400`
- Readiness: `storage/app/private/legacy-migrations/IPIL-CONVEX-SNAPSHOT-56FAD41ABBDEAE8D/prod-convex-stage-20260816-224400/readiness-assessments/prod-readiness-20260816-224400`

Review-safe evidence contains counts, hashes, classifications, and reasons. Raw rows, stored objects, source identifiers, detailed collision evidence, and operator identity remain private.

Verification Matrix:

| Verification | Result |
| --- | --- |
| Focused snapshot-intake tests | PASS - 10 tests, 120 assertions |
| Pint | PASS |
| Archive SHA-256 and ZIP validation | PASS |
| Table inventory and checksums | PASS - 53 tables |
| File-storage integrity | PASS - 34 files |
| Staging | PASS WITH EXCEPTIONS - all rows staged, 258 open errors |
| Registry planning | PASS WITH EXCEPTIONS - no accepted mappings |
| Readiness assessment | BLOCKED AS DESIGNED |
| Browser verification | NOT APPLICABLE - no browser-facing Laravel behavior changed |
| Production mutation | NONE |

## 11. Recommendation

CONTINUE WITH NOTED RISK

Autonomous implementation should continue in the engineering lane. The production reconciliation lane should continue only with read-only characterization and explicit exception handling. No migration proposal should execute until identity, broken-reference, reference-data, document-scope, and municipal authority decisions are accepted.

## 12. Next Vertical Slice

Recommended next slice: **Production Exception Classification And Reconciliation Workbench**.

It should classify the 258 broken references and source-side collision groups using exact legacy semantics and redacted evidence. It must not repair records, merge identities, invent missing parents, or execute migration. Its architectural value is to turn aggregate blockers into bounded municipal review decisions while preserving the same immutable snapshot fingerprint.

The independent engineering lane may continue remaining TOR parity work in parallel.

## 13. Coffee with Arti

When a historical financial or permit record references an absent legal parent, should the rescue preserve it as quarantined historical evidence, require an authorized source repair, or allow an explicitly approved exclusion from operational migration?

The answer affects legal traceability, opening balances, historical reports, and what the Municipality can legitimately claim after cutover. The software should support the accepted disposition, but it should not choose it.

## 14. Constitution Check

- Evidence before design: compliant.
- Design before implementation: compliant.
- Production treated as evidence, not a playground: compliant.
- Unknown policy remains explicit: compliant.
- Laravel-native direction preserved: compliant.
- No premature GNE abstraction: compliant.
- Observable parity remains primary: compliant.
- Financial calculations remain on one authoritative path: compliant.
- Identity similarity is not treated as authority: compliant.
- Storyboards and lifecycle scenarios remain verification infrastructure: compliant.
- Domain remains the source of business truth: compliant.
- Migration and cutover authority remain explicit: compliant.

## 15. Standing Board Decisions

- Target deployment direction is Laravel Cloud.
- Replacement shape is a single Laravel 13 application with Vue/Inertia and a relational source of truth.
- Convex, ClickHouse, Airbyte, and Vercel topology will not be retained merely for parity.
- Production data is authoritative evidence of legacy contents, not automatic authority for policy or migration.
- Migration requires immutable provenance, deterministic mapping, reconciliation, rehearsal, and explicit cutover authority.
- Similar names, contacts, identifiers, and fingerprints do not establish legal identity.
- Missing historical parents must not be fabricated.
- Financial calculations use one authoritative path and immutable snapshots.
- Authority-bearing outputs must not be inferred from artifacts or raw status values.
- Unknown policy is refused rather than guessed.
- Production is evidence, not a playground.
- GNE concepts must not enter the rescue prematurely.

## 16. If I Were Starting Today

I would request the authenticated production snapshot immediately after Ground Zero and run this exact intake before detailed migration implementation. The existing evidence-first architecture prevented harm, but earlier production inventory would have exposed deleted-history semantics, reference discontinuities, and registry duplication before synthetic scale work.

I would still keep source and live observation ahead of data transformation. The snapshot explains what exists; it does not independently explain what every historical value legally means.

## 17. Confidence Index

This measures confidence in sufficient understanding, not implementation progress.

| Area | Confidence | Remarks |
| --- | --- | --- |
| Architecture | [#########-] 95% | Production intake reinforced the approved boundaries without redesign. |
| Registry | [#######---] 72% | Exact scale is known; collision disposition and legal identity remain difficult. |
| Permitting | [########--] 82% | Lifecycle remains coherent; production permit discontinuities and authority remain unresolved. |
| Assessment | [#######---] 75% | Calculation architecture is strong; production policy reconciliation remains incomplete. |
| Treasury | [######----] 64% | Historical topology is now visible; broken financial references and authority remain unresolved. |
| Reporting | [#######---] 72% | Production report/configuration data is inventoried; official acceptance remains ahead. |
| Citizen Portal | [########--] 83% | Implemented journey is stable; production identity mapping remains unresolved. |
| Migration | [######----] 58% | Acquisition and staging are proven; reconciliation and execution are deliberately blocked. |
| Verification | [##########] 97% | Production evidence is fingerprinted, private, deterministic, and independently reviewable. |
| Overall Rescue | [########--] 78% | Uncertainty has reduced materially, while the remaining production decisions carry high legal and financial weight. |
