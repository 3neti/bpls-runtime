# Live Application Access Metadata

Credentials are intentionally not recorded in this repository.

## BPLS Portal

Source ID: `LIVE-APP-001`

- URL supplied: `https://www.ipil-bpls.online/dashboard`
- verification date: 2026-08-13
- status: access verified
- conservative check performed: login to supplied account, confirm non-login dashboard URL, read visible high-level navigation text, check recent console error count
- resulting URL: `https://www.ipil-bpls.online/dashboard`
- observed title: `BPLS Portal`
- observed console errors during check: 0

Read-only aggregate scale observation on 2026-08-16:

- business owners: 3,163 total owners
- businesses: 3,188 total businesses
- permit applications: 3,065 total applications
- permits: 2,709 total permits
- Payments & Billing: 29,113 unified transactions, of which 28,643 were displayed as completed payments
- clearance configuration: 5 clearance types
- method: authenticated navigation to existing list surfaces and reading their visible pagination/summary totals; no search, mutation, export, or record download was performed
- limitation: the unified transaction total is not asserted to equal the Convex `payments` table count; payment-schedule, clearance-assignment, nested line-of-business, document-object, deleted-record, and historical-version counts were not exposed by these surfaces and remain unknown
- privacy: no row-level production values, credentials, cookies, or session artifacts were persisted

Read-only deployment/database boundary observation on 2026-08-16:

- the public live login response references Convex deployment `adjoining-porcupine-740`, matching the production endpoint committed in the exact legacy source baseline
- the legacy source contains a dedicated query `dataExport:getPermitReportCount`; one bounded unauthenticated read-only invocation against that deployment returned 3,065 non-deleted applications, matching the authenticated UI total
- no report rows, personal data, financial values, stored objects, authentication material, or full database snapshot were requested or persisted
- the workstation has no Convex CLI account/deploy-key configuration, so an authoritative full snapshot cannot be acquired from this checkout alone
- the public query proves bounded database visibility, not administrative database ownership, complete export access, migration parity, or cutover authority

Legacy report-query security characterization on 2026-08-16:

- exact source module: `packages/backend/convex/dataExport.ts` at legacy baseline `b5a66a6a8b3828ebae9916f4bde1da729b1b9154`
- `getPermitReportCount` and `generatePermitReport` are declared as public Convex queries with no identity, role, or permission check in either handler
- source shows `generatePermitReport` reads nine complete operational tables per request before applying application pagination and may return owner/business names, application and receipt identifiers, classifications, fee details, amounts, payment dates, and payment status
- `generatePermitReport` was not invoked; no sensitive report row was requested, printed, persisted, or committed
- Board boundary: this endpoint is legacy security evidence only and must not become a migration source; further production invocation requires a specific explicit observation authorization
- required owner action: restrict/internalize the legacy query and review exposure independently; no production remediation was attempted by this rescue
- canonical migration path remains an authenticated administrative Convex snapshot/export with deployment identity, operator, timestamp, checksum, immutable private intake, staging, reconciliation, and rehearsal

Observed high-level surfaces:

- Dashboard
- Permit Applications
- Permits
- Business Owners
- Business Information
- Payments & Billing
- Billing Groups
- Reports
- Clearances
- Activity
- User Management
- Taxes & Fees

## Ad Hoc Reporting Application

Source ID: `REPORTING-ENV-001`

- URL supplied: `https://ipil-bpls-ad-2j2jf1jf9-ic-ubed.vercel.app/login`
- verification date: 2026-08-13
- status: access verified
- conservative check performed: login to supplied account, confirm non-login dashboard URL, read visible high-level report-template text, check recent console error count
- resulting URL: `https://ipil-bpls-ad-2j2jf1jf9-ic-ubed.vercel.app/dashboard`
- observed title: `Ad-Hoc Reports - BPLS`
- observed console errors during check: 0

Observed report-template surfaces:

- All Abstract Report
- Paid Masterlist
- Unpaid Masterlist
- Collectibles
- Business Tax by Major
- Top 100 Tax Due
- Taxpayer Account Card
- CMCI LDCS
- PLDS Template
- BSP Report
- ANNEX-C DNFBP
- ABSTRACT BY BILLING GROUP

No reports were generated or downloaded during Ground Zero verification.
