# Milestone Scenarios

This file records implementation-era milestone scenarios. Discovery lifecycle facts remain in `docs/discovery/BUSINESS_LIFECYCLE_MAP.md`; parity status remains in `docs/implementation/PARITY_LEDGER.md`; the Golden Path placeholder lives in `storyboards/GOLDEN_PATH.storyboard`.

Milestone scenarios are composed from completed vertical slices. They are not a second workflow engine and must not duplicate business logic. They invoke the same domain actions, policies, application services, generated documents, browser UI, audit evidence, and storage conventions used by the application.

## Status Vocabulary

- `PLANNED`: identified but not executable.
- `EXECUTABLE`: terminal runner exists.
- `BROWSER VERIFIED`: browser runner has inspected exact manifest records.
- `MILESTONE EVIDENCE PACKAGE`: terminal, browser, audit, storyboard, generated document, screenshot, and parity evidence are available for review.
- `BLOCKED`: implementation must stop at an explicit unresolved policy boundary.

## MS-002: Citizen-Originated New Permit Lifecycle To Authority Boundary

Status: MILESTONE EVIDENCE PACKAGE

Scenario key: `citizen_new_permit_lifecycle_authority_boundary`

Run ID: `citizen-new-permit-20260815-001`

Artifact root: `storage/app/private/lifecycle-scenarios/citizen_new_permit_lifecycle_authority_boundary/citizen-new-permit-20260815-001`

Purpose: prove that one application created and formally submitted by an established citizen can be received and processed through the real municipal domain actions, inspected by both citizen and staff in the browser, and carried through assessment, Treasury collection, clearances, and artifact verification without crossing the unresolved authority boundary.

The scenario composes the citizen and municipal processing lifecycles around one exact database record. It does not implement a second workflow path: every transition is executed by the same application actions and policies used by the production UI.

## MS-002 Lifecycle Map

| Step | Business meaning | Executed by | Evidence | Status |
| --- | --- | --- | --- | --- |
| 1 | Citizen creates an unnumbered draft under the durable legal owner identity | Citizen draft action | `manifest.json`, `terminal/prepare.json`, intake screenshots | BROWSER VERIFIED |
| 2 | Citizen attaches supporting evidence while the application remains a draft | Citizen document action | prepare evidence, supporting-document screenshots | BROWSER VERIFIED |
| 3 | Citizen formally submits and the municipality receives the application into processing | Citizen submission action | submission and receipt timeline events, citizen list/detail screenshots | BROWSER VERIFIED |
| 4 | Municipality computes assessment from persisted fee rules | Assessment action | assessment #54, terminal and browser evidence | BROWSER VERIFIED |
| 5 | Municipality establishes the payment schedule | Payment schedule action | payment schedule #51, queue/detail screenshots | BROWSER VERIFIED |
| 6 | Treasury records the full over-the-counter collection | Treasury collection action | collection #33, report and payment screenshots | BROWSER VERIFIED |
| 7 | Treasury issues the manually authorized receipt | Receipt action | receipt #33, receipt/report screenshots | BROWSER VERIFIED |
| 8 | Authorized staff complete the three clearance records | Clearance actions | audit evidence, staff and citizen detail screenshots | BROWSER VERIFIED |
| 9 | The generated permit artifact and public artifact identity become inspectable | Existing document and verification boundaries | `PVA-68-8d01ccfe76f898ed`, desktop/mobile public screenshots | ARTIFACT VERIFIED |
| 10 | Citizen and staff see readiness for authority review while issuance and release remain unavailable | Existing authority-boundary policy and projections | audit checks, citizen/staff authority screenshots | BLOCKED BY POLICY |

## MS-002 Authoritative Resource Identifiers

- Permit application: #68
- Official application number: none
- Assessment: #54
- Payment schedule: #51
- Collection: #33
- Receipt: #33
- Permit verification reference: `PVA-68-8d01ccfe76f898ed`

These are local evidence identifiers. `Application #68` and `Application record #68` are display fallbacks for the internal record identity; neither is represented as an official municipal application number.

## MS-002 Evidence Package

- Manifest: `manifest.json`
- Human summary: `summary.html`
- Reviewer note: `review.md`
- Terminal evidence: `terminal/prepare.json`, `terminal/execution.json`, `terminal/audit.json`
- Browser evidence: `browser/report.json`, browser action log, console-error and failed-request reports
- Storyboard: `storyboard/storyboard.json`, `storyboard/storyboard.html`
- Representative screenshots:
  - `browser/screenshots/01-citizen-processing-list.png`
  - `browser/screenshots/02-citizen-processing-detail.png`
  - `browser/screenshots/01-payment-schedule-queue.png`
  - `browser/screenshots/02-paid-establishments-report.png`
  - `browser/screenshots/03-permit-release-boundary.png`
  - `browser/screenshots/04-citizen-authority-review.png`
  - `browser/screenshots/06-citizen-payment-detail.png`
  - `browser/screenshots/08-citizen-public-artifact-verification.png`
  - mobile counterparts for the citizen processing, authority-review, payment, and public-verification surfaces

