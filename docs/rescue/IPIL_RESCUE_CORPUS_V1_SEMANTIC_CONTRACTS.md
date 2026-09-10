# Ipil Rescue Corpus V1 Semantic Contracts

Status: **APPROVED FOR SYNTHETIC VERIFICATION — NO CULLER OR SEED AUTHORITY**

Effective: 2026-09-10

Evidence basis: [Ipil Source Reconnaissance](IPIL_SOURCE_RECONNAISSANCE_2026_09_10.md)

Media closure: [Ipil Media Cull Readiness Closure](IPIL_MEDIA_CULL_READINESS_CLOSURE_2026_09_10.md)

Gate: [Cull Readiness Report](IPIL_CULL_READINESS_REPORT_2026_09_10.md)

## Purpose

These contracts let `ipil:rescue:verify` prove, offline, that a completed Rescue Corpus V1 is structurally self-consistent and checksum-bound. They describe transport evidence without assigning canonical Laravel meaning.

They do not authorize `ipil:cull`, mapping acceptance, `ipil:seed`, historical projections, price activation, report parity, UI work, Cloud upload, or production use.

## Governing Semantics

- Raw database evidence, media evidence, and interpreted pricing knowledge are distinct evidence classes.
- Every payload is bound by `corpus.json` and `verification/checksums.sha256`.
- Every rescuable entity has a `SourceIdentity`; raw source keys remain private.
- A `complete` corpus can contain source gaps, but every gap must be counted and expressed as a finding/disposition.
- Verification never heals, downloads, normalizes, maps, or writes domain data.
- A semantic correction creates a new corpus/version; existing completed bytes remain immutable.

## Directory Contract

```text
corpus.json
source/database/schema.json
source/database/table-manifest.json
source/database/tables/*.jsonl (or another manifest-bound JSONL path)
source/media/media-manifest.jsonl
source/media/objects/*
source/pricing/pricing-manifest.json
source/pricing/records.jsonl
provenance/acquisition.json
provenance/source-identities.jsonl
provenance/tools.json
verification/checksums.sha256
verification/exceptions.jsonl
```

No unbound file is allowed. Symlinks, Git metadata, URLs, public-root placement, and an unapproved repository location are rejected.

## Root Manifest

`corpus.json` uses `bpls.ipil-rescue-corpus.v1` and records:

- safe corpus ID and optional parent corpus ID;
- source system and deployment-identity SHA-256;
- acquisition run and consistency method;
- immutable `complete` state;
- counts for database rows, media metadata relations, distinct media objects, interpreted pricing records, and findings;
- relative-path-to-SHA-256 bindings for every payload.

`corpus.json` is not included in its own binding. The checksum manifest must exactly equal the binding object.

## Database Schema Contract

`source/database/schema.json` uses `bpls.ipil-rescue-database-schema.v1`:

| Field | Meaning |
| --- | --- |
| `source_engine` | Source storage engine, currently `convex` |
| `source_engine_version` | Authoritative version or `null` when unavailable |
| `source_schema_sha256` | Fingerprint of the source schema evidence |
| `datasets[]` | Complete dataset inventory |
| `datasets[].name` | Stable source dataset name |
| `datasets[].identity_field` | Source identity field, commonly `_id` |
| `datasets[].fields_sha256` | Canonical fingerprint of the observed/declared field inventory |
| `datasets[].relationships[]` | Field, target dataset, `one`/`many`, and required flag |

The dataset set must exactly match the database table manifest. Relationship declarations describe source topology; they do not require every historical edge to resolve.

## Database Snapshot Manifest

`source/database/table-manifest.json` uses `bpls.ipil-rescue-database-manifest.v1` and fixes format `jsonl`. Each table entry binds dataset, safe relative path, row count, and SHA-256. Verification:

1. requires every path to be root-bound;
2. recomputes the payload SHA-256;
3. parses every non-empty line as a JSON object;
4. recounts rows;
5. compares the table set to `schema.json`;
6. compares the sum to `corpus.json.counts.database_rows`.

Unknown fields remain in raw JSONL. The schema inventory records their existence; it never strips them.

## Media Manifest

Each `source/media/media-manifest.jsonl` line uses `bpls.ipil-rescue-media-entry.v1` and records:

- source dataset/key and storage-identifier SHA-256 values;
- database relationship path;
- evidence role `metadata-relationship` or `storage-object`;
- documentary type and original filename when known;
- declared/detected MIME and declared/rescued sizes;
- private object relative path and content SHA-256 when bytes were acquired;
- retrieval-attempt count and transfer-verification flag;
- association state independent of byte disposition;
- disposition and finding codes.

Approved association states are `ASSOCIATED`, `PROBABLE_ASSOCIATION`, `UNRESOLVED`, and `ORPHAN_CONFIRMED`. Unknown association is not orphan evidence.

Approved dispositions are `rescued`, `source-missing`, `access-denied`, `corrupt`, `zero-byte`, `duplicate-content`, `orphan-metadata`, `unassociated-byte`, and `orphan-byte`. `unassociated-byte` requires `PROBABLE_ASSOCIATION` or `UNRESOLVED`; `orphan-byte` requires `ORPHAN_CONFIRMED`.

