# Municipal Identity and Registry Review Packet

Status: **Municipal review requested — no policy answer recorded**

Prepared: 2026-08-18

For: Nelson and authorized Municipality of Ipil BPLO, registry, records, legal, and other designated personnel

Purpose: ask two bounded questions about actual legacy registry practice. This packet records no policy answer, accepts no mapping, and authorizes no rehearsal, production migration, or cutover.

## Why We Are Asking

Historical records contain two substantial populations whose source facts can be preserved but whose legal-owner treatment cannot be safely determined by software. We have quarantined that ambiguity rather than guessing.

Municipal answers could clarify the path for later evidence review. They would become evidence subject to the Chief Architect's acceptance boundaries; they would not change any record or activate any migration by themselves.

The two populations total 570 applications, but they are **not one readiness cohort** and must not be treated as ready to migrate. They ask different questions and retain different downstream blockers.

## Review A — Shared Email or Phone in Owner Records

### Population

| Frozen fact | Value |
| --- | ---: |
| Historical applications | 450 |
| Proposed owner records | 443 |
| Proposed business records | 447 |
| Historical `Released` applications | 442 |
| Other historical statuses | 8 |
| Hashed contact collision groups | 254 |
| Contact-signal-only class SHA-256 | `2833fc6ff6fd3d9cc581f1755bb1982aea7becc29d36b64a3d78380276e0d93b` |

For every member of this class, the only observed owner collision signal is a shared normalized email address and/or telephone number. That is a review signal, not proof that the records represent either the same legal owner or different legal owners.

### Questions for the Municipality

1. In actual Municipality of Ipil registry practice, did sharing an email address or telephone number by itself mean that two owner records represented the same legal owner?
2. If not, what authoritative evidence may establish that the records represent distinct legal owners?
3. Which municipal role or combination of roles is authorized to make and approve that determination?
4. Must review occur per hashed contact collision group, per source record, or under a precisely defined evidence rule that can be applied consistently?
5. What must happen when the evidence is missing, insufficient, expired, unverifiable, or contradictory?

An acceptable answer may distinguish different evidence-defined cases. It must identify its scope, evidence basis, approving authority, and quarantine rule.

### What a Favorable Rule Could Permit

A favorable municipal rule could permit engineering to **prepare exact owner-mapping proposals for compliant, frozen members**. Every proposal would still require separate evidence review and acceptance.

It would **not**:

- make email or phone an identity authority;
- accept any owner, business, application, or reference-data mapping;
- merge or split owners;
- resolve reference data automatically;
- convert historical `Released` into present issuance, release, validity, or legal effect;
- infer fee identity or financial authority;
- authorize a rehearsal; or
- authorize production mutation, migration, or cutover.

Even after a favorable rule, exact owner, business, and application review; reference-data reconciliation; cohort freeze; and separate rehearsal authorization remain required. Some members also carry independent business-registration or other blockers that this answer cannot resolve.

## Review B — Legacy `ownerType = Group`

### Population

| Frozen fact | Value |
| --- | ---: |
| Historical applications | 120 |
| Group-owner cohort SHA-256 | `517318c19a152a483a7285467aeaaf8f32a0e372571ca0a1f703ec08f5581f74` |

`Group` is a legacy source label. It does not by itself establish a corporation, partnership, association, cooperative, sole proprietorship, representative account, branch, beneficial owner, signatory, or any other legal category.

The canonical technical evidence and full decision boundary remain in [Organizational-Owner Registry-Policy Decision Packet](ORGANIZATIONAL_OWNER_REGISTRY_POLICY_DECISION_PACKET.md). This summary does not replace or amend that packet.

### Questions for the Municipality

1. What did `ownerType = Group` mean legally and operationally in actual Municipality of Ipil registry practice?
2. Could its meaning vary by record or evidence-defined class?
3. Which existing municipal legal-owner categories, if any, apply?
4. What authoritative evidence establishes the exact legal owner and the applicable category?
5. How should a group name and any accompanying person-oriented fields be understood when the evidence identifies a legal owner, representative, contact, signatory, or historical label?
6. Which municipal role or combination of roles is authorized to make and approve the interpretation and any record-level disposition?
7. What must happen when the evidence is missing, insufficient, expired, unverifiable, or contradictory?

An answer must not create a legal category or owner identity from the `Group` label alone. Any later record disposition remains subject to exact evidence, provenance, and separate acceptance.

## How to Answer

Please provide any combination of the following that supports the Municipality's answer:

- an existing written policy, ordinance, resolution, registry manual, or approved procedure;
- representative examples from actual legacy registry practice;
- authoritative registry documents or record types that establish legal-owner identity or category;
- the name or designation of the municipal role authorized to interpret the practice and the role authorized to approve the decision; and
- the required treatment of missing, insufficient, or contradictory evidence.

`We don't know` or `insufficient evidence` is an acceptable answer. That answer keeps the affected records safely quarantined.

Do not place production personally identifiable information in this packet or in ordinary review correspondence. Any record-specific evidence must be handled through approved private evidence channels, with appropriate access, privacy, retention, and provenance controls.

## Response Record

Review A — shared-contact practice and evidence rule:

Authoritative source or practice basis:

Scope: `collision group / record / precisely defined evidence class / other`

Insufficient or contradictory evidence disposition:

Authorized municipal reviewer(s), role(s), and approval reference:

Review B — legacy `Group` meaning and applicable existing category or categories:

Authoritative source or practice basis:

Scope: `cohort / evidence-defined class / record / other`

Insufficient or contradictory evidence disposition:

Authorized municipal reviewer(s), role(s), and approval reference:

## Standing Safety Boundary

Until an authorized answer is received, evidenced, and separately accepted by the Chief Architect, both populations remain quarantined exactly as they are.

No response to this packet by itself accepts mappings, changes owner or business identity, resolves reference data, activates historical status, changes fees or liabilities, authorizes rehearsal, mutates production, authorizes migration, or authorizes cutover. Nelson Visual Walkthrough Cycle 1 and UI/UX Cycle 1 remain frozen; this review does not start UI Cycle 2.
