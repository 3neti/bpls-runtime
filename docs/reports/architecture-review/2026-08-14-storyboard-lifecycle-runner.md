# Architecture Review Report: Storyboard and Lifecycle Scenario Runner

Date: 2026-08-14

Recommendation: CONTINUE WITH NOTED RISK

## Summary

The first implementation proof slice is complete: a Storyboard feature now exists, and an executable lifecycle scenario runner can prepare a deterministic Storyboard record, verify it through the real browser UI, compare browser evidence against authoritative application state, and preserve reviewable artifacts for a human reviewer.

This slice does not implement core BPLS permit, assessment, clearance, payment, or Treasury behavior. Its purpose is to establish the acceptance and verification mechanism that future BPLS vertical slices will use.

## What Changed

The project now has a working pattern for executable acceptance evidence:

1. A business-facing Storyboard describes an intended journey.
2. A terminal lifecycle scenario runner prepares deterministic state through real Laravel actions and application data.
3. A browser evidence runner authenticates through the real UI and opens the exact records named by the terminal manifest.
4. An audit phase reloads authoritative database state and compares it with browser evidence.
5. A portable artifact folder preserves JSON manifests, terminal reports, browser reports, screenshots, a Storyboard PDF, a Storyboard HTML view, and a reviewer note.

The important architectural lesson is that this can be done without creating a second workflow engine. The Storyboard and scenario runner are verification and communication infrastructure only. Business behavior remains owned by the domain/application layer.

## Implemented Proof Scenario

Scenario key: `storyboard_terminal_state_visibility`

Scenario label: Storyboard terminal export visibility

Run ID: `storyboard-terminal-20260814-001`

The scenario:

1. Resolves local scenario actors through the configured Laravel user model.
2. Verifies actor authorization through real permissions.
3. Creates a disposable Storyboard through the application action.
4. Renders and stores a PDF export.
5. Dispatches a video export job and records the pending export state.
6. Authenticates in the browser using runtime credentials.
7. Opens the Storyboard list and exact Storyboard detail route from the manifest.
8. Verifies desktop and mobile UI state.
9. Audits database state, export state, and browser evidence.
10. Writes persistent artifacts under Laravel storage.

Authoritative resource produced:

- Record type: `storyboard`
- Record ID: `1`
- Public reference: `STORYBOARD-1`
- PDF export ID: `1`
- Video export ID: `2`

Artifact root:

`storage/app/private/lifecycle-scenarios/storyboard_terminal_state_visibility/storyboard-terminal-20260814-001`

## Architectural Impact

This establishes a repeatable vertical-slice verification pattern:

`Business Story -> Executable Scenario -> Browser Verification -> Audit Evidence`

The pattern should be used for future BPLS capabilities such as new permit application, renewal, assessment, clearance completion, Treasury collection, and permit issuance.

The runner intentionally consumes production application actions, policies, routes, storage, exports, and database state. It should not duplicate business rules. When permit, assessment, payment, or Treasury behavior is implemented, the runner must call those same domain/application operations rather than re-implementing workflow transitions.

The artifact manifest is versioned and deterministic. Browser verification reads the terminal manifest and opens the exact record produced by the terminal phase. It does not search for the newest record or create a parallel record.

## Verification Evidence

Checks completed:

- `vendor/bin/pint --dirty --format agent`: passed
- `php artisan test --compact tests/Feature/LifecycleScenarioRunnerTest.php tests/Feature/StaffStoryboardTest.php`: 12 tests passed, 75 assertions
- `php artisan test --compact`: 102 tests passed, 601 assertions
- `npm run types:check`: passed
- `npm run build`: passed
- `git diff --check`: passed
- Scenario audit: terminal passed, browser passed, audit passed

Generated reviewer artifacts:

- `manifest.json`
- `summary.html`
- `review.md`
- `storyboard/storyboard.html`
- `storyboard/storyboard.pdf`
- `terminal/prepare.json`
- `terminal/execution.json`
- `terminal/audit.json`
- `browser/report.json`
- `browser/screenshots/01-list.png`
- `browser/screenshots/02-detail.png`
- `browser/screenshots/03-mobile-detail.png`

No discrepancy was found between authoritative Storyboard state and visible browser UI for this proof scenario.

## Risks

The primary risk is conceptual drift: Storyboards and lifecycle scenarios could accidentally become a second place where workflow behavior is defined.

This must not happen. The domain/application layer remains the single source of business truth. Storyboards describe the business journey. Lifecycle scenarios orchestrate that journey through real application behavior. Browser verification proves the UI accurately reflects the result.

There is also an environment risk around browser execution. Playwright is now installed as a development dependency because the repository had no existing Dusk, Cypress, Pest Browser, or Playwright setup. On the current macOS/Herd environment, browser execution required a normal browser launch context; credentials were supplied through runtime environment variables and were not committed or written to artifacts.

Video export remains an explicit environment boundary. The proof scenario verifies job dispatch and pending UI state. Actual video rendering depends on FFmpeg availability in the worker/runtime environment and should be verified separately when video generation becomes a production requirement.

## Recommendation

CONTINUE WITH NOTED RISK

Continue implementation using this verification pattern, but keep the Storyboard and Lifecycle Scenario Runner limited to acceptance evidence and demonstration. The next real BPLS vertical slice should prove this boundary by having the scenario runner execute genuine BPLS domain actions rather than embedding lifecycle logic in the runner.

Recommended next implementation slice:

Create the first narrow BPLS domain vertical slice for staff-facing permit application intake/status visibility, with:

- domain/application action ownership of the business transition;
- Storyboard written in business language;
- lifecycle runner executing the real action;
- browser verification against exact manifest records;
- audit comparison of authoritative state and visible UI;
- parity ledger update.

## Coffee with Arti

When BPLS capabilities mature, should Storyboards become municipality-facing acceptance artifacts that officials can review and approve, or should they remain primarily internal engineering evidence?

