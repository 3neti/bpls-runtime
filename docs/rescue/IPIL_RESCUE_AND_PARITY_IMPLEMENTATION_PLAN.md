# Ipil Rescue and Parity Implementation Plan

Status: **APPROVED PROGRAM PLAN — SOURCE RECONNAISSANCE AND CORPUS SEMANTICS V1 COMPLETE; CULL GATE CLOSED**

Approved: 2026-09-10

Governing compass: [`docs/agents/IPIL_RESCUE_AND_PARITY_COMPASS.md`](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)

Current evidence: [source reconnaissance](IPIL_SOURCE_RECONNAISSANCE_2026_09_10.md), [Rescue Corpus V1 semantic contracts](IPIL_RESCUE_CORPUS_V1_SEMANTIC_CONTRACTS.md), and [Cull Readiness Report](IPIL_CULL_READINESS_REPORT_2026_09_10.md).

## Objective

Create a repeatable, auditable local reconstruction of the current Ipil BPLS: source data, uploaded document bytes, recovered pricing evidence, historical transactions, reports, and recognizable operational UI/UX. Preserve source truth without turning history into current operational authority.

> **Rescue first. Reproduce second. Improve third.**

This is a rescue and parity program before it is a migration or redesign program. The first durable deliverable is a checksum-bound local rescue corpus. BPLS interpretation, historical projection, parity proof, UI reproduction, and product improvement follow in that order.

This plan does not authorize live access, culling, import, production migration, cutover, deployment, or application changes. Each implementation wave requires its own bounded authorization and evidence.

Implemented foundation as of 2026-09-10: Rescue Corpus V1 root, database, media, pricing, acquisition/tool provenance, `SourceIdentity`, and finding contracts; full offline semantic/integrity verification behind `ipil:rescue:verify`; fail-closed `ipil:seed` and `ipil:audit` frontiers; and the verify/seed/audit-only `bin/bpls-ipil-rescue-lab` wrapper. Synthetic tests reconcile semantic counts and bytes without real taxpayer data. The cull gate remains closed because authenticated media-byte retrieval and complete media-scope proof are not yet established. There is deliberately no `ipil:cull` command or live-source client.

## Target Flow

```text
CURRENT IPIL SOURCE (explicit read-only access)
        |
        |  ipil:cull --network (never automatic)
        v
LOCAL IMMUTABLE RESCUE CORPUS
database + media bytes + pricing evidence + manifests + checksums
        |
        |  ipil:rescue:verify
        v
VERIFIED LOCAL SOURCE EVIDENCE
        |
        |  ipil:seed (local corpus only)
        v
LOCAL BPLS RECONSTRUCTION
        |
        |  ipil:audit
        v
DATA / MEDIA / FINANCIAL / REPORT PARITY EVIDENCE
        |
        v
UI/UX CAPTURE -> PARITY SCAFFOLDING -> SIDE-BY-SIDE WALKTHROUGH
        |
        v
LATER PRODUCT AND UI IMPROVEMENT
```

## Command Boundaries

| Boundary | Responsibility | Must not do |
| --- | --- | --- |
| `ipil:cull` | Transport authorized source bytes and metadata into a new local corpus | Interpret semantics, seed BPLS, mutate source, or run automatically |
| `ipil:rescue:verify` | Prove corpus completeness and integrity from manifests/checksums | Repair, normalize, infer, import, or hide missing evidence |
| `ipil:seed` | Interpret only a verified local corpus through explicit mappings/dispositions | Contact live source, recalculate history, or invent facts |
| `ipil:audit` | Quantify source-to-corpus and corpus-to-BPLS parity | Mutate either side or accept mismatches implicitly |
| `bin/bpls-ipil-rescue-lab` | Guard local verification, seeding, audit, observatory, and parity work | Invoke `ipil:cull` implicitly or make rebuilds contact Ipil Cloud |

> **`ipil:cull` transports, `ipil:seed` interprets, and `ipil:audit` proves.**

## Safety and Authority Boundaries

