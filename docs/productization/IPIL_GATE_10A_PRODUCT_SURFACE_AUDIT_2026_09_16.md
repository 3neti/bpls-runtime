# IPIL BPLS GATE 10A — PRODUCT SURFACE AUDIT

Date: 2026-09-16. Role: BPLS Productization Engineer. **Documentation/source audit only; no implementation, deployment or data access.**

## Baselines and evidence discipline

- Starting workflow SHA: `f8a08ec28e2dfbb856bc0d42ceb9b587fcad34e6`, explicitly approved after baseline clarification. Dedicated branch `agent/product/gate10a-surface-audit`, worktree `/Users/rli/Documents/Codex/work/bpls-gate10a-product-audit`.
- Certified Gate 9: **PASS — ORDINARY BPLS WORKFLOW CERTIFIED END TO END**, as accepted by the Chief Architect. This audit does not rerun or independently recertify Application 291.
- Separately inspected reporting SHA: `953fe4ccf1bd09652516e82710b8c125a5b71884`, worktree `/Users/rli/Documents/Codex/work/bpls-report-r2-016`. Report 028 implementation SHA is `2270d5764899ceeda6df0d48cd23a3ac8593652f`. No merge, cherry-pick or report edits.
- Main checkout at `b851a56390a69ab3845e116f919544ecdc8684ec` diverges from Gate 9 by 12 main-only / 19 Gate-9-only commits. Four unrelated guidance edits in `.ai/skills/deploying-laravel-cloud/SKILL.md`, its `reference/checklists.md`, `AGENTS.md`, and `CLAUDE.md` were not touched.
- Final documentation commit: use the exact SHA returned with this packet; a commit cannot embed its own SHA. `git log -1 --format=%H -- docs/productization` identifies the packet commit on its branch.

Canonical deliverables: [machine-readable register](IPIL_PRODUCT_SURFACE_REGISTER.json), [terminology](IPIL_UI_TERMINOLOGY_REGISTER.md), [role journeys](IPIL_ROLE_JOURNEY_MAP.md), [implementation waves](IPIL_GATE_10_IMPLEMENTATION_PLAN.md), and this audit.

Read-only source examination covers routes, controller renders, auth configuration/shared props, Inbox task construction, page/component templates, existing Gate 9 correction notes and the separate reporting packet. Repository Vue/Inertia and Fortify guidance kept presentation/navigation separate from server authorization. No application boot, database query, credential read, browser request, external source access or test-data creation was needed. Desktop and 390×844 observations in prior packets remain prior evidence—not a new browser PASS.

## Quantitative controls

| Control | Count and interpretation |
| --- | --- |
| Inventory units | **95** = 80 page components + 15 embedded/output/account surfaces |
| Currently mapped page components | 77; **3 retained non-routed** components explicitly marked |
| Public/auth | 10 pages + logout = **11** inventory units; auth challenges are not all guest-accessible |
| Citizen | 9 pages + 4 supplementary = **13**; shared staff/citizen draft form counted once |
| Municipal staff/shared settings | 44 pages + 6 supplementary = **50**; includes 18 operational report pages |
| Historical/report | 7 pages + private document and 3 CSV outputs = **11**; separate reporting baseline |
| Engineering/test | **10** page components, including storyboards; no claim all are ordinary-visible |
| Route declarations | **165** = 148 workflow + 17 historical/report; source declarations, not expanded runtime/vendor-route count |
| Vue source files fingerprinted | **168** pages/components/layouts, excluding generic UI primitives |
| Laboratory/Cleanroom/Preview static occurrences | **88**: 31 legitimate UAT disclosures, 15 hide-from-ordinary recommendations, 2 audit-only, 40 engineering-only |
| Engineering-vocabulary static occurrences | **127**; identifiers/comments are excluded; ordinary financial preview is not automatically engineering |
| Substantial prose | **165 blocks**, approximately **3,084 static words**; dynamic values/branch multiplicity excluded |
| Prose dispositions | **130 KEEP / 31 SHORTEN / 1 MOVE_TO_HELP / 3 AUDIT_ONLY / 0 REMOVE** |
| Conservative reduction opportunity | Approximately **236 words** moved/shortened from primary context; judgment estimate, not rewritten copy |
| Navigation declarations | **294** = 260 secondary, 16 primary, 16 engineering-only, 1 stale-destination mismatch, 1 duplicate; 4 separately recorded navigation gaps |
| Disabled controls | **101** template declarations; **12 named primary-action families reviewed** below. 93 lexical candidates are not 93 genuine primaries |
| Terminology | **18 concept groups; 15 competing-label/meaning findings** |
| Product findings | **26 = 3 P0 / 19 P1 / 4 P2** |
| Chief Architect decisions | **5 items affecting 5 distinct surfaces**; no permission is inferred from a recommendation |

These are reproducible static inventory counts, not live user counts, screenshot measurements or a proof of authorization leaks. A dynamic `v-for` link is one source declaration, not one link per taxpayer. Descriptor entries and their rendering Link are both recorded, not summed as runtime navigation. Multi-branch prose can concatenate alternatives in extraction. Nested duplicate financial-lock prose was removed. Backend-driven task labels and dynamic readiness are reviewed separately in the role map; this is not an exhaustive census of every possible runtime string.

### Findings by category and priority

| Category | P0 | P1 | P2 |
| --- | ---: | ---: | ---: |
| public_entry | 1 | 0 | 0 |
| navigation | 1 | 3 | 0 |
| engineering_isolation | 1 | 2 | 0 |
| public_copy | 0 | 1 | 0 |
| terminology | 0 | 1 | 0 |
| prose | 0 | 1 | 0 |
| disabled | 0 | 2 | 0 |
| accessibility | 0 | 3 | 0 |
| historical | 0 | 1 | 0 |
| reports | 0 | 2 | 0 |
| responsive | 0 | 1 | 0 |
| branding | 0 | 1 | 0 |
| policy_disclosure | 0 | 1 | 0 |
| dashboard | 0 | 0 | 1 |
| help | 0 | 0 | 1 |
| visual | 0 | 0 | 1 |
| retained_surfaces | 0 | 0 | 1 |

