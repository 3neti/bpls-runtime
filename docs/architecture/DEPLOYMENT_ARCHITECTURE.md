# Deployment Architecture

Architecture date: 2026-08-13

Target deployment boundary: Laravel Cloud.

## Required Runtime Components

- Laravel 13 web application.
- Relational database.
- Queue worker.
- Scheduler.
- Cache.
- Object/file storage.
- Mail/SMS/payment provider credentials only when configured.

## Not Retained

- Convex as application database.
- ClickHouse as reporting database.
- Airbyte as reporting sync pipeline.
- Vercel as application hosting.
- Separate Next.js applications.

## Environments

Minimum practical environments:

- production;
- preview/staging if Laravel Cloud project setup allows it;
- local development.

Production data should not be used as a test sandbox. Migration rehearsals should run against copied/exported data in non-production.

## Queues

Use queues for:

- report exports;
- document generation;
- notification delivery;
- data import/migration validation;
- integration callbacks that require retry.

Use separate queues by priority when needed:

- `default`;
- `reports`;
- `documents`;
- `notifications`;
- `imports`.

## Scheduler

Use scheduler for:

- report artifact cleanup;
- expired verification token cleanup if applicable;
- scheduled reconciliation polling if a payment gateway later requires it;
- migration validation reports during cutover rehearsal.

## Storage

Use object storage for:

- uploaded business documents;
- generated permits;
- generated receipts;
- generated assessment sheets/forms;
- report exports;
- migration import files where approved.

Never store credentials or session artifacts in repository evidence files.

## Observability

Minimum requirements:

- application logs with request correlation;
- failed job visibility;
- audit events for business changes;
- export/import status;
- backup and restore process documented before production cutover.

## Cutover Posture

Before production cutover:

- complete repeatable migration rehearsal;
- validate financial totals and report counts;
- verify role access;
- browser-verify critical workflows;
- retain legacy source/evidence checksums;
- document known gaps and acceptance decisions.
