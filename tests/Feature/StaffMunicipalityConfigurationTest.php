<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authorized staff can inspect municipality identity and explicit authority status', function () {
    config()->set('municipality.name', 'Municipality of Ipil');
    config()->set('municipality.province', 'Zamboanga Sibugay');
    config()->set('municipality.system_name', 'Business Permit and Licensing System');
    config()->set('municipality.officials', [
        'municipal_mayor' => [
            'role' => 'Municipal Mayor',
            'name' => 'Configured Mayor',
            'title' => 'Municipal Mayor',
            'configured_authority_claim' => 'verified',
            'effective_from' => '2025-07-01',
            'effective_until' => null,
            'provenance' => [
                'legacy_fields' => ['mayorName', 'mayorTitle'],
                'legacy_source_status' => 'implemented',
                'production_snapshot_status' => 'observed',
            ],
        ],
        'municipal_treasurer' => [
            'role' => 'Municipal Treasurer',
            'name' => 'Configured Treasurer',
            'title' => 'Municipal Treasurer',
            'configured_authority_claim' => 'unverified',
            'effective_from' => null,
            'effective_until' => null,
            'provenance' => [
                'legacy_fields' => ['treasurerName', 'treasurerTitle'],
                'legacy_source_status' => 'implemented',
                'production_snapshot_status' => 'observed',
            ],
        ],
        'bplo_officer' => [
            'role' => 'BPLO Officer',
            'name' => 'Unverified BPLO Officer',
            'title' => 'BPLO Officer',
            'configured_authority_claim' => 'unverified',
            'effective_from' => null,
            'effective_until' => null,
            'provenance' => [
                'legacy_fields' => [],
                'legacy_source_status' => 'not_found_as_platform_setting',
                'production_snapshot_status' => 'not_observed_as_platform_setting',
            ],
        ],
    ]);
    config()->set('municipality.document_associations', [
        [
            'official_key' => 'municipal_mayor',
            'document_type' => 'permit_artifact',
            'relationship' => 'configured_signatory',
            'current_runtime_use' => true,
            'legacy_renderer_status' => 'supported',
            'production_layout_status' => 'not_observed',
        ],
        [
            'official_key' => 'municipal_treasurer',
            'document_type' => 'receipt_template',
            'relationship' => 'template_variable',
            'current_runtime_use' => false,
            'legacy_renderer_status' => 'supported',
            'production_layout_status' => 'observed',
        ],
    ]);
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewMunicipalityConfiguration,
    ], UserRole::Bplo);

    $this->actingAs($operator)
        ->get(route('staff.municipality-configuration.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('municipality/Index')
            ->where('auth.can_view_municipality_configuration', true)
            ->where('identity.municipality_name', 'Municipality of Ipil')
            ->where('identity.province', 'Zamboanga Sibugay')
            ->where('identity.system_name', 'Business Permit and Licensing System')
            ->has('officials', 3)
            ->where('officials.0.key', 'municipal_mayor')
            ->where('officials.0.configuration_status', 'configured')
            ->where('officials.0.configured_authority_claim', 'verified')
            ->where('officials.0.authorized_signatory', false)
            ->where('officials.0.effective_term.status', 'configured_not_authorized')
            ->where('officials.1.key', 'municipal_treasurer')
            ->where('officials.1.effective_term.status', 'not_evidenced')
            ->where('officials.2.key', 'bplo_officer')
            ->where('officials.2.configuration_status', 'placeholder')
            ->has('document_associations', 2)
            ->where('document_associations.0.document_type', 'permit_artifact')
            ->where('document_associations.0.authorizes_signature', false)
            ->where('document_associations.1.production_layout_status', 'observed')
            ->has('authority_chain', 5)
            ->where('authority_chain.0.key', 'configured_official')
            ->where('authority_chain.0.satisfied', true)
            ->where('authority_chain.1.key', 'document_signatory')
            ->where('authority_chain.1.status', 'configuration_evidence_only')
            ->where('authority_chain.2.key', 'authorized_signatory')
            ->where('authority_chain.2.satisfied', false)
            ->where('authority.official_count', 3)
            ->where('authority.configured_official_count', 2)
            ->where('authority.document_association_count', 2)
            ->where('authority.current_document_association_count', 1)
            ->where('authority.effective_term_evidence_count', 1)
            ->where('authority.authorized_signatory_count', 0)
            ->where('authority.permit_issuance_authorized', false)
            ->where('authority.permit_release_authorized', false)
            ->where('authority.legal_effect_authorized', false)
            ->where('source.type', 'runtime_configuration')
            ->where('source.legacy_source_status', 'characterized')
            ->where('source.production_snapshot_status', 'shape_observed_values_not_imported')
            ->where('source.production_settings_record_count', 1)
            ->where('source.effective_dates_evidenced', false)
            ->where('source.persisted_administration', false)
            ->where('source.read_only', true)
        );
});

test('staff without municipality configuration visibility cannot open the surface', function () {
    $operator = userWithPermissions([
        UserPermission::AccessStaff,
    ], UserRole::Bplo);

    $this->actingAs($operator)
        ->get(route('staff.municipality-configuration.index'))
        ->assertForbidden();
});

test('admin can inspect municipality configuration through the runtime role override', function () {
    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'code' => UserRole::Admin->value,
    ]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);

    expect($adminRole->permissions()->count())->toBe(0)
        ->and($admin->can(UserPermission::ViewMunicipalityConfiguration->value))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('staff.municipality-configuration.index'))
        ->assertSuccessful();
});