## Public front door and authentication

Two root experiences exist. With preview disabled, `routes/web.php` renders `Welcome.vue`; with preview enabled, it invokes the preview launcher under the configured preview safety/authentication middleware. Normal Welcome has municipality text, register/login, fee catalogue and overview links, but no coherent four-task service entry or generic verification entry. It also says service ends at Ready for Authority Review. That is stale relative to certified synthetic UAT, while its denial of production legal effect remains valid. Do not delete the safety sentence wholesale.

Recommend one small home: Municipality of Ipil → Business Permit and Licensing System → **Apply for a Business Permit / Track or Continue Application / Staff Login / Verify Business Permit**. Apply and Continue naturally reach existing citizen authentication; no separate Citizen Login heading is needed. Staff Login uses the same Fortify backend, with role-appropriate landing—not a new authentication system. Registration is citizen account creation, not staff provisioning. Existing fee catalogue can be a secondary link.

Tracking currently means the authenticated citizen Application list/detail, not an anonymous reference-search endpoint. Public verification already requires exact application/code and has UAT/private restrictions. A front-door Verify affordance should explain/open that existing QR/link path; a new permit-number/public-taxpayer search is **not** authorized. Chief Architect D-2 chooses the bounded affordance.

Fortify provides login, register, reset request/reset token, email verification, password confirmation, 2FA, passkeys and logout. Default post-login fallback is `/dashboard`; intended draft continuation after lodging-session expiry is explicitly preserved in `FortifyServiceProvider`. Shared auth is one architecture; do not split citizen/staff guards merely for labels. Protected web routes require auth/verification/active access and staff/citizen gates; suspended/expired access produces a 403. Historical/report admission is separately constrained. No account/credential changes occurred.

## Navigation, dashboard and Inbox

Inventory includes account links, public links, sidebar descriptors, alternate header, dashboard cards, breadcrumbs, contextual links, document tabs, verification links, Laboratory/retained history, historical directory and report links. The JSON records label/expression, destination/expression, source line and local conditions. `AppSidebar` is already permission-sensitive and provides My Work → Inbox. Settings/logout are account-menu affordances. The alternate AppHeader is inventoried, not asserted active for every layout.

Dashboard lacks an Inbox card. It calls Applications a municipal work queue, chooses Treasury Work by first available capability, sends general Reports to Daily Collections, and duplicates citizen Start/Continue and Track destinations. It does **not** need invented global metrics. Recommend Inbox-first staff work and optional quiet overview using the existing `counts.action_required`; Applications remains lookup. Citizen lands in My Applications with a clear Start action; My Businesses retains its distinct owner relationship semantics.

The [role map](IPIL_ROLE_JOURNEY_MAP.md) traces every requested role plus the distinct Assessment Officer. Source supports BPLO routing, each concerned-office Payment Order and certification, Treasury classification/counter-check, Assessment preparation/schedule, Treasurer decision, Cashier Collection/OR, Mayoral authorization/issuance and release. `BuildMunicipalWorkInbox::item()` already selects the corrected ordinary UAT Permit page and preserves separate cleanroom handling. No new stale Mayoral route defect is established. Empty state and no-active-position state are present. Completed tasks disappear via persisted-state predicates, not frontend timers.

Return-to-Inbox is explicit after routing acknowledgment, but certification/Mayoral pages mainly link to the Application; the sidebar is still available. Recommend an explicit contextual return without changing completion semantics. 'Open Application' is a generic Inbox button even for non-Application targets; contextual labeling is a P1 opportunity. Do not create a second task engine, add fake reviewer tasks or silently introduce a current staff Business Directory. Separate history/report environments cannot be merged by a navigation change.

## Engineering exposure and environment truth

`HandleInertiaRequests` derives `show_engineering_controls` from preview persona. `StakeholderPreviewBanner` and the citizen intake helper check that flag; Laboratory execution middleware requires the Management preview persona plus safety readiness. Users & Access additionally gates laboratory provisioning by capability, installation setting and non-production. Therefore: **no general ordinary-user authorization leak is established by this audit**. The preview-enabled root remains a turnover entry problem requiring disposition.

Keep Laboratory, start/advance/close-retain ceremonies, retained specimens, scenario evidence, test actor switches, office simulation, diagnostic/status and storyboards in a distinct engineering context. No deletion. Ordinary users should not get a permanent engineering entry; specifically authorized testers get one explicit secondary entry with server protection on every route/action. Re-check configured turnover roles rather than assuming all preview personas are ordinary accounts.

Keep small truthful UAT indication and action-specific simulated Collection, provisional enterprise policy, synthetic Mayoral/Permit and no-legal-effect disclosures. 'Preview · Sample Data' repeats in operational report summaries even without a local conditional; future wording must follow actual environment/population, not just disappear. A price preview is a legitimate draft operation, unlike an actor-switch control. Synthetic permit watermark must remain truthful even if its wording is later normalized.

## Pages 1–3, financial screens, duplication and errors

- **Page 1:** Applicant Declaration and preserved facts/documents/oath/signature. The document is the authority for declaration content. Repeated case header identity can stay as orientation; declaration hash and 'frozen evidentiary snapshot' explanation belong in details. Keep statutory text, privacy, submission/signature requirements and exact evidence. Helper fixture controls remain engineering-only.
- **Page 2:** Municipal processing, routing, offices, determinations, Payment Orders and post-payment certification. Remove neither stage nor office. Current fallback copy explains a 'living projection' and canonical records; task summary plus recorded office state is enough. Tabs/summary cards and office detail intentionally repeat some amounts: reduce prose, not authoritative values.
- **Page 3:** Payment Schedule, QR request, Collection and OR continuation. Replace implementation phrases such as 'Receipt projection' only in a later copy wave. Missing receipt must not imply a paid/reconciled outcome. Keep active/expired/request-confirmed distinctions and safe retry guidance. No duplicate payment button or new payment path.
- **Payment Orders/Treasury:** Show item, basis, amount, total, actor and required signature/determination. LOB and enterprise classification remain separate. Provisional bands remain pending municipal confirmation. Preserve finalized office orders; concise aggregate readiness hints should explain why Treasury cannot confirm.
- **Evaluation/Assessment:** Working determinations and frozen Assessment are different facts. Counter-check binds the exact Evaluation version. Assessment already places composition/fingerprints in an Evidence details section—retain this good separation. Do not erase binding information, invent a latest version or conflate Treasurer approval with counter-check.
- **Schedules/Collections/receipts:** Preserve exact totals, allocation identities, current action, immutable financial evidence and simulation disclaimer. Summary versus AF51/detail is intentional; do not remove reconciliation visibility merely because the number repeats. Receipt void remains unavailable, with a visible policy reason proposed.
- **Certifications/Permit/release:** Existing focused ordinary pages show one current action, prerequisites and separate completion facts. Authorization fingerprint can become audit detail; synthetic/legal warning stays. Release does not imply new issuance, and verification must not regress to pre-release availability.

