# Application / Functional Boundaries

Architecture date: 2026-08-13

These are responsibility areas. They do not require separate Composer packages or services.

## Identity And Access

Responsibilities:

- staff authentication;
- citizen authentication;
- roles and permissions;
- policies/gates;
- ownership-scoped citizen access;
- passkeys/2FA support already present in the starter where appropriate.

Why this boundary exists: authorization defects in government financial workflows are high-impact, and Discovery found nuanced staff resources plus citizen-only access.

## Registry

Responsibilities:

- business owners;
- businesses;
- locations;
- business categories/subcategories;
- business documents;
- blacklist/refusal signals;
- legacy identifiers.

Why this boundary exists: registry data is shared by permitting, assessment, reporting, and migration.

## Permit Applications

Responsibilities:

- application intake;
- staff and citizen submission;
- status workflow;
- approval/evaluation;
- lifecycle audit;
- application form/assessment sheet requests.

Why this boundary exists: it coordinates the main BPLS lifecycle but should not own fee math, payment recording, or document rendering details.

## Assessment And Revenue Policy

Responsibilities:

- fee/tax configuration;
- classifications and LOB hierarchy;
- deterministic calculation;
- overrides/exclusions;
- surcharge/penalty policy;
- unresolved policy seams for PIL, retirement, transfer, and new-business treatment.

Why this boundary exists: financial correctness is the largest risk and must be independently testable.

## Treasury Collections

Responsibilities:

- payment schedules;
- OTC payment recording;
- online payment state when later configured;
- manual transactions;
- billing group records;
- receipt metadata;
- voids/reversals;
- reconciliation status.

Why this boundary exists: Treasury behavior spans permits and non-permit collections and must be auditable.

## Billing Groups

Responsibilities:

- configurable non-permit collection definitions;
- dynamic fields;
- fee library;
- line-item transactions;
- billing receipts;
- abstract/report inputs.

Why this boundary exists: Discovery shows this is the legacy mechanism for miscellaneous/Treasury collections, but owner acceptance remains unresolved.

## Clearances And Permits

Responsibilities:

- clearance type administration;
- required clearance assignment;
- clearance completion;
- permit issuance;
- permit status;
- QR verification.

Why this boundary exists: legacy source conflates `Released` with payment milestone; architecture needs a place to keep permit issuance meaning clear.

## Documents

Responsibilities:

- document definitions;
- render requests;
- generated artifacts;
- storage paths;
- template data binding;
- PDF/spreadsheet rendering adapters.

Why this boundary exists: permits, receipts, assessments, and reports must be reproducible and must not scatter rendering code through controllers or Vue components.

## Reporting

Responsibilities:

- operational report queries;
- statutory report definitions;
- projection maintenance where required;
- export jobs;
- generated report artifacts;
- filters and run metadata.

Why this boundary exists: reporting replaces ClickHouse/Airbyte with Laravel-native relational reporting while preserving outputs.

## Municipality Configuration And Theme

Responsibilities:

- municipality identity;
- offices;
- officials/signatories;
- permit numbering/expiry settings;
- branding assets/colors.

Why this boundary exists: Ipil identity must be replaceable without rewriting BPLS behavior.

## Migration And Provenance

Responsibilities:

- staging imported legacy data;
- legacy ID mapping;
- repeatable transforms;
- validation reports;
- rejected/unresolved records.

Why this boundary exists: production data is not yet available and migration must be repeatable, auditable, and separate from permanent runtime behavior.
