# Business Lifecycle Map

Discovery date: 2026-08-13

This describes observed and evidenced lifecycles. It does not prescribe replacement architecture.

## Lifecycle 1: Citizen / Business Owner Onboarding

- Actors: citizen applicant, staff user.
- Contractual lifecycle: TOR requires public/business applicant portal, registration, profile management, business information, application submission/tracking.
- Ordinance lifecycle: business operators must identify owner/business information and provide registration/requirements for Mayor's Permit.
- Legacy implementation: citizen registers/logs in, creates or links a business owner profile, creates/selects a business, then submits applications through citizen namespace functions.
- Observed production: staff portal verified; citizen portal not live-verified.
- Starting state: citizen account without linked business owner, or staff-created owner/business records.
- Major steps: register/login -> complete profile -> link/create owner -> create/select business -> submit application.
- Ending state: linked owner/business record and, optionally, Draft or Assessment application.
- Exception paths: citizen without linked owner cannot submit; business must belong to citizen; admin users are blocked from citizen namespace.

## Lifecycle 2: Business Registry Maintenance

- Actors: BPLO/staff, possibly citizen.
- Contractual lifecycle: maintain complete business and owner record.
- Ordinance lifecycle: business must secure permit and provide registration, gross/capital basis, barangay clearance and other requirements.
- Legacy implementation: staff manages business owners and businesses; citizen can create owned business through portal; blacklist fields exist.
- Starting state: no owner/business, or existing record.
- Major steps: create owner -> create business -> capture ownership, registration, address, employees, occupancy, documents -> update as needed.
- Ending state: searchable owner/business registry record.
- Exception paths: blacklisted owner/business may affect processing; source has fields but exact enforcement needs deeper characterization.

## Lifecycle 3: New Permit Application

- Actors: applicant/citizen, BPLO reviewer, assessment officer, approving officer, payment/Treasury staff, clearance offices.
- Contractual lifecycle: Online Business Application -> Assessment -> Evaluation -> Approval -> Payment -> Treasury Collection -> Permit Release -> Reporting.
- Ordinance lifecycle: new businesses secure barangay clearance and Mayor's Permit, pay permit/regulatory fees, and under Section 2C.02 are not subject to initial local business tax.
- Legacy implementation: Draft -> Assessment -> Approval -> Pending Payment -> Released. Application type `New` is supported.
- Observed production: staff dashboard navigation confirms Permit Applications and stage surfaces; mutation workflow not exercised.
- Starting state: business exists.
- Major steps: choose business/owner -> choose application type -> enter LOBs/capital/gross/employees -> submit -> assess fees -> approve -> generate schedule -> record payment -> assign clearances -> complete clearances -> issue/print permit.
- Assessment/tax behavior: fee engine uses major/division/group hierarchy, fee category, constant/range/formula fees, overrides, UOM variables, and surcharge/penalty config.
- Payments: annual, semiannual, quarterly schedules; non-tax fees billed in Section 1, tax split across sections.
- Clearances: source assigns active clearances when Section 1 becomes paid; permit issuance UI is gated until required clearances complete.
- Documents produced: application form, assessment sheet, receipt, Mayor's Permit with QR.
- Ending state: Released application and issued/printable permit.
- Exception paths: draft save, status reversion, payment void, fee override, rejected/refused not fully characterized.
- Disagreement: TOR sequence places Treasury collection before permit release and implies clearances in workflow; source assigns clearances after first payment.

## Lifecycle 4: Renewal Application

- Actors: applicant/citizen, BPLO, assessment, approval, Treasury/payment, clearance offices.
- Contractual lifecycle: renewal is required.
- Ordinance lifecycle: annual renewal and business tax on preceding gross receipts are required; renewal application requirements include basis for taxes/charges and barangay clearance.
- Legacy implementation: application type `Renewal`; same status pipeline as new applications.
- Starting state: existing business/previous permit.
- Major steps: select existing business -> choose Renewal -> enter gross sales and LOB details -> submit -> assess -> approve -> pay -> clearances -> release.
- Assessment/tax behavior: gross sales and fee ranges/formulas drive tax; exact ordinance rates must be extracted into later test cases.
- Ending state: renewed permit/released application.
- Exception paths: late payment surcharge/penalty; deficiency tax based on ITR difference appears in ordinance but not fully discovered in source.
- Gap: no evidence yet that all ordinance renewal-specific tax/deficiency/PIL rules are implemented.

## Lifecycle 5: Additional Line / Additional Application

- Actors: applicant, BPLO, assessment, approval, Treasury/payment.
- Contractual lifecycle: not clearly named in TOR.
- Ordinance lifecycle: multiple lines/businesses may require separate permits or independent reporting depending related/unrelated business rules.
- Legacy implementation: `Additional` application type in citizen and schema.
- Starting state: existing business.
- Major steps: submit Additional application with one or more LOBs -> same assessment/payment/release pipeline.
- Ending state: additional permit/application record.
- Exception paths: unclear if this satisfies ordinance separate permit/license rules.
- Gap: needs owner review whether `Additional` is a required Ipil parity behavior.

## Lifecycle 6: Assessment / Fee Configuration

