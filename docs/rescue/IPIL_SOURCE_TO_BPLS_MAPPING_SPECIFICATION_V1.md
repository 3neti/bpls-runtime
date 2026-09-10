# Ipil Source-to-BPLS Mapping Specification V1

Status: **NORMATIVE — READY FOR SEED IMPLEMENTATION REVIEW; NO SEED AUTHORITY**

Effective: 2026-09-11

Mapping profile: `ipil-rescue-mapping-v1.0.0`

Contract: `bpls.ipil-source-to-bpls-mapping.v1`

Profile identity SHA-256: `edae710f7e29dcecb148d24e349eb4e0e3d6747704231a298d1d63bb97ab789c` (SHA-256 of `contract|profile|corpus-id|corpus-fingerprint`)

Evidence boundary: finalized corpus `ipil-20260910t153224z-2ab19c17`, fingerprint `d799a0c4da562094f3433f7ebe5b6f5175b4640e5738c518d4c2c51dc731fa81`

Companion evidence: [Gate 4 findings](IPIL_MAPPING_FINDINGS_2026_09_11.md), [decision register](IPIL_MAPPING_DECISION_REGISTER.md), [Rescue Corpus V1 contracts](IPIL_RESCUE_CORPUS_V1_SEMANTIC_CONTRACTS.md), and [governing compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md).

## 1. Authority and Boundary

This specification defines how a future `ipil:seed` implementation may interpret the one approved immutable corpus. It does not authorize implementing or running that command, creating canonical records, importing media into Spatie, changing the corpus, contacting Ipil Cloud, deploying, or beginning report/UI parity.

The governing sequence remains **rescue first, reproduce second, improve third**. `ipil:cull` transports; `ipil:seed` interprets; `ipil:audit` proves. Seeding may read only a corpus that first passes `ipil:rescue:verify` and matches the exact corpus ID and fingerprint above.

## 2. Normative Vocabulary

Every source row or embedded source concept receives exactly one disposition:

| Disposition | Meaning |
| --- | --- |
| `MAP` | Create or reuse a semantically equivalent BPLS record only when all identity and authority gates close. |
| `MAP_AS_HISTORICAL_EVIDENCE` | Project the literal source assertion into a read-only, non-operational historical representation. |
| `REFERENCE_DATA` | Use only through a versioned, reviewed crosswalk; a matching label is a proposal, not identity authority. |
| `PRESERVE_UNINTERPRETED` | Keep source facts and provenance available without assigning canonical meaning. |
| `DEFER` | Retain evidence but postpone target interpretation pending a named decision or capability. |
| `IGNORE_WITH_EVIDENCE_BASED_REASON` | Exclude from BPLS interpretation for a recorded reason while leaving it intact in the immutable corpus. |

Confidence is independently `ESTABLISHED`, `PROBABLE`, `AMBIGUOUS`, or `UNKNOWN`. Confidence describes the evidence for the disposition, not permission to execute it.

Findings use `FACT`, `INFERENCE`, `CONTRADICTION`, `UNRESOLVED_QUESTION`, or `IMPLEMENTATION_DECISION`. Seed exceptions use stable codes, severity (`INFO`, `WARNING`, `ERROR`, `BLOCKING`), status, corpus identity, hashed `SourceIdentity`, dataset, row ordinal, mapping profile, and redacted context. Raw source IDs and PII must not appear in ordinary logs, committed fixtures, or reports.

## 3. SourceIdentity, Provenance, and Idempotency

The source identity tuple is:

```text
(source_system, deployment_identity_sha256, corpus_id,
 dataset_key, source_key_sha256, source_payload_sha256)
```

The stable logical identity is the first five fields. The payload hash detects drift. Every interpreted record must retain the corpus fingerprint, dataset checksum, row/source identity hash, mapper contract/version, disposition, confidence, source timestamps and deletion markers, mapping decision reference, target type/id when one exists, and projection hash.

Idempotency is mandatory:

1. Re-running the same profile against the same fingerprint must create zero additional targets and return identical counts/fingerprints.
2. An existing mapping may be reused only when its source tuple, target snapshot hash, disposition, and mapper version still match.
3. A source identity mapped to two targets, a target changed after mapping, or the same logical identity with a different payload is `BLOCKING`.
4. A newer corpus is a new import batch. It must never mutate or silently supersede evidence from this corpus.
5. No row is dropped to improve a parity percentage. The row accounting equation is `source = mapped + historical + reference + preserved + deferred + ignored`, with every exception separately counted.

The existing `LegacySource`, `LegacyImportBatch`, `LegacyRecord`, mapping-plan/proposal/execution, `LegacyIdMapping`, validation, and exception seams should be reused. Gate 5 should add a corpus adapter and any dedicated immutable historical bundles needed for complete fidelity rather than bypass these controls.

## 4. Historical and Operational Separation

- A source owner is not a `User`; no account, password, role, actor, or login is fabricated.
- A source owner/business may be projected one-to-one for historical continuity, but duplicate-looking records are not merged. Historical registry targets require metadata `source_history=true`, `operationally_eligible=false`, and no linked User.
- Every source application projects with BPLS status `historical_evidence`, `submitted_by_id=null`, `application_number=null`, `can_continue=false`, and literal source number/status/type/deletion/timestamps in immutable provenance.
- Literal `Released`, approval, permit, clearance, assessment, payment, and receipt values are source assertions only. They do not establish present legal validity, current lifecycle state, liability, sufficiency, issuance, or numbering authority.
- The Nelson Executable Application lifecycle remains the sole forward operational path. Historical projections must be excluded from queues, mutations, current balances, current pricing, permit issuance, and ordinary citizen renewal selection unless a later explicit reconciliation authorizes a bridge.

