# Five-Record Historical Preservation Rehearsal Authorization Packet

Status: **READY FOR FIVE-RECORD REHEARSAL AUTHORIZATION**

Prepared: 2026-08-17

This packet requests authorization only. The production-derived historical preservation rehearsal has not been executed.

## Frozen Evidence

| Evidence | SHA-256 |
|---|---|
| Immutable production snapshot | `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57` |
| Frozen five-record cohort | `bf5af2693e471f336c54bf5e3345cc6b9df8709fceddd5ea1bc63360c3ebddb4` |
| Accepted prerequisite proposal package | `06907e02c12209115fdd451cceac08b2fa20baba174dbdc1198dc5803b0faa36` |
| Frozen accepted mapping set | `4989d98fee490ba7f38fa294192e0f19592eab7f219f0744a4f36885b590bcf6` |
| Preservation dependency snapshot | `e137307e9f7fde831741fbee885c1e04b830e55de491a9c83070e3248406180f` |

The five application references are privately retained as redacted SHA-256 prefixes in the checksum-bound machine packet. Raw source identities and payloads remain outside Git.

## Accepted Prerequisites

- Five exact legacy location chains are preserved as historical registry provenance; no normalized Laravel location identity was invented.
- Five exact legacy `group -> division_group -> division -> major` chains produced five explicit Laravel `LineOfBusiness` targets.
- Five exact source-group reconciliations are accepted for migration representation only.
- Five owner mappings, five business mappings, and five application mappings were created through the existing guarded migration executors.
- The application acceptance plan removed only the now-satisfied `line_of_business_mapping_required` identity reason. Declaration migration, fee policy, official numbering, and future classification-catalog authority remain unexecuted and unauthorized.
- A repeated acceptance with the same decision inputs returned the same frozen mapping set and did not create divergent mappings.

## Exact Rehearsal Assertions

| Historical evidence | Expected |
|---|---:|
| Application bundles | 5 |
| Payment schedules | 5 |
| Historical fee lines | 38 |
| Completed payments | 0 |
| Unpaid schedules | 5 |
| Scheduled amount | 2,095,000 centavos |
| Fee-line amount | 2,095,000 centavos |
| Paid amount | 0 centavos |
| Payment amount | 0 centavos |

The rehearsal may write only:

- `legacy_historical_financial_preservation_executions`
- `legacy_historical_financial_preserved_bundles`

It must not change `assessments`, `assessment_lines`, `payment_schedules`, `payment_schedule_lines`, `treasury_collections`, or `receipts`.

## Proposed Commands

These commands are recorded but have **not** been run:

```bash
php artisan legacy:execute-historical-financial-preservation 2 --proposal=307 --proposal=308 --proposal=309 --proposal=310 --proposal=311 --run-id=prod-five-record-historical-preservation-authorization-20260817-002-execute --execute --confirm-execute --json
php artisan legacy:audit-historical-financial-preservation {execution-id-from-execute} --json
php artisan legacy:rollback-historical-financial-preservation {execution-id-from-execute} --rollback --confirm-rollback --json
php artisan legacy:audit-historical-financial-preservation-restoration {execution-id-from-execute} --mapping-set=1 --json
```

The numeric IDs above identify records in the current private local reconciliation database and are valid only while every frozen fingerprint still passes. Any dependency change requires a newly generated packet.

## Fail-Closed Boundary

Execution must refuse if any source, cohort, proposal-package, accepted mapping-set, target, projection, or dependency fingerprint changes; if the ready selection differs from exactly five; if a selected history has any V1 eligibility reason; if any bundle already exists; or if either explicit execution confirmation is absent.

Audit must reproduce every count and centavo total exactly and prove operational financial counts are unchanged. Rollback may remove only unchanged, unreviewed, and unreferenced bundles. Restoration audit must prove zero bundles remain, accepted mappings still pass their frozen audit, source records remain, and operational financial counts equal the pre-execution values.

## Private Evidence

The authoritative machine packet is stored at:

`storage/app/private/legacy-migrations/IPIL-CONVEX-SNAPSHOT-56FAD41ABBDEAE8D/prod-convex-stage-reference-catalog-v2-20260816-224400/historical-financial-preservation-authorization/prod-five-record-historical-preservation-authorization-20260817-002`

Production mutation, historical recalculation, formula execution, inferred fee identity, operational financial migration, cutover, and the rehearsal itself remain unauthorized.
