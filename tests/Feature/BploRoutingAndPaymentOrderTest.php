<?php

use App\Actions\ExecutePersistedLifecycleScenario;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\PaperlessPaymentOrder;
use App\Models\PermitApplication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Artisan::call('bpls:install');
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);
});

test('BPLO owns an explicit post-lodging situational route without changing the applicant declaration', function (): void {
    $application = PermitApplication::query()->with([
        'declaration',
        'bploRoutingDetermination.determinedBy',
        'bploRoutingDetermination.works.lineOfBusiness',
    ])->sole();
    $routing = $application->bploRoutingDetermination;

    expect($routing)->not->toBeNull()
        ->and($routing->determined_at->greaterThanOrEqualTo($application->submitted_at))->toBeTrue()
        ->and($routing->determinedBy->can('business_permit_evaluations.determine_routing'))->toBeTrue()
        ->and(data_get($routing->application_facts_snapshot, 'applicant_declaration_preserved'))->toBeTrue()
        ->and($routing->application_facts_snapshot['declared_lines'])->toHaveCount(2)
        ->and($routing->works->pluck('office_code')->unique()->sort()->values()->all())->toBe([
            'assessor', 'engineering', 'health', 'menro',
        ])
        ->and($routing->works->every(fn ($work): bool => data_get($work->context_snapshot, 'automatic_lob_rule') === false))->toBeTrue();
});

test('only current issued office determinations enter the Assessment exactly once and reconcile', function (): void {
    $application = PermitApplication::query()->with(['paperlessPaymentOrders.lines', 'assessments.lines'])->sole();
    $orders = $application->paperlessPaymentOrders;
    $assessment = $application->assessments->sole();
    $orderLines = $orders->flatMap->lines;
    $assessmentOrderLines = $assessment->lines->whereNotNull('paperless_payment_order_line_id');

    expect($orders)->toHaveCount(6)
        ->and($orders->every(fn (PaperlessPaymentOrder $order): bool => $order->status === 'issued' && $order->superseded_at === null))->toBeTrue()
        ->and($orders->sum('total_amount_cents'))->toBe(87_000)
        ->and($orderLines)->toHaveCount(6)
        ->and($assessmentOrderLines)->toHaveCount(6)
        ->and($assessmentOrderLines->pluck('paperless_payment_order_line_id')->sort()->values()->all())
        ->toBe($orderLines->pluck('id')->sort()->values()->all())
        ->and($assessment->lines->whereNull('paperless_payment_order_line_id'))->toHaveCount(1)
        ->and($assessment->lines->sum('amount_cents'))->toBe($assessment->total_amount_cents)
        ->and(function () use ($assessmentOrderLines): void {
            $attributes = $assessmentOrderLines->first()->getAttributes();
            unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
            DB::table('assessment_lines')->insert($attributes);
        })->toThrow(QueryException::class);
});

test('issued Payment Order financial facts are immutable and cancellation remains unresolved', function (): void {
    $order = PaperlessPaymentOrder::query()->with('lines')->firstOrFail();

    expect(fn () => $order->forceFill(['total_amount_cents' => $order->total_amount_cents + 1])->save())
        ->toThrow(LogicException::class, 'immutable')
        ->and(fn () => $order->lines->first()->forceFill(['amount_cents' => 1])->save())
        ->toThrow(LogicException::class, 'immutable')
        ->and(data_get($order->source_snapshot, 'cancellation_policy'))->toBe('unresolved_not_implemented');
});
