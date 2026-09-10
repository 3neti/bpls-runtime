# Ipil Source Reconnaissance

Status: **COMPLETE FOR RESCUE CORPUS V1 — READ-ONLY — REDACTED**

Observed: 2026-09-10

Governing documents:

- [Ipil Rescue and Parity Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)
- [Ipil Rescue and Parity Implementation Plan](IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md)
- [Rescue Corpus V1 Semantic Contracts](IPIL_RESCUE_CORPUS_V1_SEMANTIC_CONTRACTS.md)
- [Cull Readiness Report](IPIL_CULL_READINESS_REPORT_2026_09_10.md)

## Scope and Method

This wave characterized the current Ipil implementation, the authorized live administrative surface, the private normalized production snapshot, and the previously recovered fee catalog. It performed authenticated read-only navigation, local checksum/count analysis, and source-code inspection. It did not mutate the source, download the full media corpus, write BPLS domain data, create `ipil:cull`, seed canonical records, capture UI parity, deploy, or copy taxpayer payloads into Git.

Committed evidence is restricted to schemas, counts, hashes, classifications, and redacted findings. The underlying rows, source identifiers, filenames, taxpayer details, stored objects, credentials, and operator identity remain private.

## Evidence Set and Fingerprints

| Evidence class | Evidence | Fingerprint / binding | Use in this report |
| --- | --- | --- | --- |
| Current implementation | `docs/sources/legacy/bpls-system-main.zip` | SHA-256 `9c90a376a538eccc440c7a887121eb2ec2a12848236bfc389a9691adc232eb4b` | Convex schema, functions, indexes, upload/download path, report builder |
| Source schema | `packages/backend/convex/schema.ts` inside source archive | SHA-256 `89a879f6702c8cb05092cd681cddd06f2625da43023b253323f053f310642cd9` | Declared tables, fields, relationships, unions, indexes |
| Raw database evidence | Authorized Convex backup captured `2026-08-16T22:44:00+08:00` from deployment `adjoining-porcupine-740` | Archive SHA-256 `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57`; deployment SHA-256 `92fd4fc5ca7d0e476f57208e4a3b2a7a241c1d45b7eee755212a9253a3ce0d2b` | Row counts, observed fields/values, relationship integrity |
| Normalized snapshot manifest | Private `prod-convex-20260816-224400/manifest.json` | SHA-256 `20b1eed513ee6fc837657fb00f8f47918522ff450ee28e3019235e118dd5a8d7` | 53-table inventory and per-table hashes |
| Media evidence | Private `storage-index.json` from the same intake | SHA-256 `78bdc53b3e9f2c3ddab9cd8c27e56d96f727584fa639a8e0e09ec4c392ce33ff` | Stored-object count, size, MIME, content hash, typed-reference coverage |
| Raw pricing evidence | Private `prod-convex-reference-catalog-v2-20260816-224400/fee-catalog-manifest.json` | SHA-256 `2a1223adc10bb1cc2f1b5480185a2c2ac591a646f1ae59a0b9bcfbd849782eda` | Exact five-dataset pricing subset |
| Interpreted pricing knowledge | Private bounded staging report `prod-convex-stage-fee-catalog-v1-20260908-bounded/report.json` | SHA-256 `3b675ecde03c7dd1a12712f19448f718f92fbe56ad6ea8bff7686c53a38a5911` | Candidate-only structural characterization; no price activation |
| Live source observation | Authenticated `https://www.ipil-bpls.online/dashboard` | Observation timestamp 2026-09-10; no session material retained | Current route/surface confirmation and current activity only |

These are independent evidence classes. The raw database archive is not the media index. The media index is not proof that an object is linked to a taxpayer record. The fee-catalog subset is raw pricing evidence; the later staging report is interpreted knowledge and carries a different fingerprint.

## Source Technology and Access Boundary

- The application is a Next.js monorepo backed by Convex tables, indexes, search indexes, auth tables, functions, workflows, and Convex file storage.
- The source schema declares 53 exported tables when Convex Auth tables are included.
- Convex document `_id` and `_creationTime` are source-native identity/time facts. Many business timestamps are separately stored as strings.
- Several records use optional fields for migration compatibility. Owner/business references in `businesses` and `permits` accept both typed Convex IDs and legacy strings.
- Soft deletion exists for owners, businesses, applications, permits, and many configuration tables. Payment schedules can be hard-deleted; clearance types have force-delete behavior in the implementation.
- The live administrative surface was reachable with the previously authorized session. Read-only pages for dashboard, businesses, permits, and permit applications were observed. No form was submitted and no record was changed.
- The source version does not expose a database engine version comparable to PostgreSQL/MySQL. Rescue Corpus V1 records the engine as `convex` and permits `source_engine_version: null` unless an authoritative version is captured.