Known source-backed recovery strengths: lodging-session-expired login directs back to Draft; routing has acknowledged versus uncertain result plus reload; Payment Order failure asks for item/signature review; QR image unavailable/expired is distinct from success; certification and Mayoral forms expose errors with `role=alert`; empty history/report results are not assertions of no real-world transaction. Suspended-access 403 could use clearer administrator recovery guidance in a later error-page packet. Preserve backend messages and do not turn missing policy/binding into cosmetic success. No exception or failed network request was provoked in this audit.

### Primary actions and disabled reasons

| Action family | Source classification | Review / proposed treatment |
| --- | --- | --- |
| Sign & Submit | CONTEXT_MAKES_OBVIOUS | Undertaking and signature are adjacent requirements; session-expiry recovery exists. |
| Confirm Routing | VISIBLE_REASON | Selected count, acknowledgment/recovery message and reload control exist. |
| Sign & Confirm Payment Order | CONTEXT_MAKES_OBVIOUS | Fee items plus required signature; error message after failed confirmation. |
| Confirm Treasury Classification | NEEDS_CORRECTION | Enterprise hint exists; explain all unmet treasurySelectionsReady predicates beside primary action. |
| Prepare Assessment | VISIBLE_REASON | Readiness blockers and existing Assessment distinction are presented; preserve exact binding. |
| Treasury counter-check | VISIBLE_REASON | Canonical prerequisites and readiness shown; pending disables request. |
| Municipal Treasurer approval | VISIBLE_REASON | Server availability gates action; no required-counter-check bypass. |
| QR request / simulated Collection | VISIBLE_REASON | Pending/expired state and simulation warning must stay; browser retest later. |
| Issue Official Receipts | CONTEXT_MAKES_OBVIOUS | Form processing; allocation and receipt-number validation remain necessary. |
| Office certification | CONTEXT_MAKES_OBVIOUS | Result/remarks and bound OR presented; pending request disabled. |
| Mayoral Authorization / issue / release | VISIBLE_REASON | Server-supplied single current action with prerequisite list; preserve separate actions. |
| Receipt void | NEEDS_CORRECTION | Unavailable label exists but policy reason is tooltip-only; never enable under unresolved policy. |

These 12 families are a human product review. The 101 declaration scan includes request-in-progress, pagination, secondary and engineering buttons; its `NEEDS_CORRECTION` flags are review candidates, not 25 established blockers. No new P0 gray-button failure is claimed without a reproducing role/state. Preserve disabled logic; do not use tooltip-only critical guidance. Irreversible-action/signature/confirmation recommendations are in the [wave plan](IPIL_GATE_10_IMPLEMENTATION_PLAN.md).

## Historical and reports

The workflow base does not itself contain the separate historical/report implementation. The seven historical/report pages plus associated document/CSV outputs were inspected only in the reporting worktree. Directory, Owner, Business history, Historical Application, financial/payment/receipt claims, permits/clearances and documents remain read-only evidence. Prefer a concise Historical Record boundary, with provenance/source/mapping details available secondarily. Missing associated documents stay missing; no unresolved object attachment or mapping acceptance.

016/004 already have meaningful page titles; directory links also show IDs. 028 remains a standalone restricted surface with independent financial-evidence admission. Propose ordinary names, not report-ID-led navigation. No report semantics, authorization, exact-number display, source population, frozen result or CSV bytes change. 016's event/status/OR-claim meaning, 004's recorded schedule-difference/group/quarter semantics and 028's distinct heterogeneous sources must remain visible enough for correct interpretation.

Independent `R2-016-BROWSER-CSV-CERTIFICATION`, `R2-004-BROWSER-CSV-CERTIFICATION` and the reported 028 browser-byte limitation remain open and unchanged. The 028 engineering packet records desktop 1440×1000 and 390×844 checks including keyboard-contained table scrolling, but is not independent adjudication or deployment approval. This audit neither resolves nor worsens those records. No report is deployment-authorized here.

## Responsive and bounded accessibility review

| Surface | Source/prior evidence | Required future proof; current status |
| --- | --- | --- |
| Home/auth | Responsive grids, max-width auth card, labeled fields; Welcome header has several competing links; login positive tabindex | Desktop + 390×844 wrap, logical keyboard order, intended destination. NOT RUN in 10A. |
| Dashboard/Inbox | min-w-0/grid/wrap; Inbox labeled filters and empty state; long identities truncate | Search/clear/paging and focus at both sizes; counts and task names remain visible. NOT RUN. |
| Pages 1–3 | Dense document layout, sticky navigation; tab roles but no arrow-key handler/panel associations in navigator | Visible active page/action, keyboard traversal, no clipped amount; no document redesign. NOT RUN. |
| Signature | Native dialog, named close button and labeled canvas; pointer-only drawing | Focus entry/trap/return, keyboard-compatible evidence-preserving alternative needs D-3 disposition. No statutory policy invention. |
| Payment Orders/Treasury/Assessment | Grid/min-width and wrap protections; sticky action area, long item names and financial totals | Critical primary controls reachable at 390×844; keep cents and readiness; no horizontal page overflow. NOT RUN. |
| Payment/QR/receipt | Request-state messages, QR image and document layout | QR continuation, expired/uncertain state, all OR allocations and print action; NOT RUN. |
| Certification/Mayoral/Permit | Narrow max-width ordinary pages, break-all identifiers and wrapping action labels | Long prerequisite/OR lists; distinct authorize/issue/release; NOT RUN. |
| History | Responsive directory controls/tables; source identifiers may be long | Search/filters/paging/private document return without overflow. NOT RUN. |
| Reports | 004/028 focusable named scroll regions; 016 wrapper overflow-x-auto but no tabindex/region at that wrapper | Source-a11y opportunity for 016; prior 028 browser proof is not a new cross-report certification. No report edit. |

