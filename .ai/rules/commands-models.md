---
paths:
  - '{app/Actions/StageLegacyExport.php,app/Console/Commands/StageLegacyExportCommand.php,app/Models/Legacy*.php}'
  - '{app/Actions/PlanLegacyRegistryMigration.php,app/Console/Commands/PlanLegacyRegistryMigrationCommand.php,app/Models/LegacyMapping*.php}'
  - '{app/Actions/*LegacyRegistryMigration.php,app/Console/Commands/*LegacyRegistryMigrationCommand.php,app/Models/LegacyMapping*.php}'
  - '{app/Actions/PlanLegacyApplicationDeclarations.php,app/Console/Commands/PlanLegacyApplicationDeclarationsCommand.php,app/Models/Legacy{DeclarationMapping*,LineOfBusinessReconciliation}.php}'
  - '{app/Actions/PlanLegacyFinancialDependencies.php,app/Console/Commands/PlanLegacyFinancialDependenciesCommand.php,app/Models/Legacy{FeeRuleReconciliation,FinancialMapping*}.php}'
  - '{app/Actions/PlanLegacyPermitEvidence.php,app/Console/Commands/PlanLegacyPermitEvidenceCommand.php,app/Models/Legacy{ClearanceTypeReconciliation,PermitEvidence*}.php}'
  - '{app/Actions/AssessLegacyMigrationReadiness.php,app/Console/Commands/AssessLegacyMigrationReadinessCommand.php,app/Models/LegacyMigrationReadinessAssessment.php}'
---

# Commands Models

## Legacy staging never mutates domain records
Legacy exports must enter checksum-verified staging first. Preserve payload hashes, stable source/run identity, validations, and unresolved exceptions; do not create or update BPLS domain records until a separate deterministic mapping/transform slice is explicitly implemented and reconciled.

## Registry plans never claim identity
Registry mapping plans are immutable against the staged batch and a current-registry snapshot. Similar names, TINs, emails, phones, registration numbers, and owner/name pairs are collision signals only; they require review and never create domain records or accepted LegacyIdMapping rows.

## Registry execution requires explicit reversible approval
Execute only exact ready proposal IDs under a stable run reference with dual confirmation, and revalidate the shared projection hashes before writing. Similarity never authorizes identity. Rollback may delete only unchanged targets created by that execution; exact-linked pre-existing records and targets with later changes or dependencies must be preserved or refused.

## Legacy declarations require reconciled identity and exact money
A legacy businessCategory name or matching current name never establishes LineOfBusiness identity; require a versioned accepted reconciliation with decision authority and evidence. Parse only exact non-negative monetary strings into integer cents. Ranges, conflicting gross/revenue values, excluded fees, overrides, and variable mappings remain non-executable evidence and must never trigger fee calculation in migration planning.

## Legacy financial plans preserve evidence without authority
Legacy fee identity requires an accepted reconciliation with municipal decision authority; fee names never establish identity. Convert only persisted exact historical amounts to cents for consistency checks. Overrides, exclusions, edited schedule fees, payment status/processor semantics, collections, and receipt claims remain reviewable or blocked and must not calculate liability or write financial domain records.

## Legacy permit evidence preserves the authority boundary
Permit-evidence planning may inventory exact clearance identity, supporting-file metadata, legacy status, and permit claims, but must not copy objects or write current clearances, documents, permit artifacts, numbers, QR data, issuance, release, or legal effect. Clearance identity requires an accepted reconciliation with decision authority and evidence; labels never establish identity. Hash sensitive storage, filename, actor, and permit identifiers in generated evidence.

## Migration readiness separates rehearsal from cutover
Readiness assessments are immutable, idempotent evidence snapshots and never execute migration or grant authority. Rehearsal requires complete staging, core datasets, resolved errors, and completed zero-exception plans. Cutover additionally requires production provenance, completed reversible executors, verified object transfer, and explicit municipal authorization; until those exist, the gate must fail while retaining redacted diagnostics.
