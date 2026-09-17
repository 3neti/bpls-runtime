# IPIL BPLS GATE 10C — ROLE-SENSITIVE NAVIGATION AND INBOX-FIRST WORK

Date: 2026-09-16. Bounded local implementation only; no deployment.

## Implementation

- Starting SHA: `a66d02349d42f42aa2ed5465bbd2e1181bf1d855` (Gate 10B packet; protected Gate 9 remains `f8a08ec28e2dfbb856bc0d42ceb9b587fcad34e6`).
- Implementation SHA: recorded by the commit containing this packet.
- Files changed: `resources/js/components/AppSidebar.vue`, `resources/js/pages/Dashboard.vue`, `resources/js/pages/work-inbox/Index.vue`, `resources/js/pages/post-payment-certifications/Show.vue`, `resources/js/pages/ordinary-uat-permit/Show.vue`, and `tests/Feature/Gate10CNavigationTest.php`.

## Navigation disposition

The staff sidebar now leads with **My Work → Inbox**, followed by neutral Overview. Applications remains an authorized lookup destination, not a work queue. Dashboard prominently offers Inbox / My Work and describes Applications as “Find or inspect an application record.” The ambiguous “Treasury Work” first-capability selection is removed; payment schedules, receipts, or billing groups are shown by their actual destination. Reports now points to the existing Report Templates catalogue, while Daily Collections remains separately named. No permissions or server-side readiness changed.

The canonical `BuildMunicipalWorkInbox` remains the sole task source. Inbox preserves assignments, query/task/year filters, counts, pagination, empty/no-position wording and `action_url`; cards now display the canonical `task_label` (for example Determine concerned offices, Prepare Payment Order, Treasury determination, Counter-check Assessment, Review Assessment, Collect Payment, Issue Official Receipts, Authorize Business Permit issuance, Release Business Permit). Destinations are not reconstructed in Vue. Existing permit issuance routing continues to use the ordinary `/uat-permit` destination and protected Laboratory paths remain separate.

All ordinary roles represented by the existing position model—BPLO, Assessor, Engineering, Health, MENRO/MPDO, Treasury, Treasury counter-check, Municipal Treasurer, Assessment Officer, Cashier, Mayor/permit issuer and Releasing—share Inbox-first discovery, with only existing permission-backed destinations in the sidebar. Assessment Officer remains distinct from Municipal Assessor. Multi-role accounts retain the existing role/position model; no arbitrary permission-order landing or new role selection was introduced. If authentication has an explicit intended URL it still wins; otherwise Dashboard remains the neutral authenticated landing and Inbox is immediately prominent.

Citizen navigation remains distinct: My Businesses and My Permit Applications are retained; Dashboard’s duplicate Start/Continue and Track cards remain existing presentation links to the same personal application list pending a later broader consolidation decision. No anonymous tracking, historical/current merge or new public identifier was added.

Focused task pages for post-payment certification and ordinary UAT permit now expose **Back to My Work** while retaining their Application link and canonical actions. The home/preview separation from Gate 10B is unchanged: `/` remains Welcome and the protected preview launcher remains `/stakeholder-preview`. No new engineering, Laboratory, storyboard or specimen navigation was added.

## Isolated local integration verification

- Focused Pest: `Gate10CNavigationTest` (11 tests, 136 assertions) and `DashboardTest` pass. Gate 10B `PublicHomeTest` and `ExampleTest` remain passing in prior verification; no Gate 9 workflow suite was mutated.
- PHP formatting (Pint), changed-file ESLint, Prettier, TypeScript generation/check and Vite production build pass. No domain PHP, migrations, database definitions, permissions or actions changed.
- An isolated Laravel server for this candidate was run only on `http://127.0.0.1:8877` against a disposable SQLite database at `/private/tmp/bpls_gate10c_acceptance.XXXXXX.sqlite`. The database was migrated from an empty install and populated only with the 14 canonical synthetic preview personas; no scenario command was run because its existing stakeholder-cycle fixture requires configured X-Change settings. No Cloud/UAT/protected records were used.
- Authenticated browser acceptance was performed against the actual Laravel/Inertia shell (not a synthetic HTML fixture). At desktop, BPLO and Management views visibly showed **My Work → Inbox** first, neutral Overview next, permission-backed Applications/Treasury/Reports destinations, canonical Report Templates, and the protected `/stakeholder-preview` launcher. Inbox displayed the canonical “No active municipal position” state, zero action-required work, search/task/year filters, and preserved action URL/task-label rendering. Role switching was exercised for BPLO, Treasury, Engineering, Mayor’s Office and Releasing Officer; each remained on the role-sensitive staff shell without exposing unauthorized actions. The protected Laboratory route remained separate and read-only in this check.
- At exactly `390×844`, the preview launcher and authenticated Dashboard rendered with `scrollWidth === clientWidth === 390` (no horizontal overflow); the same Inbox-first and role-sensitive controls remained visible. Browser console error logs were empty for the exercised local pages. No POST/action controls were invoked.
- The isolated server was intentionally not mapped to `bpls-runtime.test`; that hostname continues to serve the protected main checkout. Keyboard/focus behavior remains the existing Sidebar/NavMain implementation and was not altered.

## Preservation and stop boundary

No Cloud operation, deployment, seed, migration, lifecycle action, financial mutation, historical corpus access, report integration, or production policy occurred. Applications 285–291, Assessment 214, Reports 004/016/028, fingerprints and browser-certification records remain untouched. Gate 10B home/authentication behavior and preview safeguards remain intact. Pre-existing unrelated guidance edits in the main checkout were not touched.

**Readiness boundary:** navigation and Inbox-first integration are proven in the isolated local shell. Representative assigned task records were not fabricated because the existing stakeholder-cycle fixture is X-Change-configured and unavailable in this offline-only acceptance environment. A future fixture-backed run may add task-card coverage without changing this navigation implementation. Do not begin Gate 10D or deploy from this packet.

**GATE 10C: PASS — ROLE NAVIGATION READY**
