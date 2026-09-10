# IPIL RESCUE GATE 4 — SOURCE-TO-BPLS MAPPING READY FOR SEED IMPLEMENTATION REVIEW

Status: **PASS — DESCRIPTIVE, AGGREGATE-ONLY, OFFLINE**

Observed: 2026-09-11

Normative authority: [Ipil Source-to-BPLS Mapping Specification V1](IPIL_SOURCE_TO_BPLS_MAPPING_SPECIFICATION_V1.md)

Decision record: [Ipil Mapping Decision Register](IPIL_MAPPING_DECISION_REGISTER.md)

Mapping profile: `ipil-rescue-mapping-v1.0.0` / `bpls.ipil-source-to-bpls-mapping.v1`

Profile identity SHA-256: `edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c`

## Evidence Boundary

Analysis used only finalized local corpus `ipil-20260910t153224z-2ab19c17` at `storage/app/private/ipil-rescue/snapshots/ipil-20260910t153224z-2ab19c17`, fingerprint `d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81`. `ipil:rescue:verify` passed at the final path: 97 files and 324,873 SourceIdentities, with no network access, evidence repair, or domain writes.

The analysis read the corpus without changing it. It used counts, hashes, type/shape summaries, normalized collision counts, edge counts, date ordering, and arbitrary-precision decimal comparisons. It did not emit taxpayer values, raw source IDs, filenames, document hashes, credentials, or media. It did not contact Ipil Cloud or execute `ipil:seed`.

## Corpus Census

The corpus contains 53 tables, 324,833 rows, and 48 nonempty datasets. The normative disposition ledger accounts for all 53 tables and all embedded LOB, fee, document, payment, report, and dynamic-field concepts. Authentication/session/security rows selected for explicit non-interpretation total 78,643 and are excluded from BPLS interpretation while remaining in immutable source evidence.

Largest evidence classes are 127,363 activity logs, 76,352 refresh tokens, 45,413 billing-group lines, 25,372 billing-group records, 14,615 permit clearances, 7,648 schedules, 5,874 payments, 4,381 unit/variable observations, 3,212 businesses, 3,194 owners, 3,137 Applications, and 2,766 permits.

## Structural Relationship Findings

`FACT` — Core continuity is structurally strong:

| Edge | Present | Broken | Finding |
| --- | ---: | ---: | --- |
| business -> owner | 3,212 | 0 | Every business has a source owner. |
| Application -> business | 3,137 | 0 | Every Application has a source business. |
| Application -> owner | 3,137 | 0 | Every Application has a source owner. |
| Application owner vs business owner | 3,137 | 0 disagreements | The two paths agree throughout the corpus. |
| schedule -> Application | 7,648 | 69 | Broken sources remain historical exceptions. |
| payment -> Application | 5,874 | 3 | Three source events lack a rescued Application. |
| payment -> schedule | 5,874 | 56 | Fifty-six source events lack a rescued schedule. |
| clearance -> Application | 14,615 | 0 | All clearance rows have an Application. |
| clearance -> type | 14,615 | 110 | Type-label snapshots remain usable; target identity is unresolved. |
| permit -> Application | 2,751 present, 15 absent | 0 among present | Fifteen permit claims are business-scoped only. |
| permit -> business / owner | 2,766 each | 10 / 10 | Broken claims stay preserved, never repaired by name. |
| unit/variable -> Application | 4,359 present | 10 | Twenty-two rows are business-scoped; ten Application references are broken. |

`IMPLEMENTATION_DECISION` — Exact source edges may drive one-to-one historical projection. Broken edges drive orphan/unresolved cohorts and never inferred joins.

## Identity and Duplicate Signals

`FACT` — Normalization was used only to count collision risk:

| Signal | Duplicate groups | Rows in groups | Largest group |
| --- | ---: | ---: | ---: |
| owner normalized name | 286 | 697 | 34 |
| owner normalized name + birth date | 166 | 371 | 5 |
| owner email | 289 | 1,763 | 354 |
| owner phone | 310 | 1,350 | 69 |
| owner TIN | 2 | 4 | 2 |
| business normalized name | 169 | 412 | 38 |
| business owner + normalized name | 12 | 24 | 2 |
| business registration number | 202 | 470 | 15 |
| business owner + registration number | 22 | 54 | 6 |
| application number | 2 | 4 | 2 |

