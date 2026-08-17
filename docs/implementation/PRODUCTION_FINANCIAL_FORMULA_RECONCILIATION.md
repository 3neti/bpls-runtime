# Production Financial Formula Reconciliation

Status: **Decision required before production financial policy can execute**

Snapshot fingerprint: `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57`

Private run: `prod-financial-formula-reconciliation-20260817-001`

Scope: read-only reconciliation evidence. No formula was executed, no historical liability was recalculated, no source or domain record was changed, and no migration or cutover was authorized.

## Objective

Establish what can be proven across the five financial evidence layers:

```text
Revenue Code text
        -> production configuration
        -> legacy evaluator behavior
        -> historical persisted outcomes
        -> accepted interpretation or unresolved discrepancy
```

The run deliberately separates evidence from authority. A deployed formula proves historical configuration, not legality. A historical amount proves a persisted outcome, not the formula that governed it.

## Production Configuration Inventory

| Evidence class | Count | Reconciliation state |
| --- | ---: | --- |
| Fee definitions | 294 | Observed; no production fee is accepted as executable policy by this run |
| Constant fee definitions | 65 | Require ordinance and operational identity crosswalk |
| Formula fee definitions | 119 | Evaluator characterized; interpretation and rounding unaccepted |
| Range fee definitions | 110 | Brackets, eligibility, and boundary semantics unaccepted |
| Fee overrides | 9 | Eight have a surviving fee parent; one has no surviving fee parent |
| UOM records | 4,345 | 215 distinct variable names observed |
| Surcharge/penalty configurations | 1 | Configuration observed; trigger, basis, and authority unresolved |
| Payment schedules | 7,601 | Historical persisted evidence only |
| Payments | 5,806 | Historical persisted evidence only |
| Persisted schedule fee items | 24,169 | None retains an exact source fee identifier |

Fee categories are also historically inconsistent as a classification signal: 149 fee definitions have no category, 86 are `Regulatory Fee`, 41 are `Other Charges`, and 18 are `Tax`. These categories are evidence and are not normalized by this run.

## Evaluator Characterization

The authoritative legacy archive was read without executing JavaScript formulas.

| Component | Source SHA-256 | Observed behavior |
| --- | --- | --- |
| Fee calculator | `e49dfd867536d6c576709215198d53a20d679fd154cfa3b5af2730623d5db6c7` | Dynamic expression evaluation; invalid or missing formulas return zero; negative results are clamped to zero; the evaluator does not round formula results |
| Surcharge/penalty calculator | `327e43a0ab09c20c3dc1678f3c47c4b9fb39a60a1b8c8879142d0da0f9ea2932` | Dynamic expression evaluation; payment on the due date is treated as surcharge-eligible; missed months use a thirty-day ceiling with a minimum of one; outputs are rounded to two decimals |
| Schedule construction | `9c9b311ee218d44d5e41156f695ddc8a6024cdaed7b12a68c0452dfdffc7d28f` | Fee identity is optional; tax allocation and section totals round to two decimals; payment status compares rounded totals |

One range formula contains the token `unitOfMeasurement`, which is not reconciled to the 215 observed production UOM variable identities. It remains non-executable.

## Historical Outcome Attribution

Exact configuration-to-outcome attribution is blocked.

- The 24,169 persisted fee items contain zero exact `feeId` values.
- They retain only 13 distinct fee-name signals.
- Eight of those 13 names match multiple production fee definitions.
- One name corresponds to as many as 29 definitions.
- 157 definitions have some name signal; 137 have none.
- Three persisted fee items are explicitly marked as edited.

Name matching is therefore neither unique nor authorized. Summing name matches across definitions would multiply-count the same historical items. The system refuses to infer fee identity from name, amount, date, application adjacency, or formula resemblance.

The production snapshot still provides useful structural evidence: schedule components reconcile to persisted schedule totals, paid amounts do not exceed persisted totals, completed payments reconcile to schedule paid totals, resolved schedule/payment pairs agree on application identity, and transaction numbers are unique. This supports historical arithmetic coherence. It does not prove the governing formula, legal authority, rounding rule, or receipt meaning.

## Revenue Code Reconciliation

The current Laravel catalog preserves ordinance text and keeps unresolved clauses non-executable. The accepted PHP 350 annual business inspection fee remains the only currently authorized Revenue Code execution path. This run does not automatically associate that accepted rule with any production fee identifier.

A financially material timing discrepancy requires municipal decision:

- The Revenue Code evidence states that failure to pay **within the prescribed time** triggers a 25 percent surcharge and a monthly two percent penalty, subject to unresolved scope and mechanics.
- The legacy evaluator explicitly treats payment **on the due date** as surcharge-eligible and assigns at least one missed month.

The source wording and deployed evaluator do not establish the same trigger. The clause currently cataloged is also Article M evidence, while the deployed calculator is generic. On 2026-08-17, the Municipality of Ipil IT Head clarified that surcharge and penalty start on the following day. The Board rejected the legacy inclusive trigger for future Laravel behavior. The positive following-day rule, applicable scope, minimum-month treatment, rates, and rounding remain non-executable pending the municipal fiscal authority required by the reconciliation contract.

Rounding also remains unresolved. Legacy behavior rounds at different stages: formula results may remain unrounded until later composition, schedule allocation rounds per section, and surcharge outputs round to two decimals. JavaScript floating-point behavior is implementation evidence, not municipal rounding authority.

## Reconciliation Matrix State

| Evidence layer | Current state | What remains required |
| --- | --- | --- |
| Revenue Code | Extracted and traceable | Complete provision-to-production fee identity and resolve ambiguous text |
| Production configuration | Inventoried and fingerprinted | Municipal acceptance of operational identity, eligibility, ranges, overrides, and effective period |
| Legacy evaluator | Source-characterized and hashed | Accepted semantics for errors, negative results, dates, periods, and rounding |
| Historical outcomes | Structurally coherent in aggregate | Exact fee attribution or an authorized alternative evidence source |
| Executable policy | Refused | Versioned reconciliation authority linking all required evidence layers |

Accepted interpretations: **0**

Production policies made executable: **0**

## Decisions Required

1. Confirm the exact identity crosswalk between Revenue Code provisions, operational fee definitions, and future Laravel fee policies.
2. Obtain fiscal-policy authority for the operationally clarified following-day trigger and establish its applicable scope.
3. Establish surcharge and penalty scope, basis, period counting, caps, partial-payment behavior, and rounding.
4. Establish whether any authoritative source can recover exact fee identity for historical schedule items; otherwise accept a quarantined historical-snapshot disposition.
5. Resolve the orphan fee override and confirm the authority and effective scope of the eight surviving overrides.
6. Reconcile UOM variable identity, including the unmatched `unitOfMeasurement` token.

## Evidence

Private artifact root:

`storage/app/private/legacy-migrations/IPIL-CONVEX-SNAPSHOT-56FAD41ABBDEAE8D/prod-convex-stage-reference-catalog-v2-20260816-224400/reconciliation/financial-formulas/prod-financial-formula-reconciliation-20260817-001`

The private JSON contains source identifiers and configuration details required for authorized reconciliation. Git retains only aggregate counts, source hashes, behaviors, discrepancy classes, and decisions required.
