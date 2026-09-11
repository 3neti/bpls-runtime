# Ipil Rescue Gate 6 — First Local Historical Materialization

Status: **PASS — READY FOR REALITY/PARITY VALIDATION**

Executed: 2026-09-11

Governing authority: [Ipil Rescue and Parity Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md), [Seed Implementation Plan V1](IPIL_SEED_IMPLEMENTATION_PLAN_V1.md), and the unchanged private Gate 5 Seed Execution Manifest.

## Frozen Binding

| Artifact | Accepted and executed identity |
| --- | --- |
| Starting repository SHA | `39053441d503db924da7e2f47f0f1f58f0fdf0da` |
| Rescue Corpus | `ipil-20260910t153224z-2ab19c17` |
| Corpus semantic fingerprint | `d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81` |
| Mapping profile | `ipil-rescue-mapping-v1.0.0` |
| Mapping profile identity | `edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c` |
| Seed Plan | `ipil-seed-plan-874d26eb21ed0041` |
| Regenerated/accepted plan fingerprint | `874d26eb21ed004147bdec32fdbd4cbf097101defdf37a7697776ea42080d9fb` |
| Execution Manifest semantic fingerprint | `c906494f1c37a4375d78343037e4c5a443986aba9df65217ab4efa4fdbfb64b6` |
| Gate 6 authorization fingerprint | `1c380b11e7e54b4eae696d405c33a1e9129867eda5fa6fff9b08876d0d966280` |
| Target database identity | `0f178b3d4af1e8d467c11fc35b3f6d7e409f50878be655fdebb62ec74c97b7dd` |

The target was the dedicated local PostgreSQL 17 database `bpls_ipil_parity_gate6_20260911`, reached through the local `/tmp` socket. It is disposable, non-Cloud, and distinct from every pre-existing database. `migrate:fresh` was run only against this target, followed by `bpls:install`. The installation integrity check passed after materialization and continued to report an empty operational transaction state.

The Gate 5 manifest was not edited. Its `execution_authorized=false`, `domain_writes=false`, and `network_access=false` values remain evidence of the Gate 5 boundary. Gate 6 created a separate private, Git-ignored authorization record bound to the manifest fingerprint and exact local database identity.

## Execution Results

Import Run 1 was `ipil-g6-20260911031855-nof4n8kw` and completed in 37.843 seconds (38 seconds by persisted timestamps). Import Run 2 was `ipil-g6-20260911032019-lvoqxocv` and completed in 29.867 seconds (30 seconds by persisted timestamps).

| Phase | Result | Run 1 materialization evidence |
| --- | --- | --- |
| A — bind/inventory | PASS | 324,873 SourceIdentities; 246,230 non-auth payload evidence records; zero auth secret payloads staged |
| B — reference proposals | PASS | 44 barangays; 24 normalized proposals; 20 unresolved; zero automatic PSGC assignments |
| C — owners | PASS | 3,194/3,194; zero merges; 286 collision groups retained as findings; zero Users created |
| D — businesses | PASS | 3,212/3,212; 3,212 deterministic owner edges; zero fuzzy merges; 48 collision groups retained |
| E — Applications | PASS | 3,137/3,137; all structurally non-operational |
| F — declarations/classifications | PASS | 4,236 LOB items and 4,388 measurement rows; zero current Treasury LOB assignments |
| G — permit finance | PASS | 7,648 schedules, 5,874 payment events, and 5,873 receipt claims; zero current liabilities/receipts |
| H — billing/report finance | PASS | 71,032 historical billing/report rows plus four deferred print layouts; zero inferred permit-finance joins |
| I — permits/clearances | PASS | 2,766 permit claims and 14,615 clearance claims; zero current permits/certifications |
| J — media reconciliation | PASS | 35 source objects; 16 associated copies imported; 19 unresolved retained outside Spatie |
| K — search/read projection | PASS | all 3,137 Applications join to historical owner/business projections and remain non-actionable |
| L — audit/replay readiness | PASS | deterministic audit and replay boundary established |

Run 2 created zero SourceIdentities, evidence records, owners, businesses, Applications, classifications, measurements, schedules, payments, receipt claims, permit claims, clearance claims, media evidence records, or Spatie media. It reported zero duplicates, unexpected changes, and conflicts. The two persisted import-run records are audit evidence; all source-derived targets remain singular.

## Quantitative Parity

