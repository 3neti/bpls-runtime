# BPLS Architecture Consolidation Wave

Status: **READY FOR BOARD REVIEW**

Date: 2026-09-02

Base SHA: `0530c6faa347f4e92440c69a0fc098937c24c5cf`

Scope is limited to deterministic XChange fixture isolation, the `PermitApplication` macro-state contract, the BPLS-native `AssessmentComponent` contract, a PermitApplication-only Model States pilot, and a transient `whitecube/php-prices` fixed-cent parity spike. This wave does not commission downstream permit states, percentage policy, surcharge/penalty rates, quarterly allocation, settlement, Collection/OR changes, registry work, or Cloud/UAT.

## Lifecycle fixture environment contract

Every lifecycle or preview fixture that observes the online-payment boundary must declare `safety.external_dependency_expectations.x_change`. The affected deterministic lifecycle fixtures declare `unconfigured`, run the existing production `DescribeOnlinePaymentBoundary` inside a reversible configuration scope, and restore the ambient XChange configuration even when the fixture throws. The separately configured synthetic stakeholder QR preview declares `configured` and fails closed unless its fixture supplies all three adapter settings. Missing or unsupported fixture expectations fail closed.

Production XChange configuration discovery is unchanged. Authorized live/sandbox integration tests continue to own their separate configuration and authorization gates.

## PermitApplication macro-state contract

### Frozen operational graph

The current persisted names are retained because they are already implemented, externally projected, and supported by accepted evidence:

```text
Draft --SubmitCitizenPermitApplication/CreateAssessmentForPermitApplication--> Assessment
Assessment --RecordAssessmentDecision(approved exact snapshot)---------------> Approval
Approval --CreatePaymentScheduleForAssessment--------------------------------> PendingPayment

Draft | Assessment | Approval | PendingPayment --CancelPermitApplication-----> Cancelled
```

- `Draft` is an editable pre-processing record. A citizen draft is unsubmitted and officially unnumbered.
- `Assessment` is the municipal evaluation/assessment macro phase. It may contain BPLO routing, multiple department responsibilities, Payment Orders, readiness, a computed immutable Assessment, or a returned-for-correction decision without creating a different application state for each subordinate fact.
- `Approval` is the narrow, persisted pre-payment phase reached only when the Municipal Treasurer approves the exact immutable Assessment snapshot. It is not generic permit approval.
- `PendingPayment` means the exact approved Assessment has a Payment Schedule. Whether that schedule is pending, partially paid, or paid remains the Payment Schedule lifecycle.
- `Cancelled` is the implemented terminal operational state. The existing Action owns its authority, reason, history, and terminal metadata.

`HistoricalEvidence` remains a quarantined, non-operational evidence classification. It is not a node in the operational graph. `Released` remains reserved vocabulary only: no current transition may reach it until municipal issuance/release authority and semantics are accepted.

### Why candidate labels were not adopted as states

- **Lodged / municipality received** are timestamped submission and receipt facts. Citizen submission currently moves `Draft` into `Assessment`; a separate state would duplicate those facts without changing the legal command set.
- **BPLO routing / Municipal Evaluation** are work composition inside `Assessment`. BPLO selects situational work; individual offices resolve independent responsibilities and revisions. These facts are concurrent and cannot be represented truthfully by one mutually exclusive application status.
- **Evaluation ready / Ready for Assessment** is a derived projection over lodging, routing, required responsibilities, inspection/review completion, current Payment Orders, pricing eligibility, and fingerprints. Persisting it as a state would become stale.
- **Assessment Prepared** is the existence of an immutable current Assessment and its exact fingerprint. Treasurer approval/return is a separate immutable `AssessmentDecision`; neither should be reduced to another application status.
- **Treasurer ready** is authorization/readiness for a specific actor and exact snapshot, not a durable stage.
- **Payment or receipt available** is derived from XChange configuration, exact approved obligation eligibility, Payment Schedule balance, Collection, and Receipt facts.
- **Paid** belongs to `PaymentSchedule`; receipt availability belongs to Collection/Receipt evidence. The application intentionally remains `PendingPayment` after payment in the current accepted implementation.
- **Post-Payment Compliance** and **Ready for Release** describe composite clearance/artifact/authority projections. Current evidence supports `ready_for_authority_review` only as a truthful refusal boundary, not a new persisted phase.
- **Released** is unsupported operationally. **Historical** is evidence classification, not an operational state.

