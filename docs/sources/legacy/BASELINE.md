# Legacy Source Baseline

Source ID: `LEGACY-SOURCE-001`

Authoritative evidence: `docs/sources/legacy/bpls-system-main.zip`

Provenance:

- supplied by project owner from `/Users/rli/Documents/bpls`
- captured into repository evidence on 2026-08-13
- repository URL not supplied
- branch not supplied

Archive facts:

- SHA-256: `9c90a376a538eccc440c7a887121eb2ec2a12848236bfc389a9691adc232eb4b`
- root directory: `bpls-system-main/`
- entry count: 905
- uncompressed size reported by ZIP metadata: 7,948,773 bytes
- archive comment: `b5a66a6a8b3828ebae9916f4bde1da729b1b9154`
- archive entry timestamps: 2026-07-11 23:53

Ground Zero inspection only:

- archive contains Next.js application surfaces including `apps/web`, `apps/admin`, and `apps/ad-hoc`
- archive contains Convex backend files under `packages/backend/convex`
- archive contains ClickHouse reporting query files under `packages/clickhouse`
- archive contains Vercel-oriented application evidence through deployed URL and Next.js structure

This archive is evidence of implementation behavior, not a mandate to preserve the legacy architecture.
