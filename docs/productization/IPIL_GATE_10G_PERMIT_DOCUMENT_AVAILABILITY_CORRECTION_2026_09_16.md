# Gate 10G — Permit Document Availability Correction

Date: 2026-09-16
Starting SHA: `5fd8f2e8da56d4a103d8980c4e902a3ae0fa3c1f`
Scope: local synthetic Gate 10 review environment only. No Cloud/UAT/production operation.

## Disposition

The correction removes the premature claim that a permit document exists after lodging. Availability is now derived from the canonical `ProvisionalUatPermitCompletion.issued_at` evidence. Before issuance, the artifact is `not_issued`, `available=false`, has no PDF URL, and the server PDF route returns 404. After issuance, the issued synthetic document is available for authorized review. The verification reference remains a separate lookup/reference projection and, by itself, never creates a document.

## Acceptance record

1. **Root cause:** `DescribePermitReleaseReadiness` and `DescribePermitArtifact` hard-coded availability/status and a PDF URL; the staff PDF action rendered without an issuance guard. The citizen surface consequently exposed document-related affordances too early.
2. **Files changed:** `app/Actions/DescribePermitArtifact.php`; `app/Actions/DescribePermitReleaseReadiness.php`; `app/Http/Controllers/Staff/PermitApplicationController.php`; `resources/js/pages/citizen/permit-applications/Show.vue`; `resources/js/pages/permit-applications/Show.vue`; focused contract updates in `tests/Feature/CitizenPermitApplicationProcessingTest.php`, `tests/Feature/StaffPermitApplicationIntakeTest.php`, and new `tests/Feature/Gate10GPermitAvailabilityCorrectionTest.php`.
3. **Canonical rule:** issued evidence (`issued_at != null`) is the only source of `permit_artifact_available=true`.
4. **Lifecycle states:** draft, lodged, routed, assessment, payment, receipt, certification, and mayoral-authorization states do not expose an issued document; only the issued state does. Released remains separately represented.
5. **Staff/public behavior:** staff and citizen views gate document links on availability; direct staff PDF access fails closed until issuance. Public verification remains a reference/status surface, not a document-generation path.
6. **Failed specimen:** Application 1 was not resumed or mutated. No fresh browser specimen was created; synthetic state contracts are covered by focused tests.
7. **Verification:** `vendor/bin/pest --compact tests/Feature/CitizenPermitApplicationProcessingTest.php tests/Feature/StaffPermitApplicationIntakeTest.php tests/Feature/Gate10GPermitAvailabilityCorrectionTest.php` — 46 passed, 717 assertions. Gate 10G plus Gate 10D/E/F and walkthrough suites — 11 passed, 536 assertions.
8. **Static/build:** PHP formatter passed; TypeScript generation/check passed; Vite production build passed. Repository-wide ESLint and Prettier checks still report pre-existing findings in unrelated frontend test files/pages; no unrelated files were changed.
9. **Browser:** persistent `http://bpls-gate10.test/` remained reachable and prior desktop smoke checks had no browser console errors. The current browser adapter exposes no viewport-control capability, so an exact 390×844 run could not be honestly re-certified in this wave; no mobile PASS is claimed.
10. **Boundaries:** `/Users/rli/PhpstormProjects/bpls-runtime` was not touched; historical/rescue/report data was not touched; no deployment, Cloud operation, write-back, or Gate 10H work occurred.

## Recommendation

The product defect is corrected in the local candidate. A fresh turnover adjudication should exercise the full synthetic lifecycle and explicitly verify the pre-issuance, issuance, release, staff-PDF, citizen, and verification-reference states at desktop and 390×844 when a viewport-capable harness is available.

Final SHA: recorded by the commit containing this report and the bounded correction.

## Commission checklist (explicit)

1. Starting SHA: `5fd8f2e8da56d4a103d8980c4e902a3ae0fa3c1f`.
2. Final SHA: the commit containing this report.
3. Root cause: hard-coded generated availability and unguarded PDF rendering.
4. Changed files: the five production files and three focused test files listed above.
5. Availability rule: exact issuance evidence, never lodging alone.
6. Verification reference: independently queryable and non-documentary.
7. Draft: unavailable document.
8. Lodged: unavailable document.
9. Routed: unavailable document.
10. Assessment: unavailable document.
11. Payment: unavailable document.
12. Receipt: unavailable document.
13. Post-payment certification: unavailable document.
14. Mayoral authorization: still unavailable until issuance evidence exists.
15. Issued: document available for authorized synthetic review.
16. Released: separately represented; no legal effect is implied.
17. Staff access: PDF endpoint is fail-closed before issuance.
18. Public verification: reference/status only; no premature file.
19. Application 1: preserved and not resumed.
20. Fresh specimen: none created in this bounded correction.
21. Desktop: persistent-host smoke path previously passed.
22. 390×844: not re-certifiable because the available adapter lacks viewport control.
23. Console/network: no BPLS-originated browser errors observed in available smoke checks.
24. Focused tests: 46 tests / 717 assertions passed.
25. Gate 10 regressions: 11 tests / 536 assertions passed.
26. Static/type/build: Pint, TypeScript, and Vite build passed; unrelated repository lint/format findings remain.
27. Runtime checkout: `bpls-runtime` untouched.
28. Historical/report surfaces: untouched.
29. Deployment: none; local candidate only.
30. Re-adjudication: authorized next step is a fresh synthetic turnover walkthrough with mobile viewport proof.
