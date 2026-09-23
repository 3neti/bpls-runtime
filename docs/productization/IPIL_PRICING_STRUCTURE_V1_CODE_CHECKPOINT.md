# Pricing Structure V1 — Disabled Draft Persistence

## Local-main integration — 2026-09-24

User authorized proceeding with the proposed local integration. Canonical main fast-forwarded from 66d7024 to 1fc0830. All 17 non-route modified tracked files retained their exact hashes; the original Citizen permit PDF route remains an uncommitted addition alongside the committed pricing routes. No unrelated work staged, stashed or discarded.

Verified local SQLite target, made a private backup, applied only the four new additive pricing migrations (six empty tables). Hash of existing fee/application/assessment/payment/receipt/permit records unchanged. No import, seed, price proposal, review, activation or operational transaction performed.

Combined tests passed: 240 PHP /1,948 assertions; nine Treasury frontend tests. TypeScript, build, targeted PHPStan, scoped ESLint/Prettier and whitespace checks passed. First run began before the build completed and cached an old Vite manifest; rerun after build passed. No full-suite/lifecycle claim.

Local browser at https://bpls-runtime.test/staff/pricing-maintenance: administrator normal login, catalogue search, formula detail and fixed-fee form display, desktop 1440x1000 and mobile 390x844 passed; no captured errors/overflow. Existing Treasury account is correctly denied pricing permission. Browser checks were read-only apart from authentication; no permissions changed. Screenshots: workspace outputs/pricing-main-desktop.png and pricing-main-mobile.png.

Ready for local review under Administration > Pricing Maintenance. Main retains prior unrelated working changes. Cloud unchanged; nothing pushed. Next: taxonomy editing and authorized publication design, not implicit rate activation.

## Tenth slice — inline price proposals (2026-09-24)

Fixed-fee proposals can now be entered directly in Pricing Maintenance in pesos, with effective dates, reason and authority reference. Exact integer-centavo conversion rejects excess precision rather than rounding. Processing locks the form; successful save clears it and reload preserves proposal history. Latest five proposals are displayed newest-first; other calculation types retain the existing detailed editor. Reuses the existing authorized revision endpoint/action, with no new financial execution path, approval stage or fee activation.

Verification: 109 focused pricing/review/adapter/assessment tests, 474 assertions passed. Four new workspace tests cover exact amount/history, permission denial, validation/no mutation and latest-five rule-specific ordering. Initial combined user fixture collided on role uniqueness; split the tests, then passed. Browser inspection found relationship ordering overriding newest-first; explicitly reordered and added regression coverage. Pint, targeted PHPStan, ESLint, Prettier, TypeScript and production build passed (existing optional fontaine warning only).

Disposable local browser: rejected 29.155, saved 29.15 as a proposal, reloaded and verified current fee stayed 25.00. Desktop 1440x1000 and mobile 390x844: no overflow or captured errors. Screenshots in workspace outputs/pricing-revision-desktop.png and pricing-revision-mobile.png. No full-suite claim or operational records changed.

Still isolated on feature/pricing-structure-v1; not merged, pushed or deployed. Next: reconcile integration against canonical dirty main and continue taxonomy/publication design. Saving a proposal does not publish a rate.

## Ninth slice — visible maintenance workspace (2026-09-24)

Added /staff/pricing-maintenance and Administration > Pricing Maintenance. Search name/source code/revenue code; filter category and catalogue active/inactive; paginate 20 rows. Selected fee shows amount/basis, category/division, account code, effective dates, policy references, content-review state and history. Existing revision editor is linked, not duplicated. This is a draft-maintenance workspace: neither proposals nor reviews publish live prices.

New review POST requires staff access plus ViewFeeRules and ManageFeeRules. Domain action reauthorizes, locks the fee/current decision/account in a transaction, compares the displayed fingerprint and refuses stale or superseded content. Actor is server-derived; repeated identical actor/reference/content is idempotent. Review creates no reconciliation, amount change or Assessment. There is no publication endpoint or implied fiscal approval. Concurrent behavior remains dependent on existing writers respecting fee locking; no cross-DB contention test claimed.

