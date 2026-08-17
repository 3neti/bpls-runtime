<?php

use App\Actions\VerifyFinancialCalibrationSuite;
use Illuminate\Support\Facades\Storage;

/** @return array<string, mixed> */
function financialCalibrationManifest(array $overrides = []): array
{
    return array_replace_recursive([
        'schema_version' => VerifyFinancialCalibrationSuite::ManifestSchemaVersion,
        'calibration_id' => 'CAL-2026-999',
        'classification' => 'golden_financial_specimen',
        'specimen_sha256' => hash('sha256', 'specimen'),
        'production_snapshot_sha256' => hash('sha256', 'snapshot'),
        'evidence_layers' => [
            'revenue_code' => ['status' => 'observed_with_authority_gap', 'references' => ['LEGAL-001']],
            'production_configuration' => ['status' => 'observed', 'references' => ['CONFIG-SHA256']],
            'legacy_evaluator' => ['status' => 'characterized', 'references' => ['EVALUATOR-SHA256']],
            'persisted_outcome' => ['status' => 'observed', 'references' => ['OUTCOME-SHA256']],
            'municipal_specimen' => ['status' => 'observed', 'references' => ['SPECIMEN-SHA256']],
        ],
        'historical_reproduction_assertions' => [
            [
                'key' => 'business_tax_cents',
                'unit' => 'minor_currency_units',
                'expected' => '1071000',
                'actual' => '1071000',
                'evidence_layers' => ['revenue_code', 'production_configuration', 'legacy_evaluator', 'persisted_outcome', 'municipal_specimen'],
            ],
            [
                'key' => 'grand_total_cents',
                'unit' => 'minor_currency_units',
                'expected' => '1453500',
                'actual' => '1453500',
                'evidence_layers' => ['persisted_outcome', 'municipal_specimen'],
            ],
        ],
        'future_policy_assertions' => [
            ['key' => 'essential_commodity_half_rate', 'authorization_status' => 'pending', 'authority' => null],
            ['key' => 'due_date_delinquency', 'authorization_status' => 'pending', 'authority' => null],
        ],
        'historical_divergences' => [
            [
                'key' => 'application_summary_vs_assessment',
                'values' => ['application_summary_cents' => '1300000', 'assessment_cents' => '1453500'],
                'disposition' => 'preserve_both_unresolved',
            ],
        ],
    ], $overrides);
}

test('suite passes historical reproduction while keeping future policy blocked', function () {
    $report = app(VerifyFinancialCalibrationSuite::class)->handle(
        [financialCalibrationManifest()],
        'calibration-suite-20260817-001',
    );

    expect($report)
        ->summary->specimen_count->toBe(1)
        ->summary->historical_reproduction_passed->toBe(1)
        ->summary->historical_reproduction_failed->toBe(0)
        ->summary->historical_suite_passed->toBeTrue()
        ->summary->future_policy_pending->toBe(2)
        ->summary->future_policy_suite_status->toBe('blocked_pending_authority')
        ->specimens->{0}->historical_reproduction->passed->toBeTrue()
        ->specimens->{0}->future_policy->assertions_executed->toBeFalse()
        ->specimens->{0}->historical_divergences->{0}->disposition->toBe('preserve_both_unresolved')
        ->safety->formulas_evaluated->toBeFalse()
        ->safety->historical_liability_recalculated->toBeFalse()
        ->safety->financial_policy_activated->toBeFalse()
        ->safety->financial_domain_writes->toBeFalse();
});

test('historical reproduction failures do not become future policy assertions', function () {
    $manifest = financialCalibrationManifest();
    $manifest['historical_reproduction_assertions'][0]['actual'] = '1070999';

    $report = app(VerifyFinancialCalibrationSuite::class)->handle([$manifest], 'calibration-suite-failure-001');

    expect($report)
        ->summary->historical_suite_passed->toBeFalse()
        ->summary->historical_reproduction_failed->toBe(1)
        ->summary->future_policy_pending->toBe(2)
        ->specimens->{0}->historical_reproduction->assertions->{0}->passed->toBeFalse()
        ->specimens->{0}->future_policy->assertions_executed->toBeFalse();
});

