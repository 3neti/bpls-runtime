# Gate 9 Treasury enterprise determination — provisional UAT

Commission: Chief Architect, 2026-09-14. Baseline: `cc73b84dea59a386e27cd3fdb4098aabb44619d7`.

Treasury explicitly chooses Micro, Cottage, Small, Medium or Large. BPLS does not infer the choice from employees, area, business, occupancy, LOB or history. Configuration independently supplies the recovered New Business bands: PHP 200 / 500 / 1,000 / 1,500 / 2,000.

**PROVISIONAL MUNICIPAL POLICY — PENDING IPIL OFFICER CONFIRMATION.** This is a bounded UAT workflow hypothesis, not production ordinance activation or historical policy.

## Activation and transaction boundary

`config/treasury_enterprise.php` names the workflow UAT URL and schedule identity/version. The resolver additionally requires stakeholder preview mode, disabled production migration/integrations, commissioned New Application, year 2026, non-historical classification and the exact unresolved Mayor’s Permit fee identity. Other environments, historical records, Renewal, ordinary 2025 records and unrelated unresolved fees keep their existing boundary.

The existing authorized, application-locked Treasury confirmation action validates the explicit choice, current schedule fingerprint, inclusion and exact fee amount. Missing/invalid choice, missing/malformed schedule, stale fingerprint, omitted fee or arbitrary submitted amount fail closed. Existing office-finalization, fee ownership, New Business Tax exclusion and duplicate-confirmation guards remain in place. No migration, catalogue update or seed is part of this correction.

Existing immutable assignment and item snapshots record Application and LOB, officer-selected classification, actor/time, full schedule identity/version/fingerprint/bands/authority, resulting fee, canonical fee identity/version and false production-policy authority. Applicant evidence is not rewritten. Later schedule changes do not rewrite the recorded determination.

## Officer ceremony

Inbox / My Work → assigned Application → assign LOB → choose Enterprise Classification → review deterministic fee and subtotal → confirm once. Initially the Mayor’s Permit Fee and complete subtotal remain TBD; known items remain explicitly partial. The mapped Mayor’s Permit Fee is not editable/removable through the ordinary fee editor. Saved classification and provisional status are shown after reload.

Application 290 is reserved for the Testing Adjudicator/Testing Agent after deployment. Preserve POs 135–138 (PHP 3,050), zero Treasury assignments/Assessments/schedules/collections before their interaction. The commissioned Small test gives Treasury PHP 1,125 and combined components PHP 4,175 without Business Tax. No replacement Application is authorized for convenience.

## Verification and feedback

Focused/relevant PHP regression: 46 tests, 2,132 assertions passed. Focused frontend: nine tests passed. TypeScript, targeted PHPStan, lint, formatting and frontend build checked. Broader frontend has a pre-existing working-paper `.reduce` assertion failure reproduced at the baseline; not changed here. Whole-suite parallel discovery also encounters the existing non-compound `DomainException` import warning; selected complete regression files were run instead. Browser/remote acceptance remains a separate post-deployment gate, not implied by unit tests.

Both private Presenter and sanitized Ipil Officer guides were updated outside Git; private access details remain private. Record these answers as **POLICY FEEDBACK** only:

1. Is Treasury the correct determining office?
2. Are the five classification choices correct?
3. Officer selection or derivation from which application facts?
4. Are the five amounts correct for 2026 New Applications?
5. Which document, ordinance or schedule is operative?
6. Is a basis or remark required?

Do not activate verbal feedback as production policy. Stop the walkthrough at the first genuine product failure.
