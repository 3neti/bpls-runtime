# Parity Ledger

This ledger tracks Laravel replacement status against the Discovery Capability Ledger. Discovery evidence remains in `docs/discovery/CAPABILITY_LEDGER.md`; this file records implementation and verification state only.

Status vocabulary:

- `NOT STARTED`
- `BACKEND PARTIAL`
- `UI PARTIAL`
- `IMPLEMENTED`
- `TESTED`
- `BROWSER VERIFIED`
- `BLOCKED`

| Capability ID | Capability | Laravel status | Automated evidence | Browser evidence | Known gap / condition | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| CAP-010 | New business permit application | BACKEND PARTIAL | `tests/Feature/AssessmentSnapshotTest.php` | NOT STARTED | No staff/citizen UI yet; application creation flow not implemented. | Registry/application persistence exists for assessment slice. |
| CAP-017 | Application assessment queue | UI PARTIAL | `tests/Feature/AssessmentSnapshotTest.php`; `tests/Feature/StaffPermitApplicationAssessmentTest.php` | Herd browser verified `http://bpls-runtime.test/staff/permit-applications/assessments` with local fixture `APP-LOCAL-VERIFY` on 2026-08-13. | Authorization is only authenticated-user gating; legacy role parity not wired. | Staff index can compute and open assessment snapshots. |
| CAP-026 | Line of business capture | BACKEND PARTIAL | `tests/Feature/AssessmentSnapshotTest.php` | NOT STARTED | No field-level UI parity yet. | Application lines can reference lines of business and monetary bases. |
| CAP-029 | Constant fee calculation | BROWSER VERIFIED | `tests/Feature/AssessmentSnapshotTest.php`; `tests/Feature/StaffPermitApplicationAssessmentTest.php` | Herd browser verified `Local Permit Fee` assessed at `PHP 300.00` on 2026-08-13. | Needs ordinance-derived seeded fee catalog. | Fixed fee rules produce immutable assessment lines and render in staff review UI. |
| CAP-030 | Range-based fee calculation | TESTED | `tests/Feature/AssessmentSnapshotTest.php` | NOT STARTED | Rate/rounding policy remains unresolved. | Bracketed fixed-amount ranges are supported. |
| CAP-031 | Formula-based fee calculation | BLOCKED | `tests/Feature/AssessmentSnapshotTest.php` | NOT STARTED | Formula semantics and rounding policy require characterization/decision. | Formula rules throw explicit `UnsupportedAssessmentPolicy`. |
| CAP-035 | Business tax based on gross sales/receipts | BACKEND PARTIAL | `tests/Feature/AssessmentSnapshotTest.php` | NOT STARTED | Full ordinance rate extraction and test cases still required. | Gross-sales basis can drive bracketed rules. |
| CAP-116 | Revenue Code full fee catalog | NOT STARTED | NOT STARTED | NOT STARTED | Requires full ordinance fee/rate extraction and production configuration reconciliation. | Current slice provides storage/calculation structure only. |