Canonical Actions remain the business operations. A state transition may only be a consequence recorded by the Action after its authorization, row locking, evidence, exact-snapshot, idempotency, and policy checks succeed. A state object or transition API must never replace those checks.

## AssessmentComponent contract

`AssessmentComponent` is a transient BPLS-owned description of one accepted input to assessment composition. It is not a persisted aggregate and does not replace `FeeRule`, Paperless Payment Order provenance, immutable `Assessment`, or `AssessmentLine`.

Accepted vocabulary is closed:

| Type | Authority/provenance |
| --- | --- |
| `governed_fee` | Executable governed `FeeRule` and exact calculation snapshot |
| `business_tax` | Executable governed tax rule and explicit basis |
| `paperless_payment_order` | Current issued, non-superseded Payment Order line tied to eligible BPLO-routed work/revision |
| `surcharge` | Separately commissioned policy, rate/base/order/rounding only |
| `penalty` | Separately commissioned policy, rate/base/order/rounding only |
| `adjustment` | Separately authorized signed correction with target, reason, actor, and policy evidence |

There is no `other` catch-all. A new type requires explicit acceptance and an enum/code change.

Each component carries:

- stable component key and type;
- `application` or exact application-line/LOB scope;
- source type, source identity, and exact-once key equal to `source_type:source_id`;
- responsible office for Payment Order components;
- legal/policy version;
- signed integer amount in PHP minor units;
- explicit non-negative ordering phase;
- explicit percentage-base component keys (empty for the current fixed-cent specimens);
- rounding instruction;
- optional actor/time evidence where the source supplies it; and
- a complete explanation snapshot for immutable projection.

Composition rejects duplicate exact-once keys, sorts by `(ordering_phase, exact_once_key)`, and fails closed when any percentage-base keys are present because no rate, base, ordering, or rounding policy is commissioned in this wave. Current components use phase `100` only to make fixed-cent accumulation deterministic; it carries no invented surcharge/penalty priority semantics.

The transient immutable projection preserves the full contract fields. Production persistence remains the existing `AssessmentLine` path and unique provenance constraints. Negative adjustments are part of the signed vocabulary but are not production-integrated in this wave; the current unsigned `assessment_lines.amount_cents` schema must not be changed without an accepted adjustment policy and migration decision.

For the canonical Scenario 01 (2025 New) and Scenario 02 (2026 Renewal) specimens, the projector produces six `paperless_payment_order` components plus one `governed_fee`, with seven unique exact-once keys:

```text
Retail LOB          PHP 330.00
Food Service LOB    PHP 540.00
Governed inspection PHP 350.00
Grand Total         PHP 1,220.00
```

## Model States pilot and decision

Transient pilot version: `spatie/laravel-model-states` 2.14.2. The pilot used a shadow cast over the real `permit_applications.status` column and exactly the frozen graph above; it did not touch Payment Order, Assessment, responsibility, Collection, Receipt, or Payment Schedule models.

The package transition API mechanically rejected the important illegal `Assessment -> PendingPayment` transition. The canonical `SubmitCitizenPermitApplication` Action still produced the accepted stored `assessment` value and the pilot cast read it correctly. However, a direct state assignment and save still bypassed the configured graph. Preventing that bypass would require an additional model guard and conversion of every canonical Action from the existing enum assignment to a second state-class API. That would make current semantics less clear, create two representations of the same state, and risk turning transitions into substitutes for the Actions' real business checks.

Decision: **DEFER / NO-ADOPT**. Keep the current enum, Actions, locks, exact-snapshot guards, terminal metadata, and database membership constraint. Reconsider only if the application first gains one accepted transition API that can prohibit every direct status mutation without weakening or duplicating canonical Actions. No Model States code or dependency remains.

## php-prices parity spike and decision

