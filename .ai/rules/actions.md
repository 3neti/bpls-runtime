---
paths:
  - 'app/Actions/*CitizenPermitApplication*.php'
---

# Actions

## Citizen draft and submission boundary
Citizen drafts are status=draft, submitted_at=null, and officially unnumbered. Formal submission records separate citizen-submitted and municipality-received facts (currently atomically), enters assessment processing, and must not assign an official number, decide documentary sufficiency, calculate assessment, or create payment behavior.