- Actors: system administrator, assessment officer.
- Contractual lifecycle: electronic assessment based on LGU Revenue Code, local ordinances, fees, line items, exemptions, discounts, surcharges, penalties.
- Ordinance lifecycle: business taxes, Mayor's Permit fees, regulatory fees, PIL, gross receipts, capital investment, surcharge and interest rules.
- Legacy implementation: configurable fee hierarchy: Major -> Division -> Group/LOB; fees can be Constant, Range, or Formula; overrides and exclusions exist.
- Starting state: configured classifications and fees.
- Major steps: maintain major/division/group -> define fee names and fees -> map divisions to groups -> configure formulas/ranges -> configure surcharge/penalty -> assess application LOBs.
- Ending state: calculated fees snapshot and payment schedules.
- Exception paths: overrides, disabled fees, formula errors, schedule regeneration blocked after payments.
- Gap: source supports configuration but does not prove ordinance table completeness.

## Lifecycle 7: Payment / Treasury Collection

- Actors: payment officer, Treasury personnel, applicant.
- Contractual lifecycle: over-the-counter payment, online payment when enabled, official/electronic receipts, Treasury collection, reconciliation, reports.
- Ordinance lifecycle: Municipal Treasurer collects revenues and issues official receipts; late taxes get surcharge and interest.
- Legacy implementation: staff records payments against schedule sections; manual transactions and billing group records support other revenue; receipt number optional.
- Starting state: Pending Payment or payable billing/transaction record.
- Major steps: choose payment schedule/record -> enter amount/method/reference/receipt -> validate amount <= balance -> insert payment -> update schedule -> possibly release application -> produce receipt.
- Ending state: paid/partial/pending schedule, completed payment, revenue visible in reporting.
- Exception paths: void payment -> failed status -> reverse schedule paid amount -> demote Released application to Pending Payment if total paid is zero -> delete clearances.
- Gap: online payment is mock/partial; reconciliation is not fully found.

## Lifecycle 8: Clearance Completion / Permit Issuance

- Actors: clearance office users, BPLO releasing staff.
- Contractual lifecycle: clearances and electronic permit release required.
- Ordinance lifecycle: permit may be denied/refused for noncompliance with zoning, safety, health, unsettled obligations, low declared receipts.
- Legacy implementation: active clearance types are assigned after first payment causes Released status; issue permit button disabled until all clearances complete.
- Starting state: Released application with assigned clearances.
- Major steps: review clearance checklist -> complete required clearances -> issue/print Mayor's Permit -> QR verification available.
- Ending state: permit issued/active.
- Exception paths: incomplete clearance blocks issuance; payment void may delete clearances and demote status.
- Disagreement: source status name `Released` is reached before clearances are complete.

## Lifecycle 9: Billing Groups / Non-Permit Revenue

- Actors: Treasury/admin staff.
- Contractual lifecycle: miscellaneous fees, government stall rental, franchise payments, other Treasury collections; daily collection and abstract reports.
- Ordinance lifecycle: many non-business-permit fees and charges must be collected by Municipal Treasurer.
- Legacy implementation: configurable billing groups with custom fields, fee libraries, line items, record statuses, receipts, and report abstracts.
- Starting state: configured billing group.
- Major steps: define group -> define fields and amount/description mappings -> define fee library -> create transaction/record -> line items -> mark status/payment -> print receipt/report.
- Ending state: billing record included in payments/collection/reporting.
- Exception paths: edit-after-payment requires special permission; void/delete/restore supported.
- Gap: source uses configurable generic module, not explicit dedicated modules for stall rental/franchise.

## Lifecycle 10: Reporting / Collection Monitoring

- Actors: Treasurer, BPLO, LGU officials, report users.
- Contractual lifecycle: business permit reports, Treasury reports, abstract of collection, filtering by date/status/revenue source/taxpayer/etc.
- Ordinance lifecycle: Treasurer keeps rolls, accounting of collections, reports and receipts.
- Legacy implementation: two reporting systems: Convex-native saved report builder and separate ad hoc reporting app over ClickHouse plus Convex fee actions.
- Starting state: operational payments/permits/business data exists.
- Major steps: choose report/template -> set filters -> generate -> inspect virtualized table -> export CSV/PDF.
- Ending state: screen report or downloaded output.
- Exception paths: report exports may be capped on-screen; totals reflect full set; some Convex workflow exports are placeholder.
- Gap: TOR report list is broader than reports proven by source/live.

## Lifecycle 11: Retirement / Closure

- Actors: business owner/applicant, Municipal Treasurer, inspector, Mayor/BPLO.
- Contractual lifecycle: not clearly identified in TOR.
- Ordinance lifecycle: business termination/retirement requires sworn gross sales statement within 30 days, tax settlement, inspection, permit cancellation, and new owner obligations if transferred.
- Legacy implementation: no first-class retirement/closure application found in first pass.
- Observed production: not observed.
- Starting state: operating business seeking closure.
- Gap: likely missing or handled outside system; decision required if rescue must cover ordinance behavior.

## Lifecycle 12: Online Payment Reconciliation

- Actors: applicant, payment gateway/channel, Treasury staff.
- Contractual lifecycle: Assessment -> Payment Gateway -> Treasury Collection -> Receipt -> Daily Collection; record successful, failed, cancelled, refunded transactions.
- Ordinance lifecycle: receipts and collection by Treasurer required.
- Legacy implementation: citizen payment route described as mock/no live gateway; payment statuses exist but gateway reconciliation flow not found.
- Observed production: not tested.
- Gap: likely partial/missing.
