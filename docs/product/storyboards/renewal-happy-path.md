# Scenario 01 storyboard — Renewal happy path

Business question: **Can a normal Renewal become an approved payable from first principles?**

Engine contract: `renewal-happy-path` is certified only through the Payable boundary. All identities are synthetic. The six scenario-specific departmental amounts are `provisional_uat`. The ₱350 Business Inspection Fee is an accepted governed municipal rule and is **not** `provisional_uat`.

Certified local product state: Application `21`; Evaluation version `13`; Assessment `17`, sequence `1`; Payment Schedule `8`; Grand Total `₱1,220.00`.

## Engine-to-screen map

| # | Actor | Certified lifecycle state | Actual local screen evidence | Product meaning |
|---:|---|---|---|---|
| 1 | BPLO / Citizen | Owner/customer onboarded through canonical application intake | [Application, owner, business, and LOB record](assets/renewal-happy-path/01-first-principles-onboarding-application-lobs-current.png) | The synthetic owner exists as the legal/customer context for the transaction. |
| 2 | BPLO / Citizen | Business onboarded and bound to that owner | [Application, owner, business, and LOB record](assets/renewal-happy-path/01-first-principles-onboarding-application-lobs-current.png) | The business registry record is distinct from the application. |
| 3 | BPLO / Citizen | Renewal lodged for application year 2098 | [Application, owner, business, and LOB record](assets/renewal-happy-path/01-first-principles-onboarding-application-lobs-current.png) | The transaction is a Renewal and begins without a pre-seeded financial answer. |
| 4 | BPLO / Citizen | Retail Trading and Food Service declared | [Application, owner, business, and LOB record](assets/renewal-happy-path/01-first-principles-onboarding-application-lobs-current.png) | LOBs are declared business activities and grouping contexts, not charge rows. |
| 5 | BPLO / Assessment Officer | Required-work routing projected from Application + LOBs + provisional Scenario 01 applicability | [Evaluation routing and responsibility evidence](assets/renewal-happy-path/05-first-principles-routing-responsibilities-current.png) | The existing Business Permit Evaluation answers what must happen before assessment. Routing is a read/compiled projection, not a new persisted aggregate. |
| 6 | Concerned offices | Six canonical responsibilities created; routing keys equal responsibility keys exactly | [Evaluation routing and responsibility evidence](assets/renewal-happy-path/05-first-principles-routing-responsibilities-current.png) | Every required department is explained by LOB and reason; there is no missing or unexplained work. |
| 7 | Concerned office | Unresolved work projects into the office queue | [Responsibility-derived Health queue](assets/renewal-happy-path/audit-final-health-work-surface.png) | The queue is a projection of canonical unresolved responsibilities; the completed specimen is correctly absent. |
| 8 | Health / Assessor / Engineering / MENRO | Office evaluation resolves applicability, review stage, charge, provenance, and reason | [Completed responsibility detail](assets/renewal-happy-path/audit-final-evaluation-responsibilities.png) | Each office completes only its owned work. |
| 9 | Assessment Officer | Six of six departments complete; Evaluation version 13 is ready **before** Treasury | [Evaluation and 6/6 completion](assets/renewal-happy-path/05-first-principles-routing-responsibilities-current.png) | Readiness now means municipal responsibility completion; Treasury is not an initial readiness prerequisite. |
| 10 | Assessment Officer | Exact financial working paper: ₱330 + ₱540 + ₱350 = ₱1,220 | [Certified working paper](assets/renewal-happy-path/05-first-principles-routing-responsibilities-current.png) | LOB charges, subtotals, application-wide governed fee, and Grand Total are one canonical Evaluation roll-up. |
| 11 | Assessment Officer | Immutable Assessment #1 prepared from Evaluation version 13 | [Assessment, Treasury, and Treasurer evidence](assets/renewal-happy-path/11-first-principles-assessment-treasury-treasurer-current.png) | The prepared Assessment consumes the exact Evaluation version and fingerprint; it does not recalculate liability. |
| 12 | Treasury | Treasury counter-check binds Assessment `17` and its exact source Evaluation; result `no_correction` | [Assessment, Treasury, and Treasurer evidence](assets/renewal-happy-path/11-first-principles-assessment-treasury-treasurer-current.png) | Treasury reconciles the prepared Assessment after preparation and cannot approve or rewrite its amount. |
| 13 | Municipal Treasurer | Exact Assessment #1 approved for ₱1,220.00 | [Assessment, Treasury, and Treasurer evidence](assets/renewal-happy-path/11-first-principles-assessment-treasury-treasurer-current.png) | Municipal Treasurer approval is distinct from Treasury investigation and binds the immutable Assessment snapshot. |
| 14 | Assessment Officer / Treasury | Payment Schedule #1 is pending; balance ₱1,220.00; paid ₱0.00 | [Certified Payable](assets/renewal-happy-path/14-first-principles-payable-current.png) | Approved liability is payable but not paid; ordinary Evaluation financial mutation remains locked. |

## Product disposition

**ENGINE PASS / PRODUCT PASS.** The actual local screens expose the canonical start, LOB declaration, Evaluation/routing responsibilities, financial working paper, immutable Assessment, post-Assessment Treasury no-correction record, exact Municipal Treasurer decision, and Payable in the corrected order.

QR issuance, collection, settlement, receipt, permit release, and Scenario 02 behavior remain outside this certification.
