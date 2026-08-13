# Architecture Decision Register

Architecture date: 2026-08-13

## ADR-001: Single Laravel Monolith

- Decision: Build one Laravel 13 application with Vue/Inertia.
- Evidence: TOR expects one integrated BPLS/Treasury system; Discovery found three legacy apps but shared backend/domain.
- Alternatives considered: reproduce three apps; microservices; keep Next.js frontends.
- Chosen direction: single Laravel app.
- Reason: fastest coherent rescue, simpler deployment, easier shared authorization/audit/financial consistency.
- Reversibility: medium. Feature boundaries can later be split if needed.
- Known condition/risk: UI parity must still distinguish staff, citizen, and reporting surfaces.

## ADR-002: Relational Database As Source Of Truth

- Decision: Use relational operational data in Laravel as canonical storage.
- Evidence: Target Laravel Cloud direction; Discovery says Convex/ClickHouse/Airbyte should not be retained merely for parity.
- Alternatives considered: Convex compatibility layer; ClickHouse reporting mirror.
- Chosen direction: normalized relational model with optional projections.
- Reason: Laravel-native, auditable, migration-friendly, sufficient until profiling proves otherwise.
- Reversibility: medium.
- Known condition/risk: production data/config export is still needed.

## ADR-003: Assessment Snapshots Are Explicit

- Decision: Persist assessments and assessment lines as explainable snapshots.
- Evidence: TOR and ordinance require deterministic taxes/fees; legacy source has embedded fee snapshots in schedules.
- Alternatives considered: compute all fees live from current config.
- Chosen direction: compute from versioned/configured rules, then snapshot assessed result used for payment/reporting.
- Reason: auditability, financial tests, migration traceability.
- Reversibility: low once financial records exist.
- Known condition/risk: exact fee rules and production configuration remain unresolved.

## ADR-004: One Calculation Engine

- Decision: Implement one authoritative assessment/formula calculation path.
- Evidence: Discovery found multiple legacy formula evaluators and identified divergence risk.
- Alternatives considered: separate assessment/report/surcharge calculators.
- Chosen direction: shared calculation services with context-specific inputs, versioned policy, and test fixtures.
- Reason: financial correctness.
- Reversibility: low.
- Known condition/risk: must characterize legacy formulas before implementation.

## ADR-005: Billing Groups Remain A Treasury Collection Boundary

- Decision: Preserve billing groups as a configurable non-permit collection mechanism.
- Evidence: Legacy source/live reporting show billing groups; TOR names miscellaneous, rental, franchise, and other collections.
- Alternatives considered: build separate modules for each revenue type immediately.
- Chosen direction: retain billing group concept, while leaving acceptance as an owner decision.
- Reason: fastest path to parity with known legacy behavior.
- Reversibility: medium. Explicit modules can later be backed by billing-group or collection primitives.
- Known condition/risk: owner must confirm this satisfies TOR Treasury module expectations.

## ADR-006: Documents Are Rendered Through A Document Boundary

- Decision: Keep document definitions/artifacts/renderers outside controllers and Vue pages.
- Evidence: Discovery found permits, receipts, assessment sheets, application forms, and report exports.
- Alternatives considered: per-page PDF rendering.
- Chosen direction: document services/jobs and stored artifacts.
- Reason: repeatability, auditability, testability, and future renderer replacement.
- Reversibility: medium.
- Known condition/risk: rendering library choice deferred.

## ADR-007: Laravel Policies/Gates For Business Authority

- Decision: Use Laravel policies/gates backed by roles/permissions and ownership checks.
- Evidence: Discovery found staff RBAC, citizen ownership isolation, and route/UI permission gaps.
- Alternatives considered: route middleware only; frontend-only permission gating.
- Chosen direction: backend authorization on every business action, frontend only for affordances.
- Reason: security and testability.
- Reversibility: low.
- Known condition/risk: legacy permission matrix must be mapped carefully.

## ADR-008: Reporting Inside Laravel With Rebuildable Projections

- Decision: Do not recreate ClickHouse for initial rescue.
- Evidence: Project direction says not to retain ClickHouse/Airbyte; Discovery found reports coupled to operational data and fee logic.
- Alternatives considered: relational + ClickHouse mirror; BI database.
- Chosen direction: relational query services, projections/aggregates only when justified, queued exports.
- Reason: reduces infrastructure and improves traceability.
- Reversibility: medium.
- Known condition/risk: large production data volumes may later require optimization.

## ADR-009: Migration Uses Staging And Legacy ID Mapping

- Decision: Separate migration code and staging from permanent runtime behavior.
- Evidence: TOR requires migration; production data not supplied; legacy archive provenance exists.
- Alternatives considered: direct one-shot import into final tables.
- Chosen direction: staged imports, deterministic transforms, mapping tables, validation reports.
- Reason: repeatability and recoverability.
- Reversibility: high before cutover.
- Known condition/risk: production export format unknown.

## ADR-010: Unknown Business Policy Remains A Seam

- Decision: Do not invent behavior for online payments, receipt numbering, retirement, transfer, or PIL.
- Evidence: Discovery conditions explicitly unresolved.
- Alternatives considered: assume legacy behavior; assume ordinance-only behavior.
- Chosen direction: explicit interfaces/policy records and gap flags until owner/Treasury decisions.
- Reason: avoids false requirements and costly rewrites.
- Reversibility: high if seams are kept narrow.
- Known condition/risk: implementation cannot be complete until decisions/data arrive.