## 5. Domain Mapping Rules

### 5.1 Owners and Businesses

Create one historical owner candidate per `business_owners` source identity and one historical business candidate per `businesses` source identity. Preserve the exact owner edge. Do not deduplicate by name, birth date, TIN, email, phone, registration number, or similarity. Normalized values are collision signals only. Group owners, soft-deleted rows, blacklist assertions, malformed/missing location references, and duplicate identifiers remain explicit flags.

The preferred Gate 5 implementation is the existing canonical registry plus enforced historical metadata/scopes and `LegacyIdMapping`, provided tests prove historical businesses cannot enter operational intake. If that invariant cannot be made structural, add dedicated immutable historical owner/business projection tables before seeding.

### 5.2 Applications and Continuity

Project exactly one non-operational historical Application per source application. Preserve exact source business and owner edges, source application number, source type and status, all timestamps, deletion/reversion/rejection assertions, payment cadence, assessment remarks, lines, fee overrides, and source search text only in private evidence. The `businessOwnerId` must agree with the linked business owner; disagreement blocks the Application.

Literal `New`, `Renewal`, and `Additional` map to the same-named BPLS type enum with `ESTABLISHED` vocabulary confidence, but only inside historical mode; this never imports current Nelson rules retroactively. The Application year is the calendar year of valid `submittedAt`, with `_creationTime` retained as corroboration. A missing/invalid submission date makes year projection blocking for the Application target while the source row remains preserved.

Business continuity is source-key based, never name based. Multiple Applications for one source business remain a history sequence. A Renewal does not prove an earlier Application exists in the rescued time window. `Additional` remains a literal historical type; it may use the existing enum label only as non-operational evidence and must not trigger an operational Additional workflow.

### 5.3 Lines of Business and Declarations

Each embedded `linesOfBusiness[]` item receives a deterministic item identity `(application SourceIdentity, zero-based source index)`. Preserve category label, capital/gross lexeme, per-line application type, exclusions, variable mappings, and employee facts.

A unique normalized match to source `groups.name` is a `PROBABLE` source-internal relationship, not an accepted BPLS `LineOfBusiness`. Canonical `line_of_business_id` stays null until a versioned reconciliation has municipal decision authority and evidence. Missing, duplicate-label, or unmatched categories remain historical declaration evidence. Never coerce ranges, placeholders, or non-cent values into cents.

### 5.4 Address and PSGC

Preserve source province/city/barangay IDs and labels as historical facts. The crosswalk target is `config/ipil_references.php` schema `psgc.ipil-barangays.v1`.

- Exact normalized label plus confirmed Ipil municipality hierarchy is only a `PROBABLE` proposal.
- Source `code` is not accepted: none of the 13 populated codes equals the current ten-digit PSGC codes.
- Misspellings, legacy subdivisions, foreign/out-of-municipality barangays, test values, and broken IDs remain unresolved.
- No fuzzy match is accepted automatically. A reviewed crosswalk must record source identity, target code, target label, basis, authority, reviewer, version, and confidence.

Until accepted, `Business.barangay` may retain the literal label, but `barangay_psgc_code` must remain null.

### 5.5 Financial History, Payments, OR Claims, and Permits

Financial interpretation uses source persisted values only. Never invoke `Price`, `AssessmentCalculator`, current `FeeRule`, current surcharge/penalty policy, or current rounding.

Preserve the raw numeric JSON lexeme and a normalized decimal string with detected scale. Populate integer centavos only when the value is finite, non-negative, and exactly representable to two decimals. Values with greater scale remain exact decimal evidence and raise `historical_amount_not_cent_exact`; they are never rounded. Use arbitrary-precision decimal parsing; do not pass source amounts through binary floating-point arithmetic.

Application totals, schedules, embedded fee lines, surcharge, penalty, payment events, failed/pending attempts, receipt-number claims, references, processor/actor assertions, and timestamps form one immutable historical financial bundle per Application. Billing-group transactions form a separate historical treasury/reporting evidence class and must not be joined to permit finance without an explicit source edge.

No Gate 5 implementation may write historical facts into operational `Assessment`, `PaymentSchedule`, `TreasuryCollection`, `Receipt`, balances, or authority-bearing reports. Extend the existing `LegacyHistoricalFinancialPreservedBundle` contract (or add a V2 bundle) to support partial schedules, multiple attempts, non-cent-exact values, edited fees, overrides, receipt multiplicity, broken edges, and exact raw lexemes.

The corpus establishes PHP as `PROBABLE`: individual transactions omit currency, but `platform_settings.currencyFormat` is `PHP`, the configured municipality is Ipil, and the municipal reports/financial surfaces are peso-denominated. Store that evidence basis with each bundle. Any explicit conflicting currency blocks automatic financial association.

Receipt numbers are claims, not proof of an Official Receipt. Duplicate claims stay separate. A payment source identity may project one historical payment event; it must not create an issued `Receipt`. Permits similarly remain immutable historical permit claims. The 15 permit rows without an Application and all broken owner/business edges are preserved as unresolved permit evidence.

### 5.6 Media

The corpus bytes and media manifest remain immutable source truth. Gate 5 may design a reconciliation, but this gate authorizes no Spatie copy.

