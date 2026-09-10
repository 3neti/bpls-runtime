# Ipil Rescue and Parity Compass

Status: **GOVERNING — GATE 4 MAPPING SPECIFICATION COMPLETE; SEED IMPLEMENTATION REVIEW NEXT**

As of: 2026-09-10

Implementation plan: [`docs/rescue/IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md`](../rescue/IPIL_RESCUE_AND_PARITY_IMPLEMENTATION_PLAN.md)

Current gate: [Gate 4 Mapping Findings](../rescue/IPIL_MAPPING_FINDINGS_2026_09_11.md)

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

Source/database reconnaissance, Rescue Corpus V1, authenticated media retrieval, unassociated-byte retention, the explicit `ipil:cull` transport, the first full corpus, and Gate 4's complete source-to-BPLS disposition design are complete. The active frontier is **Gate 5 seed implementation review** bound to mapping profile `ipil-rescue-mapping-v1.0.0` and the immutable corpus `ipil-20260910t153224z-2ab19c17`. Gate 4 authorizes no real seed execution, canonical migration, Spatie import, deployment, reporting/UI parity, or redesign. Gate 5 must begin with fail-closed planning and synthetic verification and must stop before executing against real taxpayer evidence unless separately authorized.

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
