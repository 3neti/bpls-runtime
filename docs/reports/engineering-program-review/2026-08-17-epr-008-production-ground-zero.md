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

## 18. Production Exception Taxonomy Supplement

This supplement responds to the Board's Production Exception Taxonomy and Architectural Impact Analysis instruction. It uses the same immutable snapshot, batch 6, registry plan 3, and readiness assessment 2. Analysis remained read-only. No source record, production record, mapping decision, or domain record was changed.

### 18.1 Executive Finding

The production discrepancies are caused by a small number of repeated historical patterns, not thousands of unrelated defects.

The largest counts are planner dispositions rather than independent broken records:

- all 3,192 businesses require accepted location/classification reference mapping;
- 2,199 businesses are blocked because their parent owner proposal is not ready;
- 1,953 owners share at least one identity signal with another source owner;
- 473 businesses share a registration or owner/name signal;
- 436 owners use the legacy Group-owner representation;
- 258 declared relationship edges reference 101 distinct absent target identifiers.

No finding currently proves that the Laravel domain model is wrong. The evidence instead identifies legacy-data discontinuities, policy/reconciliation questions, and one incomplete validation catalog. Production migration rehearsal is not yet safe.

### 18.2 Stable Exception Classes

| Class | Affected records | Area | Classification | Severity | Deterministic remediation? | Municipal decision? | Architecture impact | Migration impact |
| --- | ---: | --- | --- | --- | --- | --- | --- | --- |
| Shared owner identity signals | 1,953 owners | Registry / legal identity | Policy / reconciliation problem with possible legacy duplicates | Critical | No automatic merge; collision groups can be deterministically presented | Yes | None proven | Review identity clusters; preserve separate source identities until accepted |
| Shared business identity signals | 473 businesses | Business registry | Policy / reconciliation problem with possible legacy duplicates | High | No automatic merge | Yes | None proven | Review registration and owner/name collision groups |
| Group-owner semantics | 436 owners | Legal owner registry | Policy / reconciliation problem | High | Projection is deterministic; legal meaning is not | Yes | No redesign yet; current model preserves group metadata | Accept representation before mapping |
| Reference-data mapping | 3,192 businesses | Location and business classification | Policy / reconciliation problem | High | Technically deterministic after accepted crosswalks | Yes | None | No business proposal can become ready without accepted mappings |
| Cascading parent-owner block | 2,199 businesses | Registry dependency | Planner safety consequence | High | Yes, after parent owner is accepted and reference data is mapped | Indirectly | Confirms aggregate boundary | Do not map a business before its legal owner |
| Soft-deleted registry policy | 15 records: 11 owners, 4 businesses | Historical registry | Policy / reconciliation problem | Medium | Structurally identifiable | Yes | None | Decide quarantine, historical preservation, or active migration |
| Blacklist state | 9 records: 5 owners, 4 businesses | Registry enforcement | Policy / reconciliation problem | High | State can be preserved; operational effect requires authority | Yes | None | Must not silently drop or activate blacklist meaning |
| Declared orphan references | 258 relationship failures, 101 distinct absent targets | Financial, clearance, permit | Legacy data problem plus policy disposition | Critical | Varies by class | Yes for repair/exclusion semantics | None proven | Quarantine until each class has an accepted disposition |
| UOM references to absent applications | 10 UOM rows, 7 absent application IDs | Assessment variables | Legacy data problem / historical schema drift | High | No parent reconstruction from UOM alone | Yes for historical disposition | Validation catalog needs extension; domain unchanged | Add declared validation; quarantine with related missing-application groups |
| Fee override to absent fee | 1 of 9 overrides | Financial configuration | Legacy data problem plus policy reconciliation | Critical | No, not without fee authority | Yes | Domain remains correct | Refuse executable override; preserve source evidence |
| Mixed profile-media representation | 15 owner-avatar and 3 user-profile references | Media / identity presentation | Historical schema drift / unknown | Medium | Representation can be classified; object meaning is not automatic | Maybe | Storage boundary remains valid | Treat URL/string media separately from typed Convex objects |
| Unreferenced stored objects | 22 of 34 stored blobs are not referenced by characterized typed fields | File storage | Unknown retention/history class | Medium | Object integrity is known; business scope is not | Yes before migration/exclusion | None | Retain privately; do not infer ownership or delete |

