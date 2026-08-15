<?php

namespace App\LifecycleScenarios;

use App\Actions\CreateBillingGroup;
use App\Actions\CreateBillingGroupDraftRecord;
use App\Enums\BillingGroupAcceptanceStatus;
use App\Enums\BillingGroupFieldType;
use App\Enums\BillingGroupRecordStatus;
use App\Models\BillingGroup;
use App\Models\BillingGroupRecord;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use RuntimeException;

final class BillingGroupDraftVisibilityScenario
{
    public function __construct(
        private readonly CreateBillingGroup $createBillingGroup,
        private readonly CreateBillingGroupDraftRecord $createDraftRecord,
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

        if (is_array($existingManifest) && data_get($existingManifest, 'result.terminal') === 'passed') {
            return $existingManifest;
        }

        $operator = $actors['operator'] ?? throw new RuntimeException('Scenario operator actor was not resolved.');
        $before = $this->financialCounts();
        $billingGroup = BillingGroup::query()->where('metadata->scenario_run_id', $runId)->first();

        if (! $billingGroup instanceof BillingGroup) {
            $billingGroup = $this->createBillingGroup->handle([
                'name' => 'Scenario provisional records '.str($runId)->limit(80, '')->toString(),
                'description' => 'Disposable billing-group definition for draft-boundary evidence.',
                'fields' => [
                    [
                        'key' => 'subject_name',
                        'name' => 'Subject Name',
                        'field_type' => BillingGroupFieldType::Text->value,
                        'is_required' => true,
                        'is_unique' => false,
                        'options' => [],
                        'placeholder' => 'Name recorded for later review',
                        'default_value' => null,
                    ],
                    [
                        'key' => 'record_kind',
                        'name' => 'Record Kind',
                        'field_type' => BillingGroupFieldType::Dropdown->value,
                        'is_required' => false,
                        'is_unique' => false,
                        'options' => ['Certification', 'Inspection'],
                        'placeholder' => null,
                        'default_value' => 'Certification',
                    ],
                ],
            ], [
                'scenario_run_id' => $runId,
            ]);
        }

        $record = BillingGroupRecord::query()->where('source_snapshot->scenario_run_id', $runId)->first();

        if (! $record instanceof BillingGroupRecord) {
            $record = $this->createDraftRecord->handle($billingGroup, $operator, [
                'description' => 'Draft declaration prepared for later municipal review.',
                'record_date' => now()->toDateString(),
                'payor_name' => 'Scenario Payor',
                'field_values' => [
                    'record_kind' => 'Certification',
                ],
            ], [
                'scenario_run_id' => $runId,
            ]);
        }

        $after = $this->financialCounts();
        $steps = [
            $this->step('billing-group-created', 'Create provisional billing-group definition through the application action', ['acceptance_status' => 'provisional', 'field_count' => 2], ['acceptance_status' => $billingGroup->acceptance_status->value, 'field_count' => $billingGroup->fields()->count()]),
            $this->step('draft-record-created', 'Prepare an incomplete draft through the application action', ['status' => 'draft', 'required_value_present' => false], ['status' => $record->status->value, 'required_value_present' => array_key_exists('subject_name', $record->field_values ?? [])]),
            $this->step('financial-state-unchanged', 'Verify preparation creates no collection or receipt', $before, $after),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $manifest['resources'] = [
            'record_type' => 'billing_group_record',
            'record_id' => $record->id,
            'public_reference' => $record->draft_reference,
            'billing_group_id' => $billingGroup->id,
            'billing_group_name' => $billingGroup->name,
            'list_url' => route('staff.billing-groups.index', absolute: false),
            'detail_url' => route('staff.billing-groups.show', $billingGroup, false),
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = ['root' => '.'];

        $artifactStore->putJson('terminal/prepare.json', [
            'billing_group_id' => $billingGroup->id,
            'billing_group_record_id' => $record->id,
            'draft_reference' => $record->draft_reference,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $billingGroup, $record));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId, $billingGroup, $record));
        $artifactStore->put('review.md', $this->summaryRenderer->reviewMarkdown());

        return $manifest;
    }

