# Assessment Calibration CAL-2026-001

Status: **Strong calibration convergence; policy decisions remain required**

Specimen SHA-256: `892d1c07377988ab17d12e60c7bfeb80ba9227bb5f5569bc441bf81092a9f995`

Production snapshot SHA-256: `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57`

The municipality-supplied JPEG and all identifying evidence remain private. This repository record uses only calibration ID `CAL-2026-001` and non-identifying financial findings. The specimen is operational evidence, not financial-policy authorization.

## Findings

### 1. Was the exact production application found?

Yes. The redacted application reference resolves to exactly one production application in the immutable staged snapshot. Its owner, business, line of business, UOM declarations, schedules, payments, clearances, permit, and activity evidence remain privately linked by exact source identifiers.

### 2. Do production totals match the specimen?

The original persisted schedule fee snapshots match all seven printed lines and the PHP 14,535 grand total exactly.

The application row's current summary field instead stores PHP 13,000. No surviving exact activity evidence explains that different summary value. For this calibration, the exact schedule fee snapshots are internally coherent while the application summary remains an unresolved historical discrepancy.

### 3. Do the original quarterly schedules match?

Yes. The four schedules were created together on the specimen date with this base structure:

| Section | Business tax | Other annual fees | Base total |
| --- | ---: | ---: | ---: |
| Q1 | PHP 2,677.50 | PHP 3,825.00 | PHP 6,502.50 |
| Q2 | PHP 2,677.50 | - | PHP 2,677.50 |
| Q3 | PHP 2,677.50 | - | PHP 2,677.50 |
| Q4 | PHP 2,677.50 | - | PHP 2,677.50 |

Later mutations changed Q2's due date and Q3's payable total. Those are subsequent historical states and do not contradict the original specimen.

### 4. What produces the PHP 10,710 Business Tax?

The production declaration records PHP 1,300,000 preceding-year gross sales. The applicable production formula is:

```text
[(grossSales - 400000) * 0.0126] / 2 + 5040
```

The exact arithmetic is:

```text
First PHP 400,000: 400,000 * 2.52% / 2 = PHP 5,040
Excess PHP 900,000: 900,000 * 1.26% / 2 = PHP 5,670
Total: PHP 10,710
```

### 5. Which legacy configuration and evaluator are involved?

The exact production business group supplies a range fee keyed by `grossSales`. The upper range uses the formula above. The legacy browser evaluator substitutes the declared value, evaluates the expression, rejects non-numeric results, and clamps negative results to zero. It does not round the fee-formula result.

### 6. Does the evaluator reproduce the printed tax?

Yes. The expression evaluates exactly to PHP 10,710 for PHP 1,300,000 gross sales. No rounding is involved in this specimen's tax result.

Successful reproduction proves implementation behavior and calibration convergence. It does not authorize the formula for Laravel execution.

### 7. Does the Revenue Code independently support it?

The legal evidence independently supplies the same mathematical components:

- Section 2A.02(d): 2.52 percent on the first PHP 400,000 of retail gross sales and 1.26 percent on the excess.
- Section 2A.02(c): listed essential commodities include agricultural, marine, and fresh-water products, subject to a rate not exceeding one-half of the applicable retailer rate.

The specimen's activity classification and production formula apply that one-half relationship. Convergence is strong, but authority is incomplete because the ordinance states a ceiling and no accepted municipal procedure currently maps operational classifications to the legal essential-commodity category or establishes the chosen rate.

### 8. Are rounding rules involved?

Not in the PHP 10,710 result. Quarterly allocation rounds each split tax amount and each schedule total to two decimals, but PHP 10,710 divides evenly by four.

Rounding is involved in later surcharge history: PHP 669.375 is persisted as PHP 669.38, and the composed PHP 3,400.425 is persisted as PHP 3,400.43.

### 9. What explains the PHP 3,825 other fees?

Production evidence explains every amount:

| Evidence | Amount |
| --- | ---: |
| One employee under three employee-based formulas | PHP 100 + PHP 25 + PHP 100 |
| Mapped permit UOM quantity in the renewal range | PHP 1,000 |
| Exact group-level solid-waste override | PHP 2,500 |
| Mapped weight-and-measure UOM quantity in its range | PHP 100 |
| Total | PHP 3,825 |

The sanitary fee is explicitly excluded. A separate New-only permit fee is excluded and inapplicable to the Renewal. There are no application-level amount overrides.

### 10. What explains the quarterly allocation?

The exact legacy schedule mutation implements the observed structure. It categorizes fees, divides every `Tax` fee equally across all selected periods, and places every non-tax fee at full amount in Q1. It initializes surcharge and penalty at zero.

This explains the specimen exactly. It remains characterization of one deployed mechanism, not an accepted general Laravel scheduling policy.

### 11. Can the handwritten adjustment be explained?

No. The apparent PHP 500 addition and handwritten revised totals do not appear in this exact application's persisted fee snapshots, schedule lines, payments, or activity changes. The image alone cannot establish the fee identity, authority, timing, or whether it belonged to another municipal collection process.

This remains a specific question for Nelson, BPLO, and Treasury.

### 12. What later historical changes are deterministic?

The exact chronology is:

1. Original schedules were created with the printed day-20 due dates and printed base amounts.
2. On April 20, the legacy system added PHP 669.38 surcharge and PHP 53.55 penalty to Q2.
3. Seconds later, Q2's due date was changed from April 20 to April 21 and both charges were removed.
4. The exact PHP 2,677.50 Q2 payment was then recorded.
5. The active configuration was later updated to day-21 quarterly dates; existing schedules were not updated automatically.
6. On July 20, Q3 retained its original July 20 due date and received the same PHP 669.38 surcharge and PHP 53.55 penalty.

The source allows a pending schedule date to be changed and audits the mutation. Neither source nor activity evidence states why Q2 was changed. No holiday, weekend, grace-period, or policy explanation is inferred.

The Q3 values are exactly reproduced by deployed configuration and evaluator behavior:

```text
periodFee = 10,710 / 4 = 2,677.50
surcharge = 2,677.50 * 25% = 669.375 -> 669.38
penalty = 2,677.50 * 1 month * 2% = 53.55
payable = 2,677.50 + 669.375 + 53.55 = 3,400.425 -> 3,400.43
```

The evaluator treats payment on the due date as late and assigns a minimum of one missed month. Reproduction does not authorize either semantic.

### 13. What still requires confirmation?

1. Accepted essential-commodity classification and rate under the ordinance ceiling.
2. Surcharge trigger date, due-date policy, minimum-month rule, period basis, and rounding authority.
3. Authority and reason for the Q2 due-date change and later active configuration change.
4. Meaning and disposition of the application-level PHP 13,000 summary.
5. Meaning and authority of the handwritten apparent PHP 500 adjustment.
6. Historical migration treatment when schedule amounts survive but exact fee identifiers do not.

## Reconciliation Conclusion

The calibration chain converges strongly for the original assessment:

```text
Revenue Code retail bands and essential-commodity half-rate ceiling
        <-> exact production gross-sales formula and declarations
        <-> characterized legacy evaluator
        <-> original persisted fee and schedule snapshots
        <-> municipality-issued assessment specimen
```

It does not yet converge on municipal authority for classification, selected rate under the ceiling, surcharge timing, due-date changes, application-summary divergence, or the handwritten adjustment.

No financial policy was activated or generalized. No historical liability was rewritten. No production or Laravel domain record was changed.
