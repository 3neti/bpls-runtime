# Warp/Oz Independent Product Review — Chief Architect Disposition

**Date:** 2026-08-21  
**Reviewed baseline:** `aa32b35f39ec99f0e46226bb188a28ef3f46aad9`  
**Frozen deployment:** `depl-a28d0159-40ac-477b-9bfa-6f5cfcc1e886`  
**Board-accepted verdict:** `FUNCTIONALLY STRONG, STILL FEELS LIKE UAT/ENGINEERING`

## Evidence handling

The independent Mac Warp/Oz report remains immutable product evidence in the Board work thread. This record is an additive Chief Architect disposition; it neither edits nor substitutes for the independent report and does not claim to be an independent review.

The review tested whether the preview behaves and communicates like the intended BPLS product. Prior automated, scenario, and browser checks were treated as implementation evidence only, not as a defense of the reviewed presentation.

## P0 root-cause disposition

| Finding | Decision | Root cause | Repair and proof |
|---|---|---|---|
| Treasurer approval returned a raw 500 | Accepted — `REWRITE` | The decision route did not bind the decision to the exact assessment version shown to the Treasurer, and domain refusals from stale or invalid state were not converted into a user-facing validation result. The frozen endpoint succeeded when its state was valid, confirming that the defect was state/error handling rather than a universally broken route. | The page now submits the exact assessment fingerprint; the action checks it under the same database lock used to record the immutable decision; stale/invalid decisions return a page error rather than a 5xx. Authorized success, unauthorized refusal, stale-version refusal, and immutable audit evidence are tested. |
| Treasurer return for correction returned a raw 500 | Accepted — `REWRITE` | The return route shared the same unhandled domain-refusal path and did not submit the exact version the Treasurer reviewed. | Return now carries and validates the exact fingerprint, uses the locked decision action, preserves reason and audit evidence, and returns a page error for invalid state. Fresh approval remains required after a corrected assessment is recomputed. |
| Public verification changed on consecutive loads | Accepted — `REWRITE` | A review request recomputed an assessment after payment and preview completion. That reset the operational application stage while leaving the provisional completion record in place. Separate page and JSON projections then exposed different slices of the contaminated state. The GET itself did not mutate state. | Reassessment is refused after payment scheduling or preview completion and is allowed only after an immutable return-for-correction decision. Page and JSON now use one projection action. Repeated unchanged JSON responses are byte-identical and do not update the application. |
| Complete/Released signals contradicted each other | Accepted — `REWRITE` | Generic checklist completion and two different release concepts—sample workflow completion and legal municipal release—were presented with unscoped labels and independently assembled projections. | Clearance labels now say “checklist items complete.” The public/staff projections separately identify sample workflow completion, municipal legal release, and legal effect. Generic adjacent “Released: Yes/No” labels were removed. |
| Realistic sample outputs were unsafe when cropped | Accepted — `MISSING PRESENTATION` | Preview context was only page-level, so financial totals and permit-data cards could be detached from the disclaimer in screenshots. | Inline `Preview · Sample Data` markers now travel with the public permit card and realistic financial headline cards at desktop and mobile sizes. Values remain visible. |
| Duplicate preview banners and nested shell | Accepted — `REWRITE` | The Inertia bootstrap applied the application layout globally while many pages also declared the same layout locally. | Layout ownership is now explicit by page family; self-wrapped pages are not wrapped a second time. The persistent preview marker remains. |

## Product finding disposition

