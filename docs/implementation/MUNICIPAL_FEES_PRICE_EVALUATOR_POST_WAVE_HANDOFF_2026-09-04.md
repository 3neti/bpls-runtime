# Municipal Fees, Price Composition, and Evaluator V1 — Post-Wave Chief Architect Hand-off

Status: **READY FOR ARCHITECTURAL RECONCILIATION**

Prepared: 2026-09-04

Implementation range: `47cd9d192ade47e9eaa0163cf967c40442207280` through `a10a157649d900f855b03cc67571f122d7f78bf1`

Current local baseline: `a10a157649d900f855b03cc67571f122d7f78bf1` on clean `main`

Remote comparison at preparation time: `origin/main` at `5b41b3f`; this hand-off covers 17 local commits, including the original Municipal Fees / Price Composition implementation and 16 subsequent refinements.

## Purpose

This report returns the complete post-instruction implementation to the Chief Architect. It records what is now executable, what changed through stakeholder testing, where the implementation intentionally remains fail-closed, and which newer product directions require reconciliation with the September 2 Chief Architect Compass.

This is a hand-off for architectural disposition. It is not authority to promote provisional evidence, deploy to Cloud/UAT, activate uncommissioned fees, or expand the wave.

## Executive Outcome

The original **Municipal Fees + Price Composition + Evaluator V1** wave is implemented and then materially clarified through direct stakeholder testing.

The working lifecycle is now:

```text
Citizen draft
  → formal submission and submission tracking reference
  → mandatory BPLO routing determination
  → concerned-office Evaluation responsibilities
  → scheduled/default amount visible to each office
  → Confirm / Override / Not Applicable
  → inspection or office sign-off where required
  → Paperless Payment Order per applicable office determination
  → pro-forma Price calculation and completed Evaluation
  → immutable Assessment and PriceReport snapshots
  → Municipal Treasurer exact-snapshot decision
  → Payment Schedule
  → over-the-counter collection or QR Ph payment request
  → authoritative collection confirmation
  → Official Receipt through the existing separate action
```

The Municipality still controls BPLO routing, office applicability, overrides, final Assessment review, Treasury approval, collection, and receipt issuance. Automation reduces repetitive entry but does not silently supply municipal authority.

## Delivered Architecture

### 1. Municipal Fees governance

- Fee Rules support the two approved families: **Application-wide** and **Line-of-Business**.
- Fee revisions and audit events are append-only records; proposed revisions do not rewrite a current rule or a historical Assessment.
- Active calculation rules exclude scenario, synthetic, provisional UAT, historical, mock, test, and legacy-evidence-only records.
- Staff can inspect a read-only Fee Matrix from the Evaluation workspace.
- Management links from the schedule return to the appropriate Fee Rule maintenance surface.
- Citizen Services & Fees remains restricted to commissioned, public-safe facts.

The Municipal Fee Matrix was reorganized from policy-heavy cards into an interactive schedule of fees grouped by service category. It separates:

- **Current Fee Rules** — governed entries eligible for the pricing boundary when their status permits execution; and
- **Ordinance Register** — structured Revenue Code evidence, including schedule rows and clauses, which does not execute merely because it is visible.

The local specimen observed during review showed four current Fee Rules, one executable rule, 31 fee/rate provisions, and 108 ordinance provisions. These are dataset observations, not permanent contractual counts.

### 2. Canonical price input and transient Price engine

The price boundary is now:

```text
Application + governed Fee Rules + resolved Evaluation/Paperless Payment Orders
  → AssessmentPriceInputResolver
  → versioned AssessmentPriceInput DTO
  → transient Price
  → ResolvedPrice
  → structured PriceReport
  → immutable Assessment snapshots
```

Delivered contracts include:

- `AssessmentContextData`
- `AssessmentPriceInput`
- `AssessmentPriceComponentInput`
- `AssessmentPriceModifierInput`
- `AssessmentTaxInput`
- `Price`
- `PriceComponent`
- `PriceModifier`
- `PriceTax`
- `ResolvedPrice`
- `PriceReport`
- `HistoricalPriceReport`
- `CanonicalFinancialFingerprint`

`Price` is BPLS-owned and independent of Whitecube. Its ergonomics include `Price::fromMoney()` and `Price::fromInput()`. It uses Brick Money, has no Eloquent or database access, rejects mixed currencies and duplicate exact-once keys, and does not perform FX.

Canonical amounts remain ISO currency plus integer minor units. Symbols and formatted major units are presentation only. Fingerprints ignore presentation symbols.

### 3. Assessment freeze and reconstruction

Assessment creation now persists:

- the canonical `AssessmentPriceInput` snapshot;
- the canonical `PriceReport` snapshot;
- deterministic fingerprints for both; and
- Assessment lines projected from the same resolved composition.

