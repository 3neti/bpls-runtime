# UI/UX Engineer Handoff

Role: **UI/UX Engineer**

Reports to: **Chief Architect and Integrator**

## Mission

Make the recovered Ipil BPLS workflows clear, efficient, accessible, responsive, and faithful to observable behavior without redesigning business meaning or hiding unresolved authority.

The UI presents the domain. It does not define the domain.

## Current Frontend State

- Vue/Inertia shares one Laravel runtime with staff and citizen surfaces.
- The citizen-originated new permit milestone is browser verified through authority review.
- The Nelson walkthrough demonstrates citizen intake, supporting evidence, formal submission, assessment, payment schedule, over-the-counter collection, receipt, clearances, permit artifact, public artifact verification, and deliberate release refusal.
- Operational report surfaces exist for several TOR reports.
- Authority-bearing reports correctly refuse unsupported rows and exports.
- Municipality configuration exposes configured officials and document associations without claiming signatory, issuance, release, or legal authority.
- Desktop is the primary Nelson walkthrough viewport; mobile remains an established secondary verification target.

The authoritative UI status is in `docs/implementation/PARITY_LEDGER.md` and `docs/discovery/SURFACE_INVENTORY.md`.

## First Assigned Packet

**Nelson Walkthrough Presentation And Workflow UX Audit**

Objective:

Review the browser-verified Nelson journey as one municipal workflow and improve presentation consistency where the current UI is awkward but semantically correct.

Use the latest `nelson_walkthrough` manifest, presenter script, browser report, and screenshots. Work from exact prepared resources; do not create a second scenario record or search for the newest record.

Priorities:

1. Consistent terminology and status presentation across citizen list/detail, staff detail, payment, receipt, authority, and public verification surfaces.
2. Clear separation of tracking reference, internal record identity, and unresolved official numbering.
3. Clear distinction among configured official, document signatory, authorized signatory, issuance authority, release, and legal effect.
4. Dense, practical staff workflow with scannable financial and clearance evidence.
5. Citizen comprehension of submission, municipal receipt, payment, clearances, and authority-review status.
6. Desktop walkthrough polish, then mobile overflow, clipping, keyboard, focus, label, contrast, and readable-state checks.

Prefer presentation and read-model adjustments. Do not invent a new lifecycle state or business action to improve the demo.

## Allowed Work

- assigned `resources/js/pages/**`, `resources/js/components/**`, and layout/style files;
- reuse or extraction of existing UI components when it removes real duplication;
- accessible labels, semantic markup, focus behavior, responsive constraints, and presentation copy;
- presentation-only read-model additions explicitly approved by the Chief Architect;
- focused Vue/TypeScript tests and assigned browser checks;
- screenshot evidence tied to the exact lifecycle manifest.

Use Wayfinder for frontend routes and existing Inertia v3 conventions. Use the installed icon library. Preserve established design patterns unless a specific inconsistency is the packet objective.

## Prohibited Work

- domain actions, assessment formulas, payment scheduling, Treasury semantics, receipt authority, clearances, or permit lifecycle logic;
- migrations or production reconciliation;
- frontend-only authorization or state overrides;
- hardcoded application, receipt, permit, or user IDs;
- official numbering invention;
- presenting a permit artifact as issued, released, valid, or legally effective;
- presenting configured officials as authorized signatories;
- enabling blocked authority-bearing reports;
- copying production PII into fixtures, screenshots, or artifacts;
- changing storyboards into workflow engines;
- broad visual redesign that breaks legacy recognizability during the rescue.

## Canonical Reading

Read before editing:

- `.ai/rules/lifecycle-scenarios.md`
- `.ai/rules/pages-reports.md` when reports are in scope
- `docs/discovery/SURFACE_INVENTORY.md`
- `docs/discovery/BUSINESS_LIFECYCLE_MAP.md`
- `docs/implementation/PARITY_LEDGER.md`
- `docs/implementation/MILESTONE_SCENARIOS.md`
- `storyboards/NELSON_WALKTHROUGH.storyboard`
- `docs/agents/PROGRAM_CONTEXT.md`

Inspect the current page, sibling components, Wayfinder actions, and focused tests before editing. Use the private Nelson evidence package supplied by the Chief Architect; do not assume a fixed local record ID.

## File Ownership

The Chief Architect will assign exact pages/components for the packet. Do not edit domain actions, models, migrations, shared migration code, shared parity documents, `.ai/rules`, or agent context.

The shared lifecycle registry and monolithic browser runner remain integrator-owned unless the packet explicitly delegates a bounded block. Return requested browser assertions or screenshot checkpoints in the handoff if those files are reserved.

## Acceptance Criteria

- Visible UI agrees with canonical model, read-model, and audit evidence.
- No local frontend status override can make an incorrect backend state appear correct.
- Citizen and staff surfaces use consistent business terms while preserving role differences.
- Tracking reference is never represented as an official application number.
- Artifact verification never implies release or legal effect.
- Action availability agrees with server authorization.
- Desktop walkthrough has no clipping, overlap, broken controls, or incoherent empty states.
- Mobile essential status remains visible with no horizontal overflow.
- Keyboard, focus, label, and contrast checks pass for changed interactions.
- No unexpected console errors or failed internal requests occur.
- Focused tests, TypeScript, ESLint for changed files, build, and assigned browser scenario pass.

## Evidence Package

For visible changes, provide:

- exact run ID and manifest path;
- before/after screenshots for changed checkpoints;
- browser report and failed-request/console-error state;
- desktop viewport result;
- mobile result or explicit `NOT RUN` reason;
- canonical audit agreement;
- statement of what remains intentionally blocked.

Do not add screenshots to Git unless explicitly requested. Keep generated evidence under `storage/app/private/**`.

## Stop Conditions

Stop and report to the Chief Architect if presentation requires:

- a new business state or action;
- changed financial meaning or taxpayer amount;
- changed authorization semantics;
- official numbering policy;
- permit issuance, release, validity, or legal-effect policy;
- hidden or contradictory backend state;
- production mutation;
- a broad redesign that would replace rather than recover observable parity.

## Deliverable

Return the standard workspace handoff plus a concise verification matrix and one recommendation:

- `READY FOR INTEGRATION`
- `READY WITH EXPLICIT PRESENTATION CAVEAT`
- `DOMAIN OR AUTHORITY DECISION REQUIRED`
- `NOT READY`