Headings, labels, named controls, error text, textual state and focus styles exist broadly. Areas needing bounded verification: natural auth tab order, tab/panel semantics, modal focus and pointer-only signature input, generic Back/View links, clipped labels/long identifiers, focusable scroll regions, tooltip-only disabled explanation. Text accompanies many colored statuses; no contrast-ratio or full WCAG claim is made. No newly observed desktop/mobile overflow is claimed; source risk is not runtime failure. Gate 10G must observe exact 390×844 rather than approximate device emulation.

## Branding, help and decisions

Existing AppLogo is an 'Ipil' text tile with municipality/BPLS text, not an authenticated municipal seal. Public assets include favicon SVG/ICO and apple-touch icon; they are not proof of municipal brand authority. Browser title uses page title plus configured VITE_APP_NAME with BPLS fallback; actual deployment environment value was not read. Auth has text municipal identity. Preview/synthetic banners are separate from identity; do not replace truth with branding. No new assets downloaded. **MUNICIPAL_ASSET_REQUIRED** if an official seal is desired; text identity is usable meanwhile.

Use label → short hint → recovery → optional guide. Keep critical/actionable warnings in the task; audit details hold provenance; engineering documentation holds compiler/manifest architecture. Do not create a paragraph to compensate for every unclear navigation choice.

| Decision | Surface | Chief Architect disposition requested | Recommended boundary |
| --- | --- | --- | --- |
| D-1 | SURF-61 | Approve ordinary home as turnover entry while retaining private preview protection and a separate authorized engineering entry. | Yes; no public-access expansion. |
| D-2 | SURF-34 | Approve QR/exact verification-link entry rather than inventing public permit-number or taxpayer search. | Existing verification contract only; any new anonymous lookup requires separate privacy authority. |
| D-3 | SURF-30 | Commission accessible signature-facsimile input preserving the same evidence contract? | Bounded accessibility assessment; no change to statutory authority or submission prerequisites. |
| D-4 | SURF-76 | Which separately authorized historical/report environment and accepted reports belong in the turnover guide? | Separate destinations/admissions; no workflow/history merge, no report deployment by implication. |
| D-5 | SURF-2 | Is an authoritative seal required for formal UAT? | Text municipality identity now; MUNICIPAL_ASSET_REQUIRED if seal is commissioned. |

Decisions gate the affected future subtask, not permission to implement now. No finding requires changing the certified financial/lifecycle semantics to complete this audit. If the signature or verification proposal would require new legal/privacy authority, stop that subtask; do not invent it.

## Prioritized findings

