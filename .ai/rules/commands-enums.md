---
paths:
  - 'app/{Actions,Console/Commands,Enums}/**/*Legacy*Financial*.php'
---

# Commands Enums

## Historical finance classification does not authorize migration
Classify persisted financial evidence without recalculating liability or changing proposal status. Exact historical amounts with incomplete fee-policy provenance may be preserved as historical evidence, but only proposals already marked Ready by the accepted mapping contract are rehearsal-eligible. Classification never authorizes migration or cutover.
