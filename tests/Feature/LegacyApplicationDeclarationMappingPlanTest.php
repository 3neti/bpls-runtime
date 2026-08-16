<?php

use App\Actions\PlanLegacyApplicationDeclarations;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyDeclarationMappingProposal;
use App\Models\LegacyImportBatch;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyRecord;
use App\Models\LegacySource;
use App\Models\LineOfBusiness;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Facades\Storage;

/** @return array{source: LegacySource, batch: LegacyImportBatch} */
function declarationBatch(string $suffix): array
{
    $source = LegacySource::factory()->create(['key' => 'LEGACY-DECLARATION-'.$suffix]);
    $batch = LegacyImportBatch::factory()->for($source, 'source')->create([
        'run_reference' => 'declaration-staging-'.$suffix, 'status' => LegacyImportBatchStatus::Staged,
        'source_record_count' => 1, 'staged_record_count' => 1,
    ]);

    return compact('source', 'batch');
}

/** @param list<array<string, mixed>> $lines */
function declarationRecord(LegacyImportBatch $batch, string $legacyId, array $lines): LegacyRecord
{
    $payload = ['_id' => $legacyId, 'permitApplicationType' => 'New', 'linesOfBusiness' => $lines];

    return LegacyRecord::query()->create([
        'legacy_import_batch_id' => $batch->id, 'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => 'applications', 'entity_type' => 'application', 'legacy_id' => $legacyId,
        'payload' => $payload, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        'status' => 'staged', 'line_number' => 1,
    ]);
}

function acceptLineReconciliation(LegacySource $source, string $category, LineOfBusiness $target): LegacyLineOfBusinessReconciliation
{
    return LegacyLineOfBusinessReconciliation::query()->create([
        'legacy_source_id' => $source->id, 'source_dataset' => 'groups',
        'source_value_hash' => hash('sha256', str($category)->squish()->lower()->toString()),
        'line_of_business_id' => $target->id, 'status' => LegacyLineOfBusinessReconciliationStatus::Accepted,
        'decision_authority' => 'Municipal reconciliation fixture', 'evidence_reference' => 'TEST-LOB-ACCEPTANCE', 'decided_at' => now(),
    ]);
}

test('accepted line identity and exact new-business capital produce a ready declaration without writes or calculations', function () {
    $fixture = declarationBatch('ready');
    $target = LineOfBusiness::factory()->create(['name' => 'Accepted Retail']);
    acceptLineReconciliation($fixture['source'], 'Legacy Retail Group', $target);
    declarationRecord($fixture['batch'], 'application-ready', [[
        'businessCategory' => 'Legacy Retail Group', 'permitApplicationType' => 'New', 'capitalInvestment' => '1,234.50',
    ]]);

    $plan = app(PlanLegacyApplicationDeclarations::class)->handle($fixture['batch'], 'declaration-plan-ready-001');
    $proposal = $plan->proposals->sole();

    expect($plan)->status->toBe(LegacyMappingPlanStatus::Planned)
        ->ready_count->toBe(1)
        ->and($proposal->status)->toBe(LegacyMappingProposalStatus::Ready)
        ->and($proposal->line_of_business_id)->toBe($target->id)
        ->and($proposal->metadata['projected_capital_cents'])->toBe(123450)
        ->and($proposal->metadata['financial_calculations'])->toBeFalse()
        ->and(PermitApplicationLine::query()->count())->toBe(0);
});

test('matching names do not establish line identity without accepted reconciliation', function () {
    $fixture = declarationBatch('name-only');
    LineOfBusiness::factory()->create(['name' => 'Retail Trade']);
    declarationRecord($fixture['batch'], 'application-name-only', [[
        'businessCategory' => 'Retail Trade', 'permitApplicationType' => 'New', 'capitalInvestment' => '1000',
    ]]);

    $proposal = app(PlanLegacyApplicationDeclarations::class)->handle($fixture['batch'], 'declaration-plan-name-only')->proposals->sole();

    expect($proposal)->status->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposal->line_of_business_id)->toBeNull()
        ->and($proposal->reasons)->toContain('accepted_line_of_business_reconciliation_missing');
});

