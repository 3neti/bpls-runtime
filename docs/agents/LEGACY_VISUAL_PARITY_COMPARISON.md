# Legacy Visual-Parity Comparison Framework

Status: **Framework prepared; conclusions intentionally unpopulated**

Baseline: **Nelson Visual Walkthrough / UI/UX Cycle 1 frozen**

As of: 2026-08-18

## Purpose

This framework compares observable Legacy Ipil BPLS screens with the implemented Laravel application after the Municipality supplies screenshot evidence. It preserves recognizability where that helps staff, improves observable defects where evidence supports doing so, and keeps unresolved authority visible.

Do not infer a conclusion from memory, source code alone, a text description, or an unmatched screen. Legacy and Laravel evidence remain separately identified and independently traceable.

## Comparison Contract

Each populated comparison must preserve this chain:

```text
Legacy Ipil BPLS
    -> observable operational intent
    -> Laravel implementation
    -> retain familiarity / improve defect / preserve authority boundary
```

For every comparison record:

- identify the legacy screenshot, capture context, user role, observable state, and evidence provenance;
- identify the exact Laravel Cycle 1 screenshot, scenario run, route purpose, user role, and authoritative state;
- describe observable operational intent without treating accidental layout or legacy defects as municipal procedure;
- choose a disposition only after evidence review;
- never use visual similarity to authorize workflow, liability, numbering, signatory, issuance, release, legal effect, or identity mapping.

Allowed final dispositions are `retain familiarity`, `improve defect`, and `preserve authority boundary`. A conclusion may remain `unresolved pending evidence` during review.

## Comparison Record Template

```text
Comparison ID: LVP-YYYY-NNN
Dimension:
Municipal task or question:

Legacy visual-reference evidence:
- Screenshot or artifact:
- Capture context / role / state:
- Provenance and date:
- Observable facts:

Observable operational intent:

Laravel visual-reference evidence:
- Frozen Cycle 1 screenshot or artifact:
- Scenario / role / authoritative state:
- Provenance and date:
- Observable facts:

Comparison:

Proposed disposition: retain familiarity / improve defect / preserve authority boundary / unresolved pending evidence
Evidence still required:
Related Nelson feedback:
Reviewer and decision reference:
```

## Unpopulated Comparison Matrix

| Dimension | Legacy Ipil BPLS evidence | Observable operational intent | Laravel implementation evidence | Disposition | Evidence still required |
| --- | --- | --- | --- | --- | --- |
| Workflow | Pending screenshots | — | Pending matched Cycle 1 scene | Unresolved pending evidence | Legacy role, state, sequence, and screen context |
| Terminology | Pending screenshots | — | Pending matched Cycle 1 scene | Unresolved pending evidence | Exact visible labels and municipal meaning |
| Information hierarchy | Pending screenshots | — | Pending matched Cycle 1 scene | Unresolved pending evidence | Staff task, priority facts, and viewing context |
| Queues | Pending screenshots | — | Pending matched Cycle 1 scene | Unresolved pending evidence | Queue membership, ordering, filters, role, and status meaning |
| Affordances | Pending screenshots | — | Pending matched Cycle 1 scene | Unresolved pending evidence | Intended action, authorization, enabled/disabled state, and result |
| Status visibility | Pending screenshots | — | Pending matched Cycle 1 scene | Unresolved pending evidence | Status source, transition authority, role, and operational meaning |
| Record organization | Pending screenshots | — | Pending matched Cycle 1 scene | Unresolved pending evidence | Record boundaries, identifiers, grouping, and retrieval practice |
| Visual-reference evidence | Pending screenshots | — | Pending matched Cycle 1 scene | Unresolved pending evidence | Original files, provenance, capture date, viewport, and redaction record |

## Evidence Handling

Legacy screenshots and Laravel screenshots are different evidence classes. Store original visual evidence privately when it contains personal, financial, credential, storage, or production information. Git may retain payload-safe references, hashes, redacted conclusions, and disposition records; it must not receive sensitive screenshots or raw production facts.

Do not replace the frozen Cycle 1 screenshot merely to make a comparison easier. A later UI cycle must have its own evidence run and must preserve the Cycle 1 baseline for before/after review.
