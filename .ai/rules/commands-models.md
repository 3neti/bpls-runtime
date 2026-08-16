---
paths:
  - '{app/Actions/StageLegacyExport.php,app/Console/Commands/StageLegacyExportCommand.php,app/Models/Legacy*.php}'
---

# Commands Models

## Legacy staging never mutates domain records
Legacy exports must enter checksum-verified staging first. Preserve payload hashes, stable source/run identity, validations, and unresolved exceptions; do not create or update BPLS domain records until a separate deterministic mapping/transform slice is explicitly implemented and reconciled.
