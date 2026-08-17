# Accelerated Historical Preservation Rehearsal Report

## Result

The evidence-preserving migration boundary now separates exact historical source assertions from current operational authority. A legacy `Released` assertion is preserved as historical evidence while current release authority, legal effect, validity, and operational eligibility remain false.

Recommendation: `ACCELERATED MIGRATION PATH PROVEN - CONTINUE SCALING`.

## Readiness

The corrected V1 population contains 1,223 application histories. Semantic-level classification now yields:

| Class | Count | Current disposition |
| --- | ---: | --- |
| Exact historical-evidence migration class | 407 | Migratable after exact mapping acceptance |
| Human identity reconciliation | 736 | Blocked from identity mapping; no similarity merge |
| Registry policy reconciliation | 72 | Blocked pending Group-owner, deleted, or related registry disposition |
| Soft-deleted and payment-schedule semantics | 5 | Quarantined pending application/registry reconciliation |
| Financial override plus historical release | 3 | Quarantined pending exact override evidence |

The exact class consists of 401 deterministic historical `Released` applications and six applications already compatible with the proven non-release preservation semantics. Of the 401 historical-release applications, 302 have accepted exact mappings and completed reversible rehearsals. The remaining 99 are exact candidates across different persisted schedule/payment topologies; they were not inferred or accepted by this slice.

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
| **Total coherent class** | **302** | **302** | **1,987** | **302** | **260,980,130** | **PASS** | **PASS** | **PASS** | **PASS** |

Each cohort had zero unpaid schedules. Source and target counts and centavo totals agreed exactly. All preserved bundles were rolled back; accepted mappings and target applications remain intact as prerequisite evidence.

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

The 177-record execute completed in 148.93 seconds, source-to-target audit in 380.05 seconds, rollback in 0.31 seconds, and restoration audit in 0.52 seconds. Integrity remained exact, but audit wall time increased materially. This is an evidence-processing performance concern, not a migration semantic failure; the next scaling slice should index financial proposals by application/schedule while preserving identical projection hashes and audit assertions.

## Next Boundary

The next scale may proceed autonomously. It should exercise the remaining 99 exact historical-release candidates by their actual persisted topology, beginning with the 67-record four-paid-quarter class. Identity acceptance remains exact and evidence-backed; the 816 unresolved applications remain quarantined by their smallest unresolved semantic class.

Production migration, operational financial migration, cutover, current permit release, and future fiscal-policy activation remain unauthorized.
