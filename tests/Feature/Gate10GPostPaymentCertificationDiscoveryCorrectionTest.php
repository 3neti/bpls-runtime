<?php

use App\Actions\BuildMunicipalWorkInbox;
use App\Actions\RecordPostPaymentOfficeCertification;
use App\Models\InstitutionalPosition;
use App\Models\InstitutionalPositionAssignment;
use App\Models\PaperlessPaymentOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/OrdinaryCertificationFixture.php';

function gate10PreviewCertificationOfficer(string $office): User
{
    $user = User::factory()->create();
    foreach (['staff.access', 'business_permit_evaluations.view'] as $code) {
        Permission::query()->firstOrCreate(['code' => $code], ['name' => $code, 'guard_name' => 'web']);
    }
    $previewRole = Role::factory()->create(['code' => 'preview_'.$office, 'name' => 'Preview '.$office]);
    $capabilityRole = Role::factory()->create(['code' => $office, 'name' => $office]);
    $previewRole->permissions()->sync(Permission::whereIn('code', ['staff.access', 'business_permit_evaluations.view'])->pluck('id'));
    $user->roles()->attach($previewRole);
    $position = InstitutionalPosition::factory()->for($capabilityRole, 'capabilityRole')->create();
    InstitutionalPositionAssignment::query()->create([
        'user_id' => $user->id,
        'institutional_position_id' => $position->id,
        'status' => 'active',
        'assigned_at' => now(),
        'reason' => 'Gate 10G bounded certification-discovery fixture.',
    ]);

    return $user->fresh();
}

test('ordinary inbox discovers missed certification tasks from complete canonical evidence', function (string $office) {
    [$application, $collection, $schedule, $assessment] = ordinaryCertificationFixture();
    $application->postPaymentOfficeCertifications()->delete();
    $actor = gate10PreviewCertificationOfficer($office);
    $financialBefore = [
        $collection->fresh()->getRawOriginal(),
        $schedule->fresh()->getRawOriginal(),
        $assessment->fresh()->getRawOriginal(),
        $collection->receipts()->orderBy('id')->get()->map->getRawOriginal()->all(),
        $collection->allocations()->orderBy('id')->get()->map->getRawOriginal()->all(),
    ];

    $items = app(BuildMunicipalWorkInbox::class)->handle($actor)['items'];
    $task = $items->where('task_type', 'post_payment_certification')->sole();
    $certification = $application->postPaymentOfficeCertifications()->where('office_code', $office)->sole();

    expect($actor->hasRole($office))->toBeFalse()
        ->and($application->postPaymentOfficeCertifications()->count())->toBe(4)
        ->and($task['action_url'])->toBe(route('staff.post-payment-certifications.show', $certification, false))
        ->and(data_get($application->metadata, 'lifecycle_cleanroom.run_id'))->toBeNull();

    $this->actingAs($actor)->get($task['action_url'])->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('post-payment-certifications/Show')
            ->where('certification.receipt_number', $certification->receipt->receipt_number)
            ->where('certification.amount_cents', $certification->receipt->amount_cents));
    $this->post(route('staff.post-payment-certifications.store', $certification), [
        'result' => 'certified',
        'remarks' => 'Reviewed bounded synthetic receipt evidence.',
    ])->assertRedirect();

    $completed = $certification->fresh()->getRawOriginal();
    $this->post(route('staff.post-payment-certifications.store', $certification), ['result' => 'certified'])->assertRedirect();
    expect($certification->fresh()->getRawOriginal())->toBe($completed)
        ->and(app(BuildMunicipalWorkInbox::class)->handle($actor)['items']->where('task_type', 'post_payment_certification'))->toHaveCount(0)
        ->and([
            $collection->fresh()->getRawOriginal(),
            $schedule->fresh()->getRawOriginal(),
            $assessment->fresh()->getRawOriginal(),
            $collection->receipts()->orderBy('id')->get()->map->getRawOriginal()->all(),
            $collection->allocations()->orderBy('id')->get()->map->getRawOriginal()->all(),
        ])->toBe($financialBefore);
})->with(['assessor', 'engineering', 'health', 'menro']);

