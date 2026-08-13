# Architecture Readiness Report

Architecture date: 2026-08-13

Classification: `READY FOR IMPLEMENTATION WITH CONDITIONS`

## 1. Architecture Summary

Build the replacement as a single Laravel 13 application on Laravel Cloud with Vue/Inertia surfaces for staff, citizen, and reporting workflows. Use a relational database as the source of truth, Laravel policies/gates for authorization, action/service classes for business operations, queued jobs for generated artifacts and integrations, and a document boundary for permits/receipts/reports.

The architecture preserves discovered business meaning: owner, business, permit application, assessment, fee/tax, payment schedule, collection, receipt, clearance, permit, billing group, report, municipality configuration, and theme.

## 2. Most Consequential Decisions

- Use a single Laravel monolith instead of recreating three Next.js apps.
- Replace Convex/ClickHouse/Airbyte with relational operational data and Laravel-native reporting.
- Persist assessment snapshots and financial event records for auditability.
- Use one authoritative calculation engine.
- Preserve billing groups as the likely Treasury collection mechanism, pending owner acceptance.
- Use policy-backed business authorization, not frontend-only permissions.
- Keep documents behind a rendering/artifact boundary.
- Use staged repeatable migration with legacy ID mapping.

## 3. Unresolved Conditions

- Online payment and reconciliation scope/policy.
- Whether generic billing groups satisfy explicit Treasury modules for miscellaneous fees, stall rental, franchise payments, and other collections.
- Retirement, transfer, and PIL semantics.
- Receipt numbering authority and duplication rules.
- Production configuration and production data export for fee/migration parity.
- Complete TOR report reconciliation.

## 4. Highest Risks

- Financial computation and ordinance parity.
- Treasury collection/receipt auditability.
- Data migration from Convex production data.
- Report parity without ClickHouse.
- Status semantics around `Released`, clearance completion, and actual permit issuance.
- Authorization matrix migration.
- Time pressure causing hidden policy assumptions.

## 5. Recommended First Vertical Slice

First slice: staff-created or staff-processed permit application through deterministic assessment snapshot, payment schedule generation, and non-mutating review UI.

Why this slice first:

- exercises registry, permit application, assessment, authorization, Vue/Inertia UI, audit, and tests;
- proves the highest-risk financial architecture before payment mutation and permit issuance;
- creates fixtures for later payment, clearance, document, and reporting slices;
- can be browser-verified without production mutation.

Scope should stop before real payment recording unless receipt/payment policy is clarified.

## 6. Recommended Division Of Work

Codex:

- own architecture enforcement and first high-risk slices;
- design tests for fee/assessment and workflow invariants;
- review and integrate any external agent work;
- maintain capability/parity ledger.

Warp:

- receive bounded implementation packets after architecture approval;
- good early packets: registry CRUD parity, static navigation shell, reference data screens, low-risk Vue component migration after patterns are established.

Spark or another parallel agent:

- useful for route/screenshot inventories, form field characterization, report column inventory, fixture extraction, browser checks, and parity ledger updates.
- should not independently design domain architecture.

## 7. Questions Requiring Owner Decision

1. Do configurable billing groups satisfy the TOR requirement for miscellaneous fees, government stall rental, franchise payments, and other Treasury collections, or must these be explicit named workflows?
2. Is online payment required for the first three-week rescue, or can the initial delivery support OTC/manual collection with an explicit online-payment seam?
3. Who controls official receipt numbering, and must the system generate OR numbers or only record externally issued numbers?
4. Must retirement/closure, transfer, amendment, and PIL validation be included in first delivery, or documented as post-rescue gaps?
5. Can the project provide production Convex/config/data export early enough to validate fee schedules, migration, and reports?
6. Should the replacement preserve the legacy `Released` status label even though source semantics mean "first payment paid / ready for clearance-driven issuance"?

## 8. Exact Next Action

Review and approve or amend this architecture checkpoint. After approval, begin implementation with a narrow vertical slice for permit application assessment, including tests and browser verification, while keeping unresolved Treasury/payment policy out of hardcoded behavior.
