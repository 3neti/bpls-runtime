# BPLS Source Register

This register tracks the canonical evidence governing the Ipil BPLS rescue. Original source files must be preserved unchanged and checksummed before they are used as requirements.

Captured on: 2026-08-13

## Evidence Classes

- Normative legal evidence
- Contractual evidence
- Legacy implementation evidence
- Observed production behavior

## Canonical Sources

| Source ID | Title | Type | Authority / provenance | Date if known | File or URL | Version / baseline | Checksum | Status | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| LEGAL-MRC-001 | Municipal Revenue Code / applicable ordinance | Normative legal evidence | Supplied by project owner from `/Users/rli/Documents/bpls` on 2026-08-13 | Unknown | `docs/sources/legislation/ORDINANCE-NO.-08-656-2023-REVISED-MUNICIPAL-REVENUE-CODE-OF-THE-MUNICIPALITY-OF-IPIL-ZS.pdf` | Original PDF persisted unchanged; PDF 1.7, 83 pages | SHA-256: `08af5f5c2438ca171462cfc1c1ea84d3d8cb5287263f88ec2bd1a4d2678929d3` | Present | Required before discovery can treat fee, tax, penalty, or ordinance-derived behavior as governed evidence. |
| CONTRACT-TOR-001 | Terms of Reference for Online Business Permit and Licensing System with Treasury Integration | Contractual evidence | Supplied by project owner from `/Users/rli/Documents/bpls` on 2026-08-13 | Unknown | `docs/sources/contractual/TERMS-OF-REFERENCE-ONLINE-BUSINESS-PERMIT-AND-LICENSING-SYSTEM-WITH-TREASURY-INTEGRATION-FINAL.pdf` | Original PDF persisted unchanged; PDF 1.7, 29 pages | SHA-256: `2f816d318dd323116c5f43ebbd52d80e6bc1ac34af58f60945a27bc80cd75869` | Present | Principal contractual functional specification. Inspect completely during discovery before drawing architectural conclusions. |
| LEGACY-SOURCE-001 | Previous BPLS source code archive | Legacy implementation evidence | Authoritative archive supplied by project owner from `/Users/rli/Documents/bpls` on 2026-08-13; matching checkout identified at `/Users/rli/XcodeProjects/bpls-system` on 2026-08-16 | Branch `main`; commit `b5a66a6a8b3828ebae9916f4bde1da729b1b9154`; archive entries dated 2026-07-11 | `docs/sources/legacy/bpls-system-main.zip`; repository `git@github.com:iCubed-Solutions-Inc/bpls-system.git` | Archive root `bpls-system-main/`; 905 entries; archive comment exactly matches the checkout commit SHA | SHA-256: `9c90a376a538eccc440c7a887121eb2ec2a12848236bfc389a9691adc232eb4b` | Present | Treat as evidence of implementation behavior. The matching Git baseline resolves the previously missing repository/branch provenance; unrelated local `.DS_Store` worktree drift was not treated as source evidence. Do not treat legacy architecture as a requirement. |
| LIVE-APP-001 | Currently deployed BPLS Portal | Observed production behavior | URL and authorized account supplied by project owner on 2026-08-13; authenticated administrative Convex backup supplied through the authorized deployment owner on 2026-08-17; credentials intentionally not recorded | Access verified 2026-08-13; aggregate scale observed 2026-08-16; production snapshot intake 2026-08-17 | `https://www.ipil-bpls.online/dashboard`; private checksum-bound intake outside Git | Convex production deployment `adjoining-porcupine-740`; backup captured 2026-08-16T22:44:00+08:00; private intake run `prod-convex-20260816-224400` | SHA-256: `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57` | Access verified; production snapshot acquired and staged with exceptions | Production remains evidence, not a playground. The authenticated backup contains 53 tables, 308,038 records, and 34 checksum-verified stored files. Private staging found 258 unresolved declared references and performed no BPLS domain writes. Raw payloads, object identifiers, credentials, and operator identity remain outside Git. |
| REPORTING-ENV-001 | Ad hoc reporting application | Observed production behavior | URL and authorized account supplied by project owner on 2026-08-13; credentials intentionally not recorded | Verified 2026-08-13 | `https://ipil-bpls-ad-2j2jf1jf9-ic-ubed.vercel.app/login` | Browser login verified to non-login URL `https://ipil-bpls-ad-2j2jf1jf9-ic-ubed.vercel.app/dashboard`; title observed: `Ad-Hoc Reports - BPLS`; hosted on Vercel URL | Not applicable | Access verified | Distinct reporting environment exists. Observed report templates include abstract, paid/unpaid masterlists, collectibles, business tax by major, top tax due, taxpayer account card, CMCI LDCS, PLDS, BSP, ANNEX-C DNFBP, and abstract by billing group. |

## Classification Discipline

Use these labels in derived evidence notes and later discovery artifacts:

- FACT
- INFERENCE
- CONTRADICTION
- UNRESOLVED QUESTION
- IMPLEMENTATION DECISION

Do not silently reconcile disagreements among ordinance, TOR, legacy source, and observed production behavior.
