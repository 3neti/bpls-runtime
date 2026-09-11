# Ipil Rescue Gate 8A — Historical Read Surface MVP Acceptance

Status: **PASS — READY FOR BOUNDED PRIVATE HISTORICAL UAT (GATE 8C)**

Executed: 2026-09-11

Starting repository SHA: `c00df4998c228bfacca1ec4fe4a0aaeb12d21db5`

Local evidence database: `bpls_ipil_parity_gate6_20260911`

Governing authority: [Ipil Rescue and Parity Compass](../agents/IPIL_RESCUE_AND_PARITY_COMPASS.md), [Gate 7A Reality and Product/Report Parity Inventory](IPIL_RESCUE_GATE_7A_REALITY_AND_PRODUCT_PARITY_INVENTORY_2026_09_11.md), and [Gate 8 Prioritized Read-Only Historical Parity Backlog](IPIL_RESCUE_GATE_8_PRIORITIZED_BACKLOG.md).

## Outcome

Gate 8A adds an ordinary staff-facing historical registry without converting rescued evidence into current municipal authority. Authorized staff can now browse a stable, paginated Business Directory; move between Owner, Business History, and Historical Application records; inspect classifications, measurements, payment schedules, payments, OR claims, permit claims, clearance claims, and deterministically associated private documents; and search across Business, Owner, Application reference, Permit number, or an exact normalized OR number.

Every surface displays **Historical Ipil Record · Read only**. The application detail explicitly says `Not operational` and `Unavailable` for workflow continuation. The route family contains only `GET`/`HEAD` endpoints. It provides no create, edit, transition, assessment, payment, receipt issuance, permit issuance, account claim, correction, or reconciliation action.

## Accepted Surface

| Capability | Gate 7 disposition | Gate 8A result |
| --- | --- | --- |
| Historical registry and Business Directory | ADAPT | Paginated 20-row directory with deterministic secondary ordering |
| Owner view | ADAPT | Owner → Businesses → Applications; collision candidates remain visible and are never merged or made Users |
| Business History | MATCH | Source business facts and chronological Application evidence are navigable |
| Historical Application | ADAPT | One non-operational projection for classifications, measurements, finance, claims, and documents |
| Unified search | IMPROVE | One surface searches Business/registration, Owner, Application reference, Permit, and exact normalized OR without logging the query |
| Year/type/status/barangay/classification filters | ADAPT | Evidence-literal filters; no LOB or PSGC candidate is accepted as canonical |
| Finance/payment/receipt evidence | ADAPT | Persisted source lexemes and decimals only; duplicate OR claims and broken links remain warnings |
| Permit and clearance evidence | ADAPT | Source claims remain claims; missing and broken relationships remain explicit |
| Uploaded evidence | ADAPT | Only the one deterministically linked business application document is reachable from the historical surface; authorization and private storage remain enforced |
| Unresolved media and generated artifacts | DEFER | Nineteen unresolved objects remain unattached; generated artifacts remain outside `application_documents` |
| Historical reports/exports/dashboard | DEFER | Gate 8A does not assign new report meaning, regenerate artifacts, or alter dashboard workload semantics |
| Product redesign, cleanup, PSGC/LOB reconciliation | DEFER | Explicitly unchanged |

## Evidence and Safety

The read layer selects explicit presentation columns and never sends wholesale `source_payload_json`, raw source storage identifiers, media manifest payloads, or source identity hashes to the browser. Exact OR lookup reproduces the Gate 6 normalization and hashes the query before matching. Business document association reproduces the approved deterministic edge: SHA-256 of the source `storageId` must equal the rescued manifest's `storage_identifier_sha256`. The resulting Spatie item must also be `ASSOCIATED`, accepted, imported, and in the private `application_documents` collection. Cross-business or unresolved evidence cannot satisfy that lookup.

The completed materialization was not reseeded. The local environment was pointed to the dedicated PostgreSQL database only for bounded browser validation and then restored. No live Ipil access, Cloud access, public URL, data correction, current `Price`/`FeeRule` execution, or operational write path was used.

## Validation

The Gate 6 audit passed before implementation and is required to pass again at acceptance with all 62 checks green. Preserved anchors are:

| Evidence | Accepted count/value |
| --- | ---: |
| Owners | 3,194 |
| Businesses | 3,212 |
| Historical Applications | 3,137 |
| Payment schedules | 7,648 |
| Payments | 5,874 |
| Receipt claims | 5,873 |
| Permit claims | 2,766 |
| Clearance claims | 14,615 |
| Completed payments and schedule-paid evidence | PHP 93,295,317.20 |
| Associated checksum-matched media / unresolved objects | 16 / 19 |
| Historical actionable records / history-created Users / operational leakage | 0 / 0 / 0 |

Browser validation used real rescued records through the ordinary authenticated staff navigation. It covered the directory; Owner, Business, and Application detail; schedules, payments, OR claims including a duplicate group, permit and clearance claims; unified permit search; combined year/type/status/barangay/classification filters; application-count sorting; first, middle, and last pagination; the deterministically linked DTI document; and empty-document behavior. Desktop and exact `390×844` views were inspected. On the mobile Application view, the boundary, finance content, and non-operational state remained legible and no operational action was present. Browser console errors: zero.

Observed warm local query-layer timings were 49.59 ms for the default directory payload, 12.56 ms for combined filters, and 17.33 ms for unified permit search. Existing Gate 6 indexes were sufficient; Gate 8A adds no schema or index migration.

Focused automated verification covers permission denial, all four detail/list projections, source-payload redaction, exact hashed-OR search, Business/Owner/Application/Permit search, and the GET/HEAD-only route invariant. It passed 3 tests / 93 assertions. The full repository suite passed 924 tests with one skipped test and 16,274 assertions. TypeScript checking, the production frontend build, ESLint on the changed frontend, Pint on the changed PHP, targeted PHPStan with zero findings, and Git whitespace validation passed. The final post-validation Gate 6 audit passed all checks and retained the PHP 93,295,317.20 anchor.

## Gate 8C Recommendation

**YES — READY FOR GATE 8C PRIVATE HISTORICAL UAT.**

This is a binary recommendation for a bounded, authenticated, local/private staff review of the historical read surface only. It is not authority for Cloud deployment, public access, production cutover, historical correction, unresolved-media attachment, canonical LOB/PSGC reconciliation, report-semantic acceptance, or any operational use of rescued history. Gate 8C should stop on any ability to mutate history, any missing historical label, any private-document authorization failure, any hidden duplicate/broken evidence warning, or any Gate 6 audit divergence.
