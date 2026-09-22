# Payment Confirmation Resilience V1 — Compass

Updated: 22 September 2026.

## BPLS-first checkpoint

- Provider package `release/payment-resilience-v1030` at `5ed59de2` and old host `release/payment-resilience-v1` at `56728426` are **SUPERSEDED — DO NOT DEPLOY**. Retained for reference only. Provider v1.0.34/package `a5f3380f`, host `63aa389a`, remain independently managed.
- Reviewed v1.0.34 Partner API source: collection totals remain explicit; Paid and availability are separate. Added four BPLS compatibility cases for expired/closed/cancelled availability and insufficient Paid-label evidence. No Composer dependency change.
- Focused payment packet: **105 tests / 748 assertions PASS**; six frontend monitor tests PASS; four actual Vue surfaces rerun at desktop and 390×844 PASS; updated screenshots inspected. Pint and diff check PASS.
- Read-only Cloud preflight confirms BPLS still `8bd497c` with no worker and scheduler disabled. Private bucket/disk binding verified. Logical backup creation and checksum verification in progress; no deployment yet.

## Current position

Implementation complete and locally verified. Owner approved the narrowed BPLS-only push/deployment plan on 22 September 2026. Provider candidates are superseded; notifications remain disabled. Backup and readiness remain gates. No push or deployment performed yet. Real funds and incident correction remain unauthorized. Renewal paused.

| Gate | Status |
|---|---|
| Evidence/baseline | Incident diagnosis and isolated regression coverage complete |
| Canonical confirmation/background jobs | Integrated; full suite PASS |
| Provider notification durability | Source accepted; deployment deferred, older candidates superseded |
| UI/manual checks/notification ingress | Integrated in `release/payment-resilience-v1`; focused tests passing |
| Browser acceptance | Four surfaces desktop/mobile passed; final permission/error states and screenshots verified |
| Independent acceptance | No remaining P1/P2 source blocker reported; deployment acceptance separate |
| Deployment | BPLS-only authorized; backup/readiness gates pending |
| Incident data correction | Separate proposal/approval required |

## Resume instructions

Read the sibling PLAN first. Inspect branch/diff and agent results before editing. Integration worktree is `work/bpls-payment-resilience`; engineer worktree `work/bpls-payment-engine`, both under the Chief workspace. Never mutate the shared local or Cloud databases. Use isolated test databases and fake provider HTTP. Do not run a real status endpoint as a supposedly read-only check.

## Next

Resolve exact GitHub publishing approval and pre-migration backup readiness. Recheck both Cloud deployment SHAs before release, especially a concurrent provider deployment observed during preflight. Then follow OPERATIONS, verify workers/scheduler/concurrency, and activate future-only reconciliation. A controlled Cloud payment needs separate authorization. Do not alter historical incident evidence.

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

## Shipping preflight — 22 September 2026

- User requested shipping. Unrelated dirty main checkouts are retained untouched; release work remains isolated.
- Initial Cloud baselines: BPLS `8bd497c` on `release/workflow-handoff-uat-20260911`; provider host `3e490af8` on `main`. Both push-to-deploy enabled. A later provider read reported an external deployment in progress; refresh its SHA before adopting any host patch.
- BPLS has no worker and scheduler disabled. Provider has scheduler plus an existing funding/feedback/default worker. Both use database queue/cache/session and have jobs/cache/cache_locks tables.
- Both managed databases reject snapshot creation as unsupported. No snapshots created or retention changed. BPLS has pg_dump and an S3 disk; logical backup/export has NOT been performed. Backup readiness remains a release gate.
- Publishing attempt was blocked by the tool approval check; requested explicit authorization for release branches in `3neti/bpls-runtime`, `3neti/x-change`, and `3neti/x-change-sandbox`. Do not retry or bypass that denial before it is resolved.
- Isolated provider package `work/x-change-payment-ship`, branch `release/payment-resilience-v1030`, candidate `5ed59de2040bccf553b5c0a5a3966d165af2ac40`: deployed package base plus only the two accepted changes. 43 tests / 209 assertions PASS. No stable version tag created; use a frozen branch/hash pin to avoid shipping unrelated later package work.
- BPLS rollout guard `5d00ec7`: optional inclusive `PAYMENT_RECONCILIATION_STARTS_AT`, strict app-timezone timestamp, excludes older requests from sweep and stale jobs. Combined 51 tests / 336 assertions PASS; targeted PHPStan/Pint passed in specialist packet. Manual checks unchanged.
- Isolated host `work/x-change-host-payment-ship`, candidate `bf3e2fbb`: deployment-secret-backed receiver wiring and partner-payments queue declaration. Two configuration tests / seven assertions PASS, Pint PASS. Composer adoption still pending publishing approval; copied test dependencies are not proof of exact final package installation.
- No Cloud configuration, worker, scheduler, application, Collection or receipt mutation performed by this wave. Only read-only inventory and unsuccessful snapshot requests occurred.
- Provider baseline invalidated by an external successful deployment at `2026-09-22T01:27:08Z`: host is now `63aa389acafb24e3dd0fd8699bf2075cfea85293`, deployment `depl-a2cd9f95-f756-4a95-bb05-2aef81221527`. Do NOT deploy the prepared `3e490af8`-based host branch over it. Inspect the new deployed dependency and re-prepare a minimal adoption after concurrent deployment activity settles.
- Follow-up review closes the notification activation gap: signed events for pre-cutoff payment requests now enter review without provider inquiry or payment/schedule mutation. Focused packet: **52 tests / 343 assertions PASS**. Host manifest now inventories the receiver configuration and signing secret; this remains a portable patch, not permission to deploy its stale base.
- Publishing authorization, verified private backup destination, and refreshed provider baseline remain unresolved. No push or deployment performed.
