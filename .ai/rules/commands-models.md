---
paths:
  - '{app/Actions/StageLegacyExport.php,app/Console/Commands/StageLegacyExportCommand.php,app/Models/Legacy*.php}'
  - '{app/Actions/PlanLegacyRegistryMigration.php,app/Console/Commands/PlanLegacyRegistryMigrationCommand.php,app/Models/LegacyMapping*.php}'
  - '{app/Actions/*LegacyRegistryMigration.php,app/Console/Commands/*LegacyRegistryMigrationCommand.php,app/Models/LegacyMapping*.php}'
---

# Commands Models

## Legacy staging never mutates domain records
Legacy exports must enter checksum-verified staging first. Preserve payload hashes, stable source/run identity, validations, and unresolved exceptions; do not create or update BPLS domain records until a separate deterministic mapping/transform slice is explicitly implemented and reconciled.

## Registry plans never claim identity
Registry mapping plans are immutable against the staged batch and a current-registry snapshot. Similar names, TINs, emails, phones, registration numbers, and owner/name pairs are collision signals only; they require review and never create domain records or accepted LegacyIdMapping rows.

## Registry execution requires explicit reversible approval
Execute only exact ready proposal IDs under a stable run reference with dual confirmation, and revalidate the shared projection hashes before writing. Similarity never authorizes identity. Rollback may delete only unchanged targets created by that execution; exact-linked pre-existing records and targets with later changes or dependencies must be preserved or refused.