| ID | Priority / category | Finding | Recommendation / source |
| --- | --- | --- | --- |
| F-01 | P0 / public_entry | Home lacks task-oriented Apply/Continue/Staff/Verify entry; generic register/login dominate. | Rewrite front door using existing authenticated continuation; do not invent anonymous taxpayer tracking. Source: `workflow:resources/js/pages/Welcome.vue:19`. |
| F-02 | P0 / navigation | Staff default landing has no Inbox card; Applications described as work queue and Treasury Work picks first permitted area, not assigned task. | Inbox-first landing/card with existing server task counts; preserve authorized lookup. Source: `workflow:resources/js/pages/Dashboard.vue:50`. |
| F-03 | P0 / engineering_isolation | Preview-enabled root is the role/laboratory launcher, unsuitable as the normal turnover front door. Exposure is configuration-dependent, not proof of unauthorized access. | Chief Architect chooses ordinary turnover entry while keeping private preview safeguards and protected engineering entry. Source: `workflow:routes/web.php:93`. |
| F-04 | P1 / public_copy | Welcome ends service at Ready for Authority Review although certified synthetic UAT proceeds to release. Production legal-effect denial remains true. | Use environment-accurate lifecycle wording without asserting production authority. Source: `workflow:resources/js/pages/Welcome.vue:161`. |
| F-05 | P1 / navigation | Reports dashboard shortcut opens Daily Collections whereas sidebar opens report catalogue. | Make general Reports destination catalogue; retain Daily Collections as named secondary shortcut. Source: `workflow:resources/js/pages/Dashboard.vue:99`. |
| F-06 | P1 / navigation | Citizen Start/Continue and Track cards use the same applications index. | Consolidate into My Applications with clear Start action, not a second tracking system. Source: `workflow:resources/js/pages/Dashboard.vue:134`. |
| F-07 | P1 / engineering_isolation | Intake fixtures and cleanroom helper are already conditional on show_engineering_controls. | Preserve gating, remove from ordinary role journeys; do not call this confirmed ordinary leakage. Source: `workflow:resources/js/pages/permit-applications/Create.vue:890`. |
| F-08 | P1 / engineering_isolation | Laboratory provisioning is shown in Users & Access when capability allows. | Place in separate server-authorized engineering entry, no permission expansion. Source: `workflow:resources/js/pages/users/Access.vue:142`. |
| F-09 | P1 / terminology | Business Permit Evaluator / canonical Evaluation / immutable Assessment wording burdens task understanding. | Keep Evaluation and Assessment distinct; plain task label first, technical evidence in details. Source: `workflow:resources/js/pages/business-permit-evaluations/Show.vue:1509`. |
| F-10 | P1 / prose | Fallback Application page explains living projection/canonical records and Receipt projection. | Move architecture to audit/help while preserving amounts and missing evidence. Source: `workflow:resources/js/components/permit-applications/ExecutableApplication.vue:847`. |
| F-11 | P1 / disabled | Void unavailable explains unresolved policy only in title tooltip before submission. | Visible concise policy reason; never enable voiding. Source: `workflow:resources/js/pages/receipts/Show.vue:167`. |
| F-12 | P1 / disabled | Treasury confirmation combines multiple readiness predicates; enterprise hint exists but aggregate failure reason is not adjacent to button. | Map existing unmet predicates to concise visible reasons; treat promotion to P0 as role-state browser finding. Source: `workflow:resources/js/components/permit-applications/BploRoutingTaskSheet.vue:980`. |
| F-13 | P1 / accessibility | ApplicationDocumentNavigator declares tablist/tab but lacks arrow-key handling and explicit panel associations. | Verify keyboard behavior; implement accessible tab semantics only in later authorized wave. Source: `workflow:resources/js/components/permit-applications/ApplicationDocumentNavigator.vue:52`. |
| F-14 | P1 / accessibility | Login has positive tabindex values; may reorder passkey and form navigation. | Validate natural focus order in 10F; no auth redesign. Source: `workflow:resources/js/pages/auth/Login.vue:65`. |
| F-15 | P1 / historical | Rescue/source/canonical mapping language appears in ordinary history; boundaries must remain. | Short read-only boundary, optional audit detail; no historical mapping changes. Source: `reporting:resources/js/pages/ipil-history/Index.vue:406`. |
| F-16 | P1 / reports | 016 overflow table wrapper lacks keyboard focus support present in 004/028. | Record future presentation/a11y proposal only; do not change accepted reports in this gate. Source: `reporting:resources/js/pages/ipil-history/reports/PaymentEvents.vue:300`. |
| F-17 | P1 / reports | Directory links carry report IDs; report pages already have meaningful titles. 028 has separate admission. | Names prominent, IDs retained in metadata; no shared entitlement or deployment. Source: `reporting:resources/js/pages/ipil-history/Index.vue:200`. |
| F-18 | P1 / navigation | Certification and Mayoral completion link to Application, not explicit Return to Inbox. Global sidebar remains available. | Add contextual return only, not a new action or task queue. Source: `workflow:resources/js/pages/post-payment-certifications/Show.vue:40`. |
| F-19 | P1 / responsive | Dense document sheets, sticky tabs and financial columns are high risk on 390×844; no new overflow verified in this audit. | Test exact viewport, keyboard and no page overflow in 10F/10G before calling pass. Source: `workflow:resources/js/components/permit-applications/IpilExecutableDocument.vue:234`. |
| F-20 | P1 / branding | Text identity exists; no authoritative municipal seal found in public asset inventory. | MUNICIPAL_ASSET_REQUIRED if seal requested; use existing text identity meanwhile. Source: `workflow:resources/js/components/AppLogo.vue:1`. |
| F-21 | P1 / policy_disclosure | Synthetic authority, provisional enterprise bands and historical/report limitations are indispensable. | Retain truthful disclosures; no production-readiness implication. Source: `workflow:resources/js/pages/ordinary-uat-permit/Show.vue:38`. |
| F-22 | P2 / dashboard | No general analytics needed for role work discovery. | Do not add unsupported global counts; optional analytics after turnover. Source: `workflow:resources/js/pages/Dashboard.vue:179`. |
| F-23 | P2 / help | Repeated architecture explanation can live in optional guide/audit details. | Small help hierarchy, not new help platform. Source: `workflow:resources/js/pages/business-permit-evaluations/Show.vue:2122`. |
| F-24 | P2 / visual | Visual consistency beyond task clarity is optional. | No broad document redesign. Source: `workflow:resources/js/components/permit-applications/ExecutableApplication.vue:730`. |
| F-25 | P1 / accessibility | Signature capture uses pointer-only canvas; no alternative input in this component. Modal exists but focus/assistive input not certified. | Chief Architect must commission accessible facsimile interaction without weakening signature/undertaking evidence. Do not invent statutory signature policy. Source: `workflow:resources/js/components/SignatureFacsimileCapture.vue:219`. |
| F-26 | P2 / retained_surfaces | Three retained Vue pages are no longer rendered by current controllers; route redirects or Access replacement apply. | Keep inventoried as non-routed; no deletion in Gate 10A and do not count as live surfaces. Source: `workflow:app/Http/Controllers/Staff/UserDirectoryController.php:24`. |

P0 is restricted to coherent service entry, ordinary work discovery and preview-root turnover separation. P1 includes source-copy/terminology, contextual navigation, disabled reasons, accessibility and disclosure handling. P2 remains optional analytics/help/cosmetic refinement/unused-page review; it cannot delay turnover. Suspected dynamic or configuration exposure is labeled as such, not escalated to a fabricated security failure.

## Anaïs, formal UAT and bounded next waves

**MUST before Anaïs:** settle ordinary/private front door and engineering separation; make Inbox discoverable and verify every normal role/task; correct contradictory endpoint wording; verify meaningful primary actions at desktop/390×844; deliver private access instructions and short role guide; authorize acceptance evidence separately without mutating retained specimens.

**SHOULD before Anaïs:** concise task terminology/prose, Return to Inbox, Treasury readiness and receipt-void explanation, keyboard/focus checks and explicit signature accessibility disposition.

**CAN WAIT until formal Ipil UAT:** final officer-label preferences, authoritative branding if required, separately authorized report selection/CSV certification and history/report copy. Optional analytics/visual polish can wait beyond formal UAT. Account availability and truthful UAT disclosures cannot wait. Municipal policy feedback does not activate provisional rates or production authority.

Proposed waves: **10B** home/auth entry; **10C** navigation/role landings; **10D** engineering isolation (must be completed before exposing turnover entry); **10E** prose and terminology; **10F** responsive/a11y/disabled-reason polish; **10G** role-by-role browser acceptance; **10H** guide and turnover package. The [implementation plan](IPIL_GATE_10_IMPLEMENTATION_PLAN.md) specifies ownership proposals, acceptance, exclusions, deliberate-action doctrine and stop conditions. **No wave is started or deployment-authorized.**

Production cutover, historical seeding/media transfer, Cloud production deployment, fee policy, OR stock, real Mayoral authority, statutory signature policy, backup/DR, rollback rehearsal and production provisioning remain outside this gate.

