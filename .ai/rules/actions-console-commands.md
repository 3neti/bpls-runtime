---
paths:
  - 'app/{Actions,Console/Commands}/**/*Legacy*Scale*.php'
---

# Actions Console Commands

## Scale rehearsals separate observation from assumption
Production-shaped scale fixtures contain deterministic synthetic records only. Record exact live aggregate observations separately from synthetic topology/load assumptions, invoke existing staging and planning actions, and never claim production export, migration parity, or cutover authority from a scale result.
