<?php

use App\Actions\ClassifyLegacyFinancialMigrationEvidence;
use App\Enums\LegacyFinancialMigrationDisposition;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\Assessment;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyFinancialMappingProposal;
use App\Models\LegacyImportBatch;
use App\Models\LegacyRecord;
use Illuminate\Support\Facades\Storage;

/** @return array{plan: LegacyFinancialMappingPlan, proposals: array<string, LegacyFinancialMappingProposal>} */
function financialMigrationClassificationFixture(string $suffix): array
{
    $batch = LegacyImportBatch::factory()->create(['run_reference' => 'financial-classification-stage-'.$suffix]);
    $record = LegacyRecord::factory()->for($batch, 'importBatch')->create([
        'legacy_source_id' => $batch->legacy_source_id,
        'dataset_key' => 'payment_schedules',
        'entity_type' => 'payment_schedule',
        'legacy_id' => 'sensitive-financial-record-'.$suffix,
        'payload' => ['_id' => 'sensitive-financial-record-'.$suffix, 'payer' => 'Sensitive Person'],
    ]);
    $plan = LegacyFinancialMappingPlan::factory()->for($batch, 'importBatch')->create([
        'status' => LegacyMappingPlanStatus::PlannedWithExceptions,
        'proposal_count' => 5,
        'ready_count' => 1,
        'review_count' => 2,
        'blocked_count' => 2,
    ]);

    $base = ['legacy_financial_mapping_plan_id' => $plan->id, 'legacy_record_id' => $record->id];
    $proposals = [
        'ready' => LegacyFinancialMappingProposal::factory()->create([...$base, 'kind' => 'payment_schedule', 'item_key' => 'ready', 'status' => LegacyMappingProposalStatus::Ready, 'reasons' => []]),
        'historical' => LegacyFinancialMappingProposal::factory()->create([
            ...$base,
            'kind' => 'payment_schedule_fee',
            'item_key' => 'historical',
            'status' => LegacyMappingProposalStatus::Blocked,
            'reasons' => ['aggregated_schedule_fee_identity_requires_reconciliation', 'schedule_fee_category_requires_reconciliation'],
            'metadata' => ['original_amount_cents' => 10000, 'section_amount_cents' => 2500, 'was_edited' => false],
        ]),
        'reconciliation' => LegacyFinancialMappingProposal::factory()->create([
            ...$base,
            'kind' => 'application_financial_summary',
            'item_key' => 'reconciliation',
            'status' => LegacyMappingProposalStatus::ReviewRequired,
            'reasons' => ['application_total_fees_requires_schedule_reconciliation'],
        ]),
        'quarantine' => LegacyFinancialMappingProposal::factory()->create([
            ...$base,
            'kind' => 'payment_schedule',
            'item_key' => 'quarantine',
            'status' => LegacyMappingProposalStatus::Blocked,
            'reasons' => ['schedule_application_reference_unresolved'],
        ]),
        'authority' => LegacyFinancialMappingProposal::factory()->create([
            ...$base,
            'kind' => 'receipt_claim',
            'item_key' => 'authority',
            'status' => LegacyMappingProposalStatus::Blocked,
            'reasons' => ['receipt_numbering_authority_required'],
        ]),
    ];

    return compact('plan', 'proposals');
}

