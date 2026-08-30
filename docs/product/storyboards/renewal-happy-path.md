# Scenario 01 storyboard — Renewal happy path

Business question: **Can a normal Renewal become an approved payable from first principles?**

Engine contract: `renewal-happy-path` is certified only through the Payable boundary. All identities are synthetic. The six scenario-specific departmental amounts are `provisional_uat`. The ₱350 Business Inspection Fee is an accepted governed municipal rule and is **not** `provisional_uat`.

Certified local product state: Application `21`; Evaluation version `13`; Assessment `17`, sequence `1`; Payment Schedule `8`; Grand Total `₱1,220.00`.

## Canonical Citizen identity and ownership

- `users.business_owner_id` is the explicit portal-identity bridge. The deterministic Scenario 01 Citizen `User` is linked to the exact `BusinessOwner` created by intake; no email, mobile number, or contact-value matching is used.
- `businesses.business_owner_id` is the legal/customer ownership relationship. A `Business` remains a registry record distinct from both the portal `User` and a permit transaction.
- `permit_applications.business_id` places the Renewal under that owned Business. Citizen visibility therefore follows `User → BusinessOwner → Business → PermitApplication`.
- `permit_applications.submitted_by_id` records the actor who lodged the transaction. Scenario 01 remains legitimately staff-lodged by BPLO, so this actor is not rewritten to the Citizen and is not used as the ownership rule.

## Engine-to-screen map

| # | Actor | Certified lifecycle state | Actual local screen evidence | Product meaning |
|---:|---|---|---|---|
| 1 | Citizen | The authenticated portal User is linked to the exact BusinessOwner/customer created through canonical application intake | [Citizen My Permit Applications](assets/renewal-happy-path/product-01-citizen-my-permit-applications.png) | Portal visibility follows `User → BusinessOwner → Business → PermitApplication`; the staff submitter remains an audit actor, not the owner. |
| 2 | Citizen | Business onboarded and bound to that owner | [Citizen Scenario 01 detail](assets/renewal-happy-path/product-02-citizen-scenario-detail.png) | The correct synthetic Citizen sees the staff-lodged Renewal through the durable owner/customer association. |
| 3 | BPLO / Citizen | Renewal lodged for application year 2098 | [Lifecycle-first application summary](assets/renewal-happy-path/product-03-bplo-lifecycle-summary.png) | The transaction is a Renewal and begins without a pre-seeded financial answer. |
| 4 | BPLO / Citizen | Retail Trading and Food Service declared | [Lifecycle-first application summary](assets/renewal-happy-path/product-03-bplo-lifecycle-summary.png) | LOBs are declared business activities and grouping contexts, not charge rows. |
| 5 | BPLO / Assessment Officer | Required-work routing projected from Application + LOBs + provisional Scenario 01 applicability | [Evaluation routing and responsibility evidence](assets/renewal-happy-path/05-first-principles-routing-responsibilities-current.png) | The existing Business Permit Evaluation answers what must happen before assessment. Routing is a read/compiled projection, not a new persisted aggregate. |
| 6 | Concerned offices | Six canonical responsibilities created; routing keys equal responsibility keys exactly | [Evaluation routing and responsibility evidence](assets/renewal-happy-path/05-first-principles-routing-responsibilities-current.png) | Every required department is explained by LOB and reason; there is no missing or unexplained work. |
| 7 | Concerned office | Completed work remains understandable through the office lens | [Health office lens](assets/renewal-happy-path/product-06-concerned-office-health.png) | The final specimen is read-only after Assessment while preserving the office's responsibility context. |
| 8 | Health / Assessor / Engineering / MENRO | Office evaluation resolves applicability, review stage, charge, provenance, and reason | [Health office lens](assets/renewal-happy-path/product-06-concerned-office-health.png) | Each office completes only its owned work. |
| 9 | Assessment Officer | Six of six departments complete; Evaluation version 13 is ready **before** Treasury | [Evaluation and 6/6 completion](assets/renewal-happy-path/05-first-principles-routing-responsibilities-current.png) | Readiness now means municipal responsibility completion; Treasury is not an initial readiness prerequisite. |
| 10 | Assessment Officer | Exact financial working paper: ₱330 + ₱540 + ₱350 = ₱1,220 | [Assessment Officer working paper](assets/renewal-happy-path/product-05-assessment-officer-working-paper.png) | LOB charges, subtotals, application-wide governed fee, and Grand Total are one canonical Evaluation roll-up. |
| 11 | Assessment Officer | Immutable Assessment #1 prepared from Evaluation version 13 | [Assessment Officer working paper](assets/renewal-happy-path/product-05-assessment-officer-working-paper.png) | The prepared Assessment consumes the exact Evaluation version and fingerprint; it does not recalculate liability. |
| 12 | Treasury | Treasury counter-check binds Assessment `17` and its exact source Evaluation; result `no_correction` | [Treasury lens](assets/renewal-happy-path/product-07-treasury-lens.png) and [ordered application timeline](assets/renewal-happy-path/product-04-bplo-treasury-timeline.png) | Treasury reconciles the prepared Assessment after preparation and cannot approve or rewrite its amount. |
| 13 | Municipal Treasurer | Exact Assessment #1 approved for ₱1,220.00 | [Municipal Treasurer lens](assets/renewal-happy-path/product-08-municipal-treasurer-lens.png) | Municipal Treasurer approval is distinct from Treasury investigation and binds the immutable Assessment snapshot. |
| 14 | Assessment Officer / Treasury | Payment Schedule #1 is pending; balance ₱1,220.00; paid ₱0.00 | [Certified Payable](assets/renewal-happy-path/product-09-payable-payment-schedule.png) | Approved liability is payable but not paid; ordinary Evaluation financial mutation remains locked. |

## Product disposition

**ENGINE PASS / PRODUCT PASS.** The actual local screens prove canonical Citizen ownership and visibility, the lifecycle-first BPLO projection, the ordered post-Assessment Treasury event, role-consistent financial truth, the Payable, and 390-pixel mobile layouts without page-level horizontal overflow.

QR issuance, collection, settlement, receipt, permit release, and Scenario 02 behavior remain outside this certification.