| Oz finding or anchor | Classification | Disposition | Implementation packet or retained question |
|---|---|---|---|
| Treasurer approval confirmation | `KEEP` | Accepted as the fiscal interaction anchor; exact-version confirmation was strengthened. | Chief Architect P0 packet. |
| Receipt presentation | `KEEP` | Accepted; preserved while improving mobile item/amount relationships and long-reference wrapping. | Office/mobile specialist packet. |
| Golden end-to-end journey | `KEEP` | Accepted; lifecycle order and safeguards remain unchanged. | Integrated lifecycle verification. |
| Disabled-action helper pattern | `KEEP` | Accepted; expanded to Mayor/Releasing preview actions and locked office charges. | Office/mobile specialist packet. |
| Verb-first actions | `KEEP` | Accepted and used for preview permit decisions without claiming production authority. | Office/mobile specialist packet. |
| Mobile stacked lists | `KEEP` | Accepted; retained and extended to receipt allocations. | Office/mobile specialist packet. |
| Public-verification disclaimer strategy | `KEEP` | Accepted; the disclaimer is preserved and the central data card now carries its own sample marker. | Chief Architect P0 packet. |
| UAT-role disclaimer | `KEEP` | Accepted; retained on launcher and compact mobile role switcher. | Administration/launcher and office/mobile packets. |
| Administration read-only enforcement | `KEEP` | Accepted; presentation changed, write authority did not. | Administration/launcher specialist packet. |
| Report refusal discipline | `KEEP` | Accepted; authority-pending reports still produce no fabricated official rows or exports. | Reports specialist packet. |
| One-BPLS concerned-office target architecture | `KEEP` | Accepted; each office now sees a bounded queue routed by its existing provisional contribution and only recorded application facts relevant to review. | Chief Architect queue/facts packet plus office/mobile specialist presentation. Exact required facts remain a Nelson question. |
| Engineering language in ordinary workflows | `REMOVE FROM PRODUCT UI` | Accepted. Terms such as boundary, artifact, persisted, endpoint, rescue runtime, execution readiness, and software/human matrices were removed or reframed on primary workflow surfaces. Internal field names and audit evidence remain. | Chief Architect core-language packet and all three specialist packets. |
| Assessment and payment wording implied permanent policy blocks | `REWRITE` | Accepted. Quarterly/installment and online payment are described as unavailable in this preview or not yet confirmed; neither capability was enabled. | Chief Architect core-language packet. |
| Administration looked like a schema/debug console | `POLISH` | Accepted. Primary surfaces now read as User Directory, Municipal Access Administration, Municipal Configuration, Fee and Rule Catalog, and provisional Other Collections Setup. Raw keys and dense evidence moved to secondary disclosures. | Administration/launcher specialist packet. |
| Unconfigured Mayor identity sounded like a defect or accusation | `REWRITE` | Accepted. Calm unconfigured-identity copy replaces alarming wording; no official was fabricated. | Administration/launcher specialist packet. Official display remains a Nelson question. |
| Reports looked like contracts or engineering diagnostics | `POLISH` | Accepted. Working reports use municipal language, plain availability labels, sample-data empty states, and screenshot-safe markers. Authority-pending reports remain professionally unavailable. | Reports specialist packet. View/export/print/certify authority remains a Nelson question. |
| Five concerned-office pages were mechanical clones | `POLISH` | Accepted. Office-specific headings, recorded fact sets, validation questions, and provisional amount context now differentiate the work without inventing requirements. | Chief Architect queue/facts packet plus office/mobile specialist packet. |
| Launcher and shell felt separate from the product | `POLISH` | Accepted. Launcher now presents the Business Permit and Licensing System, perspective selection, common tasks, and discreet persistent preview context. Starter-framework links were removed from the application header. | Administration/launcher specialist packet and Chief Architect shell cleanup. |
| Eleven-role switcher overwhelmed mobile | `POLISH` | Accepted. Mobile uses a labeled compact persona control while desktop keeps quick switching. | Office/mobile specialist packet. |
| Receipt item and amount lost their relationship on mobile | `POLISH` | Accepted. Mobile allocation cards keep each item with its amount; the desktop table remains. | Office/mobile specialist packet. |
| Navigation drawer lacked an accessible name | `POLISH` | Accepted. The trigger now has an explicit accessible label. | Office/mobile specialist packet. |
| Long references were truncated | `POLISH` | Accepted on reviewed receipt and public-reference surfaces through wrapping rather than silent clipping. | Office/mobile and Chief Architect P0 packets. |
| BPLO had no application search/filter | `MISSING PRESENTATION` | Accepted because semantics are straightforward over existing recorded fields. Text search now covers application/tracking reference, business/trade/registration name, and owner; status filtering uses existing application states. | Chief Architect queue packet. |
| New Draft business-activity control could be placeholder-only | `SEMANTIC / MUNICIPAL DECISION` | Reclassified. No authoritative municipal activity taxonomy was inferred. If the configured list is empty, the control now explains that the list is unavailable and prevents an unclassifiable save. | Retain registration/property identifiers and business-activity taxonomy as a Nelson/backend dependency. |
| Mayor Go/No-Go and sample signature wording looked like production procedure | `REWRITE` | Accepted. Actions now use preview-appropriate permit-review verbs, explain disabled reasons, and retain the concise no-authority note. No real signature authority was implemented. | Office/mobile specialist packet. Actual Mayor/signature procedure remains a Nelson question. |
| Releasing action and matrices looked like engineering workflow | `REMOVE FROM PRODUCT UI` | Accepted. Primary UI uses a clear preview completion action and removes engineering matrices; internal evidence remains recorded. | Chief Architect core-language and office/mobile packets. |
| Public verification could navigate or read like legal verification | `REWRITE` | Accepted. The page is a standalone document-reference check with explicit “municipal release not confirmed” and “legal effect not confirmed” language. | Chief Architect P0 packet. |
| CSV sample exports may be cropped away from preview context | `MISSING PRESENTATION` | Deferred at this pass. Export contents and authorization were intentionally unchanged; adding an embedded sample marker requires a separately verified export contract. | Retain under report view/export/print/certify authority and screenshot-safety follow-up. |

## Municipal questions retained

Safe cleanup did not decide:

1. Actual Mayor, signature, issuance, and release procedure.
2. Citizen-visible clearance detail and terminology.
3. Exact facts and requirements each concerned office needs.
4. Report view, export, print, generate, and certify authority.
5. Production permit numbering grammar.
6. Registration/property identifiers and the authoritative business-activity taxonomy.
7. Official identities and display rules.
8. TOR terminology and meaning.
9. Stakeholder purpose and scope for Billing Groups / Other Collections.

## Safety statement

All implementation packets are confined to the non-production stakeholder preview and ordinary presentation of existing data. Production, the 407-record migration campaign, real taxpayer liability, real permit numbering, municipal identity, signature authority, release authority, report authority, and legal effect remain untouched and fail closed.
