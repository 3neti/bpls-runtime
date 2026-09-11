# Ipil Seed Implementation Plan V1

Status: **GATE 5 CONTRACT PRESERVED — GATE 6 MATERIALIZATION EXECUTED UNDER SEPARATE AUTHORIZATION**

Effective: 2026-09-11

Governing authority: [Ipil Rescue and Parity Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)

Evidence interpretation authority: [Source-to-BPLS Mapping Specification V1](IPIL_SOURCE_TO_BPLS_MAPPING_SPECIFICATION_V1.md) and [Mapping Decision Register](IPIL_MAPPING_DECISION_REGISTER.md)

## Purpose and Boundary

Gate 5 turns the accepted Gate 4 interpretation into a deterministic offline plan without materializing taxpayer history. `ipil:seed --plan` reads only the immutable local corpus, the accepted mapping profile, and local code/configuration. It verifies first, plans every hashed source identity once, writes a PII-safe aggregate plan and execution manifest below `storage/app/private/ipil-rescue/plans`, and performs no database, domain, media, source, network, or Cloud write.

Calling `ipil:seed` without an explicit mode remains fail-closed. Gate 6 added only the fully bound `--execute` path described below; it requires the exact corpus/profile confirmations, unchanged private manifest, `local-postgresql` environment, local disposable database guard, and a separate private authorization artifact. There is no generic force/bypass flag.

## Fixed Inputs

| Input | Accepted identity |
| --- | --- |
| Rescue Corpus | `ipil-20260910t153224z-2ab19c17` |
| Corpus fingerprint | `d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81` |
| Mapping profile | `ipil-rescue-mapping-v1.0.0` |
| Mapping contract | `bpls.ipil-source-to-bpls-mapping.v1` |
| Profile identity | `edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c` |
| Seed plan contract | `bpls.ipil-seed-plan.v1` |
| Execution manifest contract | `bpls.ipil-seed-execution-manifest.v1` |

Any corpus ID/fingerprint, table inventory/count, mapping profile, media/pricing scope, duplicate SourceIdentity, or unknown dataset drift stops before interpretation.

## Planning Contracts

- `IpilSeedMappingProfile` encodes the accepted 53-table disposition/count/confidence ledger and Gate 4 parity anchors. It does not create policy.
- `PlanIpilRescueSeed` verifies the corpus, matches every table count, streams all SourceIdentity records, assigns one accepted disposition, proves per-dataset closure, and hashes the canonical semantic result. Wall-clock generation time is excluded from the semantic fingerprint.
- `IpilSeedPlan` is a PII-safe aggregate. It identifies corpus, mapping profile, planner semantics, accepted baseline, coverage, phases, safeguards, preserved findings, and stop conditions.
- `PersistIpilSeedPlan` writes only to fixed private Git-ignored storage, refuses symlinks, uses restrictive permissions, and will not replace an artifact with a conflicting semantic fingerprint.
- `HistoricalAmount` preserves the exact numeric source lexeme, derives a normalized decimal without floating point, and exposes minor units only when conversion is exact. It never rounds or truncates.
- `HistoricalApplicationPlan` and `HistoricalEvidencePlan` make historical status, application, owner/actor, finance, receipt, permit, clearance, classification, and document projections explicitly non-operational. They cannot create current Users, finance, Permits, lifecycle actions, or Spatie media.

Source status values remain literal evidence. `Additional` remains first-class history. Missing PSGC, LOB, document, signature, actor, lodging-manifest, parent, and relationship facts remain null/unavailable or preserved as ambiguous/orphan evidence; none are manufactured.

## Accepted Phase Order

| Phase | Planned concern | Expected scope | Gate 6 rule |
| --- | --- | --- | --- |
| A | Bind/inventory | 324,833 rows plus media/pricing | Abort on any binding drift |
| B | Reference proposals | 44 barangays, 562 LOB labels, fee/location catalogs | Proposals only; no automatic PSGC/LOB/fee acceptance |
| C | Owners | 3,194 | No merges and no Users |
| D | Businesses | 3,212 | Source identity and exact owner edge govern |
| E | Historical Applications | 3,137 | Non-operational; preserve New/Renewal/Additional and source status |
| F | Declarations/classifications | 4,236 LOB items plus 4,388 measurements | Preserve raw labels/lexemes and unresolved cohorts |
| G | Permit finance | 7,648 schedules, 24,402 fee lines, 5,874 events | Historical bundles only; exact decimals; no current Price/Assessment/Collection/Receipt |
| H | Billing/report finance | 71,032 source-table rows | Separate historical billing evidence; no inferred Application link |
| I | Clearances/permits | 14,615 plus 2,766 claims | Preserve broken/orphan edges; replay no issuance action |
| J | Media reconciliation | 35 objects, 16 typed relationships | Proposals only; no Spatie copy; 19 remain unresolved |
| K | Search/report projection | Contract/index requirements | No UI or report execution authority |
| L | Audit/replay | All evidence classes | Same semantics must reproduce the same plan fingerprint |

