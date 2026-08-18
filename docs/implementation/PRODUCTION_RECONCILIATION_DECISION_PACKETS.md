# Production Reconciliation Decision Packets

Status: **Municipal decisions pending**

Snapshot fingerprint: `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57`

Scope: read-only reconciliation evidence; no migration or cutover authority

These packets reduce recurring production exceptions to bounded questions for authorized municipal reviewers. Counts are aggregate and payload-free. `Ready` means deterministic under the current migration contract, not authorized for migration.

## Decision Order

| Priority | Packet | Why first |
| --- | --- | --- |
| 1 | Clearance-type replacement | Five historical identifiers explain 110 failed references and have strong denormalized evidence. |
| 2 | Missing-parent historical records | Establishes the disposition rule needed by several record classes. |
| 3 | Owner identity collision groups | Ownership controls the largest cascade of blocked businesses. |
| 4 | Business collision groups | Registry duplicates affect application ownership and future operations. |
| 5 | Group-owner semantics | Affects 436 owner records and cannot be inferred from person-identity rules. |
| 6 | Soft-deleted and blacklisted registry records | Requires policy on operational availability versus historical preservation. |
| 7 | Receipt-number and failed-payment semantics | Arithmetic is coherent, but receipt authority and failed-payment meaning remain unresolved. |
| 8 | Reference-data crosswalks | Every planned business requires accepted current reference identities. |
| 9 | Fee-override identity | One override has no surviving fee parent; eight others remain structurally linked. |
| 10 | Unreferenced stored objects and media retention | Retention and ownership authority are required before object migration or exclusion. |

## Current Migration Acceptance Review Bundle

The current municipal identity questions should be reviewed together rather than issued as one-off engineering questions. Counts below are bounded decision views: the 450 and 69 partition the unchanged 519 collision-free-business-source evidence class; the 120 and 12 belong to other disjoint human-frontier cohorts.

Two municipal-facing packets now carry this bundle. `MUNICIPAL_IDENTITY_AND_REGISTRY_REVIEW_PACKET.md` remains unchanged and canonical for the 450 shared-contact and 120 `Group`-owner questions. `MUNICIPAL_PRIORITY_IDENTITY_AND_REGISTRY_COLLISION_REVIEW_PACKET.md` is the second packet for the priority non-contact and registration queues plus authority-routed exceptions; it records no disposition or acceptance.

| Review item | Quantified scope | Exact decision boundary | Effect of an accepted decision |
| --- | ---: | --- | --- |
| Shared contact points only | 450 applications / 443 owner proposals / 447 business proposals; 254 hashed email/phone collision groups | Whether shared email/phone alone creates a legal-owner identity conflict; what authoritative evidence validates distinct owners; approving office; quarantine rule | Could advance the class to bounded reference-data and exact owner/business/application mapping review; does not accept mappings or make the class rehearsal-ready |
| Non-contact identity signal present | 69 applications / 68 owner proposals / 68 business proposals; 51 exact hashed `name_birth` review groups: 14 closed / 37 externally coupled | Required authoritative legal-owner evidence and complete-group disposition; coupled groups must include 52 owner proposals outside the cohort | A complete group disposition could unlock exact owner-proposal preparation for its cohort members; it does not accept mappings or make the class rehearsal-ready |
| Compound registration collision dimension | 75 applications / 72 owner proposals / 75 business proposals; 52 registration review groups: 18 closed / 34 externally coupled | Required evidence and complete-group disposition for shared registration numbers; coupled groups must include 37 business proposals outside the cohort; separate owner-evidence routing remains | A complete group disposition could unlock exact business-proposal preparation for its cohort members; it does not resolve owner identity, reference data, mappings, or rehearsal readiness |
| Legacy `ownerType = Group` | 120 applications / 110 owner proposals / 119 business proposals | Meaning of `Group`, applicable legal-owner categories, evidence, authority, and insufficient/contradictory-evidence disposition | Policy acceptance only; exact owner proposals and every dependent mapping remain separate decisions |
| Collision-free owner proposals | 12 applications / nine owner proposals | Accept or reject the nine exact owner proposals independently of unresolved business collisions | Makes only an owner-level registry rehearsal eligible for separate authorization; historical preservation remains blocked |

The 120-case Organizational-Owner Registry-Policy Decision Packet remains the controlling detailed packet for `Group`. It is preserved unchanged and must not be heuristically subdivided without new exact source evidence.