- The single business DTI relationship is `PROBABLE` application evidence only after an exact business-to-Application scope decision.
- SEC and BIR are not synthesized. Missing source evidence stays missing.
- Nineteen `UNRESOLVED` storage objects remain `PRESERVE_UNINTERPRETED`; similarity or MIME does not assign ownership.
- Three conservative CSV/text MIME findings remain usable source bytes with the finding attached; two duplicate-content findings preserve every storage identity.
- A later authorized import may copy verified applicant evidence into private Spatie collection `application_documents`, retain corpus/source/checksum provenance, and verify the managed copy. Generated municipal artifacts, layouts, branding, and report exports remain separate collections/paths.
- Local private storage is the default. A later private R2/S3-compatible design requires explicit transfer authority, least privilege, encryption, retention, backup, and no public URLs. Ordinary seeding never uploads.

### 5.7 Pricing

Raw database pricing tables, media evidence, and the five-record interpreted pricing manifest are independent evidence classes with independent fingerprints. Source `fees`, `groups`, `divisions`, `division_groups`, and `fee_overrides` may propose reconciliations to BPLS `FeeRule`, `FeeCategory`, `FeeRuleRange`, and `LineOfBusiness`; labels and formulas do not establish identity or current authority.

The pricing manifest is candidate-only. It must never activate a `FeeCatalogVersion`, change current policy, or be used to recalculate history. Reconciliation reports compare persisted source charges to candidate pricing and current BPLS policy in separately labeled columns; differences are audit results, never repairs.

## 6. Complete Source Disposition Ledger

This ledger accounts for all 53 source tables. “Target/seam” is a design candidate, not execution authority.

| Source table | Rows | Disposition | Confidence | Target/seam and rule |
| --- | ---: | --- | --- | --- |
| `activity_logs` | 127,363 | `PRESERVE_UNINTERPRETED` | `PROBABLE` | Private audit/history evidence; actor/resource links are not identity authority. |
| `assetSizes` | 3 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | Business measurement evidence; preserve literal quantity/description. |
| `authAccounts` | 33 | `IGNORE_WITH_EVIDENCE_BASED_REASON` | `ESTABLISHED` | Authentication secrets/provider records are not migrated; corpus retention only. |
| `authRateLimits` | 2 | `IGNORE_WITH_EVIDENCE_BASED_REASON` | `ESTABLISHED` | Ephemeral security control, no historical BPLS meaning. |
| `authRefreshTokens` | 76,352 | `IGNORE_WITH_EVIDENCE_BASED_REASON` | `ESTABLISHED` | Ephemeral credentials; never stage into BPLS. |
| `authSessions` | 2,256 | `IGNORE_WITH_EVIDENCE_BASED_REASON` | `ESTABLISHED` | Ephemeral sessions; never create Users or sessions. |
| `authVerificationCodes` | 0 | `IGNORE_WITH_EVIDENCE_BASED_REASON` | `ESTABLISHED` | Empty ephemeral credential table. |
| `authVerifiers` | 0 | `IGNORE_WITH_EVIDENCE_BASED_REASON` | `ESTABLISHED` | Empty ephemeral authentication table. |
| `barangays` | 44 | `REFERENCE_DATA` | `AMBIGUOUS` | Versioned source-to-PSGC crosswalk; no automatic code/fuzzy acceptance. |
| `billing_group_fee_fields` | 5 | `PRESERVE_UNINTERPRETED` | `PROBABLE` | Billing/report configuration evidence. |
| `billing_group_fees` | 225 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | Separate billing-group charge catalog snapshot; not current FeeRule. |
| `billing_group_fields` | 12 | `PRESERVE_UNINTERPRETED` | `PROBABLE` | Dynamic billing/report schema evidence. |
| `billing_group_line_items` | 45,413 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | Exact billing transaction line evidence; never permit-finance inferred. |
| `billing_group_print_layouts` | 4 | `DEFER` | `PROBABLE` | Report/UI parity input, not seed-time executable layout. |
| `billing_group_records` | 25,372 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | Separate historical treasury/reporting bundle. |
| `billing_groups` | 5 | `REFERENCE_DATA` | `PROBABLE` | Source billing dataset definitions; reviewed mapping to BPLS reporting concepts. |
| `business_categories` | 14 | `REFERENCE_DATA` | `AMBIGUOUS` | Sparse business registry classification; do not conflate with LOB. |
| `business_nature_categories` | 32 | `REFERENCE_DATA` | `AMBIGUOUS` | Preserve catalog; no demonstrated row edge to core records. |
| `business_owners` | 3,194 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | One-to-one historical owner, no dedupe and no User. |
| `business_permit_applications` | 3,137 | `MAP_AS_HISTORICAL_EVIDENCE` | `ESTABLISHED` | One non-operational Historical Application per source row. |
| `business_subcategories` | 58 | `REFERENCE_DATA` | `AMBIGUOUS` | Preserve category hierarchy; sparse business use. |
| `businesses` | 3,212 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | One-to-one historical business with exact owner edge. |
| `cities` | 38 | `REFERENCE_DATA` | `AMBIGUOUS` | Versioned PSGC/location crosswalk. |
| `clearance_types` | 5 | `REFERENCE_DATA` | `PROBABLE` | Candidate `PermitClearance` type reconciliation; labels need authority. |
| `counters` | 2 | `PRESERVE_UNINTERPRETED` | `AMBIGUOUS` | Numbering-state evidence; never seed current counters. |
| `departments` | 1 | `REFERENCE_DATA` | `PROBABLE` | Historical organizational label; no User/role activation. |
| `division_groups` | 832 | `REFERENCE_DATA` | `PROBABLE` | Source LOB/pricing join catalog; 832 live rows differ from the 827-row pricing evidence input and require an explicit delta. |
| `divisions` | 23 | `REFERENCE_DATA` | `PROBABLE` | Candidate LOB/pricing hierarchy. |
| `fee_names` | 28 | `REFERENCE_DATA` | `PROBABLE` | Candidate fee vocabulary only. |
| `fee_overrides` | 9 | `MAP_AS_HISTORICAL_EVIDENCE` | `AMBIGUOUS` | Preserve override rules and one broken fee edge; never activate. |
| `fees` | 294 | `REFERENCE_DATA` | `AMBIGUOUS` | Candidate FeeRule/range/formula reconciliation, not policy. |
| `groups` | 833 | `REFERENCE_DATA` | `PROBABLE` | Source LOB labels; live corpus has five more rows than the 828-row pricing input. |
| `majors` | 8 | `REFERENCE_DATA` | `PROBABLE` | Source LOB hierarchy. |
| `manual_transactions` | 0 | `IGNORE_WITH_EVIDENCE_BASED_REASON` | `ESTABLISHED` | Empty source feature; absence remains audited. |
| `mayorsPermitCategories` | 4 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | Business measurement/category evidence; not present permit authority. |
| `payment_schedule_config` | 0 | `PRESERVE_UNINTERPRETED` | `ESTABLISHED` | Empty configuration is an observed fact; no defaults invented. |
| `payment_schedules` | 7,648 | `MAP_AS_HISTORICAL_EVIDENCE` | `ESTABLISHED` | Exact historical schedule assertions inside immutable financial bundles. |
| `payments` | 5,874 | `MAP_AS_HISTORICAL_EVIDENCE` | `ESTABLISHED` | Exact payment attempts/events and OR claims; no operational Collection/Receipt. |
| `permissions` | 141 | `DEFER` | `ESTABLISHED` | Access-parity evidence only; do not grant current permissions. |
| `permit_clearances` | 14,615 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | Literal clearance assertions; 110 broken type edges remain exceptions. |
| `permit_layouts` | 1 | `DEFER` | `ESTABLISHED` | Permit artifact/UI parity evidence; no active layout. |
| `permits` | 2,766 | `MAP_AS_HISTORICAL_EVIDENCE` | `AMBIGUOUS` | Historical permit claims only; preserve 15 unlinked and broken edges. |
| `platform_settings` | 1 | `PRESERVE_UNINTERPRETED` | `ESTABLISHED` | Municipality/format/branding evidence; no configuration write. |
| `provinces` | 13 | `REFERENCE_DATA` | `AMBIGUOUS` | Versioned PSGC/location crosswalk. |
| `receipt_layouts` | 3 | `DEFER` | `ESTABLISHED` | Report/UI parity input; never active receipt authority. |
| `report_export_chunks` | 0 | `IGNORE_WITH_EVIDENCE_BASED_REASON` | `ESTABLISHED` | Empty transient export table. |
| `report_exports` | 11 | `PRESERVE_UNINTERPRETED` | `ESTABLISHED` | Export metadata/bytes are report-parity evidence, not taxpayer reports in Git. |
| `role_permissions` | 517 | `DEFER` | `ESTABLISHED` | Access-parity evidence; no current grants. |
| `roles` | 10 | `DEFER` | `ESTABLISHED` | Role-name mapping requires current authorization decisions. |
| `saved_reports` | 10 | `PRESERVE_UNINTERPRETED` | `PROBABLE` | Report definitions/configuration for later parity analysis. |
| `surcharge_penalty_config` | 1 | `DEFER` | `AMBIGUOUS` | Historical configuration/pricing evidence; never current policy. |
| `unitsOfMeasurement` | 4,381 | `MAP_AS_HISTORICAL_EVIDENCE` | `PROBABLE` | Application/business variable facts; retain 10 broken Application edges. |
| `users` | 28 | `PRESERVE_UNINTERPRETED` | `ESTABLISHED` | Historical actor directory only; no User/account/role creation. |

