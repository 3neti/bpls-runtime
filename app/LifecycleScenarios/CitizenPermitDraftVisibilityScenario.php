<?php

namespace App\LifecycleScenarios;

use App\Actions\CreateCitizenPermitApplicationDraft;
use App\Actions\SimplePdfDocument;
use App\Enums\PermitApplicationStatus;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CitizenPermitDraftVisibilityScenario
{
    public function __construct(
        private readonly CreateCitizenPermitApplicationDraft $createPermitApplicationDraft,
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

        $permitApplication = $this->createPermitApplicationDraft->handle([
            'owner_name' => $applicant->name,
            'owner_email' => $applicant->email,
            'owner_phone' => '09170000000',
            'owner_address' => 'Scenario citizen address',
            'business_name' => $businessName,
            'trade_name' => 'Citizen Scenario Trade',
            'business_address' => 'Scenario citizen business address',
            'barangay' => 'Poblacion',
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
            'post_submission_reference' => 'Application record #'.$permitApplication->id,
            'business_name' => $businessName,
            'list_url' => route('citizen.permit-applications.index', absolute: false),
            'create_url' => route('citizen.permit-applications.create', absolute: false),
            'edit_url' => route('citizen.permit-applications.edit', $permitApplication, false),
            'detail_url' => route('citizen.permit-applications.show', $permitApplication, false),
            'business_activities' => collect($activities)
                ->map(fn (array $activity): array => collect($activity)->except('line_of_business_id')->all())
                ->all(),
        ];

        if ($scenario->key === 'citizen_permit_draft_edit_visibility') {
            $manifest['resources']['expected_edit'] = [
                'business_name' => $businessName,
                'owner_phone' => $permitApplication->business->owner->phone,
                'registry_facts_read_only' => true,
                'business_activities' => [
                    [
                        'code' => $retail->code,
                        'name' => $retail->name,
                        'declared_gross_sales_cents' => 13_000_050,
                        'capital_investment_cents' => 7_500_025,
                        'quantity' => 4,
                        'started_on' => '2020-01-15',
                    ],
                    [
                        'code' => $services->code,
                        'name' => $services->name,
                        'declared_gross_sales_cents' => 4_500_075,
                        'capital_investment_cents' => 1_750_050,
                        'quantity' => 3,
                        'started_on' => '2021-06-01',
                    ],
                ],
            ];
        }

        if ($scenario->key === 'citizen_permit_submission_visibility') {
            $manifest['resources']['expected_submission'] = [
                'status' => PermitApplicationStatus::Assessment->value,
                'application_number' => null,
                'assessment_count' => 0,
                'payment_schedule_count' => 0,
                'browser_performs_submission' => true,
            ];
        }

        if ($scenario->key === 'citizen_permit_draft_document_visibility') {
            $fixturePath = $this->createDocumentFixture($artifactStore, $runId);
            $manifest['resources']['expected_document'] = [
                'label' => 'Business registration evidence',
                'original_name' => 'citizen-business-registration.pdf',
                'remarks' => 'Citizen lifecycle scenario evidence.',
                'fixture_path' => $fixturePath,
                'submitted_via' => 'citizen_portal',
                'requirement_catalog_status' => 'unresolved',
                'submission_readiness' => 'not_determined',
            ];
        }
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
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $permitApplication, $scenario->key));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId, $permitApplication, $scenario->key));
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
            ->with(['business.owner', 'documents', 'lines.lineOfBusiness'])
            ->withCount('assessments')
            ->findOrFail($manifest['resources']['record_id']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? ['result' => ['passed' => false]];

        if (($manifest['scenario']['key'] ?? null) === 'citizen_permit_submission_visibility') {
            return $this->auditSubmission($manifest, $permitApplication, $browserReport, $artifactStore);
        }
        $canonicalActivities = $permitApplication->lines->map(fn ($line): array => [
            'code' => $line->lineOfBusiness?->code,
            'name' => $line->lineOfBusiness?->name,
            'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
            'capital_investment_cents' => $line->capital_investment_cents,
            'quantity' => $line->quantity,
            'started_on' => $line->started_on?->toDateString(),
        ])->values()->all();
        $canonicalBrowserActivities = collect($canonicalActivities)
            ->map(fn (array $activity): array => collect($activity)->except('name')->all())
            ->all();

        $expectedEdit = data_get($manifest, 'resources.expected_edit');
        $expectedDocument = data_get($manifest, 'resources.expected_document');
        $expectedActivities = is_array($expectedEdit)
            ? $expectedEdit['business_activities']
            : $manifest['resources']['business_activities'];
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
            $this->step('audit-business-activities', 'Canonical activities match manifest activities', ['activities' => $expectedActivities], ['activities' => $canonicalActivities]),
            $this->step('audit-browser-draft-state', 'Browser draft state matches canonical state', [
                'status' => $permitApplication->status->value,
                'activities' => $canonicalBrowserActivities,
            ], [
                'status' => data_get($browserReport, 'citizen_draft.status'),
                'activities' => data_get($browserReport, 'citizen_draft.business_activities'),
            ]),
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
        ];

        if (is_array($expectedEdit)) {
            $checks[] = $this->step('audit-edited-draft-facts', 'Canonical owner and business registry facts remain unchanged by the browser edit', [
                'business_name' => $expectedEdit['business_name'],
                'owner_phone' => $expectedEdit['owner_phone'],
            ], [
                'business_name' => $permitApplication->business->name,
                'owner_phone' => $permitApplication->business->owner->phone,
            ]);
            $checks[] = $this->step('audit-browser-edited-draft-facts', 'Browser reports immutable registry facts and application-specific edits', [
                'business_name' => $permitApplication->business->name,
                'owner_phone' => $permitApplication->business->owner->phone,
                'edit_performed_by_browser' => true,
                'registry_facts_read_only' => true,
            ], [
                'business_name' => data_get($browserReport, 'citizen_draft.business_name'),
                'owner_phone' => data_get($browserReport, 'citizen_draft.owner_phone'),
                'edit_performed_by_browser' => data_get($browserReport, 'citizen_draft.edit_performed_by_browser'),
                'registry_facts_read_only' => data_get($browserReport, 'citizen_draft.registry_facts_read_only'),
            ]);
        }

        if (is_array($expectedDocument)) {
            $document = $permitApplication->documents->first();
            $checks[] = $this->step('audit-citizen-supporting-document', 'Canonical private document matches the browser upload contract', [
                'document_count' => 1,
                'label' => $expectedDocument['label'],
                'original_name' => $expectedDocument['original_name'],
                'submitted_via' => $expectedDocument['submitted_via'],
                'stored_privately' => true,
            ], [
                'document_count' => $permitApplication->documents->count(),
                'label' => $document?->label,
                'original_name' => $document?->original_name,
                'submitted_via' => $document?->source_snapshot['submitted_via'] ?? null,
                'stored_privately' => $document !== null
                    && $document->storage_disk === 'local'
                    && Storage::disk('local')->exists($document->path),
            ]);
            $checks[] = $this->step('audit-browser-citizen-supporting-document', 'Browser evidence matches the canonical private document and readiness boundary', [
                'document_id' => $document?->id,
                'label' => $document?->label,
                'download_available' => true,
                'submission_readiness' => 'not_determined',
                'document_upload_performed_by_browser' => true,
            ], [
                'document_id' => data_get($browserReport, 'citizen_draft.supporting_document.id'),
                'label' => data_get($browserReport, 'citizen_draft.supporting_document.label'),
                'download_available' => data_get($browserReport, 'citizen_draft.supporting_document.download_available'),
                'submission_readiness' => data_get($browserReport, 'citizen_draft.documentary_readiness.submission_readiness'),
                'document_upload_performed_by_browser' => data_get($browserReport, 'citizen_draft.document_upload_performed_by_browser'),
            ]);
        }
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
                'business_name' => $permitApplication->business->name,
                'owner_phone' => $permitApplication->business->owner->phone,
                'business_activities' => $canonicalActivities,
                'documents' => $permitApplication->documents->map(fn ($document): array => [
                    'id' => $document->id,
                    'label' => $document->label,
                    'original_name' => $document->original_name,
                    'submitted_via' => $document->source_snapshot['submitted_via'] ?? null,
                    'stored_privately' => $document->storage_disk === 'local'
                        && Storage::disk('local')->exists($document->path),
                ])->values(),
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $browserReport
     * @return array<string, mixed>
     */
    private function auditSubmission(array $manifest, PermitApplication $permitApplication, array $browserReport, ScenarioArtifactStore $artifactStore): array
    {
        $checks = [
            $this->step('audit-citizen-submitted', 'Canonical application records the citizen submission fact', [
                'status' => PermitApplicationStatus::Assessment->value,
                'submitted' => true,
                'actor_id' => data_get($manifest, 'actors.applicant.id'),
            ], [
                'status' => $permitApplication->status->value,
                'submitted' => data_get($permitApplication->metadata, 'citizen_submission.submitted_at') !== null,
                'actor_id' => data_get($permitApplication->metadata, 'citizen_submission.actor_id'),
            ]),
            $this->step('audit-municipality-received', 'Canonical application records municipal receipt into the processing queue', [
                'received' => true,
                'processing_status' => PermitApplicationStatus::Assessment->value,
            ], [
                'received' => data_get($permitApplication->metadata, 'municipal_receipt.received_at') !== null,
                'processing_status' => data_get($permitApplication->metadata, 'municipal_receipt.processing_status'),
            ]),
            $this->step('audit-submission-policy-seams', 'Submission leaves unresolved policy and downstream financial behavior untouched', [
                'application_number' => null,
                'assessment_count' => 0,
                'payment_schedule_count' => 0,
                'documentary_sufficiency_determined' => false,
                'payment_mode_committed' => false,
            ], [
                'application_number' => $permitApplication->application_number,
                'assessment_count' => $permitApplication->assessments()->count(),
                'payment_schedule_count' => $permitApplication->paymentSchedules()->count(),
                'documentary_sufficiency_determined' => (bool) data_get($permitApplication->metadata, 'submission_policy_boundary.documentary_sufficiency_determined'),
                'payment_mode_committed' => (bool) data_get($permitApplication->metadata, 'submission_policy_boundary.payment_mode_committed'),
            ]),
            $this->step('audit-browser-submission', 'Browser UI agrees with the canonical submission and receipt facts', [
                'status' => $permitApplication->status->value,
                'citizen_submitted' => true,
                'municipality_received' => true,
                'submit_action_available' => false,
                'edit_action_available' => false,
            ], [
                'status' => data_get($browserReport, 'citizen_submission.status'),
                'citizen_submitted' => data_get($browserReport, 'citizen_submission.citizen_submitted'),
                'municipality_received' => data_get($browserReport, 'citizen_submission.municipality_received'),
                'submit_action_available' => data_get($browserReport, 'citizen_submission.submit_action_available'),
                'edit_action_available' => data_get($browserReport, 'citizen_submission.edit_action_available'),
            ]),
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
                'status' => $permitApplication->status->value,
                'application_number' => $permitApplication->application_number,
                'submitted_at' => $permitApplication->submitted_at?->toIso8601String(),
                'citizen_submission' => data_get($permitApplication->metadata, 'citizen_submission'),
                'municipal_receipt' => data_get($permitApplication->metadata, 'municipal_receipt'),
                'assessment_count' => $permitApplication->assessments()->count(),
                'payment_schedule_count' => $permitApplication->paymentSchedules()->count(),
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
    private function storyboard(string $runId, PermitApplication $permitApplication, string $scenarioKey): array
    {
        if ($scenarioKey === 'citizen_permit_submission_visibility') {
            return [
                'title' => 'Citizen formally submits a new permit application',
                'summary' => 'A citizen submits the exact saved draft through the real portal; the municipality receives it into the processing queue without assigning an official number or triggering financial behavior.',
                'run_id' => $runId,
                'record' => [
                    'type' => 'permit_application',
                    'id' => $permitApplication->id,
                    'reference' => 'Draft #'.$permitApplication->id,
                ],
                'frames' => [
                    [
                        'title' => 'Citizen reviews the saved draft',
                        'description' => 'The draft remains unnumbered, editable, and outside municipal processing.',
                        'dialogue' => 'Submission is a separate citizen action.',
                        'duration_seconds' => 4,
                    ],
                    [
                        'title' => 'Citizen submits the application',
                        'description' => 'The browser invokes the production submission action for the exact manifest record.',
                        'dialogue' => 'The municipality receives the application into its processing queue.',
                        'duration_seconds' => 5,
                    ],
                    [
                        'title' => 'Submission and receipt are verified',
                        'description' => 'UI and canonical audit evidence agree while numbering, documentary sufficiency, assessment computation, and payment remain subsequent decisions.',
                        'dialogue' => 'Submitted and received do not mean approved or issued.',
                        'duration_seconds' => 5,
                    ],
                ],
            ];
        }

        if ($scenarioKey === 'citizen_permit_draft_document_visibility') {
            return [
                'title' => 'Citizen adds supporting evidence to a permit draft',
                'summary' => 'A citizen attaches private supporting evidence to the exact owned draft while the system preserves the unresolved documentary-readiness and formal-submission boundary.',
                'run_id' => $runId,
                'record' => [
                    'type' => 'permit_application',
                    'id' => $permitApplication->id,
                    'reference' => 'Draft #'.$permitApplication->id,
                ],
                'frames' => [
                    [
                        'title' => 'Citizen reviews documentary readiness',
                        'description' => 'The draft shows that no complete statutory requirement catalog has been accepted yet.',
                        'dialogue' => 'Received evidence is not the same as submission readiness.',
                        'duration_seconds' => 4,
                    ],
                    [
                        'title' => 'Citizen attaches private evidence',
                        'description' => 'The browser uploads one PDF through the production document action and private storage boundary.',
                        'dialogue' => 'The document is retained for later municipal review.',
                        'duration_seconds' => 5,
                    ],
                    [
                        'title' => 'Evidence remains visible and downloadable',
                        'description' => 'Desktop, mobile, download, and canonical audit evidence agree on the exact document and unchanged draft state.',
                        'dialogue' => 'No official submission or assessment is triggered.',
                        'duration_seconds' => 5,
                    ],
                ],
            ];
        }

        if ($scenarioKey === 'citizen_permit_draft_edit_visibility') {
            return [
                'title' => 'Citizen edits a saved permit application draft',
                'summary' => 'A citizen opens the exact owned draft, updates saved business and activity facts through the real application form, and confirms that the record remains outside municipal processing.',
                'run_id' => $runId,
                'record' => [
                    'type' => 'permit_application',
                    'id' => $permitApplication->id,
                    'reference' => 'Draft #'.$permitApplication->id,
                ],
                'frames' => [
                    [
                        'title' => 'Citizen reviews the saved draft',
                        'description' => 'The exact owned draft exposes editing while it remains unnumbered and unassessed.',
                        'dialogue' => 'Only saved draft facts are mutable.',
                        'duration_seconds' => 4,
                    ],
                    [
                        'title' => 'Citizen updates declared facts',
                        'description' => 'The browser submits owner, business, and activity changes through the production edit form.',
                        'dialogue' => 'Saving changes does not submit the application.',
                        'duration_seconds' => 5,
                    ],
                    [
                        'title' => 'Updated draft is verified',
                        'description' => 'Desktop, mobile, and canonical audit evidence agree on the edited facts and draft state.',
                        'dialogue' => 'No official number or assessment is created.',
                        'duration_seconds' => 5,
                    ],
                ],
            ];
        }

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

    private function storyboardHtml(string $runId, PermitApplication $permitApplication, string $scenarioKey): string
    {
        $storyboard = $this->storyboard($runId, $permitApplication, $scenarioKey);
        $frames = collect($storyboard['frames'])
            ->map(fn (array $frame): string => '<li><strong>'.e($frame['title']).'</strong><br>'.e($frame['description']).'<br><em>'.e($frame['dialogue']).'</em></li>')
            ->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($storyboard['title']).'</title></head><body><h1>'.e($storyboard['title']).'</h1><p>'.e($storyboard['summary']).'</p><p>Run ID: '.e($runId).'</p><p>Draft #'.e((string) $permitApplication->id).'</p><ol>'.$frames.'</ol></body></html>';
    }

    private function createDocumentFixture(ScenarioArtifactStore $artifactStore, string $runId): string
    {
        $fixturePath = 'browser/fixtures/citizen-business-registration.pdf';
        $document = new SimplePdfDocument(
            title: 'Citizen Scenario Supporting Evidence',
            documentCode: 'CITIZEN-SCENARIO-EVIDENCE',
            subtitle: 'Permit application draft supporting document',
            footerNote: 'Lifecycle scenario evidence only.',
        );
        $page = $document->addPage('Supporting evidence');
        $document->text($page, 'Business registration evidence', 42, 710, 14, true);
        $document->wrappedText($page, "Run ID: {$runId}", 42, 680, 511, 10);
        $document->wrappedText($page, 'Receipt of this artifact does not establish statutory sufficiency, formal submission, or permit eligibility.', 42, 650, 511, 10);
        $artifactStore->put($fixturePath, $document->render());

        return $fixturePath;
    }
}