Municipal identity evidence-class set SHA-256: `5aed72372bb3cf5260946196f23ab6f5e126eff6e1918b8947fcdfa9b14699c5`. Contact-signal-only class SHA-256: `2833fc6ff6fd3d9cc581f1755bb1982aea7becc29d36b64a3d78380276e0d93b`. Non-contact-identity-signal class SHA-256: `3c6e7efda2352b40d6d6a25522d3c7ebd1b1a1cffceb79664a0bbb5e329b055d`.

## Packet 1: Clearance-Type Replacement

**Issue:** 110 historical clearance rows reference five clearance-type identifiers absent from the current source table.

**Affected population:** 110 clearance records; five absent historical configuration identities.

**Observed evidence:** Each affected row retains denormalized clearance evidence matching exactly one surviving clearance type. This is strong reconciliation evidence, but it is not municipal approval.

**Prepared proposal:** Read-only run `prod-clearance-proposal-20260817-001` grouped the 110 rows into five source identities and produced five unique exact three-field candidates. It recorded zero accepted mappings and created no reconciliation or domain rows. Private review evidence remains under the checksum-bound production intake.

**Software can determine:** The five repeated exception classes, their affected counts, and the unique current candidate supported by source evidence.

**Software cannot determine:** Whether the historical identifiers were renamed, replaced, deleted in error, or intentionally retired; whether the Municipality accepts the proposed crosswalk.

**Options:**

1. Accept a versioned historical-to-current crosswalk. The 110 records become structurally reconcilable while preserving original identifiers and decision provenance.
2. Quarantine the 110 records as historical evidence. They remain auditable but do not become operational clearance records.
3. Authorize explicit exclusion. The exclusion decision and affected scope remain in migration evidence.

**Decision required:** Approve or reject each of the five proposed crosswalk entries.

**Authority / approver:** BPLO and the municipal official responsible for clearance configuration and historical records.

**Current state:** `PENDING MUNICIPAL DECISION`. Engineering evidence is complete for presentation; private artifact `municipal-acceptance.md` presents separate accept, reject, or quarantine choices for all five candidates while recording no decision. Migration treatment remains unchanged until each proposed crosswalk is accepted or rejected by an authorized official.

## Packet 2: Missing-Parent Historical Records

**Issue:** Historical schedules, payments, UOM rows, and permits reference parents that are absent from the production snapshot.

**Affected population:** 69 schedules reference 20 absent applications; three payments reference three absent applications; 56 payments reference 56 absent schedules; ten UOM rows reference seven absent applications; ten permit rows share absent owner and business parents.

**Observed evidence:** The 258 originally declared failed edges reduce to 101 absent target identifiers. The added source-backed UOM reference contributes ten more failed edges, all tied to seven applications already absent from schedule evidence.

**Software can determine:** Which parent is absent, which exception patterns repeat, whether related surviving records agree structurally, and whether a relationship can be restored from exact authoritative evidence.

**Software cannot determine:** Why a parent is absent or whether any surviving neighbor is the legally intended replacement.

**Options:**

1. Reconcile only from authoritative records identifying the exact parent.
2. Preserve affected rows as quarantined historical evidence without fabricating operational parents.
3. Explicitly exclude a bounded class with municipal authority and retained provenance.

**Decision required:** Adopt a disposition for each recurring missing-parent class and identify acceptable authoritative evidence for reconciliation.

**Authority / approver:** BPLO records custodian; Treasury additionally approves payment and schedule dispositions; permit authority approves permit dispositions.

## Packet 3: Owner Identity Collision Groups

**Issue:** Repeated owner identity signals may indicate duplicates, shared contact details, representatives, household practices, or placeholders.

**Affected population:** 1,953 owner records carry collision signals. Aggregate clusters include 287 email groups, 308 mobile groups, and 178 name-plus-birth-date groups. Signals overlap and are not additive.

**Observed evidence:** Similarity exists, including large shared-contact clusters. Similarity does not establish legal identity.

**Software can determine:** Collision classes, exact shared signals, affected counts, and downstream businesses blocked by unresolved ownership.

**Software cannot determine:** Which records represent the same legal owner or whether shared contact data reflects agency or historical operating practice.

**Options:**

1. Accept reviewed exact mappings for specific collision classes.
2. Preserve distinct owners and flag them for registry maintenance after migration.
3. Quarantine owners whose identity cannot be represented safely.

