# Historical Financial Preservation Boundary

Status: **PROPOSED - NOT IMPLEMENTED OR AUTHORIZED**

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
| Strict complete application-history candidates | 1,238 applications |
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

These criteria identify `1,238` source application histories as candidates. They are not currently executable because accepted application mappings do not yet exist.

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

## Recommended Next Decision

Approve or reject the separate immutable historical-preservation rehearsal boundary.

If approved, implement only the V1 planner/projector, private persistence, executor, rollback, audit, and focused tests described above. Run it first against synthetic fixtures. Production-scale execution remains blocked until exact application mappings exist and production migration rehearsal is separately authorized.

The independent TOR engineering lane can continue while this bounded decision is reviewed.
