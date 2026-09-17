# IPIL BPLS GATE 10B — PUBLIC HOME AND AUTHENTICATION ENTRY

Date: 2026-09-16. Local bounded implementation; not a deployment or turnover-candidate authorization.

Authority: Chief Architect disposition accepting [Gate 10A](IPIL_GATE_10A_PRODUCT_SURFACE_AUDIT_2026_09_16.md), decisions D-1/D-2/D-4/D-5, and commissioning only 10B under the [Gate 10 plan](IPIL_GATE_10_IMPLEMENTATION_PLAN.md). D-3 accessibility work and 10C–10H remain outside this packet.

## Implementation and behavior

1. **Starting SHA:** `598039280f538603e16bd869ab14e00498b711d2`, directly descending from protected Gate 9 `f8a08ec28e2dfbb856bc0d42ceb9b587fcad34e6`.
2. **Implementation SHA:** `5bce73f872b66d230bfa5866bcc6bfdbea36a594`. Branch `agent/product/gate10b-home`; worktree `/Users/rli/Documents/Codex/work/bpls-gate10b-home`. This report is a subsequent documentation-only commit.
3. **Exact implementation files:**
   - `routes/web.php`
   - `resources/js/pages/Welcome.vue`
   - `resources/js/pages/auth/Login.vue`
   - `resources/js/pages/auth/Register.vue`
   - `tests/Feature/ExampleTest.php`
   - `tests/Feature/PublicHomeTest.php`
   - `tests/Feature/StakeholderPreviewUatTest.php`
   - `tests/Frontend/PublicHomeBrowserTest.mjs`
   - Documentation addition: `docs/productization/IPIL_GATE_10B_PUBLIC_HOME_ACCEPTANCE_2026_09_16.md` (this file).
4. **Root before/after:** previously `/` selected the preview launcher when the safety configuration enabled preview, otherwise Welcome. Now `/` is always the informational Welcome page named `home`. The retained launcher moves to `/stakeholder-preview`, named `stakeholder-preview.index`, under its unchanged safety/throttle and profile-dependent authentication, active-access and exact-reviewer checks. Controller-derived Wayfinder links regenerate to the relocated launcher. Logout still returns to `/`, now the ordinary home. No preview action, cleanroom, specimen, storyboard or evidence facility was removed. This is root separation, not completion of Gate 10D's wider engineering-isolation audit.
5. **Home:** text municipal identity; restrained service choices; no municipal seal, taxpayer details, actor switcher, specimen controls, Laboratory entry or public search. Non-production home explicitly discloses synthetic test authority, simulated payments, provisional policy and no production legal effect. The obsolete workflow-end claim is removed from this page only.
6. **Apply:** existing `citizen.permit-applications.create` GET; a guest is sent through existing authentication, retaining the intended URL. It does not create or submit an Application.
7. **Continue:** existing `citizen.permit-applications.index` GET; authenticated, permission-controlled personal application list. Guest authentication retains the destination.
8. **Staff Login:** same Fortify login, same existing post-login destination. Copy directs staff to assigned accounts. Role-specific landing redesign belongs to 10C.
9. **Verify:** explanatory region directing the reader to the exact QR/verification link on the Permit. No generic lookup input, route, identifier or disclosure was added. Existing verification controllers and routes are unchanged.
10. **Schedule of Fees:** existing `services-and-fees.index`; the private-review profile continues to require its existing authorized authentication. A public home does not make private fee/verification surfaces public.

## Authentication and acceptance

11. **Authentication preservation:** Fortify actions, configuration, provider, middleware, permissions, passkey integration and layouts are unchanged. Existing login, invalid-password/rate-limit, registration, reset, email-verification, password-confirmation, 2FA and logout tests pass. Registration remains citizen-only, including a new assertion that submitted staff-role input cannot provision staff. Hardware passkey authentication was not re-enrolled or exercised; its code and configuration are unchanged.
12. **Draft continuation:** existing expired-lodging test passes: login retains the exact saved draft edit destination. Added Apply/Continue login tests and citizen-registration intended-Apply test pass. No draft was recreated.
13. **Engineering exposure:** home imports only ordinary route helpers. Rendered guest/citizen/staff and stale engineering props expose the same ordinary entries, no control panel. Retained private launcher tests still reject guests/ordinary users and accept the exact prepared reviewer; incomplete account infrastructure remains fail-closed. The existing synthetic-only testing profile has not been converted to a different authorization architecture.
14. **Guest:** ordinary root and service link targets verified in rendered browser; actual Laravel request tests cover login redirects/intended destinations, public auth/fees availability and no generic verification route.
15. **Citizen:** Apply and Continue authenticate back to the intended citizen route; staff Inbox denied. Root carries no launcher data. Browser fixture with citizen props has no engineering entry.
16. **Staff:** same login succeeds; existing overview reports staff access and no citizen access for the test staff role. No preview persona is assigned. Browser fixture with staff props has no engineering entry.
17. **Unauthorized/inactive:** suspended and expired test accounts cannot enter either citizen application route or the dashboard. Informational public home remains readable. Private preview and verification restrictions remain enforced.
18. **Desktop:** connected Chrome inspection at **1440×900** passed for the actual Welcome Vue component and application CSS, with synthetic Inertia props. All four links have expected targets, no clipped actions and no horizontal overflow. Screenshot inspected in the task.
19. **Mobile:** connected Chrome inspection at **exactly 390×844** passed. DOM-reported dimensions confirmed; primary cards span x=20..370; text wraps; no horizontal overflow or clipping. Page scrolls normally to fees and UAT disclosure. Screenshot inspected in the task.
20. **Keyboard/focus:** desktop Tab traverses Apply → Continue → Staff Login → Fees. Every link reports `:focus-visible` and a visible two-pixel ring with offset. Reverse traversal checked on mobile. No artificial positive tabindex was added to the home.
21. **Console/network:** no BPLS-origin errors observed in connected Chrome's error log while rendering these home states; unrelated extension warnings were identified separately. This is not a HAR or a complete browser-authentication network trace. The automatic Playwright runner could not launch Chromium because macOS denied MachPortRendezvous registration; its end-to-end automated assertions are therefore **not claimed as passed**. Connected-browser visual/focus inspection and Laravel request tests supply separate evidence, not a fabricated automated success.