Embedded concepts are additionally dispositioned: application LOB items, fee exclusions, variable mappings, fee overrides, schedule fee lines, payment/receipt claims, business documents, report configurations, dynamic billing fields, and media relationships are `MAP_AS_HISTORICAL_EVIDENCE` or `PRESERVE_UNINTERPRETED` under the rules above. No embedded item disappears with its parent row.

### 6.1 Canonical domain matrix

The table ledger above and this domain matrix jointly form the canonical Source -> BPLS matrix.

| Source entity/concept | Source identity and relationships | Observed shape | Candidate BPLS target | Historical/operational | Coverage and confidence | Loss/open question |
| --- | --- | --- | --- | --- | --- | --- |
| Owner | table PK; parent of businesses; repeated on Applications/permits | person/group; structured name, contacts, TIN, address, deletion/blacklist | `BusinessOwner` plus `LegacyIdMapping`, or dedicated historical projection if operational exclusion cannot be enforced | Historical registry only | 3,194/3,194 source-key candidates; `PROBABLE` | No automatic legal-identity merge; 98 missing addresses, one missing location triplet, 66 have no business. |
| Business | table PK; exact owner; parent of Applications | registry, address, dates, classifications, documents | `Business` plus provenance/guard | Historical registry only | 3,212/3,212; `PROBABLE` | PSGC and duplicate identity unresolved; 156 have no Application. |
| Application | table PK; exact business/owner; parent of schedules/clearances | number, type, literal status, dates, LOB array, fees | `PermitApplication` status `historical_evidence` plus immutable application bundle | Historical only | 3,137/3,137; `ESTABLISHED` | No current number/actor/lifecycle authority; missing/contradictory dates retained. |
| Application LOB item | parent Application + array index | category label, type, capital/gross, exclusions, variables | historical declaration/classification item; nullable future `LineOfBusiness` reconciliation | Historical municipal classification unless applicant provenance is separately established | 4,236/4,236 preserved; BPLS identity `AMBIGUOUS` | Five missing labels; no assumption that it was applicant-declared. |
| Location | table PK/hierarchy and literal address | 13 provinces, 38 cities, 44 barangays | accepted PSGC crosswalk plus literal snapshot | Historical descriptive | 24/44 barangay name proposals `PROBABLE`; 20 unresolved | No source code accepted; no fuzzy automatic mapping. |
| Schedule | table PK; parent Application; parent of payments | section, due date, fee array, paid/total/late charges, status | historical financial bundle section | Historical finance only | 7,648/7,648 dispositioned; 69 orphan edges; `ESTABLISHED` as source fact | Not an operational schedule; 24 totals are not cent-exact. |
| Schedule fee | schedule + array index | 24,402 lines, 13 labels, 3 categories | historical fee-line snapshot | Historical finance only | 24,402/24,402; label identity `AMBIGUOUS` | Five labels uniquely match `fees`; eight match multiple fee rows. All 13 match one `fee_names` vocabulary row, which still does not establish FeeRule identity. |
| Payment/OR claim | table PK; Application and schedule refs | amount, method, status, transaction/receipt/reference, actor/time | historical payment attempt and receipt claim | Historical finance only | 5,874/5,874; 56 schedule and 3 Application orphan edges; `ESTABLISHED` as literal event | Duplicate OR claims, attempts, payer/account/series limitations; no operational Collection/Receipt. |
| Clearance | table PK; Application/type refs | type-label snapshot, completion and actor/times | historical clearance claim | Historical only | 14,615/14,615; `PROBABLE` | 110 broken type refs; no current sufficiency. |
| Permit | table PK; optional Application; business/owner refs | number, dates, literal status | historical `BusinessPermitData` adapter backed by immutable permit claim | Historical only | 2,766/2,766; `AMBIGUOUS` authority | No original printable permit artifact relationship; 15 no Application and 10 broken registry edges. |
| Business document | business + document index + storage ID | one DTI label/relationship | later reconciled private `application_documents` copy | Historical evidence only | 1/1 typed business document preserved; Application scope `AMBIGUOUS` | No lodging-manifest claim; SEC/BIR absent. |
| Other media | manifest SourceIdentity | layouts, branding, report exports, 19 unassociated bytes | separate private managed copies only if later authorized | Evidence only | 35/35 accounted; `ESTABLISHED` bytes, mixed association confidence | Never attach unresolved bytes or conflate permit records with permit files. |
| Billing group | table PK and exact child edges | dynamic fields, charge catalog, records/lines, layouts | separate immutable billing/report bundle | Historical reporting/treasury evidence | All 71,036 rows across seven tables dispositioned; `PROBABLE` | No source edge to permit finance; keep independent. |
| Actor/security/access | source user IDs and audit references | users, roles, permissions, sessions/tokens, activity | historical actor label/hash and source snapshot | Non-authenticated evidence | All counted; user directory `ESTABLISHED`, actor equivalence `AMBIGUOUS` | No current User, permissions, signatures, or accounts. |
| Pricing configuration | source PKs/joins and independent pricing fingerprints | fee types/ranges/formulas, divisions/groups/overrides | mapping proposals/audit evidence | Candidate knowledge, never active policy | Five evidence records; all datasets identified | Effective periods/account codes/current equivalence not established. |
| Reports | report PK/export storage relationship | saved config dimensions/metrics/filters/date ranges, exports, layouts | later report-parity fixtures/read models | Historical/report evidence | 10 saved reports, 11 exports, layouts accounted | Exact UI/query semantics and authority remain later capture work. |

