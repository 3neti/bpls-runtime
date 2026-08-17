# Accelerated Historical Preservation Rehearsal Report

## Result

The evidence-preserving migration boundary now separates exact historical source assertions from current operational authority. A legacy `Released` assertion is preserved as historical evidence while current release authority, legal effect, validity, and operational eligibility remain false.

Recommendation: `ACCELERATED MIGRATION PATH PROVEN - CONTINUE SCALING`.

## Readiness

The corrected V1 population contains 1,223 application histories. Semantic-level classification now yields:

| Class | Count | Current disposition |
| --- | ---: | --- |
| Exact historical-evidence migration class | 407 | Exact mappings accepted; preservation mechanism proven and rolled back |
| Human identity reconciliation | 736 | Blocked from identity mapping; no similarity merge |
| Registry policy reconciliation | 72 | Blocked pending Group-owner, deleted, or related registry disposition |
| Soft-deleted and payment-schedule semantics | 5 | Quarantined pending application/registry reconciliation |
| Financial override plus historical release | 3 | Quarantined pending exact override evidence |

The exact class consists of 401 deterministic historical `Released` applications and six applications compatible with the proven non-release preservation semantics. All 407 now have exact accepted mappings and have completed reversible execute, source-to-target audit, rollback, and restoration-audit rehearsals. No preserved bundle remains.

## Authority Separation

Historical migration now preserves:

- the exact legacy status and source payload hash;
- an explicit historical-only disposition;
- unresolved authority provenance;
- source declaration hashes without guessed current classification;
- false current release authority, legal effect, validity, and operational eligibility.

Operational assessment, schedule creation, collection, receipt, clearance mutation, document mutation, and lifecycle continuation reject historical-only applications. `ExecuteLegacyFinancialSnapshots` and the operational assessment path remain unchanged.

## Rehearsal Progression

All production-derived rehearsals used the immutable production snapshot SHA-256 `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57` and the existing Historical Financial Preservation V1 executor.

| Cohort | Bundles | Schedules | Fee lines | Payments | Centavos preserved | Execute | Audit | Rollback | Restoration |
| ---: | ---: | ---: | ---: | ---: | ---: | --- | --- | --- | --- |
| 25 | 25 | 25 | 166 | 25 | 17,283,133 | PASS | PASS | PASS | PASS |
| 100 | 100 | 100 | 658 | 100 | 86,140,090 | PASS | PASS | PASS | PASS |
| 177 | 177 | 177 | 1,163 | 177 | 157,556,907 | PASS | PASS | PASS | PASS |
| 67 four-paid-quarter | 67 | 268 | 664 | 268 | 81,836,524 | PASS | PASS | PASS | PASS |
| 27 three-paid-quarter | 27 | 108 | 268 | 81 | 61,636,116 | PASS | PASS | PASS | PASS |
| 4 two-paid-schedule | 4 | 8 | 33 | 8 | 5,135,676 | PASS | PASS | PASS | PASS |
| 1 one-paid-of-four | 1 | 4 | 9 | 1 | 704,864 | PASS | PASS | PASS | PASS |
| 5 baseline unpaid | 5 | 5 | 38 | 0 | 2,095,000 | PASS | PASS | PASS | PASS |
| 1 remaining assessment | 1 | 1 | 8 | 0 | 382,500 | PASS | PASS | PASS | PASS |
| **Complete exact class** | **407** | **696** | **3,007** | **660** | **412,770,810** | **PASS** | **PASS** | **PASS** | **PASS** |

The complete class contains 36 unpaid schedules and 397,445,008 paid centavos. Source and target counts and centavo totals agreed exactly. All preserved bundles were rolled back; accepted mappings and target applications remain intact as prerequisite evidence.

## Isolation

The following operational counts were identical before, during, and after every rehearsal:

| Table | Count |
| --- | ---: |
| assessments | 68 |
| assessment_lines | 395 |
| payment_schedules | 63 |
| payment_schedule_lines | 390 |
| treasury_collections | 41 |
| receipts | 41 |

No formula ran. No historical amount was recalculated. No fee identity, receipt, collection, lifecycle transition, external call, production-source mutation, or present authority assertion was created.

## Frozen Evidence

The largest completed cohort is bound to:

- cohort SHA-256 `384fc8aa939038fa115e4a0a84c542c1e4e7ea85fae2b144a0981f254d70c7ad`;
- proposal package SHA-256 `17613b75e15bcb9dc24f9d36959baa79454442c01f9cfb607889c7e86af69230`;
- accepted mapping-set SHA-256 `3168e68a0ef9152087dccd2ebc6b21b2469417a4e953388fc37198ea51cdceab`;
- preservation dependency SHA-256 `5d8ff28a102188d3efa61a2e3c9406cfba920c57b76eeb730c7816caed083035`.

Private execution evidence remains under `storage/app/private/legacy-migrations/` and is excluded from Git.

## Scale Observation

The 177-record execute completed in 148.93 seconds and originally required 380.05 seconds for source-to-target audit. Financial proposals are now indexed once by exact application/schedule relationships and reused by planning, execution, and audit. The 67-record four-quarter audit completed in 2.50 seconds with unchanged source projections, projection hashes, centavo assertions, operational isolation, rollback, and restoration guarantees.

## Next Boundary

Scaling stops at the completed 407-record exact class. The next frontier is the 736 human-identity cases. Read-only characterization reduces them to 40 evidence shapes, 469 owner collision groups, and 80 business collision groups. Twelve applications expose a bounded subclass in which nine unique owner proposals are collision-free while business identity remains ambiguous. These are proposals for independent owner review only; no owner, business, or application mapping was accepted from similarity evidence.

Production migration, operational financial migration, cutover, current permit release, and future fiscal-policy activation remain unauthorized.
