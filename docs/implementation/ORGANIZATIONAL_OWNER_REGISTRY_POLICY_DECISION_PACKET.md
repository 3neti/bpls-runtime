# Organizational-Owner Registry-Policy Decision Packet

Status: **Municipal decision required — no decision recorded**

Prepared by: Chief Architect and Integrator

Prepared: 2026-08-18

Scope: the checksum-bound 120-application `group_owner_registry_policy` cohort only

Purpose: prepare an authorized municipal identity and registry-policy decision; this is not an engineering implementation, mapping-acceptance, rehearsal-authorization, migration-execution, or cutover packet

## Decision Position

The source evidence marks the owners in this cohort with the legacy `Group` owner representation. The records, their exact source ownership edges, and the boundary that separates them from the other human-identity cases are deterministic. The legal meaning of `Group`, the accepted municipal owner category for each record, and the registry treatment that should follow are not deterministic.

The cohort therefore remains quarantined at the registry-policy boundary. No owner, business, application, reference-data, fee, permit, or authority mapping is accepted by this packet.

## Frozen Cohort And Provenance

| Binding | Value |
| --- | --- |
| Source snapshot SHA-256 | `56fad41abbdeae8da23e9935550c753c82fb465d46a56b412342f27806bd0b57` |
| Financial plan | `3` |
| Financial dependency snapshot SHA-256 | `60d8e45cccbddbe772dac9b708e8ebe8d3eed12346289cf43262cd951b53c7e1` |
| Registry plan | `3` |
| Registry snapshot SHA-256 | `b2b00a8e328a5176ca77bc7695605565e0eb378529353e9b7f116b7ee266e3dd` |
| Corrected V1 candidate-set SHA-256 | `307ecf33dafb9c53fd16064288b3057edd3c3131851a0982f54f8e1fe3750d06` |
| Human-identity frontier SHA-256 | `8b1b80d4b2f38eb186186930c567e1e9eb7b83c4b28490307117381056064bbc` |
| Decision-cohort set SHA-256 | `dcbfaadec88b19ed564951af29b24c194049a903036c9c98c3ef922dc0c05d41` |
| Group-owner cohort SHA-256 | `517318c19a152a483a7285467aeaaf8f32a0e372571ca0a1f703ec08f5581f74` |
| Applications | `120` |
| Unique owner proposals | `110` |
| Unique business proposals | `119` |
| Accepted mappings | `0` |
| Rehearsed mappings | `0` |
| Production-applied records | `0` |

The cohort was reproduced by read-only run `prod-human-identity-frontier-20260818-003` using characterization schema `bpls.historical-financial-human-identity-frontier.v3`. The private checksum-bound artifacts retain record membership, source hashes, evidence shapes, and collision evidence. Git retains only aggregate, payload-safe facts.

## What `Group-owner` Means In The Source Evidence

For this packet, `Group-owner` is a source-evidence label, not a legal conclusion:

- the legacy business-owner record has `ownerType` exactly equal to `Group`;
- the source may carry a `groupName`, which the current read-only projector can preserve and use as the proposed display name;
- source person-oriented fields may also be present, but they do not prove whether a named person is the legal owner, a representative, a contact, or a legacy data-entry artifact;
- each dependent business retains an exact source `ownerId` edge to its source business-owner record; and
- the current projector can preserve the legacy owner type and group name as metadata without deciding their legal meaning.

The source label alone does not establish corporation, partnership, association, cooperative, sole proprietorship, representative account, branch relationship, beneficial owner, authorized signatory, or any other legal category. No current evidence authorizes converting `Group` into one of those categories.

## Deterministic Facts Today

The following facts are established without a municipal policy choice:

1. Exact source business-owner, business, and application records remain fingerprinted and traceable to the immutable snapshot.
2. Exact source owner-to-business relationships are preserved by source identifiers rather than names, contacts, amounts, proximity, or similarity.
3. The 120 applications form one disjoint decision cohort because every member carries the Group-owner registry-policy blocker.
4. The cohort contains 110 unique owner proposals and 119 unique business proposals; repeated dependencies do not create additional legal identities.
5. The current registry projector can preserve source fields and provenance, but does not accept a legal-owner identity or activate a target mapping.
6. Reference-data, business, and application mapping dependencies remain independently visible.
7. Historical `Released` evidence, where present, remains historical evidence only and creates no present issuance, release, validity, or legal-effect authority.