**Decision required:** Define acceptable evidence and reviewing authority for owner identity reconciliation. No automatic merge is proposed.

**Authority / approver:** BPLO registry custodian and municipal data-privacy/records authority.

### Historical-Preservation Frontier Decision

Read-only v4 run `prod-human-identity-frontier-20260818-004` narrows the collision question for the 519 collision-free-business-source applications without changing the frozen frontier or seven decision cohorts.

The first class contains 450 applications, 443 owner proposals, and 447 business proposals. Every class member has source-owner collision signals limited to normalized email and/or phone; no non-contact collision signal is present on that member. The class includes 442 historical `Released` applications and eight non-`Released` applications. Its 254 hashed contact groups comprise 125 email and 129 phone groups. Group counts may overlap the second class and are not additive.

The second class contains 69 applications, 68 owner proposals, and 68 business proposals. Every member carries at least one non-contact identity signal; the current production evidence observes `name_birth`. It includes 68 historical `Released` applications and one non-`Released` application. These signals remain collision evidence only.

**Consolidated decision required for the 450:**

1. Does shared email or phone, without a non-contact identity collision signal, require a legal-owner conflict under actual Ipil registry practice?
2. What authoritative records are sufficient to establish that two source owner records sharing a contact point are distinct legal owners?
3. Who may approve that determination and the later exact mapping: BPLO registry custodian, municipal records/privacy authority, legal officer, or a defined combination?
4. Must the rule be applied by collision group, by source owner record, or to a precisely defined evidence class?
5. When evidence is missing or contradictory, must the affected source owner and dependent applications remain quarantined?
6. After that policy is adopted, may engineering prepare exact owner-mapping proposals for compliant members, with business/application mappings and reference data still separately unaccepted?

An accepted decision would not make email/phone identity authority, merge or split owners, accept any mapping, activate historical `Released`, infer fee identity, recalculate history, authorize rehearsal, mutate production, or authorize migration/cutover. Even compliant members would still require reference-data reconciliation, exact owner/business/application mapping acceptance, cohort freeze, and separate rehearsal authorization.

For the 69, a contact-point rule is insufficient. Authorized reviewers must reconcile the person-oriented or other non-contact evidence against authoritative legal-owner records before an exact disposition can be proposed.

The v5 replay makes that workload exact without deciding identity: the same 69 applications reduce to 51 hashed `name_birth` collision review groups. Group sizes are 41 groups of two, five of three, two of four, and three of five. Review may proceed by these checksum-bound units, but neither shared nor distinct legal identity may be inferred from the signal.

The additive v6 replay separates decision scope without changing that workload. Fourteen groups are closed within the named cohort and cover 28 owner proposals / applications, all carrying historical `Released`. The other 37 groups cover 40 cohort owner proposals / 41 applications but are externally coupled to 52 additional source owner proposals; the single non-`Released` member is in this route. Review of a coupled group must include its full global membership. A complete authoritative group disposition can unlock exact owner-proposal preparation for affected cohort members, but it does not accept those proposals, resolve reference data, activate `Released`, freeze a cohort, or authorize rehearsal.

## Packet 4: Business Collision Groups

**Issue:** Registration-number and owner-plus-name signals identify possible duplicate business records without proving equivalence.

**Affected population:** 473 business records carry collision signals; 430 are also blocked by owner dependencies and 43 require direct review.

**Observed evidence:** There are 200 registration-number groups and 12 owner-plus-name groups. Some shared registration signals span more than two records.

**Software can determine:** Exact collision groups, related owner mappings, and whether each source record can be projected independently.

**Software cannot determine:** Whether records are duplicates, branches, renewals, corrections, or historical versions of one registry identity.

**Options:**

1. Accept deterministic record-to-registry mappings after municipal review.
2. Preserve separate businesses with collision flags.
3. Quarantine unresolved groups pending registry maintenance.

**Decision required:** Establish the evidence required to merge, preserve, or quarantine each collision class.

**Authority / approver:** BPLO registry custodian.

### Historical-Preservation Compound-Collision Frontier

Additive v5 run `prod-human-identity-frontier-20260818-006` preserves the frozen 76-application compound cohort while isolating its dominant review dimension:

- 75 applications / 72 owner proposals / 75 business proposals carry a registration-number collision;
- those records reduce to 52 globally deduplicated registration review groups: 45 groups of two, six of three, and one of four;
- 34 applications have contact-only owner evidence plus the registration collision;
- 41 have non-contact owner evidence plus the registration collision;
- one non-`Released` application remains a separate phone-plus-owner/name topology.