Counts overlap where one record has several review reasons. They must not be summed as unique affected records.

### 18.3 Collision Shape

Owner collisions are repeated shared-signal clusters:

| Signal | Collision clusters | Signal memberships | Largest cluster | Two-record clusters | Larger clusters |
| --- | ---: | ---: | ---: | ---: | ---: |
| Email | 287 | 1,756 | 354 | 177 | 110 |
| Phone | 308 | 1,343 | 69 | 183 | 125 |
| Name plus birth date | 178 | 394 | 5 | 152 | 26 |

Business collisions have a similarly bounded shape:

| Signal | Collision clusters | Signal memberships | Largest cluster | Two-record clusters | Larger clusters |
| --- | ---: | ---: | ---: | ---: | ---: |
| Registration number | 200 | 465 | 15 | 164 | 36 |
| Owner plus business name | 12 | 24 | 2 | 12 | 0 |

These are collision signals, not duplicate declarations. Shared contact data may represent duplicate entry, household/shared contact, placeholder data, agents, or other legacy practice. The planner correctly refuses to decide legal identity from similarity.

## 19. The 258 Unresolved References

All 308,038 snapshot rows were staged. Therefore each target below is genuinely absent from the canonical snapshot, including soft-deleted rows. The current source normally soft-deletes applications, owners, businesses, and permits, so absent targets in those tables point to older hard deletion, import history, or identifier drift. The source explicitly supports hard deletion of payment schedules and force deletion of clearance types.

| Source relationship | Affected rows | Distinct absent targets | Evidence and likely pattern | Deterministic recovery | Migration handling |
| --- | ---: | ---: | --- | --- | --- |
| Payment schedule -> application | 69 | 20 applications | 67 schedules are pending; 2 are partial; 2 carry paid amounts; 3 have payment rows. The applications are absent rather than soft-deleted. | No | Preserve as quarantined financial history pending source/municipal disposition; do not invent applications |
| Payment -> application | 3 | 3 applications | Each payment's schedule survives and points to the same absent application. | The broken chain is deterministic; the legal application is not recoverable | Keep payment and schedule evidence together; do not attach to another application |
| Payment -> schedule | 56 | 56 schedules | All 56 payments are failed and retain a receipt field. Their applications survive. Forty-eight applications have one surviving schedule and eight have multiple; three missing schedule IDs appear in activity history. Current source can hard-delete schedules and voided payments retain their original fields. | No safe schedule substitution | Preserve as failed historical payment evidence; never relink by application or amount similarity |
| Permit clearance -> clearance type | 110 | 5 clearance types | Five repeated absent IDs account for all failures. Every row's denormalized name/short-name/certificate snapshot matches exactly one current clearance type. Source allows force deletion of types despite dependencies. | Strong deterministic candidate, but not yet authorized | Create accepted clearance-type reconciliation records before mapping; preserve original IDs and snapshots |
| Permit -> owner | 10 | 10 owners | The same 10 permits also lack businesses and have no surviving application anchor. Permit schema explicitly accepted legacy string IDs. | No | Quarantine authority claims; do not invent legal owners |
| Permit -> business | 10 | 10 businesses | Same 10 unanchored legacy permits as above. | No | Preserve as historical permit evidence only until authoritative identity is supplied |

The relationship failures refer to 101 distinct absent targets because the three payment-level missing applications overlap with the 20 applications already referenced by orphan schedules.

The 110 clearance failures are the only class with a strong exact reconciliation candidate. Exact denormalized matching is evidence for a proposed crosswalk, not authority to accept it.

