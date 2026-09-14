<?php

use App\Actions\AssignTreasuryLinesOfBusiness;
use App\Actions\BuildBploRoutingTask;
use App\Assessment\ProvisionalTreasuryEnterpriseSchedule;
use App\Enums\UserPermission;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\FeeRule;
use App\Models\PaperlessPaymentOrder;
use App\Models\PaperlessPaymentOrderLine;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\User;
use Database\Seeders\MunicipalFeeCatalogSeeder;
use Illuminate\Validation\ValidationException;

function enterpriseUatFixture(): array
{
    config(['app.url' => config('treasury_enterprise.workflow_url'), 'stakeholder_preview.mode' => true,
        'stakeholder_preview.production_migration_enabled' => false, 'stakeholder_preview.production_integrations' => 'disabled']);
    test()->seed(MunicipalFeeCatalogSeeder::class);
    $application = PermitApplication::factory()->create(['application_year' => 2026, 'type' => 'new',
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]]]);
    PermitApplicationDeclaration::factory()->for($application)->create(['snapshot' => ['establishment' => ['male_employees' => 1, 'female_employees' => 0, 'business_area_square_meters' => '12.00']]]);
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::CorrectEvaluationLinesOfBusiness]);
    $routing = BploRoutingDetermination::factory()->create(['permit_application_id' => $application->id]);
    foreach (['assessor' => 10000, 'engineering' => 15000, 'health' => 30000, 'menro' => 250000] as $office => $amount) {
        $work = BploRoutingWork::factory()->create(['bplo_routing_determination_id' => $routing->id, 'office_code' => $office]);
        $order = PaperlessPaymentOrder::factory()->create(['bplo_routing_work_id' => $work->id, 'permit_application_id' => $application->id, 'total_amount_cents' => $amount]);
        PaperlessPaymentOrderLine::factory()->create(['paperless_payment_order_id' => $order->id, 'amount_cents' => $amount]);
    }
    $lob = collect(app(BuildBploRoutingTask::class)->handle($application, $actor)->financial_editor['line_of_business_options'])->firstWhere('code', 'LOB-3A9A93CA46967768');
    $mayor = collect($lob['default_items'])->firstWhere('code', ProvisionalTreasuryEnterpriseSchedule::FeeCode);
    $selection = ['line_of_business_id' => $lob['id'], 'enterprise_classification' => 'Small',
        'enterprise_schedule_fingerprint' => $mayor['enterprise_schedule']['fingerprint'],
        'items' => collect($lob['default_items'])->map(fn ($item) => ['fee_rule_id' => $item['fee_rule_id'], 'amount_cents' => $item['code'] === ProvisionalTreasuryEnterpriseSchedule::FeeCode ? 100000 : $item['amount_cents']])->all()];

    return [$application, $actor, $selection, $mayor];
}

test('explicit enterprise bands persist deterministic Treasury evidence without rewriting offices or declaration', function (string $classification, int $amount): void {
    [$application, $actor, $selection, $mayor] = enterpriseUatFixture();
    $before = $application->fresh()->getRawOriginal();
    $declaration = $application->declaration->getRawOriginal();
    $orders = $application->paperlessPaymentOrders()->with('lines')->get()->toJson();
    $catalogue = FeeRule::query()->orderBy('id')->get()->toJson();
    expect($mayor['resolution_status'])->toBe('unresolved');
    $selection['enterprise_classification'] = $classification;
    foreach ($selection['items'] as &$item) {
        if ($item['fee_rule_id'] === $mayor['fee_rule_id']) {
            $item['amount_cents'] = $amount;
        }
    }
    unset($item);
    test()->actingAs($actor)->postJson('/staff/permit-applications/'.$application->id.'/treasury-lines-of-business', ['selections' => [$selection]])->assertRedirect();
    $assignment = $application->treasuryLineOfBusinessAssignments()->with('items')->sole();
    $evidence = $assignment->source_snapshot['enterprise_determination'];
    expect($evidence['classification'])->toBe($classification)
        ->and($evidence['determined_by_id'])->toBe($actor->id)
        ->and($evidence['determined_at'])->not->toBeEmpty()
        ->and($evidence['derived_from_applicant_data'])->toBeFalse()
        ->and($evidence['production_policy_authority'])->toBeFalse()
        ->and($evidence['schedule']['version'])->toBe('2026-09-14.v1')
        ->and($assignment->items->sum('determined_amount_cents'))->toBe($amount + 12500)
        ->and($assignment->items->firstWhere('fee_rule_id', $mayor['fee_rule_id'])->variance_cents)->toBe(0)
        ->and($assignment->items->pluck('name')->filter(fn ($name) => str_contains(strtolower($name), 'business tax')))->toBeEmpty()
        ->and($application->fresh()->getRawOriginal())->toBe($before)
        ->and($application->declaration->fresh()->getRawOriginal())->toBe($declaration)
        ->and($application->paperlessPaymentOrders()->with('lines')->get()->toJson())->toBe($orders)
        ->and(FeeRule::query()->orderBy('id')->get()->toJson())->toBe($catalogue);
    $projection = app(BuildBploRoutingTask::class)->handle($application->fresh(), $actor)->financial_editor;
    expect($projection['treasury_assignments'][0]['enterprise_determination']['classification'])->toBe($classification);
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $actor))->toThrow(LogicException::class, 'already been confirmed');
    expect($application->treasuryLineOfBusinessAssignments()->count())->toBe(1)->and($application->assessments()->count())->toBe(0);
})->with(['Micro' => ['Micro', 20000], 'Cottage' => ['Cottage', 50000], 'Small' => ['Small', 100000], 'Medium' => ['Medium', 150000], 'Large' => ['Large', 200000]]);

test('enterprise confirmation rejects incomplete tampered stale or unavailable authority', function (string $case): void {
    [$application, $actor, $selection, $mayor] = enterpriseUatFixture();
    if ($case === 'missing') {
        unset($selection['enterprise_classification']);
    }
    if ($case === 'invalid') {
        $selection['enterprise_classification'] = 'Unknown';
    }
    if ($case === 'stale') {
        $selection['enterprise_schedule_fingerprint'] = str_repeat('0', 64);
    }
    if ($case === 'amount') {
        foreach ($selection['items'] as &$item) {
            if ($item['fee_rule_id'] === $mayor['fee_rule_id']) {
                $item['amount_cents'] = 1;
            }
        } unset($item);
    }
    if ($case === 'omitted') {
        $selection['items'] = array_values(array_filter($selection['items'], fn ($item) => $item['fee_rule_id'] !== $mayor['fee_rule_id']));
    }
    if ($case === 'schedule') {
        config(['treasury_enterprise.schedule' => null]);
    }
    if ($case === 'other_environment') {
        config(['app.url' => 'https://production.example.test']);
    }
    if ($case === 'disabled') {
        config(['stakeholder_preview.mode' => false]);
    }
    $before = $application->fresh()->toJson();
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $actor))->toThrow(ValidationException::class);
    expect($application->treasuryLineOfBusinessAssignments()->count())->toBe(0)->and($application->lines()->count())->toBe(0)
        ->and($application->fresh()->toJson())->toBe($before);
})->with(['missing', 'invalid', 'stale', 'amount', 'omitted', 'schedule', 'other_environment', 'disabled']);

test('enterprise authority does not grant a non Treasury actor permission', function (): void {
    [$application, $actor, $selection] = enterpriseUatFixture();
    $unauthorized = User::factory()->create();
    expect(fn () => app(AssignTreasuryLinesOfBusiness::class)->handle($application, [$selection], $unauthorized))->toThrow(LogicException::class, 'authorized Treasury actor');
});
