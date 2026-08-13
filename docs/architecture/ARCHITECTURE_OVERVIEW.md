# Architecture Overview

Architecture date: 2026-08-13

Status: proposed architecture for review. This is not an implementation plan.

## Architectural Style

`bpls-runtime` should be a single Laravel 13 application with a Vue/Inertia frontend, relational operational data, queued background work for slow artifacts, and explicit domain services/actions for high-risk business operations.

The target is not a Convex/Next.js clone. The legacy system is evidence of behavior and surfaces. Laravel should recover the business meaning in a maintainable monolith.

## Boundary Diagram

```text
Users
  |
  | browser
  v
Laravel 13 on Laravel Cloud
  |
  +-- Inertia/Vue screens
  |     - staff portal
  |     - citizen portal
  |     - reporting screens
  |
  +-- HTTP controllers / form requests / policies
  |
  +-- Application actions
  |     - submit application
  |     - assess application
  |     - approve application
  |     - record collection/payment
  |     - complete clearance
  |     - issue permit
  |     - generate report/document
  |
  +-- Domain services
  |     - assessment calculator
  |     - payment schedule builder
  |     - receipt policy seam
  |     - permit lifecycle rules
  |     - reporting query services
  |
  +-- Relational database
  |     - transactional records
  |     - reference/configuration
  |     - financial ledgers
  |     - audit log
  |     - reporting projections
  |
  +-- Queue / scheduler / storage / cache
        - report exports
        - generated PDFs
        - notifications
        - migration imports
```

## Runtime / Frontend Relationship

The Laravel app owns routing, authentication, authorization, business actions, persistence, queues, and generated artifacts. Vue/Inertia owns the interactive screen experience. Use Wayfinder-generated route/action helpers for frontend calls rather than hardcoded URLs.

The rescue UI should reproduce the observable staff portal, citizen portal, and reporting surfaces. Internal Vue component structure may improve, but page names, navigation, terminology, and visible workflows should remain recognizable.

## Persistence

Use one relational operational database as the source of truth. Use normalized tables for business records, applications, assessments, payment schedules, collections, permits, clearances, and configuration. Preserve legacy identifiers and source provenance for migration traceability, but do not reproduce Convex embedded arrays or ClickHouse mirrors as-is.

## Reporting

Reporting stays inside Laravel. Operational transactions remain canonical. Reporting query services read normalized data and, when needed, maintained relational projections/aggregate tables. Long exports run through queued jobs and write generated artifacts to storage. Do not introduce ClickHouse unless later profiling proves the relational design cannot satisfy required reporting volumes.

## Document Generation

Documents are a boundary, not controller code. Permits, receipts, assessment sheets, application forms, clearance certificates, and reports should be represented by document definitions and renderers. Business identity such as "Mayor's Permit" or "Official Receipt" must remain separate from the PDF rendering library chosen later.

## Authorization

Use Laravel authentication through the installed Fortify baseline. Add a first-party role/permission model derived from discovered resources and business actions. Policies and gates authorize both screen access and business operations. Citizen access is separate from staff authority and is ownership-scoped.

## Audit

Important business actions create audit entries with actor, action, before/after state where practical, affected resource, source channel, and reason/remarks. Financial changes, assessment changes, clearances, approvals, payments, receipt events, permit issuance, and configuration changes are audit-critical.

## Deployment Boundary

Deploy the Laravel app to Laravel Cloud with:

- web runtime;
- relational database;
- queue worker;
- scheduler;
- cache;
- object/file storage;
- mail/SMS/payment integrations only when configured.

The production boundary replaces Vercel, Convex, ClickHouse, and Airbyte for the rescue system.

## External Integration Boundary

All external systems are behind contracts:

- payment gateway / reconciliation provider;
- notification channels;
- future government/LGU integrations;
- public permit verification;
- export/download artifact storage.

Unknown integrations remain explicit unknowns and must not leak into core domain code.