Verification: six new HTTP tests; combined pricing/assessment selection 189 tests /1,477 assertions passed. Pint, targeted PHPStan, page/sidebar ESLint, Prettier, configured TypeScript check and production build passed. Initial frontend route generation omitted preview-only modules; reran the repository's configured types:check command successfully. Initial HTTP fixtures needed staff-access permission, fresh persisted snapshot inputs and the newly built Vite manifest; corrected and reran.

Browser: isolated disposable SQLite database only, six synthetic demo fees. Normal login, search, review save/reload, revision-editor navigation, proposed amount save/reload passed. Desktop 1440x1000 and mobile 390x844 captured; no captured browser errors or horizontal overflow. No live municipality records used. Screenshots: workspace outputs/pricing-maintenance-desktop.png and pricing-maintenance-mobile.png. Laravel migrations applied only to the disposable database, not canonical local/UAT data.

The UI follows repository Inertia/Vue, Tailwind, Wayfinder and testing guidance. Reuses existing categories, permissions and revision workflow; no new dependencies. Canonical main remains unmerged and deployment not performed. Next: integration review against dirty main, then authorized publication/price activation design and fuller taxonomy maintenance; these are not silently enabled by this screen.

## Eighth slice — persisted review-content binding (2026-09-24)

Verification: nine new binding tests; 28 binding/adapter tests passed. Combined focused/assessment regression selection: 183 tests /1,413 assertions passed. Pint, targeted PHPStan and whitespace checks passed. SQLite in-memory only; no full-suite/browser claims.

Added append-only pricing_rule_reviews with exact FeeRule/reconciliation/recorder FKs, review reference, source snapshot and deterministic SHA-256. Snapshot captures serialized rule/decision/account attributes, excluding only top-level created/updated timestamps. Unlike display-oriented financial hashing, no evidence keys named display/formatted/symbol are removed.

The fixed-fee adapter now requires an explicit review ID, verifies linkage and snapshot integrity, and compares current content before calculation. Amount, legal basis, applicability metadata, decision text/reference and account changes invalidate the old binding. It still separately requires current executable reconciliation and applicability. Existing adapter tests updated to create explicit test review records.

This binds recorded content, not municipal authenticity. Review creation has no fiscal activation effect. No maintenance endpoint/write authorization implemented; bulk database writes can bypass append-only model guards and an attacker with DB write access can rewrite hashes. It is not a signature scheme. No transactional publication/concurrency guarantee or workflow integration claimed.

Next: authorized maintenance/publication service with review permissions and transaction boundaries, followed by a read-only maintenance UI. No operational migration/import, main merge, push or deployment.

## Seventh slice — reconciled fixed-fee Price adapter (2026-09-24)

Verification: 19 new tests; combined focused/regression selection 174 tests /1,394 assertions passed. Pint, targeted PHPStan and whitespace checks passed. An initial null-amount fixture failed the existing NOT NULL schema constraint; corrected the test to exercise an unsupported basis instead. No full-suite/browser claim; SQLite in-memory test data only.

Added a read-only adapter using stored FeeRule and its exact current FeeRuleReconciliation, ApplicableFeeRuleQuery, AssessmentCalculator and existing Price/PriceReport. Supports only New, automatic, application-wide, fixed, basis-none Fee-category rules. Requires executable reconciliation, recorded non-future decision, nonblank authority/evidence/decision references and applicable reconciliation dates. Uses the existing application-year January 1 selection convention; does not establish a new fiscal date policy.

Unlike the legacy calculator shortcut, this adapter always requires reconciliation, even when reconciliation_required is absent. It never consumes a PricingDefinitionDraft or accepts a caller approval boolean. Account identity and serialized reconciliation evidence are frozen in the component explanation; exact-once key matches the existing fee_rule convention. Explicit fixed zero remains a priced zero only after these gates. No Assessment is persisted.

Boundary: this is a characterized adapter over existing reconciled records, NOT the finished published rule-version contract. Current FeeRule records remain mutable. Immutable publication must bind the exact rule content to the approving decision before this is exposed through maintenance or wired to operational workflows. Reference strings and status records are not independently authenticated here. No tax/formula/quantity/LOB/renewal expansion, draft promotion, route/UI or automatic activation.