Generated evidence remains under `storage/app/private/**` and outside version control.

## MS-002 Verification Matrix

| Verification layer | Result | Evidence |
| --- | --- | --- |
| Terminal scenario prepare | PASS | exact citizen, owner, business, application, and downstream records in `manifest.json` |
| Domain transition audit | PASS | `terminal/audit.json`; fourteen expected timeline events and no duplicate operations |
| Citizen browser verification | PASS | exact manifest record on desktop and mobile |
| Staff browser verification | PASS | exact manifest record across assessment, Treasury, reports, documents, and authority boundary |
| Canonical/UI agreement | PASS | 124 browser checks and terminal audit checks |
| Browser JavaScript errors | ZERO | `browser/console-errors.json` |
| Failed application requests | ZERO | `browser/failed-requests.json` |
| External integrations | NONE | scenario safety manifest |
| Irreversible actions | NONE | scenario safety manifest |

## MS-002 Explicit Non-Claims

This milestone does not claim documentary sufficiency, an official application-number format, complete ordinance fee parity, automatic official receipt numbering, online payment or reconciliation, legal permit issuance, legal release, legal effect, production migration parity, or live-production UI parity.

## MS-001: Unified New Permit Lifecycle To Authority Boundary

Status: MILESTONE EVIDENCE PACKAGE

Scenario key: `new_permit_lifecycle_authority_boundary`

Run ID: `new-permit-lifecycle-20260814-001`

Artifact root: `storage/app/private/lifecycle-scenarios/new_permit_lifecycle_authority_boundary/new-permit-lifecycle-20260814-001`

Purpose: prove that the Laravel replacement can execute a new business permit lifecycle through the real application domain and show the same state in the browser, while preserving the explicit authority boundary before legal issuance or release.

This is the project's first milestone scenario candidate. It is not the final Golden Path because permit issuance, release, signatory authority, QR semantics, receipt numbering authority, online payment, reconciliation, and remaining Treasury policy are unresolved.

## Lifecycle Map

| Step | Business meaning | Executed by | Evidence | Status |
| --- | --- | --- | --- | --- |
| 1 | Staff records a new permit application | Staff intake domain action | `manifest.json`, `terminal/prepare.json`, storyboard frame 1 | BROWSER VERIFIED |
| 2 | Assessment is computed from persisted fee rules | Assessment domain action | `manifest.json`, `terminal/prepare.json`, `browser/report.json`, assessment PDF evidence | BROWSER VERIFIED |
| 3 | Payment schedule is prepared | Payment schedule action | `manifest.json`, payment schedule #18, payment queue screenshot | BROWSER VERIFIED |
| 4 | Treasury records full over-the-counter collection | Treasury collection action | `manifest.json`, collection #13, payment schedule detail screenshot | BROWSER VERIFIED |
| 5 | Manual receipt is issued | Receipt issuance action | receipt #13, `SCENARIO-OR-NEW-PERMIT-LIFECYCLE-20260814-001`, receipt screenshots | BROWSER VERIFIED |
| 6 | Receipt void remains blocked | Receipt policy boundary action | `RVB-13-1130cbb92bbde7b8`, audit/browser checks | BLOCKED BY POLICY |
| 7 | Clearance checklist is completed | Clearance actions | audit checks, permit detail screenshot, `3 / 3 complete` evidence | BROWSER VERIFIED |
| 8 | Permit artifact is generated for review | Permit document route | `/staff/permit-applications/26/permit.pdf`, browser document evidence | ARTIFACT VERIFIED |
| 9 | Public verification confirms artifact identity only | Public verification route and page | `PVA-26-5843cdd5aced1d4f`, public verification screenshot; latest public page verification `PVA-42-b941419e749ba2e8` | BROWSER VERIFIED |
| 10 | Release remains unavailable at authority boundary | Permit release boundary action | audit/browser checks, permit release boundary screenshot | BLOCKED BY POLICY |

## Authoritative Resource Identifiers

- Permit application: #26
- Application number: `APP-SCENARIO-NEW-PERMIT-LIFECYCLE-20260814-001`
- Assessment: #21
- Assessment total: `PHP 1,200.00`
- Payment schedule: #18
- Collection: #13
- Receipt: #13
- Receipt number: `SCENARIO-OR-NEW-PERMIT-LIFECYCLE-20260814-001`
- Receipt void boundary reference: `RVB-13-1130cbb92bbde7b8`
- Permit verification reference: `PVA-26-5843cdd5aced1d4f`

