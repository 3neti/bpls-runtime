<?php

use App\Actions\AuthorizeRoutedOfficeActor;
use App\Actions\PostPaymentCertificationEligibility;
use App\Enums\UserPermission;
use App\Models\InstitutionalPosition;
use App\Models\InstitutionalPositionAssignment;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('bpls:install');
});

test('active institutional assignment authorizes a preview office actor without broadening role authority', function () {
    $previewRole = Role::factory()->create(['code' => 'preview_assessor_fixture']);
    $previewRole->syncPermissions([
        Permission::query()->where('code', UserPermission::ContributeBusinessPermitEvaluations->value)->firstOrFail(),
    ]);
    $actor = User::factory()->create();
    $actor->assignRole($previewRole);
    $position = InstitutionalPosition::query()->where('code', 'municipal_assessor')->firstOrFail();
    InstitutionalPositionAssignment::query()->create([
        'user_id' => $actor->id,
        'institutional_position_id' => $position->id,
        'status' => 'active',
        'reason' => 'Synthetic bounded correction fixture.',
        'assigned_at' => now(),
    ]);

    $application = PermitApplication::factory()->create();

    expect(app(AuthorizeRoutedOfficeActor::class)->allows($application, 'assessor', $actor))->toBeTrue()
        ->and(app(AuthorizeRoutedOfficeActor::class)->allows($application, 'engineering', $actor))->toBeFalse()
        ->and($actor->hasRole('assessor'))->toBeFalse()
        ->and($actor->can(UserPermission::ApproveAssessments->value))->toBeFalse();
});

test('preview controls require the protected engineering context', function () {
    $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

    expect($middleware)
        ->toContain('StakeholderPreviewPersona::Management')
        ->toContain('$showEngineeringControls = $cleanroomActor !== null')
        ->toContain("str_contains(\$item['href'], 'lifecycle-laboratory')");
});

test('concerned-office payment order presentation does not wait for evaluation', function () {
    $page = file_get_contents(resource_path('js/pages/business-permit-evaluations/Show.vue'));

    expect($page)
        ->toContain('Concerned-office Payment Orders are recorded here')
        ->toContain('first; Evaluation and Assessment follow their')
        ->not->toContain('The Evaluation has not started, so no concerned office')
        ->not->toContain('Waiting for an authorized Assessment Officer to start');
});

test('ordinary post-payment certification eligibility admits the Gate 10 local hostname', function () {
    config()->set('app.url', 'http://bpls-gate10.test');
    config()->set('stakeholder_preview.mode', true);
    config()->set('stakeholder_preview.production_migration_enabled', false);
    config()->set('stakeholder_preview.production_integrations', 'disabled');

    $application = PermitApplication::factory()->create([
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]],
    ]);

    expect(app(PostPaymentCertificationEligibility::class)->ordinaryUat($application))->toBeTrue();
});
