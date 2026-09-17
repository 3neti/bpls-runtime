# Gate 9 — Cashier simulation action availability

Scope: workflow UAT only; preserved Application 291, Payment 5, Schedule 63,
Assessment 215. No financial policy change, historical/report work, or data repair.
Starting code: `909c530597e8e984475b397a91929e139940408e`.

## Proven suppressing predicate

Read-only command `comm-a2be98ff-be50-4090-b987-52515254fc67`, captured at
2026-09-14T14:09:07Z, and the Testing Adjudicator's already-rendered browser
evidence identify the actual actor as municipal Cashier user 46, role `cashier`,
active institutional position 5 with capability `cashier`. The previous audit
incorrectly tested preview Cashier user 7 instead.

| Gate at capture | Actual municipal Cashier |
| --- | --- |
| Staff/payment-schedule access | true |
| `collections.record` | true |
| Active municipal Cashier position | true |
| Workflow target / staging | true |
| Explicit preview mode; production integrations disabled | true |
| Preserved commissioned Application/Schedule/Assessment/Payment binding | true |
| Payment payable, amount 417500 PHP centavos | true |
| Unique active attempt 9 | true |
| Collections / ORs absent | true |
| Laboratory run | absent; not required for ordinary workflow |
| **`personaFor(user46) === preview Cashier`** | **false** |
| Rendered `can.simulate_classic_payment` | false |
| Rendered simulation URL | null |
| Vue action | correctly hidden by false server props |

The attempt resolver is accepted and unchanged. The second coupling was to a
preview account identity, not to payment state. Municipal authority must not be
substituted with preview-user identity or an Admin shortcut.

## Corrected contract

`AuthorizeUatQrPhSimulation::handle` authorizes the environment, actor and exact
commissioned specimen. Ordinary Cashier authority is the existing active,
non-ended institutional position whose capability role is `cashier`, plus
effective `collections.record` permission. It creates no roles, assignments or
permissions. The existing Classic ceremony branch retains its manifest-bound
actor restriction, now also behind the environment safety boundary.

The environment must explicitly enable preview mode, disable production
integrations and production migration, and be `staging`, `local` or `testing`.
Cloud execution is limited to the exact existing workflow-UAT URL. Configured
local/testing execution permits localhost/127.0.0.1/bpls-runtime.test. Production,
historical-UAT and unapproved targets fail closed on the server.

`available` adds exact current Treasurer-approved schedule eligibility, payable
Payment state, matching Assessment/amount/currency, Pay Code, no Collection and
the existing canonical resolver's unique active result. The page uses this
decision directly. No independent frontend authorization rule is added.

Execution acquires the existing schedule cache lock and database locks, reloads
the actor, rechecks scope/authority and availability, and validates the submitted
attempt against the canonical active attempt. Expiry after rendering fails
without Collection. An exact retry returns the existing Collection; a different
attempt cannot claim that result. Provider, rail, Payment, attempt and reference
are preserved in Collection provenance. No external funds move.

## Preservation and verification boundaries

Diagnosis preserved Application 291, Payment 5, attempts 7–9, Schedule 63 and its
lines, immutable Assessment 215, Applications 285–290 and Assessment 214. At
capture Collections and ORs were zero and the obligation remained ₱4,175.00.
Attempt 9 expires at 2026-09-14T14:12:01Z; its historical active observation must
not be presented as perpetual validity.

Synthetic tests exercise real municipal position authority rather than mocking
the preview persona. Coverage includes excluded actors, environment denial,
revoked positions, no/expired/ambiguous requests, non-payable Payment, expiry
between presentation and submission, exact Collection amount and provenance,
HTTP retry idempotence and settled-action hiding. Existing resolver and upstream
lifecycle regressions remain mandatory.

Deployment and live acceptance are separate from local test success. The same
Testing Adjudicator must validate actual user 46 on desktop and 390×844. If the
request expired naturally, only one normal Citizen replacement under Payment 5
is commissioned. Stop on the first genuine defect; do not regenerate blindly,
alter expiry, substitute preview user 7, or replay upstream lifecycle stages.