These identifiers are local evidence identifiers from the milestone run. They are not production identifiers.

## Milestone Evidence Package

The following artifacts form the first milestone evidence package:

- Manifest: `manifest.json`
- Human summary: `summary.html`
- Reviewer note: `review.md`
- Terminal prepare evidence: `terminal/prepare.json`
- Terminal execution evidence: `terminal/execution.json`
- Terminal audit evidence: `terminal/audit.json`
- Terminal action log: `terminal/action-log.jsonl`
- Browser report: `browser/report.json`
- Browser action log: `browser/action-log.jsonl`
- Browser console errors: `browser/console-errors.json`
- Browser failed requests: `browser/failed-requests.json`
- Storyboard JSON: `storyboard/storyboard.json`
- Storyboard HTML: `storyboard/storyboard.html`
- Screenshots:
  - `browser/screenshots/01-payment-schedule-queue.png`
  - `browser/screenshots/02-receipt-queue.png`
  - `browser/screenshots/01-payment-schedule.png`
  - `browser/screenshots/02-receipt-detail.png`
  - `browser/screenshots/03-permit-release-boundary.png`
  - `browser/screenshots/04-permit-verification-boundary.png`
  - `browser/screenshots/04b-mobile-permit-verification-boundary.png`
  - `browser/screenshots/05-mobile-receipt.png`

Generated execution artifacts remain under `storage/app/private/**` and are not committed. This document is the committed index that tells reviewers what evidence exists and where it was generated.

## Verification Matrix

| Verification layer | Result | Evidence |
| --- | --- | --- |
| Terminal scenario prepare | PASS | `manifest.json`, `terminal/prepare.json` |
| Browser verification | PASS | `browser/report.json`, screenshots |
| Audit verification | PASS | `terminal/audit.json` |
| Application form PDF evidence | PASS | `browser/report.json`, application form document check |
| Assessment PDF evidence | PASS | `browser/report.json`, assessment document check |
| Permit artifact evidence | PASS | `browser/report.json`, permit artifact verification |
| Public verification boundary | PASS | `browser/report.json`, `PVA-26-5843cdd5aced1d4f` |
| Receipt void/reversal behavior | BLOCKED BY POLICY | `terminal/audit.json`, `browser/report.json` |
| Permit legal release | BLOCKED BY POLICY | `terminal/audit.json`, permit release boundary screenshot |
| External integrations | NONE | scenario safety manifest |
| Irreversible actions | NONE | scenario safety manifest |

## Confirmed Capability Composition

MS-001 composes these implemented capabilities:

- CAP-010 New business permit application
- CAP-017 Application assessment queue
- CAP-019 Pending payment queue
- CAP-020 Permit release / releasing queue boundary
- CAP-026 Line of business capture
- CAP-029 Constant fee calculation
- CAP-039 Payment schedule configuration
- CAP-040 Over-the-counter collection recording
- CAP-041 Manual receipt issuance
- CAP-042 Receipt detail / print-friendly view
- CAP-043 Receipt void / reversal policy boundary
- CAP-060 Permit clearance checklist
- CAP-061 Clearance completion gating for permit issuance boundary
- CAP-063 Mayor's Permit PDF artifact
- CAP-065 Application form PDF
- CAP-066 Assessment sheet PDF
- CAP-081 Payment search/filter/table
- CAP-115 Permit release documents after clearances boundary

## Explicit Non-Claims

This milestone does not claim:

- citizen-facing permit application parity;
- renewal lifecycle parity;
- retirement, transfer, or PIL behavior;
- complete ordinance fee catalog parity;
- automatic official receipt numbering;
- receipt void/reversal/reconciliation behavior;
- online payment behavior;
- legal permit issuance;
- legal permit release;
- QR verification of an issued or released permit;
- production data migration parity.

## Current Authority Boundary

The milestone proves that software can know:

- the application exists;
- assessment was computed;
- payment schedule was paid;
- collection was receipted;
- clearances are complete;
- permit artifact exists;
- public artifact verification exists;
- the record is ready for authority review.

It does not prove that a permit is issued, released, currently valid, or legally effective.

## Next Board Trigger

The project now has:

1. the Unified New Permit Lifecycle Scenario;
2. browser evidence for the full lifecycle through the authority boundary;
3. a lifecycle map and milestone evidence package index.

This satisfies the previously defined trigger for the next Engineering Program Review. The next report should introduce MS-001 as the first milestone scenario candidate and assess whether autonomous implementation should continue into the next lifecycle area.
