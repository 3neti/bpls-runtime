---
paths:
  - 'app/{Actions,Console/Commands}/**/*Legacy*Scale*.php'
  - 'app/{Actions,Console/Commands}/**/*Legacy*Convex*Snapshot*.php'
  - 'app/{Actions,Console/Commands}/**/*Legacy*Convex*.php'
---

# Actions Console Commands

## Scale rehearsals separate observation from assumption
Production-shaped scale fixtures contain deterministic synthetic records only. Record exact live aggregate observations separately from synthetic topology/load assumptions, invoke existing staging and planning actions, and never claim production export, migration parity, or cutover authority from a scale result.

## Convex snapshots enter private immutable intake
Operator-supplied production Convex snapshots are local/testing-only, require dual confirmation, and are copied checksum-verified into private storage. Intake may extract declared table documents and generate the existing staging manifest, but must not download production, expose payloads, infer undeclared relationships, stage automatically, execute migration, or grant cutover authority.

## Public legacy report query is not migration authority
The deployed legacy dataExport queries lack an application authorization check and may expose personal/financial report rows. Do not invoke them for migration acquisition or treat accidental accessibility as authority. Production migration evidence must come from an authenticated administrative Convex snapshot with deployment, operator, timestamp, and checksum provenance.
