# Gate 9 — Ordinary UAT Mayoral Authorization correction

Base: `c17ce784d5dc5d14afcb5bac1928c69fcd0a45ca`.

## Bounded authority

The Chief Architect commissioned the ordinary workflow-UAT handoff after four completed office certifications. No financial recalculation, recertification, historical/report work, production authority, or application rewrite is included.

Previously, PermitReadiness inferred synthetic issuance authority from cleanroom metadata, and issuance verified an actor in the cleanroom manifest. The ordinary Application had neither. Actor identity, UAT authority mode, cleanroom membership, readiness, and issuance had been conflated.

The corrected ordinary path requires the existing explicit workflow-UAT environment safeguards plus `WORKFLOW_UAT_AUTHORITY_MODE=synthetic_only`. The exact active institutional assignment IDs are configured separately through `WORKFLOW_UAT_MAYOR_ASSIGNMENT_ID` and `WORKFLOW_UAT_RELEASING_ASSIGNMENT_ID`. Their expected position and capability, actor identity, staff access, and role are checked server-side. No assignment or role is provisioned or widened by this code. The default configuration denies authority. Production and historical UAT fail closed.

## Canonical sequence and evidence

Ordinary Inbox → Application → Mayoral Authorization → existing Permit issuance Action → existing separate BPLO release Action → released public identity verification.

Financial, receipt-allocation, routing, office-OR binding, and certification prerequisites must pass before authorization. Readiness then reports authorization pending, not missing cleanroom membership. Authorization is stored in the existing uniquely application-bound provisional completion record using its decision actor/time fields and an immutable `ordinary_mayoral_authorization` snapshot. No schema migration or data backfill is needed.

The snapshot freezes the configured assignment identity/version/fingerprint, actor, timestamp, exact application, readiness, Assessment/Collection/receipt references, certification completion evidence, and financial/certification evidence fingerprint. It explicitly denies production authority, statutory signature, and personal real-Mayor action. Issuance preserves that snapshot and verifies no evidence drift. Release does not issue or authorize. Locked application transactions and the existing unique application constraint preserve one authoritative result; retries return the original evidence.

Ordinary public identity lookup is withheld until release. Existing synthetic Permit numbering and non-legal verification semantics remain unchanged. The original laboratory path is retained, not used to authorize ordinary Applications.

## Verification

- Focused ordinary certification/Mayoral tests and related Inbox, Nelson, complete-lifecycle, and executable-document tests: 36 passed, 549 assertions (one existing warning without details).
- Larger suite: one cleanroom payment-provider assertion failed identically on the unchanged base commit, at `LifecycleLaboratoryTest.php:1414` (expected an active `netbank` attempt after Collection; actual null). No payment correction is included.
- Changed PHP static analysis: zero errors.
- TypeScript, targeted ESLint, Pint, frontend build and diff whitespace checks passed.
- Regression coverage includes separate authorization/issuance/release, unchanged financial and certification rows, frozen evidence, duplicate submissions/reloads, wrong actors, missing certificates/OR coverage, production/historical denial, cross-Application non-reuse, and issuance refusal after evidence drift.

Implementation and synthetic tests are not deployed browser acceptance. Record the exact deployment and live Adjudicator result separately. Desktop and exactly 390×844 must be observed; tooling failure is not product success. Stop at the first genuine downstream defect. Preserve the existing acceptance Application and protected specimens; Report R2-016 remains paused.