## Verification and preservation

- Clean dedicated baseline created with user approval; only the five requested docs are added. No integration into main.
- `git diff --exit-code f8a08ec28e2dfbb856bc0d42ceb9b587fcad34e6 -- app routes config resources database tests` passes; application behavior, routes, authorization and tests unchanged.
- Protected workflow Git trees: app `02df8a52dab93e37781da9315e75cd40ae8e13de`; routes `ef42df0fc412389f4456cd4b4c1f2f53aa4ae3b2`; resources `0dc75d772badfe9ca8110be2b57f9bda7e7bf27d`; config `1ad8e171cfbd1b2ffa203a15dde7383b0f7f4be6`.
- Reporting HEAD remains `953fe4ccf1bd09652516e82710b8c125a5b71884`, with no working-tree changes. Report code, contracts, evidence identities, accepted fingerprints and deferred records untouched.
- No data connections/commands, seed, mutation, browser action, source access, private taxpayer/media reads, push or deployment. Applications 285–291 and Assessment 214 were not accessed or changed by this work. This is action non-interference, **not a fresh database-hash attestation**.
- Verification passed: 195 programmed consistency/hash/count assertions; all 168 Vue source SHA-256 bindings match; 19 local Markdown links resolve; surface dispositions, priorities, unique IDs and source paths validate. Privacy scan found no email addresses, private-key blocks, bearer credentials or Convex deployment keys; manual review found only repository source copy/paths and aggregate findings, not taxpayer rows. Documentation whitespace is checked before commit. No unrelated full suite is justified for documentation-only changes.

### Reproduce metrics without booting the application

From this worktree, the following read-only Node snippet recomputes the primary totals. Group the corresponding arrays by category/priority/disposition for the detailed tables; expected values are in `metrics`. Every source-copy/navigation/disabled item has a baseline, path and line. `source_files` supplies SHA-256 for the 168 scanned Vue files; match each baseline to the exact approved checkout before verifying hashes.

```sh
node -e 'const r=require("./docs/productization/IPIL_PRODUCT_SURFACE_REGISTER.json"); console.log({surfaces:r.surfaces.length+r.supplementary_surfaces.length,routes:r.route_declarations.length,navigation:r.navigation.length,vocabulary:r.vocabulary.length,engineering:r.engineering_exposure.length,prose:r.prose.length,words:r.prose.reduce((n,x)=>n+x.approximate_words,0),reduction:r.prose.reduce((n,x)=>n+x.estimated_reduction_words,0),disabled:r.disabled_controls.length,primary:r.primary_action_review.length,findings:r.findings.length,decisions:r.decisions.length})'
```

Reclassification is a review decision, not a mechanical fact. Do not use source occurrence totals as deployed exposure counts. Re-running source extraction requires the same two baselines, sorted tracked Vue paths excluding generic `components/ui`, Vue template text/literal attributes, the declared >=12-word substantial-block rule and documented human exclusions. Runtime/authenticated acceptance remains 10G.

## Complete page-component register (source census)

Route and controller source candidates, action labels, headings, navigation/prose/exposure references and disposition are in the JSON. Three retained non-routed pages are deliberately included rather than silently discarded. Auth/setting pages are shared but counted once.

