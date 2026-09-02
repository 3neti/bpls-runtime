# BPLO Routing + Paperless Payment Order + Computation/Assessment V1

Status: ready for Board review on 2026-09-02.

## Executable contracts

- A lodged applicant declaration remains frozen. Application facts and Lines of Business are evidence available to BPLO; they do not select municipal offices.
- BPLO records one explicit situational routing determination with actor, time, context, selected office, relevant declared Line of Business where applicable, reason, and required work.
- A concerned office can issue an amount-bearing Paperless Payment Order only for work BPLO selected. Each order is bound to the application, routing work, issuing office actor, Evaluation item/revision, contextual Line of Business, and immutable financial lines.
- A corrected office charge creates a later order sequence and supersedes the prior order. Cancellation is not executable because the accepted evidence does not yet define its municipal authority or transition.
- The Assessment Officer consolidates active, issued office orders plus governed canonical pricing into one immutable Assessment. Database uniqueness and source fingerprinting prevent a payment-order line from entering the same Assessment twice.
- The Municipal Treasurer approves the exact Assessment snapshot or returns it with remarks. The earlier Treasury counter-check remains a separately labelled repository-evidenced control and is not attributed to the Zoom or slip.
- The separate Computation/Assessment Slip is the authoritative financial artifact. Application Page 2's Assessment area stays unpopulated; concerned-office verification/signatures remain explicitly unavailable pending the post-payment slice.

## Evidence disposition

Direct Nelson/Zoom evidence establishes BPLO routing authority, concerned-office amount contributions described as paperless payment orders, Assessment Officer consolidation under Treasury, Municipal Treasurer approve/return, payment before the applicant returns to concerned offices, and the separation of those later office signatures from Payment Orders.

Direct specimen evidence establishes the recognizable Computation/Assessment Slip grammar and the existence of a Q1-Q4 Schedule of Payments section. The specimen does not establish an official numbering policy or quarterly allocation formula.

V1 inference is deliberately limited to internal database identity/sequence, issued-order eligibility, exact-once consolidation, and supersession on correction using the repository's existing immutable Assessment versioning/return semantics.

## Reconciliation and Laboratory

Both retained product-laboratory specimens reconcile as:

`PHP 330.00 Retail Trading + PHP 540.00 Food Service + PHP 350.00 application-wide = PHP 1,220.00 Grand Total`

The 2025 New and 2026 Renewal chronologies now read: Application lodged → BPLO routing determination → concerned-office work / Paperless Payment Orders → Assessment Officer consolidation → Computation/Assessment Slip → Treasurer decision → Payable. Scenario actions use the canonical domain actions; no browser-side state insertion or production routing rules were introduced.

- New semantic hash: `03265ccb231cfcf9454a5024d9d36f7386f0b852dafd2ca13956f782587f09b0`
- Renewal semantic hash: `6327e33fe70f56c15904d0f354f5017ecbd31bce4db911a31233b60c1532cb1c`
- SQLite/PostgreSQL parity artifacts: `storage/app/private/lifecycle-scenarios/new-application-happy-path/certification` and `storage/app/private/bpls-installation/product-lab-baseline-certification`

The hashes changed because explicit routing and payment-order provenance are now part of the normalized semantic result. The financial specimen total remains unchanged.

## Verification record

- Slice regression: 63 tests passed, 1,269 assertions.
- PHPStan: passed with zero errors.
- Pint, Prettier, ESLint, Vue TypeScript, and production frontend build: passed.
- Full suite was executed: 732 passed, 7 failed, 1 errored, 1 skipped; 11,557 assertions. The failures reproduce in older, out-of-scope baseline fixtures (root-page expectation, lifecycle audit isolation/generated-reference collision, payment/preview cross-test expectations). Every test introduced or changed by this packet passes.
- Browser: desktop and 390x844 routing/slip views have no horizontal page overflow; the application emitted no console errors. Browser-extension-only warnings were excluded.

## Genuine unresolved municipal decisions

- Official Payment Order numbering, cancellation authority, and any states beyond issue/supersede/include.
- Q1-Q4 allocation, due-date, amount, and balance formula: **BLOCKED — MUNICIPAL FISCAL DECISION**.
- Canonical acknowledgement actor/date semantics on the Computation/Assessment Slip.
- Post-payment concerned-office Page 2 verification/signature mechanics and authority.
