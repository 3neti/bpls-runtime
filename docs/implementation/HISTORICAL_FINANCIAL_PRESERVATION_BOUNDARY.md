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

## Exact-Class Completion And Human-Identity Frontier

The exact 407-application class has completed execute, source-to-target audit, rollback, and restoration audit across every persisted financial topology. In aggregate, the rehearsals reproduced 696 schedules, 3,007 fee lines, 660 completed payments, 36 unpaid schedules, 412,770,810 scheduled centavos, and 397,445,008 paid centavos. All operational financial counts remained unchanged and no preservation bundle remains after rollback.

The preservation audit now builds one exact application/schedule proposal index and reuses it in planning, execution, and audit. This changes evidence lookup cost only. Projection membership, canonical serialization, hashes, counts, centavo assertions, and fail-closed behavior remain unchanged.

Read-only human-identity frontier run `prod-human-identity-frontier-20260818-001` binds the unchanged corrected V1 evidence to frontier SHA-256 `8b1b80d4b2f38eb186186930c567e1e9eb7b83c4b28490307117381056064bbc`. The 736 applications represent 708 unique owner proposals and 727 unique business proposals across 40 evidence shapes. They contain 469 owner collision groups and 80 business collision groups; raw collision fingerprints and source values remain private.

Of these, 727 carry only identity collision/reference-data/lifecycle-authority evidence and nine carry additional deletion, financial-override, or lifecycle reconciliation semantics. A bounded 12-application subclass has nine unique collision-free owner proposals but unresolved business collisions. Only those owner identities may be prepared for independent acceptance review; business and application mappings remain unaccepted. Similarity has not established or merged any legal identity.

Additive read-only run `prod-human-identity-frontier-20260818-004` uses characterization schema `bpls.historical-financial-human-identity-frontier.v4`. It preserves the human-frontier SHA-256 `8b1b80d4b2f38eb186186930c567e1e9eb7b83c4b28490307117381056064bbc`, business-source subclass SHA-256 `ab4380ec8b56e928e0b73671c424ccc7048a032ca7a2bc4095577cb50e2ead03`, and seven-cohort set SHA-256 `dcbfaadec88b19ed564951af29b24c194049a903036c9c98c3ef922dc0c05d41` exactly.

Within the unchanged 519 business-source evidence class, v4 identifies 450 applications / 443 owner proposals / 447 business proposals whose owner collisions use only email/phone signals, and 69 applications / 68 owner proposals / 68 business proposals with a non-contact identity signal present. The 450 include 442 historical `Released` and eight non-`Released` applications across 254 hashed contact collision groups. The 69 include 68 historical `Released` and one non-`Released`; current production evidence observes `name_birth`. Evidence-class set SHA-256 is `5aed72372bb3cf5260946196f23ab6f5e126eff6e1918b8947fcdfa9b14699c5`.

This split narrows the municipal decision without deciding identity. A source-backed rule for shared contact points could move the 450 into bounded exact mapping/reference-data review, but it would not accept mappings or make the class rehearsal-ready. The 69 remain subject to authoritative person-oriented identity reconciliation. Collision-group counts across the two evidence classes may overlap and are not additive.

Additive read-only run `prod-human-identity-frontier-20260818-006` uses characterization schema `bpls.historical-financial-human-identity-frontier.v5`. It reproduces the complete v4 summary and all frozen fingerprints unchanged, then adds priority-review topology SHA-256 `53790859b7bd63430c4e3f35e0a212b22cade849202d56aa25a45def80a59c7f`.

The 69 non-contact applications reduce to 51 exact hashed `name_birth` collision review groups: 41 groups of two, five of three, two of four, and three of five. These are review units only; name and birth date remain collision evidence rather than legal-identity authority.

The 76 compound owner/business collision applications separate into 75 applications / 72 owner proposals / 75 business proposals carrying a registration-number collision dimension across 52 globally deduplicated review groups, plus one non-`Released` phone-plus-owner/name outlier. The 75 registration cases route into 34 contact-only-owner applications and 41 non-contact-owner applications. Registration-group counts inside those two routes overlap, so the 52-group aggregate is authoritative. A shared-contact decision could address only the owner-evidence dependency for the 34; business-registration identity, reference data, exact mappings, cohort freeze, and rehearsal authorization would remain.

The eight soft-deleted applications remain quarantined as one exception matrix: five carry contact-only owner signals, three carry non-contact signals, two carry Treasury interpretation, one carries financial-policy authority, one carries permit-authority semantics, and one carries a genuine source contradiction. These overlays are not asserted disjoint. The single identity-plus-financial application preserves separate identity and financial-override blockers. No newly characterized class has an accepted mapping, has been rehearsed, is production-applied, or is one bounded decision from rehearsal-ready.

Additive read-only run `prod-human-identity-frontier-20260818-008` uses characterization schema `bpls.historical-financial-human-identity-frontier.v6`. It preserves the complete v5 output and priority-review topology SHA-256 `53790859b7bd63430c4e3f35e0a212b22cade849202d56aa25a45def80a59c7f`, then adds decision-unlock topology SHA-256 `b627a317ccff26133ea5b98d3afcf0ee5c4fb356154480de3fe6eae7bc5bfceb`.

