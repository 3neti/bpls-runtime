# Ipil Rescue Gate 5 — Offline Seed Planner Report

Status: **PASS — OFFLINE SEED PLAN READY FOR FIRST LOCAL MATERIALIZATION REVIEW**

Effective: 2026-09-11

Implementation contract: [Ipil Seed Implementation Plan V1](IPIL_SEED_IMPLEMENTATION_PLAN_V1.md)

## Gate Outcome

Gate 5 implemented and exercised a deterministic, offline, read-only seed planner against the finalized Rescue Corpus. It planned all 324,873 source identities—324,833 database rows, 35 media identities, and 5 independently versioned pricing identities—with zero omissions and no canonical database write. The accepted Gate 4 mapping profile required no revision.

```text
corpus: ipil-20260910t153224z-2ab19c17
corpus fingerprint: d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81
mapping profile: ipil-rescue-mapping-v1.0.0
profile identity: edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c
plan: ipil-seed-plan-874d26eb21ed0041
plan fingerprint: 874d26eb21ed004147bdec32fdbd4cbf097101defdf37a7697776ea42080d9fb
execution manifest: bpls.ipil-seed-execution-manifest.v1
execution-manifest fingerprint: c906494f1c37a4375d78343037e4c5a443986aba9df65217ab4efa4fdbfb64b6
```

Private artifacts:

```text
storage/app/private/ipil-rescue/plans/ipil-seed-plan-874d26eb21ed0041/seed-plan.json
storage/app/private/ipil-rescue/plans/ipil-seed-plan-874d26eb21ed0041/seed-execution-manifest.json
```

They contain safe aggregates and hashed/bound identities only, are Git-ignored, and grant no execution authority.

## Complete Disposition

| Disposition | Source identities |
| --- | ---: |
| `MAP_AS_HISTORICAL_EVIDENCE` | 115,854 |
| `REFERENCE_DATA` | 2,233 |
| `PRESERVE_UNINTERPRETED` | 127,466 |
| `DEFER` | 677 |
| `IGNORE_WITH_EVIDENCE_BASED_REASON` | 78,643 |
| `MAP` | 0 |
| `BLOCKED` | 0 |
| **Total** | **324,873** |

Each of the 53 table counts closes to the verified manifest. Media closes to 35 and pricing to 5. The planner streams the private SourceIdentity registry, rejects duplicates/unknown datasets, and records only aggregate dataset/disposition counts. There are zero unplanned identities.

## Accepted Mapping Evidence Preserved

- Owners: 3,194 planned one-to-one; 286 collision groups covering 697 rows; zero automatic merges and zero Users.
- Businesses: 3,212 planned by source identity; 48 collision groups covering 128 rows; zero fuzzy merges.
- Applications: 3,137 historical and non-operational—2,621 Renewal, 461 New, 55 Additional; literal statuses are 2,907 Released, 199 Assessment, 28 Pending Payment, and 3 Draft.
- Classifications: 4,236 historical LOB items; 562 usable normalized labels; 559 probable source-group candidates, 1 ambiguous, 2 unmatched, and 5 unlabeled. No current BPLS LOB is activated.
- Barangays: 44 literals; 24 normalized proposals; 20 unresolved; zero automatic PSGC assignments.
- Finance: 7,648 schedules and 5,874 payment events; schedule statuses 5,741 paid, 1,904 pending, 3 partial; payment statuses 5,744 completed, 129 failed, 1 pending. The PHP 93,295,317.20 anchor is retained without current pricing.
- Exactness: 348 Application totals and 24 schedule totals are not cent-exact and remain exact lexemes; rounding is forbidden.
- Receipt claims: 5,873 retained, including 196 duplicate-number groups and all 744 events in those groups; none becomes a current Official Receipt.
- Permit claims: all 2,766 retained; 2,751 Application-linked, 15 missing an Application, 10 broken Business edges, and 10 broken Owner edges. No Application is fabricated.
- Clearance claims: all 14,615 retained, including 110 broken type references.
- Media: all 35 accounted; the single typed DTI business-document relation is a probable historical `application_documents` proposal, not an import; other typed assets and 19 unresolved objects remain source evidence. SEC and BIR remain unestablished. No Spatie record or managed copy was created.
- Pricing: all 5 candidate-only records remain independent mapping/reference evidence and do not activate `FeeRule`, `Price`, or policy.

