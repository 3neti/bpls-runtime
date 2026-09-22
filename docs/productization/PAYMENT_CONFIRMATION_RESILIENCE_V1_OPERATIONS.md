# Payment Confirmation Resilience V1 — Release and Operations

Status: BPLS-only shipping approved; backup and verification gates remain mandatory. Provider notifications stay disabled. This document never authorizes a real payment.

## Officer interaction

If a payment was made but confirmation has not appeared, select **Check payment status**. Reopening the payment page also checks the existing request. Do not pay again or simulate a payment because confirmation is delayed. **Needs review** belongs with Treasury; **checking unavailable** is not proof that payment failed.

`needs_review` is deliberately persistent. Ordinary checks and notifications cannot clear it; disposition requires a separately authorized investigation. Transport outages remain automatically recoverable. Background checks stop after the configured 72-hour/864-check window; notification inbox processing enters review after 24 hours. These limits do not declare an obligation unpaid.

## Release gates

- Publish and deploy only the reviewed BPLS release. Do not publish retired provider/host candidates. x-change v1.0.34 is an independently deployed API service, not a BPLS Composer dependency.
- Back up databases and apply additive migrations. Never reset an environment or rewrite incident Collections 33/34.
- Provision durable database queues and a shared lock/cache store. Ensure jobs, failed-jobs and cache tables exist.
- BPLS worker: `php artisan queue:work payments --queue=payments --timeout=105 --tries=3`. Connection retry interval is 180 seconds, greater than worker/job timeout. Job-specific attempts apply to notification processing.
- Run the Laravel scheduler continuously; restart workers after deployment. Verify a worker actually consumes a harmless test job before enabling payment reconciliation.
- Provider worker and notification setup below are deferred: do not change the provider host in this wave.
- Keep all new background/event feature flags disabled until worker, scheduler, shared-lock and receiver checks pass.
- Set `PAYMENT_RECONCILIATION_STARTS_AT` to the agreed activation timestamp in the BPLS app timezone, using `YYYY-MM-DD HH:MM:SS`. The initial rollout must exclude historical requests. The sweep and already queued jobs both enforce this boundary; invalid configuration fails closed. Do not remove the boundary to sweep incident records without separate approval.

## BPLS-first activation

Keep `XCHANGE_PAYMENT_EVENTS_ENABLED=false`; no signing secret or provider receiver is required. After backup, deployment, worker consumption and scheduler checks pass, set a verified app-timezone `PAYMENT_RECONCILIATION_STARTS_AT` and enable only `PAYMENT_RECONCILIATION_ENABLED`. The cutoff excludes existing requests from automatic sweeps and signed-event processing. Manual checks remain explicit officer/citizen actions.

Rollback: disable reconciliation first, drain/stop the dedicated worker if necessary, and restore the recorded previous BPLS release. Preserve additive tables, durable jobs and payment evidence. Do not reverse data migrations or overwrite newer financial records as routine code rollback.

## Notification setup — deferred, not part of this deployment

Configure the provider's deployment-managed receiver map for the exact BPLS partner reference. Destination is the approved BPLS HTTPS host plus `/integrations/x-change/payment-events`; allowlist that host. Use a dedicated secret of at least 32 characters, stored in deployment secrets, never in this document or screenshots.

Set the matching BPLS `XCHANGE_PAYMENT_EVENTS_SECRET` and `XCHANGE_PAYMENT_EVENTS_PARTNER_REFERENCE`. Configure provider durable connection, queue and shared lock store. Both hosts require correct clocks. Timestamp tolerance is five minutes; retries re-sign the same event body.

BPLS notification uniqueness uses `XCHANGE_PAYMENT_EVENTS_LOCK_STORE` (database by default). Its 180-second lease allows recovery after a failed queue submission; the sweep may wait up to this lease plus its next scheduled run. Keep the application's default cache shared as well, for canonical confirmation and background-job locks.

After approval, enable BPLS `XCHANGE_PAYMENT_EVENTS_ENABLED`, provider `XCHANGE_PARTNER_PAYMENT_EVENTS_ENABLED`, and BPLS `PAYMENT_RECONCILIATION_ENABLED`. A notification schedules authoritative inquiry; its reported amount alone never records collection.

## Acceptance after deployment

Use one separately authorized test obligation. Verify payment confirmation after leaving the QR page, reopening the staff handoff, and reopening the Citizen application. Repeat the status check without creating another Collection or receipt. Check queue-outage recovery, delayed notification replay and mismatched-evidence review with isolated fixtures—not real inconsistent payment records.

Confirm exact financial parity and receipt coverage when a payment test is separately authorized. Capture worker/scheduler health without secrets or full payment codes. Notification Cloud acceptance is deferred. Real funds require explicit authorization, amount and destination.

## Monitoring and recovery

Monitor failed jobs, oldest pending provider event, oldest accepted BPLS event, reconciliation errors and `needs_review` records. Alert on stalled queues and scheduler heartbeat loss. Failed provider delivery may be replayed using its documented event retry command; replay must not reissue payment or collection.

Disable new feature flags to stop new background processing if necessary. Preserve durable events and evidence for recovery; do not drop tables on rollback. Browser/manual authoritative checks remain the fallback. Resolve real-versus-synthetic discrepancies through a separately approved reconciliation procedure, never by relabelling historical evidence.