| ID | Category | Page | Route / mapping | Disposition; priority |
| --- | --- | --- | --- | --- |
| SURF-1 | municipal_staff_shared | `Dashboard.vue` | /dashboard | SIMPLIFY; P0 |
| SURF-2 | public_auth | `Welcome.vue` | / | REWRITE; P0 |
| SURF-3 | public_auth | `auth/ConfirmPassword.vue` | /user/confirm-password | KEEP; P2 |
| SURF-4 | public_auth | `auth/ForgotPassword.vue` | /forgot-password | KEEP; P2 |
| SURF-5 | public_auth | `auth/Login.vue` | /login | KEEP; P2 |
| SURF-6 | public_auth | `auth/Register.vue` | /register | KEEP; P2 |
| SURF-7 | public_auth | `auth/ResetPassword.vue` | /reset-password/{token} | KEEP; P2 |
| SURF-8 | public_auth | `auth/TwoFactorChallenge.vue` | /two-factor-challenge | KEEP; P2 |
| SURF-9 | public_auth | `auth/VerifyEmail.vue` | /email/verify | KEEP; P2 |
| SURF-10 | municipal_staff_shared | `billing-groups/Index.vue` | /staff/billing-groups | KEEP; P2 |
| SURF-11 | municipal_staff_shared | `billing-groups/Show.vue` | /staff/billing-groups/{billingGroup} | KEEP; P2 |
| SURF-12 | municipal_staff_shared | `business-permit-evaluations/Show.vue` | /staff/permit-applications/{permitApplication}/evaluation | SIMPLIFY; P1 |
| SURF-13 | citizen | `citizen/businesses/Show.vue` | /citizen/businesses/{business} | KEEP; P2 |
| SURF-14 | citizen | `citizen/notifications/Index.vue` | /citizen/notifications | KEEP; P2 |
| SURF-15 | citizen | `citizen/payment-schedules/Show.vue` | /citizen/payment-schedules/{paymentSchedule} | KEEP; P2 |
| SURF-16 | citizen | `citizen/permit-applications/Index.vue` | /citizen/permit-applications | KEEP; P2 |
| SURF-17 | citizen | `citizen/permit-applications/Show.vue` | /citizen/permit-applications/{permitApplication} | KEEP; P2 |
| SURF-18 | citizen | `citizen/profile/Identity.vue` | /citizen/profile/identity | KEEP; P2 |
| SURF-19 | citizen | `citizen/profile/Show.vue` | /citizen/profile | KEEP; P2 |
| SURF-20 | citizen | `citizen/services-and-fees/Index.vue` | NO CURRENT RENDER; /citizen/services-and-fees redirects to /services-and-fees | AUDIT_ONLY; P2 |
| SURF-21 | municipal_staff_shared | `fee-rules/Index.vue` | /staff/fee-rules | KEEP; P2 |
| SURF-22 | municipal_staff_shared | `fee-rules/LegacyCandidates.vue` | /staff/fee-rules/legacy-candidates | KEEP; P2 |
| SURF-23 | municipal_staff_shared | `fee-rules/Show.vue` | /staff/fee-rules/{feeRule} | KEEP; P2 |
| SURF-24 | municipal_staff_shared | `municipality/Index.vue` | /staff/municipality-configuration | KEEP; P2 |
| SURF-25 | municipal_staff_shared | `ordinary-uat-permit/Show.vue` | /staff/permit-applications/{permitApplication}/uat-permit | SIMPLIFY; P1 |
| SURF-26 | municipal_staff_shared | `payment-schedules/Index.vue` | /staff/payment-schedules | KEEP; P2 |
| SURF-27 | municipal_staff_shared | `payment-schedules/Show.vue` | /staff/payment-schedules/{paymentSchedule} | KEEP; P2 |
| SURF-28 | municipal_staff_shared | `permit-applications/Assessments/Index.vue` | /staff/permit-applications/assessments | KEEP; P2 |
| SURF-29 | municipal_staff_shared | `permit-applications/Assessments/Show.vue` | /staff/assessments/{assessment} | KEEP; P1 |
| SURF-30 | citizen | `permit-applications/Create.vue` | /citizen/permit-applications/create  /  /citizen/permit-applications/{permitApplication}/edit  /  /staff/permit-applications/create | SIMPLIFY; P1 |
| SURF-31 | municipal_staff_shared | `permit-applications/Index.vue` | /staff/permit-applications | KEEP; P2 |
| SURF-32 | municipal_staff_shared | `permit-applications/Show.vue` | /staff/permit-applications/{permitApplication} | SIMPLIFY; P1 |
| SURF-33 | municipal_staff_shared | `post-payment-certifications/Show.vue` | /staff/post-payment-certifications/{certification} | KEEP; P2 |
| SURF-34 | public_auth | `public/PermitVerification.vue` | /permits/verify/{permitApplication}/{verificationCode}/view | SIMPLIFY; P1 |
| SURF-35 | public_auth | `public/ServicesAndFees.vue` | /services-and-fees | KEEP; P2 |
| SURF-36 | municipal_staff_shared | `receipts/Index.vue` | /staff/receipts | KEEP; P2 |
| SURF-37 | municipal_staff_shared | `receipts/Show.vue` | /staff/receipts/{receipt} | SIMPLIFY; P1 |
| SURF-38 | municipal_staff_shared | `reports/AllAbstract.vue` | /staff/reports/all-abstract | KEEP; P2 |
| SURF-39 | municipal_staff_shared | `reports/AnnexCDnfbp.vue` | /staff/reports/annex-c-dnfbp | KEEP; P2 |
| SURF-40 | municipal_staff_shared | `reports/AssessmentSummary.vue` | /staff/reports/assessment-summary | KEEP; P2 |
| SURF-41 | municipal_staff_shared | `reports/BillingGroupAbstract.vue` | /staff/reports/billing-groups/{billingGroup}/abstract | KEEP; P2 |
| SURF-42 | municipal_staff_shared | `reports/BreakdownOfCollectibles.vue` | /staff/reports/collectibles | KEEP; P2 |
| SURF-43 | municipal_staff_shared | `reports/Bsp.vue` | /staff/reports/bsp | KEEP; P2 |
| SURF-44 | municipal_staff_shared | `reports/BusinessTaxByMajorType.vue` | /staff/reports/business-tax-by-major-type | KEEP; P2 |
| SURF-45 | municipal_staff_shared | `reports/CmciLdcs.vue` | /staff/reports/cmci-ldcs | KEEP; P2 |
| SURF-46 | municipal_staff_shared | `reports/DailyCollections.vue` | /staff/reports/daily-collections | KEEP; P2 |
| SURF-47 | municipal_staff_shared | `reports/Index.vue` | /staff/reports | KEEP; P2 |
| SURF-48 | municipal_staff_shared | `reports/PaidEstablishments.vue` | /staff/reports/paid-establishments | KEEP; P2 |
| SURF-49 | municipal_staff_shared | `reports/PaymentSummary.vue` | /staff/reports/payment-summary | KEEP; P2 |
| SURF-50 | municipal_staff_shared | `reports/Plds.vue` | /staff/reports/plds | KEEP; P2 |
| SURF-51 | municipal_staff_shared | `reports/RevenueSources.vue` | /staff/reports/revenue-sources | KEEP; P2 |
| SURF-52 | municipal_staff_shared | `reports/TaxpayerAccountCard.vue` | /staff/reports/taxpayer-account-card | KEEP; P2 |
| SURF-53 | municipal_staff_shared | `reports/TopEstablishmentsTaxDue.vue` | /staff/reports/top-establishments-tax-due | KEEP; P2 |
| SURF-54 | municipal_staff_shared | `reports/TotalCapitalGrossSummary.vue` | /staff/reports/total-capital-gross-summary | KEEP; P2 |
| SURF-55 | municipal_staff_shared | `reports/UnpaidEstablishments.vue` | /staff/reports/unpaid-establishments | KEEP; P2 |
| SURF-56 | municipal_staff_shared | `roles/Index.vue` | /staff/roles | KEEP; P2 |
| SURF-57 | municipal_staff_shared | `services-and-fees/Internal.vue` | NO CURRENT RENDER; /staff/services-and-fees redirects to fee-rules or dashboard | AUDIT_ONLY; P2 |
| SURF-58 | municipal_staff_shared | `settings/Appearance.vue` | /settings/appearance | KEEP; P2 |
| SURF-59 | municipal_staff_shared | `settings/Profile.vue` | /settings/profile | KEEP; P2 |
| SURF-60 | municipal_staff_shared | `settings/Security.vue` | /settings/security | KEEP; P2 |
| SURF-61 | engineering_test | `stakeholder-preview/Launcher.vue` | / (preview enabled) | REQUIRES_PRODUCT_DECISION; P0 |
| SURF-62 | engineering_test | `stakeholder-preview/LifecycleApplication.vue` | /stakeholder-preview/lifecycle-laboratory/specimens/{lifecycleScenarioSpecimen}/application  /  /stakeholder-preview/lifecycle-laboratory/cleanrooms/{lifecycleCleanroomRun}/application | ENGINEERING_ONLY; P1 |
| SURF-63 | engineering_test | `stakeholder-preview/LifecycleCleanroomEvidence.vue` | /stakeholder-preview/lifecycle-laboratory/cleanrooms/{lifecycleCleanroomRun}/evidence | ENGINEERING_ONLY; P1 |
| SURF-64 | engineering_test | `stakeholder-preview/LifecycleCleanroomStatus.vue` | /stakeholder-preview/lifecycle-laboratory/cleanrooms/{lifecycleCleanroomRun}/status | ENGINEERING_ONLY; P1 |
| SURF-65 | engineering_test | `stakeholder-preview/LifecycleLaboratory.vue` | /stakeholder-preview/lifecycle-laboratory | ENGINEERING_ONLY; P1 |
| SURF-66 | engineering_test | `stakeholder-preview/OfficeReviewsAssigned.vue` | /stakeholder-preview/lifecycle-laboratory/cleanrooms/{lifecycleCleanroomRun}/office-reviews-assigned/{applicationYear} | ENGINEERING_ONLY; P1 |
| SURF-67 | engineering_test | `stakeholder-preview/Walkthrough.vue` | /stakeholder-preview/walkthrough | ENGINEERING_ONLY; P1 |
| SURF-68 | engineering_test | `stakeholder-preview/Workflow.vue` | /stakeholder-preview/workflow | ENGINEERING_ONLY; P1 |
| SURF-69 | engineering_test | `storyboards/Edit.vue` | /staff/storyboards/create  /  /staff/storyboards/{storyboard}/edit | ENGINEERING_ONLY; P1 |
| SURF-70 | engineering_test | `storyboards/Index.vue` | /staff/storyboards | ENGINEERING_ONLY; P1 |
| SURF-71 | municipal_staff_shared | `users/Access.vue` | /staff/users (controller-render branch) | MOVE; P1 |
| SURF-72 | municipal_staff_shared | `users/Index.vue` | NO CURRENT RENDER; /staff/users renders users/Access | AUDIT_ONLY; P2 |
| SURF-73 | municipal_staff_shared | `work-inbox/Index.vue` | /staff/work | KEEP; P1 |
| SURF-74 | historical_report | `ipil-history/Application.vue` | /staff/ipil-history/applications/{application} | SIMPLIFY; P1 |
| SURF-75 | historical_report | `ipil-history/Business.vue` | /staff/ipil-history/businesses/{business} | SIMPLIFY; P1 |
| SURF-76 | historical_report | `ipil-history/Index.vue` | /staff/ipil-history | SIMPLIFY; P1 |
| SURF-77 | historical_report | `ipil-history/Owner.vue` | /staff/ipil-history/owners/{owner} | SIMPLIFY; P1 |
| SURF-78 | historical_report | `ipil-history/reports/PaymentEvents.vue` | /staff/ipil-history/reports/016  /  /staff/ipil-history/reports/016/{result} | SIMPLIFY; P1 |
| SURF-79 | historical_report | `ipil-history/reports/ScheduleBalances.vue` | /staff/ipil-history/reports/004  /  /staff/ipil-history/reports/004/{result} | SIMPLIFY; P1 |
| SURF-80 | historical_report | `ipil-history/reports/TransactionRegister.vue` | /staff/ipil-history/reports/028  /  /staff/ipil-history/reports/028/{result} | SIMPLIFY; P1 |

