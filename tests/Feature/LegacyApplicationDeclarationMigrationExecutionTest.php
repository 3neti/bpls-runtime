<?php

use App\Actions\ExecuteLegacyApplicationDeclarations;
use App\Actions\PlanLegacyApplicationDeclarations;
use App\Actions\RollbackLegacyApplicationDeclarations;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Enums\LegacyMappingExecutionStatus;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyDeclarationLineMapping;
use App\Models\LegacyDeclarationMappingExecution;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyDeclarationMappingProposal;
use App\Models\LegacyImportBatch;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

function declarationExecutionTestCents(string $amount): int
{
    $normalized = str_replace(',', '', $amount);
    [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '');

    return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
}

/**
 * @param  list<array<string, mixed>>|null  $lines
 * @return array{source: LegacySource, batch: LegacyImportBatch, application: PermitApplication, applicationMapping: LegacyApplicationIdMapping, record: LegacyRecord, reconciliations: list<LegacyLineOfBusinessReconciliation>, plan: LegacyDeclarationMappingPlan, proposals: Collection<int, LegacyDeclarationMappingProposal>}
 */
function executableDeclarationPlan(string $suffix, ?array $lines = null, bool $alreadyMapped = false): array
{
    $lines ??= [
        ['businessCategory' => 'Legacy Retail '.$suffix, 'permitApplicationType' => 'New', 'capitalInvestment' => '1,234.50'],
        ['businessCategory' => 'Legacy Services '.$suffix, 'permitApplicationType' => 'New', 'capitalInvestment' => '5,000.00'],
    ];
    $source = LegacySource::factory()->create([
        'key' => 'LEGACY-DECLARATION-EXECUTION-'.$suffix,
        'baseline' => 'declaration-execution-baseline-'.$suffix,
        'archive_checksum' => hash('sha256', 'declaration-execution-archive-'.$suffix),
    ]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'declaration-execution-staging-'.$suffix,
        'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 1,
        'staged_record_count' => 1,
        'exception_count' => 0,
    ]);
    $owner = BusinessOwner::factory()->create();
    $business = Business::factory()->for($owner, 'owner')->create();
    $application = PermitApplication::factory()->for($business)->create([
        'application_number' => null,
        'legacy_source_id' => 'legacy-declaration-application-'.$suffix,
    ]);
    $applicationMapping = LegacyApplicationIdMapping::query()->create([
        'legacy_source_id' => $source->id,
        'legacy_import_batch_id' => $batch->id,
        'permit_application_id' => $application->id,
        'dataset_key' => 'applications',
        'legacy_id' => $application->legacy_source_id,
        'status' => 'mapped',
        'mapping_basis' => 'test_accepted_application_mapping',
        'metadata' => ['fixture' => true],
    ]);
    $payload = [
        '_id' => $application->legacy_source_id,
        'permitApplicationType' => 'New',
        'linesOfBusiness' => $lines,
        'applicantName' => 'Sensitive Declaration Applicant '.$suffix,
    ];
    $record = LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id,
        'legacy_source_id' => $source->id,
        'dataset_key' => 'applications',
        'entity_type' => 'application',
        'legacy_id' => $application->legacy_source_id,
        'payload' => $payload,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        'status' => 'staged',
        'line_number' => 1,
    ]);
    $reconciliations = [];

    foreach ($lines as $index => $line) {
        $category = (string) ($line['businessCategory'] ?? 'Missing '.$index);
        $target = LineOfBusiness::factory()->create(['name' => 'Accepted '.$category]);
        $reconciliations[] = LegacyLineOfBusinessReconciliation::query()->create([
            'legacy_source_id' => $source->id,
            'source_dataset' => 'groups',
            'source_value_hash' => hash('sha256', str($category)->squish()->lower()->toString()),
            'line_of_business_id' => $target->id,
            'status' => LegacyLineOfBusinessReconciliationStatus::Accepted,
            'decision_authority' => 'Municipal declaration execution fixture',
            'evidence_reference' => 'TEST-DECLARATION-EXECUTION-'.$index,
            'decided_at' => now(),
        ]);

        if ($alreadyMapped) {
            $capital = declarationExecutionTestCents((string) ($line['capitalInvestment'] ?? '0'));
            $targetLine = PermitApplicationLine::factory()->for($application)->for($target, 'lineOfBusiness')->create([
                'declared_gross_sales_cents' => 0,
                'capital_investment_cents' => $capital,
                'quantity' => 1,
                'started_on' => null,
                'metadata' => [
                    'legacy_number_of_employees' => null,
                    'legacy_category_hash' => hash('sha256', str($category)->squish()->lower()->toString()),
                ],
            ]);
            LegacyDeclarationLineMapping::query()->create([
                'legacy_application_id_mapping_id' => $applicationMapping->id,
                'legacy_line_of_business_reconciliation_id' => $reconciliations[$index]->id,
                'legacy_source_id' => $source->id,
                'legacy_import_batch_id' => $batch->id,
                'permit_application_line_id' => $targetLine->id,
                'dataset_key' => 'applications',
                'legacy_id' => $record->legacy_id,
                'line_index' => $index,
                'status' => 'mapped',
                'mapping_basis' => 'pre_existing_accepted_mapping',
                'metadata' => ['fixture' => true],
            ]);
        }
    }

    $plan = app(PlanLegacyApplicationDeclarations::class)->handle($batch, 'declaration-execution-plan-'.$suffix);

    return [
        'source' => $source,
        'batch' => $batch,
        'application' => $application,
        'applicationMapping' => $applicationMapping,
        'record' => $record,
        'reconciliations' => $reconciliations,
        'plan' => $plan,
        'proposals' => $plan->proposals,
    ];
}

