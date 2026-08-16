---
paths:
  - 'app/Actions/*CitizenPermitApplication*.php'
  - 'app/Actions/*Legacy*Document*.php'
---

# Actions

## Citizen draft and submission boundary
Citizen drafts are status=draft, submitted_at=null, and officially unnumbered. Formal submission records separate citizen-submitted and municipality-received facts (currently atomically), enters assessment processing, and must not assign an official number, decide documentary sufficiency, calculate assessment, or create payment behavior.

## Legacy document objects require verified scope and bytes
Legacy business documents may enter permit applications only through an operator-authorized reconciliation to one exact accepted application mapping. Verify checksum, size, and MIME before staging, execution, readiness, and rollback. Legacy document status remains observational and must not assert documentary sufficiency or permit authority.