Unknown classifications, document labels, PSGC mappings, historical operational equivalents, signatures, lodging manifests, and broken descriptive relationships are non-blocking only because the lossless historical/uninterpreted fallback preserves them. Any ambiguity requiring identity, financial attribution, currency, amount, receipt relationship, or operational authority inference remains a stop.

## Historical-Evidence Safety

Small value contracts now prove exact-decimal preservation and non-operational Application/evidence projections without adding tables or parallel application architecture. Synthetic tests prove duplicate receipt claims can coexist, orphan permit claims need no fabricated Application, historical actors create no User, historical financial evidence creates no current finance, and media proposals create no Media. Existing PermitApplication status controls and the Nelson forward lifecycle were not weakened or changed.

The plan retains future search/report fields—owner/business names, application/permit/receipt references, year, New/Renewal/Additional, legacy status, barangay literal, historical classification, financial facts, and provenance—without implementing UI or reports. Historic lodging/signature evidence remains absent unless the corpus proves it.

## Determinism, Isolation, and Replay

Two complete canonical planning runs returned the same semantic plan fingerprint. The fingerprint binds the corpus fingerprint, mapping-profile identity, planner semantics, accepted code baseline, complete coverage, phase order, parity anchors, safeguards, and stop conditions; generation time is excluded. A corpus/profile/table/count/dataset change fails closed or changes plan identity.

The full planner read only the private finalized snapshot and local code/configuration. It made no Convex, Ipil web, source-media, external API, or Cloud call. Pre/post corpus verification passed, and planning did not mutate the corpus. No Laravel canonical/domain model, database record, Spatie media record, file upload, or live-source write occurred. The only persistent outputs are the two private aggregate artifacts above.

`ipil:seed` without `--plan` still exits unsuccessfully after verification with `seeded=false`, `offline=true`, and `domain_writes=false`. It has no force/execute option. The convenience runner exposes `plan` explicitly and keeps live acquisition unavailable.

## Environment, Batching, and Gate 6 Recommendation

The only recommended Gate 6 target is a disposable private local PostgreSQL database. UAT, production, Laravel Cloud, public storage, R2/S3, and live Ipil remain forbidden. SQLite is limited to synthetic/small contract tests. Gate 6 should use phase/chunk checkpoints, source/profile/plan-bound import-run identity, unique source mapping indexes, exact target-hash conflict checks, reverse dependency rollback, and immediate audit/replay. Suggested initial chunks are 1,000 simple rows, 250 nested bundles, and 25 media reconciliations.

Gate 6 may follow steps 1–6 of the ceremony in [Ipil Seed Implementation Plan V1](IPIL_SEED_IMPLEMENTATION_PLAN_V1.md) now. That document records the exact proposed future `--execute` command, including manifest, corpus, profile, and local-PostgreSQL confirmations. Step 7 onward requires a separately reviewed implementation and explicit authorization because no real execution path exists in Gate 5.

## Verification and Privacy

Focused synthetic verification passed 22 tests / 195 assertions covering integrity/profile binding, drift, deterministic semantics, complete disposition, immutable private artifacts, exact decimal/exponent/trailing-zero behavior, non-cent rejection, historical Application isolation, duplicate receipt claims, orphan permit claims, idempotent replay/conflict detection, and absence of operational side effects. The full repository suite passed 908 tests with one skipped test and 15,996 assertions. Pint, targeted PHPStan, strict Composer validation, shell syntax, and Git whitespace checks passed.

No taxpayer row, media byte, raw identifier, credential, detailed PII plan, or private artifact entered Git. The immutable corpus was not changed. The four pre-existing guidance edits remained unstaged and untouched.

## Gate Decision

Gate 5 proves what the accepted profile would materialize without materializing it. Gate 6 may be commissioned for the first real local PostgreSQL materialization only after reviewing this plan and manifest. It must stop before UI parity, deployment, Cloud upload, cutover, or production migration.

**GATE 5: PASS — OFFLINE SEED PLAN READY FOR FIRST LOCAL MATERIALIZATION**