test('a complete ready declaration set creates exact declared facts once without assessment behavior', function () {
    $fixture = executableDeclarationPlan('create');
    $proposalIds = $fixture['proposals']->pluck('id')->all();
    $action = app(ExecuteLegacyApplicationDeclarations::class);

    $execution = $action->handle($fixture['plan'], $proposalIds, 'declaration-execution-create-001');
    $retry = $action->handle($fixture['plan'], array_reverse($proposalIds), 'declaration-execution-create-001');
    $lines = $fixture['application']->lines()->get();

    expect($execution)
        ->status->toBe(LegacyMappingExecutionStatus::Completed)
        ->selected_count->toBe(2)
        ->created_count->toBe(2)
        ->reused_count->toBe(0)
        ->mapping_count->toBe(2)
        ->and($retry->id)->toBe($execution->id)
        ->and(LegacyDeclarationMappingExecution::query()->count())->toBe(1)
        ->and(LegacyDeclarationLineMapping::query()->count())->toBe(2)
        ->and($lines->pluck('capital_investment_cents')->all())->toBe([123450, 500000])
        ->and($lines->pluck('declared_gross_sales_cents')->all())->toBe([0, 0])
        ->and($lines->every(fn (PermitApplicationLine $line): bool => $line->quantity === 1))->toBeTrue()
        ->and($lines->every(fn (PermitApplicationLine $line): bool => isset($line->metadata['migration']['reconciliation_id'])))->toBeTrue()
        ->and(Assessment::query()->count())->toBe(0)
        ->and(AssessmentLine::query()->count())->toBe(0)
        ->and($fixture['application']->fresh()->application_number)->toBeNull();
});

test('a legacy application declaration set cannot execute partially or with a non-ready member', function () {
    $fixture = executableDeclarationPlan('complete-set');

    expect(fn () => app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $fixture['plan'],
        [$fixture['proposals']->first()->id],
        'declaration-execution-partial-001',
    ))->toThrow(RuntimeException::class, 'complete declaration proposal set atomically');

    $fixture['proposals']->last()->update(['status' => 'review_required']);

    expect(fn () => app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $fixture['plan'],
        $fixture['proposals']->pluck('id')->all(),
        'declaration-execution-not-ready-001',
    ))->toThrow(RuntimeException::class, 'is not ready and cannot execute');
});

test('execution refuses unmanaged existing declarations and changed source or reconciliation evidence', function () {
    $unmanaged = executableDeclarationPlan('unmanaged');
    PermitApplicationLine::factory()->for($unmanaged['application'])->create();

    expect(fn () => app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $unmanaged['plan'],
        $unmanaged['proposals']->pluck('id')->all(),
        'declaration-execution-unmanaged-001',
    ))->toThrow(RuntimeException::class, 'has unmanaged existing declarations');

    $sourceDrift = executableDeclarationPlan('source-drift');
    $payload = $sourceDrift['record']->payload;
    $payload['linesOfBusiness'][0]['capitalInvestment'] = '9,999.99';
    $sourceDrift['record']->update(['payload' => $payload]);

    expect(fn () => app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $sourceDrift['plan'],
        $sourceDrift['proposals']->pluck('id')->all(),
        'declaration-execution-source-drift-001',
    ))->toThrow(RuntimeException::class, 'no longer matches its staged projection');

    $reconciliationDrift = executableDeclarationPlan('reconciliation-drift');
    $reconciliationDrift['reconciliations'][0]->update(['evidence_reference' => 'CHANGED-AUTHORITY-EVIDENCE']);

    expect(fn () => app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $reconciliationDrift['plan'],
        $reconciliationDrift['proposals']->pluck('id')->all(),
        'declaration-execution-reconciliation-drift-001',
    ))->toThrow(RuntimeException::class, 'no longer matches its dependency snapshot');
});