| Dimension | Expected | Materialized/audited |
| --- | ---: | ---: |
| Owners | 3,194 | 3,194 |
| Businesses | 3,212 | 3,212 |
| Historical Applications | 3,137 | 3,137 |
| Renewal / New / Additional | 2,621 / 461 / 55 | 2,621 / 461 / 55 |
| Released / Assessment / Pending Payment / Draft | 2,907 / 199 / 28 / 3 | 2,907 / 199 / 28 / 3 |
| LOB items | 4,236 | 4,236 |
| Payment schedules | 7,648 | 7,648 |
| Payments | 5,874 | 5,874 |
| completed / failed / pending | 5,744 / 129 / 1 | 5,744 / 129 / 1 |
| Receipt-number claims | 5,873 | 5,873 |
| Duplicate receipt groups / events | 196 / 744 | 196 / 744 |
| Permit claims | 2,766 | 2,766 |
| Permit claims lacking Application | 15 | 15 |
| Broken Permit Business / Owner edges | 10 / 10 | 10 / 10 |
| Clearance claims | 14,615 | 14,615 |
| Broken clearance-type edges | 110 | 110 |
| Media source objects / unresolved | 35 / 19 | 35 / 19 |
| Pricing identities | 5 | 5, candidate-only |

All 348 non-cent-exact Application totals and 24 non-cent-exact schedule totals retain their original JSON numeric lexeme and exact normalized decimal. No rounding, binary floating-point conversion, current `Price`, current `FeeRule`, or present liability participated. Completed historical payments and aggregate schedule-paid evidence both audit to exactly **PHP 93,295,317.20**.

Gate 6 also materialized accepted source-orphan finance without guessing: 69 schedules lack a rescued Application, three payments lack a rescued Application, and 56 payments lack a rescued schedule. These were already governed by the Mapping Specification's broken-edge preservation rule; their discovery exposed an implementation assumption, not a mapping change.

## Media, Privacy, and Operational Isolation

All 35 media identities remain accounted for. Sixteen manifest-associated objects were copied without byte mutation: the one DTI business document uses private collection `application_documents`; 15 legacy report/layout/platform artifacts use separate private collection `legacy_generated_artifacts`. All managed-copy SHA-256 values equal the Rescue Corpus values. The 19 unresolved objects remain only in immutable source evidence and were not attached. No R2, S3, Laravel Cloud, or public disk was used.

The audit proved zero history-created Users, operational Applications, current Payment Schedules, Treasury Collections, Receipts, Permits, signatures, routing work, Payment Orders, post-payment certifications, lodging manifests, or historical media on operational Applications. Current Fee/LOB/reference installation integrity remained green. No current Price execution, permit issuance Action, OR issuance, account claiming, notification, job, Post-it, or Nelson lifecycle transition was invoked.

The real PostgreSQL database, private authorization record, detailed command output, source payloads, and managed media are Git-ignored. No taxpayer row, PII-bearing report, rescued byte, database dump, credential, or token enters Git. The immutable corpus was read and verified only; materialization required neither live Ipil access nor any source write.

## Verification and Gate Decision

`ipil:audit` passed after Run 1 and after Run 2. The final audit had zero failed checks, two completed import runs, zero second-run creations, and identical count and monetary anchors. The bounded read-only inspection proved owner → business → Application → finance/permit/document navigation, single- and multi-Application histories, Additional history, orphan claims, exact-decimal evidence, and private media projection without exposing PII in this report.

The PostgreSQL database occupied approximately 483 MB. A 25-row business name projection executed in 0.755 ms; an indexed Application year/type/status projection in 0.090 ms. The first cross-history specimen took 6.734 ms and revealed missing foreign-key indexes. After adding those indexes it executed in 0.142 ms, with schedule, payment, and permit joins using indexes.

Focused Gate 6 verification passed 26 tests / 217 assertions. The final full repository suite passed 912 tests with one skipped test and 16,018 assertions. Pint's repository-wide read-only check, targeted PHPStan for all changed PHP code, strict Composer validation, and Git whitespace validation passed. A repository-wide PHPStan observation still reports 345 pre-existing findings outside this bounded change; no Gate 6 changed file appears in that result. No frontend, browser, TypeScript, build, or deployment check was applicable.

The next bounded gate is **Gate 7 — Reality Validation and Historical Product/Report Parity**: expose reviewed read-only historical projections, validate search/filter/report semantics against Ipil evidence, and classify visible divergence. It must not begin broad UI redesign, cleanup, deduplication, PSGC/LOB acceptance, Cloud media movement, or production cutover.

**GATE 6: PASS — LOCAL IPIL HISTORY READY FOR REALITY/PARITY VALIDATION**
