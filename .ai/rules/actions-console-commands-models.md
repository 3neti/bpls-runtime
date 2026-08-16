---
paths:
  - 'app/{Actions,Console/Commands,Models}/**/*Legacy*Application*.php'
  - 'app/{Actions,Console/Commands,Models}/**/*Legacy*Declaration*.php'
  - 'app/{Actions,Console/Commands,Models}/**/*Legacy*Financial*.php'
  - 'app/{Actions,Console/Commands,Models}/**/*Legacy*Permit*Evidence*.php'
---

# Actions Console Commands Models

## Execute only exact ready application migration proposals
Permit-application migration execution is local/testing-only, requires exact ready proposal IDs plus a stable run reference and dual command confirmation, and revalidates source projection, accepted owner/business mappings, ownership, and the plan dependency snapshot. Created applications remain officially unnumbered, infer no submitting actor, and create no downstream records. Rollback may delete only unchanged execution-created applications with no downstream dependencies; exact-linked pre-existing applications are preserved.

## Execute declarations as complete reversible application sets
Declaration migration execution is local/testing-only and requires every proposal for each selected legacy application to be selected and ready. Revalidate the staged projection, accepted application mapping, accepted line-of-business reconciliation, and dependency snapshot; refuse unmanaged existing lines. Persist declared facts only and never calculate assessments. Rollback may delete only unchanged execution-created lines without assessment dependencies.

## Execute only annual unpaid legacy snapshots
Financial migration execution is local/testing-only and accepts complete exact ready sets for one annual, single-section, unpaid schedule with application-scoped reconciled fees. It converts persisted amounts to immutable assessment/schedule snapshots without calculating liability, inferring payment state, creating collections/receipts, or changing application lifecycle. Installments, payment evidence, edited fees, overrides, and unmanaged targets remain non-executable; rollback requires an unchanged target graph with no collections.

## Execute only reconciled pending legacy clearances
Legacy permit-evidence execution is local/testing-only and accepts exact Ready `clearance` proposals only when `isCompleted=false`, one accepted application mapping exists, and the clearance reconciliation records decision authority and evidence. Never execute supporting documents without checksum-verified object transfer/application scope, or permit/released/completed-clearance claims without separately accepted authority. Rollback deletes only unchanged execution-created pending clearances.