## Missing Municipal Fact

The missing fact is the Municipality's accepted legal-owner and registry disposition for legacy records whose source owner type is `Group`.

Authorized reviewers must determine what evidence establishes the legal owner for each affected record or defensible subcohort, which municipal owner category applies, whether a distinct operational owner may be created or selected, and what should happen when that evidence is absent or contradictory. Engineering cannot supply that fact from the `Group` label, `groupName`, person fields, contact fields, or downstream business history.

The decision must also identify the authority responsible for the determination and the evidence retained with it. A category label without reviewer authority, evidence provenance, and record scope is not an accepted policy.

## Evidence-Supported Dispositions

These are bounded dispositions already supported by the program's existing reconciliation conventions. They are choices for authorized review, not recommendations or decisions made by this packet.

| Disposition | Municipal finding required | What it could permit after separate exact review | What remains quarantined |
| --- | --- | --- | --- |
| Map to an accepted legal-owner identity with organization semantics | Authoritative evidence identifies the exact legal owner and an accepted municipal owner category | Preparation and later acceptance of an exact owner mapping for the reviewed record or subcohort | Business and application mappings, reference data, historical status, rehearsal, and production migration until separately resolved and authorized |
| Preserve as a distinct historical owner pending registry maintenance | The Municipality determines that source identity and history must be retained but current operational legal identity is not yet established | Historical preservation of the source identity and provenance under a non-operational disposition, if the target contract supports the accepted treatment | Operational owner activation and all dependent operational mappings; any unsupported target-model change returns to the Board |
| Quarantine | Legal identity cannot be established from accepted evidence, or evidence is contradictory | Continued auditable preservation of the exact source facts and blocker | Owner, business, application, reference-data, rehearsal, execution, and production migration for the affected scope |

The Municipality may apply different dispositions to evidence-defined members or subcohorts. Any additional disposition requires its own authoritative definition and must not be inferred or implemented through this packet.

## Meaning Of A Future Acceptance

A future policy acceptance would mean only that the Municipality has:

- adopted an explicit registry disposition for the reviewed Group-owner scope;
- named the authorized decision-maker and applicable legal or registry basis;
- defined the evidence sufficient to establish an exact legal owner and accepted owner category;
- identified how absent, incomplete, or contradictory evidence is quarantined; and
- recorded the decision against the frozen cohort or a newly fingerprinted, evidence-defined subcohort.

Policy acceptance by itself would not accept any owner mapping. Exact mapping acceptance would remain a subsequent, evidence-bound review step.

A future acceptance would **not**:

- infer a legal identity from `Group`, `groupName`, a person's name, contact details, similarity, or adjacency;
- merge owners or businesses;
- invent a parent, representative, legal owner, organization, registration, or source fact;
- accept business, application, location, classification, line-of-business, fee, or financial mappings;
- infer fee identity or recalculate historical liability;
- convert historical `Released` into current permit issuance, release, validity, or legal effect;
- authorize a registry or historical-preservation rehearsal;
- weaken any planner, executor, audit, or rollback guard;
- mutate production; or
- authorize production migration or cutover.

## Effect On Business And Application Mapping

Owner policy is a prerequisite, not a cascade of automatic acceptance.

If an accepted policy and record-level evidence establish an exact owner mapping, the 119 business proposals may then be reviewed against that owner decision. Each business would still require its own exact identity decision and accepted location and classification reference-data crosswalks. Each application would still require exact application mapping, declaration and line-of-business reconciliation where applicable, and preservation of any independent financial, lifecycle, or permit-authority blocker.

If the owner is preserved only as non-operational historical identity or remains quarantined, dependent business and application records may retain their exact source evidence and ownership edges, but they must not be promoted into operational mappings by bypassing the owner boundary.

No disposition permits one unresolved business or application fact to erase an otherwise exact source fact. Equally, no exact source fact silently resolves legal-owner policy.

## Reversible Rehearsal Boundary

No rehearsal is authorized now.

After an accepted Group-owner policy, a reversible registry rehearsal could become eligible for separate preparation only for records that also receive exact owner-mapping acceptance and satisfy the existing target-contract guards. A historical-preservation rehearsal could become eligible only after exact owner, business, application, and required reference-data mappings are all accepted for a checksum-bound selection.