test('certification discovery remains office-specific for canonical positions', function () {
    [$application] = ordinaryCertificationFixture();
    $application->postPaymentOfficeCertifications()->delete();
    $assessor = gate10PreviewCertificationOfficer('assessor');
    $engineering = gate10PreviewCertificationOfficer('engineering');
    $assessorTask = app(BuildMunicipalWorkInbox::class)->handle($assessor)['items']->where('task_type', 'post_payment_certification')->sole();
    $assessorCertification = $application->postPaymentOfficeCertifications()->where('office_code', 'assessor')->sole();

    $this->actingAs($engineering)->get($assessorTask['action_url'])->assertForbidden();
    $this->post(route('staff.post-payment-certifications.store', $assessorCertification), ['result' => 'certified'])->assertForbidden();
    expect($assessorCertification->fresh()->status)->toBe('pending')
        ->and(app(BuildMunicipalWorkInbox::class)->handle($engineering)['items']->where('task_type', 'post_payment_certification')->sole()['office_label'])->toBe('engineering');
});

test('certification discovery fails closed when canonical evidence is incomplete', function (string $damage) {
    [$application, $collection, $schedule] = ordinaryCertificationFixture();
    $application->postPaymentOfficeCertifications()->delete();
    $actor = gate10PreviewCertificationOfficer('assessor');

    match ($damage) {
        'unpaid_schedule' => $schedule->update(['paid_amount_cents' => 0]),
        'no_collection' => $collection->delete(),
        'missing_office_receipt' => $collection->receipts()->where('receipt_group_key', 'office:assessor')->sole()->delete(),
        'incomplete_allocation' => $collection->allocations()->where('receipt_group_key', 'office:assessor')->sole()->update(['receipt_id' => null]),
        'unfinalized_payment_order' => PaperlessPaymentOrder::query()
            ->where('permit_application_id', $application->id)
            ->whereIn('bplo_routing_work_id', $application->bploRoutingDetermination->works->where('office_code', 'assessor')->pluck('id'))
            ->update(['status' => 'draft']),
    };

    expect(app(BuildMunicipalWorkInbox::class)->handle($actor)['items']->where('task_type', 'post_payment_certification'))->toHaveCount(0)
        ->and($application->postPaymentOfficeCertifications()->count())->toBe(0);
})->with(['unpaid_schedule', 'no_collection', 'missing_office_receipt', 'incomplete_allocation', 'unfinalized_payment_order']);

test('an actor without a canonical office position cannot discover or execute certification work', function () {
    [$application] = ordinaryCertificationFixture();
    $application->postPaymentOfficeCertifications()->delete();
    $actor = User::factory()->create();
    $permission = Permission::query()->firstOrCreate(['code' => 'staff.access'], ['name' => 'staff.access', 'guard_name' => 'web']);
    $role = Role::factory()->create(['code' => 'preview_without_position']);
    $role->permissions()->sync([$permission->id]);
    $actor->roles()->attach($role);

    expect(app(BuildMunicipalWorkInbox::class)->handle($actor)['items']->where('task_type', 'post_payment_certification'))->toHaveCount(0)
        ->and($application->postPaymentOfficeCertifications()->count())->toBe(0);
});

test('four completed office certifications reconnect Mayoral Authorization readiness', function () {
    [$application] = ordinaryCertificationFixture();
    $application->postPaymentOfficeCertifications()->delete();

    foreach (['assessor', 'engineering', 'health', 'menro'] as $office) {
        $actor = gate10PreviewCertificationOfficer($office);
        $task = app(BuildMunicipalWorkInbox::class)->handle($actor)['items']->where('task_type', 'post_payment_certification')->sole();
        $certification = $application->postPaymentOfficeCertifications()->where('office_code', $office)->sole();
        expect($task['action_url'])->toBe(route('staff.post-payment-certifications.show', $certification, false));
        app(RecordPostPaymentOfficeCertification::class)->handle($certification, $actor, 'certified');
    }

    $mayor = certificationOfficer('mayor_office');
    $assignment = InstitutionalPositionAssignment::where('user_id', $mayor->id)->sole();
    $assignment->position->update(['code' => 'mayors_office_reviewer']);
    config([
        'workflow_uat_authority.mode' => 'synthetic_only',
        'workflow_uat_authority.mayor_assignment_id' => $assignment->id,
    ]);

    expect($application->postPaymentOfficeCertifications()->where('status', 'completed')->count())->toBe(4)
        ->and(app(BuildMunicipalWorkInbox::class)->handle($mayor)['items']->where('task_type', 'permit_issuance')->sole()['task_label'])
        ->toBe('Authorize Business Permit issuance');
});
