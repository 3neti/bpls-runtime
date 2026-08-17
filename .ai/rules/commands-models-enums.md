---
paths:
  - 'app/{Actions,Console/Commands,Models,Enums}/**/*Legacy*Application*.php'
---

# Commands Models Enums

## Quarantine historical release semantics, not application identity
Exact legacy Released is migratable as a historical source assertion only. Materialize it with an explicitly non-operational historical status and preserved source/authority metadata; never create a current Released state or infer validity, issuance, release, legal effect, or policy. Identity certainty remains separate from semantic authority, while contradictory identity and invented facts still fail closed.
