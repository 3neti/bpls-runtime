# Discovery Synthesis Report

Discovery date: 2026-08-13

Classification: `READY FOR ARCHITECTURE WITH CONDITIONS`

## What Is This System?

The system being replaced is not only a Business Permit issuance application. Based on the TOR, ordinance, legacy source, and live surfaces, it is a combined BPLS, Treasury collection, configurable non-permit billing, permit document, and reporting platform for the Municipality of Ipil.

As the TOR expects it, the system should support public applicant registration, new and renewal business permit processing, assessment, evaluation, approval, payment, Treasury collection, release, reporting, audit, notifications, data migration, and electronic documents.

As the legacy system exists, it is a three-application Next.js/Convex/ClickHouse system:

- staff back-office for permit processing, registries, fees, payments, billing groups, clearances, settings, activity, users, and reports;
- citizen portal for registration, profile/business setup, application submission/tracking, and mock payment detail;
- separate ad hoc reporting application for statutory/analytical reports.

## Major Functional Domains

- Identity and authorization: staff login, citizen login, RBAC, roles, permissions.
- Registry: business owners, businesses, locations, categories/subcategories.
- Permit application workflow: draft, assessment, approval, pending payment, released/releasing.
- Assessment and fee configuration: major/division/group hierarchy, fees, formulas, ranges, overrides, UOM variables, surcharge/penalty config.
- Treasury/payment: payment schedules, payment recording, voiding, manual transactions, billing groups, receipts.
- Clearances and permit issuance: clearance types, checklist, permit PDF, QR verification.
- Reporting: saved report builder, workflow CSV export, separate ad hoc reports over warehouse data.
- Municipality configuration/theme: municipality, officials, branding, platform settings, permit and receipt layouts.
- Audit and operations: activity logs, onboarding, security controls in code.

These are domains of responsibility, not Laravel modules.

## Current Completeness

Complete or substantially implemented:

- staff login and dashboard;
- registries for owners/businesses;
- permit application stage surfaces;
- fee hierarchy and fee engine;
- payment schedule and staff payment recording;
- configurable billing groups;
- clearances;
- permit/receipt layout systems;
- many statutory reports;
- activity logs and RBAC.

Partial:

- online payment and reconciliation;
- Convex-native report exports beyond permit applications;
- TOR Treasury reports beyond the reports explicitly found;
- PLDS report fields;
- notifications;
- data migration support;
- ordinance-specific fee/rule completeness.

Missing or not found:

- first-class retirement/closure lifecycle;
- first-class transfer/amendment lifecycle;
- explicit PIL enforcement;
- SMS/email integration;
- complete live-observed citizen portal behavior.

Uncertain:

- whether generic billing groups are accepted by Ipil users as the intended implementation of miscellaneous fees, stall rentals, franchise payments, and other Treasury collections;
- whether production database configuration contains the complete Revenue Code fee schedule;
- official receipt numbering controls and source of truth.

## Largest Gaps

The largest TOR gaps are Treasury-related: online payment, reconciliation, official receipt controls, and the complete report list. The largest ordinance gaps are financial/legal: new business initial tax treatment, PIL validation, retirement/closure, transfer/change handling, and full fee schedule parity.

## Largest Risks

- Financial computation: formula/range behavior, Revenue Code rates, surcharges, penalties, and exemptions need characterization tests.
- Treasury integration: online payment appears mock; reconciliation is not proven.
- Permissions: Admin UI and backend enforcement can diverge if permission rows are incomplete.
- Data migration: legacy source shows Convex as source of truth, but no production data export was supplied.
- Reporting: separate ClickHouse/ad hoc reports exist; some reports call back into Convex fee logic.
- Permit lifecycle: `Released` status in source is reached after first payment and before all clearances, which is semantically dangerous.
- Production-only behavior: full workflow cannot be safely observed without mutating production.

## Important Contradictions

- TOR says payment/reconciliation/Treasury collection/permit release; source marks applications `Released` after Section 1 payment and then assigns clearances.
- TOR expects online payment/reconciliation; source explicitly describes citizen payment as mock/no live gateway.
- Ordinance has retirement/transfer/PIL/new-business tax rules; source first pass does not show first-class support for several of these.
- TOR names broad Treasury reports; source/live prove a subset and expose other reports, but not full TOR coverage.

## Architectural Signals

- Reporting is tightly coupled to operational permit/payment data and fee calculation, even when using ClickHouse.
- The fee engine is configurable and central to parity, but formula behavior exists in multiple implementations.
- Municipality identity and document branding are configurable in settings and print layout systems.
- Generic billing groups appear to be the legacy answer for non-permit Treasury collections.
- Source code alone is insufficient for financial parity; production configuration/data is required.

These are signals only, not design decisions.

## GNE / Product Observations

Reusable BPLS concepts naturally emerging from evidence:

- business owner;
- business;
- line of business;
- permit application;
- assessment;
- fee;
- payment schedule;
- clearance;
- permit;
- billing group;
- taxpayer account card;
- revenue collection/reporting.

No generic BPLS grammar should be designed yet.

## Readiness Recommendation

`READY FOR ARCHITECTURE WITH CONDITIONS`

Reason: Discovery now identifies the major capabilities, surfaces, lifecycles, and known contradictions well enough to start architecture discussion. Conditions are material: before implementation, the project needs conscious decisions on online payment scope, generic billing groups versus explicit Treasury modules, retirement/transfer/PIL treatment, receipt numbering, and access to production configuration/data for fee and migration parity.
