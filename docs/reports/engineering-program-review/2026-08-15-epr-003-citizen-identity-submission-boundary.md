# Engineering Program Review #003: Citizen Identity And Submission Boundary

Date: 2026-08-15

==================================================

BPLS-RUNTIME

Engineering Program Review #003

Overall Health: YELLOW - implementation remains healthy; citizen identity and submission policy require a decision

Architecture Health: HEALTHY WITH A REQUIRED DOMAIN ADJUSTMENT

Current Phase: Early Implementation / Citizen Lifecycle Composition

Recommendation: DECISION REQUIRED

==================================================

## 1. Executive Dashboard

`bpls-runtime` remains healthy and evidence-driven. The first staff-oriented new-permit milestone is complete through the authority boundary, the citizen portal now covers owned draft intake, editing, supporting documents, processing visibility, timeline, detailed payment evidence, authority-review readiness, permit-artifact identity, and artifact-only public verification, and 35 implementation-ledger capabilities are browser verified.

The next planned work was to compose those citizen slices into a citizen permit milestone. Repository and authoritative legacy-source research exposed two connected domain decisions that must be settled first: what establishes a citizen's durable legal relationship to a business owner/business, and what a citizen submission means to the municipality.

The legacy application answers the identity question with `users.linkedBusinessOwnerId`, not with application submitter identity. It answers submission by changing `Draft` to `Assessment`, but it also assigns an application number and `submittedAt` when saving a draft. The current Laravel rescue deliberately treats a draft as unsubmitted, leaves its application number null, and scopes access through `submitted_by_id`. Continuing without review would silently select one of two materially different models and harden it into migration, authorization, numbering, and audit behavior.

## 2. Current Phase

Current phase: Early Implementation / Citizen Lifecycle Composition.

- Ground Zero, Discovery, and Architecture are complete and approved.
- The first milestone scenario proves the staff-operated new-permit journey through the authority boundary.
- Renewal, amendment, transfer, and retirement have browser-verified policy-boundary foundations.
- Foundational Treasury and operational reports are browser verified.
- The citizen journey is substantial but stops before a consciously defined formal-submission transition.
- Migration, production configuration, final financial policy, legal issuance/release, and deployment readiness remain ahead.

## 3. Capability Progress

Discovery identified 116 externally or contractually meaningful capabilities. The implementation ledger currently records:

- 35 browser-verified capabilities;
- 4 partially implemented capabilities;
- 9 explicitly blocked capabilities;
- 68 discovered capabilities not yet active in the implementation ledger.

These counts are not weighted progress percentages.

| Area | Current State | Evidence / Remarks |
| --- | --- | --- |
| Registry | Foundational / decision required | Owner and business facts are persisted, but durable citizen-to-owner identity is absent from Laravel. Legacy uses an explicit user-to-owner link. |
| Permitting | Foundational with one milestone | New permit is verified through authority review. Formal citizen submission, legal issuance, and release remain incomplete. |
| Assessment | Foundational / partial | Deterministic constant and range snapshots work. Formula, rounding, full catalog, PIL, surcharge, and interest remain unresolved. |
| Treasury | Partial with explicit seams | OTC collection, allocation, receipt, and several reports work. Numbering, reversal, online payment, and reconciliation remain blocked. |
| Reporting | Foundational / partial | Daily collections, revenue sources, paid/unpaid establishments, and top tax due are browser verified. Official report acceptance remains ahead. |
| Citizen Portal | Substantial foundation | Drafts, editing, documents, tracking, timeline, payment detail, authority review, and artifact identity are verified. Durable profile ownership and formal submission are unresolved. |
| Administration | Foundational | Roles and permissions work locally. Production role/configuration migration remains ahead. |
| Migration | Not started | The citizen identity decision directly affects deterministic legacy-user, owner, business, and application mapping. |
| Verification | Strong | Exact-record lifecycle scenarios, browser evidence, audits, PDFs, storyboards, and parity records are operational. |

## 4. Architecture Health

Architecture health: healthy with a required domain adjustment.

The approved Laravel monolith, relational source of truth, Vue/Inertia frontend, domain actions, deterministic financial snapshots, internal reporting, document boundaries, and lifecycle evidence architecture remain valid. No redesign is required.

One approved assumption must be sharpened: `submitted_by_id` identifies the actor who created/submitted an application, but it is not sufficient evidence that the user owns the reusable legal `BusinessOwner` or every `Business` attached to that owner. The legacy system models those concepts separately through `users.linkedBusinessOwnerId`.

This distinction should be resolved before reusable citizen business selection is implemented. Otherwise the rescue must either share mutable registry rows without a durable ownership claim or clone them and create duplicate legal identities.

## 5. Project Risks

1. Financial and Revenue Code correctness remains the highest correctness risk.
2. Production data and migration are now also prerequisites for validating citizen-to-owner identity mappings.
3. Treasury numbering, reversal, online payment, and reconciliation policy remain unresolved.
4. Citizen submission semantics now affect official numbering, timestamps, audit history, assessment queues, edit locks, and migration parity.
5. Permit issuance, release, signatory authority, QR meaning, and legal effect remain at the authority boundary.
6. Official reporting acceptance and non-permit Treasury scope remain incomplete.
7. Schedule pressure creates a risk of treating legacy field behavior as business meaning without conscious acceptance.

