# Ipil Rescue Gate 3 — First Full Rescue Corpus

Status: **PASS — RESCUE CORPUS READY FOR MAPPING REVIEW**

Effective: 2026-09-10

Governing authority: [Ipil Rescue and Parity Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)

Implementation plan: [Ipil Rescue and Parity Implementation Plan](IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md)

## Gate Outcome

The first complete approved Ipil source rescue is finalized as immutable local evidence:

```text
snapshot: ipil-20260910t153224z-2ab19c17
path: storage/app/private/ipil-rescue/snapshots/ipil-20260910t153224z-2ab19c17
corpus schema: bpls.ipil-rescue-corpus.v1
corpus fingerprint: d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81
verification: PASS
```

The source acquisition ran from 2026-09-10T15:32:24Z through 2026-09-10T15:35:12Z (2 minutes 48 seconds). The production-scale verifier was corrected, committed, and rerun; the candidate was atomically placed at its final private path and independently verified there at 2026-09-10T15:52:42Z. Final location, manifest state `complete`, and the culler's refusal to replace or resume a final path constitute lifecycle status `FINALIZED`.

Starting code SHA was `0e624eb580c2f8f87123248ecbcf272bac69e360`. Gate 3 corrective commits were:

- `ca94e75` — use the exact authenticated media permission as preflight proof;
- `861a25b` — use the deployment-scoped key instead of the unsupported CLI deployment selector;
- `be01f98` — expose bounded, credential-redacted Convex export diagnostics;
- `8128228` — stream database JSONL and source-identity verification at production scale.

The deploy key and user token were available only through the loopback secure handoff and process environment. No secret value was persisted in this report, repository content, the corpus, or command output.

## Source and Safety

- Source system: production Ipil Convex deployment `adjoining-porcupine-740`.
- Deployment identity SHA-256: `92fd4fc5ca7d0e476f57208e4a3b2a7a241c1d45b7eee755212a9253a3ce0d2b`.
- Source schema SHA-256: `89a879f6702c8cb05092cd681cddd06f2625da43023b253323f053f310642cd9`.
- Export tool: frozen Convex CLI `1.34.0`.
- Mode: explicit network read, `read_only=true`, `write_back=false`.
- Destination: local private, Git-ignored storage only.

No source record, storage object, relationship, or metadata was modified. No BPLS domain write occurred. Nothing was uploaded to GitHub, Laravel Cloud, R2, S3, chat, CI, or another external destination.

## Database Inventory

All 53 approved source tables are present and checksum-bound. They contain 324,833 rows across 48 non-empty tables. The five zero-row tables—`authVerificationCodes`, `authVerifiers`, `manual_transactions`, `payment_schedule_config`, and `report_export_chunks`—match the prior source reconnaissance and are not unexpected loss.

Notable raw counts:

| Source table | Rows |
| --- | ---: |
| `activity_logs` | 127,363 |
| `authRefreshTokens` | 76,352 |
| `billing_group_line_items` | 45,413 |
| `billing_group_records` | 25,372 |
| `permit_clearances` | 14,615 |
| `payment_schedules` | 7,648 |
| `payments` | 5,874 |
| `unitsOfMeasurement` | 4,381 |
| `businesses` | 3,212 |
| `business_owners` | 3,194 |
| `business_permit_applications` | 3,137 |
| `permits` | 2,766 |

The database table-manifest SHA-256 is `3d016d57b59a2521860ee82b3e380e07238bf48ff65f21197c9767c87cccd701`. The private manifest records the row count, relative path, and SHA-256 for every table; its 53 per-table fingerprints are the canonical detailed inventory and were independently verified.

Source applications span submitted timestamps from 2025-10-26 through 2026-09-08. Literal source values include 2,621 `Renewal`, 461 `New`, and 55 `Additional` applications; 2,907 `Released`, 199 `Assessment`, 28 `Pending Payment`, and 3 `Draft` statuses. These are vocabulary observations only, not canonical mappings.

## Financial Evidence

Financial figures below are raw-source aggregates, not recalculation or policy acceptance:

- 7,648 payment schedules record total amounts of PHP 372,252,667.85 and paid amounts of PHP 93,295,317.20;
- 5,741 `paid` schedules total PHP 83,263,626.11; three `partial` schedules record PHP 10,031,691.09 paid; 1,904 `pending` schedules record zero paid;
- 5,744 `completed` payment rows total PHP 93,295,317.20, matching the aggregate schedule paid amount to cent precision;
- 5,873 payment rows carry a receipt number and all 5,874 carry a transaction number;
- source applications contain 2,929 assessed/total-fee-bearing rows and 4,236 literal line-of-business entries.

Failed and pending payment rows remain source evidence and are not counted as completed collections. No current `Price` calculation ran, and no historical amount was reconstructed.

## Media and Document Evidence

The corpus contains 35 distinct storage objects totaling 37,234,582 bytes. All 35 bytes were retrieved and checksum/size verified. Sixteen objects have typed metadata relationships and 19 remain `UNRESOLVED` storage-only evidence. There are zero probable associations, zero confirmed orphans, zero missing source bytes, zero retrieval failures, zero access denials, and zero zero-byte objects.

