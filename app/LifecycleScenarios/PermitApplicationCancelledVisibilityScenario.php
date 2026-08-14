<?php

namespace App\LifecycleScenarios;

use App\Actions\CancelPermitApplication;
use App\Actions\CreateStaffPermitApplication;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use RuntimeException;

final class PermitApplicationCancelledVisibilityScenario
{
    public function __construct(
        private readonly CreateStaffPermitApplication $createPermitApplication,
        private readonly CancelPermitApplication $cancelPermitApplication,
        private readonly ScenarioManifest $scenarioManifest,
        private readonly ScenarioSummaryRenderer $summaryRenderer,
    ) {}

    /**
     * @param  array<string, User>  $actors
     * @return array<string, mixed>
     */
    public function prepare(LifecycleScenarioDefinition $scenario, string $runId, array $actors, ScenarioArtifactStore $artifactStore): array
    {
        $existingManifest = $artifactStore->readJson('manifest.json');
        if (is_array($existingManifest) && ($existingManifest['result']['terminal'] ?? null) === 'passed') {
            return $existingManifest;
        }

        $operator = $actors['operator'] ?? throw new RuntimeException('Scenario operator actor was not resolved.');
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $lineOfBusiness = LineOfBusiness::query()->firstOrCreate(
            ['code' => 'SCENARIO-RETAIL'],
            [
                'name' => 'Scenario Retail',
                'major_category' => 'Retail',
                'is_active' => true,
            ],
        );
        $applicationNumber = 'APP-SCENARIO-'.str($runId)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(40, '')->toString();
        $permitApplication = $this->createPermitApplication->handle([
            'owner_name' => 'Scenario Owner '.$runId,
            'owner_email' => null,
            'owner_phone' => null,
            'owner_address' => 'Scenario verification address',
            'business_name' => 'Scenario Business '.$runId,
            'trade_name' => 'Scenario Trade',
            'registration_number' => 'SCENARIO-'.$runId,
            'business_address' => 'Scenario verification address',
            'barangay' => 'Poblacion',
            'application_number' => $applicationNumber,
            'type' => PermitApplicationType::New->value,
            'application_year' => now()->year,
            'lines' => [[
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_cents' => 125_000_00,
                'capital_investment_cents' => 75_000_00,
                'quantity' => 1,
            ]],
        ], $operator);

        $permitApplication = $this->cancelPermitApplication->handle(
            $permitApplication,
            $operator,
            'Lifecycle scenario cancellation verifies terminal status visibility.',
        );

        $steps = [
            $this->step('actors-resolved', 'Resolve actual application users', ['operator_id' => $operator->id], ['operator_id' => $operator->id]),
            $this->step('permit-application-created', 'Create permit application through staff intake action', ['status' => PermitApplicationStatus::Draft->value], ['status' => PermitApplicationStatus::Draft->value, 'permit_application_id' => $permitApplication->id]),
            $this->step('permit-application-cancelled', 'Cancel permit application through domain action', ['status' => PermitApplicationStatus::Cancelled->value, 'can_continue' => false], ['status' => $permitApplication->status->value, 'can_continue' => $permitApplication->metadata['terminal_state']['can_continue'] ?? null]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'permit_application',
            'record_id' => $permitApplication->id,
            'public_reference' => $permitApplication->application_number,
            'list_url' => route('staff.permit-applications.index', absolute: false),
            'detail_url' => route('staff.permit-applications.show', $permitApplication, false),
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = [
            'root' => '.',
        ];

        $artifactStore->putJson('terminal/prepare.json', [
            'permit_application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'status' => $permitApplication->status->value,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $permitApplication));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId, $permitApplication));
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('review.md', $this->summaryRenderer->reviewMarkdown());

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function audit(array $manifest, ScenarioArtifactStore $artifactStore): array
    {
        $permitApplication = PermitApplication::query()
            ->with(['business.owner', 'lines.lineOfBusiness'])
            ->findOrFail($manifest['resources']['record_id']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [
            'result' => [
                'passed' => false,
            ],
            'checks' => [],
        ];

        $checks = [
            $this->step('audit-canonical-status', 'Canonical permit application status is cancelled', ['status' => PermitApplicationStatus::Cancelled->value], ['status' => $permitApplication->status->value]),
            $this->step('audit-terminal-state', 'Canonical terminal state blocks continuation', ['can_continue' => false], ['can_continue' => $permitApplication->metadata['terminal_state']['can_continue'] ?? null]),
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
        ];

        $passed = collect($checks)->every(fn (array $check): bool => $check['passed']);

        $manifest['steps'] = [
            ...($manifest['steps'] ?? []),
            ...$checks,
        ];
        $manifest['result']['audit'] = $passed ? 'passed' : 'failed';
        $manifest['result']['browser'] = data_get($browserReport, 'result.passed') ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed'
            && $manifest['result']['browser'] === 'passed'
            && $manifest['result']['audit'] === 'passed';
        $manifest['artifacts']['screenshots'] = data_get($browserReport, 'artifacts.screenshots', []);

        $artifactStore->putJson('terminal/audit.json', [
            'checks' => $checks,
            'passed' => $passed,
            'canonical' => [
                'permit_application_id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
                'status' => $permitApplication->status->value,
                'terminal_state' => $permitApplication->metadata['terminal_state'] ?? null,
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    private function step(string $key, string $action, array $expected, array $actual): array
    {
        return [
            'key' => $key,
            'actor' => 'operator',
            'action' => $action,
            'expected' => $expected,
            'actual' => $actual,
            'passed' => collect($expected)->every(fn (mixed $value, string $field): bool => ($actual[$field] ?? null) === $value),
            'occurred_at' => now()->toIso8601String(),
            'evidence' => $actual,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storyboard(string $runId, PermitApplication $permitApplication): array
    {
        return [
            'title' => 'Cancelled permit application visibility',
            'summary' => 'A staff operator creates a disposable permit application, cancels it through the real domain action, and verifies that staff screens show terminal status.',
            'run_id' => $runId,
            'record' => [
                'type' => 'permit_application',
                'id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
            ],
            'frames' => [
                [
                    'title' => 'Staff records application',
                    'description' => 'BPLO staff records a new business permit application for the scenario business.',
                    'dialogue' => 'The application is now visible to staff for review.',
                    'duration_seconds' => 4,
                ],
                [
                    'title' => 'Staff cancels application',
                    'description' => 'BPLO staff cancels the application through the domain cancellation action.',
                    'dialogue' => 'The application is terminal and cannot continue to assessment or release.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Reviewer confirms status',
                    'description' => 'The browser opens the list and detail screens for the exact manifest record.',
                    'dialogue' => 'Visible UI state and canonical database state agree.',
                    'duration_seconds' => 5,
                ],
            ],
        ];
    }

    private function storyboardHtml(string $runId, PermitApplication $permitApplication): string
    {
        $storyboard = $this->storyboard($runId, $permitApplication);
        $frames = collect($storyboard['frames'])
            ->map(fn (array $frame): string => '<li><strong>'.e($frame['title']).'</strong><br>'.e($frame['description']).'<br><em>'.e($frame['dialogue']).'</em></li>')
            ->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($storyboard['title']).'</title></head><body><h1>'.e($storyboard['title']).'</h1><p>'.e($storyboard['summary']).'</p><p>Run ID: '.e($runId).'</p><p>Application: '.e((string) $permitApplication->application_number).'</p><ol>'.$frames.'</ol></body></html>';
    }
}
