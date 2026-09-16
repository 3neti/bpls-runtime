# IPIL BPLS GATE 10D — ENGINEERING AND LABORATORY ISOLATION

Date: 2026-09-16  
Candidate: `ef987076b7da03820be741e7f0d7f4dc7ec841ad`  
Worktree: `/Users/rli/PhpstormProjects/bpls-gate10`  
Review URL: [http://bpls-gate10.test/](http://bpls-gate10.test/)

## Disposition

Gate 10D validates the persistent local, synthetic Gate 10 review environment. Ordinary BPLS surfaces do not expose the Lifecycle Laboratory, cleanroom controls, specimen helpers, or laboratory provisioning. The existing protected preview entrance and fail-closed laboratory middleware remain intact for the exact approved Management preview operator. No Cloud, UAT, production, historical, report, or workflow-UAT operation was performed.

## Isolation evidence

- Public home contains only ordinary Apply, Continue, Staff Login, Verify guidance, and Schedule of Fees; no Laboratory or engineering link.
- Citizen dashboard/session exposes only Citizen navigation and returns `404 Not Found` for direct `/users` access; `/staff/work` is forbidden for Citizen.
- Ordinary generated walkthrough identities retain `show_engineering_controls=false`, no preview persona, and no cleanroom actor, even when an active synthetic cleanroom exists.
- Exact approved preview personas retain the established preview presentation. Lifecycle Laboratory routes additionally require `StakeholderPreviewPersona::Management`; non-Management direct access remains `404`.
- Users & Access now advertises “Provision laboratory actors” only when the caller is the exact approved Management preview identity, has the existing permission, the laboratory seed flag is enabled, and the app is non-production. No permission is granted by this change.
- Public permit verification retains the cleanroom return affordance only when an active exact preview cleanroom actor is shared; ordinary users receive no such actor or link.
- `show_engineering_controls` remains an explicit, safety-validated preview identity signal; it is not inferred from role or email alone.

## Responsive/browser checks

- Persistent HTTP hostname: PASS (`http://bpls-gate10.test/`); HTTPS was not used.
- Desktop public home and Citizen dashboard: PASS.
- Exact `390×844` public-home viewport: PASS; prior validation recorded `innerWidth=390`, `scrollWidth=390`, `clientWidth=390`.
- No horizontal overflow observed; no BPLS-originated console errors observed in the persistent browser tab.
- Keyboard/focus and ordinary navigation remain unchanged; no engineering control is reachable through ordinary navigation.

## Verification

- PHP syntax and `git diff --check`: PASS.
- Focused Pest execution was attempted, but the checkout’s shared symlinked Pest runtime is not writable in this sandbox (`vendor/.../.temp/test-run-history: Operation not permitted`) and consequently reports the pre-existing test-bootstrap `withoutVite()` failure. This is an environment limitation, not a product assertion.
- The Gate 10B/10C acceptance baseline and existing preview/laboratory authorization tests remain unchanged except for the focused regression assertion added to `WalkthroughPresentationTest`.

## Preservation and boundaries

`/Users/rli/PhpstormProjects/bpls-runtime` remains on branch `main` at `b851a56390a69ab3845e116f919544ecdc8684ec` with its four pre-existing guidance edits untouched. No Git integration, Cloud operation, database mutation, taxpayer data, media, deployment, or Gate 10E work occurred. The Gate 10 worktree remains a local/private synthetic review environment.

## Review classification

- Existing preview/laboratory protection: `MATCH`.
- Ordinary-surface engineering isolation: `ADAPT` (server-side capability narrowed to the exact Management preview reviewer).
- Broad UI redesign, cleanup, data reconciliation, or product simplification: `DEFER`.

GATE 10D: PASS — ENGINEERING SURFACES ISOLATED