## Historical and Operational Separation

The eventual historical projection may display owner/business names, legacy application and permit references, year, type, source status, barangay literal, classifications, exact financial evidence, payment and receipt claims, permit/clearance claims, document evidence, and provenance. It must show missing evidence honestly and expose no current Post-it work, current assessment/liability, current receipt authority, current permit readiness, or Nelson lifecycle action.

Historical completed payments are not `TreasuryCollection`; historical receipt numbers are claims and may duplicate; historical permits are claims and may lack an Application; historical clearances may reference missing types. The PHP `93,295,317.20` completed-payment/schedule-paid equality is an audit anchor, not a current pricing result. The 348 non-cent Application totals and 24 non-cent schedule totals retain exact lexemes and never enter cent-based Money by rounding.

The future Spatie proposal for the single typed DTI business-document relationship uses private collection `application_documents` and provenance custom properties for source system/snapshot/object/document identity, checksum, filename, historical flag, type, association confidence, and profile. SEC and BIR associations remain unestablished. No media is copied in Gate 5, and corpus bytes remain source truth.

## Gate 6 Materialization Design

Gate 6, if separately authorized, should use a disposable local PostgreSQL database. SQLite remains for synthetic tests only. Use an explicit import-run identity bound to corpus, profile, plan, planner, code revision, timestamps, and status. Preserve raw evidence first, then materialize in phases and checkpoint each phase/chunk. Suggested initial chunks are 1,000 simple evidence rows, 250 nested bundles, and 25 media reconciliations. Batch size must never affect identity or hashes.

Bulk insertion is appropriate for immutable raw/provenance ledgers and simple evidence rows after validation. Domain-aware adapters remain required for historical owner/business/Application projections and every boundary that could touch current authorization, lifecycle, finance, permit, or media behavior. Conflicting target/source hashes stop; replay of the same source/profile is a no-op. Rollback may delete only records created by that import run whose hashes and downstream dependency counts remain unchanged, in reverse phase order.

Required indexes include unique corpus/dataset/source identity; import batch/dataset/ordinal; disposition/status; target type/id; owner/business/Application source mappings; exception code/status; and private historical search fields for year, type, status, names, references, barangay literal, and classification. Full-scale performance and JSON/index behavior must be rehearsed on PostgreSQL before any broader environment is considered.

## Gate 6 Ceremony — Executed Under Separate Owner Authorization

1. Prepare a disposable, private local PostgreSQL database with no Cloud connection.
2. Record the database identity and prove it is not UAT/production.
3. Run `php artisan migrate:fresh`, then the repository's approved local `bpls:install` procedure.
4. Run `php artisan ipil:rescue:verify storage/app/private/ipil-rescue/snapshots/ipil-20260910t153224z-2ab19c17 --json`.
5. Regenerate with `php artisan ipil:seed storage/app/private/ipil-rescue/snapshots/ipil-20260910t153224z-2ab19c17 --plan --json`.
6. Verify the accepted plan and execution-manifest fingerprints and confirm `execution_authorized=false` under the Gate 5 implementation.
7. The separately commissioned Gate 6 added and invoked this explicit interface against the disposable database:

   ```text
   php artisan ipil:seed storage/app/private/ipil-rescue/snapshots/ipil-20260910t153224z-2ab19c17 \
     --execute \
     --manifest=storage/app/private/ipil-rescue/plans/ipil-seed-plan-874d26eb21ed0041/seed-execution-manifest.json \
     --confirm-corpus=ipil-20260910t153224z-2ab19c17 \
     --confirm-profile=ipil-rescue-mapping-v1.0.0 \
     --environment=local-postgresql
   ```
8. `ipil:audit` passed, the identical seed created zero source-derived records, and the second audit reproduced all anchors. Full evidence is in the [Gate 6 report](IPIL_RESCUE_GATE_6_FIRST_LOCAL_MATERIALIZATION_2026_09_11.md).
9. Stop before UI parity, Cloud upload, deployment, cutover, or production migration.

## Stop Conditions

Stop on profile/corpus drift, an unplanned identity, dependency ambiguity that would require guessing, amount coercion, hidden duplicate receipt claims, orphan deletion, fabricated evidence, operational leakage, current Price/FeeRule/Collection/Receipt/Permit/Media use, network access, public/Cloud storage, or any canonical write before Gate 6. A green plan means ambiguity is completely accounted for—not eliminated.