1. Current Ipil is read-only. Nothing writes back.
2. Live access is explicit, separately authorized, network-only, logged, and absent from ordinary rebuilds, tests, seeds, CI, deploys, and scheduled work.
3. The rescue corpus is local/private by default. Nothing uploads it to Laravel Cloud, R2, S3, public artifacts, or another environment without a later explicit decision.
4. A completed snapshot is immutable evidence. Corrections create a new corpus version.
5. Every rescued record and byte has deterministic `SourceIdentity` provenance and checksum-bound manifests.
6. Missing evidence stays missing. Missing files, orphan references, unknown mappings, absent prices, and contradictions are recorded, never fabricated.
7. Historical and operational state remain distinct. A historical owner is not automatically a `User`; a historical Application is not executable; historical `Released` is not current release authority.
8. Historical financial truth is preserved and reconciled, not recalculated with current `Price` policy.
9. Source media bytes are evidence. Spatie Media Library manages a verified BPLS copy; it does not replace source truth.
10. Parity precedes simplification. Every intentional divergence is `MATCH`, `ADAPT`, `IMPROVE`, or `DEFER` with evidence and ownership.
11. The new Nelson Executable Application lifecycle remains the forward operational path. Rescued history supplies continuity, not a second lifecycle.

Existing migration characterization, mappings, preservation rehearsals, hashes, and safety boundaries remain evidence. This broader program must reconcile with them and reuse proven contracts where appropriate; it does not broaden their authority or treat earlier candidate cohorts as a complete rescue corpus.

## Git and Privacy Boundary

Commit to Git:

- command and domain code;
- manifest and source-identity schemas;
- deterministic mappers and non-PII mapping tables;
- synthetic fixtures, tests, and verification logic;
- redacted aggregate audit examples;
- documentation and operator instructions.

Keep out of Git:

- real taxpayer database dumps or snapshots;
- rescued SEC, DTI, BIR, and other uploaded document bytes;
- PII-bearing media/database manifests and identifying source keys;
- credentials, tokens, cookies, sessions, keys, connection strings, and sensitive logs;
- unredacted audit output, observatory indexes, and real-data exports.

Ignore rules and private placement must be proven before the first cull. Review-safe committed evidence may contain only redacted aggregates, non-sensitive schemas, and safe checksums.

## Rescue Corpus V1

The corpus is a self-describing, versioned private directory outside Git and public web roots:

```text
ipil-rescue/<corpus-id>/
    corpus.json
    source/
        database/
            snapshot.<source-format>
            schema.json
            table-manifest.json
        media/
            objects/...
            media-manifest.jsonl
        pricing/
            records.jsonl
            pricing-manifest.json
    provenance/
        source-identities.jsonl
        acquisition.json
        tools.json
    verification/
        checksums.sha256
        verification-report.json
        exceptions.jsonl
```

`corpus.json` binds the corpus/schema version, source/deployment identity, authorized run and tool versions, consistency method, row/media/pricing counts, subordinate manifest hashes, completion/verification state, known exceptions, and any parent corpus. A failed or partial capture stays partial and cannot become seed input by moving files. A completed corpus is never silently edited.

## `SourceIdentity` and Provenance Registry

Every source entity that may be mapped, imported, audited, or displayed gets a deterministic identity. The registry joins immutable evidence to BPLS interpretation; it does not establish legal identity.

Record source system/deployment/corpus/dataset/key, canonical payload hash, raw-evidence locator, observed timestamps/deletion markers, acquisition manifest, entity kind, mapping state, mapper version, disposition/evidence/authority, target reference after explicit mapping, and contradiction/orphan/missing/privacy flags.

Use the existing migration state grammar where applicable: `observed -> inferred -> proposed -> accepted -> rehearsed -> production-applied`. Raw source identifiers and PII remain private; Git-tracked reports use redacted identifiers or safe hashes. Matching names, contacts, registrations, filenames, or paths are review evidence, not identity authority.

## Wave 0 — Source and Database Reconnaissance

Document source technology and database version; deployment and export boundaries; read-only administrative access; schemas, collections/tables, indexes, relationships, IDs, timestamps, deletions, enums, and embedded structures; safe aggregate counts; owner/business/application/declaration/financial/document/pricing/user/reference topology; snapshot consistency options; and inaccessible or contradictory areas.

Compare any earlier private snapshots to the intended full corpus without assuming their coverage. Public legacy exports or accidentally accessible endpoints are not acquisition authority. Output a reviewed inventory, access contract, classification, and known blind spots—not source payloads in Git.

Exit: reviewers can name every dataset, consistency method, access authority, privacy treatment, and blind spot.

## Wave 1 — Media and Document Storage Reconnaissance

Prove where uploads live and how metadata reaches bytes. Inventory provider/bucket/key/access behavior; metadata relationships; filenames, MIME types, sizes, dates, existing checksums, variants, duplicates, and deletions; signed/authenticated download behavior; every observed documentary class including SEC, DTI, and BIR; and metadata-without-byte, byte-without-metadata, broken-path, zero-byte, unsupported-type, and access-denied cases.

