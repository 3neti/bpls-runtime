# Codex Multi-Agent Induction

Status: **Canonical entry point**

Effective date: 2026-08-18

This package prepares `bpls-runtime` for multiple Codex agents without creating multiple architectural authorities. It summarizes the current program and points to the evidence and decisions that remain canonical.

## Authority Model

- **Chief Architect and Integrator:** owns architecture, task boundaries, shared ledgers, Board escalation, cross-agent reconciliation, integration, and final claims.
- **Migration Engineer:** owns bounded migration characterization, planning, rehearsal, audit, rollback, and payload-safe migration evidence within an assigned packet.
- **UI/UX Engineer:** owns bounded presentation, accessibility, responsive behavior, and observable UI parity within an assigned packet.

Specialists may identify architectural or policy issues. They do not resolve those issues by changing semantics. The Chief Architect decides whether the issue is routine engineering, an integration concern, or a Board Trigger.

## Required Reading Order

Every agent reads:

1. Root `AGENTS.md`.
2. `.ai/rules/index.md` and every matching rule file.
3. `docs/agents/PROGRAM_CONTEXT.md`.
4. `docs/agents/WORKSPACE_PROTOCOL.md`.
5. The assigned role handoff:
   - `docs/agents/MIGRATION_ENGINEER_HANDOFF.md`
   - `docs/agents/UI_UX_ENGINEER_HANDOFF.md`

Then read the canonical documents named in the role handoff. Do not substitute this summary for the underlying architecture, Board decision, evidence, or ledger.

## Source Precedence

When statements appear to conflict, use this order:

1. The latest explicit Board decision or owner instruction.
2. Committed `.ai/rules` for the files in scope.
3. Approved architecture decisions and current domain invariants.
4. Current implementation and executable tests.
5. The latest Engineering Program Review and implementation evidence.
6. Discovery artifacts and the four canonical sources.
7. Legacy behavior as evidence only.

Never resolve a genuine contradiction silently. Preserve the facts and escalate through the Chief Architect.

## Start Gate

Do not begin specialist work from a dirty shared workspace. The Chief Architect must provide:

- an exact base commit;
- a dedicated worktree and branch;
- one bounded objective;
- file ownership;
- acceptance and verification requirements;
- any private artifact access required for that packet.

At the time this package was created, `main` was at `4e0038d` with the Nelson walkthrough slice still uncommitted. That is transition context, not a permanent branch target. Agents must use the clean integration baseline explicitly supplied by the Chief Architect.

## Standing Rule

Business behavior belongs in the domain. Storyboards describe it, lifecycle scenarios orchestrate it, browser verification proves it, and evidence packages make it reviewable. No agent may create a second workflow, financial engine, authorization path, or migration truth.
