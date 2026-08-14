<?php

namespace App\LifecycleScenarios;

use App\Actions\CreatePermitApplication;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use RuntimeException;

final class CitizenPermitDraftVisibilityScenario
{
    public function __construct(
        private readonly CreatePermitApplication $createPermitApplication,
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

        $applicant = $actors['applicant'] ?? throw new RuntimeException('Scenario citizen applicant actor was not resolved.');
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $retail = LineOfBusiness::query()->firstOrCreate(
            ['code' => 'SCENARIO-CITIZEN-RETAIL'],
            [
                'name' => 'Scenario Citizen Retail',
                'major_category' => 'Retail',
                'is_active' => true,
            ],
        );
        $services = LineOfBusiness::query()->firstOrCreate(
            ['code' => 'SCENARIO-CITIZEN-SERVICES'],
            [
                'name' => 'Scenario Citizen Services',
                'major_category' => 'Services',
                'is_active' => true,
            ],
        );
        $businessName = 'Citizen Scenario Business '.$runId;
        $activities = [
            [
                'line_of_business_id' => $retail->id,
                'code' => $retail->code,
                'name' => $retail->name,
                'declared_gross_sales_cents' => 12_500_050,
                'capital_investment_cents' => 7_500_025,
                'quantity' => 1,
                'started_on' => '2020-01-15',
            ],
            [
                'line_of_business_id' => $services->id,
                'code' => $services->code,
                'name' => $services->name,
                'declared_gross_sales_cents' => 4_500_075,
                'capital_investment_cents' => 1_500_050,
                'quantity' => 2,
                'started_on' => '2021-06-01',
            ],
        ];

        $permitApplication = $this->createPermitApplication->handle([
            'owner_name' => $applicant->name,
            'owner_email' => $applicant->email,
            'owner_phone' => '09170000000',
            'owner_address' => 'Scenario citizen address',
            'business_name' => $businessName,
            'trade_name' => 'Citizen Scenario Trade',
            'business_address' => 'Scenario citizen business address',
            'barangay' => 'Poblacion',
            'application_number' => null,
            'type' => PermitApplicationType::New->value,
            'application_year' => now()->year,
            'lines' => collect($activities)
                ->map(fn (array $activity): array => collect($activity)->except(['code', 'name'])->all())
                ->all(),
        ], $applicant);

        $steps = [
            $this->step('citizen-resolved', 'Resolve actual citizen applicant', ['applicant_id' => $applicant->id], ['applicant_id' => $applicant->id]),
            $this->step('citizen-draft-created', 'Create citizen-owned permit draft through canonical application action', [
                'status' => PermitApplicationStatus::Draft->value,
                'application_number' => null,
                'activity_count' => 2,
            ], [
                'status' => $permitApplication->status->value,
                'application_number' => $permitApplication->application_number,
                'activity_count' => $permitApplication->lines->count(),
            ]),
            $this->step('citizen-draft-boundary-preserved', 'Keep assessment and official numbering outside citizen draft creation', [
                'assessment_count' => 0,
                'submitted_by_id' => $applicant->id,
            ], [
                'assessment_count' => $permitApplication->assessments()->count(),
                'submitted_by_id' => $permitApplication->submitted_by_id,
            ]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'permit_application',
            'record_id' => $permitApplication->id,
            'public_reference' => 'Draft #'.$permitApplication->id,
            'business_name' => $businessName,
            'list_url' => route('citizen.permit-applications.index', absolute: false),
            'create_url' => route('citizen.permit-applications.create', absolute: false),
            'detail_url' => route('citizen.permit-applications.show', $permitApplication, false),
            'business_activities' => collect($activities)
                ->map(fn (array $activity): array => collect($activity)->except('line_of_business_id')->all())
                ->all(),
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = ['root' => '.'];

        $artifactStore->putJson('terminal/prepare.json', [
            'permit_application_id' => $permitApplication->id,
            'status' => $permitApplication->status->value,
            'submitted_by_id' => $permitApplication->submitted_by_id,
            'business_activities' => $manifest['resources']['business_activities'],
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
            ->with(['lines.lineOfBusiness'])
            ->withCount('assessments')
            ->findOrFail($manifest['resources']['record_id']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? ['result' => ['passed' => false]];
        $canonicalActivities = $permitApplication->lines->map(fn ($line): array => [
            'code' => $line->lineOfBusiness?->code,
            'name' => $line->lineOfBusiness?->name,
            'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
            'capital_investment_cents' => $line->capital_investment_cents,
            'quantity' => $line->quantity,
            'started_on' => $line->started_on?->toDateString(),
        ])->values()->all();

        $checks = [
            $this->step('audit-citizen-ownership', 'Canonical draft belongs to manifest citizen', ['submitted_by_id' => data_get($manifest, 'actors.applicant.id')], ['submitted_by_id' => $permitApplication->submitted_by_id]),
            $this->step('audit-draft-state', 'Canonical application remains an unnumbered unassessed draft', [
                'status' => PermitApplicationStatus::Draft->value,
                'application_number' => null,
                'assessment_count' => 0,
            ], [
                'status' => $permitApplication->status->value,
                'application_number' => $permitApplication->application_number,
                'assessment_count' => $permitApplication->assessments_count,
            ]),
            $this->step('audit-business-activities', 'Canonical activities match manifest activities', ['activities' => $manifest['resources']['business_activities']], ['activities' => $canonicalActivities]),
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
        ];
        $passed = collect($checks)->every(fn (array $check): bool => $check['passed']);

        $manifest['steps'] = [...($manifest['steps'] ?? []), ...$checks];
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
                'submitted_by_id' => $permitApplication->submitted_by_id,
                'status' => $permitApplication->status->value,
                'application_number' => $permitApplication->application_number,
                'assessment_count' => $permitApplication->assessments_count,
                'business_activities' => $canonicalActivities,
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
            'actor' => 'applicant',
            'action' => $action,
            'expected' => $expected,
            'actual' => $actual,
            'passed' => $expected === array_intersect_key($actual, $expected),
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
            'title' => 'Citizen saves a new permit application draft',
            'summary' => 'A citizen records business and activity facts, then reviews the exact saved draft without starting assessment or receiving an official application number.',
            'run_id' => $runId,
            'record' => [
                'type' => 'permit_application',
                'id' => $permitApplication->id,
                'reference' => 'Draft #'.$permitApplication->id,
            ],
            'frames' => [
                [
                    'title' => 'Citizen starts a draft',
                    'description' => 'The applicant opens the citizen intake and sees the explicit draft boundary.',
                    'dialogue' => 'Saving does not submit the application for assessment.',
                    'duration_seconds' => 4,
                ],
                [
                    'title' => 'Business activities are recorded',
                    'description' => 'The canonical permit creation action persists two ordered business activities.',
                    'dialogue' => 'Declared facts are saved without calculating fees.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Citizen reviews the exact draft',
                    'description' => 'Citizen list and detail screens show only the manifest-bound application.',
                    'dialogue' => 'Canonical state and visible state agree that the record is an unnumbered draft.',
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

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($storyboard['title']).'</title></head><body><h1>'.e($storyboard['title']).'</h1><p>'.e($storyboard['summary']).'</p><p>Run ID: '.e($runId).'</p><p>Draft #'.e((string) $permitApplication->id).'</p><ol>'.$frames.'</ol></body></html>';
    }
}
