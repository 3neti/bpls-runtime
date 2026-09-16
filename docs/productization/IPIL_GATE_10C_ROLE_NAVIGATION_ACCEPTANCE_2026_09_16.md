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

## Verification

- Focused Pest: `Gate10CNavigationTest` and `DashboardTest` pass (10 tests, 132 assertions before final static test addition; the added assertions are covered by the same suite). Gate 10B `PublicHomeTest`, `ExampleTest`, and preview suite remain passing in prior verification; no Gate 9 workflow suite was mutated.
- PHP formatting (Pint), changed-file ESLint, Prettier, TypeScript generation/check and Vite production build pass. No domain PHP, migrations, database definitions, permissions or actions changed.
- Desktop and exactly `390×844` browser acceptance of the changed live Laravel shell was not run in this bounded local worktree: `bpls-runtime.test` still serves the separate main checkout, and no local server was started or switched. The existing synthetic Gate 10B browser fixture remains valid for the public home but does not represent the authenticated staff shell. This is an explicit infrastructure/integration boundary, not a claim of browser PASS.
- Keyboard and responsive code paths retain existing Sidebar/NavMain focus behavior and use the same responsive layout classes; the focused browser proof belongs in the local integration step before presenting this wave to a reviewer.

## Preservation and stop boundary

No Cloud operation, deployment, seed, migration, lifecycle action, financial mutation, historical corpus access, report integration, or production policy occurred. Applications 285–291, Assessment 214, Reports 004/016/028, fingerprints and browser-certification records remain untouched. Gate 10B home/authentication behavior and preview safeguards remain intact. Pre-existing unrelated guidance edits in the main checkout were not touched.

**Recommended next disposition:** integrate this branch into the designated local `bpls-runtime.test` checkout, run authenticated synthetic role browser checks at desktop and 390×844, then return for Gate 10C acceptance. Do not begin Gate 10D or deploy from this packet.

**GATE 10C: BLOCKED — CHIEF ARCHITECT DISPOSITION REQUIRED**
