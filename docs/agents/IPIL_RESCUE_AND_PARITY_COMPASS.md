# Ipil Rescue and Parity Compass

Status: **GOVERNING — GATE 7A REALITY INVENTORY COMPLETE; GATE 8 READ-ONLY PARITY NEXT**

As of: 2026-09-11

Implementation plan: [`docs/rescue/IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md`](../rescue/IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md)

Current gate: [Gate 7A Local Reality and Product/Report Parity Inventory](../rescue/IPIL_RESCUE_GATE_7A_REALITY_AND_PRODUCT_PARITY_INVENTORY_2026_09_11.md)

Next bounded backlog: [Gate 8 Prioritized Read-Only Historical Parity Backlog](../rescue/IPIL_RESCUE_GATE_8_PRIORITIZED_BACKLOG.md)

Seed implementation contract: [Ipil Seed Implementation Plan V1](../rescue/IPIL_SEED_IMPLEMENTATION_PLAN_V1.md)

Normative mapping authority: [Source-to-BPLS Mapping Specification V1](../rescue/IPIL_SOURCE_TO_BPLS_MAPPING_SPECIFICATION_V1.md) and [Mapping Decision Register](../rescue/IPIL_MAPPING_DECISION_REGISTER.md)

Gate 2 implementation: [`ipil:cull` Implementation](../rescue/IPIL_RESCUE_GATE_2_IPIL_CULL_IMPLEMENTATION.md)

Media evidence: [Ipil Media Cull Readiness Closure](../rescue/IPIL_MEDIA_CULL_READINESS_CLOSURE_2026_09_10.md)

## North Star

> **Rescue first. Reproduce second. Improve third.**

Recover a complete, verifiable local history of the current Ipil BPLS; reproduce its required data, documents, reports, and recognizable operating experience; improve it only after parity is proven.

The new Nelson Executable Application lifecycle remains the forward operational path. Rescued history supplies continuity; it does not become a second operational workflow.

## Non-Negotiable Rules

1. **Cull first. Interpret later.**
2. **The source snapshot is immutable evidence.** A correction creates a new corpus version.
3. **`ipil:cull` transports, `ipil:seed` interprets, `ipil:audit` proves.** `ipil:rescue:verify` proves corpus integrity between transport and interpretation.
4. **Live source access is explicit, read-only, and exceptional.** It is never part of an ordinary rebuild, seed, test, deploy, CI run, scheduler, or `bin/bpls-ipil-rescue-lab` execution.
5. **Historical truth is not recalculated with current policy.**
6. **Missing source evidence stays missing.** Unknown, missing, contradictory, and orphaned facts stay visible.
7. **Media bytes are rescued with checksums and provenance.** Spatie manages verified BPLS media copies, not source truth.
8. **A historical owner is not automatically a `User`.** Do not fabricate accounts or submission actors.
9. **A historical Application is not operational.** It cannot enter queues, incur current liability, issue a permit, or execute the Nelson lifecycle.
10. **Parity before simplification.**
11. **No unexplained divergence.** Every surface and behavior is `MATCH`, `ADAPT`, `IMPROVE`, or `DEFER` with evidence.
12. **Real taxpayer data never enters Git.** Dumps, media, PII manifests, private identifiers, and credentials remain private and outside public or Cloud storage.

## Active Frontier

Source/database reconnaissance, Rescue Corpus V1, `ipil:cull`, the first full corpus, Gate 4 mapping, Gate 5 deterministic planning, and Gate 6's first local PostgreSQL materialization are complete. Two imports and two audits proved exact count/financial parity, zero replay duplication, private associated-media checksums, and zero operational leakage for corpus `ipil-20260910t153224z-2ab19c17` under profile `ipil-rescue-mapping-v1.0.0`.

Gate 7A validated the exact Gate 6 PostgreSQL materialization through ordinary staff screens and found that the historical store is intact, fast enough for bounded projection, and still completely non-operational. It also proved that ordinary screens and operational reports do not yet project the rescued history. The active frontier is therefore **Gate 8 bounded read-only historical product/report parity**, following the prioritized backlog. Gate 7A does not authorize broad UI redesign, cleanup/deduplication, PSGC/LOB acceptance, historical correction, official-report meaning, Cloud/R2 movement, UAT/production deployment, cutover, or account claiming.

Advance only in this order:

```text
reconnaissance
    -> immutable rescue corpus
    -> offline integrity verification
    -> deterministic local interpretation
    -> quantitative data/media/financial audit
    -> read-only historical and report parity
    -> systematic current-Ipil UI/UX capture
    -> parity scaffolding and side-by-side walkthrough
    -> later product/UI improvement
```

Existing migration characterization and rehearsals remain evidence, not authority to skip these gates.

## Stop Conditions

Stop and obtain an explicit decision if:

- read-only authority, source scope, snapshot consistency, privacy controls, or provenance is uncertain;
- a checksum, row, relationship, financial total, or media count does not balance;
- a mapping would infer legal identity, PSGC identity, current policy, lifecycle authority, or missing evidence;
- history would need to become operational to fit BPLS;
- data or media could enter Git, a public disk, Cloud storage, CI artifacts, or an unapproved backup;
- UI or report work lacks a verified corpus, quantitative audit, source capture, or parity disposition;
- a proposed step would redesign, simplify, deploy, cut over, or write to the live source before the applicable gates.

Do not jump ahead into UI redesign because some records import successfully. Corpus and audit parity are the gate.