The 34/41 routing counts are disjoint by application, but their registration-group counts overlap. The 52-group aggregate is the authoritative review workload.

The v6 containment replay further separates 18 closed registration groups covering 39 businesses/applications from 34 externally coupled groups covering 36 cohort businesses/applications plus 37 source business proposals outside the cohort. Closed members route into 17 contact-owner and 22 non-contact-owner applications; coupled members route into 17 and 19. All 75 carry historical `Released`. A coupled registration decision must cover the full global group. A complete group disposition can unlock exact business-proposal preparation for that identity dimension only; owner identity and every downstream acceptance or authorization gate remain separate.

The additive v7 routing replay makes the reviewer handoff exact. Contact-only owner evidence contains seven closed registration groups / 14 applications and 16 coupled groups / 17 applications plus 18 outside business proposals. Non-contact owner evidence contains eight closed groups / 17 applications and 18 coupled groups / 19 applications plus 19 outside business proposals. Three closed registration groups / eight applications span both owner-evidence lanes; no coupled group is mixed. The 52 groups therefore reduce to 49 single-lane packets and three closed coordinated packets. Registration decision-route SHA-256 is `f64c014c67354ed0700e54ad06d069dd6fbb5ba2d8a311a059f8322932359e57`; every v6 fingerprint remains exact.

| Registration route | Exact population | Route SHA-256 | Decision effect |
| --- | --- | --- | --- |
| Contact-only, closed | 7 groups / 14 applications | `0462da1689b4e317747ed7ee29ec3f06aa90dfdcbf60e2f8a59975e00ba75090` | Complete registry disposition can unlock business-proposal preparation for this dimension; shared-contact owner review remains independent |
| Contact-only, coupled | 16 groups / 17 applications + 18 outside business proposals | `27c596d7813f0d2e7538f26d6bf1617aa1db426b4791c9b5ca3761a70daad272` | Full global registration-group review is required; shared-contact owner review remains independent |
| Non-contact-only, closed | 8 groups / 17 applications | `abc920be1e7125dd7d8c03149eda2713510f25858f190ea25fd7733a8a87755e` | Complete registry disposition can unlock business-proposal preparation for this dimension; person-oriented owner review remains independent |
| Non-contact-only, coupled | 18 groups / 19 applications + 19 outside business proposals | `7ee607938366ff39940cf44b1980ec3603e7dc2cc4aec8b4ee6a6af36654656a` | Full global registration-group review is required; person-oriented owner review remains independent |
| Mixed owner evidence, closed | 3 groups / 8 applications | `e303b2c1d9d4c5a1f65115ceeaff71cdae485ff43725d72c661278d4bba6c722` | Complete registry disposition can unlock business-proposal preparation for this dimension, but owner review must be coordinated across both lanes |

Acceptance of any route means only that the authorized registry reviewer has recorded an evidence-backed disposition for every member of each complete registration group. It does not infer business or owner identity from any signal, resolve reference data, accept mappings, activate historical `Released`, freeze a cohort, authorize rehearsal, or authorize production migration. No reversible rehearsal becomes available from registration acceptance alone; every affected record remains quarantined behind its owner, reference-data, exact-mapping, freeze, and separate authorization gates.

**Consolidated registration decision required:**

1. Under actual Municipality of Ipil registry practice, what did reuse of a business registration number mean across historical business records?
2. What authoritative evidence distinguishes duplicate, renewal/version, branch, correction, or genuinely separate business identities?
3. Who may approve the disposition for each collision group, and must review occur by group or by source record?
4. When evidence is insufficient or contradictory, must the business, owner dependency, and affected application remain quarantined?
5. After a disposition is recorded, may engineering prepare exact business-mapping proposals while owner identity, reference data, application mappings, acceptance, freeze, and rehearsal authorization remain separate?
6. For the three closed mixed-owner-evidence groups, who coordinates the business-registry disposition with both the shared-contact and person-oriented legal-owner reviews?

An accepted registration disposition would not make registration number an identity authority, merge or split businesses automatically, resolve legal-owner identity, activate historical `Released`, accept mappings, authorize rehearsal, or authorize production migration. The shared-contact decision can address only the owner-evidence dimension of the 34 contact-owner applications; the 41 non-contact-owner applications still require authoritative legal-owner reconciliation.

