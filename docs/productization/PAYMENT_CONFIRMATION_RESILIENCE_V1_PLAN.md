# Payment Confirmation Resilience V1

Approved implementation: 22 September 2026. Deployment, live-money testing and historical incident correction remain separate approval gates. Renewal is paused.

## Outcome

Confirm an exact, authoritative full payment independently of browsers, without duplicate Collections or receipts. Preserve liability, assessment, collection/receipt separation and all permit ceremonies.

## Baseline and evidence

BPLS base: `8bd497c0bd20240ee27caaa7c1ce3bcdb7d38af6`. Work occurs in clean dedicated branches; existing main and guidance edits remain untouched. The private incident report records two real provider payments: one confirmed normally, one missed after logout and then simulated. Do not change either incident transaction. No identifiers or private bank evidence belong in committed fixtures.

## Implementation gates

1. Preserve evidence; inventory queues/scheduler/provider notification capabilities; reproduce browser-dependency and simulation-conflict failures in isolated tests.
2. One canonical reconciliation service validates exact correlation/currency/amount, uses transaction/locking/uniqueness protection and exposes explicit pending/review/error states. No automatic receipt issuance.
3. Dedicated BPLS payments jobs dispatched after commit, periodic pending sweeps, bounded retries/backoff, late-settlement window, persisted check/review evidence. Background activation defaults off until approved deployment.
4. Reuse or add durable provider outbox and signed partner notifications with stable event IDs, delivery retry/audit, configured destinations and replay protection. BPLS durably accepts authenticated events and reconciles against authoritative inquiry; no blind payment mutation from payload.
5. Citizen/staff/Executable Application share manual Check payment status behavior, resume on reopen/focus, prevent concurrent clicks, and show last check plus truthful pending/error/review messages. QR expiry is not proof of nonpayment.
6. Isolate simulation from real payables. Never overwrite prior synthetic evidence or accept provider uncertainty as permission to simulate. Historical incident disposition is separately approved.
7. Focused regression and concurrency/retry/authentication tests, frontend types/lint/build, financial lifecycle regressions, independent review, isolated desktop and 390×844 browser checks.
8. Prepare compatible rollout/rollback, worker/scheduler verification and monitoring. Seek deployment approval; after approval run controlled Cloud acceptance without duplicate financial actions.

## Acceptance matrix

- Browser closed/logout/navigation: background payment confirmation continues.
- Existing QR reopened: visible manual check and automatic refresh resume for authorized actors.
- Provider outage/worker restart: durable recovery, bounded load and explicit failure reporting.
- Duplicate/out-of-order notification/concurrent checks: one Collection, unchanged separate receipt ceremony.
- Invalid signature/reference/currency or partial/excess collection: reject or Needs review, never false paid.
- QR expired with late authoritative settlement: safe reconciliation within configured review window.
- Real payment versus simulation race: no duplicate collection and no misleading real/synthetic provenance.
- Existing completed incidents remain unchanged throughout implementation/testing.

## Ownership

Chief: integration, frontend, notification ingress, plan/compass and release gates. Migration and Rescue Engineer: dedicated backend reconciliation packet. Testing Agent: isolated UI/background acceptance. Testing Adjudicator: independent integrity review. Provider specialist: clean x-change package packet and wire-contract coordination.

## Operations

Dedicated payments queue; worker timeout below retry-after; shared locks; failed-job and overdue-payment monitoring; no credentials/payloads in logs. Queue mechanisms are at-least-once: database/domain idempotence is mandatory. Do not introduce dependencies or silently upgrade the provider package. Validate exact runtime settings before activation.
