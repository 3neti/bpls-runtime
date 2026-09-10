# Ipil Rescue Gate 2 — `ipil:cull` Implementation

Status: **IMPLEMENTED AND SYNTHETICALLY CERTIFIED — FULL BULK RESCUE NOT RUN**

Effective: 2026-09-10

Governing authority: [Ipil Rescue and Parity Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)

Contracts: [Rescue Corpus V1 Semantic Contracts](IPIL_RESCUE_CORPUS_V1_SEMANTIC_CONTRACTS.md)

Readiness basis: [Cull Readiness Report](IPIL_CULL_READINESS_REPORT_2026_09_10.md) and [Media Cull Readiness Closure](IPIL_MEDIA_CULL_READINESS_CLOSURE_2026_09_10.md)

## Gate Outcome

Gate 2 implements the explicit transport boundary:

```text
IpilCullCommand
    -> CullIpilRescueCorpus
        -> IpilCullSource
            -> ConvexExportIpilCullSource
            -> ConvexAuthenticatedMediaRetriever
        -> VerifyIpilRescueCorpus
        -> immutable finalized Rescue Corpus V1
```

The command transports raw evidence only. It does not map source statuses, identities, lines of business, fees, documents, or Applications into BPLS. It performs no BPLS database write, Spatie import, current-price calculation, Cloud upload, deployment, or source mutation.

No real-source smoke test and no full bulk rescue were performed in Gate 2. Certification uses only synthetic source rows and bytes.

## Explicit Command Boundary

`php artisan ipil:cull` is the only cull entry point. It requires `--accept-source-access`; creating or resuming a snapshot additionally requires `--confirm-source-access`. `--preflight` checks the complete safety and authentication boundary without creating a snapshot. `--resume=<snapshot-id>` addresses only one exact private incomplete/failed snapshot.

There is deliberately no `--force-production`, `--skip-verification`, `--ignore-checksums`, destination override, or source-write option. The command is absent from install, migration, seed, audit, tests, application boot, scheduling, deployment, `bin/bpls-product-lab`, and `bin/bpls-ipil-rescue-lab` flows.

## Source and Authentication Architecture

The production adapter uses two independently authenticated read paths:

1. a pinned Convex CLI executes `convex export --include-file-storage` against the exact configured deployment, producing one consistent private ZIP containing all table JSONL and `_storage` evidence;
2. a configured user bearer token proves `users:getCurrentUser` and empty `businesses:getDocumentUrls` access, then retrieves each storage object through the established `businesses:getDocumentUrl` -> `ctx.storage.getUrl` path.

The adapter invokes only export and query/download operations. It has no mutation/import/deploy call. The export ZIP remains in the snapshot's private control directory for safe resume; that control directory is deleted only after verified atomic finalization. Secrets, signed URLs, cookies, source identifiers, taxpayer names, and document filenames are never written to ordinary console output.

The approved source inventory is fail-closed at 53 exact datasets. A missing `activity_logs` payload, a new unreviewed table, or any other inventory drift prevents transport from being declared complete. Table rows are streamed in deterministic chunks from the immutable export, preserving raw JSON, IDs, nulls, timestamps, source types, unknown fields, and row order. Camel-case Convex table and field names remain literal source facts.

## Preflight

Preflight rejects the run unless all of these close:

- environment is `local` or `testing`;
- destination is the fixed configured path below `storage/app/private`, is writable, has practical free space, is Git-ignored, and traverses no symlink;
- destination is not a URL, public path, Cloud/UAT/production store, or operator-selected escape path;
- exact deployment, HTTPS Convex URL, deploy key, user token, pinned executable, legacy project, source schema, and private pricing evidence are configured;
- the operator has explicitly marked the source profile read-only;
- the pinned CLI executes and the authenticated user can establish the proven media-read permission;
- Rescue Corpus V1 is the known output schema.

Failure occurs before source export. Preflight output contains only safe hashes/classifications, never credentials.

## Snapshot Layout and Lifecycle

Work begins at:

```text
storage/app/private/ipil-rescue/snapshots/.in-progress/<snapshot-id>/
```

An incomplete run writes corpus payloads under `.in-progress/<snapshot-id>` and keeps `run-state.json`, the immutable `source-export.zip`, and database/media checkpoints in the sibling private `.control/<snapshot-id>` directory. Keeping control evidence outside the candidate corpus lets the real verifier enforce an exact payload inventory without sacrificing the source snapshot needed for safe resume. Only after database, media, pricing, provenance, source identities, findings, checksums, and offline semantic verification close is the corpus directory atomically moved to:

