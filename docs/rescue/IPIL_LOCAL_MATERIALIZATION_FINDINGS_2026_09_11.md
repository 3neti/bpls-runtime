# Ipil Local Materialization Findings

Status: **GATE 6 OBSERVATIONS — NON-PII**

Date: 2026-09-11

Execution report: [Gate 6 First Local Historical Materialization](IPIL_RESCUE_GATE_6_FIRST_LOCAL_MATERIALIZATION_2026_09_11.md)

## Model Fit

Dedicated `ipil_historical_*` projections were necessary to preserve legacy truth without weakening current BPLS invariants. The approach fits the rescued history cleanly: one-to-one source-key owners and businesses, non-operational Applications, exact-decimal financial evidence, receipt and permit claims without present authority, and an all-source identity/provenance ledger. Current domain tables remain at their installed zero-transaction baseline.

The SourceIdentity ledger contains all 324,873 database/media/pricing identities. It intentionally stages no authentication token/session/account payload. The separate historical evidence ledger contains 246,230 non-auth records, preserving raw JSON spelling and independent media/pricing classes. Specialized projections refer back to those identities; a read-only check found zero specialized targets without provenance.

## Relationship Findings

Owner → Business and Owner/Business → Application edges materialized completely. Permit anomalies reconciled exactly to the accepted anchors: 15 missing Applications, ten broken Business edges, and ten broken Owner edges. Clearance anomalies also reconciled at 110 broken type references.

The first attempt revealed a too-strong implementation constraint around financial parent edges. The corpus contains 69 schedules without a rescued Application, three payments without a rescued Application, and 56 payments without a rescued schedule. The Mapping Specification already required broken child edges to remain unresolved rather than guessed or discarded. The schema was corrected to nullable historical relationships with explicit flags; no mapping-profile change was required.

## Exactness and Evidence Fallbacks

Raw numeric lexemes remain authoritative. Exact normalized decimal text is derived without float arithmetic. The 348 Application totals and 24 schedule totals that cannot be represented as integer centavos remain exact historical values. Completed payment evidence and schedule-paid evidence independently total PHP 93,295,317.20.

Duplicate receipt claims fit the historical model without touching current OR uniqueness: 5,873 claims include 196 normalized duplicate groups and 744 events in those groups. Failed and pending payment attempts remain separate. No current collection, retry, OR, liability, or pricing behavior is inferred.

PSGC remains proposals only: 24 exact normalized-name candidates and 20 unresolved source barangays, with zero accepted canonical assignments. The 4,236 historical LOB items remain source classifications; no current Nelson or Treasury LOB assignment was created. Five recovered pricing identities remain an independent candidate-only evidence class and activate no FeeRule.

## Media Fit

The historical media evidence model accounts for 35 objects. Sixteen manifest-associated objects have checksum-equal managed copies on the isolated local-private `ipil_gate6` disk. The DTI business document is separated as `application_documents`; report, permit-layout, and platform artifacts use `legacy_generated_artifacts`. Nineteen unresolved bytes remain outside Spatie attachment. This preserves the distinction between applicant evidence and generated municipal artifacts while the corpus remains source truth.

An early successful import used the general private media root. Those derivative copies were removed from that shared path and retained under the private Gate 6 evidence area before the final clean ceremony; final managed media uses only the isolated Gate 6 disk. No source byte changed and nothing was uploaded.

## PostgreSQL and Product Projection

The final database size was approximately 483 MB. Bulk SourceIdentity/evidence preservation dominated Run 1; the complete run took 37.843 seconds and all domain projections together completed in only a few seconds. Run 2 took 29.867 seconds because it revalidates every identity and payload before treating it as already materialized. This is appropriate for the first proof; a later observatory may optimize validation without reducing provenance.

Read-only inspection found 2,984 businesses with one historical Application and 72 with multiple Applications; the remaining businesses retain registry continuity without an Application in this corpus. The relational projection supports history navigation, Additional Applications, exact financial bundles, duplicate receipt claims, permit/clearance claims, and associated document evidence.

Business and Application list probes were sub-millisecond. The initial Application → schedule/payment/permit detail probe exposed sequential scans and took 6.734 ms. Narrow foreign-key indexes reduced the same probe to 0.142 ms. Gate 7 should build on these relations and measure real pagination/search/report shapes; it should not use JSON payload scanning for ordinary list/detail views.

## UI and Next-Gate Implications

No web route currently presents the new historical projections, so Gate 6 found no false actionable controls to remove. The data is navigable and projectable at the repository/database boundary, but Gate 7 must add explicitly read-only presentation and authorization before staff use. Surfaces should say historical/legacy, distinguish claims from issued current artifacts, show missing relationships plainly, and never offer Nelson actions.

No mapping-spec correction was required. The implementation defects corrected during the disposable rehearsal were PostgreSQL constraint-name length, nullable preservation of already-governed broken finance edges, installed-user baseline accounting, private media-disk isolation, and missing relationship indexes.