The enforced invariant is:

```text
Assessment.total
  = sum(Assessment lines)
  = ResolvedPrice.total
  = PriceReport.total
```

All sides use the same ISO currency and integer minor-unit amount. Historical reconstruction validates and reads the frozen PriceReport; it does not recalculate from current Fee Rules.

The certified 2025 New and 2026 Renewal scenarios remain financially unchanged at `PHP 122000` minor units, or **₱1,220.00**.

### 4. Evaluator V1

The staff page is now an application-specific **Municipal evaluation workspace**, not a narrative policy report.

It presents:

- the mandatory BPLO routing record;
- office work grouped by concerned office;
- open and completed determinations;
- scheduled/default amount and its provenance;
- determined amount and variance;
- Confirm, Override, and Not Applicable actions;
- reason, source classification, actor, office, and time;
- Paperless Payment Order state; and
- a Price-backed pro-forma calculator.

The calculator is populated from the application and current Evaluation DTO context. It creates a non-official preview through the same transient Price engine and returns input/report fingerprints. It does not persist the transient `Price` object or make an open charge payable.

The Evaluation form now defaults the **Amount to resolve** when an exact proposal exists. A concerned office can confirm it, override it with provenance, or mark it Not Applicable. Inspection-bearing responsibilities retain the proposed amount while requiring the inspection/sign-off before completion.

### 5. Default confirmation and reduced manual entry

Two distinct mechanisms now exist:

1. **Canonical office confirmation** can confirm selected open office charges together only when each has an exact default and no outstanding inspection.
2. **Lifecycle Laboratory helpers** accelerate the synthetic cleanroom:
   - `Complete routine office confirmations` confirms all eligible non-inspection defaults across concerned offices using each cleanroom office actor.
   - `Simulate remaining office reviews` completes only inspection-bearing cleanroom responsibilities with an explicit statement that no real inspection occurred.

Both cleanroom helpers fail closed outside an active `synthetic_only` run with `production_liability = false`.

This resolved the earlier defect where the one-click routine action confirmed only the Assessor and left other eligible offices untouched. Engineering now retains its default amount while its inspection remains pending; its Paperless Payment Order issues only after the inspection/sign-off is completed.

### 6. Paperless Payment Orders and Application Form Page 2

An applicable resolved concerned-office charge issues one Paperless Payment Order through the canonical action. The action verifies:

- explicit BPLO-routed work;
- office and application provenance;
- resolved applicability;
- a non-negative exact amount; and
- the same office actor who resolved the amount.

Application Form Page 2 now projects, by office:

- determination status;
- scheduled or determined amount;
- Paperless Payment Order identity;
- office total;
- electronic certification actor and time; and
- the emerging or prepared consolidated Assessment total.

This projection does not itself create an Assessment, make an amount payable, record payment, or issue a permit. Post-payment verification signatures and legal release remain outside this wave.

### 7. Citizen application corrections

The following intake defects were corrected during stakeholder testing:

- The Oath of Undertaking is bound to the canonical application owner rather than a stale synthetic fixture name.
- Loading **SAHAO SARI-SARI STORE** now carries **SARHANA ALLANI SAHAO** consistently into the application and oath.
- Legacy specimen loading synchronizes the selected business owner with the oath projection.
- The citizen draft detail was tightened to remove repeated explanations and duplicate navigation while retaining the useful owner, business, activity, document, and timeline facts.
- Formal submission records the applicant's attestation evidence rather than leaving the earlier checkbox semantically invisible.
- A submitted application receives a `SUB-...` tracking reference while the official Application Number remains unassigned. The UI now labels these separately and does not concatenate them into one apparent number.
- Submission remains idempotent and does not imply documentary sufficiency, Assessment acceptance, payment, permit issuance, or release.

### 8. Lifecycle Laboratory hand-off

The previously backend-only “office work created” result now has a dedicated **Office Reviews Assigned** page. It explains:

- which offices BPLO selected;
- which responsibilities were created;
- what each office must determine;
- the current proposed/default amount;
- inspection requirements;
- Paperless Payment Order state; and
- the next role-specific action.

The page links to the canonical Evaluation workspace and the relevant application. The staff application detail now points directly to the Evaluation workspace so that **Assess** no longer appears to do nothing when office work is the current task.

### 9. QR Ph in the Payment Schedule workflow

The existing citizen QR Ph payment engine is now exposed on the staff Payment Schedule rather than duplicated.

The staff panel shows:

- exact unpaid amount;
- request status;
- collection status;
- Generate QR Ph / Generate fresh QR;
- provider-returned QR image; and
- expiration countdown.

