<?php

use App\Actions\BuildBploRoutingTask;
use App\Actions\ProvisionalMenroFeeDeterminationProposal;
use App\Actions\RecordProvisionalMenroFeeDetermination;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\FeeRule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\Role;
use Database\Seeders\MunicipalFeeCatalogSeeder;
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
        'reason' => ProvisionalMenroFeeDeterminationProposal::Reason,
    ];
}

function provisionalMenroFixture(): array
{
    Artisan::call('bpls:install');
    Artisan::call('db:seed', ['--class' => MunicipalFeeCatalogSeeder::class]);
    PermitApplication::factory()->count(4)->create();
    $application = PermitApplication::factory()->create([
        'application_year' => 2026,
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);
    $application->business->update(['business_area_square_meters' => 12]);
    PermitApplicationDeclaration::factory()->for($application)->create([
        'snapshot' => [
            'schema_version' => 1,
            'establishment' => [
                'total_employees' => 1,
                'male_employees' => 1,
                'female_employees' => 0,
                'business_area_square_meters' => '12.00',
            ],
        ],
    ]);
    $actor = userWithRole(Role::query()->where('code', 'menro')->sole());
    $determination = BploRoutingDetermination::factory()->for($application)->create();
    BploRoutingWork::factory()->for($determination, 'determination')->create([
        'office_code' => 'menro',
        'office_label' => 'MENRO',
        'context_snapshot' => ['authorized_actor_id' => $actor->id],
    ]);

    return [$application, $actor];
}

it('records the exact provisional MENRO evidence idempotently without a Payment Order', function (): void {
    [$application, $actor] = provisionalMenroFixture();
    expect($application->id)->not->toBe(3);
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
        ->and(data_get($before, 'financial_editor.can_record_menro_determination'))->toBeTrue()
        ->and(data_get($before, 'financial_editor.menro_determination_proposal'))->toMatchArray([
            'scope_label' => 'Application',
            'source_identity' => 176,
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
            'reason' => ProvisionalMenroFeeDeterminationProposal::Reason,
        ]);

    app(RecordProvisionalMenroFeeDetermination::class)->handle($application, $actor, provisionalMenroFacts());
    $after = $builder->handle($application->refresh(), $actor)->toArray();

    expect(data_get($after, 'financial_editor.menro_determination.code'))
        ->toBe('IPIL-LEGACY-98CDCAD9D28055FB')
        ->and(data_get($after, 'financial_editor.menro_determination.id'))->toBeInt()
        ->and(data_get($after, 'financial_editor.menro_determination.source_identity'))->toBe(176)
        ->and(data_get($after, 'financial_editor.menro_determination.basis'))->toBe('business_area_square_meters')
        ->and(data_get($after, 'financial_editor.menro_determination.reason'))->toBe(ProvisionalMenroFeeDeterminationProposal::Reason)
        ->and(data_get($after, 'financial_editor.menro_determination.production_authority'))->toBeFalse()
        ->and(data_get($after, 'financial_editor.menro_determination.warning'))->toContain('not municipal policy')
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

it('derives eligibility from canonical application and rule facts and fails closed on ambiguity', function (): void {
    [$application] = provisionalMenroFixture();
    $proposal = app(ProvisionalMenroFeeDeterminationProposal::class);

    expect($proposal->forApplication($application))->not->toBeNull();

    $application->business->update(['business_area_square_meters' => null]);
    expect($proposal->forApplication($application->refresh()))->toBeNull();

    $application->business->update(['business_area_square_meters' => '10.50']);
    expect($proposal->forApplication($application->refresh()))->toBeNull();

    $application->business->update(['business_area_square_meters' => '12.00']);
    $overlap = FeeRule::query()->findOrFail(ProvisionalMenroFeeDeterminationProposal::FeeRuleId)
        ->ranges()->create([
            'min_basis_cents' => 1200,
            'max_basis_cents' => 1200,
            'amount_cents' => 999999,
        ]);
    expect($proposal->forApplication($application->refresh()))->toBeNull();
    $overlap->delete();

    $application->update(['metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => false]]]);
    expect($proposal->forApplication($application->refresh()))->toBeNull();

    $application->update([
        'application_year' => 2025,
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);
    expect($proposal->forApplication($application->refresh()))->toBeNull();

    $application->update(['application_year' => 2026, 'type' => 'renewal']);
    expect($proposal->forApplication($application->refresh()))->toBeNull();

    $application->update(['type' => 'new']);
    BploRoutingWork::query()->whereHas(
        'determination',
        fn ($query) => $query->where('permit_application_id', $application->id),
    )->delete();
    expect($proposal->forApplication($application->refresh()))->toBeNull();
});