## Packet 5: Group-Owner Semantics

**Issue:** 436 owner records represent group or organization semantics not safely resolved by individual-person matching.

**Affected population:** 436 owner records and their dependent businesses.

**Observed evidence:** Production stores these records in the owner population, but person-oriented identity signals are insufficient to classify legal representation.

**Software can determine:** Which records use group-owner indicators and which businesses depend on them.

**Software cannot determine:** Whether each record is a corporation, partnership, association, representative account, or legacy data convention.

**Options:**

1. Map to an accepted legal-owner identity with organization semantics.
2. Preserve as a distinct historical owner pending registry maintenance.
3. Quarantine when legal identity cannot be established.

**Decision required:** Confirm how group-owner records map to the Municipality's accepted legal-owner categories.

**Authority / approver:** BPLO registry authority with municipal legal guidance where required.

The detailed controlling packet for the 120-application historical-preservation subset is `docs/implementation/ORGANIZATIONAL_OWNER_REGISTRY_POLICY_DECISION_PACKET.md`. Its cohort remains quarantined at SHA-256 `517318c19a152a483a7285467aeaaf8f32a0e372571ca0a1f703ec08f5581f74`; nothing in the contact-point review changes or subdivides that cohort.

## Packet 6: Soft-Deleted And Blacklisted Registry Records

**Issue:** Historical deletion and blacklist flags may affect operational eligibility but do not erase historical facts.

**Affected population:** 11 soft-deleted and five blacklisted owner records; four soft-deleted and four blacklisted business records. Categories may overlap.

**Observed evidence:** The flags exist in production and affected records may retain dependent history.

**Software can determine:** Flag state, dependencies, and whether historical evidence references each record.

**Software cannot determine:** Whether a flag means invalid identity, operational restriction, duplicate suppression, temporary suspension, or administrative cleanup.

**Options:**

1. Preserve the registry identity but prohibit ordinary operational reuse.
2. Quarantine the identity and dependent history.
3. Reconcile the flag under an accepted municipal status crosswalk.

**Decision required:** Define migration and operational meaning for each flag.

**Authority / approver:** BPLO administrator and the authority responsible for blacklist policy.

Within the 736-case historical-preservation frontier, the eight soft-deleted applications remain one checksum-bound quarantine. V6 proves the previously reported overlays are disjoint decision routes: three have deletion/identity/reference blockers without Treasury, fiscal, permit-authority, or contradiction overlays; two require Treasury interpretation; one requires fiscal authority; one requires permit-authority semantics; and one requires a genuine source-contradiction disposition. A deletion/retention rule resolves only the registry-policy dimension. Every member still requires identity, reference-data, exact-mapping, freeze, and separate rehearsal gates as applicable, so no member is one decision from exact proposal preparation or rehearsal-ready.

## Packet 7: Receipt-Number And Failed-Payment Semantics

**Issue:** Persisted financial arithmetic is coherent, while receipt claims and failed-payment history require policy interpretation.

**Affected population:** 129 failed payments retain a receipt field; 194 duplicate receipt-field groups cover 738 records; 56 payments reference absent schedules. Eight duplicate groups span applications and 13 contain mixed payment statuses.

**Observed evidence:** Schedule components reconcile, paid totals do not exceed schedule totals, completed-payment totals agree with schedules, resolved payment/schedule pairs agree on application identity, and transaction numbers are unique.

**Software can determine:** Persisted arithmetic consistency, duplicate receipt signals, payment states, and missing structural parents.

**Software cannot determine:** Whether the receipt field is an official receipt number, failed-attempt reference, reused provisional value, or other Treasury identifier; whether a historical payment should count as legally collected.

**Options:**

1. Accept a Treasury-defined status and receipt interpretation crosswalk.
2. Preserve uncertain payments as quarantined financial evidence.
3. Explicitly exclude a bounded class only with fiscal authority and retained provenance.

**Decision required:** Define receipt-number authority, failed-payment meaning, and treatment of missing-schedule payments.

**Authority / approver:** Municipal Treasurer or formally delegated Treasury authority.

## Packet 8: Reference-Data Crosswalks

**Issue:** Legacy businesses use historical reference identities that must map to accepted current classifications before operational migration.

**Affected population:** All 3,192 planned business records require at least one reference-data crosswalk.

**Observed evidence:** Names and denormalized labels can produce candidates, but naming similarity is not authority.

