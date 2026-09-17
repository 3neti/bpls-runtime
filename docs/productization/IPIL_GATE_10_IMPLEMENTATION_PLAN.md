# Gate 10 — Productization and turnover readiness

Date: 2026-09-16. **Plan only. Every implementation wave requires separate Chief Architect authorization.**

North star: **The system works. Now make the software get out of the user's way.** Anaïs should need only the UAT URL, authorized access instructions and turnover guide—not engineering chat, Laboratory, database access or hidden routes.

Governing packet: [audit](IPIL_GATE_10A_PRODUCT_SURFACE_AUDIT_2026_09_16.md), [register](IPIL_PRODUCT_SURFACE_REGISTER.json), [terminology](IPIL_UI_TERMINOLOGY_REGISTER.md), [role journeys](IPIL_ROLE_JOURNEY_MAP.md). Gate 9 baseline `f8a08ec28e2dfbb856bc0d42ceb9b587fcad34e6` is protected. Reports are inspected at separate baseline `953fe4ccf1bd09652516e82710b8c125a5b71884`, not integrated by this plan.

## Non-negotiable boundary

Citizen → Application → Lodge → BPLO Routing → Concerned-Office Payment Orders → Treasury Classification → Evaluation → immutable Assessment → Treasury Counter-check → Municipal Treasurer approval → Payment Schedule → QR Ph request → Cashier Collection → all Official Receipts → post-payment certifications → Mayoral Authorization → Permit issuance → separate BPLO release → public identity verification.

No presentation wave may remove a ceremony, replace canonical readiness, recalculate money, manufacture authority or turn historical evidence operational. Preserve Applications 285–291 and Assessment 214. Do not re-use them for new mutations without explicit authority. Retain Laboratory and evidence, but separate engineering access. Authentication/policy remains server-side; hiding a link or CSS is not authorization.

Reports 004/016/028, their evidence identities, exact-money rules, frozen-result contracts and independent browser-CSV certification records are protected. No report deployment or cross-environment data union. Gate 9 Application 291 is not a historical report member.

## Ordered bounded waves

The initial outline separated terminology as 10F. This plan follows the commission's later detailed decomposition: terminology joins copy in 10E; 10F is product/accessibility polish; 10G acceptance; 10H packages turnover. Perform 10D isolation before exposing the new turnover front door, even if its engineering packet follows 10C. These are separately reviewable changes, not one broad redesign.

| Wave | Bounded ownership proposal | Required result and checks | Explicit exclusions |
| --- | --- | --- | --- |
| **10B — Public home and authentication entry** | Welcome/public-entry presentation, auth entry labels, focused navigation tests; any route/controller change separately named in commission | Four clear tasks: Apply, Track/Continue, Staff Login, Verify. Existing login/registration/reset/2FA/passkeys retained. Intended draft continuation survives authentication. Verify uses existing exact-link/QR contract; no anonymous taxpayer lookup. Private UAT remains private. Test guest/citizen/staff/denied, desktop and 390×844, keyboard/focus. | No new authentication architecture, access grant, legal-validity assertion, marketing site or invented seal. Resolve D-1/D-2 first. |
| **10C — Navigation and role surfaces** | Sidebar, dashboard, contextual return links and presentation-level landing dispatch; approved role-route tests | Inbox-first ordinary municipal landing; Applications for lookup. Correct general Reports shortcut; consolidate citizen duplicate cards. Retain multi-role intended destinations. Each role sees useful authorized navigation only. Keep canonical Inbox `action_url`, readiness and corrected ordinary permit handoff. Return to Inbox after work. | No new queues, current Business Directory invention, historical/current merge, task synthesis, role provisioning or report rollout. D-4 governs separate reviewer links. |
| **10D — Engineering isolation** | Explicitly commissioned engineering entry and ordinary shell exposure checks; existing middleware/policy tests | No ordinary citizen/officer Laboratory entry, fixture loader, role switch or provision-test-actors control. Engineering user retains explicit protected access and retained evidence. Test direct deep routes/actions denied for ordinary users, authorized for assigned testing context. Preserve current preview safety prerequisites and private restriction. | No deletion of Laboratory, stored specimens, cleanroom logic or broad auth rewrite. Do not remove UAT/synthetic disclosures. Existing conditional gating is a baseline, not a defect to bypass. |
| **10E — Copy and terminology** | Named ordinary Vue components and labels from prose/terminology register; snapshot/presentation tests | Task-first headings; concise errors and missing-prerequisite reasons. Page 1 declaration, Page 2 processing, Page 3 payment remain distinct. Move architecture/provenance into audit details. Correct environment-specific Welcome endpoint wording. Financial and policy disclosures survive review. | No domain-class/enum rename, formula/rate changes, financial display rounding change, report/history contract changes or reinterpretation of printed statutory text. |
| **10F — Product/accessibility polish** | Named tabs, layout containers, action hints, focus/label/table fixes; focused frontend/browser tests | Desktop and exactly 390×844: no page-level overflow, reachable actions, contained wide tables, readable long IDs/amounts, visible disabled reasons. Keyboard tab/focus semantics, modal focus return, labels/errors and status not color-only. Prioritize confirmed task barriers, not cosmetic preference. | No wholesale Application-document redesign. D-3 gates accessible signature alternatives; cannot weaken signature evidence or invent statutory authority. Report-specific presentation changes require separate report-owner authorization. |
| **10G — Turnover role acceptance** | Testing Adjudicator's commissioned browser/evidence packet | Complete role matrix from normal login with no Laboratory/direct-route coaching; assigned task → canonical destination → action → return. Preserve all financial/readiness invariants. Verify exact 390×844 and desktop, no origin console/network errors, unauthorized routes remain denied. | No automatic replay/mutation of protected cases. No new specimen until explicitly authorized; stop first genuine product failure. Infrastructure limitation is NOT product PASS or permission to change product. |
| **10H — Turnover package** | Versioned guide, known limitations, access handoff procedure and support contacts approved separately | Anaïs receives UAT URL/access instructions privately, role-task guide, environment/disclosure explanation, known-deferred items and exact accepted release identity. No secrets in Git. | Not production cutover or implicit production policy. |

