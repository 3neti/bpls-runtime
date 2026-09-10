# Ipil Mapping Decision Register

Status: **NORMATIVE DECISIONS FOR GATE 5 REVIEW — NO SEED AUTHORITY**

Effective: 2026-09-11

Specification: [Ipil Source-to-BPLS Mapping Specification V1](IPIL_SOURCE_TO_BPLS_MAPPING_SPECIFICATION_V1.md)

Evidence: [Gate 4 Mapping Findings](IPIL_MAPPING_FINDINGS_2026_09_11.md)

Each row records a chosen disposition. The standing alternatives considered were: direct operational mapping, dedicated historical evidence, uninterpreted preservation, deferral, or evidence-based non-interpretation. The chosen option is the least semantically powerful representation that preserves the source fact. Unless a row states otherwise, source-fact confidence is `ESTABLISHED`; target semantic confidence is the `PROBABLE`/`AMBIGUOUS` boundary documented in the mapping specification. Information loss must be zero at the corpus/evidence layer; a null canonical target is an explicit unresolved result, not loss.

| ID | Decision | Basis | Consequence / reopen condition |
| --- | --- | --- | --- |
| IPIL-MAP-001 | Bind mapping profile `ipil-rescue-mapping-v1.0.0` only to corpus `ipil-20260910t153224z-2ab19c17` and fingerprint `d799a0c…1fa81`; profile identity is `edae710f…ab789c`. | Verified immutable Gate 3 evidence. | Any corpus/profile drift stops. A new corpus requires a new batch and reviewed profile compatibility. |
| IPIL-MAP-002 | Source-key identity, legal identity, semantic identity, and operational authority are separate decisions. | Duplicate/collision census and governing compass. | A deterministic source key permits evidence continuity, never automatic merge or present authority. |
| IPIL-MAP-003 | Project source owners and businesses one-to-one as historical continuity; merge none automatically. | Complete core edges; material name/contact/registration collisions. | Each source identity remains distinct. Reopen only with authoritative, reviewed identity evidence. |
| IPIL-MAP-004 | Create no `User` from a historical owner or source user. | Only one source user is linked to an owner; credentials/roles are source-system security state. | `submitted_by_id` and upload actors remain null unless an exact separately accepted actor mapping exists. |
| IPIL-MAP-005 | Every source Application is non-operational `historical_evidence`. | Historical continuity is required; lifecycle authority is unresolved. | No queueing, continuation, Price, collection, issuance, or Nelson workflow action. Reopen only with explicit municipal/cutover authority. |
| IPIL-MAP-006 | Preserve source Application number as evidence, not current official number. | Two duplicate normalized-number groups and no accepted numbering authority. | `application_number=null`; literal value remains private in provenance. |
| IPIL-MAP-007 | Preserve literal statuses/types/deletion/timestamps; do not normalize them into present authority. | Released rows commonly lack release/approval timestamps; one assessment precedes submission. | Source contradictions remain findings; no fabricated timestamps or lifecycle transitions. |
| IPIL-MAP-008 | Source business identity is the continuity key across Applications. | All Application business/owner paths agree; only 72 businesses have multiple Applications. | Never link by business name. A Renewal does not imply a missing predecessor must be invented. |
| IPIL-MAP-009 | Treat LOB label matching as proposals only. | 559 distinct labels uniquely match source groups, but fee/municipal identity is not established. | Preserve all 4,236 items; keep BPLS LOB target null until accepted reconciliation. |
| IPIL-MAP-010 | Never coerce non-numeric or non-cent declaration values to zero. | 3,695 capital and 558 gross source states are non-decimal; one gross is non-cent-exact. | Preserve raw value/lexeme and classification; current line projection waits or uses an evidence bundle. |
| IPIL-MAP-011 | PSGC population requires a versioned accepted crosswalk. | 24/44 exact-name candidates, 20 unmatched; 0/13 source codes are current PSGC codes. | Literal barangay remains; target PSGC code stays null. Fuzzy matching can propose but never accept. |
| IPIL-MAP-012 | Historical finance is immutable evidence, not operational finance. | Current model carries liability/collection authority; source contains partials, attempts, edits, overrides, broken edges. | No writes to Assessment, PaymentSchedule, TreasuryCollection, Receipt, or current balances. |
| IPIL-MAP-013 | Preserve arbitrary-precision decimal lexemes; derive centavos only when exact. | Hundreds of source values exceed two-decimal precision. | Never round. Gate 5 must add/extend historical evidence storage before complete import. |
| IPIL-MAP-014 | Payment attempts remain separate events and receipt numbers remain claims. | Paid schedules may also have failed/pending attempts; 196 duplicate receipt-number groups cover 744 events. | No attempt collapse and no issued-OR record from a claim. Transaction number may remain a source event key. |
| IPIL-MAP-015 | Persisted schedule components are historical truth; do not impose an undocumented Application-total equation. | Every schedule equals fees + surcharge + penalty, but 2,925 assessed Application totals differ from summed schedule totals. | Audit reports both layers and discrepancies without repair or recalculation. |
| IPIL-MAP-016 | Clearances and permits are historical claims only. | 110 broken clearance-type edges, 15 permits without Applications, broken permit registry edges, unresolved authority. | Preserve labels/status/numbers/times; create no current clearance completion or permit issuance. |
| IPIL-MAP-017 | Corpus media is source truth; Spatie is a later managed copy. | All bytes are checksum-bound; associations include unresolved and duplicate content. | No Gate 5 media import without separate authority and exact Application reconciliation. Missing SEC/BIR remains missing. |
| IPIL-MAP-018 | Retain all 19 unassociated bytes without guessing. | No deterministic relationship exists. | `PRESERVE_UNINTERPRETED`; only new authoritative evidence may change association state. |
| IPIL-MAP-019 | Keep pricing evidence independent and candidate-only. | Independent manifest/raw fingerprints and a five-row live-vs-pricing dataset delta. | No FeeRule activation or historical recalculation. Differences are audit findings. |
| IPIL-MAP-020 | Keep billing-group transactions separate from permit finance. | No deterministic source edge joins the two financial systems. | Preserve separate historical treasury/reporting bundles; no inferred taxpayer/application join. |
| IPIL-MAP-021 | Do not interpret authentication tokens, sessions, verification state, or rate limits. | Ephemeral security material has no BPLS historical business meaning and creates security risk. | Count as `IGNORE_WITH_EVIDENCE_BASED_REASON`; do not stage payloads or log values. |
| IPIL-MAP-022 | Preserve source roles, permissions, layouts, saved reports, exports, and settings only for later parity analysis. | They describe legacy behavior but cannot grant current authority or configuration. | No access grants, active templates, or platform setting writes during seed. |
| IPIL-MAP-023 | Require a dry-run-first, streaming, resumable, reversible execution ledger. | 324,833 rows and nested evidence exceed casual in-memory/local-transaction assumptions. | Same corpus/profile must produce the same plan hash; execution stops on drift or target mutation. |
| IPIL-MAP-024 | Use production-engine rehearsal for full-corpus performance; SQLite is synthetic/small-rehearsal only. | JSON/index/locking/transaction behavior and source scale are material. | Gate 5 must test representative production-engine batches before any real execution authorization. |
| IPIL-MAP-025 | A broken source edge creates an unresolved/orphan evidence record, never a guessed repair. | Known broken schedule, payment, clearance, permit, location, fee, and unit edges exist. | Row parity includes the exception cohort; target linkage waits for evidence. |
| IPIL-MAP-026 | Gate 4 passes because complete disposition does not require complete semantic certainty. | All rows/concepts have lossless fallbacks and stop rules. | Gate 5 may implement planning and synthetic verification only; execution remains a separate gate. |

## Deferred Authority Queue

The following decisions are deliberately not made here:

1. legal-person/business merge or split decisions;
2. acceptance of the 44-row source-to-PSGC crosswalk;
3. acceptance of legacy LOB/group to BPLS LOB mappings;
4. legacy fee/formula/range to current FeeRule identity or fiscal authority;
5. legacy clearance equivalence, completion authority, and permit validity;
6. official Application/permit/OR numbering authority;
7. assignment of the DTI document to an exact historical Application;
8. any association for the 19 unresolved objects;
9. source role/permission equivalence to current access policy;
10. report/UI parity dispositions and any later product improvement.

None blocks lossless historical-evidence planning. Each blocks the corresponding canonical or operational interpretation.
