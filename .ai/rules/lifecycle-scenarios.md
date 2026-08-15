---
paths:
  - 'app/LifecycleScenarios/**'
---

# Lifecycle Scenarios

## Bound financial scenario fixtures by policy year
Scenario-only fee rules must use an explicit, non-overlapping application year with both effective_from and effective_until. This keeps lifecycle runs deterministic while still exercising the production fee-rule selector; never bypass policy selection to isolate a scenario.
