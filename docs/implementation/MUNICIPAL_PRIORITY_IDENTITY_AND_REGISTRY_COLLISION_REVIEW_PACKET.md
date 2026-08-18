# Municipal Priority Identity and Registry Collision Review Packet

Status: **Municipal and registry review requested — no disposition recorded**

Prepared: 2026-08-18

For: Authorized Municipality of Ipil BPLO registry, records, legal, Treasury, fiscal, permit-authority, and other designated reviewers

Purpose: present the second bounded municipal review queue for the priority non-contact legal-owner and business-registration collision groups, then route exceptional records to the authority appropriate to each issue. This packet prepares decisions only. It accepts no mapping and authorizes no rehearsal, production migration, or cutover.

The earlier [Municipal Identity and Registry Review Packet](MUNICIPAL_IDENTITY_AND_REGISTRY_REVIEW_PACKET.md) remains unchanged and canonical for the separate 450 shared-contact and 120 `Group`-owner questions. This packet does not reopen, duplicate, or answer those questions.

## What Reviewers Can Decide Now

Authorized reviewers can record an evidence-backed `same identity`, `different identities`, or `quarantine` disposition for every member of a complete collision group. A complete disposition may allow engineering to prepare exact mapping proposals for the decided identity dimension only.

The collision signal identifies the review unit. It does not prove the answer:

- name and date of birth do not establish legal-owner identity;
- a registration number does not establish business identity; and
- historical `Released` is non-operational evidence only. It grants no present issuance, release, validity, or legal effect.

## Order of Review

Review the self-contained queues first because their complete membership is already inside the frozen frontier slice. Review counts are separate identity dimensions and may overlap; they are not a percentage or a count of unique migration-ready applications.

| Order | Queue | Exact review scope | Why now |
| ---: | --- | ---: | --- |
| 1 | Closed non-contact legal-owner groups | 14 groups / 28 applications | Every known member is inside the frozen slice, so the legal-owner disposition can be self-contained. |
| 2 | Closed registration groups | 18 groups / 39 applications | Every known business member is inside the frozen slice, so the registry disposition can be self-contained. |
| 3 | Coupled non-contact legal-owner groups | 37 groups / 41 applications + 52 owner proposals outside the slice | Review must include full global owner-group membership. |
| 4 | Coupled registration groups | 34 groups / 36 applications + 37 business proposals outside the slice | Review must include full global business-group membership. |
| 5 | Soft-deleted exceptions | Eight applications across five disjoint authority routes | Route each issue separately; no single office or decision can dispose of the cohort. |
| 6 | Identity-plus-financial exception | One application | Coordinate a full-group legal-identity disposition and a separate fiscal-override decision. |

## A. Non-Contact Legal-Owner Review

### Frozen topology

| Review unit | Hashed `name_birth` groups | Applications in the priority slice | Membership outside the slice |
| --- | ---: | ---: | ---: |
| Closed / self-contained | 14 | 28 | 0 |
| Coupled / global | 37 | 41 | 52 owner proposals |
| Total | 51 | 69 | — |

The 69 applications contain 68 owner proposals. The closed route covers 28 owner proposals and all 28 applications carry historical `Released`. The coupled route covers 40 owner proposals / 41 applications plus 52 outside owner proposals; 40 applications carry historical `Released` and one does not.

`name_birth` is a hashed collision signal used to assemble complete review groups. It is not legal-identity authority and must not be used by itself to merge, separate, or categorize owners.

### Decision requested

For each complete group in scope, the Municipality should state:

1. What authoritative legal-owner evidence is acceptable to establish the same owner or distinct owners?
2. Which municipal role or combination of roles reviews the evidence and approves `merge`, `separate`, or `quarantine`?
3. Must the decision cover the whole collision group, including every source proposal in global membership?
4. What happens when evidence is missing, insufficient, expired, unverifiable, or contradictory?

Closed groups can be reviewed from the self-contained packet for that group. Coupled groups cannot advance from a partial decision: every outside owner proposal in the global collision group must be included before exact owner-proposal preparation can be unlocked.

