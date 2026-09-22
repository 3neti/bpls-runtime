# Payment Confirmation Resilience V1 — Release and Operations

Status: implementation verification in progress. This document does not authorize deployment or payment.

## Officer interaction

If a payment was made but confirmation has not appeared, select **Check payment status**. Reopening the payment page also checks the existing request. Do not pay again or simulate a payment because confirmation is delayed. **Needs review** belongs with Treasury; **checking unavailable** is not proof that payment failed.

## Release gates

- Approve exact BPLS and x-change release SHAs separately. The editable x-change package baseline is newer than the currently deployed package; do not upgrade unrelated package changes implicitly.
- Back up databases and apply additive migrations. Never reset an environment or rewrite incident Collections 33/34.
- Provision durable database queues and a shared lock/cache store. Ensure jobs, failed-jobs and cache tables exist.
- BPLS worker: `php artisan queue:work payments --queue=payments --timeout=105 --tries=3`. Connection retry interval is 180 seconds, greater than worker/job timeout. Job-specific attempts apply to notification processing.
- Run the Laravel scheduler continuously; restart workers after deployment. Verify a worker actually consumes a harmless test job before enabling payment reconciliation.
- Provider worker: use the configured durable connection and `partner-payments` queue, with 30-second timeout and connection retry interval greater than 30 seconds. The scheduler dispatches delivery jobs; it must not perform network delivery itself.
- Keep all new background/event feature flags disabled until worker, scheduler, shared-lock and receiver checks pass.

## Notification setup

Configure the provider's deployment-managed receiver map for the exact BPLS partner reference. Destination is the approved BPLS HTTPS host plus `/integrations/x-change/payment-events`; allowlist that host. Use a dedicated secret of at least 32 characters, stored in deployment secrets, never in this document or screenshots.

Set the matching BPLS `XCHANGE_PAYMENT_EVENTS_SECRET` and `XCHANGE_PAYMENT_EVENTS_PARTNER_REFERENCE`. Configure provider durable connection, queue and shared lock store. Both hosts require correct clocks. Timestamp tolerance is five minutes; retries re-sign the same event body.

After approval, enable BPLS `XCHANGE_PAYMENT_EVENTS_ENABLED`, provider `XCHANGE_PARTNER_PAYMENT_EVENTS_ENABLED`, and BPLS `PAYMENT_RECONCILIATION_ENABLED`. A notification schedules authoritative inquiry; its reported amount alone never records collection.

## Acceptance after deployment

Use one separately authorized test obligation. Verify payment confirmation after leaving the QR page, reopening the staff handoff, and reopening the Citizen application. Repeat the status check without creating another Collection or receipt. Check queue-outage recovery, delayed notification replay and mismatched-evidence review with isolated fixtures—not real inconsistent payment records.

Confirm exact financial parity and receipt coverage. Capture worker/scheduler health, notification delivery and inbox-processing evidence without secrets or full payment codes. A test with real funds requires explicit authorization, amount and destination.

## Monitoring and recovery

Monitor failed jobs, oldest pending provider event, oldest accepted BPLS event, reconciliation errors and `needs_review` records. Alert on stalled queues and scheduler heartbeat loss. Failed provider delivery may be replayed using its documented event retry command; replay must not reissue payment or collection.

Disable new feature flags to stop new background processing if necessary. Preserve durable events and evidence for recovery; do not drop tables on rollback. Browser/manual authoritative checks remain the fallback. Resolve real-versus-synthetic discrepancies through a separately approved reconciliation procedure, never by relabelling historical evidence.
