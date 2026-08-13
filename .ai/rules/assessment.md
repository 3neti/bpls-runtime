---
paths:
  - 'app/Assessment/**'
---

# Assessment

## Single assessment calculation path
Assessment calculations must flow through the assessment boundary and persist immutable line snapshots. Do not add alternate fee/tax calculation paths, and throw explicit policy exceptions for uncharacterized formula, rate, rounding, receipt, or reconciliation behavior instead of guessing.
