---
paths:
  - '{app/Actions/StageLegacyExport.php,app/Actions/ValidateStagedLegacyDatasets.php,app/Console/Commands/StageLegacyExportCommand.php}'
---

# Commands

## Legacy references are declared, never inferred
Cross-dataset integrity checks come from the immutable staging manifest and resolve only against records in the same import batch. Preserve field/type/count evidence and hash unresolved values; never infer relationships from field names or create ID mappings during validation.
