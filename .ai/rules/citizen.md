---
paths:
  - 'app/{Actions,Http/Controllers/Citizen}/**'
---

# Citizen

## Permit intake cannot mutate registry identity
Permit application editing may change application-specific declarations but must not silently update shared BusinessOwner or Business registry facts. Registry maintenance requires a separate authorized and audited behavior.
