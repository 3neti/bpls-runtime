# Ipil Media Cull Readiness Closure

Status: **COMPLETE — READ-ONLY — REDACTED**

Observed: 2026-09-10

Governing documents:

- [Ipil Rescue and Parity Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)
- [Ipil Rescue and Parity Implementation Plan](IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md)
- [Ipil Source Reconnaissance](IPIL_SOURCE_RECONNAISSANCE_2026_09_10.md)
- [Rescue Corpus V1 Semantic Contracts](IPIL_RESCUE_CORPUS_V1_SEMANTIC_CONTRACTS.md)
- [Cull Readiness Report](IPIL_CULL_READINESS_REPORT_2026_09_10.md)

## Scope and Safety

This closure wave tested the unresolved media boundary only. It used authenticated, read-only portal navigation, the checksum-bound private August intake, and the exact legacy source archive. It did not implement `ipil:cull`, enumerate or copy the current full media corpus, write source or BPLS data, accept mappings, import into Spatie, deploy, or place taxpayer identifiers or media in Git.

Real sample bytes and the 19-object inventory are mode `0600` under the ignored private rescue root. This document records only redacted counts, classifications, and proof outcomes.

## Storage Mechanism and Retrieval Path

The authoritative source is Convex file storage, not a database blob column, Laravel disk, S3 bucket, or public application directory. The complete observed business-document path is:

```text
businesses.documents[].storageId
  -> authenticated businesses.getDocumentUrl(storageId)
  -> ctx.storage.getUrl(storageId)
  -> generated /api/storage token URL
  -> HTTP response bytes
```

The storage identity and generated retrieval token are different values. A raw storage identity is not a downloadable public path. The source also uses the same provider for platform branding, permit-layout images, report exports, owner avatars, and user profile photos. Business-document removal deletes the database relationship but deliberately does not delete the stored object. User profile-photo replacement does not delete the previous object. These behaviors explain why unassociated bytes can legitimately remain without proving what any particular byte represents.

## Representative Retrieval Proof

| Sample | Source relationship | Authentication/path | Declared or observed file fact | Retrieved proof | Discrepancy |
| --- | --- | --- | --- | --- | --- |
| Business DTI evidence | `businesses.documents[0].storageId` | Existing authenticated Download control -> `businesses.getDocumentUrl` -> `ctx.storage.getUrl` | DTI Certificate; pending; JPEG; archived index size 4,675 bytes | HTTP bytes acquired privately; detected JPEG; 4,675 bytes; SHA-256 exactly matches archived index; browser decoded 500 x 410 image | None |
| Platform logo | `platform_settings.logoStorageId` | Already-rendered authenticated portal image -> generated Convex storage URL | PNG | 23,763 bytes acquired privately; browser decoded 120 x 120 image; SHA-256 and size match two archived entries | `duplicate-content`: two storage identities have the same content, so content identity must not replace relationship identity |

This proves both a real taxpayer-document relationship and a non-taxpayer storage relationship can produce retrievable, hash-stable bytes through the current source mechanism. It is a bounded mechanism proof, not a bulk cull or a claim that every current object was downloaded.

## SEC, DTI, BIR, and Other Document Scope

| Documentary class | Classification | Evidence and boundary |
| --- | --- | --- |
| DTI Certificate | **SUPPORTED AND RETRIEVABLE** | Declared schema/UI/backend path, one snapshot metadata relationship, exact storage-index relationship, and current authenticated byte retrieval with matching size/SHA/MIME/openability |
| SEC Certificate | **SUPPORTED BUT SOURCE BYTES MISSING** | Declared for partnership, corporation, and religious/non-profit ownership; the live corporation sample exposed the SEC upload control but no existing file; the August snapshot contains no SEC metadata relationship |
| Articles of Partnership/Incorporation, By-Laws, GIS, CDA/Articles of Cooperation | **SUPPORTED BUT SOURCE BYTES MISSING** | Declared by the same ownership-document component and backend requirements; no relationship was observed in the authorized snapshot |
| BIR evidence | **AMBIGUOUS** | No BIR field, declared UI class, or observed metadata exists, but `documentType` is an unrestricted source string and unidentified byte contents cannot be inspected from the retained index alone; absence cannot be proven globally |
| Unassociated stored bytes that might contain documentary content | **AMBIGUOUS** | No metadata permits documentary classification; filename/MIME alone cannot establish SEC, BIR, DTI, owner, or business association |

Missing declared documents stay missing. The future culler must not synthesize SEC/BIR rows or infer a documentary class from image/PDF content.

## Nineteen-Object Inventory

The private inventory accounts for every storage-index object not reached by a typed snapshot relationship:

| Content type | Objects | Bytes | Association classification |
| --- | ---: | ---: | --- |
| PDF | 1 | 1,531,659 | `UNRESOLVED` |
| JPEG | 15 | 12,144,955 | `UNRESOLVED` |
| PNG | 2 | 5,018,145 | `UNRESOLVED` |
| WebP | 1 | 9,262 | `UNRESOLVED` |
| **Total** | **19** | **18,704,021** | **19 `UNRESOLVED`; zero confirmed orphans** |

The private inventory records each raw storage identity, archive locator, content type, size, SHA-256, evidence role, intended media disposition, evidence notes, and `RETAIN_AND_ATTEMPT_RESCUE`. Its SHA-256 is `8cf33bab9682207d37b95b89f2bdcd5e5f033420444ca16d5c9e6c6450f89d40`. The raw inventory and identifiers are not in Git.

All 19 must be enumerated and retrieval-attempted by a future cull. Successful bytes use the media disposition `unassociated-byte` with association state `UNRESOLVED` or `PROBABLE_ASSOCIATION`. `orphan-byte` is reserved for `ORPHAN_CONFIRMED` after evidence or an authorized records decision. Unknown association is not discard permission.

The normalized intake currently retains 52 of the 53 table payloads; `activity_logs.jsonl` is absent even though its manifest entry and prior staging count remain. That prevents retrospective activity-log association of these objects from this normalized copy. It does not justify orphan classification or deletion. A fresh cull must retain the activity-log dataset like every other authorized table.

## Rescue Corpus V1 Consequence

The evidence requires two semantic clarifications: byte acquisition and relationship certainty are separate dimensions, and a database metadata relationship is not the same count as a storage-only inventory entry. Each media entry carries evidence role `metadata-relationship` or `storage-object` plus one association state:

- `ASSOCIATED`
- `PROBABLE_ASSOCIATION`
- `UNRESOLVED`
- `ORPHAN_CONFIRMED`

`unassociated-byte` preserves acquired bytes whose association remains probable or unresolved. `orphan-byte` requires confirmed orphan evidence. The offline verifier enforces this distinction with synthetic fixtures only. No live client or culler was added.

## Future Spatie Compatibility

Laravel already uses Spatie Media Library v11.23.7. `PermitApplication` defines private collection `application_documents` on disk `local`, whose root is `storage/app/private`; application-document rows bind the Spatie `media_id`, path, MIME, size, SHA-256, and source snapshot. The source DTI JPEG is therefore technically compatible with a later checksum-verified `addMedia` copy.

That is not mapping authority. A legacy document belongs to a source business, while `application_documents` belongs to a BPLS Application. Choosing an Application requires an accepted historical relationship; it must not be inferred merely to make import possible. The immutable rescue copy remains source truth. Generated permits, receipts, assessment slips, and reports remain separate from applicant evidence. A later private R2/S3-compatible disk may replace local byte storage only through an explicitly authorized private-storage design; ordinary seeding must never upload automatically.

## Mapping Candidates — No Canonical Mapping

| Source evidence | Candidate | Confidence | Boundary |
| --- | --- | --- | --- |
| `businesses.documents[].storageId` -> indexed/fetched object | Preserve exact source relationship and byte provenance | `ESTABLISHED` | Does not establish a target Application |
| DTI source document -> current document type | Propose DTI registration evidence | `PROBABLE` | Current catalog/municipal applicability still requires accepted mapping |
| SEC/other declared empty controls -> missing evidence | Preserve absence with no placeholder | `ESTABLISHED` | Empty required UI is not a rescued document |
| BIR -> legacy document relationship | No candidate relationship | `UNKNOWN` | No implemented source field/path exists |
| 19 unassociated objects -> source owner/business/document | Retain as `unassociated-byte` | `UNKNOWN` | All remain `UNRESOLVED`; MIME/content similarity is insufficient |
| Source business document -> Spatie `application_documents` | Propose only after accepted business/application relationship | `AMBIGUOUS` | Business-scoped evidence cannot be assigned to an Application automatically |
| Two matching logo-content objects | Preserve both identities; record duplicate content | `ESTABLISHED` | Deduplication must retain every original relationship and identity |

## Closure Findings

1. Current authenticated `ctx.storage.getUrl` retrieval works for a real DTI relationship and produces bytes identical to the archived index.
2. Convex file storage is the complete implemented provider boundary found in the reviewed source.
3. DTI is supported and empirically retrievable. SEC and other declared ownership documents are supported but absent in the observed source scope. BIR has no dedicated legacy field/path and remains ambiguous because the source accepts arbitrary document labels.
4. All 19 unassociated objects are privately inventoried and retained as `UNRESOLVED`; none is asserted to be a confirmed orphan.
5. Rescue Corpus V1 can transport every storage object without knowing its business meaning. Association can remain unresolved without loss.
6. Spatie is a technically compatible later managed copy, not source truth and not automatic application-mapping authority.

No real taxpayer identifier, filename, content checksum, generated storage URL, credential, or media byte appears in this report.
