# Surface Inventory

Discovery date: 2026-08-13

This inventory records externally meaningful surfaces. It is not an architecture plan.

## Applications / Entry Points

| ID | Surface | Evidence | Status | Notes |
| --- | --- | --- | --- | --- |
| SURF-001 | Staff BPLS Portal | `LIVE-APP-001`, `LEGACY-SOURCE-001` | Observed live | `https://www.ipil-bpls.online/dashboard`, title `BPLS Portal`. |
| SURF-002 | Citizen portal | `LEGACY-SOURCE-001`, `CONTRACT-TOR-001` | Source implemented, not live-observed | Source app `apps/web`. |
| SURF-003 | Ad hoc reporting app | `REPORTING-ENV-001`, `LEGACY-SOURCE-001` | Observed live | Separate Vercel URL, title `Ad-Hoc Reports - BPLS`. |
| SURF-004 | Public permit verification | `LEGACY-SOURCE-001`, `CONTRACT-TOR-001` | Source implemented, not live-observed | Route `/permits/verify/[id]`. |

## Staff Portal Routes

Source: `LEGACY-SOURCE-001`, `apps/admin/app`.

| Route | Surface | Evidence state |
| --- | --- | --- |
| `/login` | Staff login | Implemented; live login verified |
| `/admin/onboarding` | First-run setup | Implemented |
| `/dashboard` | Staff dashboard | Implemented; live observed |
| `/dashboard/account` | Current-user profile/password | Implemented |
| `/dashboard/activity` | Audit/activity log | Implemented; live nav observed |
| `/dashboard/business-owners`, `/new`, `/[id]` | Business owner registry | Implemented; live nav observed |
| `/dashboard/businesses`, `/new`, `/[id]` | Business registry | Implemented; live nav observed |
| `/dashboard/business-categories` | Category/subcategory taxonomy | Implemented |
| `/dashboard/permit-applications` | All applications | Implemented; live nav observed |
| `/dashboard/permit-applications/new` | Staff-created application | Implemented |
| `/dashboard/permit-applications/drafts` | Draft applications | Implemented |
| `/dashboard/permit-applications/assessment`, `/assessment/[id]` | Assessment queue/detail | Implemented |
| `/dashboard/permit-applications/approval`, `/approval/[id]` | Approval queue/detail | Implemented |
| `/dashboard/permit-applications/payment`, `/payment/[id]` | Pending payment queue/detail | Implemented |
| `/dashboard/permit-applications/released`, `/released/[id]` | Released/releasing queue/detail | Implemented |
| `/dashboard/permits`, `/permits/[id]` | Issued permits | Implemented; live nav observed |
| `/dashboard/clearances` | Clearance type administration | Implemented; live nav observed |
| `/dashboard/payments-billing` | Permit payments, billing records, manual transactions | Implemented; live nav observed |
| `/dashboard/billing-groups`, `/billing-groups/[id]` | Configurable non-permit billing | Implemented; live nav observed |
| `/dashboard/reports`, `/reports/new`, `/reports/[id]` | Convex-native report builder | Implemented; live nav observed |
| `/dashboard/locations` | Province/city/barangay references | Implemented |
| `/dashboard/taxes-and-fees` | Fee configuration landing | Implemented; live nav observed |
| `/dashboard/taxes-and-fees/major`, `/major/[id]` | Major classifications | Implemented |
| `/dashboard/taxes-and-fees/division`, `/division/[id]` | Division classifications | Implemented |
| `/dashboard/taxes-and-fees/line-of-business`, `/line-of-business/[id]` | Groups / lines of business | Implemented |
| `/dashboard/taxes-and-fees/fees` | Fee definitions | Implemented |
| `/dashboard/taxes-and-fees/surcharge-penalty` | Surcharge/penalty formulas | Implemented |
| `/dashboard/user-management` | Staff users | Implemented; live nav observed |
| `/dashboard/user-management/roles` | Role/permission matrix | Implemented |
| `/dashboard/settings` | Settings landing | Implemented |
| `/dashboard/settings/platform-configuration` | System name, description, permit formats/expiry | Implemented |
| `/dashboard/settings/municipality` | Municipality profile | Implemented |
| `/dashboard/settings/officials` | Officials/signatories | Implemented |
| `/dashboard/settings/branding` | Logos/seal/colors | Implemented |
| `/dashboard/settings/receipting` | Receipt layout | Implemented |
| `/dashboard/settings/permit-layout` | Permit layout | Implemented |

