# Financial Calibration Suite

Status: **Active verification infrastructure; not a financial policy engine**

## Purpose

The Financial Calibration Suite preserves municipality-issued assessments as Golden Financial Specimens. Each specimen answers two separate questions:

1. Can the historical municipal outcome be reproduced and explained from its evidence?
2. Has the Municipality authorized the same behavior as future executable policy?

A specimen may pass the first question while remaining blocked on the second. Historical reproduction never activates policy.

## Evidence Chain

Every specimen must reference all five evidence layers:

```text
Revenue Code evidence
        <-> production configuration
        <-> legacy evaluator behavior
        <-> persisted historical outcome
        <-> municipality-issued specimen
```

The manifest records evidence references and checksums, not raw personal, business, receipt, transaction, storage, or application identifiers.

## Assertion Boundaries

### Historical Reproduction Assertions

These compare canonical, non-floating values such as integer minor currency units, dates, counts, or stable classifications. They prove whether the recorded evidence agrees.

They do not:

- execute legacy formulas;
- invoke the Laravel assessment engine;
- recalculate historical taxpayer liability;
- rewrite historical values;
- infer missing fee identity;
- establish future policy.

### Future Policy Assertions

These track whether a reproduced behavior is `pending`, `accepted`, or `rejected` by municipal authority. An accepted assertion requires a decision reference, authority role, and decision date.

The suite records authority evidence but never executes the policy assertion. Actual Laravel policy remains owned by the authoritative assessment domain and requires its own implementation and tests after acceptance.

## Historical Divergences

Specimens preserve contradictory historical facts without choosing among them. For `CAL-2026-001`, the PHP 13,000 application summary and PHP 14,535 assessment remain separately visible with disposition `preserve_both_unresolved`.

Historical assessment lines without exact fee-policy identity remain immutable historical financial evidence with incomplete policy provenance. Fee names never manufacture missing identity.

## Private Manifest Contract

Private manifests live under:

```text
storage/app/private/financial-calibrations/specimens/{calibration-id}/manifest.json
```

Each manifest requires:

- schema version and `CAL-YYYY-NNN` identifier;
- Golden Financial Specimen classification;
- specimen and production-snapshot SHA-256 fingerprints;
- all five evidence layers;
- historical reproduction assertions;
- separately declared future policy assertions;
- preserved historical divergences.

Sensitive identity fields are rejected by the verifier. Source images and detailed traces remain in checksum-bound private evidence storage and outside Git.

## Verification

Run the suite with a stable reference:

```bash
php artisan financial:verify-calibrations \
    --run-id=financial-calibration-suite-YYYYMMDD-NNN
```

Machine-readable output is available with `--json`. Immutable results are written to:

```text
storage/app/private/financial-calibrations/runs/{run-id}/
```

A historical assertion mismatch returns a non-zero exit code while preserving diagnostic evidence. Pending future policy is an expected blocked state and does not make historical reproduction fail.

## First Specimen

`CAL-2026-001` is the first Golden Financial Specimen. It passes historical reproduction for the original assessment and schedule while leaving essential-commodity classification/rate, quarterly allocation policy, due-date delinquency, surcharge, penalty, and rounding authority pending.

Verified run: `financial-calibration-suite-20260817-001` - one specimen passed historical reproduction; five future-policy assertions remain blocked pending authority; no formulas or policy assertions executed.

Additional specimens should be added only when municipality-issued evidence has private provenance and an exact production trace. Contradictions between specimens are Board Triggers; they must not be averaged, normalized, or resolved by choosing the most convenient outcome.
