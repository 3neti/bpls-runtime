# Pricing Structure V1 — Disabled Draft Persistence

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