Typed relationships comprise one business document, one permit-layout background, three platform-setting images, and eleven report exports. This is one more report-export object than the earlier 34-object reconnaissance snapshot; the unresolved count remains exactly 19. Two typed relationships have duplicate content hashes but retain independent source identities.

Literal document evidence contains one `DTI Certificate`. No typed SEC document or dedicated BIR document/label appears. SEC remains observed absent in this corpus; BIR remains unestablished rather than globally disproven. The 19 unlabeled storage-only objects remain unassociated and must not be guessed into an Application.

Three report-export objects declare `text/csv` while local MIME detection reports `text/plain`. Their sizes and SHA-256 values verify exactly. The conservative corpus contract records three `corrupt-media` review findings; this is a clustered MIME-classification discrepancy, not missing bytes or checksum corruption. Mapping review must preserve the bytes and decide no business meaning from this label.

Media-manifest SHA-256: `a5579df24002a22d0cb5bbd9a9d6991d82af6ba0fc59475baa569d2a699bf2a4`.

## Pricing Evidence

Pricing evidence is the recovered `prod-convex-reference-catalog-v2-20260816-224400` corpus, packaged as five candidate records covering `division_groups`, `divisions`, `fee_overrides`, `fees`, and `groups`.

- contract: `bpls.ipil-rescue-pricing-manifest.v1`;
- interpretation state: `candidate-only`;
- raw database fingerprint: `96b18d6cddda22fd0278616f02ce1903a9e416fe744be7c607d87779509cf3fc`;
- records SHA-256: `1db805d6f9223f30147ee2b955191981f3b8912343b51873af2761ffb29ae207`;
- pricing-manifest SHA-256: `98695a5b72d13a7a5e11e3dbb0d00bc2de3f6f89b4c81d695d7100bed6fbece5`.

It remains independent evidence. It was not activated as BPLS pricing policy and created no `FeeRule`.

## Exception Inventory

All 24 findings are explicit and checksum-bound:

| Finding | Count | Disposition |
| --- | ---: | --- |
| `unassociated-byte` | 19 | retained as `UNRESOLVED` evidence |
| `corrupt-media` | 3 | retained; CSV/plain MIME discrepancy, bytes verified |
| `duplicate-content` | 2 | retained with distinct source identities |

No exceptional source evidence was silently discarded.

## Execution and Verification History

Credential preflight initially exposed three bounded culler/integration defects: a redundant identity probe rejected a valid media-authorized token; the pinned CLI was passed an unsupported deployment selector; and the real CLI error was hidden. Each was corrected with a regression test and committed before retry. The temporary secure runner also corrected its Convex working directory from the monorepo root to `packages/backend`.

Two full read-only submissions then completed transport because the browser appeared unresponsive during verification. The earlier candidate `ipil-20260910t153224z-2ab19c17` is the sole canonical finalized snapshot. The later `ipil-20260910t153521z-926c9351` remains an untouched, non-authoritative private candidate; it was neither merged into nor allowed to overwrite the canonical corpus. Earlier failed/in-progress control evidence also remains private and outside Git pending a separately authorized cleanup decision.

The bulk run exposed a verifier defect: whole-file parsing exceeded the normal 256 MB PHP limit on an 81 MB table and 210 MB source-identity registry. Commit `8128228` changed verification to stream those JSONL records. Verification then passed twice on the real corpus at the normal limit, including once at the final path:

- integrity: PASS;
- semantic contracts: PASS;
- database: 324,833 rows / 53 tables;
- media: 35 objects / 16 metadata relationships;
- pricing: 5 candidate records;
- exception accounting: 24 findings;
- files verified: 97;
- source identities verified: 324,873;
- corpus fingerprint: `d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81`.

Corrective-code verification: 22 focused rescue tests passed; full suite 895 tests with 894 passed and one skipped (15,868 assertions); Pint passed; targeted PHPStan passed; `composer validate --strict` passed.

## Interpretation and External Boundaries

`ipil:seed` was not run. No canonical `User`, `BusinessOwner`, `Business`, `PermitApplication`, LOB, `Assessment`, `Payment`, Official Receipt, `Permit`, or other BPLS domain record was created. No legacy status, category, fee, document, or identity was mapped. No rescued document entered Spatie Media Library. No report or UI/UX parity work began.

No taxpayer row, database dump, media byte, PII-bearing manifest, credential, deploy key, user token, cookie, session material, signed URL, or private rescue artifact entered Git. The four unrelated guidance edits remained untouched.

## Mapping-Readiness Recommendation

Gate 4 should be a mapping-design and disposition wave operating only from this finalized local corpus. It should begin with explicit mappings for reference identities and literal vocabularies, reconcile owners separately from Users, preserve historical Applications as non-operational, reconcile financial records without current-price recalculation, and review the 19 unresolved objects and CSV/plain MIME findings without guessing. It must not seed canonical BPLS records until mapping decisions and audit expectations are approved.

> **GATE 3: PASS — RESCUE CORPUS READY FOR MAPPING REVIEW**

Authorized Gate 4 evidence: `ipil-20260910t153224z-2ab19c17` at `storage/app/private/ipil-rescue/snapshots/ipil-20260910t153224z-2ab19c17`.
