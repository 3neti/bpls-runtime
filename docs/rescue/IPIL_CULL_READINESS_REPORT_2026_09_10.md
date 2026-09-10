# Ipil Cull Readiness Report

Status: **CULL READINESS: YES — IMPLEMENTATION REQUIRES A SEPARATE BOUNDED WAVE**

Gate date: 2026-09-10

Evidence:

- [Ipil Source Reconnaissance](IPIL_SOURCE_RECONNAISSANCE_2026_09_10.md)
- [Ipil Media Cull Readiness Closure](IPIL_MEDIA_CULL_READINESS_CLOSURE_2026_09_10.md)
- [Rescue Corpus V1 Semantic Contracts](IPIL_RESCUE_CORPUS_V1_SEMANTIC_CONTRACTS.md)
- [Governing Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)

## Gate Decision

The culler is now safe to implement in a separate bounded wave. Current authenticated signed retrieval has been proven with a real DTI document whose bytes exactly match the archived size, MIME, and SHA-256. All 19 unassociated objects have a private inventory and a mandatory retain-and-attempt policy. SEC/BIR scope is explicitly classified. Rescue Corpus V1 can preserve bytes without interpreting their documentary meaning, and the synthetic verifier distinguishes unresolved bytes from confirmed orphans. This decision authorizes implementation design and synthetic acquisition testing only; it does not itself authorize a production cull, seeding, mapping, import, deployment, or UI work.

## Required Questions

### 1. Can every required source table be read safely?

**Yes for the authorized backup path; a culler must enforce current-run consistency.**

The authenticated production backup captured all 53 tables and 308,038 rows and the prior staging read all rows without BPLS writes. The live administrative surface remains reachable read-only. The present normalized intake retains 52 table payloads; `activity_logs.jsonl` is absent although its manifest/count remain, so it cannot substitute for a fresh capture. A future cull must acquire all 53 current datasets through an explicit authorized export and close atomically.

### 2. Can legacy uploaded-document bytes be located and retrieved?

**Yes for the representative DTI object and the shared Convex storage mechanism.**

The DTI metadata row deterministically identifies one Convex storage object. Its current authenticated Download action called `businesses.getDocumentUrl`/`ctx.storage.getUrl`; the privately retrieved 4,675-byte JPEG matched the archived size, MIME, and SHA-256 and decoded as a 500 x 410 image. A platform-logo sample independently retrieved through Convex also matched the archived index and exposed a real duplicate-content case. SEC is supported but has no observed bytes; BIR has no dedicated source field/path and remains ambiguous because document labels are unrestricted strings.

### 3. Can database rows and media be linked deterministically?

**Yes for typed references; unlinked objects remain explicitly unresolved rather than discarded.**

All 15 distinct typed storage references resolve exactly to the 34-object index: one DTI document, one permit-layout background, three platform-setting images, and ten report exports. Every remaining object is privately inventoried: 19 objects / 18,704,021 bytes, all `UNRESOLVED`, zero `ORPHAN_CONFIRMED`. A culler can link typed relationships deterministically and retain every other storage object as `unassociated-byte` without interpretation.

### 4. Is the pricing corpus identifiable and independently versioned?

**Yes.**

The five raw datasets total 1,981 rows and have independent dataset hashes plus manifest SHA-256 `2a1223adc10bb1cc2f1b5480185a2c2ac591a646f1ae59a0b9bcfbd849782eda`. The candidate-only staging interpretation has its own SHA-256 `3b675ecde03c7dd1a12712f19448f718f92fbe56ad6ea8bff7686c53a38a5911`, performed no mappings/writes, and records one unresolved override-to-fee edge.

### 5. Are corpus schemas sufficient to rescue without interpretation?

**Yes.**

The contracts separately bind database rows, media relationships/objects, candidate pricing knowledge, acquisition/tool provenance, source identities, and findings. Unknown values and missing evidence can be transported literally. `ipil:rescue:verify` validates these contracts offline, now including association state and the `unassociated-byte`/confirmed-orphan boundary, using synthetic fixtures only.

### 6. What source gaps remain?

- a fresh production run must reacquire all current database and storage bytes; the August normalized intake is not a complete rebuild source;
- SEC and other declared ownership-document bytes were absent in the observed scope and must remain missing unless encountered in a fresh capture;
- BIR has no dedicated legacy storage contract and remains ambiguous; unidentified byte contents must not be relabeled;
- the missing normalized `activity_logs.jsonl` prevents retrospective association research from that copy;
- reconciliation vocabulary for the 258 declared broken edges and one pricing-override edge, without repair;
- the implementation wave must encode the fresh export consistency procedure and explicit read-only source profile before any production run;
- authoritative PSGC and LOB mapping decisions, which are intentionally later and do not block raw rescue;
- confirmation that all dynamic/untyped storage uses have been enumerated before final media completeness is claimed.

### 7. Is `ipil:cull` now safe to implement?

**Yes.**

The reconnaissance gate is passed because the culler can transport every authorized table and every enumerated storage object without needing canonical meaning. Implementation remains a separate authorization: no `ipil:cull` command is added by this closure wave, and no production acquisition is authorized here.

## Conditions a Future Implementation Must Enforce

All remain mandatory acceptance conditions for the culler:

1. authenticated `getUrl` retrieval is streamed to an approved private incomplete corpus and independently hash-checked;
2. SEC, BIR, and unexpected documentary scope is reported literally from each fresh capture;
3. the source-backed storage-reference catalog covers typed and proven dynamic uses;
4. every storage object is enumerated and fetched, or every failure produces a stable media disposition;
5. unassociated-object retention defaults to private preservation; `orphan-byte` requires `ORPHAN_CONFIRMED` evidence;
6. a fresh export consistency method and source read-only profile are documented;
7. a dry synthetic acquisition can finalize atomically into the approved private root and cannot write source, Git, public disk, or Cloud.

## Stop Conditions

This gate does not authorize any later frontier. Until separately authorized:

- no production `ipil:cull` execution;
- no canonical mappings or `ipil:seed` implementation;
- no historical BPLS writes;
- no Spatie import;
- no current-price evaluation;
- no UI/report parity implementation;
- no Cloud upload, deployment, or public artifact;
- no omission of unassociated objects, missing bytes, or broken references to improve a parity percentage.

## Binary Decision

**CULL READINESS: YES**

The next separately authorized wave may implement the explicit network-only culler and synthetic dry acquisition. It must stop before any real cull unless the operator profile, consistency method, destination, and run are explicitly authorized.
