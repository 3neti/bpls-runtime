# Historical Financial Preservation Boundary

Status: **IMPLEMENTED AND SYNTHETICALLY REHEARSED - PRODUCTION EXECUTION NOT AUTHORIZED**

Prepared: 2026-08-17

Production snapshot: `IPIL-CONVEX-SNAPSHOT-56FAD41ABBDEAE8D`

Financial plan: `prod-financial-migration-plan-20260817-001` / plan 3

## Conclusion

A separate historical-preservation rehearsal class is justified.

The current `0` rehearsal-eligible result remains correct for the operational financial executor. That executor creates current `Assessment` and `PaymentSchedule` records and therefore requires an accepted application mapping, accepted fee identity, accepted category, and a deliberately narrow annual/unpaid structure.

Historical financial preservation has a different meaning:

```text
historical financial fact
    != accepted fee-policy provenance
    != future executable assessment policy
```

The preservation path must therefore remain separate from `ExecuteLegacyFinancialSnapshots`. It may copy an exact, immutable historical projection into a restricted evidence store, but it must not create operational assessments, schedules, collections, receipts, or executable fee rules.

No production record is currently executable under this proposed class. The production batch has no accepted `LegacyApplicationIdMapping` records, so there is no deterministic Laravel permit-application target yet.

## Production Characterization

The earlier line-level classification found:

| Classification | Proposals |
|---|---:|
| Deterministic historical schedule-fee snapshots with incomplete provenance | 21,347 |
| Reconciliation required | 13,993 |
| Quarantined historical evidence | 3,506 |
| Authority blocked | 25,397 |
| Existing operational rehearsal eligible | 0 |

Fee-line counts are not migration units. Complete schedules and complete application histories are the relevant consistency boundaries.

### Bundle Reduction

| Boundary | Result |
|---|---:|
| Payment schedules in the production plan | 7,601 |
| Schedules containing at least one fee line | 7,205 |
| Schedules whose fee lines are all exact, unedited, and incomplete only in fee-policy provenance | 4,384 |
| Structurally coherent source-preservation schedule candidates | 4,350 |
| Frozen complete application-history candidate census | 1,238 applications |
| Compatible with the implemented V1 preservation projector | 1,223 applications |
| Requiring sibling-payment reconciliation before V1 execution | 15 applications |
| Schedules in those complete application histories | 1,930 |
| Fee lines in those histories | 8,735 |
| Exact single completed-payment events in those histories | 1,790 |
| Unpaid schedules with no payment event in those histories | 140 |

Four application/section pairs contain duplicate section numbers in the broader population. They are not eligible under the strict complete-history rule.

The strict candidate population deliberately excludes zero-total schedules, partial schedules, late-charge schedules, edited lines, malformed money or dates, incomplete sibling schedules, multiple payment attempts, failed/pending/cancelled payment siblings, contradictory statuses, broken references, and component/total disagreements. Those facts remain preserved in staging but require another reconciliation class.

## Why The Existing Executor Must Not Be Weakened

The operational snapshot executor correctly requires:

- every selected proposal to be `Ready`;
- an accepted `LegacyApplicationIdMapping` to a real `PermitApplication`;
- accepted fee-rule identity and authority evidence;
- application-scoped Tax rules;
- one annual, pending, unpaid schedule;
- no payment evidence, surcharge, or penalty;
- exact projection and target hashes;
- no unmanaged operational financial records.

It writes `Assessment`, `AssessmentLine`, `PaymentSchedule`, and `PaymentScheduleLine` records. Relaxing fee identity there would require manufacturing `code`, `name`, `category`, `calculation_type`, and legal/rule provenance, and would make ordinary operational reports treat uncertain history as an accepted computed assessment.

That would collapse historical fact into future policy. The existing executor must remain unchanged.

## Proposed Preservation Eligibility V1

Eligibility is evaluated per complete legacy application history, never per fee line or isolated quarter.

Every selected application bundle must satisfy all of the following:

1. The import source, batch, archive checksum, financial-plan hash, and classifier version are fixed.
2. The source application exists and every schedule referencing it is included.
3. The application has an accepted exact Laravel application mapping before execution. Similarity is never sufficient.
4. Schedule section numbers are valid and unique within the source application.
5. Every schedule has a valid source date and a literal source status of either `pending` or `paid`.
6. Every monetary field converts exactly to integer centavos.
7. Every schedule total equals its persisted fee components; V1 requires zero persisted surcharge and penalty.
8. Every fee line is present, exact, and unedited. Missing fee identity is allowed only as explicitly incomplete provenance.
9. A pending schedule has no related payment events.
10. A paid schedule has exactly one structurally valid completed payment, with no failed, cancelled, or pending sibling attempts, and persisted paid totals agree.
11. The complete application bundle has no quarantined sibling schedule or payment evidence.
12. No target historical-preservation mapping already exists under a different source or projection hash.