Next: immutable reviewed rule snapshot/publication binding, with stale-content rejection, before catalogue integration. Operational calculator behavior remains unchanged.

## Sixth slice — guarded draft resolution (2026-09-24)

Added exact code/revision/source selection with explicit no_match, ambiguous_match, conflicting_source and policy_disabled outcomes. Duplicate candidates do not select the first amount, and mixed source fingerprints are not silently discarded. Every outcome has null amount and executable=false. requirePriceComponent throws the existing UnsupportedAssessmentPolicy before any AssessmentPriceComponentInput/Price construction; even fixed zero and purported evidence acceptance remain disabled.

This is a diagnostic draft boundary, NOT an operational calculator adapter. Existing AssessmentCalculator/Price/PriceReport are unchanged. Successful integration needs a separate adopted rule-version contract covering authority, applicability, effective period, units and rounding. Do not introduce a boolean flag to promote these drafts.

Nine new tests /26 assertions; combined focused/regression selection 155 tests /1,369 assertions passed. Pint and targeted PHPStan passed. No operational migration, UI/browser changes, main merge or deployment. Next: model the separate approved-rule contract and characterize resolver-to-Price inputs against existing accepted-policy boundaries.

## Fifth slice — mapping coverage audit (2026-09-24)

Combined focused/assessment regression result: 146 tests /1,343 assertions passed; whitespace check passed.

Added PricingMappingCoverageAudit and pricing:audit-mappings with required source SHA-256. Reports every stored draft revision in that exact source cohort, selecting its highest mapping revision. This is stored-record coverage, not a claim that all source catalogue rows were imported. Empty cohorts explicitly report no_stored_drafts.

Exclusive counts: unmapped, account_unmapped, identity_drift, account_inactive, mapping_recorded. Drift compares frozen source/charge/account identity fields; no rewriting or fallback to old mappings occurs. Reports expose aggregate counts, not recorder emails or evidence text. All results retain fiscal_readiness=false and executable=false. An exit success means audit completed, not financial readiness. This is a read-only diagnostic, not a concurrent publication gate or evidence-authenticity verifier.

Verification: seven new tests /23 assertions; Pint and targeted PHPStan passed. No operational database audit, browser check, import, live pricing change or deployment. Next: define explicit rule-resolution outcomes and guarded integration with existing Price/PriceReport; mapping evidence alone must never authorize calculation.

## Fourth slice — explicit mapping evidence (2026-09-24)

Verification: 15 new tests /33 assertions; combined focused/assessment regressions 139 tests /1,320 assertions passed. Pint and targeted PHPStan passed. Initial static analysis identified ambiguous single-versus-multiple lookup types; explicit scalar reference validation and single-row lookup corrected this. SQLite in-memory schema only; no full-suite/browser claims.

Added append-only pricing_draft_mappings linking an exact draft revision to a charge item and optional existing revenue account. Each mapping records a recorder user, review reference/hash and rationale; identities are snapshotted on creation, not supplied by callers. Source account and selected account remain separately visible. Matching codes never auto-map, and account renames do not rewrite recorded evidence.

Mapping revisions are unique per draft; restrictive FKs retain referenced records. Model updates/deletes fail; bulk SQL is outside that immutability guarantee. This is mapping evidence only, not fiscal acceptance or an authorization service. No route/UI/importer exposes writes; future maintenance must enforce permissions, effective scope and reviewed evidence authenticity. No actual municipal mappings were accepted in this slice.

Next: a read-only mapping coverage audit, then the guarded rule resolver/Price adapter. No operational migration, active fee mutation, merge, push or deployment.

## Third slice — catalogue structure

Verification: 21 new tests /33 assertions; combined pricing/assessment regression selection 124 tests /1,287 assertions passed. Pint, targeted PHPStan and whitespace checks passed. Full suite and browser checks not run.

Added pricing_charge_groups, pricing_units and pricing_charge_items, with factories and Eloquent relationships. Reuses existing fee_categories instead of duplicating it. Business divisions, office routing and LOB applicability remain separate. No catalogue rows are automatically imported, no existing fee_rules modified and no draft-to-item crosswalk guessed.