### 6.2 Literal status disposition

| Source location | Vocabulary and frequency | Gate 5 representation | Confidence |
| --- | --- | --- | --- |
| Applications | `Released` 2,907; `Assessment` 199; `Pending Payment` 28; `Draft` 3 | Literal historical status; target Application always `historical_evidence` | `ESTABLISHED` |
| Schedules | `paid` 5,741; `pending` 1,904; `partial` 3 | Historical schedule status only | `ESTABLISHED` |
| Payments | `completed` 5,744; `failed` 129; `pending` 1 | Historical attempt/event status only | `ESTABLISHED` |
| Permits | `Active` 2,763; `Expired` 3 | Historical permit claim status only | `ESTABLISHED` as literal, `AMBIGUOUS` as legal validity |
| Billing records | `completed` 24,947; `cancelled` 362; `pending` 63 | Separate billing-history status | `PROBABLE` |
| Users | `Active` 25; `Inactive` 3 | Historical directory status; no account provisioning | `ESTABLISHED` |
| Documents | one `pending` DTI relationship | Historical document workflow label, not sufficiency | `ESTABLISHED` as literal, `AMBIGUOUS` as documentary meaning |
| Deletion/blacklist flags | 47 Applications, 11 owners, 4 businesses, 22 permits, plus billing/config rows | Preserve as independent historical flags; never delete/activate target implicitly | `ESTABLISHED` |

Unknown future values follow the raw-status fallback and stop profile compatibility review; they are never coerced into a current enum.

### 6.3 Application-document matrix