## Complete Table Inventory

The snapshot manifest closes at **53 tables and 308,038 rows**.

| Table | Rows | Class |
| --- | ---: | --- |
| `activity_logs` | 118,450 | audit |
| `assetSizes` | 3 | reference |
| `authAccounts` | 33 | auth/private |
| `authRateLimits` | 2 | auth/private |
| `authRefreshTokens` | 73,546 | auth/private |
| `authSessions` | 2,089 | auth/private |
| `authVerificationCodes` | 0 | auth/private |
| `authVerifiers` | 0 | auth/private |
| `barangays` | 44 | location reference |
| `billing_group_fee_fields` | 5 | ad-hoc billing configuration |
| `billing_group_fees` | 223 | ad-hoc billing fee library |
| `billing_group_fields` | 12 | ad-hoc billing configuration |
| `billing_group_line_items` | 42,835 | ad-hoc billing evidence |
| `billing_group_print_layouts` | 4 | report/layout configuration |
| `billing_group_records` | 23,450 | ad-hoc billing evidence |
| `billing_groups` | 5 | ad-hoc billing configuration |
| `business_categories` | 14 | business reference |
| `business_nature_categories` | 32 | legacy business reference |
| `business_owners` | 3,174 | taxpayer registry |
| `business_permit_applications` | 3,112 | permit history |
| `business_subcategories` | 58 | business reference |
| `businesses` | 3,192 | taxpayer registry |
| `cities` | 38 | location reference |
| `clearance_types` | 5 | clearance reference |
| `counters` | 2 | numbering/configuration |
| `departments` | 1 | user reference |
| `division_groups` | 827 | pricing/LOB junction |
| `divisions` | 23 | pricing/LOB reference |
| `fee_names` | 28 | pricing reference |
| `fee_overrides` | 9 | pricing evidence |
| `fees` | 294 | pricing evidence |
| `groups` | 828 | line-of-business reference |
| `majors` | 8 | line-of-business reference |
| `manual_transactions` | 0 | ad-hoc finance |
| `mayorsPermitCategories` | 4 | permit reference |
| `payment_schedule_config` | 0 | finance configuration |
| `payment_schedules` | 7,601 | historical assessment/schedule evidence |
| `payments` | 5,806 | historical collection/receipt evidence |
| `permissions` | 141 | authorization reference |
| `permit_clearances` | 14,470 | clearance evidence |
| `permit_layouts` | 1 | permit artifact layout |
| `permits` | 2,731 | historical permit evidence |
| `platform_settings` | 1 | platform/configuration/media |
| `provinces` | 13 | location reference |
| `receipt_layouts` | 3 | receipt layout |
| `report_export_chunks` | 0 | report export working data |
| `report_exports` | 10 | report export evidence/media |
| `role_permissions` | 517 | authorization junction |
| `roles` | 10 | authorization reference |
| `saved_reports` | 10 | report definitions |
| `surcharge_penalty_config` | 1 | finance configuration |
| `unitsOfMeasurement` | 4,345 | application declaration evidence |
| `users` | 28 | platform users |

Auth/session/token tables are lossless source evidence and must be classified as highly sensitive. Their rescue does not authorize credential reuse or creation of BPLS users.

## Core Representation and Observed Values

### Owners, businesses, users, and locations

- `business_owners` stores personal/contact/location fields, `ownerType`, blacklist evidence, and soft-deletion evidence. Observed `ownerType`: 2,738 `Single`, 436 `Group`. Eleven rows are soft-deleted. Five rows explicitly carry blacklisted state.
- `businesses` points to an owner and stores registration, location, classification, scale, occupancy, employee, contact, embedded legacy LOB/override, document, blacklist, and soft-delete fields. Observed active/not-deleted count is 3,188; four are soft-deleted.
- Ownership-type spelling is not canonical in source evidence: `sole-proprietorship` 2,744, `corporation` 368, `non-profit` 38, `cooperative` 30, plus title-case legacy values (`Sole Proprietorship` 8, `Corporation` 2, `Partnership` 2).
- Business scale is 3,082 `Micro`, 106 `Small`, 2 `Medium`, and 2 `Large`.
- `users` is a separate platform-user table: 28 rows, only one observed `linkedBusinessOwnerId`. This proves that owner and user are different source concepts.
- Location hierarchy is `provinces <- cities <- barangays`. Only 2/13 provinces, 1/38 cities, and 13/44 barangays have a populated `code`; code presence alone is not an accepted PSGC crosswalk.