`INFERENCE` — Source-key one-to-one historical projection is deterministic; legal-person/business deduplication is not. Name, contact, birth date, TIN, registration, or combinations cannot authorize merges. The profile therefore has 3,194 owner and 3,212 business source-key candidates, but zero accepted deduplication/merge decisions.

Owners comprise 2,756 `Single` and 438 `Group` rows. First name, last name, birth date, email, and mobile are populated on all rows; 98 lack address and one lacks the source location triplet. Sixty-six owners have no business, while 68 owners have multiple businesses covering 152 business rows. No normalized collision group qualifies as `ESTABLISHED_SAME_IDENTITY`; all 286 owner-name collision groups remain `AMBIGUOUS` review signals and every source identity remains distinct. No owner is orphaned from its own table identity.

## Business and Application Continuity

`FACT` — 3,056 businesses have Applications and 156 do not. Of those with Applications, 2,984 have one, 66 have two, four have three, one has four, and one has five. Seventy-two businesses have multiple Applications; only one spans multiple submitted calendar years in this corpus. All 2,621 Renewal rows lack an earlier-year source Application in this bounded snapshot.

Forty-eight normalized business-name-plus-address collision groups contain 128 rows (largest 34); adding source owner reduces this to two groups/four rows. No group is accepted as the same business. Source business identity, not fuzzy name/address similarity, governs continuity; potential re-registration/name/owner-change interpretations remain `AMBIGUOUS`.

`INFERENCE` — Business source identity is the only safe continuity anchor. Renewal is a literal type, not evidence that a predecessor was rescued. Application numbers are presentation evidence only because two normalized duplicate groups exist and municipal numbering authority is not accepted.

Application vocabulary is exact: 2,621 `Renewal`, 461 `New`, and 55 `Additional`; 2,907 `Released`, 199 `Assessment`, 28 `Pending Payment`, and 3 `Draft`. Forty-seven Applications are soft-deleted. Historical `Released` is not mapped to operational `released`.

## Line-of-Business and Declaration Findings

There are 4,236 embedded LOB items across 3,016 Applications; 121 Applications have no LOB item. Five items have no usable category label. The remaining items contain 562 normalized category labels.

Against the source `groups` catalog, 4,225 items have one normalized-name candidate, four match a duplicated normalized group label, and seven have no group candidate. Across distinct labels, 559 are unique source-internal candidates, one is ambiguous, and two are unmatched. These are `PROBABLE` links inside the source, not accepted BPLS LOB mappings.

Financial configuration is materially embedded in declarations: 3,796 lines carry nonempty fee exclusions and 4,215 carry nonempty variable mappings. Numeric declaration values also contain non-numeric source states: 3,695 supplied capital values and 558 supplied gross values do not parse as exact decimals; one parseable gross value is not cent-exact. No source value may be replaced with zero merely to satisfy the current line model.

## PSGC Findings

The source has 44 barangays; 24 normalize exactly to names in current `psgc.ipil-barangays.v1` and 20 do not. Thirteen source barangays have a code, but none equals a current ten-digit target PSGC code. Exact-name candidates cover 2,176/3,212 businesses (67.75%) and 2,703/3,194 owners (84.63%). Fourteen business and 13 owner barangay references are broken; one owner lacks the province/city/barangay triplet.

Unmatched labels include misspellings/variants, subdivisions or place labels, locations outside the canonical Ipil barangay list, and test values. These are safe reference-data observations, but no fuzzy repair was accepted. PSGC target readiness is therefore 0% accepted and 100% dispositioned: 24/44 `PROBABLE` proposals, 20/44 unresolved.

## Financial and Monetary Findings

`FACT` — Raw decimal sums and precision characteristics are:

| Evidence | Rows | Exact source sum | Non-cent-exact values |
| --- | ---: | ---: | ---: |
| Application `totalFees` | 2,929 | PHP 120,498,631.06218000103804 | 348 |
| schedule `totalAmount` | 7,648 | PHP 372,252,667.8500 | 24 |
| schedule `paidAmount` | 7,648 | PHP 93,295,317.20 | 0 |
| schedule penalty | 7,648 | PHP 313,487.68 | 0 |
| schedule surcharge | 7,648 | PHP 1,939,608.79 | 0 |
| payment amount, all statuses | 5,874 | PHP 95,441,657.72 | 0 |
| billing-group record total | 25,372 | PHP 74,430,458.59600000001025 | 57 |
| billing-group line amount | 45,413 | PHP 74,434,859.596 | 2 |