test('financial migration evidence is classified without changing proposal status or writing domain records', function () {
    $fixture = financialMigrationClassificationFixture('all-dispositions');
    $originalStatuses = collect($fixture['proposals'])->map->status->all();

    $report = app(ClassifyLegacyFinancialMigrationEvidence::class)->handle($fixture['plan']);

    expect($report)
        ->schema_version->toBe(ClassifyLegacyFinancialMigrationEvidence::ClassifierVersion)
        ->summary->classified_count->toBe(5)
        ->summary->existing_rehearsal_eligible_count->toBe(1)
        ->summary->historical_snapshot_incomplete_provenance_count->toBe(1)
        ->summary->migration_execution_authorized->toBeFalse()
        ->summary->cutover_authorized->toBeFalse()
        ->summary->disposition_counts->{LegacyFinancialMigrationDisposition::DeterministicAndRehearsalEligible->value}->toBe(1)
        ->summary->disposition_counts->{LegacyFinancialMigrationDisposition::DeterministicHistoricalSnapshotIncompleteProvenance->value}->toBe(1)
        ->summary->disposition_counts->{LegacyFinancialMigrationDisposition::ReconciliationRequired->value}->toBe(1)
        ->summary->disposition_counts->{LegacyFinancialMigrationDisposition::QuarantinedHistoricalEvidence->value}->toBe(1)
        ->summary->disposition_counts->{LegacyFinancialMigrationDisposition::AuthorityBlocked->value}->toBe(1)
        ->safety->liability_calculations->toBeFalse()
        ->safety->financial_domain_writes->toBeFalse()
        ->safety->production_mutation->toBeFalse()
        ->and(collect($fixture['proposals'])->map(fn (LegacyFinancialMappingProposal $proposal) => $proposal->fresh()->status)->all())->toBe($originalStatuses)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(json_encode($report, JSON_THROW_ON_ERROR))->not->toContain('Sensitive Person')
        ->not->toContain('sensitive-financial-record');
});

test('structural defects take precedence over incomplete provenance and edited snapshots require authority', function () {
    $fixture = financialMigrationClassificationFixture('precedence');
    $historical = $fixture['proposals']['historical'];
    $historical->update(['reasons' => ['schedule_fee_amount_not_exact', 'legacy_fee_identity_missing']]);
    $fixture['proposals']['authority']->update([
        'kind' => 'payment_schedule_fee',
        'reasons' => ['historical_schedule_fee_edit_requires_acceptance'],
        'metadata' => ['original_amount_cents' => 10000, 'section_amount_cents' => 10000, 'was_edited' => true],
    ]);

    $report = app(ClassifyLegacyFinancialMigrationEvidence::class)->handle($fixture['plan']);

    expect($report)
        ->summary->disposition_counts->{LegacyFinancialMigrationDisposition::QuarantinedHistoricalEvidence->value}->toBe(2)
        ->summary->disposition_counts->{LegacyFinancialMigrationDisposition::AuthorityBlocked->value}->toBe(1)
        ->summary->disposition_counts->{LegacyFinancialMigrationDisposition::DeterministicHistoricalSnapshotIncompleteProvenance->value}->toBe(0);
});

test('classification refuses an incomplete proposal population', function () {
    $fixture = financialMigrationClassificationFixture('incomplete-population');
    $fixture['plan']->update(['proposal_count' => 6]);

    expect(fn () => app(ClassifyLegacyFinancialMigrationEvidence::class)->handle($fixture['plan']->fresh()))
        ->toThrow(RuntimeException::class, 'declares 6 proposals but 5 were available');
});

test('classification command writes immutable payload safe evidence without authorizing migration', function () {
    Storage::fake('local');
    $fixture = financialMigrationClassificationFixture('command');

    $arguments = [
        'plan' => $fixture['plan']->id,
        '--run-id' => 'financial-classification-20260817-001',
        '--json' => true,
    ];
    $this->artisan('legacy:classify-financial-migration', $arguments)
        ->assertSuccessful()
        ->expectsOutputToContain('"migration_executed": false');

    $root = "legacy-migrations/{$fixture['plan']->importBatch->source->key}/{$fixture['plan']->importBatch->run_reference}/reconciliation/financial-migration-classification/financial-classification-20260817-001";
    $report = Storage::disk('local')->get($root.'/classification.json');
    expect($report)
        ->not->toContain('Sensitive Person')
        ->not->toContain('sensitive-financial-record')
        ->and(Storage::disk('local')->exists($root.'/review.md'))->toBeTrue();

    $this->artisan('legacy:classify-financial-migration', $arguments)->assertSuccessful();

    $fixture['proposals']['reconciliation']->update(['reasons' => ['payment_mode_schedule_policy_requires_reconciliation']]);
    $this->artisan('legacy:classify-financial-migration', $arguments)
        ->assertFailed()
        ->expectsOutputToContain('already bound to different evidence');
});