Estimate transfer volume and restart needs without copying content into Git or public evidence.

Exit: every metadata-to-byte route is classified and transfer integrity can be independently verified.

## Wave 2 — Corpus Contract

Freeze and synthetically test root/table/media/pricing/provenance/exception schemas; canonical serialization and stable hashes; schema evolution; complete/partial/failed/superseded/verified states; atomic finalization; local permissions/encryption/backup expectations; redaction; deterministic replay; and corruption rejection.

Exit: a synthetic corpus can be produced, verified, rejected when altered, and proven absent from Git/public roots.

## Wave 3 — Explicit Network-Only `ipil:cull`

The culler must require an explicit option such as `--network`, authorized source profile, new corpus ID, and operator confirmation; fail outside an allowed local environment; verify read-only posture; acquire into an incomplete directory; finalize only after manifests close; resume without duplication or overwrite; redact logs; record retries/failures; and refuse Git, public, ordinary application, or Cloud/object-upload destinations.

It is never invoked by migrations, seeds, tests, CI, deploy hooks, schedulers, application boot, developer setup, or `bin/bpls-ipil-rescue-lab`.

### Lossless database culling

Capture every authorized dataset/field, including unknown fields. Preserve IDs, nulls, types, meaningful order, timestamps, deletions, contradictions, and unrecognized values. Record schema, per-table counts/hashes, and consistency evidence. Do not rename, normalize, join away, deduplicate, filter, or repair the raw layer. Quarantine extraction failures without dropping successful evidence.

### Document and media culling

For every media metadata row—including SEC, DTI, and BIR—acquire or explicitly fail to acquire its bytes. The private media manifest records source identity/relationship/key, original filename, declared/detected MIME, size/dates, rescued object identity/path, SHA-256, available source checksum, attempts, verified bytes, and disposition.

Dispositions include `rescued`, `source-missing`, `access-denied`, `corrupt`, `zero-byte`, `duplicate-content`, `orphan-metadata`, and `orphan-byte`. Missing bytes get no placeholders and do not count as parity. Content may be deduplicated only if every original relationship and content hash remains provable.

### Recovered pricing corpus

Treat pricing as a separate evidence class. Preserve all observed rate/configuration rows, relationships, dates, scopes, overrides, edits, and raw values without turning them into current `Price` policy. Bind them with their own manifest/checksums for later reconciliation without fiscal authorization.

Exit: one authorized run yields an immutable corpus whose database, media, pricing, provenance, and exception manifests close deterministically, with no source mutation or upload.

## Wave 4 — `ipil:rescue:verify` and Media Integrity

Offline verification validates schemas, manifest bindings, completion state, and every checksum; recounts database rows; checks canonical hashes; compares media metadata to objects; verifies byte sizes/SHA-256; identifies extra, missing, truncated, corrupt, duplicate, orphaned, or modified files; distinguishes source-missing/access-denied from transfer corruption; and proves no payload is tracked or public.

It produces machine-readable private results and a redacted human summary. It never heals evidence; repair means a new acquisition or explicitly versioned additive evidence.

Exit: zero unexplained integrity failure, with every exception counted and carried forward.

## Wave 5 — Offline `ipil:seed`

`ipil:seed` accepts only a local corpus whose current verification binds its root manifest. It fails for incomplete/changed evidence or any network source.

```text
raw evidence -> SourceIdentity -> structural projection
    -> reference proposal -> identity/mapping proposal
    -> map / preserve / quarantine / defer / reject
    -> historical projection -> source-to-target evidence
```

Mappers are versioned, deterministic, idempotent, dependency-ordered, and explain exclusions. Proposal generation does not imply acceptance.

### Reference mappings, including PSGC barangays

Map province/municipality/barangay, line-of-business/category, document type, status, organization form, and other reference data explicitly. Source barangay names/IDs require a source-to-PSGC mapping. Normalization may propose but never accept. Historical/renamed/unknown/contradictory values remain visible with evidence, authority, version, and disposition.

### Owners, businesses, users, and Applications

Preserve historical `BusinessOwner`/`Business` facts and relationships without fabricating `User` accounts, credentials, email, submission actors, or legal ownership. Shared names, contacts, and registration numbers follow existing reconciliation boundaries.