## Citizen Portal Routes

Source: `LEGACY-SOURCE-001`, `apps/web/app`.

| Route | Surface | Evidence state |
| --- | --- | --- |
| `/` | Public landing page | Implemented |
| `/register` | Citizen registration | Implemented |
| `/login` | Citizen login | Implemented |
| `/portal` | Citizen dashboard | Implemented |
| `/portal/profile` | Profile and business-owner linking/creation | Implemented |
| `/portal/applications` | Citizen's applications | Implemented |
| `/portal/applications/new` | Citizen application wizard | Implemented |
| `/portal/applications/[id]` | Citizen application detail/tracking | Implemented |
| `/portal/payments/[id]` | Citizen payment detail/mock payment | Partial; source states no live gateway |

## Reporting Routes

Source: `LEGACY-SOURCE-001`, `apps/ad-hoc/app`; live high-level nav verified by `REPORTING-ENV-001`.

| Route | Report |
| --- | --- |
| `/dashboard` | Report template gallery |
| `/dashboard/reports/all-abstract` | All Abstract Report |
| `/dashboard/reports/abstract/[billingGroupId]` | Abstract by Billing Group |
| `/dashboard/reports/masterlist-paid` | Paid Establishment Masterlist |
| `/dashboard/reports/masterlist-unpaid` | Unpaid Establishment Masterlist |
| `/dashboard/reports/collectibles` | Breakdown of Collectibles |
| `/dashboard/reports/business-tax-major` | Business Tax by Major |
| `/dashboard/reports/top-establishments-tax-due` | Top 100 Establishments Tax Due |
| `/dashboard/reports/taxpayer-card` | Taxpayer Account Card list |
| `/dashboard/reports/taxpayer-card/[businessId]` | Taxpayer Account Card detail |
| `/dashboard/reports/capital-gross-summary` | Total Capital and Gross Summary |
| `/dashboard/reports/cmci-ldcs` | CMCI LDCS |
| `/dashboard/reports/plds` | PLDS |
| `/dashboard/reports/bsp` | BSP |
| `/dashboard/reports/annex-c-dnfbp` | ANNEX-C DNFBP |

## Main Forms / Fields

Source: `LEGACY-SOURCE-001`; TOR and ordinance require broader field coverage.

- Business owner: first/middle/last name, birth date, civil status, gender, citizenship, TIN, mobile, email, address, province/city/barangay, owner type, group name, blacklist fields.
- Business: name, owner, scale, annual revenue, established/date started, registration number/date, address/building, province/city/barangay, ownership type, company/group name, occupancy, payment cadence, employees by gender, area, contact, email, documents.
- Citizen application: business, application type (`New`, `Renewal`, `Additional`), LOB lines, capital investment, gross sales, employees, payment mode.
- Permit detail: owner/business cards, transaction type, LOB configuration, UOM variables, fees summary, schedule of payments, clearances checklist, certificates/documents.
- Fee definitions: application type, category, constant/range/formula type, range field, ranges, formula, status, overrides.
- Billing groups: group name/description, dynamic fields, amount/description field mapping, fee library, line items, receipt layout.
- Payment recording: schedule, amount, method, reference number, receipt number, remarks, processed-by.
- Settings: platform name/description, permit number format/expiry, municipality, officials, branding assets, receipt layout, permit layout.

## Authorization Surface

Source: `LEGACY-SOURCE-001`.

Roles include system roles `Admin`, `BPLO`, and `Treasury`; custom roles are supported. Permission resources include permit applications and sub-stages, permits, business owners, businesses, billing groups and sub-resources, payments/billing, clearances, users, roles, taxes/fees and sub-resources, locations, business categories, settings and sub-resources, reports, and activity logs.

## Documents / Generated Outputs

- Business application form PDF.
- Assessment sheet PDF.
- Mayor's Permit PDF with QR verification.
- Payment receipt PDF.
- Template-based receipt documents.
- Billing group receipt PDF.
- CSV/PDF exports from ad hoc reporting app.
- CSV exports from Convex-native report workflow, with known partial coverage.

## Visible Integrations

- Convex backend/system of record in legacy source.
- ClickHouse reporting warehouse in legacy source.
- Airbyte sync referenced in legacy README but not configured in source.
- Vercel-hosted ad hoc reporting environment observed live.
- QR code permit verification in source.
- No live payment gateway found; citizen payment described as mock in source.
- No SMS/email implementation confirmed in source.
