# Ipil Rescue and Parity Compass

Status: **GOVERNING — GATE 5 OFFLINE SEED PLAN COMPLETE; REAL LOCAL MATERIALIZATION REQUIRES GATE 6**

As of: 2026-09-10

Implementation plan: [`docs/rescue/IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md`](../rescue/IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md)

Current gate: [Gate 5 Offline Seed Planner Report](../rescue/IPIL_RESCUE_GATE_5_SEED_PLANNER_2026_09_11.md)

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

Source/database reconnaissance, Rescue Corpus V1, authenticated media retrieval, unassociated-byte retention, `ipil:cull`, the first full corpus, Gate 4 mapping, and Gate 5's complete deterministic offline plan are complete. The active frontier is **Gate 6 first local materialization review**, bound to corpus `ipil-20260910t153224z-2ab19c17`, profile `ipil-rescue-mapping-v1.0.0`, and the accepted Gate 5 Seed Execution Manifest. Gate 5 authorizes no real seed, canonical write, Spatie import, deployment, report/UI parity, or redesign. A real execution interface does not exist and must not be added or invoked without a separately commissioned Gate 6.

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