test('ranges conflicting revenue and financial configuration remain non executable', function () {
    $fixture = declarationBatch('financial');
    $target = LineOfBusiness::factory()->create();
    acceptLineReconciliation($fixture['source'], 'Services', $target);
    declarationRecord($fixture['batch'], 'application-financial', [
        ['businessCategory' => 'Services', 'permitApplicationType' => 'New', 'capitalInvestment' => '1000 - 2000'],
        ['businessCategory' => 'Services', 'permitApplicationType' => 'Renewal', 'capitalInvestment' => '0', 'grossSales' => '5000', 'businessAnnualRevenue' => '6000'],
        ['businessCategory' => 'Services', 'permitApplicationType' => 'New', 'capitalInvestment' => '1000', 'excludedFees' => ['fee-secret']],
    ]);

    $proposals = app(PlanLegacyApplicationDeclarations::class)->handle($fixture['batch'], 'declaration-plan-financial')->proposals->keyBy('line_index');

    expect($proposals[0])->status->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposals[0]->reasons)->toContain('new_application_capital_not_exact_amount')
        ->and($proposals[1]->status)->toBe(LegacyMappingProposalStatus::Blocked)
        ->and($proposals[1]->reasons)->toContain('gross_sales_and_legacy_revenue_conflict', 'renewal_gross_sales_not_exact_amount')
        ->and($proposals[2]->status)->toBe(LegacyMappingProposalStatus::ReviewRequired)
        ->and($proposals[2]->reasons)->toContain('line_financial_configuration_migration_required')
        ->and(PermitApplicationLine::query()->count())->toBe(0);
});

test('stable declaration plan retries are immutable against reconciliation changes', function () {
    $fixture = declarationBatch('stable');
    $target = LineOfBusiness::factory()->create();
    $reconciliation = acceptLineReconciliation($fixture['source'], 'Stable Group', $target);
    declarationRecord($fixture['batch'], 'application-stable', [['businessCategory' => 'Stable Group', 'permitApplicationType' => 'New', 'capitalInvestment' => '100']]);
    $action = app(PlanLegacyApplicationDeclarations::class);

    $first = $action->handle($fixture['batch'], 'declaration-plan-stable');
    $second = $action->handle($fixture['batch'], 'declaration-plan-stable');
    expect($second->id)->toBe($first->id)
        ->and(LegacyDeclarationMappingPlan::query()->count())->toBe(1)
        ->and(LegacyDeclarationMappingProposal::query()->count())->toBe(1);

    $first->update([
        'status' => LegacyMappingPlanStatus::Planning,
        'proposal_count' => 0,
        'ready_count' => 0,
        'review_count' => 0,
        'blocked_count' => 0,
        'completed_at' => null,
    ]);
    $resumed = $action->handle($fixture['batch'], 'declaration-plan-stable');
    expect($resumed->status)->toBe(LegacyMappingPlanStatus::Planned)
        ->and($resumed->proposal_count)->toBe(1)
        ->and(LegacyDeclarationMappingProposal::query()->count())->toBe(1);

    $reconciliation->update(['evidence_reference' => 'CHANGED-EVIDENCE']);
    expect(fn () => $action->handle($fixture['batch'], 'declaration-plan-stable'))
        ->toThrow(RuntimeException::class, 'different source or reconciliation evidence');
});

test('declaration command writes category-redacted evidence and requires a stable run id', function () {
    Storage::fake('local');
    $fixture = declarationBatch('command');
    declarationRecord($fixture['batch'], 'application-secret', [[
        'businessCategory' => 'Sensitive Business Category', 'permitApplicationType' => 'New', 'capitalInvestment' => '999.99',
    ]]);

    $this->artisan('legacy:plan-declarations', ['batch' => $fixture['batch']->id])->expectsOutput('A stable --run-id is required.')->assertFailed();
    $this->artisan('legacy:plan-declarations', ['batch' => $fixture['batch']->id, '--run-id' => 'declaration-plan-command', '--json' => true])->assertSuccessful();

    $root = 'legacy-migrations/LEGACY-DECLARATION-command/declaration-staging-command/declaration-mapping-plans/declaration-plan-command';
    Storage::disk('local')->assertExists($root.'/declaration-plan.json');
    $report = Storage::disk('local')->get($root.'/declaration-plan.json');
    $decoded = json_decode($report, true, 512, JSON_THROW_ON_ERROR);
    expect($report)->not->toContain('Sensitive Business Category', 'application-secret')
        ->and($decoded['safety'])->toMatchArray(['name_only_matching' => false, 'range_values_interpreted_as_exact' => false, 'financial_calculations' => false, 'domain_writes' => false]);
});