| Source type/class | Count | Associated entity | Candidate document type | Media | Disposition/confidence |
| --- | ---: | --- | --- | --- | --- |
| `DTI Certificate` | 1 | Business | DTI registration evidence after exact Application-scope decision | Bytes verified | Reconcile later; `PROBABLE` |
| SEC | 0 | None observed | None | Missing | Preserve absence; `ESTABLISHED` |
| BIR-like/dedicated BIR | 0 established | None observed | None | Not established | Preserve absence/unknown; `UNKNOWN` |
| Other arbitrary business labels | 0 in this corpus | Business if later observed | Historical unclassified evidence | None observed | Literal fallback; `UNKNOWN` |
| Report CSV exports | 3 MIME findings within 11 report-export relationships | Report export, never Application | Report artifact | Bytes/checksums valid; detected `text/plain` | Preserve declared/detected MIME and finding; not corruption; `ESTABLISHED` |
| Permit-layout/platform media | 4 typed relationships | Configuration/layout | Generated/configuration media, not applicant document | Bytes verified | Separate later managed copy; `ESTABLISHED` |
| Unassociated storage objects | 19 | None | None | Bytes retained | `PRESERVE_UNINTERPRETED`; association `UNKNOWN` |

A later Spatie copy must carry custom properties equivalent to `source_system`, `corpus_id`, `source_identity_sha256`, `source_object_identity_sha256`, `source_checksum_sha256`, literal source filename, declared/detected MIME, historical flag, literal/accepted document type, association state/confidence, reconciliation decision, and import checksum. No historical lodging manifest is created unless the source proves the exact document set at submission time.

### 6.4 Pricing vocabulary crosswalk posture

The 24,402 schedule fee lines use 13 distinct literal labels across `Regulatory Fee` (10,441 uses / six labels), `Other Charges` (7,053 / eight labels), and `Tax` (6,908 / one label); a label can appear in more than one category. All 13 labels match one `fee_names` vocabulary record, but only five labels match one `fees` record while eight match multiple `fees` records. Consequently label equality establishes vocabulary spelling, not historical fee-rule identity.

Gate 5 must generate one private candidate record per `(source fee identity or schedule-item identity, candidate target)` using the candidate contract below. Each records the hashed legacy identifier/label, observed usage count, recovered-pricing candidate, BPLS Fee candidate, office/account/effective-period evidence, confidence, discrepancies, and disposition. Office, account code, and effective period are `UNKNOWN` unless independently present. The five pricing records are `MAPPING_AID` and `HISTORICAL_REFERENCE`; none is `CANDIDATE_CURRENT_POLICY` without later fiscal authority.

## 7. Mapping Candidate Contract

Every candidate record contains:

```text
candidate_id, corpus_id, corpus_fingerprint, mapping_profile,
source_identity_sha256, source_dataset, source_item_key,
source_payload_sha256, source_label_sha256 (when sensitive),
proposed_target_type, proposed_target_key, disposition, confidence,
basis[], contradictions[], required_decisions[], authority_reference,
projection_sha256, created_at
```

Candidate states are `PROPOSED`, `ACCEPTED`, `REJECTED`, `QUARANTINED`, or `SUPERSEDED`. Only `ACCEPTED` with decision authority may satisfy a reference/identity gate. The committed specification may contain safe labels and aggregate counts, but candidate artifacts containing taxpayer identifiers remain private.

## 8. Dependency Graph and Seed Phases

```text
verify exact corpus
  -> register source/import batch/profile
  -> inventory + disposition every row/item
  -> reference proposals (no acceptance)
  -> historical owner projection
  -> historical business projection
  -> historical application projection
  -> declarations / measurements / clearances / permit claims
  -> immutable permit-financial and billing-group bundles
  -> media reconciliation proposals only
  -> reporting/search projections
  -> ipil:audit dry parity
```

Phase barriers:

1. **Verification:** exact ID/fingerprint, all 53 table checksums, media/pricing/provenance contracts pass.
2. **Inventory:** every row and embedded item has a disposition; ignored secret/session data is counted but not staged as BPLS payload.
3. **Registry:** zero broken owner/business/application core edges; collision signals cannot merge targets.
4. **Historical Application:** all targets are non-operational and lack fabricated actors/numbers.
5. **Evidence:** all child rows either link by source identity or enter an explicit orphan/unresolved cohort.
6. **Finance:** arbitrary-precision parsing, raw lexemes, independent sums, and exception counts balance; no operational finance writes.
7. **Media:** no copy in Gate 5 unless separately authorized; all 35 objects and 16 relationships remain accounted for.
8. **Audit:** second identical dry run produces the same plan/profile fingerprint and zero extra targets.

