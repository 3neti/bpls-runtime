# Payment Confirmation Resilience V1 — Verification

22 September 2026. Local implementation only; Cloud acceptance remains separate.

## Scope

- BPLS baseline: `8bd497c0bd20240ee27caaa7c1ce3bcdb7d38af6`.
- Integration branch: `release/payment-resilience-v1`.
- Provider commits: `5e0c8efaf63617840216ab183bae6a31bacd733b`, `9da6e3473c12b704d135bcb26ba816fa7a71b64a` on isolated package baseline `bf245cbbe3605827dbf6e09a2a6dc93068dac742`.
- Existing main, Cloud applications and historical Collections were not changed. No push, deployment, real funds or historical corrections.

## Implemented

1. Shared automatic/manual checks on Citizen schedule, Citizen application, staff schedule and Executable Application payment surfaces. Existing QR requests resume checking after navigation/reopen/focus.
2. Durable BPLS reconciliation queue and sweep, exact reference/currency/amount checks, schedule/payment locks, bounded retries and persisted support state.
3. Provider signed outbox and delivery workers; BPLS authenticated durable inbox. Events request authoritative inquiry, never declare payment by themselves.
4. Duplicate-event/provider-collection protection, queue-outage recovery and sticky `needs_review`.
5. No simulation of provider-backed obligations. Explicit isolated synthetic fixtures retain simulation. Existing synthetic evidence is not relabelled as real.
6. Collection remains separate from receipt issuance, certifications, authorization, issuance and release.

## Evidence

| Check | Result |
|---|---|
| BPLS focused payment/projection packet | 97 tests, 2,347 assertions passed |
| Full BPLS suite | **1,236 passed, one intentional live-test skip; 19,630 assertions** (1,237 total, 323.5 seconds) |
| Complete Classic ceremony file | 3 tests, 275 assertions passed; authoritative fake-provider settlement, downstream assertions retained |
| Lifecycle Laboratory file | Backend specialist: 19 tests, 953 assertions passed |
| Provider events/payment lifecycle/consumer status | Chief rerun: 43 tests, 209 assertions passed |
| Shared frontend monitor | 6 tests passed |
| Receipt permission/responsive layout regressions | 7 tests passed |
| Browser fixtures | Chief rerun passed: four payment surfaces; desktop 1280×900 and mobile 390×844 |
| Browser conditions | Existing QR, unavailable/error, expired, needs review, paid, overlap suppression and staff permission denial |
| Static/build | TypeScript, ESLint, Prettier, Pint, targeted PHPStan and production build passed |
| Independent review | Testing Adjudicator found no remaining P1/P2 source blocker; deployment acceptance not granted |

Browser evidence is under `tests/Frontend/artifacts/payment-confirmation-resilience/`. Screenshots use synthetic placeholders, not scannable real QR codes. Browser checks used local fake HTTP responses; they do not prove a live provider payment. No horizontal overflow or unexpected browser page errors were observed.

The first full run exposed a Classic test expecting simulation after provider issuance. Commit `713d20a` corrects it to authoritative status confirmation and adds provenance/no-automatic-receipt assertions. Runtime protection was not loosened.

## Remaining release gates

- Exact release approval; implementation tests are green.
- Pin the approved provider package release without silently adopting unrelated changes.
- Verify real queue/cache/database concurrency, scheduler heartbeat, workers, secrets and feature flags in the deployment topology.
- Conduct a separately approved controlled Cloud acceptance run. The live smoke test remains intentionally disabled without authorization.
- Historical incident disposition and any integrity-review reset require separate authority.

See `PAYMENT_CONFIRMATION_RESILIENCE_V1_OPERATIONS.md` for activation, monitoring and rollback boundaries.
