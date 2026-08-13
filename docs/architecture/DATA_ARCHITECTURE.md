# Data Architecture

Architecture date: 2026-08-13

This proposes relational persistence after the domain model. It is not a migration file list.

## Principles

- Preserve meaning and data, not Convex storage topology.
- Use relational integrity for core records.
- Use JSON only for bounded, schema-flexible configuration or snapshots where relational columns would create churn.
- Store money as integer minor units or fixed `decimal(12, 2)` consistently; do not use float.
- Keep source provenance and legacy IDs for migration and audit.
- Prefer append/reversal records for financial events.

## Transactional Data

Candidate records:

- users;
- roles;
- permissions;
- role_permission;
- business_owners;
- businesses;
- business_lines;
- business_documents;
- permit_applications;
- permit_application_lines;
- application_status_events;
- assessments;
- assessment_lines;
- assessment_overrides;
- payment_schedules;
- payment_schedule_lines;
- collections;
- collection_allocations;
- collection_reversals;
- receipts;
- clearance_types;
- permit_clearances;
- permits;
- billing_groups;
- billing_group_fields;
- billing_group_fee_items;
- billing_records;
- billing_record_fields;
- billing_record_lines;
- manual_transactions.

## Reference / Configuration Data

Candidate records:

- provinces;
- cities;
- barangays;
- business_categories;
- business_subcategories;
- revenue_classifications;
- fee_names;
- assessment_majors;
- assessment_divisions;
- assessment_groups / lines of business;
- division_group assignments;
- fee_rules;
- fee_rule_ranges;
- fee_rule_formulas;
- surcharge_penalty_policies;
- payment_due_date_policies;
- municipality_profile;
- municipality_officials;
- theme_settings;
- permit_layouts;
- receipt_layouts;
- document_templates.

## Financial Records

Financial records should be explainable after the fact:

- `assessments` identify application, status, assessed_at/by, source policy versions, and total.
- `assessment_lines` record fee/tax name, category, basis, computed amount, formula/range provenance, and whether it was overridden/excluded.
- `payment_schedules` record due sections.
- `payment_schedule_lines` allocate assessed lines into due sections.
- `collections` record money/payment events, channel, method, reference, status, received_at, received_by.
- `collection_allocations` connect collections to payment schedule lines or billing record lines.
- `collection_reversals` record void/refund/cancellation without deleting original events.
- `receipts` record receipt number, authority/source, status, issued_at/by, and related collection.

Receipt numbering remains an explicit policy seam until owner/Treasury clarifies authority.

## Audit Data

Use an `audit_events` table or equivalent append-only log for:

- resource type/id;
- actor type/id;
- action;
- previous status/value where practical;
- new status/value where practical;
- reason/remarks;
- request channel;
- correlation/request ID;
- occurred_at;
- legacy source marker for migrated events.

Audit should be queryable by resource and actor.

## Reporting Data

Start with query services over operational tables. Add relational projections only when needed:

- `report_runs` for user, report type, filters, status, row count, totals, artifact paths.
- `report_artifacts` for CSV/PDF/spreadsheet outputs.
- optional aggregate tables for daily collections by revenue account/source/date;
- optional permit/reporting denormalizations for report speed.

All projections must be rebuildable from transactional data.

## Migration / Provenance Data

Use import-staging and mapping records:

- `legacy_sources`;
- `legacy_import_batches`;
- `legacy_records` or per-source staging tables;
- `legacy_id_mappings`;
- `migration_validation_results`;
- `migration_exceptions`.

Core domain tables should carry optional `legacy_source_id`, `legacy_table`, `legacy_id`, and `legacy_payload_hash` where traceability is useful.

## Deliberate Non-Reproduction Of Legacy Storage

Do not preserve:

- Convex embedded arrays as the permanent model when relationships matter;
- ClickHouse mirror tables;
- Airbyte sync state;
- Vercel/Next.js route topology as data design;
- multiple formula evaluators.

Recover:

- fee hierarchy;
- application snapshots;
- payment schedules;
- billing group flexibility;
- document template meaning;
- reporting outputs.