### Applications and lines of business

- `business_permit_applications` points to one owner and one business and embeds lines of business, excluded fees, variable mappings, and application-specific fee overrides.
- Observed status: 3 `Draft`, 203 `Assessment`, 28 `Pending Payment`, 2,878 `Released`; no `Approval` rows were present in this snapshot although the union permits it.
- Observed type: 445 `New`, 2,617 `Renewal`, 50 `Additional`.
- Observed mode of payment among populated rows: 1,480 `Annually`, 20 `Semi-Annually`, 1,610 `Quarterly`; two rows omit the optional field.
- Applications contain 4,186 embedded LOB declarations and nine embedded fee overrides. LOB declarations carry a string `businessCategory`, not a typed `groups` foreign key in the observed data. Name-based matching is therefore evidence for a candidate only.
- Forty-seven applications are soft-deleted; 3,065 are not deleted.

### Assessment, schedules, payments, receipts, and permits

- The source has no standalone immutable assessment table. Assessment evidence is split across application `totalFees`/assessment fields and `payment_schedules.fees`, surcharge, penalty, total, paid amount, due date, and section number.
- `payment_schedules` contains 7,601 rows and 24,169 embedded fee lines. Status: 5,673 `paid`, 3 `partial`, 1,925 `pending`. Sections 1–4 contain 3,007, 1,546, 1,524, and 1,524 rows respectively.
- `payments` contains 5,806 rows. Status: 5,676 `completed`, 129 `failed`, 1 `pending`. Methods: 5,512 Cash, 285 Check, 5 Bank Transfer, 3 GCash, 1 PayMaya. Receipt number is a field on payment evidence; there is no separate source receipt ledger/table.
- `permits` contains 2,731 rows: 2,728 `Active`, 3 `Expired`; 22 rows are soft-deleted and 2,709 are not. Fifteen legacy permits omit the optional application link; every populated application link resolves.
- `permit_clearances` contains 14,470 rows and snapshots clearance name/short name/certificate beside its reference. 13,651 are complete and 819 incomplete.
- `billing_group_records` and 42,835 subordinate line items represent a separate ad-hoc billing subsystem. Record status: 23,046 completed, 343 cancelled, 61 pending. It must not be silently folded into permit payments.

### Pricing and fee configuration

- `majors -> divisions -> division_groups <- groups` is the LOB/fee inheritance topology.
- `fees` may belong to a division or group and use `Constant`, `Formula`, or `Range`; observed counts are 65, 119, and 110 respectively.
- Observed optional fee categories are 18 Tax, 86 Regulatory Fee, and 41 Other Charges. `Special Fee` is declared but not observed. Only two fee rows carry explicit status, both `Custom`.
- `fee_overrides` points to a `division_group` and a fee. All nine division-group references resolve; eight fee references resolve and one points to an absent fee.
- Current source values are historical pricing evidence. They are not current Laravel `Price` authority and must never be executed merely because they parse.

### Reporting structures

- `saved_reports` stores a JSON-stringified report configuration, resource type, creator, visibility, timestamps, and deletion state. Ten rows exist: eight public, two private, one soft-deleted.
- `report_exports` stores configuration, workflow/progress, output storage IDs, row/file totals, creator, expiry, and status. All ten observed exports are completed `permit_applications` CSV exports.
- `report_export_chunks` is a working table and is empty in the snapshot.
- Source report configuration declares resource families for permit applications, billing groups, payments, permits, businesses, business owners, and manual transactions. Permit-application reports can project application, owner, business, LOB, persisted schedule-fee, payment/OR, schedule, clearance, employee, and financial metric dimensions.
- Receipt, billing-group receipt, and permit print layouts are data-backed template structures. They are source report/artifact evidence, not automatically accepted municipal formats.

## Relationship Integrity

| Edge | Observed edges | Missing/absent target | Classification |
| --- | ---: | ---: | --- |
| business -> owner | 3,192 | 0 | structurally established |
| application -> owner | 3,112 | 0 | structurally established |
| application -> business | 3,112 | 0 | structurally established |
| schedule -> application | 7,601 | 69 | unresolved historical reference |
| payment -> application | 5,806 | 3 | unresolved historical reference |
| payment -> schedule | 5,806 | 56 | unresolved historical reference |
| permit -> owner | 2,731 | 10 | unresolved legacy reference |
| permit -> business | 2,731 | 10 | unresolved legacy reference |
| permit -> application | 2,716 populated | 0; 15 omit field | valid optional legacy gap |
| clearance -> application | 14,470 | 0 | structurally established |
| clearance -> clearance type | 14,470 | 110 | five absent target IDs |
| fee override -> division group | 9 | 0 | structurally established |
| fee override -> fee | 9 | 1 | unresolved pricing reference |

