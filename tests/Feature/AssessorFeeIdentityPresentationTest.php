<?php

use App\Actions\BuildBploRoutingTask;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use App\Models\FeeRuleOfficeAssignment;
use App\Models\PermitApplication;

it('keeps duplicate assessor identities and exposes provenance in the office menu', function (): void {
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);

    foreach ([
        [
            'effective_from' => '2025-01-01',
            'metadata' => [
                'catalog_version' => 'ipil-municipal-fees-v1',
                'price_list_source_classification' => 'migrated_legacy_uat',
                'responsible_office_code' => 'assessor',
                'application_types' => ['new'],
            ],
        ],
        [
            'effective_from' => '2026-01-01',
            'metadata' => [
                'catalog_version' => 'nelson-concerned-office-preview-v1',
                'semantic_classification' => 'synthetic_only',
                'responsible_office_code' => 'assessor',
                'application_types' => ['new'],
            ],
        ],
    ] as $attributes) {
        $fee = FeeRule::factory()->create([
            'code' => 'LAB-IPIL-ASSESSOR-SERVICE-FEE',
            'name' => 'Assessor Service Fee',
            'category' => FeeRuleCategory::Fee,
            'scope' => FeeRuleScope::Application,
            'determination_channel' => FeeDeterminationChannel::ConcernedOfficePaymentOrder,
            'calculation_type' => FeeRuleCalculationType::Fixed,
            'effective_from' => $attributes['effective_from'],
            'amount_cents' => 0,
            ...$attributes,
        ]);
        FeeRuleOfficeAssignment::create([
            'fee_rule_id' => $fee->id,
            'office_code' => 'assessor',
            'office_label' => 'Municipal Assessor',
            'source' => 'test',
            'metadata' => [],
        ]);
    }

    config()->set('ipil_references.concerned_offices.items', [
        [
            'code' => 'assessor',
            'label' => 'Municipal Assessor',
            'fee_rule_codes' => ['LAB-IPIL-ASSESSOR-SERVICE-FEE'],
        ],
        ['code' => 'health', 'label' => 'Municipal Health Office'],
    ]);

    $task = app(BuildBploRoutingTask::class)->handle($application, null)->toArray();
    $options = collect(data_get($task, 'financial_editor.office_fee_options.assessor'))
        ->where('code', 'LAB-IPIL-ASSESSOR-SERVICE-FEE')
        ->values();

    expect($options)->toHaveCount(2)
        ->and($options->pluck('name')->unique())->toHaveCount(2)
        ->and($options->pluck('provenance.catalog_version')->sort()->values()->all())->toBe([
            'ipil-municipal-fees-v1',
            'nelson-concerned-office-preview-v1',
        ])
        ->and($options->pluck('provenance.classification')->sort()->values()->all())->toBe([
            'migrated_legacy_uat',
            'synthetic_only',
        ])
        ->and(collect(data_get($task, 'financial_editor.office_fee_options.health'))
            ->where('code', 'LAB-IPIL-ASSESSOR-SERVICE-FEE'))
        ->toHaveCount(0)
        ->and($application->paperlessPaymentOrders()->count())->toBe(0);
});
