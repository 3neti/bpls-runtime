# Ceremony-first guide v1.6.3 - release plan and compass

Updated 21 September 2026. Implementation and deployment were requested; exact release-SHA approval remains a separate pre-deployment gate. Renewal is paused. No production fiscal authority is introduced.

## Scope

- Private guide: first-page ceremony map, actor credentials repeated beside each action, concise main flow, detailed illustrated annex in the same PDF, internal return links. Credentials and private PDF remain outside Git.
- Reusable in-app confirmations for Evaluation actions and ordinary Permit authorization, issuance and release. Cancel/Escape must not mutate; existing stale-state, actor and duplicate-action boundaries remain authoritative.
- Formal ordinary Permit workspace: actual business/year/type, readable Philippine dates, compact readiness, visible blockers, one restrained test-use notice, collapsed evidence. Separate authorization/issuance/release and artifact specimen markings remain intact.
- Include already approved nullable Treasury entry-default and owner-only released-Permit preview changes. No hardcoded universal Mayor fee, no fee activation, no schema migration or catalog import required for the shipped null default.
- Generic unmapped-fee fallback, unresolved bracket activation and renewal policy remain deferred.

## Release ancestry

- Independently observed Cloud: `a0401d740816f25c7a3dcd5ee3ac382e1c5a2001`, deployment `depl-a2c97a8a-9f38-4944-b2a8-3a9052ac0bd6`, succeeded.
- Candidate branch: `release/ceremony-guide-v163`, accepted base `3093d507bc6977f75402786886103c08b9e335e2` and bounded transplants `03ba6c2`, `fb96e0b`, `4df2951`, `a148637`, `1f9ca66`, `6709c98`.
- Do not deploy canonical main wholesale: its unrelated paused-renewal ancestry is intentionally excluded here.
- Preserve local Application 39, Cloud Application 298, existing uploads/signatures and unrelated local guidance edits. Never copy a disposable database to Cloud.

## Compass

- [x] Scope and Cloud ancestry identified; isolated candidate worktree prepared.
- [x] Bounded UI/default/preview implementation; no domain-policy weakening.
- [x] PHP full suite: 1,192 passed, one skipped; 19,405 assertions. Tests use preview disabled by default, with explicit safe profiles and gated-route registration for preview fixtures. No dependency on a developer's private catalog or environment.
- [x] Frontend suite: 121 passed, serial execution. Actual dialog browser test passed desktop 1440px and 390x844: focus containment/restoration, Cancel/Escape zero actions, single confirmation, zero captured errors or overflow. Ordinary Permit component harness also passed blocked, authorization, issuance, release and released states at both viewports, with no repeat action on released state. Harness data is not a persisted lifecycle claim.
- [x] TypeScript, build, full ESLint, resource Prettier and full Pint passed. Eight lint spacing findings were corrected without behavior changes, including four inherited lines in the report-layout test.
- [x] Targeted PHPStan regression comparison: baseline Citizen controller extracted from candidate HEAD and analyzed with the same dependencies reports the same eight identifiers/messages as the changed controller; only line offsets differ. Other targeted changed PHP files have no reported findings. This is a file-level regression comparison, not a globally clean PHPStan claim.
- [x] Private candidate guide rendered: 46 pages, seven-page main flow. Testing Agent inspected all main pages and checked 62 PDF links, zero unresolved destinations. Exact Engineering Certificate Fee identity verified; no substitution with the synthetic Regulatory Fee.
- [ ] Fresh disposable guide-only lifecycle. Testing Agent is currently waiting on a tool approval before proceeding; no lifecycle pass is claimed.
- [ ] Independent source/browser/document acceptance; clean reviewed release SHA.
- [ ] Exact-SHA approval, push and Cloud deployment.
- [ ] Cloud read-only continuity checks, fresh Cloud guide-only lifecycle and final screenshot/guide publication.

## Disposable browser acceptance

The Testing Agent owns the new SQLite database and loopback server on port 8127. It began with zero transactions and an independently checked source fixture. Only the guide origin changes to that local server. Reuse authorized local QR service settings by an allowlisted process environment without printing credentials or copying the canonical environment/database. Commissioned simulation is local only; no real funds.

Require one exact 2025 New Classic replay: four Payment Orders totaling PHP 2,850, Treasury PHP 1,125, Assessment/Schedule/Collection/OR sum PHP 3,975, six receipts, four certifications, issuance then separate BPLO release, matching Citizen and signed-out public Permit identity. Check actual document viewing, canceled confirmation, reload persistence, desktop/mobile layout and reports. Record discrepancies; do not manufacture a pass or silently change fees.

## Deployment gate and rollback

Before asking for exact-SHA approval, record the scoped diff, all acceptance evidence, existing private upload/signature storage configuration, backup availability and prior successful deployment. No database reset, seed, new migration or fee-catalog import is planned. Preserve private storage. Rollback means redeploying the verified prior code release, not restoring over newly recorded transactions. Any necessary environment or migration expansion requires separate review. Cloud acceptance and the final guide must identify the actual verified deployed SHA.

This compass is not a release certificate. Update it after every gate and interruption; final acceptance remains open until Cloud verification and private guide delivery.
