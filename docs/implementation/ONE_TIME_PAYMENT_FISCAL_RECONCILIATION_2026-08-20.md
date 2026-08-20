# One-Time Payment Fiscal Reconciliation

Status: **FISCAL / TREASURY DECISION REQUIRED — BOARD TRIGGER; PAYMENT POLICY UNCHANGED**

Sources: `OPERATIONAL-NELSON-001`; `LEGAL-MRC-001`; `CONTRACT-TOR-001`; legacy and current payment-schedule evidence.

## Evidence tension

The operational table states that the applicant pays all assessed business taxes, regulatory fees, and other applicable charges `in one transaction`.

The Revised Municipal Revenue Code contains payment provisions that cannot be silently narrowed by operational prose. In particular, Section 2E.03 states that covered taxes may be paid once within the first twenty days of January or in quarterly installments within the first twenty days of January, April, July, and October. Other provisions contain distinct installment, recomputation, timing, and exception rules, including contractor project-term installments.

Legacy evidence supports annual, semiannual, and quarterly schedule sections. Current Laravel deliberately preserves one full-assessment `single` schedule in the preview while leaving statutory schedule policy unresolved; its collection model can record a partial collection against a remaining balance.

## Decision required from accepted fiscal/Treasury authority

1. What precise scope does `in one transaction` govern: all business-permit applications, only the current BOSS service flow, only applicants choosing full payment, only regulatory fees, or another bounded class?
2. Does it mean one consolidated collection transaction while preserving statutory installment elections across eligible taxes?
3. Which Revenue Code taxes, fees, and charges may or must be paid annually, quarterly, semiannually, by project-term installment, or through another schedule?
4. Are partial payments permitted within a due installment or only across authorized schedule sections?
5. How are mixed obligations handled when some components allow installments and others require full payment?
6. Which application types, start dates, amendments, reassessments, deficiency assessments, and business classes are exceptions?
7. What authority adopted the current one-transaction practice, and how is it reconciled with the Revenue Code?
8. What exact schedule, due-date, collection, receipt, delinquency, surcharge, interest, and rounding rules are accepted for execution?

## Refusal boundary

Until accepted fiscal authority answers these questions, Laravel must not disable partial-capable collection evidence, remove installment seams, assert that one-time payment is universal, or calculate unaccepted schedules. The preview may demonstrate one full payment as a scenario only and must disclose that it is not universal policy.
