# BPLS State + Financial Composition Fitness Audit

Status: **READY FOR BOARD REVIEW**

Audit date: 2026-09-02

Canonical implementation audited: `75edb27e16a3e7137187dc58ecd942ee8528d0d8`

Scope: read-only fitness review of `spatie/laravel-model-states` and Whitecube Prices against the current BPLS domain. No package was installed and no business behavior was changed.

## Decision

Adopt neither package now.

- Keep the current enum, Action, database-constraint, immutable-snapshot, and readiness-projection architecture.
- Treat `spatie/laravel-model-states` as a possible later implementation aid for a deliberately approved `PermitApplication` macro graph, not as a general replacement for domain Actions or readiness projections.
- Treat `whitecube/php-prices` as a possible later transient arithmetic primitive behind a BPLS-owned `AssessmentComposition`, only after percentage-base and rounding policy are accepted and a cent-for-cent spike proves value.
- Do not adopt `whitecube/laravel-prices`; its price-history persistence duplicates and weakens the existing `FeeRule`, Payment Order provenance, and immutable Assessment boundaries.

## Full-suite disposition at the audited SHA

The canonical full run produced 741 tests: 732 passed, 7 failed, 1 errored, 1 skipped, 11,557 assertions. Every failure and the error reproduced alone. None was introduced by the current BPLO routing / Paperless Payment Order / Assessment slice.

| Case | Classification | First failing invariant | Isolated reproducibility | Lifecycle certification infrastructure | Blocks this architecture audit |
| --- | --- | --- | --- | --- | --- |
| `returns a successful response` | PRE-EXISTING BASELINE DEFECT | `GET route('home')` expected HTTP 200, received 404. | Always | No | No |
| `citizen permit processing scenario audits browser financial state against canonical records` | FIXTURE/ISOLATION DEFECT | Scenario expects `can_reconcile_online=false`; this environment has all XChange connection settings, so the canonical boundary reports `true` and the audit result becomes `failed`. | Always in the configured environment | Yes | No; it blocks portable lifecycle certification until isolated. |
| `citizen permit authority review scenario composes domain actions idempotently and audits browser evidence` | FIXTURE/ISOLATION DEFECT | The first prepare is marked failed by the same hard-coded XChange expectation; the second prepare therefore re-enters creation and violates unique `permit_applications.application_number`. | Always in the configured environment | Yes | No; it blocks portable lifecycle certification until isolated. |
| `command prepares the citizen permit processing visibility scenario` | FIXTURE/ISOLATION DEFECT | Prepare command returns 1 because the scenario hard-codes `can_reconcile_online=false` while the configured adapter reports `true`. | Always in the configured environment | Yes | No; it blocks portable lifecycle certification until isolated. |
| `command prepares the citizen permit authority review visibility scenario` | FIXTURE/ISOLATION DEFECT | Prepare command returns 1 for the same environment-dependent online-reconciliation expectation. | Always in the configured environment | Yes | No; it blocks portable lifecycle certification until isolated. |
| `manual collection receipt scenario audit compares browser evidence with canonical treasury state` | FIXTURE/ISOLATION DEFECT | Browser fixture records `can_reconcile_online=false` while the audit correctly derives `true` from configured XChange settings. | Always in the configured environment | Yes | No; it blocks portable lifecycle certification until isolated. |
| `staff users with view permission can review a payment schedule` | ENVIRONMENT/ORDER-SENSITIVE DEFECT | Inertia expectation requires `online_payment_boundary.can_reconcile_online=false`; actual is `true` because XChange is configured. | Always in the configured environment; not order-dependent | No | No |
| `public permit verification confirms artifact identity but not release` | PRE-EXISTING BASELINE DEFECT | Stale fixture expects provisional preview `available=false/status=not_available`; current projection returns `available=true/status=not_started`. | Always | No, although it exercises a lifecycle boundary projection | No |
| `live x-change testing issues a payable QR and supports inquiry` | EXPECTED SKIP | Explicit authorization gate requires `XCHANGE_LIVE_TEST=1`. | Always skipped unless explicitly authorized | No | No |

The XChange-sensitive cases predate `75edb27`; the current slice did not touch their hard-coded expectations or the online-payment boundary. The authority-review uniqueness error is a secondary symptom of the failed first prepare, not a new duplicate-creation path from Paperless Payment Orders.

No correction was made. A safe correction needs one explicit test contract: either lifecycle fixtures deliberately disable XChange or their expected reconciliation capability is derived from a named fixture mode. Choosing that contract is separate from this package-fitness audit. The two stale baseline assertions are also outside this slice.

## State taxonomy

Use three distinct semantic categories:

1. **Durable macro state** — a mutually exclusive, persisted stage that controls which commands are legal.
2. **Derived readiness** — a projection over multiple facts; it must not be stored as if it were a transition.
3. **Immutable fact/event** — an occurrence or snapshot whose correction is another fact, decision, or superseding record.

### PermitApplication

The operational core has a coherent macro graph:

```text
Draft -> Assessment -> Approval -> PendingPayment
  |          |             |
  +----------+-------------+-> Cancelled (where the cancellation Action permits)

HistoricalEvidence = separate non-operational import/evidence state
Released = named but deliberately unreachable pending municipal authority
```

`Draft`, `Assessment`, `Approval`, `PendingPayment`, and `Cancelled` are durable macro states. `HistoricalEvidence` is a quarantined evidence classification and should not participate in the operational graph. `Released` must remain unavailable until authority is accepted.

The following are readiness projections or facts, not application states:

- applicant declarations complete, application lodged, and municipality received;
- BPLO routing recorded;
- Evaluation responsibilities complete and Evaluation ready for assessment;
- all eligible Payment Orders current;
- Assessment computed, Treasurer-approved, or returned for correction;
- payment complete and receipt recorded;
- clearances complete;
- permit artifact generated;
- ready for authority review, issuance/release authorized, or legal effect confirmed.

`spatie/laravel-model-states` could make the small macro graph discoverable and reject direct illegal status changes. It would not replace Action authorization, row locks, exact-snapshot guards, or the composite readiness services. Adoption should wait until the Board accepts the terminal release/cancellation graph and the code has one transition API rather than several direct enum assignments.

### PaperlessPaymentOrder

The implemented lifecycle is intentionally small:

```text
resolved applicable office determination
    -> issued immutable Payment Order
    -> optional supersession by a new revision and new issued order
```

Issuance is idempotent per Evaluation item revision. Financial fields and lines are immutable; deletion is refused. Correction creates a new office revision/order and timestamps the predecessor's `superseded_at`. Only current `status=issued`, non-superseded orders tied to eligible revisions enter Assessment. Cancellation is explicitly unresolved and unavailable.

This is an immutable fact with a supersession relation, not a useful mutable state machine. Model States would add ceremony without strengthening the key invariants, which are already enforced by `IssuePaperlessPaymentOrder`, model immutability hooks, foreign keys, and unique constraints. Keep the current design; an enum for the persisted status may improve type safety without introducing a state package.

### Assessment

Assessment is an immutable financial snapshot, while `AssessmentDecision` is the Treasurer's separate immutable approve/return fact bound to the exact total and snapshot hash. Supersession represents correction. This is more accurate than making approval/return states on Assessment itself.

Do not use Model States for Assessment. Tighten snapshot immutability directly if later evidence finds a mutation gap. `Draft` and `Voided` are currently mostly reserved vocabulary; do not create transitions merely because enum cases exist.

### PaymentSchedule

`Pending -> PartiallyPaid -> Paid` is a genuine macro lifecycle driven atomically by collection allocation. `Voided` is named but lacks an accepted operational policy. This noun is a better future Model States candidate than Assessment or Payment Order, but only after installment, void/reversal, and reconciliation policy is commissioned. Existing transactional Actions and amount invariants remain authoritative.

### Evaluation responsibility

Evaluation responsibilities are durable items with append-only revisions. Applicability, resolution, confirmation, inspection completion, targeted-return status, and readiness are projections of the current version/revisions. They are not one mutually exclusive state and should not be collapsed into Model States.

### Collection and Receipt

`TreasuryCollection` has a small `PendingReceipt -> Receipted` lifecycle; `Voided` is unavailable without policy. A `Receipt` is an issued immutable fact; voiding should later be a separately authorized event or superseding fact, not casual mutation. Defer Model States until void, reversal, and official receipt authority are accepted.

## BPLS-native Assessment composition

Introduce vocabulary before introducing a library. A transient `AssessmentComponent` should describe an accepted input to composition with at least:

- stable component key and type;
- signed amount or authorized percentage operation;
- currency and scope (`application` or exact LOB/application line);
- ordering phase and explicit percentage base;
- provenance type/id and exact-once key;
- actor/time where applicable;
- legal/policy version, basis, and rounding instruction;
- explanation snapshot destined for `AssessmentLine`.

Accepted component types should be:

| Component type | Authoritative source |
| --- | --- |
| `governed_fee` | executable `FeeRule` / Price List selection and calculation snapshot |
| `business_tax` | executable governed tax rule with explicit legal basis and calculation basis |
| `paperless_payment_order` | current immutable Payment Order line with office/application/LOB provenance |
| `surcharge` | separately commissioned policy and base |
| `penalty` | separately commissioned policy and base |
| `adjustment` | authorized signed correction with reason, actor, and target/base |
| `other_accepted` | explicit accepted component kind; never a catch-all that bypasses policy |