All 7,648 schedule totals equal their embedded fee-line sum plus persisted surcharge and penalty. The simpler fee-only equation fails for 1,406 schedules. Schedule paid amounts equal the sum of completed payment events for every schedule, preserving the Gate 3 anchor PHP 93,295,317.20.

Schedule/payment multiplicity is 1,892 schedules with no payment event, 5,699 with one, 54 with two, two with three, and one with five. Every 5,741 `paid` schedule has exactly one completed event; 56 also have failed attempts and one also has a pending attempt. Twelve pending schedules have failed events. All three partial schedules have one completed event. This proves attempts and completed collections must not be collapsed.

There are three edited schedule fee lines, nine Application fee overrides, and one broken fee-override-to-fee edge. Application totals do not directly equal the sum of schedule `totalAmount` for 2,925 of 2,929 assessed Applications, so no undocumented equation may be imposed. The source schedule totals incorporate cadence/section semantics and persisted late charges; the exact rows are authoritative historical evidence.

`CONTRADICTION` — 348 Application totals, 24 schedule totals, 57 billing totals, two billing lines, and one gross declaration cannot be represented losslessly as integer centavos. Gate 5 needs exact-decimal/raw-lexeme preservation rather than rounding into current money columns.

## Payment, Receipt, Clearance, and Permit Findings

Payments comprise 5,744 completed, 129 failed, and one pending event. All 5,874 transaction numbers are distinct. Receipt-number claims exist on 5,873 events, but only 5,325 normalized values are distinct: 196 duplicate groups contain 744 events, up to five per group. Receipt claims therefore cannot be inserted into the current globally unique issued-receipt model.

The corpus has 14,615 clearances: 13,826 completed and 789 incomplete. Most Applications have five clearance rows; seven have 15. One hundred ten type references are broken. Completion and type labels remain historical assertions without present sufficiency or authority.

Permits comprise 2,766 claims, 2,763 literal `Active` and three `Expired`. Permit numbers are present and distinct on 2,751 Application-linked rows; 15 rows lack an Application. No literal status, number, or timestamp creates a current permit or legal authority.

## Temporal Findings

One Application records assessment before submission. Of 2,907 literal `Released` Applications, 2,595 lack `releasedAt` and 2,778 lack `approvedAt`. These are source contradictions/absences, not invitations to infer timestamps. No observed permit has release before issue or expiry before release under parseable timestamps.

All Applications have `submittedAt`; its year agrees with the Convex creation timestamp. Linked schedule due dates, payment dates, and permit release dates have the same calendar year as their Application submission date. Gate 5 should use `submittedAt` for historical Application year, preserve original timezone-bearing strings and `_creationTime`, and avoid converting a date-only field through a timezone. The corpus contains 3,128 submitted Applications in 2026 and nine in 2025; permit release evidence includes 2,745 in 2026, 12 in 2024, six in 2025, and three in 2023.

## Media and Pricing Findings

Media remains exactly 35 acquired objects / 37,234,582 bytes and 16 typed metadata relationships. The manifest carries 11 `rescued`, 19 `unassociated-byte`, three conservative `corrupt` MIME classifications, and two `duplicate-content` entries. The only business document is DTI; SEC is supported but absent and BIR has no dedicated path. Media mapping readiness is 16/16 relationships dispositioned and 35/35 objects accounted, but zero Spatie imports authorized and 19 associations unresolved.

Pricing remains a separate five-record, candidate-only interpreted evidence class. Its manifest SHA-256 is `98695a5b72d13a7a5e11e3dbb0d00bc2de3f6f89b4c81d695d7100bed6fbece5`; records SHA-256 is `1db805d6f9223f30147ee2b955191981f3b8912343b51873af2761ffb29ae207`; raw database fingerprint is `96b18d6cddda22fd0278616f02ce1903a9e416fe744be7c607d87779509cf3fc`.

Individual financial rows do not carry currency. The source platform setting is `PHP`, the configured municipality is Ipil, and the reviewed municipal financial/report context is peso-denominated. PHP is therefore a `PROBABLE`, explicitly evidenced bundle-level mapping, not an unexplained default.