Any rehearsal would require a new bounded authorization naming the exact cohort or subcohort, accepted mapping set, expected projections, audit assertions, rollback and restoration checks, and confirmation that operational and production records remain unchanged. A policy decision alone does not make all 120 applications rehearseable.

## Required Municipal, Legal, And Registry Evidence

Authorized reviewers should provide, for the cohort or each evidence-defined subcohort:

- the accepted meaning of the legacy `Group` representation;
- the municipal legal-owner categories that may receive such records, without creating a new category from engineering inference;
- the authoritative documents or registry sources sufficient to establish the exact legal owner;
- the rule for interpreting any person-oriented fields stored beside a Group record;
- the required relationship between the recorded group name, legal name, registration identity, and municipal registry identity;
- the treatment of representatives, contacts, or signatories where those roles are evidenced;
- the disposition for missing, incomplete, expired, conflicting, or unverifiable evidence;
- whether decisions are record-specific or may apply to a precisely defined evidence class;
- the BPLO registry custodian responsible for review and the municipal legal authority required for category interpretation;
- privacy, retention, and provenance requirements for the supporting evidence; and
- the effective date, decision reference, reviewer identity, and approval record.

Where the decision could alter legal ownership, municipal legal guidance is required. Where a dependent disposition would alter payment, liability, receipt, or collection meaning, Treasury authority remains separately required.

## Safety Invariants

The following invariants apply regardless of disposition:

1. No similarity-based identity merge or acceptance.
2. No inferred legal identity from names, contacts, amounts, adjacency, or source labels.
3. No invented parent, legal owner, representative, organization, registration, or mapping.
4. Exact source records, ownership edges, hashes, and contradictory evidence remain preserved.
5. Proposed, accepted, rehearsed, and production-applied states remain distinct.
6. Owner certainty does not automatically promote business or application certainty.
7. Historical `Released` never creates current issuance, release, validity, signatory, or legal-effect authority.
8. Historical financial facts are preserved without recalculation or inferred fee identity.
9. Migration executors, audit checks, rollback, and restoration guards are not weakened.
10. No production mutation, production migration, or cutover is authorized.

## Exact Board And Municipal Questions

The Municipality and Board must answer the following before this cohort can advance beyond the registry-policy boundary:

1. What does the legacy `ownerType = Group` representation mean for municipal registry purposes, and may that meaning vary by record or evidence-defined subcohort?
2. Which existing municipal legal-owner categories, if any, may receive a Group-owner record?
3. What authoritative evidence is required to establish the exact legal owner and category for each affected record?
4. How must `groupName` and any accompanying person-oriented fields be interpreted: legal identity, representative, contact, historical label, or another role established by evidence?
5. Who has authority to approve the registry disposition and each exact owner mapping: BPLO registry custodian, municipal legal officer, another official, or a defined combination?
6. May an exact, evidence-backed organization owner become an operational registry identity, or must it remain a distinct non-operational historical identity pending registry maintenance?
7. What is the required disposition when authoritative identity evidence is missing, incomplete, expired, or contradictory?
8. May one policy apply to a precisely defined evidence class, or is record-by-record adjudication required?
9. What decision provenance, supporting-document retention, privacy controls, and effective-date evidence must accompany each accepted disposition?
10. After the policy is adopted, does the Board authorize preparation of exact owner-mapping proposals only, with business and application mappings still separately blocked?
11. After exact owner mappings are separately accepted, may the Chief Architect prepare a bounded reversible registry-rehearsal authorization packet, without executing it?
12. Does the Board affirm that this decision grants no current permit authority, financial authority, production mutation, production migration, or cutover authority?

## Decision Record

Decision status: `PENDING`

Selected disposition(s):

Evidence-defined scope or subcohort fingerprint(s):

Municipal legal or registry basis:

Required evidence standard:

Disposition for insufficient or contradictory evidence:

Authorized reviewer(s) and office(s):

Decision reference and effective date:

Owner-mapping proposal preparation authorized: `NO / YES — PREPARATION ONLY`

Rehearsal-packet preparation authorized: `NO / YES — PREPARATION ONLY AFTER EXACT MAPPING ACCEPTANCE`

Production mutation, migration, or cutover authorized: `NO`

## Current Chief Architect Disposition

`DECISION-READY AT REGISTRY-POLICY BOUNDARY; COHORT QUARANTINED`

The current Migration Readiness Compass already records this exact position. No Compass change is required by preparation of this packet.