The composer validates eligibility, exact-once keys, deterministic ordering, explicit bases, currency, and reconciliation. It returns transient calculated components. Persisted `AssessmentLine` snapshots and the immutable Assessment remain the sole assessed result.

## Whitecube fitness

### What fits

`whitecube/php-prices` v3 uses Brick Money values, supports custom keyed modifier types and attributes, preserves chronological modification results, and can use `RationalMoney` to postpone rounding across division/multiplication. Those capabilities could help implement arithmetic and diagnostic history inside a transient composer.

### What does not fit automatically

- A Whitecube `Price` is mutable. It must be newly created inside one composition call, never shared, stored, or treated as an aggregate.
- Modifier order is insertion order split around a VAT phase. Municipal surcharge, penalty, tax, and adjustment ordering needs explicit BPLS phases and cannot inherit retail VAT semantics.
- A percentage modifier naturally acts on the running price. BPLS must name the exact base component set; the library does not decide whether a surcharge applies to tax, fees, prior penalties, or selected LOBs.
- Rational arithmetic can delay rounding but cannot choose a municipal rounding rule. Unsupported percentage/rate policy must continue to fail closed.
- Modification history is useful diagnostic evidence but does not replace FeeRule snapshots, Payment Order provenance, component identity, or persisted Assessment lines.
- Exact-once inclusion remains a BPLS/database invariant. The current unique `(assessment_id, paperless_payment_order_line_id)` constraint and source eligibility checks must remain.

Scenario 01 and Scenario 02 can remain cent-for-cent unchanged if fixed minor-unit components are composed in their current deterministic order and the persisted snapshots are compared before any cutover. The current PHP 1,220 specimen gains no arithmetic benefit from a library: six fixed Payment Order lines total PHP 870 and one fixed governed fee adds PHP 350. A package is justified only when an accepted percentage/formula component makes the native implementation materially clearer without weakening provenance.

### `php-prices` versus `laravel-prices`

If later authorized, choose `whitecube/php-prices`, pinned to a stable release and wrapped behind BPLS interfaces. Do not choose `whitecube/laravel-prices`: its purpose is polymorphic, time-effective product/service price history, which duplicates the governed FeeRule/Price List and immutable Assessment model. Its Laravel 13 compatibility does not make its persistence semantics appropriate.

The latest stable `php-prices` line observed during this audit is v3.3.0; v4 remains beta. The current application has no Whitecube or Brick Money dependency installed. `spatie/laravel-model-states` v2.13+ supports Laravel 13 and PHP 8.4.

Official sources inspected:

- <https://spatie.be/docs/laravel-model-states/v2/01-introduction>
- <https://spatie.be/docs/laravel-model-states/v2/working-with-transitions/05-transition-events>
- <https://github.com/spatie/laravel-model-states>
- <https://github.com/whitecube/php-prices>
- <https://packagist.org/packages/whitecube/php-prices>
- <https://github.com/whitecube/laravel-prices>
- <https://packagist.org/packages/whitecube/laravel-prices>

## Later adoption sequence, if authorized

1. Accept the PermitApplication macro graph, especially cancellation, release, and historical-evidence separation.
2. Add characterization tests proving every current transition, refusal, idempotency rule, and readiness projection.
3. If still valuable, pilot Model States on PermitApplication only; route all status changes through existing domain Actions and prove identical stored values and events.
4. Accept component ordering, percentage bases, rounding, surcharge, penalty, and adjustment policies.
5. Introduce the BPLS `AssessmentComponent` and `AssessmentComposition` contracts without Whitecube.
6. Run a throwaway adapter spike comparing native integer/Brick calculations with stable `php-prices` across Scenario 01, Scenario 02, boundaries, negative adjustments, and percentage/rounding fixtures.
7. Adopt only if the adapter reduces complexity and all persisted Assessment lines, totals, fingerprints, and slips remain identical. Keep the adapter replaceable and never persist `Price` objects.

## Risks and next recommendation

Primary risks are state proliferation, accidentally storing readiness as state, retail VAT ordering leaking into municipal policy, hidden percentage bases, premature rounding, mutable Price reuse, duplicated price persistence, and substituting library history for legal/audit provenance.

Next bounded move: repair the lifecycle test fixture contract for configured versus unconfigured XChange environments, then obtain Board acceptance of the PermitApplication macro graph and the Assessment component/ordering vocabulary. Do not install either candidate package or implement surcharge, penalty, quarterly allocation, Treasury-added LOB, registry, Cloud, or UAT behavior yet.