    /** @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function audit(array $manifest, ScenarioArtifactStore $artifactStore): array
    {
        $billingGroup = $this->billingGroup((int) $manifest['resources']['billing_group_id']);
        $record = $this->billingGroupRecord((int) $manifest['resources']['record_id']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [];
        $checks = [
            $this->step('audit-provisional-definition', 'Canonical definition remains provisional', ['acceptance_status' => BillingGroupAcceptanceStatus::Provisional->value], ['acceptance_status' => $billingGroup->acceptance_status->value]),
            $this->step('audit-draft-state', 'Canonical record remains a non-financial draft', ['status' => BillingGroupRecordStatus::Draft->value, 'financial_effect' => 'none'], ['status' => $record->status->value, 'financial_effect' => $record->source_snapshot['financial_effect'] ?? null]),
            $this->step('audit-schema-snapshot', 'Draft preserves the exact field schema used at preparation', ['field_count' => $billingGroup->fields->count()], ['field_count' => count($record->schema_snapshot)]),
            $this->step('audit-browser-visibility', 'Browser shows the exact definition, draft, and policy boundary', ['definition_visible' => true, 'draft_visible' => true, 'policy_boundary_visible' => true], ['definition_visible' => data_get($browserReport, 'billing_group.definition_visible'), 'draft_visible' => data_get($browserReport, 'billing_group.draft_visible'), 'policy_boundary_visible' => data_get($browserReport, 'billing_group.policy_boundary_visible')]),
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
                'billing_group_id' => $billingGroup->id,
                'acceptance_status' => $billingGroup->acceptance_status->value,
                'record_id' => $record->id,
                'draft_reference' => $record->draft_reference,
                'status' => $record->status->value,
                'financial_effect' => $record->source_snapshot['financial_effect'] ?? null,
                'schema_snapshot' => $record->schema_snapshot,
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    /** @return array{treasury_collections: int, receipts: int} */
    private function financialCounts(): array
    {
        return [
            'treasury_collections' => TreasuryCollection::query()->count(),
            'receipts' => Receipt::query()->count(),
        ];
    }

    private function billingGroup(int $id): BillingGroup
    {
        return BillingGroup::query()->with('fields')->whereKey($id)->sole();
    }

    private function billingGroupRecord(int $id): BillingGroupRecord
    {
        return BillingGroupRecord::query()->whereKey($id)->sole();
    }

    /** @param array<string, mixed> $expected
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

    /** @return array<string, mixed> */
    private function storyboard(string $runId, BillingGroup $billingGroup, BillingGroupRecord $record): array
    {
        return [
            'title' => 'Provisional billing group draft visibility',
            'summary' => 'Treasury staff records a configurable definition and prepares a draft declaration without creating financial or municipal authority.',
            'run_id' => $runId,
            'records' => ['billing_group_id' => $billingGroup->id, 'billing_group_record_id' => $record->id],
            'frames' => [
                ['title' => 'Definition recorded', 'description' => 'Staff records a provisional definition and ordered field schema.', 'dialogue' => 'Configuration does not establish policy acceptance.', 'duration_seconds' => 5],
                ['title' => 'Draft prepared', 'description' => 'Staff prepares an incomplete declaration against the exact schema.', 'dialogue' => 'A draft is not a bill, collection, or receipt.', 'duration_seconds' => 5],
                ['title' => 'Boundary verified', 'description' => 'Browser and audit evidence agree that no financial side effect occurred.', 'dialogue' => 'Financial execution remains unavailable.', 'duration_seconds' => 5],
            ],
        ];
    }

    private function storyboardHtml(string $runId, BillingGroup $billingGroup, BillingGroupRecord $record): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Provisional billing group draft visibility</title></head><body><h1>Provisional billing group draft visibility</h1><p>Run ID: '.e($runId).'</p><p>Definition: '.e($billingGroup->name).'</p><p>Draft reference: '.e($record->draft_reference).'</p><p>This journey records configurable structure and an incomplete declaration only. It creates no liability, collection, receipt, or official transaction number.</p></body></html>';
    }
}