Transient spike version: `whitecube/php-prices` 3.3.0 with `brick/money` 0.10.3. A test-only adapter accepted only ordered BPLS `AssessmentComponent` values, constructed a fresh PHP `Price` per composition, keyed each modifier by the BPLS exact-once key, and returned only BPLS projections and integer minor units. No Whitecube vocabulary entered models, FeeRules, Payment Orders, Assessment lines, or persisted snapshots.

Both canonical specimens produced identical results on repeated runs:

- 2025 New: `33,000 + 54,000 + 35,000 = 122,000` cents;
- 2026 Renewal: `33,000 + 54,000 + 35,000 = 122,000` cents;
- BPLS native total = Brick Money total = php-prices total;
- exact-once keys and deterministic order were unchanged;
- all inputs and outputs remained integer PHP minor units; and
- no rounding occurred or drifted because every component is fixed cents.

The spike exposed a present compatibility cost: php-prices 3.3.0 requires `brick/money` 0.10 and `brick/math` no newer than the 0.14 line, while the application locks `brick/math` 0.18. A transient installation therefore required downgrading Brick Math and moved 34 unrelated locked packages before the exact dependency baseline was restored. The package added no arithmetic or audit value for current fixed-cent inputs.

Decision: **DEFER / NO-ADOPT**. Reconsider a stable compatible version only after an accepted percentage/formula component creates real arithmetic complexity and the municipality has explicitly commissioned its base, ordering, and rounding policy. Do not install `whitecube/laravel-prices`. No Whitecube or Brick Money dependency remains.

## Decision gate

| Package | Decision | Durable result |
| --- | --- | --- |
| `spatie/laravel-model-states` | **DEFER / NO-ADOPT** | Frozen macro-state contract and pilot evidence only; no dependency or state classes retained |
| `whitecube/php-prices` | **DEFER / NO-ADOPT** | BPLS-native component contract retained; transient adapter and dependency removed |
| `whitecube/laravel-prices` | **REJECT** (unchanged from accepted audit) | Its persistence vocabulary conflicts with FeeRule/provenance/immutable Assessment truth |

## Verification disposition

- All seven previously identified configured-XChange failures/error passed independently: citizen processing prepare/audit, citizen authority review prepare/audit/idempotency, both prepare commands, manual receipt audit, and the staff payment-schedule projection.
- Full file-order runs passed: 77 lifecycle scenario runner tests / 845 assertions and 9 staff payment-schedule tests / 123 assertions.
- Focused assessment and Scenario 01/02 tests passed: 18 tests / 540 assertions, including the BPLS `AssessmentComponent` contract.
- Scenario 01 fresh SQLite/PostgreSQL parity passed at semantic hash `03265ccb231cfcf9454a5024d9d36f7386f0b852dafd2ca13956f782587f09b0` and 122,000 centavos.
- Scenario 02 fresh SQLite/PostgreSQL parity passed at semantic hash `6327e33fe70f56c15904d0f354f5017ecbd31bce4db911a31233b60c1532cb1c` and 122,000 centavos.
- Final full suite: **745 tests; 742 passed, 2 failed, 1 expected skip; 11,691 assertions; 0 errors**. The only failures are the accepted pre-existing home-route 404 and stale provisional permit-verification assertion. All environment-sensitive lifecycle cases are green.
- Composer validation, Pint, targeted PHPStan for every new contract/profile class, Prettier, ESLint, TypeScript, and the Vite production build passed. A broader PHPStan invocation over the pre-existing large lifecycle scenario classes still reports their existing Eloquent model/collection inference debt; no baseline or suppression was added.
- The pre-spike `composer.json` and `composer.lock` were restored byte-for-byte. No Spatie, Whitecube, Brick Money, or other dependency change remains.

## Exact next recommendation

Commission no new lifecycle or fiscal behavior. The next bounded move is Board review and acceptance of the frozen PermitApplication macro graph and `AssessmentComponent` vocabulary. After acceptance, tighten direct PermitApplication status mutation behind one BPLS-owned transition guard using the existing enum and canonical Actions; do not revisit either package until that guard is proven and an accepted percentage/formula policy exists.
