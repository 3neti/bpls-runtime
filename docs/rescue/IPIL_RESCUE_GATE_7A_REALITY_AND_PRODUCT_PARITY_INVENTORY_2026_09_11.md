# Ipil Rescue Gate 7A — Local Reality and Product/Report Parity Inventory

Status: **PASS — INVENTORY COMPLETE; HISTORICAL PRODUCT PARITY NOT YET IMPLEMENTED**

Validated: 2026-09-11

Governing authority: [Ipil Rescue and Parity Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md)

Next work: [Prioritized Gate 8 Backlog](IPIL_RESCUE_GATE_8_PRIORITIZED_BACKLOG.md)

## Gate Decision

Gate 7A proves that the completed Gate 6 materialization is intact, private, queryable, and non-operational. It also proves a material product gap: ordinary Laravel screens and reports do not yet project the isolated Ipil history. The operational application, payment, receipt, report, and dashboard surfaces therefore remain empty even though the historical tables contain the complete audited anchors.

This is the intended point to stop and inventory. No history was promoted into operational tables, no source record was corrected, no current policy was run, no UI was redesigned, and no new index was necessary. Gate 8 may implement bounded read-only historical projections from this evidence; it may not make rescued history actionable.

## Validation Binding and Method

| Boundary | Gate 7A value |
| --- | --- |
| Repository HEAD inspected | `798d86a` (`Reconcile BPLO routing completion`) |
| Gate 6 implementation commit in ancestry | `72f316d7a6e3545bf529a652371c2f19e19db303` |
| Database | `bpls_ipil_parity_gate6_20260911` |
| Database identity | `0f178b3d4af1e8d467c11fc35b3f6d7e409f50878be655fdebb62ec74c97b7dd` |
| Corpus | `ipil-20260910t153224z-2ab19c17` |
| Mapping profile | `ipil-rescue-mapping-v1.0.0` |
| Seed plan | `ipil-seed-plan-874d26eb21ed0041` |
| Execution manifest fingerprint | `c906494f1c37a4375d78343037e4c5a443986aba9df65217ab4efa4fdbfb64b6` |
| Live Ipil access | None |
| Reseed | None |
| Database corrections | None |

The ordinary Herd-served BPLS was temporarily connected to the named local PostgreSQL database through the ignored local environment only. The environment was restored after browser validation. A Management preview actor was used to reach normal staff routes without creating an account or importing a specimen. Browser checks exercised the dashboard, work inbox, Applications, payment schedules, receipts, report catalog, all 17 report entries, representative filters, and a representative CSV download action.

Legacy comparison uses the committed [Surface Inventory](../discovery/SURFACE_INVENTORY.md) and [Legacy Visual-Parity Comparison Framework](../agents/LEGACY_VISUAL_PARITY_COMPARISON.md). Gate 7A did not access Ipil Cloud or the live Ipil site. Matched current-Ipil screenshots with role/state provenance are still absent, so visual resemblance and interaction parity remain `DEFER`; source and route evidence is sufficient only for semantic inventory.

## Before-State Audit

`ipil:audit` passed all 62 checks before browser validation:

- corpus, profile, plan, and manifest bindings matched;
- two completed imports remained recorded;
- the second import created zero source-derived rows;
- execution remained offline and read-only with no live-source access;
- current `Price` was not invoked and domain writes remained false;
- completed-payment and schedule-paid anchors both equalled **PHP 93,295,317.20**.

## Quantitative Reality

| Dimension | Gate 6 anchor | Gate 7A observed |
| --- | ---: | ---: |
| Historical owners | 3,194 | 3,194 |
| Historical businesses | 3,212 | 3,212 |
| Historical Applications | 3,137 | 3,137 |
| Renewal / New / Additional | 2,621 / 461 / 55 | 2,621 / 461 / 55 |
| Released / Assessment / Pending Payment / Draft | 2,907 / 199 / 28 / 3 | 2,907 / 199 / 28 / 3 |
| Historical classifications | 4,236 | 4,236 |
| Historical payment schedules | 7,648 | 7,648 |
| Historical payments | 5,874 | 5,874 |
| Historical receipt claims | 5,873 | 5,873 |
| Historical permit claims | 2,766 | 2,766 |
| Historical clearance claims | 14,615 | 14,615 |
| Completed-payment total | PHP 93,295,317.20 | PHP 93,295,317.20 |
| Schedule-paid total | PHP 93,295,317.20 | PHP 93,295,317.20 |
| Associated / unresolved media | 16 / 19 | 16 / 19 |
| Verified private Spatie copies | 16 | 16 |

