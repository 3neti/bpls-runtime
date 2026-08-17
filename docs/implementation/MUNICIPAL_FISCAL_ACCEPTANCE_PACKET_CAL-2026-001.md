# Municipal Fiscal Acceptance Packet CAL-2026-001

Status: **PENDING MUNICIPAL FISCAL DECISION**

Prepared: 2026-08-17

## Purpose

This packet asks the appropriate Municipality of Ipil fiscal/revenue authority to decide five bounded rules needed for future Laravel assessments. It does not ask the Municipality to approve historical data, the legacy evaluator, or a production migration.

The packet contains no taxpayer, business, receipt, transaction, application, or document identifiers. Its calibration source is Golden Financial Specimen `CAL-2026-001`; identifying source evidence remains private and checksum-bound.

## Authority Boundary

Nelson, Municipality of Ipil IT Head, supplied operational clarification on 2026-08-17 that fresh-fish retail intentionally receives the essential-commodity half-rate and delinquency begins on the day after the due date. These answers are strong operational evidence. Under the adopted financial reconciliation contract, they do not by themselves establish fiscal-policy authority.

The accepting official must record the office/role through which the Municipality authorizes revenue policy. If the accepting official determines that another office, ordinance, resolution, or legal instrument is required, return the item for clarification rather than accepting it conditionally.

Acceptance in this packet does not activate software. An accepted decision must subsequently become a versioned executable-policy record with its authority, effective dates, tests, and assessment snapshot provenance.

## Evidence Classes

| Evidence class | What it establishes | What it does not establish |
|---|---|---|
| Revenue Code | Enacted legal text and stated ceilings/rules | Operational interpretation where text is ambiguous or incomplete |
| Production configuration | What the deployed system was configured to calculate | Legal correctness or current authority |
| Legacy evaluator | What the old software computed | Accepted future Laravel behavior |
| Persisted history and specimen | What was historically assessed and scheduled in the calibrated case | A general rule for all taxpayers or periods |
| IT Head clarification | Confirmed operational intent/practice | Fiscal authority unless the Municipality designates that role accordingly |

## Decision 1: Essential-Commodity Retailer Rate

**Proposed executable statement**

When an accepted classification identifies a retail line of business as fresh fish/essential commodity, calculate the retailer tax under the applicable retailer bracket and charge 50% of that amount, subject to the rule's effective date and eligibility evidence.

**Evidence convergence**

- Revenue Code evidence contains the retailer brackets and an essential-commodity one-half ceiling.
- Production configuration and the legacy evaluator apply the half-rate in `CAL-2026-001`.
- The persisted assessment and municipality-issued specimen agree with that calculation.
- The IT Head confirmed the operational intent on 2026-08-17.

**Still requiring fiscal decision**

- Authoritative eligibility classification and scope beyond the calibrated fresh-fish line.
- Effective period and treatment of mixed or multiple lines of business.

Decision: `[ ] Accept` `[ ] Reject` `[ ] Return for clarification`

Accepted scope / conditions:

## Decision 2: Quarterly Allocation

**Proposed executable statement**

For an assessment accepted for quarterly payment, distribute the Business Tax equally across the selected quarters and charge non-tax annual fees entirely in Q1. Any indivisible-cent remainder must follow the separately accepted rounding decision below.

**Evidence convergence**

- `CAL-2026-001` persisted and printed Q2-Q4 as one quarter of Business Tax each.
- Q1 equals one quarter of Business Tax plus all six non-tax annual fees.
- The four persisted schedule totals equal the persisted assessment total.

**Still requiring fiscal decision**

- Whether the rule applies to every quarterly assessment or only specified taxes/fees.
- Whether a taxpayer may select fewer than all remaining quarters and how midyear applications are allocated.
- Treatment of amendments, reassessments, deficiencies, PIL, surcharge, and penalty.

Decision: `[ ] Accept` `[ ] Reject` `[ ] Return for clarification`

Accepted scope / conditions:

## Decision 3: Delinquency Start

**Proposed executable statement**

A quarterly obligation becomes delinquent on the calendar day following its accepted due date, not on the due date itself.

**Evidence convergence and conflict**

- The IT Head confirmed the following-day rule on 2026-08-17.
- The legacy evaluator treated the due date itself as late. That behavior is retained only as historical implementation evidence and is rejected as future behavior.

**Still requiring fiscal decision**

- Scope across quarterly tax, annual fees, installments, deficiencies, and other municipal obligations.
- Effect of weekends, holidays, declared non-working days, and administratively moved due dates.
- Time zone and end-of-day boundary.

Decision: `[ ] Accept` `[ ] Reject` `[ ] Return for clarification`

Accepted scope / conditions:

## Decision 4: Surcharge And Penalty Basis

**Decision required**

Specify the authoritative surcharge percentage, penalty/interest rate, base amount, period unit, minimum-period treatment, compounding behavior, caps, and application order. The calibrated history and legacy evaluator may be used to explain prior outcomes, but must not select these rules by imitation.

Record explicitly:

- Surcharge rate and base:
- Penalty/interest rate and base:
- First chargeable period:
- Partial-period/minimum-period treatment:
- Compounding or simple treatment:
- Maximum period or cap:
- Applicable obligations and exclusions:
- Effective date:

Decision: `[ ] Accept completed specification` `[ ] Reject` `[ ] Return for clarification`

## Decision 5: Authoritative Rounding Sequence

**Decision required**

Specify where currency rounding occurs and how remainder cents are allocated. Legacy JavaScript floating-point output is implementation evidence, not rounding authority.

Record explicitly:

- Precision used during bracket/formula calculation:
- Point at which the assessed line is rounded to centavos:
- Allocation of remainder centavos across quarters:
- Rounding of surcharge and penalty:
- Rounding method for midpoint values:
- Whether totals are sums of rounded lines or rounded aggregate values:
- Effective date:

Decision: `[ ] Accept completed specification` `[ ] Reject` `[ ] Return for clarification`

## Decision Record

Municipal authority / office:

Authorized official:

Authority instrument or reference:

Decision date:

Effective date / covered assessment periods:

Notes:

## Software And Migration Consequences

- `Accepted`: permits a separate, versioned executable-policy implementation after tests and evidence review. It does not change historical assessments.
- `Rejected`: the proposed behavior remains non-executable; historical evidence is preserved unchanged.
- `Return for clarification`: the rule remains authority-blocked.
- Historical finance will preserve what was actually assessed, scheduled, collected, and paid. Missing fee-policy identity remains explicitly incomplete; fee names never manufacture identity.
- Production migration execution and cutover remain unauthorized regardless of decisions in this packet.

## Related Evidence

- `docs/implementation/ASSESSMENT_CALIBRATION_CAL-2026-001.md`
- `docs/implementation/FINANCIAL_CALIBRATION_SUITE.md`
- `docs/implementation/PRODUCTION_FINANCIAL_FORMULA_RECONCILIATION.md`
- Private calibration and production-snapshot evidence identified in Engineering Program Review #009