```text
storage/app/private/ipil-rescue/snapshots/<snapshot-id>/
├── corpus.json
├── source/database/{schema.json,table-manifest.json,tables/*.jsonl}
├── source/media/{media-manifest.jsonl,objects/*}
├── source/pricing/{pricing-manifest.json,records.jsonl}
├── provenance/{acquisition.json,source-identities.jsonl,tools.json}
└── verification/{checksums.sha256,exceptions.jsonl}
```

The finalized manifest state is `complete`, corresponding to the operator lifecycle state `FINALIZED`. A finalized snapshot is never updated or resumed; a later cull creates a new ID. Failure never produces a finalized directory.

## Database Transport and Resume

Every table closes to its own JSONL path, row count, and SHA-256. The root count equals the table sum. Each row receives a hashed `SourceIdentity` bound to the raw table evidence; raw source keys remain private. No status, owner, business, Application, LOB, fee, payment, receipt, permit, or reference-data mapping occurs.

Completed table files are checksum-recounted and reused on resume. An incomplete `.part` table is discarded and reconstructed from the beginning of the same preserved source export. Paging is cursor-checked and deterministic; the adapter rejects an unexpected cursor rather than silently restarting or duplicating rows.

## Media Transport, Integrity, and Resume

Typed relationships are enumerated for business documents, permit-layout backgrounds, platform images, and report exports. Each metadata relationship remains a separate manifest entry. Every remaining `_storage` object is retained as `storage-object` / `UNRESOLVED` / `unassociated-byte`; checksum equality never erases a source identity or relationship. SEC, DTI, BIR, and arbitrary source labels remain literal and are never fabricated or normalized.

Each successful retrieval is written only below the private snapshot, then checked for byte count, SHA-256, detected MIME, declared MIME, and basic image/PDF structure. Missing, access-denied, retry-exhausted, corrupt, zero-byte, duplicate-content, and unresolved cases receive explicit dispositions/findings. Missing bytes have no placeholder. Retries are bounded by configuration (default three).

The private media checkpoint is append-only during acquisition. Resume skips an entry only when its existing object still matches the checkpoint size and SHA-256. Changed bytes abort the run; they are never overwritten into success. Multiple relationships may share a content or storage hash while retaining distinct manifest/source-identity entries.

## Pricing Evidence

The previously recovered private fee-catalog manifest remains a separate evidence input. The culler fingerprints that manifest and packages one candidate-only evidence record per raw pricing dataset, preserving dataset name, row count, and hash. It does not create or activate `FeeRule`, call current `Price`, or claim fiscal authority. Raw database, media, and pricing-reference fingerprints remain independent.

## Exceptions and Finalization

Exceptions are first-class JSONL evidence. The Gate 2 compatible vocabulary adds `retrieval-failed`, `duplicate-source-identity`, `relationship-inconsistency`, and `source-shape-loss`; unresolved remains unresolved and `orphan-byte` still requires `ORPHAN_CONFIRMED`. The terminal summary exposes only aggregate tables/rows, media counts/bytes/gaps, pricing fingerprint, exception count, verification result, snapshot path, and corpus fingerprint.

`VerifyIpilRescueCorpus` is invoked on the actual completed working directory. Only a passing checksum inventory and semantic verification permit atomic finalization. There is no verification bypass.

## Synthetic Certification

Synthetic tests prove:

- lossless IDs, nulls, integer/string source statuses, deterministic JSONL hashes, and cursor paging;
- authenticated media query/download behavior with bounded retries;
- verified bytes, source-missing evidence, corrupt evidence, duplicate-byte relationships, and unresolved storage-only retention;
- unique snapshot identity, `FAILED` interruption state, exact resume, immutable finalization, and rejection of changed checkpoint bytes;
- public and symlinked destinations fail before source acquisition;
- source confirmations are mandatory and console output is redacted;
- the final corpus passes the real offline verifier;
- ordinary local workflows contain no automatic cull invocation.

No synthetic test depends on taxpayer data, source credentials, or network access.

## Next Gate

The next gate may authorize the first full rescue using the exact configured deployment, credentials, pinned CLI, source schema, and recovered pricing manifest. Gate 2 does not grant that authorization. It also does not open `ipil:seed`, canonical mapping, Spatie import, BPLS historical writes, `ipil:audit` canonical parity, report/UI parity, Cloud deployment, or production migration.