Object paths are mandatory for acquired bytes and forbidden as placeholders for absent bytes. `rescued` requires a bound object, exact length/hash agreement, and `bytes_verified: true`. `unassociated-byte` and `orphan-byte` require role `storage-object`; `orphan-metadata` requires `metadata-relationship`. Root `media_metadata` counts only `metadata-relationship` entries, including explicit source-missing/access-denied cases. Root `media_bytes` counts distinct bound object paths, including acquired storage-only entries. Duplicate-content relationships may share an object path while retaining separate manifest lines.

SEC, DTI, BIR, and unexpected documentary labels remain literal source facts. The manifest does not normalize them to a current document type.

## Pricing Manifest

`source/pricing/pricing-manifest.json` uses `bpls.ipil-rescue-pricing-manifest.v1` and explicitly declares `evidence_class: interpreted-pricing-knowledge`. It binds:

- `raw_database_fingerprint_sha256` independent of media and interpretation;
- every source dataset name/count/hash used by the interpreter;
- interpreter name and version;
- records path, SHA-256, and count;
- mandatory `interpretation_state: candidate-only`.

Each pricing record uses `bpls.ipil-rescue-pricing-record.v1`, contains the source dataset/key pair, knowledge kind, candidate-payload fingerprint, evidence object, and confidence `established`, `probable`, `ambiguous`, or `unknown`.

The verifier deliberately rejects a pricing manifest that claims `canonical` activation. Fiscal/policy acceptance belongs to a later explicit mapping/authority boundary.

## Acquisition Provenance

`provenance/acquisition.json` uses `bpls.ipil-rescue-acquisition.v1`. It must agree with root source/run/consistency fields and prove:

- mode is `explicit-network` or `synthetic`;
- start/completion timestamps are recorded;
- `read_only` is true;
- `write_back` is false;
- destination class is `local-private`.

`provenance/tools.json` uses `bpls.ipil-rescue-tools.v1` and binds at least one tool name, version, and SHA-256. An actual culler version does not yet exist.

## Source Identity Registry

`provenance/source-identities.jsonl` retains `bpls.ipil-source-identity.v1`. Verification requires corpus/source agreement, bound evidence locators, and uniqueness of dataset plus source-key hash. The expected count is:

```text
database rows + media manifest entries (including unassociated and orphan bytes) + interpreted pricing records
```

Identity does not imply a mapping. The existing state grammar remains `observed -> inferred -> proposed -> accepted -> rehearsed -> production-applied`.

## Finding and Exception Vocabulary

Each `verification/exceptions.jsonl` line uses `bpls.ipil-rescue-finding.v1`:

- evidence class: `database`, `media`, `pricing`, `provenance`, or `corpus`;
- severity: `info`, `warning`, `error`, or `blocker`;
- status: `open`, `accepted`, `resolved`, or `waived`;
- optional source dataset/key pair;
- human message plus structured details;
- optional disposition and authority.

Approved finding codes are:

`access-denied`, `ambiguous-mapping`, `checksum-mismatch`, `contradictory-source-evidence`, `corrupt-media`, `duplicate-content`, `missing-required-field`, `unassociated-byte`, `orphan-byte`, `orphan-metadata`, `source-missing`, `unresolved-reference`, `unsupported-source-value`, and `zero-byte`.

`accepted` or `waived` records a decision; it never makes missing evidence present. New vocabulary requires a schema version or compatible reviewed extension, not a free-form spelling.

## Offline Verification Result

A passing `ipil:rescue:verify` now means:

- root inventory/checksums close exactly;
- database schema/table sets, hashes, JSONL, and counts agree;
- every acquired media object matches declared bytes/hash;
- media metadata/object counts agree;
- pricing remains candidate-only and independently fingerprinted;
- acquisition/tools/source identities/findings validate;
- no network call, repair, domain write, or upload occurred.

It does **not** mean source completeness, mapping acceptance, financial parity, canonical import readiness, or cull readiness. The cull gate is separate.

## Synthetic Proof

The committed tests construct a private synthetic corpus containing one database row, one verified DTI-like media relationship/object, one candidate pricing record, three source identities, and one finding. Tests prove deterministic success and fail-closed rejection of:

- altered bytes;
- unbound payloads;
- false row counts even after rebinding checksums;
- media length mismatch;
- an unresolved byte mislabeled as a confirmed orphan;
- attempted canonical price activation;
- partial, remote, public, Git-contained, and unapproved repository corpora.

No real taxpayer or media data is used.

## Acceptance Grammar for a Future Real Corpus

| Dimension | Required proof |
| --- | --- |
| Rows | Every declared table readable; per-table hash/count closes; root rows equal table sum |
| Relationships | Every edge resolves or has one stable finding/disposition; no invented parent |
| Media | Metadata total = classified relationships; every acquired object length/hash matches; missing/orphan/corrupt classes balance |
| Pricing | Raw dataset fingerprint independent; interpreter/version bound; all outputs candidate-only |
| Finance | Source values preserved exactly; totals audited later in integer centavos; no current-price recalculation |
| Reports/UI | No claim at this gate; later parity uses `MATCH`/`ADAPT`/`IMPROVE`/`DEFER` |

The verifier implements only this contract. It contains no live-source client and there is still no `ipil:cull` command.
