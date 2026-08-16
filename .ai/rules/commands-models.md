---
paths:
  - '{app/Actions/StageLegacyExport.php,app/Console/Commands/StageLegacyExportCommand.php,app/Models/Legacy*.php}'
  - '{app/Actions/PlanLegacyRegistryMigration.php,app/Console/Commands/PlanLegacyRegistryMigrationCommand.php,app/Models/LegacyMapping*.php}'
---

# Commands Models

## Legacy staging never mutates domain records
Legacy exports must enter checksum-verified staging first. Preserve payload hashes, stable source/run identity, validations, and unresolved exceptions; do not create or update BPLS domain records until a separate deterministic mapping/transform slice is explicitly implemented and reconciled.

## Registry plans never claim identity
Registry mapping plans are immutable against the staged batch and a current-registry snapshot. Similar names, TINs, emails, phones, registration numbers, and owner/name pairs are collision signals only; they require review and never create domain records or accepted LegacyIdMapping rows.
