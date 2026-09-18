<?php

use App\Enums\UserPermission;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized staff can record private supporting evidence for a permit application', function (string $disk) {
    config(['filesystems.application_documents_disk' => $disk]);
    Storage::fake($disk);

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::CreatePermitApplications,
    ]);
    $application = PermitApplication::factory()->create([
        'submitted_by_id' => $user->id,
        'application_number' => 'APP-DOCUMENT-001',
    ]);

    $this->actingAs($user)
        ->post(route('staff.permit-applications.documents.store', $application), [
            'label' => 'Business registration evidence',
            'file' => UploadedFile::fake()->create('registration.pdf', 120, 'application/pdf'),
            'remarks' => 'Received for intake review.',
        ])
        ->assertRedirect(route('staff.permit-applications.show', $application));

    $document = PermitApplicationDocument::query()->sole();

    expect($document->permit_application_id)->toBe($application->id)
        ->and($document->uploaded_by_id)->toBe($user->id)
        ->and($document->label)->toBe('Business registration evidence')
        ->and($document->original_name)->toBe('registration.pdf')
        ->and($document->storage_disk)->toBe($disk)
        ->and($document->media->disk)->toBe($disk)
        ->and($document->source_snapshot['requirement_catalog_status'])->toBe('unresolved')
        ->and($document->source_snapshot['policy_note'])->toContain('does not establish statutory sufficiency');
    Storage::disk($disk)->assertExists($document->path);

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permit-applications/Show')
            ->where('permitApplication.documents.0.id', $document->id)
            ->where('permitApplication.documents.0.label', 'Business registration evidence')
            ->where('permitApplication.documents.0.view_url', route('staff.permit-applications.documents.view', [$application, $document], false))
            ->where('permitApplication.documents.0.download_url', route('staff.permit-applications.documents.download', [$application, $document], false))
            ->where('permitApplication.timeline.1.key', "document-recorded:{$document->id}")
            ->where('can.upload_documents', true)
        );
})->with(['local', 's3']);

test('supporting evidence upload validates file type and required label', function () {
    Storage::fake('local');

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::CreatePermitApplications,
    ]);
    $application = PermitApplication::factory()->create();

    $this->actingAs($user)
        ->post(route('staff.permit-applications.documents.store', $application), [
            'label' => '',
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
        ])
        ->assertSessionHasErrors(['label', 'file']);

    expect(PermitApplicationDocument::query()->count())->toBe(0);
});

test('staff without intake authority cannot record supporting evidence', function () {
    Storage::fake('local');

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $application = PermitApplication::factory()->create();

    $this->actingAs($user)
        ->post(route('staff.permit-applications.documents.store', $application), [
            'label' => 'Unauthorized evidence',
            'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    expect(PermitApplicationDocument::query()->count())->toBe(0);
});

test('supporting evidence cannot be added after the permit application reaches a terminal state', function () {
    Storage::fake('local');

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::CreatePermitApplications,
    ]);
    $application = PermitApplication::factory()->create([
        'metadata' => [
            'terminal_state' => [
                'can_continue' => false,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('staff.permit-applications.documents.store', $application), [
            'label' => 'Late evidence',
            'file' => UploadedFile::fake()->create('late-evidence.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.can_continue', false)
            ->where('can.upload_documents', false)
        );

    expect(PermitApplicationDocument::query()->count())->toBe(0);
});

test('authorized staff can download only documents belonging to the requested application', function () {
    Storage::fake('local');
    Storage::fake('s3');
    config(['filesystems.application_documents_disk' => 's3']);

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);
    $application = PermitApplication::factory()->create();
    $otherApplication = PermitApplication::factory()->create();
    $document = PermitApplicationDocument::factory()->for($application)->create([
        'path' => "permit-applications/{$application->id}/documents/evidence.pdf",
        'original_name' => 'evidence.pdf',
    ]);
    Storage::disk('local')->put($document->path, '%PDF-1.4 supporting evidence');

    $this->actingAs($user)
        ->get(route('staff.permit-applications.documents.download', [$application, $document]))
        ->assertOk()
        ->assertDownload('evidence.pdf');

    $viewResponse = $this->actingAs($user)
        ->get(route('staff.permit-applications.documents.view', [$application, $document]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($viewResponse->headers->get('content-disposition'))->toContain('inline');
    expect($viewResponse->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and($viewResponse->headers->get('cache-control'))->toContain('private')
        ->and($viewResponse->headers->get('cache-control'))->toContain('no-store');

    $this->actingAs($user)
        ->get(route('staff.permit-applications.documents.download', [$otherApplication, $document]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('staff.permit-applications.documents.view', [$otherApplication, $document]))
        ->assertNotFound();

    $unauthorizedRole = Role::factory()->create();
    $unauthorizedRole->permissions()->attach(
        Permission::query()->where('code', UserPermission::AccessStaff->value)->sole(),
    );
    $unauthorized = userWithRole($unauthorizedRole);

    $this->actingAs($unauthorized)
        ->get(route('staff.permit-applications.documents.view', [$application, $document]))
        ->assertForbidden();

    $unsupported = PermitApplicationDocument::factory()->for($application)->create([
        'path' => "permit-applications/{$application->id}/documents/legacy.html",
        'original_name' => 'legacy.html',
        'mime_type' => 'text/html',
    ]);
    Storage::disk('local')->put($unsupported->path, '<script>window.activeContent = true</script>');

    $this->actingAs($user)
        ->get(route('staff.permit-applications.documents.view', [$application, $unsupported]))
        ->assertStatus(415);
    $this->actingAs($user)
        ->get(route('staff.permit-applications.documents.download', [$application, $unsupported]))
        ->assertDownload('legacy.html');
});
