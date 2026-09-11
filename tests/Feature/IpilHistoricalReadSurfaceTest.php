<?php

use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $now = now();
    DB::table('ipil_rescue_import_runs')->insert([
        'id' => 'synthetic-gate8', 'corpus_id' => 'synthetic', 'corpus_fingerprint_sha256' => str_repeat('a', 64),
        'mapping_profile' => 'synthetic', 'mapping_profile_identity_sha256' => str_repeat('b', 64), 'seed_plan_id' => 'synthetic',
        'seed_plan_fingerprint_sha256' => str_repeat('c', 64), 'execution_manifest_fingerprint_sha256' => str_repeat('d', 64),
        'authorization_fingerprint_sha256' => str_repeat('e', 64), 'code_commit' => 'synthetic', 'target_environment' => 'testing',
        'target_database_identity_sha256' => str_repeat('f', 64), 'status' => 'COMPLETED', 'started_at' => $now, 'completed_at' => $now,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    foreach (['business_owners', 'businesses', 'business_permit_applications', 'payment_transactions', 'permits'] as $ordinal => $dataset) {
        DB::table('ipil_rescue_source_identities')->insert([
            'first_import_run_id' => 'synthetic-gate8', 'source_system' => 'synthetic', 'deployment_identity_sha256' => str_repeat('1', 64),
            'corpus_id' => 'synthetic', 'dataset' => $dataset, 'source_key_sha256' => hash('sha256', $dataset),
            'canonical_payload_sha256' => hash('sha256', 'payload-'.$dataset), 'raw_evidence_locator' => 'synthetic/'.$dataset,
            'entity_kind' => $dataset, 'disposition' => 'MAP_AS_HISTORICAL_EVIDENCE', 'confidence' => 'ESTABLISHED',
            'source_ordinal' => $ordinal, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $identities = DB::table('ipil_rescue_source_identities')->pluck('id', 'dataset');
    $owner = DB::table('ipil_historical_owners')->insertGetId([
        'ipil_rescue_source_identity_id' => $identities['business_owners'], 'name' => 'Synthetic Owner', 'barangay_literal' => 'Synthetic Barangay',
        'operationally_eligible' => false, 'collision_candidate' => true, 'source_payload_json' => '{}', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $business = DB::table('ipil_historical_businesses')->insertGetId([
        'ipil_rescue_source_identity_id' => $identities['businesses'], 'ipil_historical_owner_id' => $owner, 'name' => 'Synthetic Trading',
        'registration_number' => 'REG-SYNTHETIC', 'barangay_literal' => 'Synthetic Barangay', 'operationally_eligible' => false,
        'collision_candidate' => false, 'source_payload_json' => '{"documents":[]}', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $application = DB::table('ipil_historical_applications')->insertGetId([
        'ipil_rescue_source_identity_id' => $identities['business_permit_applications'], 'ipil_historical_owner_id' => $owner,
        'ipil_historical_business_id' => $business, 'source_application_number' => 'APP-SYNTHETIC', 'source_type' => 'Renewal',
        'source_status' => 'Released', 'application_year' => 2024, 'total_fees_source_lexeme' => '100.00', 'total_fees_decimal' => '100.00',
        'total_fees_cent_exact' => true, 'operationally_eligible' => false, 'can_continue' => false,
        'source_payload_json' => '{"private":"must-not-render"}', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $payment = DB::table('ipil_historical_payments')->insertGetId([
        'ipil_rescue_source_identity_id' => $identities['payment_transactions'], 'ipil_historical_application_id' => $application,
        'source_status' => 'completed', 'amount_source_lexeme' => '100.00', 'amount_decimal' => '100.00',
        'receipt_number' => 'OR-SYNTHETIC-1', 'receipt_number_normalized_sha256' => hash('sha256', 'orsynthetic1'),
        'missing_application' => false, 'missing_schedule' => true, 'source_payload_json' => '{}', 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('ipil_historical_receipt_claims')->insert([
        'ipil_historical_payment_id' => $payment, 'receipt_number' => 'OR-SYNTHETIC-1', 'normalized_sha256' => hash('sha256', 'orsynthetic1'),
        'is_duplicate_claim' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('ipil_historical_permit_claims')->insert([
        'ipil_rescue_source_identity_id' => $identities['permits'], 'ipil_historical_application_id' => $application,
        'ipil_historical_business_id' => $business, 'ipil_historical_owner_id' => $owner, 'permit_number' => 'PERMIT-SYNTHETIC',
        'source_status' => 'Released', 'missing_application' => false, 'broken_business_edge' => false, 'broken_owner_edge' => false,
        'source_payload_json' => '{}', 'created_at' => $now, 'updated_at' => $now,
    ]);
});

test('authorized staff can navigate the historical read surface without source payload exposure', function () {
    $staff = userWithPermissions([UserPermission::AccessStaff, UserPermission::ViewPermitApplications]);
    $ownerId = DB::table('ipil_historical_owners')->value('id');
    $businessId = DB::table('ipil_historical_businesses')->value('id');
    $applicationId = DB::table('ipil_historical_applications')->value('id');

    $this->actingAs($staff)->get(route('staff.ipil-history.index', ['q' => 'OR-SYNTHETIC-1']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('ipil-history/Index')
        ->where('anchors.completed_payment_total', '100.00')
        ->where('matches.receipts.0.application_id', $applicationId)->where('matches.receipts.0.is_duplicate_claim', true)
        ->missing('businesses.data.0.source_payload_json'));
    $this->actingAs($staff)->get(route('staff.ipil-history.owners.show', $ownerId))->assertOk()->assertInertia(fn (Assert $page) => $page->component('ipil-history/Owner')->where('owner.operationally_eligible', false)->missing('owner.source_payload_json'));
    $this->actingAs($staff)->get(route('staff.ipil-history.businesses.show', $businessId))->assertOk()->assertInertia(fn (Assert $page) => $page->component('ipil-history/Business')->missing('business.source_payload_json'));
    $this->actingAs($staff)->get(route('staff.ipil-history.applications.show', $applicationId))->assertOk()->assertInertia(fn (Assert $page) => $page->component('ipil-history/Application')->where('application.can_continue', false)->missing('application.source_payload_json'));

    foreach ([
        'Synthetic Trading' => 'matches.businesses.0.id',
        'Synthetic Owner' => 'matches.owners.0.id',
        'APP-SYNTHETIC' => 'matches.applications.0.id',
        'PERMIT-SYNTHETIC' => 'matches.permits.0.application_id',
    ] as $query => $property) {
        $this->actingAs($staff)->get(route('staff.ipil-history.index', ['q' => $query]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where($property, match ($query) {
                'Synthetic Trading' => $businessId,
                'Synthetic Owner' => $ownerId,
                default => $applicationId,
            }));
    }
});

test('history requires the existing permit application viewing authority', function () {
    $staffWithoutPermission = userWithPermissions([UserPermission::AccessStaff]);
    $this->actingAs($staffWithoutPermission)->get(route('staff.ipil-history.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('staff.ipil-history.index'))->assertForbidden();
});

test('the historical surface exposes only get and head routes', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'staff.ipil-history.'));
    expect($routes)->not->toBeEmpty();
    $routes->each(fn ($route) => expect($route->methods())->each->toBeIn(['GET', 'HEAD']));
});
