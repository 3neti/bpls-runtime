# Payment Confirmation Resilience V1 — Compass

Updated: 22 September 2026.

## Current position

Implementation complete and locally verified; baseline preserved. Deployment, real funds and incident correction NOT authorized. Renewal paused.

| Gate | Status |
|---|---|
| Evidence/baseline | Incident diagnosis and isolated regression coverage complete |
| Canonical confirmation/background jobs | Integrated; full suite PASS |
| Provider notification durability | Durable signed outbox + worker commits `5e0c8ef`, `9da6e34`; source accepted |
| UI/manual checks/notification ingress | Integrated in `release/payment-resilience-v1`; focused tests passing |
| Browser acceptance | Four surfaces desktop/mobile passed; final permission/error states and screenshots verified |
| Independent acceptance | No remaining P1/P2 source blocker reported; deployment acceptance separate |
| Deployment | Not authorized; prepare only |
| Incident data correction | Separate proposal/approval required |

## Resume instructions

Read the sibling PLAN first. Inspect branch/diff and agent results before editing. Integration worktree is `work/bpls-payment-resilience`; engineer worktree `work/bpls-payment-engine`, both under the Chief workspace. Never mutate the shared local or Cloud databases. Use isolated test databases and fake provider HTTP. Do not run a real status endpoint as a supposedly read-only check.

## Next

Seek approval for exact release integration/deployment, then follow OPERATIONS and verify the actual workers, scheduler and concurrency topology before enabling background features. A controlled Cloud payment needs separate authorization. Do not alter historical incident evidence.

## Checkpoint — implementation underway

- Shared screen monitor implemented; five frontend behavior tests pass (overlap, reopen, failure recovery, expiry/review, teardown). Type check passed. Initial lint findings corrected; final lint pending.
- Signed notification receiver implemented; five isolated PHP tests / 21 assertions pass. Durable inbox recovery and processing tests still expanding. Feature disabled by default.
- Provider specialist reports durable outbox and fake-only tests passing; independent source review and integration pending.
- Browser specialist has clean branch `agent/payment-resilience-browser`, base `bb5f9fe`, authorized loopback fake-only fixture checks.
- Visible Migration Engineer returned empty completed turns with no edits. Fresh backend sub-agent assigned the unchanged dedicated engineer worktree to avoid stalled implementation; independent Adjudicator remains active.
- No deployment, real payment, incident correction or existing database mutation performed.

## Checkpoint — combined verification preparation

- UI commits: `629ea26`, `bb5f9fe`, `f31a52b`. Existing staff QR handoffs and Citizen application summaries now use the shared status monitor. Provider-error wording has an additional regression test pending the next run.
- Pre-backend focused PHP run: 70 tests / 2,237 assertions passed. Receiver coverage expanded afterward to nine tests / 38 assertions, including provider-collection identity deduplication and durable acceptance during queue failure.
- TypeScript and production build passed. Build uses `--configLoader runner` because the isolated worktree shares read-only dependencies.
- Provider isolated commits: `5e0c8ef` and `9da6e34`. Scheduler dispatches durable worker jobs; signed outbox events remain disabled by default. Provider specialist reports 21 queue/event tests / 96 assertions passing; release adoption remains unapproved.
- Backend specialist reports 30 focused tests / 207 assertions and targeted PHPStan passing. Canonical confirmation, durable reconciliation and simulation isolation are still being integrated; these are not yet final combined acceptance results.
- Independent review identified synthetic-collection provenance and provider-collection deduplication risks. Deduplication fixed; provenance handling is in the backend packet. Lost-dispatch unique-lock recovery is being checked.
- Browser specialist remains on isolated, fake-provider fixtures. No Cloud or existing transaction changes.

## Checkpoint — final verification

- Backend integrated as `980780f`, `9bafa77`, `6420b69`, `385c357`; explicit simulation isolation, bounded queue recovery and sticky integrity review are in place.
- Final focused BPLS packet: **97 tests / 2,347 assertions PASS**. Receiver collision, queue outage/recovery and shared-lock configuration checks pass. This is deterministic isolated coverage, not a claim of production-database concurrent execution.
- Targeted PHPStan, Pint, TypeScript, ESLint, Prettier and production build pass. Six pure frontend monitor tests pass.
- Browser fixture packet `3bec2dc` is integrated. Final follow-up must reflect the explicit staff permission and provider-backed simulation prohibition before accepting screenshots.
- Deployed provider v1.0.30 dependency `bfa34afaa63cc48e8c271b318585d23b2a11acb4` was inspected read-only: explicit `data.currency` and integer minor-unit collection totals already exist. Stricter BPLS inquiry parsing is compatible.
- Full PHP suite still running. No deployment, push, real money or incident data changes. Worker operation, database concurrency under deployment topology, and controlled Cloud acceptance remain deployment gates.

## Checkpoint — full-suite compatibility correction

- First broad run: 1,229 tests; 1,227 passed, one skipped, one failed. The failure was the Classic ceremony fixture expecting simulation after creating a provider-backed payable.
- `713d20a` changes that fixture to authoritative status confirmation with fake provider HTTP; no runtime guard weakened. Complete Classic file passes (three tests / 275 assertions), including downstream receipts, certifications and Permit assertions.
- `8f0532e` clarifies that staff may check an existing Classic request while Citizen QR generation remains separate.
- Full suite rerunning on this integrated runtime. Final browser evidence synchronization and release report remain in progress.

## Final local verification

- **Full BPLS suite: 1,236 passed, one intentional live-payment smoke skip; 19,630 assertions; 1,237 total.** No failures. Final run took 323.5 seconds.
- Chief provider rerun: **43 tests / 209 assertions PASS**.
- Chief reran both browser fixtures after integration: PASS on desktop and 390×844. Screenshots visually inspected; unavailable and paid states now match their filenames. Browser caches are worktree-local, not shared dependency writes.
- Six monitor tests and seven receipt/layout tests pass. Runtime types/lint/format/build and targeted PHPStan pass; all changed PHP files pass Pint.
- See VERIFICATION for evidence scope and OPERATIONS for activation boundaries. Source implementation is accepted; deployed concurrency/workers and Cloud payment acceptance remain unperformed, not presumed.
- No push, deployment, real funds, main-worktree mutation or historical correction.