## Verification and preservation

22. **Focused tests:** 101 PHP tests / 2,237 assertions passed across `PublicHomeTest`, `ExampleTest`, all `Auth` tests and `StakeholderPreviewUatTest`. Database explicitly SQLite `:memory:` with empty DB_URL. Initial missing-Vite-manifest failures were resolved by building this clean worktree. A new private-profile test's incomplete synthetic config was corrected before the final passing run.
23. **Broader checks:** 39 PHP tests / 628 assertions passed for staff access, citizen processing, staff assessment and ordinary UAT mayoral authorization (runner also emitted one warning with no detail); another 110 PHP tests / 1,009 assertions passed for citizen intake, QR handoff/payment, post-payment certification and municipal fees. Total: **250 passing PHP tests / 3,874 assertions** across these suites. TypeScript, changed-file ESLint, changed-file Prettier, Pint and production asset build pass. The frontend TypeScript suite is **59/60**, not green: `EvaluationWorkingPaperTest.ts:319` forbids any `.reduce(` in the existing Evaluation page, which already contains a Treasury projection reduction. The identical failure was reproduced by running that test in untouched Gate 10A worktree `bpls-gate10a-product-audit` (10/11 in that file). Neither the test nor the Evaluation page changed. No fiscal correction or weakened assertion is included. The full repository PHP suite was not run.
24. **Gate 9 preservation:** no Cloud connection, lifecycle execution against persisted data, seed, migration, financial mutation or deployment. Applications 285–291 and Assessment 214 were not accessed or altered. All domain code, configuration and database definitions remain unchanged. Gate 9 and this branch share `app` Git tree `02df8a52dab93e37781da9315e75cd40ae8e13de` and `database` tree `865035cde89274a25b5ac3ea10ea346e4e7864f5`. This is code/no-access preservation evidence, not a fresh database-hash audit. All mutated test records were synthetic in-memory fixtures.
25. **Historical/report preservation:** no corpus/private historical artifacts opened, no reporting branch merged, no reports or fingerprints changed. Reports 004/016/028 and their independent certification records remain in their separate reporting lineage. The four pre-existing main-worktree guidance edits remain untouched: `AGENTS.md`, `CLAUDE.md`, `.ai/skills/deploying-laravel-cloud/SKILL.md`, `.ai/skills/deploying-laravel-cloud/reference/checklists.md`.
26. **Deployment:** local branch only. No push, Cloud deployment, production exposure or Anaïs turnover-candidate presentation. 10B, 10C and 10D must each independently pass before that candidate is established.
27. **Boundaries/caveats:** no new architectural authority required by this implementation. Automated Chromium launch remains infrastructure-blocked; connected-browser rendering and request-boundary authentication evidence are explicitly separate. Live browser authentication, hardware passkey ceremony, full role turnover acceptance and broader engineering isolation are not certified by this packet. The unrelated baseline frontend failure remains visible for integrator disposition. Stop here; do not start 10C without a new commission.

## Reproduction

Generate Wayfinder helpers with `npm run types:check`; build with `npm run build`. Run the named PHP suites using the repository's `phpunit.xml` in-memory defaults and no real environment file. The new browser test runs as `node --test tests/Frontend/PublicHomeBrowserTest.mjs` when Chromium launch is permitted. `node tests/Frontend/PublicHomeBrowserTest.mjs --serve` exposes only the synthetic component fixture on a loopback ephemeral port for connected-browser inspection; it is not the Laravel application or an authentication replacement. Do not interpret intercepted browser-test responses as server-authentication proof.

Repository guidance used: [Inertia Vue](../../.agents/skills/inertia-vue-development/SKILL.md), [Tailwind](../../.agents/skills/tailwindcss-development/SKILL.md), [Wayfinder](../../.agents/skills/wayfinder-development/SKILL.md), [Fortify](../../.agents/skills/fortify-development/SKILL.md), and [Pest](../../.agents/skills/pest-testing/SKILL.md). They kept the change within existing route/authentication conventions, responsive styles and isolated regression tests. Boost documentation tools were unavailable; official [Inertia links](https://inertiajs.com/docs/v3/the-basics/links), [Laravel routing](https://laravel.com/framework/docs/13.x/routing) and [Tailwind responsive guidance](https://tailwindcss.com/docs/responsive-design) were consulted as fallback.

**GATE 10B: PASS — ORDINARY BPLS FRONT DOOR READY**