## B. Business-Registration Collision Review

### Frozen topology

| Review unit | Hashed registration groups | Applications / business proposals in the priority slice | Membership outside the slice |
| --- | ---: | ---: | ---: |
| Closed / self-contained | 18 | 39 | 0 |
| Coupled / global | 34 | 36 | 37 business proposals |
| Total | 52 | 75 | — |

All 75 applications carry historical `Released`, which remains non-operational evidence only.

A shared registration number is a collision signal, not proof that records are duplicates or the same business. Authoritative evidence must distinguish, as applicable, a duplicate, renewal or historical version, branch, correction, or genuinely separate business identity.

### Decision requested

For each complete registration group in scope, the Municipality should state:

1. What authoritative registry evidence establishes that source records represent the same business-registration identity or different identities?
2. Which registry authority reviews the evidence and approves `same identity`, `different identities`, or `quarantine`?
3. Must the disposition cover every member of the whole registration group?
4. What is the quarantine rule when evidence is insufficient, unverifiable, or contradictory?

Closed groups can be reviewed self-contained. Coupled groups require all 37 outside business proposals to be included in their respective global groups. A registration disposition can unlock exact business-proposal preparation for that dimension only; it cannot settle legal-owner identity.

The current reviewer routing further separates 49 registration groups into one owner-evidence lane and three closed groups requiring coordination across shared-contact and non-contact owner review. That routing reduces coordination but does not change the 18 closed / 34 coupled decision boundary.

## C. Soft-Deleted Exception Routing

The eight applications form five disjoint decision routes. Do not send all eight to one authority and do not treat a deletion decision as a complete disposition.

| Route | Applications | Primary decision routing | What remains independent |
| --- | ---: | --- | --- |
| Deletion / identity / reference only | 3 | BPLO registry custodian with the records/privacy authority responsible for deletion treatment, legal identity, and historical reference evidence | Exact mapping acceptance, cohort freeze, and rehearsal authorization |
| Treasury interpretation | 2 | Municipal Treasurer or formally delegated Treasury authority, coordinated with BPLO registry/records reviewers | Deletion, owner identity, reference data, exact mappings, freeze, and rehearsal authorization |
| Fiscal authority | 1 | Authorized fiscal or revenue-policy authority, coordinated with BPLO registry/records reviewers | Deletion, owner identity, reference data, exact mappings, freeze, and rehearsal authorization |
| Permit-authority semantics | 1 | The municipal permit authority responsible for issuance/release meaning, coordinated with BPLO registry/records reviewers | Deletion, owner identity, reference data, exact mappings, freeze, and rehearsal authorization |
| Source contradiction | 1 | BPLO records custodian and the authoritative owner of the contradictory source facts; preserve both facts until an evidenced disposition is approved | Deletion, owner identity, reference data, exact mappings, freeze, and rehearsal authorization |

No route is one decision from exact proposal preparation or rehearsal-ready. Where more than one authority is named, each authority decides only its own dimension.

## D. Cross-Authority Identity and Financial Exception

One application requires two independent decisions:

1. an authoritative legal-owner disposition across the full global owner-collision group; and
2. a separate decision by the authorized fiscal-override authority.

Neither decision alone advances the record to exact proposal preparation. Nelson is not the legal-identity or fiscal-override authority and cannot decide this exception alone. Reference data, exact mappings, cohort freeze, rehearsal authorization, and every production gate remain separate after both decisions.

## Response Record

For every reviewed collision group or exception route, record only payload-safe references in this packet:

- queue and checksum-bound group reference;
- disposition: `same identity / different identities / quarantine / referred to another authority`;
- authoritative evidence type and private evidence location;
- reviewing and approving municipal roles;
- approval reference and date;
- confirmation that the decision covers complete group membership; and
- treatment of insufficient or contradictory evidence.

Do not place names, dates of birth, contact details, registration numbers, source identifiers, financial details, or other private record data in this packet or ordinary correspondence. Record-specific evidence belongs only in the approved private, checksum-bound review channel.