Groups are an append-only browsing hierarchy: new nodes may reference existing parents, and model updates/reparenting are refused. Units retain explicit dimension and integer decimal precision (0–12); no conversions are inferred. Charge items have nullable group/category/unit references with restrictive foreign keys. Definitions are immutable through model operations at this foundation stage; a versioned maintenance service is still required. Bulk SQL can bypass model immutability; this is not a database-wide cycle/immutability guarantee.

No account FK is assigned merely because source evidence includes a code. Existing revenue_accounts will be reused by a later explicit reviewed-mapping layer. No migration was applied to an operational database; schema exercised in SQLite in-memory tests only. No browser-visible changes or browser checks.

Next: explicit draft-to-item and reviewed account crosswalks with provenance, then versioned rule resolution through existing Price/PriceReport. Unit conversion, maintenance UI and fiscal activation remain unimplemented.

## Second slice — source-to-draft dry run

Implemented read-only PricingCatalogDraftPlanner and pricing:plan-definitions. An explicit reviewed SHA-256 is required; mismatched fingerprints and duplicate fee identities fail. Existing pinned tax-evidence validation is reused. The command has no apply/import option and reports aggregate evidence only.

Verified committed-worktree catalogue SHA-256: 40585c0a819fc182830f1ec01c7a494d77fc7a458a87c7fb528640e8a83d8a6b. This is not the dirty canonical working YAML fingerprint.

All 171 fee rows and 17 definitions/245 branches survive mapping exactly. Every draft remains source_observed with null executable amount/unit; original values stay in evidence. Missing account references: 144; basis reconciliation flags: 138. These are review flags, not accepted mappings. Whole-catalogue taxonomy normalization is not implemented yet.

Verification: 103 focused/regression tests, 1,254 assertions; Pint, targeted PHPStan and whitespace checks passed. Direct command run confirmed database_writes=false and executable=false. An initial test used multiple expectations against one console output block; corrected to inspect decoded output and reran successfully. No operational migrations, imports, live pricing changes, browser acceptance, merge, push or deployment.

Next: normalized charge/group/UoM relationships and explicit source-account mapping, retaining disabled-policy boundaries and reconciling dirty canonical work before integration.

2026-09-23. First code slice, not complete catalogue implementation.

Branch: feature/pricing-structure-v1. Base: 66d70243fb9d2db3b740ad33188bc34137ca82f5.

## Implemented

- Additive pricing_definition_drafts migration: flat identity/revision/method/basis/unit/amount/currency/account-reference columns and JSON source evidence.
- Typed draft definition with validation for explicit fixed/unit amounts versus unresolved null, provenance fields, exact decimal evidence and non-executable behavior.
- Model round-trip hydration and append-only model guards; database unique code/revision.
- Factory and focused unit/persistence tests. No seeder, import command, route or active fee lookup consumes these drafts.

This is an evidence-staging layer, not the final item/group/UoM/account/rule schema. Source account code is text evidence, NOT an accepted FK mapping. Complex tariffs remain source_observed, not supported executable methods. No new calculation engine was added.

## Verification

25 new tests /42 assertions passed. Combined with three existing Price/renewal suites: 96 tests /365 assertions passed. Pint passed. Targeted PHPStan passed in debug/serial mode (sandbox blocked the first parallel socket attempt). Diff whitespace check passed.

First regression attempt had two missing-Vite-manifest errors in the new worktree; supplied an isolated copy of existing local built assets and reran successfully. This does not claim a fresh frontend build or browser validation. Vendor was independently copied and local autoload regenerated; no shared vendor writes.

SQLite in-memory testing only. No .env copied; no operational migration, catalogue import, price changes, payment/assessment changes, push or deployment. No full-suite or browser claims. Worktree based on committed HEAD, not the user's dirty local pricing/renewal edits.

## Limitations and next slice

Eloquent guards are not database immutability guarantees: bulk SQL can bypass them. No activation column/path is offered and current calculators do not query this table. These constraints must be retained when adding services/UI.

Next: exact source-to-draft dry-run mapping and validation of all 171 identities/245 tax branches, followed by normalized charge/group/UoM relationships and reviewed account mappings. Do not merge this branch over uncommitted main work; integrator reconciliation required. Unresolved fiscal policy remains disabled.
