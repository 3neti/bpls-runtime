---
paths:
  - 'app/{Actions,Console/Commands,Models}/**/*Legacy*Application*.php'
---

# Actions Console Commands Models

## Execute only exact ready application migration proposals
Permit-application migration execution is local/testing-only, requires exact ready proposal IDs plus a stable run reference and dual command confirmation, and revalidates source projection, accepted owner/business mappings, ownership, and the plan dependency snapshot. Created applications remain officially unnumbered, infer no submitting actor, and create no downstream records. Rollback may delete only unchanged execution-created applications with no downstream dependencies; exact-linked pre-existing applications are preserved.