## Decision Effect and Standing Boundary

A complete authorized disposition can unlock **exact proposal preparation for decided dimensions only**. It does not:

- accept any owner, business, application, declaration, financial, or reference-data mapping;
- create, merge, split, or activate an owner or business record;
- resolve reference data automatically;
- infer identity from name, date of birth, contact details, registration number, adjacency, or similarity;
- activate historical `Released` or grant present permit authority;
- infer fee identity, alter taxpayer liability, or exercise Treasury/fiscal authority;
- freeze a migration cohort;
- authorize or execute rehearsal;
- authorize or execute production migration or cutover; or
- start UI Cycle 2.

Human-frontier accepted mappings: **0**. Human-frontier rehearsed mappings: **0**. Human-frontier production-applied records: **0**.

## Technical Provenance Appendix

The appendix binds this stakeholder packet to payload-safe additive evidence. Raw collision fingerprints and private identifiers remain excluded.

### Frozen v4 and v5 lineage

| Evidence set | SHA-256 |
| --- | --- |
| Human-identity frontier | `8b1b80d4b2f38eb186186930c567e1e9eb7b83c4b28490307117381056064bbc` |
| Collision-free business-source subclass | `ab4380ec8b56e928e0b73671c424ccc7048a032ca7a2bc4095577cb50e2ead03` |
| Seven decision cohorts | `dcbfaadec88b19ed564951af29b24c194049a903036c9c98c3ef922dc0c05d41` |
| Municipal identity evidence classes | `5aed72372bb3cf5260946196f23ab6f5e126eff6e1918b8947fcdfa9b14699c5` |
| Contact-signal-only class | `2833fc6ff6fd3d9cc581f1755bb1982aea7becc29d36b64a3d78380276e0d93b` |
| Non-contact-identity-signal class | `3c6e7efda2352b40d6d6a25522d3c7ebd1b1a1cffceb79664a0bbb5e329b055d` |
| V5 priority-review topology | `53790859b7bd63430c4e3f35e0a212b22cade849202d56aa25a45def80a59c7f` |

### Additive v6 decision-unlock evidence

| Evidence set or route | SHA-256 |
| --- | --- |
| V6 decision-unlock topology | `b627a317ccff26133ea5b98d3afcf0ee5c4fb356154480de3fe6eae7bc5bfceb` |
| Non-contact closed/coupled topology | `d1efd18f13e178bcd1cf4e90559eba73f8305acedd92a0c2f68b91e42e0c5aed` |
| Registration closed/coupled topology | `d59cc618662288edb98d12c46667e7382e81d3099177b2cbf4ce448d5ef9080b` |
| Soft-deleted: deletion / identity / reference only | `bc5e7c6e0367c3cef4bdf9c6d0e8dc3362ff6f9a18a91aeedcab02e6dceb041a` |
| Soft-deleted: Treasury interpretation | `36897bb86f28f4379733eeb2d74bbb48842a78d42beb33fdec758d78a3bf996b` |
| Soft-deleted: fiscal authority | `98d94003de6fd9adc3c7a87c998b44ca57b4edc67d5e60340df9a60b89bfb7a7` |
| Soft-deleted: permit-authority semantics | `e3eddc9c40a7e32dca78ac22a3641170bf475675210d1d420bfdaf79cff21bdd` |
| Soft-deleted: source contradiction | `a66dcc4a646671a7eb061bddfc77452433828b46b556c3396f8b36d86b00506d` |
| Identity-plus-financial exception class | `a56925f4ef95b61e6838883b3bb9b0f9f3e5ba48c8ef6ce6a2df0b2f9a6bec15` |
| Identity-plus-financial global owner-group topology | `d1eaa42f49f969a8c8d966a1c0d7324ec464521e1a5fe42b1fcc3ec43238fb87` |

### Current additive routing

V7 preserves the complete v6 output unchanged. Registration decision-route set SHA-256: `f64c014c67354ed0700e54ad06d069dd6fbb5ba2d8a311a059f8322932359e57`.
