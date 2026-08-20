# Stakeholder Preview / UAT Contract

Status: **Frozen for implementation**

Baseline: `main@3bd8eab8a1be1dba0a58922508636aaccec819e7`

Current review hold: `OPERATIONAL-NELSON-001` introduces unresolved pre-assessment payment-order, one-transaction fiscal, named-clearance, post-clearance approval, portal, issuance, and release semantics. This historical contract remains preserved, but the deployed preview must not be frozen as the semantic baseline for Warp Product/UI Critic review until `NELSON_OPERATIONAL_WORKFLOW_ARTIFACT_RECONCILIATION_2026-08-20.md` is resolved and a new evidence cycle is verified.

## Purpose

The Stakeholder Preview is a non-production delivery surface over the real BPLS application. It gives a tester one URL, four synthetic stakeholder perspectives, normal Laravel authentication and authorization after entry, persistent preview labeling, and a deterministic administrative recovery path.

It does not create a second workflow, change accepted municipal semantics, grant production role policy, activate fiscal or numbering policy, authorize permit issuance or release, execute production migration, or use production data.

## Canonical Safety Gate

`App\StakeholderPreview\StakeholderPreviewSafety` is the only authority for enabling preview behavior. Preview routes are registered only when every predicate below is true:

1. the application environment is not `production`;
2. `STAKEHOLDER_PREVIEW_MODE=true`;
3. the configured profile is exactly `stakeholder_preview_weekend_v1`;
4. the data classification is exactly `synthetic_only`;
5. the PII mode is exactly `synthetic_only`;
6. production migration execution is disabled;
7. production integrations are disabled;
8. all four configured accounts use the approved exact synthetic identities, names, role codes, permission bundles, and `example.test` domain.

The gate fails closed on absent, malformed, contradictory, or unexpected configuration. `APP_ENV=production` always disables preview behavior even if every preview flag is otherwise enabled.

The same service guards route registration, defense-in-depth middleware, account resolution, shared Inertia preview context, and preview preparation. Application code reads configuration; it never reads environment variables directly.

## Authentication And Switching

The client submits only one backed persona key: `citizen`, `bplo`, `treasury`, or `management`. It never submits a user ID or email.

For every entry or switch, the server:

1. re-checks the canonical safety gate;
2. resolves the exact configured account by persona;
3. verifies its exact email, name, role code, verified state, disabled two-factor state, and effective permission set;
4. logs out the current web guard;
5. invalidates the old session and regenerates the CSRF token;
6. authenticates the resolved existing user through Laravel's web guard;
7. regenerates the authenticated session;
8. redirects to the normal role-aware BPLS Overview.

No frontend impersonation, arbitrary identifier lookup, shared mutable role, permission bypass, credential display, or credential logging is allowed.

## User Experience

When safe, `/` is the UAT role launcher. When unsafe, `/` remains the ordinary BPLS welcome page and no UAT route exists.

All authenticated Inertia pages show `STAKEHOLDER PREVIEW / UAT — SYNTHETIC DATA — NOT PRODUCTION`, the active preview perspective, and a server-backed role switch. Public permit verification pages show the same preview banner only while the safety gate is active; their lookup and verification semantics do not change.

The Overview shows three to five server-selected, permission-checked links for the active persona. A UAT-only walkthrough page presents the approved role sequence as guidance over existing routes. The private Board storyboard and frozen evidence are not copied into the deployment.

The approval-stage update projects a narrow accepted workflow through the real domain and authorization path:

```text
Assessment Officer prepares/computes persisted assessment
    -> Municipal Treasurer approves exact assessment/amount or returns it for correction
    -> matching approval makes payment scheduling available
```

The synthetic Treasury persona receives `assessments.approve`; the BPLO and Citizen personas do not. This is a preview permission bundle, not final municipal role policy. Deterministic data must record an explicit approval fact rather than backfilling or inferring one. Approval does not collect payment, issue a receipt, establish documentary sufficiency, or authorize permit signing, issuance, release, validity, or legal effect.

## Recovery

No tester-facing reset route is permitted. The current scenario graph has no proven selective deletion contract, and a general database or migration reset is forbidden.

The preview administrator may run the fail-closed preparation command to create a fresh deterministic synthetic run. The command may operate outside `local` and `testing` only when the canonical safety gate passes. It never deletes general data, runs a production migration campaign, or changes frozen evidence. The UI tells testers that the preview administrator can restore fresh synthetic data.

## Deployment Boundary

The Laravel Cloud target must be a new non-production application/environment for `3neti/bpls-runtime`, backed only by a fresh synthetic database. Runtime secrets and preview profile settings belong in Laravel Cloud environment variables, never Git.

The Cloud environment must use a non-production `APP_ENV`, disable production integrations and production migration execution, and set no production PII or credentials. Deployment may run ordinary schema migrations for the new empty UAT database; it may not run any legacy or production migration campaign command.

Deployment is complete only after the HTTPS URL passes role entry, all ordered role switches, authorization denials, banner/guidance, logout, public verification, desktop/mobile, console/network, and recovery checks.