These criteria originally identified a frozen census of `1,238` source application histories. Root-cause replay against the implemented projector showed that the original classifier incorrectly treated `application_has_unassigned_payment_events` as census-compatible instead of enforcing it as a V1 exclusion. The corrected V1 candidate population is `1,223`; the original census remains immutable evidence, while the other `15` are a separate non-executable exception class.

All 15 exceptions have the same legacy-data shape: the application has a valid persisted paid schedule and its completed payment, plus failed payment attempts whose non-empty source schedule identifiers refer to schedules absent from the production snapshot. There are `36` such failed attempts: eight applications contain one and seven contain four. The records are not discarded or repaired. V1 refuses them because complete application history is atomic and an absent referenced schedule prevents exact preservation.

## Smallest Reversible Boundary

Introduce a dedicated migration-only historical preservation action and storage boundary after Board acceptance. Do not add a mode to the operational financial executor.

The minimum durable target is one immutable preservation bundle per mapped legacy application, containing:

- exact source, batch, application-mapping, and staged-record references;
- source payload hashes and one canonical bundle hash;
- normalized integer-centavo schedule, fee-line, and payment facts;
- literal historical statuses and dates;
- explicit `fee_policy_provenance = incomplete` on every unidentified line;
- explicit `future_policy_executable = false`;
- explicit `operational_financial_record = false`;
- references back to restricted immutable source evidence for identifiers not copied into the projection.

The preservation target must not be an `Assessment`, `PaymentSchedule`, `TreasuryCollection`, or `Receipt`. It must not participate in ordinary assessment, collection, balance, receipt, or authority-bearing reports.

The executor should require:

- local/testing environment;
- stable operator-supplied run reference;
- dual execution confirmation;
- exact bundle selection;
- accepted application mappings;
- dependency and selection hashes;
- one database transaction;
- no queues, notifications, integrations, calculations, or lifecycle transitions.

## Integrity Rules

Before writing, the rehearsal must regenerate every bundle from the immutable staged records and verify:

- source archive and batch fingerprints;
- source payload hashes;
- complete application/schedule/payment membership;
- unique schedule sections;
- exact monetary conversion;
- schedule component totals;
- persisted paid-total agreement;
- unchanged proposal projections;
- accepted exact application mapping;
- absence of operational financial writes;
- absence of duplicate source or target preservation mappings.

After writing, audit must compare the canonical target bundle hash with a fresh source projection and verify counts and centavo totals at application, schedule, line, and payment levels.

## Rollback Rules

Rollback may delete only preservation bundles created by the exact execution when:

- the target bundle hash is unchanged;
- no reviewer disposition, annotation, acceptance, or downstream reference has been attached;
- the application mapping still matches the execution evidence;
- no operational financial record was created from the bundle.

Rollback must refuse changed or referenced evidence. It must never delete source staging records, operational assessments, schedules, collections, receipts, or application mappings.

## Explicit Non-Goals

This boundary does not:

- infer fee identity from names;
- assign current fee rules or categories;
- execute a formula;
- recalculate historical liability;
- create collections or receipts;
- decide receipt-number authority;
- interpret surcharge or penalty policy;
- authorize future assessment behavior;
- authorize production migration or cutover.

The five-item Municipal Fiscal Acceptance Packet remains the only current path toward future executable financial policy.

## Implementation Outcome

The Board-approved V1 boundary is implemented as a separate planner/projector, immutable preservation store, guarded executor, source-to-target audit, and rollback path. The operational `ExecuteLegacyFinancialSnapshots` path was not changed and has no historical mode.

Synthetic rehearsal proves:

- one complete application history is the atomic unit;
- exact paid and unpaid schedule facts, fee-line centavos, and completed-payment facts survive projection without formula execution or fee-identity inference;
- source and target counts and centavo totals agree exactly;
- no operational assessment, schedule, collection, receipt, liability, lifecycle, queue, notification, or integration write occurs;
- stable retries do not duplicate bundles;
- rollback returns preservation-bundle storage to its exact pre-rehearsal count while retaining source staging, application mappings, and operational records;
- rollback refuses reviewed, referenced, or changed evidence.

The `1,223` corrected V1 production application histories remain candidates only. The 15 excluded histories remain immutable exception evidence outside V1. The exact prerequisite for a production-scale preservation rehearsal is an accepted `LegacyApplicationIdMapping` for every selected source application, followed by a separate Board authorization for that checksum-bound selection. Production migration and cutover remain unauthorized.

## Exact Application Mapping Readiness

Corrected read-only characterization run `prod-historical-financial-application-mapping-readiness-20260817-012` binds financial plan 3 and registry plan 3 to production snapshot SHA-256 `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57`. It preserves the original frozen census exactly: `1,238` applications, `1,930` schedules, `8,735` fee lines, `1,790` completed payments, and `140` unpaid schedules. It separately establishes the exact V1-compatible population: `1,223` applications, `1,912` schedules, `8,642` fee lines, `1,773` completed payments, and `139` unpaid schedules.