## 20. Ready, Review Required, And Blocked

### Ready: 981

All 981 ready proposals are business owners with complete deterministic projections and no detected collision, group-owner, deletion, or blacklist reason. Each proposes creation because the current Laravel registry has no exact legacy-ID link.

Ready means only:

- the source projection is deterministic under the current contract;
- no known planner exception applies;
- the proposal could be selected for a later authorized execution.

Ready does not mean municipality-approved, migrated, rehearsal-ready, cutover-ready, or legally verified. No ready proposal was executed.

### Review Required: 3,186

Review-required records can be structurally projected but have one or more decisions that automation cannot make safely:

- 2,193 owners: collision, Group-owner, soft-delete, or blacklist semantics;
- 993 businesses: accepted location/classification mapping is missing, and 43 also have business collision signals.

Review Required means the record itself is projectable, but an accepted reconciliation or municipal decision is missing.

### Blocked: 2,199

All blocked proposals are businesses whose owner proposal is not ready. Every blocked business also needs reference-data mapping; 430 have business collision signals, 4 are soft-deleted, and 4 carry blacklist state.

Blocked means a required dependency is unresolved, so the business cannot be safely represented even as an independently selectable proposal. The 2,199 count is primarily a cascading ownership dependency, not 2,199 separate architecture failures.

## 21. Financial Integrity Analysis

No liability was recalculated and no historical amount was corrected.

### Structurally Coherent Evidence

- all 7,601 schedule totals agree with their persisted fee components plus persisted surcharge and penalty within half a cent;
- no schedule paid amount exceeds its persisted total;
- every schedule paid amount agrees with the sum of completed payment rows;
- all 5,750 resolved payment/schedule pairs agree on application identity;
- no payment amount has more than two decimal places;
- all transaction numbers are unique;
- all 12 typed storage references from business documents, permit layout, and report exports resolve to checksum-verified objects.

### Reconciliation Classes

| Finding | Count | Classification | Impact |
| --- | ---: | --- | --- |
| Failed payments retaining receipt fields | 129 | Historical void/status behavior plus receipt-authority policy | A receipt field is not proof of an issued or valid official receipt |
| Failed payments with absent schedules | 56 | Legacy hard-delete/void data problem | Preserve as quarantined failed evidence; do not relink |
| Duplicate receipt-field groups | 194 groups / 738 rows | Mixed expected auto-payment reuse and unresolved numbering authority | 186 groups stay within one application; 8 span applications; 13 span statuses. Municipal receipt semantics are required |
| Completed zero-amount payments | 489 | Legacy auto-payment behavior for zero-total later sections | Source proves intentional creation; preserve as historical behavior, not a monetary collection |
| Other zero-amount failed payments | 30 | Historical void/auto-payment behavior | Preserve status evidence; do not count as collected value |
| Partial schedules effectively fully paid | 2 of 3 partial schedules | Stale status or floating comparison issue | Paid totals remain coherent; status requires reconciliation |
| Schedule/section totals beyond two decimals | 24 schedules | Legacy floating-point precision | Maximum distance is half a cent; preserve source and apply accepted rounding only in executable policy |
| Fee original amounts beyond two decimals | 2,794 fee occurrences | Legacy formula/installment precision | Original amount is evidence, not Laravel rounding authority |
| Fee override with missing fee | 1 of 9 overrides | Configuration discontinuity | Override remains non-executable until fee identity and authority are reconciled |

The 194 duplicate receipt-field groups are not automatically duplicate official receipts. Legacy source intentionally copies one supplied receipt value to auto-created zero-total section payments, and voiding preserves receipt fields. Eight groups spanning applications still require explicit municipal review.

## 22. Model And Architecture Impact

### Confirmed by production