The historical population spans 2025 (9 Applications) and 2026 (3,128 Applications). It contains 28 distinct literal business-barangay values, with 14 businesses missing a literal barangay. Classification evidence contains 562 distinct source literals and five unresolved normalized candidates. These are evidence facts, not PSGC or LOB acceptance.

Known exceptions remain visible and unchanged: 69 schedules lack an Application, three payments lack an Application, 56 payments lack a schedule, 15 permit claims lack an Application, ten permit claims have a broken Business edge, ten have a broken Owner edge, and 110 clearance claims have a broken clearance-type edge.

## Operational Isolation

| Safety check | Result |
| --- | ---: |
| Historical Applications with `operationally_eligible=true` | 0 |
| Historical Applications with `can_continue=true` | 0 |
| Users created by the rescue imports | 0 |
| Operational Businesses / Owners | 0 / 0 |
| Operational Applications | 0 |
| Operational payment schedules / collections / receipts | 0 / 0 / 0 |
| Operational clearances / application documents | 0 / 0 |

No ordinary historical route exists. Consequently there is no route from a rescued record to assessment, collection, receipt issuance, clearance completion, permit issuance/release, notification, or Nelson lifecycle action. The absence of a historical presentation route is a product gap, but it is also why Gate 7A found no accidental operational controls on history.

## Ordinary Product Reality

| Surface or task | Existing Ipil evidence | Laravel reality against Gate 6 | Disposition | Gate 7A conclusion |
| --- | --- | --- | --- | --- |
| Staff dashboard | Legacy `/dashboard` is observed | Role/task launch surface works; no rescued counts, activity, or continuity | ADAPT | Retain Laravel role clarity, add a clearly separate historical summary in Gate 8 |
| Owner registry | Legacy owner list/new/detail routes exist | No ordinary staff Owner registry route; 3,194 records are database-only | DEFER | Requires a read-only historical Owner index/detail |
| Business registry | Legacy business list/new/detail routes exist | No ordinary staff Business registry route; 3,212 records are database-only | DEFER | Requires a read-only historical Business index/detail |
| Applications list | Legacy all/stage queues exist | Operational list works but shows zero; `Historical Evidence` filter does not query Gate 6 history | ADAPT | Keep operational queue separate; add an explicit historical mode/surface |
| Business/Owner/Application search | Legacy registries and application list support retrieval work | Current application search covers operational application, tracking, business, registration, and owner fields only | DEFER | Historical cross-entity search is not exposed |
| Year/type/barangay/status/classification filters | Legacy route and reporting evidence supports these staff dimensions | Ordinary Application screen exposes only free text and operational status | DEFER | Add evidence-literal historical filters without accepting PSGC/LOB mappings |
| Sorting and pagination | Legacy list routes imply list navigation; exact behavior is not visually captured | Operational Applications paginate 15; no historical rows can exercise sort/page behavior | DEFER | Gate 8 must specify stable historical sort keys and page behavior |
| Business history | Legacy business detail and taxpayer-card intent show longitudinal retrieval | No historical Business detail route | DEFER | Build one read-only history projection across Applications and evidence |
| Historical Application | Legacy detail exposes owner, business, type, LOB, fees, schedules, clearances, and documents | No historical Application route | DEFER | Build a claim-labelled, non-actionable detail projection |
| Schedules and payments | Legacy payment/billing surface exists | Ordinary schedules query current schedules and return zero | ADAPT | Reuse information hierarchy but source a separate historical read model |
| Receipt claims and OR search | Legacy payment entry and report filters use receipt numbers | Ordinary receipts query current receipts and return zero | ADAPT | Historical OR lookup must retain duplicate-claim warnings and claim semantics |
| Permit claims | Legacy permit list/detail routes exist | Laravel has no historical permit index/detail; operational permit authority remains separate | DEFER | Present source permit claims, never current issuance or validity |
| Clearances | Legacy clearance administration and Application detail exist | Current clearances are embedded in operational Applications; 14,615 historical claims are invisible | ADAPT | Present claim name/completion plus broken-type evidence without current completion controls |
| Uploaded documents | Legacy Business detail links source evidence | Sixteen verified copies and 19 unresolved objects have no staff historical presentation | DEFER | Add authorized private view/download only for deterministically associated evidence |
| Dashboard/report exports | Legacy provides CSV/PDF and saved-report workflows | Representative Laravel CSV download action dispatches, but exports zero operational rows | ADAPT | Historical export needs a separate privacy/audit contract and explicit columns |
| Historical non-operational boundary | Legacy statuses describe what the source asserted | Laravel preserves status literally while keeping every record ineligible and non-continuable | MATCH | Preserve this boundary unchanged |
| Missing/orphan evidence | Source contains broken and unresolved relationships | Laravel retains explicit flags and does not fabricate edges | MATCH | Surface the flags rather than repairing them |
| Broad visual parity | Current-Ipil matched screenshots are not registered | Laravel screens were exercised, but no evidence-valid side-by-side exists | DEFER | Capture source screenshots systematically before visual adaptation |

