<?php

use App\Actions\AttemptPermitApplicationRelease;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\RecordOfficeChargeContribution;
use App\Actions\RecordProvisionalUatPermitDecision;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitClearanceStatus;
use App\Enums\StakeholderPreviewPersona;
use App\Exceptions\UnresolvedPermitReleasePolicy;
use App\Models\Assessment;
use App\Models\PaymentSchedule;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\PermitClearance;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => 'stakeholder_preview_weekend_v1',
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
    ]);
});

test('each concerned office contributes only its own manually assessed charge to the canonical assessment snapshot', function () {
    $application = PermitApplication::factory()->create([
        'status' => PermitApplicationStatus::Assessment,
        'application_year' => 2099,
    ]);
    $recordCharge = app(RecordOfficeChargeContribution::class);
    $expectedTotal = 0;

    foreach ([
        StakeholderPreviewPersona::Engineering,
        StakeholderPreviewPersona::Mpdo,
        StakeholderPreviewPersona::Assessor,
        StakeholderPreviewPersona::Health,
        StakeholderPreviewPersona::Menro,
    ] as $persona) {
        $actor = weekendPreviewPersona($persona);
        $amount = (int) config('stakeholder_preview.weekend_hypothesis.office_charges.'.$persona->officeCode().'.scenario_amount_cents');
        $recordCharge->handle($application, $actor, true, $amount);
        $expectedTotal += $amount;
    }

    $assessmentOfficer = weekendPreviewPersona(StakeholderPreviewPersona::Bplo);
    $assessment = app(CreateAssessmentForPermitApplication::class)->handle($application->fresh(), $assessmentOfficer);

    expect($application->officeChargeContributions()->count())->toBe(5)
        ->and($application->officeChargeContributions()->distinct('submitted_by_id')->count('submitted_by_id'))->toBe(5)
        ->and($assessment->lines)->toHaveCount(5)
        ->and($assessment->total_amount_cents)->toBe($expectedTotal)
        ->and($assessment->lines->pluck('rule_snapshot')->every(
            fn (array $snapshot): bool => $snapshot['semantic_classification'] === 'provisional_uat'
                && $snapshot['real_taxpayer_liability'] === false,
        ))->toBeTrue();
});

test('preview permit completion requires paid and cleared state while real release remains fail closed', function () {
    $application = PermitApplication::factory()->create([
        'status' => PermitApplicationStatus::PendingPayment,
        'application_year' => 2099,
    ]);
    $assessment = Assessment::factory()->create(['permit_application_id' => $application->id]);
    PaymentSchedule::factory()->create([
        'permit_application_id' => $application->id,
        'assessment_id' => $assessment->id,
        'status' => PaymentScheduleStatus::Paid,
        'total_amount_cents' => 40_000,
        'paid_amount_cents' => 40_000,
    ]);
    PermitClearance::factory()->count(2)->create([
        'permit_application_id' => $application->id,
        'status' => PermitClearanceStatus::Completed,
        'completed_at' => now(),
    ]);

    $mayorOffice = weekendPreviewPersona(StakeholderPreviewPersona::MayorOffice);
    $releasing = weekendPreviewPersona(StakeholderPreviewPersona::Releasing);
    $recordDecision = app(RecordProvisionalUatPermitDecision::class);
    $approved = $recordDecision->handle($application, $mayorOffice, 'go');
    $released = $recordDecision->handle($application, $releasing, 'release');

    expect($approved->status)->toBe('approved_for_preview_release')
        ->and($approved->permit_number)->toStartWith('UAT-IPIL-2099-')
        ->and($approved->synthetic_signature_reference)->toBe('SYNTHETIC-UAT-MAYOR-SIGNATURE')
        ->and($released->status)->toBe('released_in_preview')
        ->and($released->semantic_classification)->toBe('provisional_uat')
        ->and($application->fresh()->status)->toBe(PermitApplicationStatus::PendingPayment);

    expect(fn () => app(AttemptPermitApplicationRelease::class)->handle($application->fresh(), $releasing))
        ->toThrow(UnresolvedPermitReleasePolicy::class);
});

function weekendPreviewPersona(StakeholderPreviewPersona $persona): User
{
    $role = Role::factory()->create([
        'code' => $persona->roleCode(),
        'name' => 'Preview '.$persona->label(),
    ]);
    $role->permissions()->sync(collect($persona->permissions())->map(
        fn ($permission): int => Permission::query()->firstOrCreate(
            ['code' => $permission->value],
            ['name' => str($permission->value)->headline()->toString()],
        )->id,
    ));

    return User::factory()->create([
        'name' => $persona->accountName(),
        'email' => $persona->approvedEmail(),
        'email_verified_at' => now(),
        'role_id' => $role->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);
}