The established staging total remains **258 unresolved declared edges referring to 101 distinct absent targets**: 69 + 3 + 56 + 110 + 10 + 10. Optional permit-to-application absence and the separately identified pricing override are different exception classes and must not be folded into that denominator.

## Complete Legacy Document Path

The source-code path is deterministic:

```text
businesses.documents[]
  -> storageId: Convex _storage identity
  -> authenticated generateUploadUrl()
  -> HTTP POST of bytes to Convex file storage
  -> businesses.addDocument() stores document metadata
  -> businesses.getDocumentUrl(storageId)
  -> ctx.storage.getUrl(storageId)
  -> authenticated/signed retrievable URL
  -> bytes
```

The edit form uses the authenticated `businessOwners.generateUploadUrl` function, uploads the file bytes to that returned URL, receives a storage ID, and then calls `businesses.addDocument`. Metadata contains storage ID, document type, original filename, upload time/uploader, review status, and optional rejection reason. The application form carries ownership documents forward from the business by storage ID; it does not duplicate the bytes into an application table.

Declared document classes are:

- Sole Proprietorship: DTI Certificate;
- Partnership: SEC Certificate and Articles of Partnership;
- Corporation: SEC Certificate, Articles of Incorporation, By-Laws, GIS;
- Cooperative: CDA Certificate, Articles of Cooperation, By-Laws;
- Religious/Non-Profit: SEC Certificate, Articles of Incorporation, By-Laws, GIS.

No BIR document field, BIR document class, or BIR-specific storage relationship was found in the reviewed schema, backend functions, or upload components. BIR remains `unknown`: the culler must preserve any unexpected document type or untyped object rather than infer absence from the UI design.

### Representative real evidence

The snapshot contains one business with one document metadata row:

- type: DTI Certificate;
- review status: pending;
- storage identity: present and retained privately;
- exactly one matching storage-index entry;
- indexed MIME: JPEG;
- indexed size: 4,675 bytes;
- indexed SHA-256: present;
- archive entry locator: present.

The storage index proves that the original authenticated backup contained and checksum-verified the matching bytes. A public direct storage path correctly returned `InvalidStoragePath`; retrieval requires `ctx.storage.getUrl`. During this wave, the normalized private intake no longer had the original `snapshot.zip` or extracted `_storage` bytes beside it, so the indexed object could not be re-hashed locally. The live portal's first safely sampled business had no document. An end-to-end signed retrieval of the DTI object was therefore **not re-proven** in this wave.

No SEC or BIR metadata row was present in the 2026-08-16 snapshot. Their source-to-byte paths are declared/unknown respectively, not empirically verified.

### Stored-object inventory and orphans

The index contains 34 objects / 44,093,196 bytes:

| Detected/declared content type | Objects | Bytes |
| --- | ---: | ---: |
| PDF | 1 | 1,531,659 |
| JPEG | 17 | 17,244,872 |
| PNG | 5 | 7,636,476 |
| WebP | 1 | 9,262 |
| CSV | 10 | 17,670,927 |

The expanded source-backed storage relationship catalog finds 15 distinct, resolved typed references: one business document, one permit-layout background, three platform-setting images, and ten report-export CSVs. Every one of those 15 resolves to the storage index; none is metadata-without-index.

This supersedes the older 12-resolved/22-unassociated characterization. Under the expanded catalog, **19 objects remain unassociated**. Owner avatar and user profile fields are strings/URLs rather than typed `_storage` fields; none of their observed final path segments matches the 34 indexed storage identities. A missing typed association is not authority to delete an object. All 19 remain `orphan-byte` candidates pending source-use and retention decisions.

Because the byte archive is not currently beside the normalized intake, this wave cannot independently reclassify zero-byte, truncated, or corrupt content. The prior intake reported all 34 objects passing size and SHA-256 checks; that remains historical verification evidence, not a fresh byte verification.

## Recovered Pricing Corpus

The raw pricing subset is independently versioned and contains 1,981 rows:

| Dataset | Rows | SHA-256 |
| --- | ---: | --- |
| `division_groups` | 827 | `e9a5b22b2b50b12ea65b2d5f9bf0cbfa2cc9cbfcdc09bdbeffc6e3e7b2000c1e` |
| `divisions` | 23 | `a5684c0ccdcfbff5d7c0b38423f98260da92a5e1194d76ccc3afbc58545df33c` |
| `fee_overrides` | 9 | `c660f147de9d106cb4ff75cb030127e6111b90ff0054a63e4fe16c03dbcce4c8` |
| `fees` | 294 | `3b078b7b6f67ccfd347aa9bd643827f1371a446320275927ff340c5e4da43f20` |
| `groups` | 828 | `d126f7020c3244b997023f9832b66eb41f6e6458a623470e6c1537e1c52c1cb6` |

The bounded interpreter staged all 1,981 rows, created zero mappings, performed no domain writes or integrations, and emitted one open error for the absent fee referenced by one override. Its output is candidate knowledge, not a raw snapshot and not executable price policy.

Rescue Corpus V1 therefore requires three separate fingerprints:

1. the root/raw database evidence fingerprint;
2. a media manifest/object fingerprint family;
3. an interpreted-pricing-knowledge fingerprint bound to explicit raw dataset hashes and an interpreter version.

## Mapping Candidates — No Canonical Mapping Implemented

| Source concept | Candidate target/disposition | Confidence | Evidence and boundary |
| --- | --- | --- | --- |
| owner row | read-only historical owner projection | probable | Fields and source edge are deterministic; collision/legal-identity decisions remain unresolved |
| owner row -> BPLS User | no automatic mapping | established | Separate source `users` table; only one source user links to an owner |
| business -> source owner | preserve exact source edge | established | 3,192/3,192 references resolve |
| application -> source owner/business | preserve exact source edges | established | 3,112/3,112 of both edge classes resolve |
| source `Released` | literal historical status only | established | It is an observed source assertion, not proof of current release authority |
| application LOB `businessCategory` -> `groups` | propose exact/normalized candidate | ambiguous | Observed value is a string, not a typed foreign key; name equality cannot establish identity |
| source province/city/barangay -> PSGC | versioned source-to-PSGC proposal | ambiguous | Hierarchy is present, but most codes are absent and no authority crosswalk is accepted |
| clearance snapshots -> surviving clearance type | source reconciliation proposal | probable | 110 broken refs collapse to five IDs; denormalized names align, but acceptance is municipal authority |
| schedule/payment with absent parent | preserve/quarantine as historical financial evidence | established | Missing target is proven; substitution by amount/application similarity is forbidden |
| legacy permit without owner/business | non-operational historical permit evidence | established | Ten permit owner and business edges are absent; identity must not be invented |
| business DTI metadata -> media object | preserve exact relationship | established | One metadata row has one exact indexed storage object |
| SEC document bytes | preserve if encountered | unknown | Declared source document class but no representative snapshot row/byte |
| BIR document bytes | preserve unexpected type/object if encountered | unknown | No BIR class or observed row found; absence is not proven globally |
| raw fee hierarchy/rules -> current `Price` | candidate-only pricing knowledge | ambiguous | Structure is recoverable; fiscal authority, effective dates, and one override parent remain unresolved |
| historical receipt field -> canonical receipt | historical receipt evidence only | probable | Receipt number exists on payment; no standalone source receipt ledger proves issuance semantics |
| saved report/layout -> parity target | later `MATCH`/`ADAPT` candidate | probable | Structures and source fields are known; official acceptance and visual parity are not yet tested |

## Reconnaissance Findings

1. Database transport is structurally feasible and lossless JSONL already proved for the bounded snapshot.
2. Current live activity means the August snapshot is evidence, not a current cull substitute.
3. The database contains known historical referential gaps; a correct culler preserves them rather than failing the entire acquisition or repairing them.
4. Media metadata-to-storage linkage is deterministic for the one observed DTI record, but current byte retrieval has not been re-proven.
5. The normalized intake retained the storage index but not a locally re-verifiable byte archive. Future acquisition must close both manifest and bytes atomically.
6. Nineteen stored objects remain unassociated after expanding the typed-reference catalog. Preserve them privately until retention and ownership are decided.
7. SEC is declared but unobserved; BIR is neither declared nor observed in this evidence set.
8. The pricing corpus is identifiable and independently fingerprinted, but interpreted pricing remains candidate-only.
9. No canonical owner, business, LOB, PSGC, status, receipt, permit, report, or price mappings are authorized by this report.

## Privacy Statement

This document intentionally omits names, emails, phone numbers, TINs, addresses, business names, raw source IDs, storage IDs, filenames, object hashes tied to taxpayer records, credentials, and signed URLs. The browser inspection produced no saved screenshot or export. Real rows and media stay outside Git.