`MATCH`, `ADAPT`, `IMPROVE`, and `DEFER` are the only dispositions in this inventory. `IMPROVE` is reserved below for already implemented, evidence-safe clarity; it does not authorize redesign.

## Realistic Staff Search Validation

Browser searches used non-taxpayer, format-realistic probes. They confirmed that Application reference/status, payment-schedule reference, OR, report year/type/business, and OR-range controls submit normally, but all resolve only against empty operational tables. Real taxpayer identifiers were not placed in URLs, screenshots, logs, or Git.

The historical data layer was separately probed inside a read-only PostgreSQL transaction using representative records without emitting their values:

| Search or navigation shape | Local execution | Index/readiness finding | Product exposure |
| --- | ---: | --- | --- |
| Exact Business name | 0.622 ms | Existing name index used | None |
| Partial Business name | 4.073 ms | Full 3,212-row name-index scan; acceptable at current volume | None |
| Partial Owner name | 4.144 ms | Full 3,194-row name-index scan; acceptable at current volume | None |
| Exact Application reference | 0.376 ms | Existing reference index used | None |
| Exact permit number | 0.554 ms | Existing permit-number index used | None |
| Exact OR claim via normalized hash | 0.034 ms | Existing privacy-preserving hash index used | None |
| Year + transaction type + legacy status | 0.021 ms | Existing composite evidence index is sufficient | None |
| Literal barangay + Business-name order | 0.248 ms | Existing indexes are sufficient at current volume | None |
| Historical classification candidate | 0.325 ms | Existing candidate index used | None |
| Joined Owner/Business/Application page, offset 2,500 | 20.288 ms | Bounded local sort; acceptable for Gate 8 design | None |

These are warm local observations, not production service-level guarantees. They justify **no Gate 7A index change**. Gate 8 should first implement the actual query shapes, then re-run plans under realistic concurrent use before adding indexes.

## Report and Export Parity Inventory

The Laravel catalog renders 17 entries: ten labelled Available and seven labelled Awaiting confirmation/Review. “Available” currently means available for the operational domain, not available over rescued history.

