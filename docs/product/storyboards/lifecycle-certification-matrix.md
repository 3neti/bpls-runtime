# BPLS lifecycle certification matrix

Scenario is the unit of completion. Only Scenario 01 has been executed. No status below authorizes implementation or execution of a later scenario.

| Tier | Stable scenario id | Business question (one line) | Status |
|---|---|---|---|
| A | `renewal-happy-path` | Can a normal Renewal become an approved payable? | **ENGINE PASSED · PRODUCT PASSED** |
| A | `new-permit-happy-path` | Can a normal New application become an approved payable? | **NOT RUN** |
| A | `department-confirms-default` | Can an office confirm a legitimate default without changing it? | **NOT RUN** |
| A | `department-corrects-charge` | Can an office make an authorized correction with provenance? | **NOT RUN** |
| A | `department-not-applicable` | Can an office record a legitimate not-applicable result? | **NOT RUN** |
| A | `treasury-adds-line-of-business` | Can Treasury add a missing LOB through the canonical boundary? | **NOT RUN** |
| A | `treasury-corrects-line-of-business` | Can Treasury correct LOB facts without bypassing reassessment? | **NOT RUN** |
| A | `treasurer-returns-assessment` | Can the Municipal Treasurer return, rather than mutate, an Assessment? | **NOT RUN** |
| A | `stale-assessment-rejected` | Is a stale Assessment safely rejected? | **NOT RUN** |
| A | `concurrent-department-work` | Does independent office work reconcile deterministically? | **NOT RUN** |
| A | `evaluation-assessment-reconciliation` | Does every resolved applicable charge enter Assessment exactly once? | **NOT RUN** |
| A | `authority-separation` | Are Assessment Officer, Treasury, and Treasurer authorities distinct? | **NOT RUN** |
| A | `payment-boundary-lock` | Does the accepted payable boundary lock ordinary financial mutation? | **NOT RUN** |
| B | `full-payment-cash` | Can a safe cash collection produce a paid schedule and receipt? | **NOT RUN** |
| B | `qr-ph-payable-issued` | Can a QR request be issued without pretending settlement? | **NOT RUN** |
| B | `qr-ph-settlement-to-collection-receipt` | Can confirmed QR settlement reconcile to collection and receipt? | **BLOCKED — LIVE SETTLEMENT PROOF** |
| C | `quarterly-payment-schedule` | Can a governed quarterly schedule be produced correctly? | **BLOCKED — MUNICIPAL POLICY** |
| C | `receipt-cancellation` | Can an authorized receipt cancellation preserve fiscal history? | **NOT RUN** |
| D | `multi-line-of-business-stress` | Does a large multi-LOB application preserve grouping and totals? | **NOT RUN** |
| D | `fixture-preparation-idempotency` | Can deterministic preparation rerun without accumulating state? | **NOT RUN** |
| D | `historical-migrated-application` | Can historical migrated evidence remain distinct from current authority? | **NOT RUN** |
| D | `price-book-evaluator-separation` | Does scenario mechanics remain outside ordinary pricing semantics? | **NOT RUN** |