Historical Applications are read-only evidence. Preserve source keys, literal lifecycle assertions, dates, deletions, declarations, relationships, and provenance. Do not allocate current numbers, notify, queue, trigger clearances, create payables, issue permits, or enter the Nelson lifecycle. Historical `Released` never becomes current issuance, validity, release, or legal effect.

Do not fabricate lodging/submission manifests, ceremonies, signatures, consents, acknowledgements, submission actors, or receipt events. If current schema requires an unproven fact, use a non-operational historical projection or leave it unresolved.

### Historical finance and pricing reconciliation

Import persisted historical assessments, schedules, lines, payments, receipt evidence, totals, dates, statuses, overrides, and contradictions through the existing preservation boundary where compatible. Never rerun current `Price`, infer fee identity from a name, calculate missing liability, manufacture collections/receipts, or alter current policy.

Compare historical outcomes to recovered pricing only as audit classifications. Explainable/unexplained difference and authority gaps remain evidence, not recalculation or future-policy acceptance.

### Spatie Media Library and private storage

After corpus verification and accepted ownership mapping, import verified copies into Spatie Media Library collection `application_documents` on a private Laravel disk. Spatie stores the media record and locator in the BPLS database while the actual file bytes remain on the configured filesystem disk. Retain corpus ID, `SourceIdentity`, original metadata, source SHA-256, and import hash; verify the managed copy checksum; preserve missing-byte entries as missing evidence; prevent public delivery; and leave immutable corpus bytes separate.

Generated municipal artifacts—permits, payment orders, assessment slips, receipts, reports—use separate purpose-specific collections/storage. They are not `application_documents` or source uploads.

Use a local private disk in the rescue lab. A later production design may use a private Cloudflare R2 or other S3-compatible disk with encryption, least privilege, private delivery, retention, backup, checksum verification, and explicit transfer authority. Seeds/rebuilds never upload automatically.

Exit: repeated offline seeding produces identical projections/dispositions, no operational activity, and an explanation for every excluded record.

## Wave 6 — `ipil:audit` Quantitative Parity

Audit both source-inventory-to-corpus and corpus-to-BPLS boundaries. Report counts by entity/status/deletion/year/disposition; relationship and orphan parity; mapping coverage/reasons; media metadata/bytes/checksums/missing/corrupt/duplicates/orphans; historical financial counts and integer-centavo totals at application/schedule/line/payment/receipt levels; recovered-price reconciliation; report fixture parity; UI/workflow dispositions; and a stable exception inventory/delta.

No single percentage may hide exceptions. Every denominator and exclusion class is explicit.

| Dimension | Example acceptance grammar |
| --- | --- |
| Rows | `source = rescued`; `rescued = mapped + preserved + quarantined + deferred`; zero unexplained rows |
| Relationships | 100% of source edges mapped, preserved, or individually dispositioned as orphans |
| Media | 100% of acquired bytes checksum-match; source-missing/access-denied stay explicit and never count as rescued |
| Finance | Exact counts and centavo totals for each eligible class; contradictions quarantined, never rounded away |
| Mappings | Zero unexplained cases; unresolved PSGC/identity/policy cases have stable reason, owner, and gate |
| Reports | Field/count/total equality for `MATCH`; documented transformation for `ADAPT`; no silent omissions |
| UI | Every observed priority surface has evidence and `MATCH`, `ADAPT`, `IMPROVE`, or `DEFER` |

Illustrative accounting: `10,000 source applications = 9,700 preserved + 200 quarantined + 100 deferred`; `2,400 media rows = 2,350 verified bytes + 30 source-missing + 10 access-denied + 10 orphan-metadata`; or `PHP 12,345,678.90 source paid = PHP 12,345,678.90 historical projection`. These are examples, not Ipil facts.

Exit: row, relationship, media, financial, mapping, and report totals balance inside declared classes with no unexplained divergence.

## Wave 7 — Local Import Observatory and Scale Proof

Provide a local-only observatory for corpus/verification identity, dataset/mapper progress, disposition funnels, unresolved mappings, missing/corrupt/orphan media, financial reconciliation, provenance-safe drill-down, audit deltas, and search/index health. It is not public, a taxpayer-data browser, or a mutation/acceptance back door.

Stress deterministic synthetic scale and separately controlled private-corpus scenarios for business/owner/application/source/year/status/barangay/document search; BPLO/Treasury/report/import filters; pagination/sorting/collision groups; media lookups; query plans/indexes; response budgets; memory; and report/export load. Synthetic load proves performance shape, not production parity; only redacted aggregates may enter Git.

