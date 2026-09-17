<?php

use App\Actions\RecordProvisionalMenroFeeDetermination;
use App\Actions\BuildBploRoutingTask;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\FeeRule;
use App\Models\PermitApplication;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;

function provisionalMenroFacts(): array
{
    return [
        'scope' => 'application',
        'fee_rule_id' => 176,
        'code' => 'IPIL-LEGACY-98CDCAD9D28055FB',
        'basis' => 'business_area_square_meters',
        'application_area_square_meters' => 12,
        'calculation_basis_centi_square_meters' => 1200,
        'operative_range_min_centi_square_meters' => 1100,
        'operative_range_max_centi_square_meters' => 1600,
        'amount_minor' => 250000,
        'schedule_version' => 'ipil-municipal-fees-v1',
        'source_evidence' => 'LIVE-APP-001',
        'classification' => 'PROVISIONAL_UAT_ONLY',
        'production_authority' => false,
        'reason' => RecordProvisionalMenroFeeDetermination::Reason,
    ];
}

function provisionalMenroFixture(): array
{
    Artisan::call('bpls:install');
    PermitApplication::factory()->create();
    PermitApplication::factory()->create();
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);
    $actor = userWithRole(Role::query()->where('code', 'menro')->sole());
    $determination = BploRoutingDetermination::factory()->for($application)->create();
    BploRoutingWork::factory()->for($determination, 'determination')->create([
        'office_code' => 'menro',
        'office_label' => 'MENRO',
        'context_snapshot' => ['authorized_actor_id' => $actor->id],
    ]);
    FeeRule::unguarded(fn () => FeeRule::query()->firstOrCreate([
        'id' => 176,
    ], [
        'code' => 'IPIL-LEGACY-98CDCAD9D28055FB',
        'name' => 'Solid Waste Management',
        'category' => FeeRuleCategory::Fee,
        'scope' => FeeRuleScope::Application,
        'determination_channel' => FeeDeterminationChannel::ConcernedOfficePaymentOrder,
        'calculation_type' => FeeRuleCalculationType::Range,
        'effective_from' => '2025-01-01',
    ]));

    return [$application, $actor];
}

it('records the exact provisional MENRO evidence idempotently without a Payment Order', function (): void {
    [$application, $actor] = provisionalMenroFixture();
    $action = app(RecordProvisionalMenroFeeDetermination::class);

    $first = $action->handle($application, $actor, provisionalMenroFacts());
    $retry = $action->handle($application->refresh(), $actor, provisionalMenroFacts());

    expect($retry->id)->toBe($first->id)
        ->and($first->fingerprint)->toHaveLength(64)
        ->and($first->amount_minor)->toBe(250000)
        ->and($first->classification)->toBe('PROVISIONAL_UAT_ONLY')
        ->and($first->actor_id)->toBe($actor->id)
        ->and($first->fresh()->fingerprint)->toBe($first->fingerprint)
        ->and($application->paperlessPaymentOrders()->count())->toBe(0)
        ->and($application->menroFeeDetermination()->count())->toBe(1);
});

it('builds the ordinary MENRO task before and after evidence without financial mutation', function (): void {
    [$application, $actor] = provisionalMenroFixture();
    $builder = app(BuildBploRoutingTask::class);

    $before = $builder->handle($application, $actor)->toArray();
    expect(data_get($before, 'financial_editor.menro_determination'))->toBeNull()
        ->and(data_get($before, 'financial_editor.can_record_menro_determination'))->toBeTrue();

    app(RecordProvisionalMenroFeeDetermination::class)->handle($application, $actor, provisionalMenroFacts());
    $after = $builder->handle($application->refresh(), $actor)->toArray();

    expect(data_get($after, 'financial_editor.menro_determination.code'))
        ->toBe('IPIL-LEGACY-98CDCAD9D28055FB')
        ->and(data_get($after, 'financial_editor.can_record_menro_determination'))->toBeFalse()
        ->and($application->paperlessPaymentOrders()->count())->toBe(0);
});

it('fails closed for a changed authorized fact', function (): void {
    [$application, $actor] = provisionalMenroFixture();
    $facts = provisionalMenroFacts();
    $facts['amount_minor'] = 0;

    expect(fn () => app(RecordProvisionalMenroFeeDetermination::class)->handle($application, $actor, $facts))
        ->toThrow(LogicException::class, 'amount_minor');
});
