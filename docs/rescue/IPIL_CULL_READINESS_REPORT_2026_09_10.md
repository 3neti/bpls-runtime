# Ipil Cull Readiness Report

Status: **STOP — NOT YET SAFE TO IMPLEMENT `ipil:cull`**

Gate date: 2026-09-10

Evidence:

- [Ipil Source Reconnaissance](IPIL_SOURCE_RECONNAISSANCE_2026_09_10.md)
- [Rescue Corpus V1 Semantic Contracts](IPIL_RESCUE_CORPUS_V1_SEMANTIC_CONTRACTS.md)
- [Governing Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)

## Gate Decision

Database acquisition semantics are ready to scaffold, and the corpus contracts are synthetically verifiable. The full culler is not yet safe to implement because current signed media-byte retrieval has not been re-proven, the original byte archive is not retained beside the normalized intake, SEC/BIR representative coverage is absent, and unassociated-object retention remains undecided. Stop at this gate; do not build a database-only culler and call it complete.

## Required Questions

### 1. Can every required source table be read safely?

**Yes for the authorized backup path; current-run consistency must still be enforced.**

The authenticated production backup captured all 53 tables and 308,038 rows, and every normalized JSONL table is readable/checksum-bound. The live administrative surface remains reachable read-only. A future cull must use a fresh explicit authorized export and atomic completion; the August snapshot is not a current source substitute.

### 2. Can legacy uploaded-document bytes be located and retrieved?

**Located: yes for the representative DTI object. Retrieved now: not re-proven.**

The DTI metadata row deterministically identifies one indexed Convex storage object whose prior intake size/hash passed. Source code proves retrieval must go through authenticated `ctx.storage.getUrl`. A public storage-ID URL correctly fails. The normalized intake does not retain the original byte archive, and this wave did not obtain/re-hash the signed object. SEC has no representative row; BIR is not found in schema or observed data.

### 3. Can database rows and media be linked deterministically?

**Yes for typed references; no for every stored object.**

All 15 distinct typed storage references resolve exactly to the 34-object index: one DTI document, one permit-layout background, three platform-setting images, and ten report exports. Nineteen objects have no source-proven typed association and remain `orphan-byte` candidates. They must be rescued or explicitly dispositioned, not dropped.

### 4. Is the pricing corpus identifiable and independently versioned?

**Yes.**

The five raw datasets total 1,981 rows and have independent dataset hashes plus manifest SHA-256 `2a1223adc10bb1cc2f1b5480185a2c2ac591a646f1ae59a0b9bcfbd849782eda`. The candidate-only staging interpretation has its own SHA-256 `3b675ecde03c7dd1a12712f19448f718f92fbe56ad6ea8bff7686c53a38a5911`, performed no mappings/writes, and records one unresolved override-to-fee edge.

### 5. Are corpus schemas sufficient to rescue without interpretation?

**Yes, subject to a successful synthetic verifier and the media retrieval gate.**

The contracts separately bind database rows, media relationships/objects, candidate pricing knowledge, acquisition/tool provenance, source identities, and findings. Unknown values and missing evidence can be transported literally. `ipil:rescue:verify` now validates these contracts offline with synthetic fixtures and does not contact the source or write BPLS data.

### 6. What source gaps remain?

- signed/authenticated retrieval and re-hashing of at least the representative DTI byte;
- representative SEC retrieval, or an explicit finding that none exists in the authorized source scope;
- determination whether BIR uploads exist outside the reviewed business-document path;
- restoration/reacquisition of all 34 source bytes for a fresh atomic corpus, not only the index;
- retention/ownership disposition for 19 currently unassociated objects;
- reconciliation vocabulary for the 258 declared broken edges and one pricing-override edge, without repair;
- current snapshot/export consistency procedure and explicit read-only source profile;
- authoritative PSGC and LOB mapping decisions, which are intentionally later and do not block raw rescue;
- confirmation that all dynamic/untyped storage uses have been enumerated before final media completeness is claimed.

### 7. Is `ipil:cull` now safe to implement?

**No.**

The implementation gate remains closed until the media retrieval and completeness proof below passes. It is acceptable to design a read-only source adapter spike outside the culler only if separately authorized and incapable of finalizing a corpus. Do not add the `ipil:cull` command yet.

## Conditions to Reopen the Gate

All must be evidenced:

1. one authenticated DTI `getUrl` retrieval is streamed and hash-checked without persistence outside an approved private temporary location;
2. SEC and BIR scope is explicitly classified as observed, absent-in-scope, or alternate-path with evidence;
3. a source-backed storage-reference catalog covers typed and proven dynamic uses;
4. all 34 currently known objects can be enumerated and fetched, or every failure produces a stable media disposition;
5. orphan retention defaults to private preservation unless an authorized records/privacy decision says otherwise;
6. a fresh export consistency method and source read-only profile are documented;
7. a dry synthetic acquisition can finalize atomically into the approved private root and cannot write source, Git, public disk, or Cloud.

## Stop Conditions

Until the gate reopens:

- no `ipil:cull` command;
- no canonical mappings or `ipil:seed` implementation;
- no historical BPLS writes;
- no Spatie import;
- no current-price evaluation;
- no UI/report parity implementation;
- no Cloud upload, deployment, or public artifact;
- no omission of unassociated objects, missing bytes, or broken references to improve a parity percentage.

The next authorized wave should be narrowly named **MEDIA RETRIEVAL AND CULL SAFETY PROOF**, not culler implementation.