**Software can determine:** Exact source identities, candidate current values, usage counts, and records affected by each candidate mapping.

**Software cannot determine:** Whether historical and current labels have equivalent legal or operational meaning.

**Options:**

1. Approve versioned source-to-current crosswalks by reference class.
2. Preserve legacy declarations while deferring operational activation.
3. Quarantine records requiring classifications that have no accepted equivalent.

**Decision required:** Approve crosswalks for the recurring reference classes used by business records.

**Authority / approver:** BPLO configuration owner and relevant municipal policy authority.

### Five-Record Historical-Finance Cohort Proposal

Read-only run `prod-historical-financial-cohort-prerequisites-20260817-002` narrows this packet for the frozen first-rehearsal cohort without accepting a crosswalk.

**Location evidence:** All 15 source references resolve by exact identifier, and all five province-city-barangay hierarchies are internally consistent. Because the Laravel runtime has no normalized location catalog, engineering proposes preserving the exact source lookup chain and hashes as registry provenance. This disposition requires explicit acceptance; engineering did not invent target location identities.

**Line-of-business evidence:** Every declaration uniquely matches one legacy `groups` record, and every matched group has a complete source-backed division and major hierarchy. No exact legacy-bound Laravel target currently exists for these five groups. Engineering therefore proposes creating or explicitly selecting five Laravel line-of-business targets from the exact source records after acceptance, followed by five authority-bearing reconciliation decisions. Names remain evidence only and were not used to create identity.

**Current state:** Five proposals are `evidence_complete_acceptance_pending`; zero reconciliations and zero mappings are accepted. Cohort SHA-256 remains `bf5af2693e471f336c54bf5e3345cc6b9df8709fceddd5ea1bc63360c3ebddb4`. Proposal SHA-256 is `06907e02c12209115fdd451cceac08b2fa20baba174dbdc1198dc5803b0faa36`. Rehearsal and production migration remain unauthorized.

## Packet 9: Fee-Override Identity

**Issue:** One of nine fee overrides references a fee absent from the production snapshot.

**Affected population:** One unresolved override; eight structurally linked overrides remain subject to financial-policy reconciliation.

**Observed evidence:** The Convex schema requires each override to reference a fee and division-group. All nine division-group references resolve; one fee reference does not.

**Software can determine:** Override type, surviving structural relationships, and the exact missing-parent class without executing any amount.

**Software cannot determine:** Whether the fee was deleted, replaced, or retired; whether the override remains legally operative.

**Options:**

1. Reconcile to an exact fee through accepted fee-schedule evidence.
2. Quarantine the override as historical financial evidence.
3. Explicitly exclude it with Treasury/revenue authority.

**Decision required:** Determine the disposition of the orphan override and confirm whether surviving overrides remain operational policy.

**Authority / approver:** Municipal Treasurer and authorized revenue-policy owner.

## Packet 10: Unreferenced Stored Objects And Media Retention

**Issue:** The snapshot contains stored objects not linked through currently characterized typed storage fields, while some media fields contain mixed URL or string representations.

**Affected population:** 34 stored objects total; 12 resolve through characterized typed fields and 22 do not. Owner-avatar and user-profile fields use mixed representations outside the current typed storage-reference catalog.

**Observed evidence:** File-storage intake verified every exported object against metadata by size and SHA-256. Lack of a characterized typed reference does not prove that an object is unused.

**Software can determine:** Object integrity, aggregate typed-reference coverage, and which objects remain unassociated under the current catalog.

**Software cannot determine:** Legal retention period, business ownership, whether mixed URL/string media remains required, or whether an unreferenced object may be excluded.

**Options:**

1. Preserve all objects privately until retention and ownership are decided.
2. Extend the typed reference catalog only where source usage proves a relationship.
3. Explicitly exclude bounded objects only under an approved retention decision.

**Decision required:** Approve retention and disposition rules for unassociated objects and mixed media fields.

**Authority / approver:** Municipal records/data-privacy authority with BPLO system ownership.

## Decision Recording Contract

Every accepted disposition must record:

- packet and decision identifier;
- decision authority and role;
- decision date and effective scope;
- source snapshot fingerprint;
- accepted option or explicit crosswalk;
- affected aggregate scope;
- supporting evidence reference;
- whether the result authorizes reconciliation, quarantine, or exclusion.

No packet decision by itself authorizes production migration or cutover. Accepted decisions become inputs to a new immutable reconciliation plan and production-scale rehearsal.