Exit: agreed volume is usable within documented budgets and no search bypasses authorization or historical/operational separation.

## Wave 8 — Historical Projection and Report Parity

Build read-only Historical Business Detail with owner/business/location/registration/application/declaration/document/history provenance and missing-evidence signals. Build a recognizable Historical Executable Application projection that cannot execute Nelson actions. Link history to current records only through accepted mappings.

Inventory actual Ipil reports, filters, columns, groups, totals, date semantics, empty states, exports, and authorization. Give each priority report source fixtures, expected output, field mapping, financial authority classification, and parity result. Historical reports read persisted evidence and never current policy.

Exit: priority history and reports reproduce agreed facts/totals, remain private/read-only, and mark adapted or unavailable behavior.

## Wave 9 — Systematic Current-Ipil UI/UX Capture

With authorized test or redacted records, capture routes, roles, navigation, dashboards, lists, filters, search, pagination, details, forms, modals, documents, reports, exports, error/empty/loading states, responsiveness, terminology, hierarchy, density, defaults, validation, feedback, and end-to-end workflows. PII screenshots remain private; committed evidence is redacted or synthetic.

| Disposition | Meaning |
| --- | --- |
| `MATCH` | Reproduce required, evidence-backed behavior |
| `ADAPT` | Preserve intent within approved Laravel architecture/authority |
| `IMPROVE` | Change deliberately after parity is understood and accepted |
| `DEFER` | Retain evidence, reason, owner, and revisit gate |

Legacy behavior is evidence, not policy authority. Unsafe behavior must be classified, never silently copied or omitted.

Exit: priority surfaces have captures, source links, role/authority analysis, and approved dispositions.

## Wave 10 — Parity Scaffolding and Walkthrough

Implement bounded recognizable navigation, lists, search/filter, details, documents, reports, read-only history, missing-evidence/adaptation messages, responsiveness, accessibility, and browser checks tied to fixtures/matrix.

Walk current Ipil and local BPLS side by side with the same authorized scenarios. Record every visible, data, report, and workflow difference. Never resolve difference by altering evidence or bypassing authority.

Exit: priority scenarios pass data, media, finance, report, and UI review; all remaining differences are accepted `ADAPT`, `IMPROVE`, or `DEFER`.

## Wave 11 — Product and UI Improvement

Only after corpus integrity, quantitative audit, report parity, and side-by-side review pass may simplification/redesign begin. Improvement preserves evidence/provenance, remains compatible with the forward Nelson lifecycle, names the parity baseline, records user benefit/authority/migration impact/rollback, and changes the matrix only through approved `IMPROVE` decisions.

## Intended Operator Experience

```text
bin/bpls-ipil-rescue-lab verify <corpus-id>
bin/bpls-ipil-rescue-lab seed <corpus-id>
bin/bpls-ipil-rescue-lab audit <corpus-id>
bin/bpls-ipil-rescue-lab observe <corpus-id>
```

Live acquisition is deliberately absent from that ordinary lab flow:

```text
php artisan ipil:cull --network --source=<authorized-profile> --corpus=<new-id>
```

These are intended interfaces, not implemented commands. Exact flags, confirmations, and private placement are implementation decisions.

## Program Acceptance

The program completes only when an authorized immutable corpus accounts for the agreed inventory; offline checks prove acquired data/media; deterministic seeding dispositions every entity; historical identity/Applications/finance/media stay non-operational and provenance-linked; row/edge/media/centavo totals balance; unresolved PSGC/identity/document/financial/policy mappings have explicit ownership/gates; priority reports and UI have approved parity decisions; side-by-side review has no unexplained divergence; taxpayer evidence and credentials stay outside Git/public/Cloud storage; and ordinary rebuilds never access the live source.

## Active Frontier and Stop Conditions

The active frontier is **Wave 0 reconnaissance and the Wave 2 synthetic corpus contract**, subject to separate authorization for live inspection. Stop for a decision when read-only authority/scope/credentials are missing; consistency is unprovable; media access exceeds authority; private evidence could enter Git/public/Cloud storage; checksums/counts/totals fail; mapping requires identity/policy/lifecycle inference; history must become operational; reports require current price calculation; UI lacks audited corpus/capture/disposition; or any step would redesign, deploy, cut over, or write to production before its gate.

Future agents must not jump from reconnaissance or partial seeding into UI redesign. The fixed order is: verified corpus, deterministic reconstruction, quantitative audit, read-only/report parity, UI capture, parity scaffolding, side-by-side acceptance, then improvement.