| Existing Ipil report intent | Laravel surface | Disposition | Historical parity finding |
| --- | --- | --- | --- |
| Report gallery / saved report builder | Report Templates catalog | ADAPT | Curated catalog is clearer, but saved reports and rescued exports are not projected |
| All Abstract Report | All Abstract of Collection | DEFER | Correctly refuses official output pending complete Treasury coverage/rules |
| Abstract by Billing Group | Billing Group Abstract | DEFER | 25,372 records, 45,413 lines, and 225 fee rows are preserved separately; four print layouts remain deferred |
| Paid Establishment Masterlist | Paid Establishments | ADAPT | Filters and CSV exist, but 5,874 historical payments do not feed the report |
| Unpaid Establishment Masterlist | Unpaid Establishments | ADAPT | Current schedule semantics are guarded; historical pending/partial evidence is absent |
| Breakdown of Collectibles | Breakdown of Collectibles | ADAPT | Layout intent is present; historical schedules and orphan flags are absent |
| Business Tax by Major | Business Tax on Major Type | ADAPT | OR/date filters exist; accepted historical major/LOB identity does not |
| Top 100 Establishments Tax Due | Top Tax Due | ADAPT | Correctly avoids declaring delinquency; historical assessment evidence is not projected |
| Taxpayer Account Card | Taxpayer Account Card | DEFER | Template-only refusal is correct; longitudinal historical account projection is absent |
| Total Capital and Gross Summary | Total Capital and Gross Summary | ADAPT | Current declaration/receipt view exists; historical exact evidence is absent |
| CMCI LDCS Annex B | CMCI LDCS Annex B | DEFER | Correctly refuses rows/export pending permit/classification/municipal authority |
| PLDS | PLDS | DEFER | Correctly refuses rows/export pending permit authority and required facts |
| BSP Non-Bank Entities | BSP Non-Bank Entities | DEFER | Correctly refuses rows/export pending regulated classification and permit authority |
| ANNEX C – DNFBP | ANNEX C – DNFBP | DEFER | Correctly refuses rows/export pending DNFBP classification and authority |
| Daily collection detail | Daily Collections | IMPROVE | Explicit receipt/date/cashier semantics improve clarity, but history is not included |
| Revenue-source detail | Revenue Sources | IMPROVE | Persisted allocation semantics are explicit, but history lacks an accepted join |
| Assessment/payment reconciliation | Assessment Summary / Payment Summary | IMPROVE | Separates persisted assessment and schedule evidence without recalculation; history remains absent |

Eleven rescued report-export records and their eleven media objects remain preserved as source evidence. They are not treated as regenerated Laravel reports or proof of current report totals. The 71,032 billing/report rows remain a separate evidence class: 25,372 billing-group records, 45,413 line items, 225 fee rows, 12 field definitions, five fee-field mappings, five group definitions, and four deferred print layouts. No deterministic edge currently joins that estate to permit finance, so Gate 8 must not manufacture one.

## Browser Evidence

The following local routes rendered successfully against the Gate 6 database: dashboard, work inbox, Applications, payment schedules, receipts, report catalog, Daily Collections, Paid/Unpaid Establishments, Collectibles, Business Tax by Major, Top Tax Due, Taxpayer Account Card, Total Capital and Gross, Payment Summary, Revenue Sources, All Abstract, CMCI, PLDS, BSP, and Annex C.

Representative filter submissions preserved their query intent. The report CSV action triggered a browser download. No application-origin console error was observed; captured warnings came from installed browser extensions rather than `bpls-runtime.test`.

No Business history, Owner history, historical Application detail, historical permit, historical clearance, or historical document route could be exercised because those routes do not exist. This is reported as `DEFER`, not as browser-verified parity.

## Gate 7A Acceptance

- **Local reality validated:** yes; ordinary surfaces were exercised against the exact Gate 6 PostgreSQL database.
- **Quantitative anchors preserved:** yes; all commissioned counts and PHP 93,295,317.20 anchors remain exact.
- **History remains read-only and non-operational:** yes; zero eligibility/continuation and zero operational leakage.
- **Search/index readiness characterized:** yes; no Gate 7A index change required.
- **Product parity implemented:** no; rescued history is not exposed by ordinary screens.
- **Report parity implemented:** no; current operational reports do not project historical evidence.
- **Visual parity proven:** no; matched current-Ipil screenshot evidence remains outstanding.
- **Safe to proceed to Gate 8:** yes, but only for the prioritized read-only projection backlog.

**GATE 7A: PASS — REALITY INVENTORIED; GATE 8 READ-ONLY HISTORICAL PARITY IS THE NEXT FRONTIER**