### Supplementary embedded/output/account surfaces

| ID | Surface | Route / parent | Disposition; priority |
| --- | --- | --- | --- |
| SUP-1 | Page 1 — Applicant declaration | /citizen/permit-applications/{permitApplication} (Application tab) | SIMPLIFY; P1 |
| SUP-2 | Page 2 — Municipal processing | /staff/permit-applications/{permitApplication} (Processing tab) | SIMPLIFY; P1 |
| SUP-3 | Page 3 — Payment | /citizen/permit-applications/{permitApplication} (Payment tab) | SIMPLIFY; P1 |
| SUP-4 | Citizen evidence download | /citizen/permit-applications/{permitApplication}/documents/{document}/download | KEEP; P2 |
| SUP-5 | Staff evidence view/download | /staff/permit-applications/{permitApplication}/documents/{document}/view  /  /download | KEEP; P2 |
| SUP-6 | Historical private document | /staff/ipil-history/businesses/{business}/documents/{document} | KEEP; P1 |
| SUP-7 | Application PDF | /staff/permit-applications/{permitApplication}/application-form.pdf | KEEP; P2 |
| SUP-8 | Assessment PDF | /staff/assessments/{assessment}/pdf | KEEP; P2 |
| SUP-9 | Receipt PDF | /staff/receipts/{receipt}/pdf | KEEP; P2 |
| SUP-10 | Permit PDF | /staff/permit-applications/{permitApplication}/permit.pdf | KEEP; P2 |
| SUP-11 | Report 016 frozen CSV | /staff/ipil-history/reports/016/{result}/csv | KEEP; P1 |
| SUP-12 | Report 004 frozen CSV | /staff/ipil-history/reports/004/{result}/csv | KEEP; P1 |
| SUP-13 | Report 028 frozen CSV | /staff/ipil-history/reports/028/{result}/csv | KEEP; P1 |
| SUP-14 | Logout | POST /logout | KEEP; P2 |
| SUP-15 | QR Ph status continuation | /citizen/payment-schedules/{paymentSchedule}/qr-ph/status  /  /staff/payment-schedules/{paymentSchedule}/qr-ph/status | KEEP; P1 |

## Gate disposition

Gate 10A establishes the source-backed surface map, gaps, bounded priorities and implementation packets. It does not certify the product polished, every conditional state browser-tested, production-ready or deployed. D-1–D-5 remain explicit choices for their later packets. The next action is Chief Architect disposition of a bounded 10B/10C/10D sequence, not automatic execution.

**GATE 10A: PASS — PRODUCTIZATION IMPLEMENTATION WAVES READY**
