# BPLS Runtime Program Context

Status: **Canonical induction context**

As of: 2026-08-18

## Mission

Rescue and replatform the Municipality of Ipil Business Permit and Licensing System into one Laravel 13 application on Laravel Cloud. Preserve observable behavior and municipal evidence while replacing accidental legacy architecture.

The rescue comes first. Do not broaden this work into a generic municipal platform or GNE product effort.

## Current Phase

The program is in mid implementation with two active lanes:

```text
Engineering lane
    continues through bounded TOR and parity capabilities

Production reconciliation lane
    continues through read-only evidence, exact mapping, rehearsal, audit, and rollback
```

Production migration execution and cutover are not authorized.

The Nelson Visual Walkthrough and UI/UX Cycle 1 are the frozen stakeholder baseline while municipal operational feedback is pending. Do not begin UI Cycle 2 or make speculative navigation, workflow, terminology, status, clearance, or report changes. Use `docs/agents/NELSON_FEEDBACK_INTAKE.md` to preserve and classify feedback when it arrives, and `docs/agents/LEGACY_VISUAL_PARITY_COMPARISON.md` to compare separately proven legacy and Laravel visual evidence.

## Canonical Evidence

The four canonical discovery sources are registered under `docs/sources/`:

- Municipal Revenue Code;
- Terms of Reference;
- authoritative legacy source archive;
- observable live applications.

The authenticated Convex production snapshot is private production evidence. Its provenance and checksums are recorded through the migration intake process; raw payloads, storage identifiers, credentials, and personal or financial records remain outside Git.

Production tells us what happened. It does not automatically establish what is legally, fiscally, or operationally authorized.

## Approved Architecture

- One Laravel 13 monolith with Vue/Inertia.
- One relational operational source of truth.
- Laravel policies and permissions enforce business authority server-side.
- One authoritative assessment calculation path.
- Assessments and financial outcomes use explainable immutable snapshots.
- Reporting reads persisted domain evidence and does not recalculate liability.
- Generated documents have a dedicated rendering boundary.
- Migration uses immutable intake, staging, deterministic mappings, audit, rollback, and provenance.
- Unknown business policy remains an explicit seam.
- No Convex, ClickHouse, Airbyte, or Vercel runtime is retained merely for parity.

The architecture is approved and remains healthy. Some older architecture documents retain their original review-era status wording; later Board decisions, `.ai/rules`, and implemented invariants reflect the approved current state.

## Business And Authority Boundaries

### Identity

```text
User account
    != legal BusinessOwner identity
    != application submission actor
```

The durable relationship is `User -> BusinessOwner -> Businesses`. `submitted_by_id` records who submitted an application and does not establish legal ownership.

### Submission

```text
Draft
    -> citizen submits
    -> municipality receives
    -> municipal processing begins
```

Drafts remain unsubmitted, unnumbered, and outside municipal processing. A tracking reference is not an official application number.

### Permit Authority

```text
Permit artifact
    != authority review
    != permit issuance
    != permit release
    != legal effect
```

Likewise:

```text
Configured official
    != document signatory
    != authorized signatory
    != issuance authority
    != legal effect
```

The current system can reach `Ready for Authority Review`, generate an artifact, and publicly verify artifact identity. It must refuse issuance, release, validity, and legal-effect claims until accepted municipal authority exists.

### Numbering

Internal identity, citizen tracking reference, official application number, permit number, and receipt number are separate facts. Official numbering formats and allocation authority remain unresolved unless a specific accepted boundary says otherwise.

## Financial Boundary

The governing chain is:

```text
Revenue Code evidence
    -> operational reconciliation
    -> accepted executable policy
    -> assessment snapshot
```

Extraction and deployed legacy configuration do not authorize execution. Ambiguous rules remain recorded, visible, traceable, and non-executable.

`CAL-2026-001` is the first Golden Financial Specimen. It preserves a five-layer historical calibration chain while keeping historical reproduction separate from future policy authorization. The Municipal Fiscal Acceptance Packet remains the path for unresolved future rate, quarterly allocation, delinquency, surcharge/penalty, and rounding policy.

Historical financial migration preserves what was actually assessed, scheduled, collected, and paid. It does not recalculate historical liability or manufacture missing fee-policy identity from names.

## Migration State

The migration state model must remain explicit:

```text
observed -> inferred -> proposed -> accepted -> rehearsed -> production-applied
```

These states never collapse automatically.

Current production findings:

- 308,038 production records were inventoried at Production Ground Zero.
- Historical Financial Preservation V1 has a corrected population of 1,223 applications; 15 incompatible histories remain preserved as a separate exception class.
- The complete exact historical-evidence class contains 407 applications.
- All 407 were rehearsed through execute, source-to-target audit, rollback, and restoration audit.
- Those rehearsals covered 696 schedules, 3,007 historical fee lines, 660 completed payments, 36 unpaid schedules, 412,770,810 scheduled centavos, and 397,445,008 paid centavos.
- Operational assessment, schedule, collection, and receipt records remained unchanged.
- 816 V1 histories remain outside exact mapping: 736 require human identity reconciliation, 72 require registry-policy reconciliation, five require soft-delete/payment-schedule reconciliation, and three require financial-override reconciliation.
- The 736 human-identity applications remain quarantined rather than guessed.
- The current human-identity frontier contains 40 evidence shapes, 469 owner-collision groups, and 80 business-collision groups. A 12-application subclass has collision-free owner proposals but unresolved business collisions.
- A separate evidence-only subclass contains 519 applications and 515 unique business proposals with collision-free source business records, exact source owner edges, and collision-free business projections. Legal-owner identity, reference-data reconciliation, and all business/application mapping acceptance remain unresolved; this class contains zero mapping candidates and authorizes no rehearsal.
- The 736 frontier now reproduces as seven disjoint decision cohorts: 510 collision-free business-source records carrying historical `Released`; nine collision-free business-source records without that overlay; 120 Group-owner registry-policy cases; 76 compound owner/business collisions; 12 collision-free-owner/business-decision cases; eight soft-deleted registry-policy/exception cases; and one identity-plus-financial exception. The original frontier and 519-subclass fingerprints remain unchanged; decision-cohort set SHA-256 is `dcbfaadec88b19ed564951af29b24c194049a903036c9c98c3ef922dc0c05d41`.
- The seven-cohort characterization creates no accepted or rehearsed human-frontier mapping. The 12-application/nine-owner cohort remains ready only for owner-acceptance review, while the 120 Group-owner cases require an explicit registry-policy decision before exact mapping review can advance.
- Additive v4 municipal-identity characterization leaves the seven cohorts and every prior fingerprint unchanged while splitting the 519 collision-free-business-source evidence class into 450 contact-signal-only applications and 69 applications carrying a non-contact identity signal. The 450 represent 443 owner proposals and 447 business proposals across 254 hashed email/phone collision groups; 442 carry historical `Released` and eight do not. The 69 represent 68 owner and business proposals; current production evidence observes the planner `name_birth` signal. No signal establishes legal identity.
- A bounded municipal decision on shared-contact reuse, acceptable evidence for distinct legal owners, approving authority, and quarantine disposition could advance up to 450 applications into exact mapping and reference-data review. It would not accept a mapping or make the applications rehearsal-ready.
- Additive v5 priority-review characterization preserves every v4 count and fingerprint. The 69 non-contact applications reduce to 51 exact hashed `name_birth` review groups. Of the 76 compound owner/business collision applications, 75 share a separate registration-number collision dimension across 52 globally deduplicated review groups; those 75 route into 34 contact-owner and 41 non-contact-owner applications, while one non-`Released` phone-plus-owner/name outlier remains separate. Signals define review units only and do not establish legal owner or business identity.
- The eight soft-deleted frontier applications remain one quarantine with independently visible policy/authority overlays: five contact-only, three non-contact, two Treasury, one financial-authority, one permit-authority, and one source-contradiction application. Overlay counts are not asserted disjoint. The one identity-plus-financial exception remains behind separate identity and fiscal-authority decisions.
- No newly characterized frontier application is one bounded decision from rehearsal-ready. Human-frontier accepted mappings, rehearsed mappings, and production-applied records remain zero.

The acceleration principle is: quarantine ambiguity at the smallest semantic boundary rather than blocking an otherwise exact historical fact. This does not authorize identity merges, invented parents, inferred fee identity, current release authority, historical recalculation, production migration, or cutover.

## Capability And Evidence State

- Discovery identified 118 externally or contractually meaningful capabilities.
- The authoritative implementation status is `docs/implementation/PARITY_LEDGER.md`; capability rows have unequal business weight.
- The citizen-originated new permit milestone is browser verified through authority review.
- The Nelson walkthrough composes the current operational journey and payload-safe migration evidence without introducing business logic.
- Operational reports are implemented across several TOR surfaces; authority-bearing reports remain blocked where issuance or classification authority is absent.
- Production financial, identity, configuration, and migration parity remain incomplete until accepted reconciliation exists.

Do not restate a capability as complete unless its parity row, tests, browser evidence, and audit support that claim.

## Evidence Discipline

- Scenario artifacts live under `storage/app/private/**` and normally remain outside Git.
- Git may contain aggregate counts, hashes, table names, redacted classifications, and payload-safe conclusions.
- Git must not contain raw production rows, personal information, receipt/payment identifiers, storage IDs, documents, credentials, cookies, or tokens.
- Browser verification is complete only when actually executed. Report `NOT RUN` when credentials or environment are unavailable.
- Production is evidence, not a test sandbox. Mutation requires explicit authorization.
- Nelson feedback is operational evidence, not automatic legal, fiscal, Treasury, numbering, signatory, issuance, release, or legal-effect authority.
- Legacy screenshots and Laravel screenshots remain separate evidence classes with separate provenance. Familiarity does not override an approved authority or safety boundary.

## Canonical References

- Architecture: `docs/architecture/`
- Discovery: `docs/discovery/`
- Sources and safety: `docs/sources/`
- Parity: `docs/implementation/PARITY_LEDGER.md`
- Milestones: `docs/implementation/MILESTONE_SCENARIOS.md`
- Financial calibration: `docs/implementation/FINANCIAL_CALIBRATION_SUITE.md`
- Historical preservation: `docs/implementation/HISTORICAL_FINANCIAL_PRESERVATION_BOUNDARY.md`
- Program decisions: `docs/reports/engineering-program-review/`
- Business journeys: `storyboards/`