Staff and citizen initiation reuse one `XChangePayment` and one active `XChangePaymentAttempt` for the exact approved obligation. Eligibility requires the current unpaid Payment Schedule, the exact immutable Assessment, and the matching Municipal Treasurer approval fingerprint.

Generating a QR does not record a collection or issue an Official Receipt. Status polling records the existing online QR Ph Treasury collection only after authoritative full-amount confirmation. Amount, target, and external-reference mismatches fail closed. Receipt issuance remains a separate existing action.

## UX Changes Driven by Stakeholder Testing

The implementation moved away from dense policy narration toward immediate municipal tasks:

| Surface | Earlier problem | Current behavior |
| --- | --- | --- |
| Citizen draft | Repeated warnings, actions, and record facts | One concise draft status, primary action, retained evidence |
| Submission identity | “Not yet assigned” visually merged with `SUB-...` | Official Application Number and submission tracking reference are distinct |
| Evaluation | Long routing narrative with no obvious task | Office-grouped work, current action, defaults, PPO state, calculator |
| Lifecycle hand-off | Canonical action produced a result with no usable page | Dedicated Office Reviews Assigned page and direct workspace link |
| Fee Matrix | Sparse rules and policy-heavy ordinance text | Interactive, categorized schedule with governed rules and ordinance evidence |
| Amount determination | Blank amount despite an available proposal | Exact default pre-populated with provenance and override path |
| Routine cleanroom work | Repetitive confirmations; only Assessor completed | All eligible office defaults confirmed; inspections remain explicit |
| Application Form Page 2 | No visible office progress | Office determinations, PPOs, and electronic certification projection |
| Payment Schedule | Contradictory “available/not configured” prose | Operational QR Ph request panel with exact amount and lifecycle state |

## Preserved Boundaries

The post-wave work did not add:

- production tax, surcharge, penalty, or deficiency-tax formulas;
- percentage modifiers;
- Sanggunian approval workflow;
- quarterly allocation formulas;
- post-payment Page 2 verification/signature workflow;
- permit release or registry claiming;
- production numbering authority;
- actual Mayor-signing or legal-effect semantics;
- Cloud/UAT deployment or production integration cutover;
- implicit foreign exchange; or
- a second collection, receipt, or QR payment engine.

Ordinance evidence remains non-executable until reconciled into a governed Fee Rule. Synthetic scenario values do not become municipal truth.

## Verification Evidence

### Focused financial and workflow regression

The current baseline passes the combined high-value suite:

```text
71 tests passed
1,044 assertions
```

Covered files include Municipal Fees / Price Composition, Evaluation domain and HTTP behavior, Lifecycle Laboratory, citizen submission, executable Application Form, and QR Ph.

The final QR Ph and Payment Schedule focused run passed:

```text
25 tests passed
265 assertions
```

Targeted PHPStan for the new QR controller and payment actions passed with zero errors. Pint, TypeScript, ESLint, Prettier, and the production frontend build passed.

### Browser evidence

- Staff Payment Schedule visually verified with the QR Ph panel.
- Exact displayed specimen balance: **₱5,175.00**.
- Desktop: panel visible with amount, request state, collection state, and Generate QR Ph action.
- Mobile `390 × 844`: panel width remained within the viewport with no horizontal overflow.
- The live Generate action was deliberately not invoked during browser review because it would transmit the current Payment Schedule to the configured payment integration. Automated tests cover initiation and confirmation without mutating the stakeholder record.

### Full-suite status

The full suite completed with:

```text
793 passed
2 failed
1 skipped
12,295 assertions
```

The two failures are outside this wave:

1. the default example feature test expects `/` to return 200 while the application returns 404; and
2. the public permit-verification test expects the preview sample to be unavailable, while the current response reports the existing preview path as available/not-started.

Neither failure touches Municipal Fees, Price, Evaluation, Paperless Payment Orders, Payment Schedules, or QR Ph. They should nevertheless be reconciled before claiming a globally green baseline.

## Architectural Reconciliation Required

### Decision 0 — Brick Money dependency

The approved architecture explicitly required Brick Money while also saying “no new package adoption.” Brick Money was not a direct dependency at the parent baseline, so `47cd9d1` added `brick/money ^0.11` to implement the required monetary semantics. No Whitecube dependency was added.

The Chief Architect should explicitly ratify Brick Money as the narrow exception or direct replacement for the prohibited broader package adoption. If “no new package adoption” was intended literally, the current dependency must be reconsidered before this architecture is treated as final.

### Decision 1 — Application Form Page 2

The September 2 Chief Architect Compass states that the Application Form Page 2 Assessment area is unused and should not be populated. Subsequent explicit stakeholder direction produced the current office-determination/Paperless-Payment-Order/electronic-certification projection.

The Chief Architect should decide whether to:

- ratify Page 2 as a pre-Assessment office certification view while retaining the separate Computation/Assessment Slip as the financial authority;
- move this projection to a separate municipal working paper; or
- remove it from the Application Form.

The current implementation does not treat Page 2 as the payable Assessment, but the canonical documents must say so if it is retained.

### Decision 2 — Default amount authority

The UI now sensibly provides a default whenever exact evidence exists. That evidence may be:

- an executable governed Fee Rule;
- an exact recorded schedule requiring municipal confirmation; or
- a source-backed/provisional cleanroom proposal.

The source classification is preserved, but the Chief Architect should ratify which classes may be bulk-confirmed outside the Lifecycle Laboratory and whether historical amounts may ever be proposed as defaults in production.

### Decision 3 — QR Ph capabilities and reconciliation ownership

The staff QR action currently uses `payment_schedules.view`, matching the fact that a citizen with access to their own financials can initiate the same request. Authoritative provider confirmation can cause the system to record a Treasury collection through the existing collection action.

The Chief Architect should decide whether staff initiation and reconciliation require dedicated capabilities, for example separate **initiate online payment** and **reconcile online payment** permissions, and identify the owning Treasury/Cashier role.

### Decision 4 — Office electronic certification meaning

The current Page 2 label is **Electronically certified** when all charge responsibilities for that office are resolved. This is an operational completion signal, not a statutory digital signature or post-payment verification.

The Municipality should confirm the accepted label, signer role, evidentiary meaning, and whether non-charge responsibilities must also be complete.

### Decision 5 — Fee commissioning program

The Ordinance Register now makes the full schedule legible, but most entries remain evidence rather than executable rules. The next municipal-policy program must reconcile service identity, amount/rate semantics, units, inclusivity, cadence, responsible office, payer, and effective dates before commissioning additional Fee Rules.

### Decision 6 — Submission and official Application Number

The system correctly separates the submission tracking reference from an official Application Number. The Municipality still needs to define who assigns the official number, at what lifecycle event, under which sequence, and whether rejected or withdrawn submissions consume a number.

## Recommended Next Bounded Move

Do not begin another broad implementation wave yet.

The recommended next action is a short **Architectural Reconciliation Packet** covering only:

1. ratification of the Brick Money dependency;
2. Page 2 ownership and meaning;
3. default amount authority by source classification;
4. office electronic certification semantics;
5. QR Ph staff capabilities and Treasury reconciliation ownership; and
6. the fee-commissioning sequence; and
7. official Application Number assignment.

After those decisions, update the Chief Architect Compass, Architecture Decision Register, workflow lifecycle map, and UI/UX hand-off before assigning the next code packet.

The fee-commissioning program should then proceed category by category from the structured Ordinance Register. Production tax/rate formulas and quarterly policy should remain separate fiscal decision packets.

## Commit Ledger

| Commit | Delivered change |
| --- | --- |
| `47cd9d1` | Municipal Fees governance, versioned revisions/audit, typed input DTOs, transient Price engine, reports/fingerprints, Evaluation integration, immutable Assessment snapshots |
| `e4301b7` | Bound Application Form oath to the canonical owner record |
| `16bbde9` | Synchronized legacy specimen owner with oath projection |
| `d3e8180` | Tightened citizen draft review |
| `18e10de` | Clarified attested submission, submission tracking reference, and unassigned official Application Number |
| `b342b1f` | Reframed the staff Evaluation as an operational municipal workspace |
| `022564e` | Added the Lifecycle Laboratory Office Reviews Assigned page |
| `b00b7cc` | Guided the hand-off directly into the canonical Evaluation workspace |
| `94b5983` | Made contextual Fee Matrix access functional and actionable |
| `9d8a565` | Exposed structured Ordinance Register evidence in the matrix |
| `a140e82` | Organized the complete schedule of fees interactively by category |
| `e0c6703` | Focused office evaluation and added the Price-backed pro-forma calculator |
| `f8ff1f9` | Projected office determinations and Paperless Payment Orders on Application Form Page 2 |
| `8a952ec` | Populated exact office defaults and added grouped confirmation |
| `2964d01` | Automated eligible routine confirmations inside the synthetic cleanroom |
| `31a6550` | Preserved inspection-pending defaults and added explicit synthetic office-review simulation |
| `a10a157` | Added the shared QR Ph request to the staff Payment Schedule workflow |

## Handoff Disposition

**DOMAIN AND AUTHORITY DECISIONS REQUIRED BEFORE THE NEXT BROAD WAVE.**

The implementation is internally coherent and high-value focused tests are green. The next Chief Architect action should be reconciliation of the seven post-wave decisions above, followed by a narrow implementation assignment. No production promotion, fee activation, Cloud/UAT deployment, or new fiscal formula is implied by this report.