## 6. Technical Debt

Deliberate technical debt remains visible:

- Citizen applications are currently scoped by `submitted_by_id` rather than a durable citizen-owner profile link.
  - Reason: deliver safe owned draft and tracking slices before production identity data was available.
  - Impact: existing-business reuse and migration identity reconciliation cannot yet claim parity.
  - Cleanup: implement the accepted durable identity relationship and migrate legacy links deterministically.

- Citizen drafts currently create exclusive owner/business records.
  - Reason: prevent one citizen draft from mutating shared registry history.
  - Impact: the current path is safe but may create identities that later require reconciliation.
  - Cleanup: move reusable registry maintenance behind the accepted ownership model.

- The shared creation action currently records `submitted_at` for citizen drafts even though the UI correctly describes them as unsubmitted.
  - Reason: the original action preceded the explicit citizen draft boundary.
  - Impact: timestamp semantics are internally inconsistent and must be corrected with migration awareness.
  - Cleanup: separate draft creation time from formal submission time after the submission decision.

Existing narrow payment, receipt, document-layout, and evidence-storage debt remains as recorded in EPR #002. No hidden financial or issuance behavior has been introduced.

## 7. Major Discoveries

- Legacy citizen authorization is based on a durable user-to-business-owner link. Applications and payments are authorized by comparing `application.businessOwnerId` with `user.linkedBusinessOwnerId`.
- Legacy citizens select businesses belonging to that linked owner. Application submitter identity and legal owner identity are different concepts.
- Legacy citizen submission changes `Draft` to `Assessment` and requires a payment-mode choice.
- Legacy draft creation also assigns `BPA-{year}-{sequence}` and `submittedAt`, even when `saveAsDraft=true`. This conflicts with the rescue's explicit and clearer statement that a saved draft is not submitted or official.
- Legacy source permits submission without uploaded documents. The municipality's actual sufficiency/acceptance policy remains unresolved.
- A citizen milestone cannot honestly claim an end-to-end submitted journey until identity, numbering, timestamp, and municipal-receipt semantics are consciously reconciled.

## 8. Evolution

What became simpler:

- “Citizen owns this application” and “citizen is linked to this legal business owner” are now separate concepts.
- Draft creation, citizen submission, municipal receipt, municipal acceptance, assessment computation, and approval can remain distinct rather than being hidden behind one status change.
- Existing-business selection no longer needs to be guessed from prior application history; it can follow an explicit accepted ownership relationship.
- Migration requirements are clearer: user, owner, business, and application identity must be mapped together rather than independently.

## 9. Current Slice Summary

The latest completed implementation slice is CAP-113 Citizen Payment Detail. It provides ownership-scoped, read-only assessment, schedule, collection, allocation, receipt, and policy-boundary evidence. Its terminal, browser, and audit phases passed, and it is committed as `92b801d`.

No citizen identity or formal-submission code was added after this discovery. The current review was triggered during pre-implementation research for the next bearings.

## 10. Evidence Produced

Authoritative legacy evidence:

- `packages/backend/convex/citizen/profile.ts`: establishes and persists `linkedBusinessOwnerId`.
- `packages/backend/convex/citizen/businesses.ts`: lists/registers businesses for the linked owner.
- `packages/backend/convex/citizen/applications.ts`: enforces owner linkage and implements Draft/Assessment submission behavior.
- `apps/web/app/portal/applications/new/page.tsx`: exposes existing-business selection, save-draft, and submit actions.
- `packages/shared/src/schemas/permit-form.ts`: states that applications may be submitted without uploaded documents.

Current Laravel evidence:

- `permit_applications.submitted_by_id` scopes citizen application access.
- `users` has no business-owner relationship.
- citizen draft UI states that a draft is unsubmitted and unofficial.
- `CreatePermitApplication` currently records `submitted_at` even for those drafts.
- CAP-110 and CAP-111 remain UI partial in `docs/implementation/PARITY_LEDGER.md`.

Latest executable citizen evidence:

`storage/app/private/lifecycle-scenarios/citizen_permit_authority_review_visibility/citizen-payment-detail-20260815-002`

## 11. Recommendation

DECISION REQUIRED

Pause citizen profile reuse, formal submission, and the citizen milestone scenario until the Board accepts the identity and submission contract below. Other unrelated implementation could continue, but implementing these three bearings without a decision would create expensive migration and authorization consequences.

## 12. Proposed Decision Contract And Next Slice

Recommended citizen identity contract:

1. Add an explicit nullable relationship from an application user to one legal `BusinessOwner`, preserving the legacy distinction between account identity and owner identity.
2. Scope selectable citizen businesses through that linked owner, not through “latest application” inference.
3. Keep `submitted_by_id` as the application actor/audit field.
4. Do not allow application-draft editing to mutate shared owner/business registry facts. Registry maintenance and application-specific declarations remain separate operations.
5. Migrate legacy `linkedBusinessOwnerId` deterministically when production data becomes available; do not auto-claim unmatched staff-created owners by email alone.

Recommended submission contract:

1. Saving a draft leaves status `Draft`, `submitted_at=null`, and no official application number.
2. Citizen submission is an explicit idempotent domain action that changes `Draft` to `Assessment`, records `submitted_at`, locks citizen draft editing, and records status-history/audit evidence.
3. Submission means “received into the municipal assessment queue,” not documentary sufficiency, approval, or legal acceptance.
4. Documents remain supporting evidence and do not block submission until an accepted requirement catalog exists.
5. Preferred payment mode may be captured as applicant intent but must not create or calculate a payment schedule.
6. Official application-number format and allocation authority must be accepted before assignment. The legacy `BPA-{YYYY}-{NNN}` format is evidence, not automatically governing policy.

Decision requested from the Board:

- Accept or amend the recommended identity contract.
- Accept or amend the recommended submission contract.
- Decide whether a formal citizen submission receives an official application number immediately and, if so, authorize the initial format/source of truth.
- Decide whether payment-mode choice is required at submission or deferred until Treasury policy is accepted.

Once accepted, the next vertical slice is Citizen Registry Identity And Formal Submission, followed by the first citizen milestone evidence package.

## 13. Coffee with Arti

Should a citizen account represent a person who acts for one legal business owner, or can one account legitimately act for several owners and organizations?

The legacy system permits one `linkedBusinessOwnerId`, but partnerships, corporations, authorized representatives, accountants, and future delegation may require a many-to-many authority model. The rescue should not generalize prematurely, but the migration contract should avoid making a one-owner assumption irreversible.

## 14. Constitution Check

- Evidence before design: compliant.
- Design before implementation: compliant; implementation stopped at the decision boundary.
- Production treated as evidence, not a playground: compliant.
- Unknown policy remains explicit: compliant.
- Laravel-native direction preserved: compliant.
- No premature GNE abstraction: compliant.
- Observable parity remains primary: compliant, with legacy behavior separated from accepted business meaning.
- Storyboards remain verification and communication artifacts: compliant.
- Lifecycle runner remains verification infrastructure, not a workflow engine: compliant.
- Domain remains the source of business truth: compliant.

## 15. Standing Board Decisions

- Target deployment direction is Laravel Cloud.
- Replacement shape is a single Laravel 13 application.
- Vue/Inertia is the frontend path.
- Relational database is the application source of truth.
- Convex storage topology will not be mechanically reproduced.
- ClickHouse, Airbyte, and Vercel deployment topology will not be retained merely for parity.
- Reporting belongs inside Laravel unless measured requirements prove otherwise.
- Storyboards and Lifecycle Scenario Runner are verification infrastructure, not workflow engines.
- Production is evidence, not a playground.
- Unknown business policy remains an explicit seam.
- Billing Groups remain provisional pending Treasury acceptance.
- Permit artifact, issuance, release, legal effect, and observed validity remain distinct.
- The Golden Path is emergent.
- GNE concepts must not enter the rescue prematurely.

## 16. If I Were Starting Today

I would introduce the citizen-to-business-owner identity question during Architecture rather than allowing application ownership to temporarily stand in for registry ownership. Discovery contained the legacy link, but its migration and authorization consequences became clear only when reusable citizen business selection reached implementation.

I would also define `created_at`, `submitted_at`, municipal receipt, acceptance, and official numbering as separate vocabulary before implementing citizen drafts. The current explicit draft UI is directionally correct; the persistence timestamp now needs to be reconciled to that meaning.

## 17. Confidence Index

This measures confidence in sufficient understanding, not implementation progress.

| Area | Confidence | Remarks |
| --- | --- | --- |
| Architecture | [#########-] 88% | Core architecture is healthy; citizen identity needs one bounded adjustment. |
| Registry | [######----] 55% | Legacy linkage is now clear; production identity mapping and accepted cardinality remain unknown. |
| Permitting | [#######---] 70% | New permit is proven through authority review; submission and legal issuance remain unresolved. |
| Assessment | [######----] 55% | Snapshot execution works; ordinance completeness and advanced policy remain high-risk. |
| Treasury | [#####-----] 45% | OTC and reporting foundations work; numbering, reversal, online payment, and reconciliation remain uncertain. |
| Reporting | [#####-----] 45% | Several operational reports work; official formats and broader TOR scope remain ahead. |
| Citizen Portal | [#######---] 68% | Most read/write surfaces are proven; durable identity and formal submission now have explicit decision points. |
| Migration | [#---------] 10% | Identity mapping is clearer, but production data remains unavailable. |
| Verification | [#########-] 90% | Exact-record terminal/browser/audit evidence is established across staff and citizen slices. |
| Overall Rescue | [#######---] 66% | Understanding continues to rise because policy conflicts are surfaced before implementation. |