test('accepted future policy requires explicit authority evidence and is never executed by the suite', function () {
    $missingAuthority = financialCalibrationManifest();
    $missingAuthority['future_policy_assertions'] = [
        ['key' => 'essential_commodity_half_rate', 'authorization_status' => 'accepted', 'authority' => null],
    ];

    expect(fn () => app(VerifyFinancialCalibrationSuite::class)->handle([$missingAuthority], 'calibration-suite-authority-001'))
        ->toThrow(RuntimeException::class, 'requires authority evidence');

    $withAuthority = financialCalibrationManifest();
    $withAuthority['future_policy_assertions'] = [[
        'key' => 'essential_commodity_half_rate',
        'authorization_status' => 'accepted',
        'authority' => [
            'decision_reference' => 'MUNICIPAL-DECISION-001',
            'authority_role' => 'Municipal Revenue Authority',
            'decided_at' => '2026-08-18T09:00:00+08:00',
        ],
    ]];
    $report = app(VerifyFinancialCalibrationSuite::class)->handle([$withAuthority], 'calibration-suite-authority-002');

    expect($report)
        ->summary->future_policy_accepted->toBe(1)
        ->summary->future_policy_pending->toBe(0)
        ->specimens->{0}->future_policy->assertions_executed->toBeFalse()
        ->safety->financial_policy_activated->toBeFalse();
});

test('rejected future policy requires authority and municipal reconciliation evidence remains non executable', function () {
    $missingAuthority = financialCalibrationManifest();
    $missingAuthority['future_policy_assertions'] = [
        ['key' => 'inclusive_due_date_delinquency_trigger', 'authorization_status' => 'rejected', 'authority' => null],
    ];

    expect(fn () => app(VerifyFinancialCalibrationSuite::class)->handle([$missingAuthority], 'calibration-suite-rejection-001'))
        ->toThrow(RuntimeException::class, 'requires authority evidence');

    $withEvidence = financialCalibrationManifest();
    $withEvidence['future_policy_assertions'] = [[
        'key' => 'following_day_delinquency_trigger',
        'authorization_status' => 'pending',
        'authority' => null,
        'reconciliation_evidence' => [
            'evidence_reference' => 'MUNICIPAL-CLARIFICATION-001',
            'source_role' => 'Municipality IT Head',
            'recorded_at' => '2026-08-17T00:00:00+08:00',
            'evidence_status' => 'municipality_operational_clarification',
            'evidence_sha256' => hash('sha256', 'clarification'),
            'policy_authority_sufficient' => false,
        ],
    ]];

    $report = app(VerifyFinancialCalibrationSuite::class)->handle([$withEvidence], 'calibration-suite-reconciliation-001');

    expect($report)
        ->summary->future_policy_pending->toBe(1)
        ->specimens->{0}->future_policy->assertions->{0}->reconciliation_evidence->evidence_status
        ->toBe('municipality_operational_clarification')
        ->specimens->{0}->future_policy->assertions->{0}->reconciliation_evidence->policy_authority_sufficient
        ->toBeFalse()
        ->specimens->{0}->future_policy->assertions->{0}->executed->toBeFalse()
        ->safety->financial_policy_activated->toBeFalse();
});

test('suite rejects sensitive identity fields in specimen manifests', function () {
    $manifest = financialCalibrationManifest(['application_number' => 'PRIVATE-APPLICATION']);

    expect(fn () => app(VerifyFinancialCalibrationSuite::class)->handle([$manifest], 'calibration-suite-sensitive-001'))
        ->toThrow(RuntimeException::class, 'forbidden sensitive field [application_number]');
});

test('command discovers private specimens and writes an immutable payload-safe run', function () {
    Storage::fake('local');
    Storage::disk('local')->put(
        'financial-calibrations/specimens/CAL-2026-999/manifest.json',
        json_encode(financialCalibrationManifest(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );

    $this->artisan('financial:verify-calibrations', [
        '--run-id' => 'calibration-suite-command-001',
        '--json' => true,
    ])->assertSuccessful()->expectsOutputToContain('"historical_reproduction_passed": 1');

    $summaryPath = 'financial-calibrations/runs/calibration-suite-command-001/summary.json';
    $firstHash = hash('sha256', Storage::disk('local')->get($summaryPath));

    $this->artisan('financial:verify-calibrations', [
        '--run-id' => 'calibration-suite-command-001',
        '--json' => true,
    ])->assertSuccessful();

    $summary = json_decode(Storage::disk('local')->get($summaryPath), true, 512, JSON_THROW_ON_ERROR);
    expect(hash('sha256', Storage::disk('local')->get($summaryPath)))
        ->toBe($firstHash)
        ->and($summary['summary']['future_policy_suite_status'])->toBe('blocked_pending_authority')
        ->and($summary['safety']['formulas_evaluated'])->toBeFalse()
        ->and($summary['safety']['financial_policy_activated'])->toBeFalse()
        ->and(Storage::disk('local')->visibility($summaryPath))->toBe('private');
});
