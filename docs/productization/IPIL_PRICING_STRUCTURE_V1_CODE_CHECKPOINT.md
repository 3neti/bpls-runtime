# Pricing Structure V1 — Disabled Draft Persistence

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
