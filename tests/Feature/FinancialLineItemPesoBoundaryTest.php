<?php

use App\Actions\AssignTreasuryLinesOfBusiness;
use App\Actions\ConfirmOfficePaymentOrder;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\FeeRule;
use App\Models\LifecycleCleanroomRun;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the shared Nelson editor exposes pesos for office and Treasury entry', function () {
    $editor = file_get_contents(resource_path('js/components/permit-applications/FinancialLineItemEditor.vue'));
    $taskSheet = file_get_contents(resource_path('js/components/permit-applications/BploRoutingTaskSheet.vue'));

    expect($editor)
        ->toContain('aria-label="Amount in pesos"')
        ->toContain('placeholder="0.00"')
        ->toContain('parsePesoAmount(amount.value)')
        ->not->toContain('Amount (centavos)')
        ->and(substr_count($taskSheet, '<FinancialLineItemEditor'))->toBe(3);
});

test('peso editor centavos persist unchanged through office and Treasury actions', function () {
    Storage::fake('local');

    $bplo = userWithPermissions([UserPermission::DetermineBploRouting], UserRole::Admin);
    $assessor = userWithPermissions([UserPermission::ContributeBusinessPermitEvaluations], UserRole::Bplo);
    $treasury = userWithPermissions([UserPermission::CorrectEvaluationLinesOfBusiness], UserRole::Treasury);
    $run = LifecycleCleanroomRun::factory()->for($bplo, 'startedBy')->create([
        'actor_manifest' => [
            'actors' => [
                'assessor' => [
                    'label' => 'Municipal Assessor',
                    'user_id' => $assessor->id,
                    'role_id' => $assessor->primaryRole()->id,
                ],
            ],
            'semantic_classification' => 'synthetic_only',
            'production_liability' => false,
        ],
    ]);
    $application = PermitApplication::factory()->create([
        'application_year' => now()->year,
        'metadata' => [
            'nelson_reconciliation_v1' => ['commissioned_path' => true],
            'lifecycle_cleanroom' => [
                'run_id' => $run->public_id,
                'semantic_classification' => 'synthetic_only',
                'production_liability' => false,
            ],
        ],
    ]);
    $determination = BploRoutingDetermination::factory()
        ->for($application, 'permitApplication')
        ->for($bplo, 'determinedBy')
        ->create();
    $work = BploRoutingWork::factory()->for($determination, 'determination')->create([
        'office_code' => 'assessor',
        'office_label' => 'Municipal Assessor',
    ]);
    $officeFee = FeeRule::factory()->create([
        'code' => 'LAB-IPIL-ASSESSOR-SERVICE-FEE',
        'name' => 'Assessor Service Fee',
        'scope' => FeeRuleScope::Application,
        'category' => FeeRuleCategory::Fee,
        'determination_channel' => FeeDeterminationChannel::ConcernedOfficePaymentOrder,
        'amount_cents' => 0,
        'effective_from' => now()->startOfYear(),
        'metadata' => [
            'responsible_office_code' => 'assessor',
            'manual_amount_required' => true,
        ],
    ]);

    $order = app(ConfirmOfficePaymentOrder::class)->handle($work, [[
        'fee_rule_id' => $officeFee->id,
        'amount_cents' => 10_000,
    ]], $assessor, UploadedFile::fake()->image('assessor-signature.png'));

    expect($order->total_amount_cents)->toBe(10_000)
        ->and($order->lines)->toHaveCount(1)
        ->and($order->lines->sole()->amount_cents)->toBe(10_000)
        ->and(data_get($order->lines->sole()->source_snapshot, 'determined_amount_minor'))->toBe(10_000);

    $lineOfBusiness = LineOfBusiness::factory()->create(['name' => 'Fresh Fish Retailer']);
    $treasuryFee = FeeRule::factory()->create([
        'line_of_business_id' => $lineOfBusiness->id,
        'code' => 'LAB-IPIL-TREASURY-PERMIT-FEE',
        'name' => "Mayor's Permit Fee",
        'scope' => FeeRuleScope::LineOfBusiness,
        'category' => FeeRuleCategory::Fee,
        'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness,
        'amount_cents' => 0,
        'effective_from' => now()->startOfYear(),
        'metadata' => ['manual_amount_required' => true],
    ]);

    $assignments = app(AssignTreasuryLinesOfBusiness::class)->handle($application, [[
        'line_of_business_id' => $lineOfBusiness->id,
        'items' => [[
            'fee_rule_id' => $treasuryFee->id,
            'amount_cents' => 10_050,
        ]],
    ]], $treasury);

    $item = $assignments[0]->items->sole();
    expect($item->determined_amount_cents)->toBe(10_050)
        ->and($item->variance_cents)->toBe(10_050)
        ->and(data_get($item->source_snapshot, 'determined_amount_minor'))->toBe(10_050)
        ->and($application->paperlessPaymentOrders()->count())->toBe(1);
});