Every later packet must name exact base/owned files, independent source/evidence references, acceptance steps and deployment scope. The integrator alone reconciles the divergent workflow and main/report branches. Do not deploy the audit branch or silently cherry-pick reporting work.

## Disabled and irreversible-action doctrine

A disabled primary action has an obvious adjacent prerequisite or a concise visible reason. Tooltips are not the only explanation for essential prerequisites. Keep server checks; do not make buttons clickable to hide an unclear state.

| Ceremony | Minimum deliberate presentation | No extra ceremony proposal |
| --- | --- | --- |
| Lodge | Review declaration, explicit undertaking/signature, clear final Sign & Submit; safe session-expired recovery | No second blanket confirmation if signature/undertaking already provides deliberate submission. |
| BPLO routing | Selected-office count and explicit Confirm Routing; acknowledged/uncertain outcomes cannot be resubmitted blindly | Preserve existing reload/Return to My Work rather than new modal. |
| Final Payment Order | Reviewed fee items/total and required signature; pending indicator | Preserve Sign & Confirm, not duplicate dialogs. |
| Treasury | LOB plus separate enterprise determination; provisional policy visible; exact subtotal; explicit confirmation | No auto-selection/auto-confirmation or inferred classification. |
| Assessment preparation | Complete prerequisites, amount, immutable effect and exact Evaluation binding | Clear primary action; no recalculation or reconstruction during presentation. |
| Counter-check / Treasurer decision | Exact Assessment and source version; distinct counter-check and approval; return reason | Never merge these decisions or bypass incomplete state. |
| QR / simulated Collection | Distinguish request from Collection; active/expired state; simulated/no real funds disclosure | Do not add a second collection path or encourage retry after uncertain success. |
| OR issuance | Allocation totals, exact receipt identities/number authority and explicit issue action | Never fabricate stock or auto-issue missing receipts. |
| Office certification | Bound office/OR/amount, result, remarks and explicit confirm | No redundant sign-off invented. |
| Mayoral Authorization | Synthetic authority scope, all prerequisites, explicit authorization | Preserve separate authorize/issue/release actions. |
| Permit issuance / release | Existing authority and issued/released facts; named actor action; no legal-effect overclaim | No “Finish all” combined action. |
| Account deletion / receipt void | Destructive warning and existing protections; void remains unavailable under unresolved policy | Neither operation is tested or enabled by this plan. |

## Before Anaïs

### MUST COMPLETE BEFORE ANAÏS

- Close F-01/F-02/F-03: coherent task entry, Inbox discovery and ordinary-vs-engineering front door. Confirm target's preview/private mode instead of assuming all static branches render.
- Verify ordinary users cannot see/use test provisioning, actor switching or specimen-loading controls; deep-route denial must remain.
- Resolve inconsistent endpoint wording without removing no-production-authority disclosure.
- Verify every required primary action has usable prerequisites at desktop and 390×844; promote a confirmed unavailable/unexplained critical action to P0 and stop until corrected.
- Provide privately authorized accounts, one normal role landing and guide; no credentials or role creation in this gate. Verify all roles needed for the rehearsal are actually assigned.
- Establish a separately authorized acceptance-evidence plan. Do not mutate 285–291/214 just to manufacture pending tasks.

### SHOULD COMPLETE BEFORE ANAÏS

- 10E task terminology and most architecture-prose reduction; explicit return to Inbox after certification/permit actions.
- Resolve receipt-void tooltip explanation and Treasury aggregate readiness hints.
- Validate keyboard navigation, signature modal focus and tab semantics; choose the bounded D-3 accessibility response without changing evidence authority.
- Provide a short context-sensitive error/recovery guide; small truthful UAT marker and preserved action-specific warnings.

### CAN WAIT UNTIL IPIL FORMAL UAT

- Final officer-label preferences and authoritative seal, if officers require it; text identity is already available.
- Separately authorized report selection/admission and outstanding independent CSV-byte certification before reports are claimed accepted for officer use. Do not delay ordinary workflow rehearsal for unavailable report infrastructure.
- Historical/report copy refinements after respective owners authorize them.

P2 analytics, richer help, animation, cosmetic preferences and deletion of unused page files may wait beyond formal UAT; they cannot delay turnover.

## Formal Ipil UAT versus production

Before formal UAT, agree officer terminology, role accounts, selected surfaces and known limitations; keep private UAT/environment identity and guide exact to the release. Branding and report inclusion are Chief Architect/municipal choices, not invented legal prerequisites. Provisional fee-policy feedback is recorded as POLICY FEEDBACK, never silently activated. Synthetic permit/Mayoral/legal-effect warnings remain mandatory while that authority mode remains in use.

Production database cutover, production historical seed, Cloud production deployment, R2 migration, OR stock policy, final fee policy, real Mayoral authority, statutory signatures, backup/DR certification, rollback rehearsal and production user provisioning are **outside Gate 10A and this implementation authorization**. They require the later Cutover/Production Readiness gate.

## Stop and handoff

Stop the affected implementation packet if it requires changing lifecycle/financial semantics, weakening authorization, removing required disclosure, altering history/report meaning, deleting Laboratory, or making production policy. Bring the exact finding to the Chief Architect. D-1 through D-5 are explicit pre-implementation choices, not silent permissions. This plan does not commission 10B.