| Mapping-readiness class | Applications |
|---|---:|
| Deterministic identity chain | 415 |
| Blocked only by reference-data crosswalks | 6 |
| Human identity reconciliation required | 736 |
| Registry-policy reconciliation required | 72 |
| Other application/lifecycle reconciliation required | 409 |

The mapping-readiness classes are mutually exclusive; the deterministic-chain count is an independent flag. Production flags include `736` collision cases, `192` Group-owner cases, `13` soft-deleted cases, and zero blacklisted cases within corrected V1. Four otherwise deterministic identity chains, ten human-identity cases, and one registry-policy case belonged to the excluded 15-record class. Similarity remains review evidence only and created no accepted mapping.

All `1,223` V1 businesses require an explicit source-backed reference-data crosswalk, and `1,221` applications require line-of-business declaration reconciliation. Of the deterministic identity chains, six are blocked only by those reference-data dependencies. Five members form the smallest proposed first rehearsal cohort. None belong to the excluded 15-record class.

The cohort prerequisite packet records three source location-reference fields and one line-of-business declaration for each cohort member as hashed, pending proposals. No accepted target evidence currently exists for those line-of-business values, so the packet explicitly records `target_evidence_required`; owner, business, and application mappings remain pending in dependency order. The packet created zero reconciliation or mapping rows and does not yet qualify for municipal acceptance or rehearsal authorization.

The corrected candidate-set SHA-256 is `307ecf33dafb9c53fd16064288b3057edd3c3131851a0982f54f8e1fe3750d06`; the 15-record exception-set SHA-256 is `c5f598029e1047d6579fa201718637fa6e39299fc4fc7758c4153f37e612adae`. The unchanged five-record conditional cohort SHA-256 is `bf5af2693e471f336c54bf5e3345cc6b9df8709fceddd5ea1bc63360c3ebddb4`, and its prerequisite-proposal SHA-256 is `7937edfa67048fde4253952529024a01f27a2fdb03e96ffcdc84370a2bee9c92`. All hashes bind the source archive, financial batch and plan, registry batch and plan, and selected evidence. Raw identifiers and payloads remain only in private checksum-bound artifacts.

The independent TOR engineering lane continues separately from production reconciliation and preservation authorization.

## Five-Record Mapping Prerequisites

Read-only prerequisite run `prod-historical-financial-cohort-prerequisites-20260817-002` recomputed the same five-record cohort from financial plan 3 and registry plan 3 and refused to proceed unless the result matched frozen cohort SHA-256 `bf5af2693e471f336c54bf5e3345cc6b9df8709fceddd5ea1bc63360c3ebddb4`. The cohort did not change.

Source-backed evidence now establishes:

- all 15 province, city, and barangay references resolve by exact source identifier;
- all five city-to-province and barangay-to-city edges are internally consistent;
- each of the five application declarations matches exactly one legacy `groups` record by the exact behavior used by the legacy source;
- each matched group has a complete `division_groups -> divisions -> majors` hierarchy in the immutable snapshot;
- no existing Laravel `LineOfBusiness` is bound to any of those five exact legacy group identities;
- no line-of-business reconciliation, registry mapping, or application mapping was accepted or created.

The five location dispositions and five line-of-business target definitions are therefore `proposed`, not `accepted`. The proposed location disposition is to preserve the exact source lookup chain and hashes as registry provenance rather than invent normalized location identities that the current Laravel model does not possess. The proposed line-of-business action is to create or explicitly select a Laravel target from each exact legacy group and hierarchy only after acceptance; normalized name equality alone is not mapping authority.

All five exact application-mapping proposals are now `evidence_complete_acceptance_pending`. This means their prerequisite evidence is reviewable. It does not mean that target records may be created, reconciliations accepted, mappings written, or a rehearsal executed.

The proposal package SHA-256 is `06907e02c12209115fdd451cceac08b2fa20baba174dbdc1198dc5803b0faa36`. Its private artifacts remain under the checksum-bound production evidence hierarchy. The remaining dependency order is:

1. Accept or reject the exact location-chain preservation disposition.
2. Accept target definitions and create or select explicit Laravel line-of-business targets.
3. Accept line-of-business reconciliations with authority and evidence references.
4. Accept owner, business, and application mappings in dependency order.
5. Freeze the resulting exact five-record mapping set.
6. Obtain separate Board authorization before a production-derived preservation rehearsal.

The state model remains explicit: source facts are `observed`; the legacy `groups` interpretation is `inferred` from exact source behavior; crosswalks and mappings are `proposed`; nothing is yet `accepted`, `rehearsed`, or `production-applied`.