The pricing evidence input contains 827 `division_groups` and 828 `groups`, while the finalized database snapshot contains 832 and 833 respectively. This five-row delta in each dataset is explicit temporal/evidence-class drift, not a reason to rewrite either fingerprint. Pricing identity and current fiscal authority remain 0% accepted.

## Current BPLS Fit

The codebase already provides the correct safety seams: `LegacySource`, `LegacyImportBatch`, checksum-bound `LegacyRecord`, mapping plans/proposals/executions, `LegacyIdMapping`, migration exceptions/validations, non-operational `PermitApplicationStatus::HistoricalEvidence`, legacy document reconciliation, and immutable historical financial preservation bundles. Spatie collection `application_documents` uses the private local disk.

Material gaps for Gate 5 review are:

- enforced historical-only registry scopes/guards for owners and businesses;
- a complete historical Application bundle capable of preserving every embedded declaration and lifecycle claim;
- historical financial bundle V2 support for exact decimals/raw lexemes, partial schedules, multiple attempts, duplicate receipt claims, edited fees, overrides, and broken edges;
- explicit source-to-PSGC and source-to-LOB proposal stores with no implicit acceptance;
- an orphan/unresolved evidence projection for child rows whose source parents are absent;
- streaming, production-engine rehearsal and indexes suitable for 324,833 rows without exposing PII.

## Quantified Mapping Readiness

| Domain | Disposition readiness | Semantic acceptance/readiness | Gate 4 result |
| --- | ---: | ---: | --- |
| Table inventory | 53/53 (100%) | Not applicable | Ready |
| Source rows | 324,833/324,833 (100%) class-addressable | No execution authorized | Ready for planner |
| Owner/business/Application core edges | 9,486/9,486 expected edges accounted; 0 broken | 0 identity merges accepted | Ready for one-to-one historical plan |
| Owners | 3,194/3,194 source-key candidates | 0 deduplication decisions | Ready with no-merge rule |
| Businesses | 3,212/3,212 source-key candidates | 14 barangay edges unresolved | Ready with literal location fallback |
| Applications | 3,137/3,137 structurally linked | 0 operational statuses authorized | Ready as historical evidence only |
| LOB items | 4,236/4,236 dispositionable | 0 BPLS LOB crosswalks accepted | Ready for evidence; target mapping deferred |
| PSGC | 44/44 dispositioned | 0 accepted; 24 probable, 20 unresolved | Ready with null-code fallback |
| Schedules/payments | 13,522/13,522 dispositionable | 0 operational finance writes authorized | Ready for historical bundle V2 design |
| Receipt claims | 5,873/5,873 identified | 0 issued-OR mappings authorized | Ready as claims only |
| Clearances/permits | 17,381/17,381 dispositionable | 0 present authority mappings | Ready as historical claims |
| Media | 35/35 objects and 16/16 relationships accounted | 19 unresolved; 0 Spatie imports | Ready for reconciliation design only |
| Pricing | 5/5 evidence records independently versioned | 0 FeeRule/current-policy acceptance | Ready for audit comparison only |

## Cull Readiness-to-Seed Review Decision

Every source table and embedded concept now has a deterministic disposition and fallback. All known broken edges, collisions, non-cent values, duplicate OR claims, missing timestamps, unresolved media, and pricing drift remain visible. The mapping contract can therefore drive a synthetic-first, fail-closed Gate 5 seed implementation review without requiring semantic invention.

Gate 5 is not authorized to execute a real seed by this report. It must first implement planning, exact corpus/profile binding, historical-only safeguards, arbitrary-precision evidence preservation, idempotency, rollback, and aggregate audit using synthetic fixtures, then stop for an execution decision.

Canonical mapping specification: `docs/rescue/IPIL_SOURCE_TO_BPLS_MAPPING_SPECIFICATION_V1.md`

Canonical corpus: `ipil-20260910t153224z-2ab19c17`

Corpus fingerprint: `d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81`

Mapping profile/version: `ipil-rescue-mapping-v1.0.0` / `bpls.ipil-source-to-bpls-mapping.v1`; identity SHA-256 `edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c`

**GATE 4: PASS — MAPPING SPECIFICATION READY FOR SEED IMPLEMENTATION**
