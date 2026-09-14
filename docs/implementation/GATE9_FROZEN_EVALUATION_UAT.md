# Gate 9 — Prospective financial Evaluation binding

Base: `baec67adb16787c96565ddcf861b0bdc7f66c751`.

## Canonical boundary

Finalized concerned-office Payment Orders and the authorized Treasury confirmation produce the first complete financial state. In the same transaction as Treasury assignment, `FreezeTreasuryFinancialEvaluation` creates an Evaluation/version (or appends the financial version to an existing pre-financial Evaluation). No Assessment may already exist. This is not a repair or backfill mechanism.

The version freezes the existing `AssessmentPriceInput` and `PriceReport`, source PO identities, Treasury assignment identities, enterprise determination/schedule provenance, actor and timestamp in version metadata under `bpls.frozen-financial-evaluation.v1`. These are immutable evidence, not a new calculator or new municipal policy.

The canonical financial digest covers that snapshot, with only its self-reference at `input.assessment_context.evaluation_fingerprint` normalized to null. The actual stored input contains the resulting fingerprint and exact version ID. This explicitly avoids a circular hash. Input/report fingerprints remain independently verifiable on Assessment.

Assessment creation consumes the frozen input, verifies that the existing Price engine produces the identical frozen report, and persists its exact version/fingerprint. It does not resolve current fee/PO/Treasury state again. The model rejects wrong-Application, missing, mismatched-amount or mismatched-fingerprint creation on the commissioned path, and rejects later binding changes. Frozen version updates/regeneration are prohibited.

Counter-check takes an Assessment, resolves its bound version, and verifies the frozen artifacts. It never selects a latest version or reruns Price. The visible form submits the displayed Assessment identity and expected version/fingerprint. Permission checks exist at both HTTP and domain boundaries; duplicate identical checks retain one authoritative result. Treasurer approval requires a matching no-correction counter-check.

`AssessmentCounterCheckReadiness` supplies the Assessment page, municipal Inbox, executable-Application task projection and Treasurer decision guard. Missing provenance is explicitly incomplete, never an implied bypass to approval. No migration, seed, retroactive binding or historical/report changes are included.

## Retained failure specimen

Application 290 and Assessment 214 are not repairable: no Evaluation existed. Preserve them, along with Applications 285–289. Assessment 214 remains 417,500 minor units, hash `13d32b0920a99fc72ffe35e0f0f8ec51f4b22ad7dd5807ce587edfefe865abaa`.

Pre-deployment read-only preservation command: `comm-a2be00c2-684e-4824-ba92-3760956f0c7f`. All six Application preservation hashes match the accepted stop; Evaluation count for Application 290 remains zero.

## Verification and handoff

Focused tests cover Treasury-created frozen Evaluation, exact 417,500-minor-unit parity, immutable reload/binding, actor authorization, actual counter-check request, Inbox membership, one authoritative result, approval sequencing, mismatched identity/amount/fingerprint rejection, retained incomplete evidence, and stability despite later changes to current inputs. Older synthetic fixtures now supply authorized actors and the mandatory counter-check instead of bypassing it or attaching a version retrospectively.

Known baseline findings: seven PHPStan findings in `PermitApplicationAssessmentController` reproduce on the pre-correction release worktree; the frontend working-paper whole-file `.reduce(` assertion also predates this correction. No baseline suppression or unrelated cleanup is included.

Only after the bounded deployment and preservation verification may the Testing Adjudicator create one fresh ordinary UAT specimen. Application 290 must not resume. Require Evaluation = PriceReport = Assessment = Payment Schedule = Collection = sum of all ORs = PHP 4,175.00. Small and the five-band Mayor fee schedule remain provisional UAT policy pending municipal confirmation. Stop at the first genuine product failure; do not start Report R2-016.