| Phase | Inputs | Target/projection | Expected volume | Prerequisite and acceptance | Failure behavior |
| --- | --- | --- | ---: | --- | --- |
| A — bind/inventory | corpus manifests, 53 tables, media, pricing | `LegacySource`, import batch, disposition plan | 324,833 rows plus embedded items | exact verifier/fingerprint/profile | Abort before writes on any drift; secrets counted but not payload-staged. |
| B — reference proposals | location, LOB, fee, office, access catalogs | versioned mapping proposals | 44 barangays, 562 LOB labels, 13 transactional fee labels plus catalogs | no automatic acceptance | Ambiguous/unknown stays unresolved and does not block descriptive history. |
| C — owners | `business_owners` | historical owner projection/mapping | 3,194 | source identity unique; no merge | Quarantine only malformed required identity; never create User. |
| D — businesses | `businesses` | historical business projection/mapping | 3,212 | exact owner mapping | Broken descriptive location uses literal fallback; broken owner would block business attachment. |
| E — Applications | `business_permit_applications` | non-operational Historical Application + bundle | 3,137 | exact business/owner and agreement | Broken/mismatched financial children do not block Application identity; core edge conflict does. |
| F — declarations/classifications | embedded LOB, UOM, asset/permit categories | immutable declaration/measurement evidence | 4,236 LOB + 4,388 measurement rows | parent identity or orphan cohort | Preserve raw values; do not substitute zero or accepted LOB. |
| G — permit finance | schedules, schedule fees, payments, overrides | historical financial bundle V2 | 7,648 schedules, 24,402 fee lines, 5,874 events | exact decimal and deterministic edge | Orphan/ambiguous attribution remains separate unresolved finance evidence. |
| H — billing/report finance | seven billing-group tables | separate historical billing bundles | 71,032 table rows | exact native billing edges | Never infer permit/Application link. |
| I — clearances/permits | clearances, permits | historical claims/read projections | 14,615 + 2,766 | exact source parent or orphan cohort | Preserve broken claims; no operational completion/issuance. |
| J — media reconciliation | media manifest + business/report/config refs | reconciliation proposals only | 35 objects, 16 typed refs | checksum and deterministic scope | No Spatie copy in initial Gate 5; 19 remain unresolved. |
| K — search/report projection | historical bundles + saved report shapes | private indexes/read adapters | corpus-derived | all counts and provenance stable | No UI/report authority; fail on operational leakage. |
| L — audit/replay | complete plan/execution ledger | parity result | all classes | second-run identity and zero-duplication | Any unexplained divergence blocks execution review. |

## 9. Dry Run, Batch, Rollback, and Engine Strategy

`ipil:seed` should default to plan/dry-run and require explicit local-only confirmation for any later execution. Planning must be streaming and bounded: suggested batches are 1,000 ordinary rows, 250 large/nested historical bundles, and 25 media reconciliations. Batch size affects performance only, never identities or output hashes.

Use database transactions per phase chunk plus an immutable execution ledger. Rollback is dependency-reverse and may remove only targets created by that execution whose target hashes and downstream dependency counts are unchanged. Reused targets and evidence from earlier executions are never deleted. A failed chunk leaves the phase incomplete and resumable from verified checkpoints.

SQLite remains suitable for synthetic fixtures and small disposable rehearsals. PostgreSQL is the recommended full-corpus local parity target because JSON, indexing, transaction and concurrent-read behavior are material at this scale; this is a rehearsal recommendation, not a production-engine decision. If Laravel Cloud's accepted production engine differs, repeat the full rehearsal on that exact engine before cutover. Required indexes include source tuple uniqueness, `(batch,dataset,line)`, disposition/status, target type/id, application/business/owner source mappings, exception code/status, and searchable historical application year/status/type/business fields. PII search columns remain private and access-controlled; logs and observatory outputs use hashes and aggregates.

For the initially disposable local parity database, `migrate:fresh -> bpls:install -> ipil:seed plan/execute` is the preferred coarse rollback. Per-execution rollback is still required for bounded rehearsals on a shared local database and must follow the unchanged-target rules above. Neither posture touches the immutable corpus.

## 10. Audit and Readiness Thresholds

The future `ipil:audit` must prove at least:

| Domain | Gate 5 planning threshold | Later execution/audit threshold |
| --- | --- | --- |
| Corpus | Exact fingerprint and verifier PASS | Same; no corpus write |
| Row dispositions | 324,833/324,833 classified | 100%; zero unexplained |
| Core registry edges | 3,212/3,212 owner edges and 3,137/3,137 Application edges accounted | Zero unexplained breakage |
| Owner/business identity | 100% source-key candidates; zero automatic merges | Every reuse/create decision deterministic |
| PSGC | Every row proposed or unresolved | Only accepted crosswalks populate PSGC codes |
| LOB | 4,236/4,236 items dispositioned | No line silently dropped; target IDs only after accepted reconciliation |
| Finance | Every persisted decimal/lexeme and child edge preserved | Source totals reproduced exactly by evidence class; cent-exact subset reconciles to integer centavos |
| Payments | 5,874/5,874 events accounted | PHP 93,295,317.20 completed-event/schedule-paid anchor preserved; failures/pending remain distinct |
| Receipts | Every claim accounted, duplicates explicit | No uniqueness collision converted into issued OR |
| Permits/clearances | 2,766 permits and 14,615 clearances dispositioned | Unlinked/broken claims remain visible; no operational authority |
| Media | 35 objects, 16 typed relationships, 19 unresolved, 24 findings | 100% acquired-byte checksum parity; zero guessed associations |
| Pricing | Five records and independent fingerprints match | Audit only; zero activation/recalculation |
| Reports/search | Required source fields and parity anchors identified | Historical projections return complete, stable counts without operational contamination |

Any checksum drift, unknown table, unclassified row/item, duplicate SourceIdentity, target collision, arithmetic loss, fabricated value, operational leakage, public/Cloud write, or current-price invocation is an immediate stop.

## 11. Reporting, Search, and Parity Anchors

Historical Business Detail must be able to resolve owner, business, literal address/location, registration, Applications, declarations, documents, clearances, permit claims, financial bundles, and provenance without enabling actions. Historical Executable Application must present recognizable source history while disabling Nelson actions.

Indexes and projections must support private search/filter by application year/type/literal status, business/owner, barangay, LOB label, payment status/method/date, permit claim/status, and exception/disposition. Search results must distinguish source assertions from current operational records.

Report parity anchors include the source saved-report/export family plus abstract, paid/unpaid masterlists, collectibles, business tax by major, top tax due, taxpayer account card, CMCI LDCS, PLDS, BSP, ANNEX-C DNFBP, and billing-group abstract. Gate 5 only prepares source fields and deterministic fixtures. Report output, UI capture, and parity disposition remain later gates.