- `User`, legal `BusinessOwner`, `Business`, and transactional application identity remain distinct.
- Applications reference owners and businesses coherently in the current production set.
- Permit-to-application is correctly optional for historical permits.
- Payment schedules, payments, clearances, permits, reference data, billing groups, audit, report configuration, and stored objects are genuine migration domains.
- Immutable staging, explicit mappings, financial snapshots, and authority boundaries are necessary.
- Production contains soft-deleted history that must not be treated as active state.

### Contradicted or refined

- The earlier assumption that the main production graph would be referentially closed is false.
- UI totals are active/visible projections, not complete production inventory.
- The declared staging reference catalog is incomplete: it did not yet include UOM-to-application and fee-override-to-fee relationships.
- Receipt fields cannot be treated as unique or authoritative without status, application, auto-payment, void, and numbering context.
- Profile media is represented through mixed URL/string history as well as typed storage IDs.

### Architecture conclusion

No approved domain or architecture decision has become invalid.

The missing-parent records do not prove that payments can legitimately exist without schedules, permits without legal owners, or businesses without owners in the future Laravel runtime. They prove that migration needs a quarantined historical-evidence path and accepted exception dispositions. The current normalized runtime model should remain strict.

The validation catalog requires extension, but this is an implementation completeness issue rather than an architectural redesign.

## 23. Git Security Review

Commit `3b95363` was reviewed file by file and by added-line pattern scan.

Permitted production-derived material present in Git:

- aggregate counts;
- archive and evidence hashes;
- source table names;
- deployment name and capture timestamp;
- redacted exception categories;
- private artifact paths without source record identifiers.

Production-derived material not present in Git:

- citizen or business payloads;
- actual operator identity;
- receipt, transaction, permit, or storage identifiers;
- uploaded documents or object payloads;
- credentials, cookies, sessions, tokens, or authorization headers;
- JSONL tables or the snapshot archive.

The only email added by the commit is the synthetic test fixture `authorized@example.test`. No file under the private snapshot or staging artifact roots is tracked. The commit satisfies the Board's stated Git sensitivity boundary, but remains unpushed pending Board review.

## 24. Board Questions Answered

1. **What does production confirm?** The recovered registry, application, schedule, payment, clearance, permit, billing-group, reporting, audit, and storage domains are real and fit the approved architecture.
2. **What does production contradict?** The graph is not fully referentially closed; UI totals are not total history; receipt fields are not inherently unique authority; the validation catalog is incomplete.
3. **Data or domain problem?** Major exceptions are legacy-data and policy/reconciliation problems. No domain-model invalidation is proven.
4. **Largest recurring classes?** Shared owner signals, reference-data mapping, cascading owner dependencies, Group-owner semantics, shared business signals, and five repeated historical clearance IDs.
5. **What explains the 258?** Historical hard deletion/identifier drift, explicit hard-delete behavior for schedules and clearance types, and unanchored legacy permit IDs.
6. **What converts review/blocked to deterministic?** Accepted identity dispositions, owner decisions, location/classification crosswalks, clearance-type reconciliation, deletion/blacklist policy, and source-backed missing-parent dispositions.
7. **Is architecture invalid?** No.
8. **Is production migration rehearsal safe?** No. Open relationship errors, unresolved identity, incomplete reference validation, and absent municipal decisions remain.
9. **What decisions/evidence are required?** Identity collision review rules, Group-owner meaning, deleted/blacklisted history policy, clearance replacement acceptance, missing-parent disposition, receipt authority, fee-override identity, media retention, and accepted reference-data crosswalks.
10. **Exact next action?** Extend the declared read-only reference catalog, reproduce this taxonomy from the same snapshot fingerprint, and prepare bounded municipal decision packets for clearance replacement, missing-parent history, identity collision classes, and receipt semantics. Do not execute mappings or migration.

## 25. Supplement Recommendation

CONTINUE WITH NOTED RISK

Continue the independent engineering lane. Continue production reconciliation only through payload-free classification, reference-catalog completion, and municipal decision preparation. Keep production migration execution and cutover blocked.