The 51 non-contact `name_birth` groups divide into 14 closed groups covering 28 owner proposals / 28 applications, all carrying historical `Released`, and 37 externally coupled groups covering 40 owner proposals / 41 applications plus 52 owner proposals outside the cohort. The single non-`Released` application is externally coupled. Closure topology SHA-256 is `d1efd18f13e178bcd1cf4e90559eba73f8305acedd92a0c2f68b91e42e0c5aed`.

The 52 business-registration groups divide into 18 closed groups covering 39 business proposals / applications and 34 externally coupled groups covering 36 cohort business proposals / applications plus 37 business proposals outside the cohort. The closed route contains 17 contact-owner and 22 non-contact-owner applications; the coupled route contains 17 and 19 respectively. All 75 carry historical `Released`. Closure topology SHA-256 is `d59cc618662288edb98d12c46667e7382e81d3099177b2cbf4ce448d5ef9080b`.

A complete authoritative disposition for a closed group can unlock exact proposal preparation for that identity dimension. An externally coupled group must be reviewed across its full global membership. This does not establish identity from a signal, accept a mapping, resolve reference data or another identity dimension, activate `Released`, freeze a cohort, authorize rehearsal, or authorize production migration.

The eight soft-deleted applications now reproduce as five disjoint, fingerprinted routes: three deletion/identity/reference-only, two Treasury, one fiscal-authority, one permit-authority, and one genuine source-contradiction application. Each route remains behind deletion, identity, reference-data, exact-mapping, freeze, and separate rehearsal-authorization gates as applicable. The identity-plus-financial exception separately requires full global owner-collision-group review and fiscal authority; neither decision alone unlocks exact proposal preparation.

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

The state model remains explicit: source facts are `observed`; the legacy `groups` interpretation is `inferred` from exact source behavior; crosswalks and mappings were `proposed` in this packet and subsequently accepted only through the Board decision recorded below; nothing has been `rehearsed` or `production-applied`.

## Five-Record Accepted Mapping Set

The 2026-08-17 Board decision accepted only the exact prerequisites bound to cohort SHA-256 `bf5af2693e471f336c54bf5e3345cc6b9df8709fceddd5ea1bc63360c3ebddb4` and proposal-package SHA-256 `06907e02c12209115fdd451cceac08b2fa20baba174dbdc1198dc5803b0faa36`.

Acceptance run `prod-historical-financial-cohort-mapping-acceptance-20260817-001` revalidated the immutable snapshot and proposal package before creating five exact line-of-business targets and reconciliations, five owner mappings, five business mappings, and five application mappings through guarded local migration executors. It froze accepted mapping-set SHA-256 `4989d98fee490ba7f38fa294192e0f19592eab7f219f0744a4f36885b590bcf6`. Stable retry returned the same mapping set. Operational finance remained unchanged: 68 assessments, 395 assessment lines, 63 payment schedules, 390 schedule lines, 41 Treasury collections, and 41 receipts before and after acceptance.

The generic application planner identified only `line_of_business_mapping_required` on the five production proposals. A Board-bound application acceptance plan removed only that identity prerequisite after exact targets and accepted reconciliations existed. It did not execute declaration migration, assign fee policy, authorize a future classification catalog, assign official application numbers, or create operational financial records.

Authorization-packet run `prod-five-record-historical-preservation-authorization-20260817-002` uses the unchanged V1 projector and full financial dependency snapshot but plans only the frozen five source applications. This selected planning boundary avoids an unnecessary full-census projection while preserving the executor's snapshot and proposal invariants. Exactly five proposals are Ready; no selected history has a V1 eligibility exception.

The packet expects five bundles, five schedules, 38 fee lines, zero completed payments, five unpaid schedules, and exactly 2,095,000 scheduled/fee centavos. Its private JSON and Markdown artifacts contain the full assertions and proposed commands. The payload-safe Board packet is `docs/implementation/FIVE_RECORD_HISTORICAL_PRESERVATION_REHEARSAL_AUTHORIZATION_PACKET.md`.

Board-authorized execution 1 completed execute, exact source-to-target audit, rollback, and restoration audit. It preserved the expected five bundles, five schedules, 38 fee lines, zero completed payments, five unpaid schedules, and 2,095,000 scheduled/fee centavos before removing only its own bundles. Operational finance counts remained unchanged, accepted mappings and target applications remained intact, and execution 1 is permanently recorded as rolled back.

## Next-Scale Readiness

Read-only run `prod-historical-financial-next-scale-readiness-20260817-001` revalidated the immutable production snapshot, corrected V1 candidate set, proven five-record cohort, accepted mapping set, permanently rolled-back baseline execution, and preservation dependency. It found six total candidates with the exact proven semantics: the five baseline applications plus one unused candidate. A six-record replay would add only one record and would not materially test scale.

The next coherent deterministic class contains `401` applications, but every member carries `legacy_release_authority_unresolved` in addition to line-of-business reconciliation. The guarded application executor correctly refuses that class because mapping would otherwise materialize unresolved historical release authority in the current domain. No mappings, plans, bundles, or executions were created. `NEXT_SCALE_HISTORICAL_PRESERVATION_REHEARSAL_AUTHORIZATION_PACKET.md` records the failed authorization gates and payload-safe fingerprints.

Production mutation, operational financial migration, historical recalculation, inferred fee identity, cutover, and further preservation execution remain unauthorized.