| Report/metric family | Source facts | Proposed BPLS facts | Readiness / gap |
| --- | --- | --- | --- |
| Owner/business masterlists | owners, businesses, location/category refs, deletion flags | historical registry projection | Structurally ready; PSGC/category targets may be unresolved. |
| Application paid/unpaid/collectibles | Applications, schedules, payments, literal statuses/dates | historical application + financial bundle | Ready as source-defined evidence; do not reuse current balance semantics. |
| Business tax by major/top tax due | schedule fee labels/categories, groups/divisions/majors | historical fee lines + classification proposals | Amounts ready; classification/FeeRule identity partial. |
| Taxpayer account card | owner/business/Application/schedule/payment/OR claims | linked historical timeline | Core edges ready; orphan finance and duplicate OR claims remain explicit. |
| Abstract/payment summaries | schedules, payment events, methods/dates, billing-group records | two independently labeled historical financial sources | Ready if sources are not merged. |
| CMCI LDCS/PLDS/BSP/ANNEX-C | saved report dimensions/metrics/filters plus registry/Application facts | later dedicated read adapters | Source shapes preserved; exact formulas/authority require report capture. |
| Billing-group abstract | billing group definitions/fields/fees/records/lines | historical billing bundle | Native relationships complete; amount discrepancies remain visible. |
| Export parity | saved reports, report exports, layouts, media objects | report fixture metadata and later generated artifact comparison | 10 definitions/11 exports available; UI/output semantics deferred. |

High-confidence later audit anchors are: 3,194 owners; 3,212 businesses; 3,137 Applications; type counts 2,621/461/55; status counts 2,907/199/28/3; 3,128 Applications submitted in 2026 and nine in 2025; 7,648 schedules with 5,741/1,904/3 statuses; 5,874 payment events with 5,744/129/1 statuses; PHP 93,295,317.20 schedule-paid/completed-event equality; 2,766 permit claims (2,745 dated 2026, 12 in 2024, six in 2025, three in 2023); 14,615 clearance claims; 4,236 LOB items; 25,372 billing records; and the exact media/pricing anchors above. Each anchor is scoped to this corpus and literal source logic.

### 11.1 Acceptance thresholds and fallbacks

- `ESTABLISHED` structural identity may auto-plan, but only into the historical/non-operational target allowed by this profile.
- `PROBABLE` descriptive mappings may populate literal historical fields and proposal records; they may not populate authority-bearing canonical IDs without acceptance.
- `AMBIGUOUS` and `UNKNOWN` descriptive values use raw/literal evidence with a null canonical target and do not block the parent record.
- Financial parentage, owner/business/Application core identity, currency, exact amount representation, media Application scope, and current authority are strict. Ambiguity produces an unattached historical evidence record or blocks that child association; it never guesses.
- Unknown LOB/status/category/address/document labels remain literal. Unmapped fee lines remain labeled historical lines. Orphan schedules/payments/permits/measurements remain orphan evidence. Unassociated media remains corpus-only evidence.

Currency is absent on individual financial rows but `platform_settings.currencyFormat` is `PHP`, the source identifies the Municipality of Ipil, and every reviewed municipal/reporting context uses Philippine peso. The profile may label source financial evidence `PHP` with `PROBABLE` confidence and this explicit basis. If any row or later corpus supplies a conflicting currency, automatic financial association stops.

Historical source fields remain inspectable through bounded immutable bundles and SourceIdentity-linked projections, not unbounded model metadata. High-value normalized/indexed fields live in projections; the exact raw row remains in the private corpus/legacy record with its checksum. Corrections are additive annotations or superseding mapping decisions; rescued history is never edited.

### 11.2 Historical `ApplicationData` projection

The existing `ApplicationData` read boundary should gain a historical adapter rather than a parallel executable lifecycle. It may populate owner/business display, literal addresses, source Application identity/type/year/status, historical municipal classification labels, exact financial bundle, payment attempts/OR claims, permit claims, and deterministically reconciled applicant documents. It must mark `historical_record=true`, `operational=false`, `source_evidence_incomplete` when applicable, and provide no next task, Post-it, lifecycle command, current balance, current Price result, or issuance action.

Modern-only facts remain unavailable unless separately evidenced: applicant-vs-municipal LOB provenance, BPLO/Treasury routing decisions, lodging manifest, modern declarations/undertaking, current assessment composition, consolidated collection grouping, multiple current OR groups, post-payment certifications, municipal approval authority, and signatures. No `SignatureEvidence` is created from an actor name or printable layout. A historical permit database claim may display without a permit artifact; a rescued file is not a permit record unless a source relationship establishes that fact.

The safe representative projection patterns identified for Gate 5 fixtures are: one-Application business; multiple-Application business; paid Application with permit claim; schedule with completed plus failed attempt; business with DTI relationship; unresolved LOB/location; and orphan financial/permit child. Fixtures must be synthetic equivalents; private taxpayer examples remain outside Git.

## 12. Gate 5 Review Inputs and Stop Conditions

Gate 5 may review an implementation plan for a fail-closed, offline, synthetic-first `ipil:seed`. It must not start until this specification and its decision register are accepted. It must stop if historical/operational isolation needs an unapproved domain redesign; if PSGC/LOB/fee/identity ambiguity is hidden; if current finance would be written; if source secrets would be staged; or if the exact corpus/profile cannot be reproduced.

This specification deliberately permits unresolved evidence. It does not permit unexplained loss.
