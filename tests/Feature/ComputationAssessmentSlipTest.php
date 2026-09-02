<?php

use App\Actions\BuildComputationAssessmentSlip;
use App\Actions\ExecutePersistedLifecycleScenario;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\Assessment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Artisan::call('bpls:install');
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);
});

test('Computation Assessment Slip V1 preserves the Ipil grammar and projects the immutable Assessment', function (): void {
    $assessment = Assessment::query()->sole();
    $slip = app(BuildComputationAssessmentSlip::class)->handle($assessment);

    expect(data_get($slip, 'institution.title'))->toBe('Computation/Assessment Slip')
        ->and(data_get($slip, 'reference.official_number'))->toBeNull()
        ->and(data_get($slip, 'reference.official_number_status'))->toBe('not_assigned_or_authorized')
        ->and($slip['transaction_type'])->toBe('new')
        ->and($slip['owner_proprietor'])->toBe('Scenario Synthetic Owner')
        ->and($slip['business_name'])->toBe('Scenario Market and Kitchen')
        ->and($slip['line_of_businesses'])->toHaveCount(2)
        ->and($slip['line_sections'])->toHaveCount(2)
        ->and(collect($slip['line_sections'])->pluck('subtotal_amount_cents')->all())->toBe([33_000, 54_000])
        ->and($slip['application_subtotal_amount_cents'])->toBe(35_000)
        ->and($slip['grand_total_amount_cents'])->toBe(122_000)
        ->and($slip['grouped_total_amount_cents'])->toBe(122_000)
        ->and($slip['reconciles'])->toBeTrue()
        ->and($slip['in_words'])->toBe('One Thousand Two Hundred Twenty Pesos')
        ->and(data_get($slip, 'prepared_by.role'))->toBe('Assessment Officer')
        ->and(data_get($slip, 'approved_by.role'))->toBe('Municipal Treasurer')
        ->and($slip['acknowledged_by'])->toBeNull();
});

test('the visible Q1 to Q4 schedule fails closed instead of inventing allocation', function (): void {
    $slip = app(BuildComputationAssessmentSlip::class)->handle(Assessment::query()->sole());
    $quarters = data_get($slip, 'schedule_of_payments.quarters');

    expect(data_get($slip, 'schedule_of_payments.allocation_status'))->toBe('blocked_municipal_fiscal_decision')
        ->and(collect($quarters)->pluck('section')->all())->toBe(['Q1', 'Q2', 'Q3', 'Q4'])
        ->and(collect($quarters)->every(fn (array $quarter): bool => $quarter['amount_cents'] === null && $quarter['due_date'] === null))->toBeTrue()
        ->and(data_get($slip, 'schedule_of_payments.canonical_single_schedule.total_amount_cents'))->toBe(122_000);
});

test('the executable slip component contains no browser-side calculator', function (): void {
    $component = file_get_contents(resource_path('js/components/assessments/ComputationAssessmentSlip.vue'));

    expect($component)->toContain('SCHEDULE OF PAYMENTS')
        ->and($component)->toContain('quarter.section')
        ->and($component)->toContain('BLOCKED — MUNICIPAL FISCAL DECISION')
        ->and($component)->not->toContain('/ 4')
        ->and($component)->not->toContain('reduce(');
});