test('accepted pre-existing declaration mappings are reused without duplication', function () {
    $fixture = executableDeclarationPlan('reuse', null, true);

    $execution = app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $fixture['plan'],
        $fixture['proposals']->pluck('id')->all(),
        'declaration-execution-reuse-001',
    );

    expect($execution)
        ->created_count->toBe(0)
        ->reused_count->toBe(2)
        ->mapping_count->toBe(0)
        ->and(PermitApplicationLine::query()->count())->toBe(2)
        ->and(LegacyDeclarationLineMapping::query()->count())->toBe(2);
});

test('rollback deletes unchanged created lines and refuses changed or assessed lines', function () {
    $rollback = executableDeclarationPlan('rollback');
    $rollbackExecution = app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $rollback['plan'],
        $rollback['proposals']->pluck('id')->all(),
        'declaration-execution-rollback-001',
    );
    $rolledBack = app(RollbackLegacyApplicationDeclarations::class)->handle($rollbackExecution);
    $retry = app(RollbackLegacyApplicationDeclarations::class)->handle($rolledBack);

    expect($rolledBack->status)->toBe(LegacyMappingExecutionStatus::RolledBack)
        ->and($retry->id)->toBe($rollbackExecution->id)
        ->and(PermitApplicationLine::query()->count())->toBe(0)
        ->and(LegacyDeclarationLineMapping::query()->count())->toBe(0);

    $changed = executableDeclarationPlan('changed');
    $changedExecution = app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $changed['plan'],
        $changed['proposals']->pluck('id')->all(),
        'declaration-execution-changed-001',
    );
    $changed['application']->lines()->firstOrFail()->update(['capital_investment_cents' => 1]);

    expect(fn () => app(RollbackLegacyApplicationDeclarations::class)->handle($changedExecution))
        ->toThrow(RuntimeException::class, 'changed after migration; rollback refused');

    $assessed = executableDeclarationPlan('assessed');
    $assessedExecution = app(ExecuteLegacyApplicationDeclarations::class)->handle(
        $assessed['plan'],
        $assessed['proposals']->pluck('id')->all(),
        'declaration-execution-assessed-001',
    );
    $assessedLine = $assessed['application']->lines()->firstOrFail();
    $assessment = Assessment::factory()->for($assessed['application'])->create();
    AssessmentLine::factory()->for($assessment)->create(['permit_application_line_id' => $assessedLine->id]);

    expect(fn () => app(RollbackLegacyApplicationDeclarations::class)->handle($assessedExecution))
        ->toThrow(RuntimeException::class, 'has assessment dependencies; rollback refused');
});

test('commands require dual confirmation and write redacted execution and rollback evidence', function () {
    Storage::fake('local');
    $fixture = executableDeclarationPlan('command');
    $arguments = [
        'plan' => $fixture['plan']->id,
        '--proposal' => $fixture['proposals']->pluck('id')->all(),
        '--run-id' => 'declaration-execution-command-001',
        '--json' => true,
    ];

    $this->artisan('legacy:execute-declarations', $arguments)->assertFailed();
    $this->artisan('legacy:execute-declarations', [...$arguments, '--execute' => true, '--confirm-execute' => true])->assertSuccessful();

    $execution = LegacyDeclarationMappingExecution::query()->sole();
    $root = 'legacy-migrations/LEGACY-DECLARATION-EXECUTION-command/declaration-execution-staging-command/declaration-mapping-plans/declaration-execution-plan-command/executions/declaration-execution-command-001';
    Storage::disk('local')->assertExists($root.'/execution.json');
    Storage::disk('local')->assertExists($root.'/review.md');
    $report = Storage::disk('local')->get($root.'/execution.json');
    $decoded = json_decode($report, true, flags: JSON_THROW_ON_ERROR);

    expect($report)
        ->not->toContain('legacy-declaration-application-command')
        ->not->toContain('Legacy Retail command')
        ->not->toContain('Sensitive Declaration Applicant command')
        ->and($decoded['safety'])->toMatchArray([
            'complete_application_sets_required' => true,
            'financial_calculations' => false,
            'assessment_records_created' => false,
            'external_integrations' => false,
        ]);

    $this->artisan('legacy:rollback-declarations', ['execution' => $execution->id, '--json' => true])->assertFailed();
    $this->artisan('legacy:rollback-declarations', [
        'execution' => $execution->id,
        '--rollback' => true,
        '--confirm-rollback' => true,
        '--json' => true,
    ])->assertSuccessful();
    Storage::disk('local')->assertExists($root.'/rollback.json');
});
