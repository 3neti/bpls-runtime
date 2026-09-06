<?php

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('citizens can add and download private supporting evidence for an owned draft', function () {
    Storage::fake('local');

    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::UploadOwnPermitApplicationDocuments,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationDocuments,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
        'type' => PermitApplicationType::New,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.documents.store', $application), [
            'document_type' => 'dti_registration',
            'file' => UploadedFile::fake()->create('registration.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('citizen.permit-applications.show', $application));

    $document = PermitApplicationDocument::query()->sole();

    expect($document->permit_application_id)->toBe($application->id)
        ->and($document->uploaded_by_id)->toBe($citizen->id)
        ->and($document->label)->toBe('DTI Registration')
        ->and($document->document_type)->toBe('dti_registration')
        ->and($document->version)->toBe(1)
        ->and($document->original_name)->toBe('registration.pdf')
        ->and($document->source_snapshot['submitted_via'])->toBe('citizen_portal')
        ->and($document->source_snapshot['document_type_catalog_revision'])->toBe('ipil_application_document_types_v1')
        ->and($document->source_snapshot['requirement_catalog_status'])->toBe('unresolved');
    Storage::disk('local')->assertExists($document->path);

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/permit-applications/Show')
            ->where('permitApplication.documents.0.id', $document->id)
            ->where('permitApplication.documents.0.label', 'DTI Registration')
            ->where('permitApplication.documents.0.version', 1)
            ->where('permitApplication.documents.0.uploaded_by', 'You')
            ->where('permitApplication.documentary_readiness.received_document_count', 1)
            ->where('permitApplication.documentary_readiness.requirement_catalog_status', 'unresolved')
            ->where('permitApplication.documentary_readiness.submission_readiness', 'not_determined')
            ->where('permitApplication.can_upload_documents', true)
            ->where('permitApplication.can_view_documents', true)
        );

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.documents.download', [$application, $document]))
        ->assertOk()
        ->assertDownload('registration.pdf');
});

test('citizen supporting evidence accepts configured types only and derives its label', function () {
    Storage::fake('local');

    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::UploadOwnPermitApplicationDocuments,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.documents.store', $application), [
            'label' => 'Citizen cannot name this evidence',
            'document_type' => 'invented_document_type',
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
        ])
        ->assertSessionHasErrors(['label', 'document_type', 'file']);

    expect(PermitApplicationDocument::query()->count())->toBe(0);
});

test('adding a singleton document type replaces the active pill without rewriting its history', function () {
    Storage::fake('local');

    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::UploadOwnPermitApplicationDocuments,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationDocuments,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
        'type' => PermitApplicationType::New,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);

    foreach (['dti-original.pdf', 'dti-replacement.pdf'] as $filename) {
        $this->actingAs($citizen)
            ->post(route('citizen.permit-applications.documents.store', $application), [
                'document_type' => 'dti_registration',
                'file' => UploadedFile::fake()->create($filename, 12, 'application/pdf'),
                'return_to' => 'edit',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('citizen.permit-applications.edit', $application));
    }

    $documents = PermitApplicationDocument::query()->orderBy('version')->get();

    expect($documents)->toHaveCount(2)
        ->and($documents[0]->version)->toBe(1)
        ->and($documents[0]->removed_at)->not->toBeNull()
        ->and($documents[1]->version)->toBe(2)
        ->and($documents[1]->removed_at)->toBeNull()
        ->and($documents[0]->media)->not->toBeNull()
        ->and($documents[1]->media)->not->toBeNull();

    Storage::disk('local')->assertExists($documents[0]->path);
    Storage::disk('local')->assertExists($documents[1]->path);
});

test('citizen document access is permission and ownership scoped', function () {
    Storage::fake('local');

    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::UploadOwnPermitApplicationDocuments,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationDocuments,
    ], UserRole::Citizen);
    $otherCitizen = User::factory()->create(['role_id' => $citizen->role_id]);
    $otherApplication = PermitApplication::factory()->for($otherCitizen, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
    ]);
    $document = PermitApplicationDocument::factory()->for($otherApplication)->create([
        'path' => "permit-applications/{$otherApplication->id}/documents/other.pdf",
        'original_name' => 'other.pdf',
    ]);
    Storage::disk('local')->put($document->path, '%PDF-1.4 other citizen evidence');

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.documents.store', $otherApplication), [
            'document_type' => 'dti_registration',
            'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.documents.download', [$otherApplication, $document]))
        ->assertNotFound();

    $citizenOnlyRole = Role::factory()->create();
    $citizenOnlyRole->permissions()->attach(
        Permission::query()->where('code', UserPermission::AccessCitizen->value)->sole(),
    );
    $citizenWithoutDocumentPermissions = User::factory()->for($citizenOnlyRole)->create();
    $ownedApplication = PermitApplication::factory()->for($citizenWithoutDocumentPermissions, 'submittedBy')->create([
        'application_number' => null,
        'status' => PermitApplicationStatus::Draft,
    ]);
    $ownedDocument = PermitApplicationDocument::factory()->for($ownedApplication)->create([
        'path' => "permit-applications/{$ownedApplication->id}/documents/owned.pdf",
        'original_name' => 'owned.pdf',
    ]);
    Storage::disk('local')->put($ownedDocument->path, '%PDF-1.4 owned evidence');

    $this->actingAs($citizenWithoutDocumentPermissions)
        ->post(route('citizen.permit-applications.documents.store', $ownedApplication), [
            'document_type' => 'dti_registration',
            'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    $this->actingAs($citizenWithoutDocumentPermissions)
        ->get(route('citizen.permit-applications.documents.download', [$ownedApplication, $ownedDocument]))
        ->assertForbidden();

    expect(PermitApplicationDocument::query()->count())->toBe(2);
});

test('citizens cannot add supporting evidence after municipal processing begins', function () {
    Storage::fake('local');

    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::UploadOwnPermitApplicationDocuments,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationDocuments,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->for($citizen, 'submittedBy')->create([
        'application_number' => 'APP-PROCESSED-DOCUMENT-001',
        'status' => PermitApplicationStatus::PendingPayment,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);

    $this->actingAs($citizen)
        ->post(route('citizen.permit-applications.documents.store', $application), [
            'document_type' => 'dti_registration',
            'file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');

    $this->actingAs($citizen)
        ->get(route('citizen.permit-applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permitApplication.can_upload_documents', false)
            ->where('permitApplication.documentary_readiness.submission_readiness', 'not_determined')
        );

    expect(PermitApplicationDocument::query()->count())->toBe(0);
});
