# Anaïs walkthrough repair — v1.6.2

## Scope

Repair scenario guidance and restore a financially inert, fill-blanks-only helper for the exact authorized 2025 New Classic invitation. Classic is the testing ceremony, not a transaction type. Preserve Cloud Application 298 unchanged. Renewal remains paused and excluded from this branch.

## Release boundary

- Base: `a0401d740816f25c7a3dcd5ee3ac382e1c5a2001`, independently verified running on UAT on 2026-09-20.
- Branch: `repair/anais-walkthrough-20260920`.
- No pricing-policy changes, fee overrides, backdating, existing-application changes or deployment authorized by this repair.
- Exact bound 2025 Classic historical replay remains available; a year alone never authorizes that exception.

## Implementation and acceptance

1. Add a separate compact Citizen helper gated by the resolved active Classic invitation, exact source specimen, bound actor, synthetic/no-liability flags and no existing application. Fill only blank applicant fields. Never fill documents, oath, signature, year/type, fees or classifications; never save automatically.
2. Display year and scenario before saving/lodging; distinguish ordinary intake from the authorized walkthrough without declaring all 2026 applications unsupported.
3. Explain unresolved Treasury requirements beside Confirm Treasury. Keep exact LOB and Enterprise Classification distinct. Preserve locked unresolved fees.
4. Show the authorized reference only for the exact bound 2025 replay: office charges ₱2,850; Treasury ₱1,125 (ID ₱25, Occupation ₱100, source-observed Mayor ₱1,000); total ₱3,975.
5. Test positive and negative helper/authority boundaries, retained drafts and both Treasury paths. Browser-check desktop and 390×844 without mutating Application 298.
6. Publish a compact private v1.6.2 guide/PDF using existing style. Do not commit credentials. Existing screenshots remain identified as historical evidence.
7. Independent review, clean repair-only commit, then request deployment approval. Do not merge paused renewal work.

## Division of labor

- Chief: Citizen helper, scenario preflight, guide and coordination.
- Migration and Rescue Engineer: Treasury presentation and focused tests.
- Testing Agent: read-only Cloud evidence and isolated local browser acceptance.
- Testing Adjudicator: independent boundary and acceptance review.

## Compass — 2026-09-20

- [x] Cloud base independently verified; isolated repair worktree created.
- [x] Exact historical-replay exception reviewed and preserved in plan.
- [x] Citizen helper and preflight implemented; strict server-boundary, blank-only and reversible-clear tests passed.
- [x] Treasury guidance implemented; 39 focused PHP tests / 248 assertions passed. Pricing/domain actions unchanged.
- [x] Bounded changed-surface desktop/mobile browser checks complete; instrumentation and unrelated display limitations below.
- [x] Guide v1.6.2 Markdown and 44-page PDF visually verified (private candidate, not deployed).
- [x] Testing Adjudicator final bounded acceptance: PASS, ready for clean repair commit and separately authorized deployment.
- [ ] Deployment separately approved/performed.

Application 298 has not been modified. This file is the repair compass; update it at each gate and interruption.

### Verification checkpoint

- Final source review: Testing Adjudicator accepted the corrected candidate; no critical source/guide finding. Browser and full-suite gates remain separate.
- Frontend suite: 108 passed after allowing localhost/browser test permissions (initial sandbox run could not bind five local test servers).
- TypeScript, build, changed-surface ESLint/Prettier, Pint and diff whitespace passed.
- New helper PHPStan passed. Independent exact-base comparison: both base and candidate have the same 13 findings (BuildBploRoutingTask 5; Citizen controller 8), only line offsets differ. No new findings; not a globally clean PHPStan result.
- Full PHP suite initially exhausted the default 256 MB limit. Rerun at 1 GB passed: 1,166 passed, 1 skipped, 19,277 assertions, 302.799 seconds. Includes complete canonical Classic lifecycle regression, not a new manual Cloud run.
- Private guide v1.6.2 Markdown/PDF drafted, marked candidate/not deployed. 44-page monograph rendered; changed Citizen and Treasury pages and whole-document layout visually inspected. Credentials remain outside Git.
- Browser owner: backup `guide_cold_reader`, after dashboard Testing Agent produced no work product. New isolated SQLite only, server port 8126. Ordinary 2026 and exact Classic Citizen, 2026 Treasury blocked state, and exact Classic Treasury reference passed at 1440px and 390×844 without horizontal overflow.
- Actual helper test cleared visible Street, preserved pre-existing entries, filled blanks, then retained a user-edited Owner Street while clearing unchanged helper assignments. No request or lodging from helper actions; oath remained unchecked. Initial helper run captured page errors/failed requests (none), not console.error; no blanket console-error certification for that initial run.
- Disposable local App2 subsequently saved/uploaded/lodged through the UI and routed to four offices. Treasury reference viewed read-only before PPOs; its ₱3,975 is explicitly an expected reference, not a prepared/reconciled Assessment. Console errors/page errors/failed requests were empty for this positive navigation and reload. No PPO, Treasury confirmation, Assessment, payment or permit created for App2.
- Remaining separate display finding: narrow applicant-document card clips Download text; View remains visible. Not in the changed reference; not independently compared against base and not claimed fixed. Does not block the tested reference/helper actions.
- Final browser evidence: `storage/app/anais-repair-evidence/browser-report.json`, plus desktop/mobile helper, negative Treasury and positive reference screenshots. Chief visually inspected the positive desktop/mobile screenshots. Local Application 2 tracking: `SUB-01M2Z3PDXBYFMR4SX5PE21DDPV`; no Cloud mutation. Earlier ordinary/helper/2026 checks lack console.error instrumentation; only the positive Classic Treasury navigation/reload has that capture.

## Next authorized gate

Implementation and bounded local acceptance are complete. Commit this repair branch only. Request separate approval before push/deployment, then perform bounded UAT smoke checks. Do not merge paused renewal commits or migrate/copy the disposable browser database. The v1.6.2 guide remains explicitly marked candidate/not deployed until that release is verified.

### Recovery pointers (internal, not officer instructions)

- Worktree: `/Users/rli/Documents/Codex/2026-09-07/referenced-chatgpt-conversation-this-is-an-2/work/bpls-anais-walkthrough-repair`.
- Private Markdown: parent task `outputs/Ipil Business Permit System - Complete Walkthrough and Acceptance Guide - Version 1.6.2.md`.
- Private PDF: parent task `output/pdf/Ipil Business Permit System - Complete Walkthrough and Acceptance Guide - Version 1.6.2 - Monograph.pdf`.
- Renderer: parent task `work/render_complete_guide.py --revision162 --monograph`; illustrated assets remain in `outputs/guide16/assets`.
- Private artifact SHA-256: Markdown `6417da48017a7b224d87c963c4ce1905083ddbd95532009748762fefa801f062`; PDF `f03ea92a6c013155a9f0edc36444d0c9f454fc067b859dbfef2bf80940ee0326`.
- Isolated browser database is ignored at `database/anais-browser.sqlite`; do not copy it to Cloud or canonical storage. Existing environments and Application 298 are out of mutation scope.
