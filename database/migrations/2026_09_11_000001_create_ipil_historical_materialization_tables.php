<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipil_rescue_import_runs', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('corpus_id');
            $table->string('corpus_fingerprint_sha256', 64);
            $table->string('mapping_profile');
            $table->string('mapping_profile_identity_sha256', 64);
            $table->string('seed_plan_id');
            $table->string('seed_plan_fingerprint_sha256', 64);
            $table->string('execution_manifest_fingerprint_sha256', 64);
            $table->string('authorization_fingerprint_sha256', 64);
            $table->string('code_commit', 64);
            $table->string('target_environment');
            $table->string('target_database_identity_sha256', 64);
            $table->string('status');
            $table->json('result')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['corpus_id', 'mapping_profile', 'status'], 'ipil_import_run_binding_idx');
        });

        Schema::create('ipil_rescue_phase_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('ipil_rescue_import_run_id', 64);
            $table->string('phase_code', 2);
            $table->string('phase_name');
            $table->string('semantic_fingerprint_sha256', 64);
            $table->string('status');
            $table->json('expected');
            $table->json('actual')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz();

            $table->foreign('ipil_rescue_import_run_id', 'ipil_phase_run_fk')->references('id')->on('ipil_rescue_import_runs')->cascadeOnDelete();
            $table->unique(['ipil_rescue_import_run_id', 'phase_code'], 'ipil_phase_run_code_unique');
        });

        Schema::create('ipil_rescue_source_identities', function (Blueprint $table) {
            $table->id();
            $table->string('first_import_run_id', 64);
            $table->string('source_system');
            $table->string('deployment_identity_sha256', 64);
            $table->string('corpus_id');
            $table->string('dataset');
            $table->string('source_key_sha256', 64);
            $table->string('canonical_payload_sha256', 64);
            $table->string('raw_evidence_locator');
            $table->string('entity_kind');
            $table->string('disposition');
            $table->string('confidence');
            $table->unsignedInteger('source_ordinal');
            $table->string('projection_type')->nullable();
            $table->unsignedBigInteger('projection_id')->nullable();
            $table->timestampsTz();

            $table->foreign('first_import_run_id', 'ipil_identity_run_fk')->references('id')->on('ipil_rescue_import_runs')->restrictOnDelete();
            $table->unique(['corpus_id', 'dataset', 'source_key_sha256'], 'ipil_identity_logical_unique');
            $table->index(['dataset', 'source_ordinal'], 'ipil_identity_dataset_ordinal_idx');
            $table->index(['projection_type', 'projection_id'], 'ipil_identity_projection_idx');
        });

        Schema::create('ipil_historical_evidence_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->string('dataset');
            $table->string('evidence_class');
            $table->string('disposition');
            $table->string('confidence');
            $table->boolean('unresolved')->default(false);
            $table->longText('source_payload_json')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_evidence_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_evidence_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index(['dataset', 'disposition'], 'ipil_evidence_dataset_disposition_idx');
            $table->index(['evidence_class', 'unresolved'], 'ipil_evidence_class_unresolved_idx');
        });

        Schema::create('ipil_historical_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('barangay_literal')->nullable();
            $table->boolean('operationally_eligible')->default(false);
            $table->boolean('collision_candidate')->default(false);
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_owner_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_owner_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index('name');
            $table->index('barangay_literal');
        });

        Schema::create('ipil_historical_businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->foreignId('ipil_historical_owner_id')->constrained('ipil_historical_owners')->restrictOnDelete();
            $table->string('name');
            $table->string('registration_number')->nullable();
            $table->text('address')->nullable();
            $table->string('barangay_literal')->nullable();
            $table->string('barangay_psgc_proposal')->nullable();
            $table->boolean('operationally_eligible')->default(false);
            $table->boolean('collision_candidate')->default(false);
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_business_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_business_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index('name');
            $table->index('registration_number');
            $table->index('barangay_literal');
        });

        Schema::create('ipil_historical_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->foreignId('ipil_historical_owner_id')->constrained('ipil_historical_owners')->restrictOnDelete();
            $table->foreignId('ipil_historical_business_id')->constrained('ipil_historical_businesses')->restrictOnDelete();
            $table->string('source_application_number')->nullable();
            $table->string('source_type');
            $table->string('source_status');
            $table->unsignedSmallInteger('application_year');
            $table->text('total_fees_source_lexeme')->nullable();
            $table->text('total_fees_decimal')->nullable();
            $table->boolean('total_fees_cent_exact')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->boolean('operationally_eligible')->default(false);
            $table->boolean('can_continue')->default(false);
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_application_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_application_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index(['application_year', 'source_type', 'source_status'], 'ipil_hist_app_search_idx');
            $table->index('source_application_number');
        });

        Schema::create('ipil_historical_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_historical_application_id')->constrained('ipil_historical_applications')->restrictOnDelete();
            $table->foreignId('source_application_identity_id')->constrained('ipil_rescue_source_identities')->restrictOnDelete();
            $table->unsignedInteger('source_index');
            $table->string('item_identity_sha256', 64)->unique();
            $table->text('source_literal')->nullable();
            $table->text('normalized_candidate')->nullable();
            $table->string('confidence');
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique(['ipil_historical_application_id', 'source_index'], 'ipil_hist_class_app_index_unique');
            $table->index('normalized_candidate');
        });

        Schema::create('ipil_historical_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->foreignId('ipil_historical_application_id')->nullable()->constrained('ipil_historical_applications')->nullOnDelete();
            $table->string('source_dataset');
            $table->string('source_variable')->nullable();
            $table->text('source_quantity_lexeme')->nullable();
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_measurement_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_measurement_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
        });

        Schema::create('ipil_historical_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->foreignId('ipil_historical_application_id')->nullable()->constrained('ipil_historical_applications')->nullOnDelete();
            $table->string('source_status');
            $table->text('total_amount_source_lexeme');
            $table->text('total_amount_decimal');
            $table->boolean('total_amount_cent_exact');
            $table->text('paid_amount_source_lexeme');
            $table->text('paid_amount_decimal');
            $table->boolean('missing_application')->default(false);
            $table->json('source_fee_lines');
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_schedule_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_schedule_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index('source_status');
        });

        Schema::create('ipil_historical_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->foreignId('ipil_historical_application_id')->nullable()->constrained('ipil_historical_applications')->nullOnDelete();
            $table->foreignId('ipil_historical_payment_schedule_id')->nullable()->constrained('ipil_historical_payment_schedules')->nullOnDelete();
            $table->string('source_status');
            $table->text('amount_source_lexeme');
            $table->text('amount_decimal');
            $table->string('transaction_number')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('receipt_number_normalized_sha256', 64)->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->boolean('missing_application')->default(false);
            $table->boolean('missing_schedule')->default(false);
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_payment_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_payment_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index('source_status');
            $table->index('receipt_number_normalized_sha256', 'ipil_hist_payment_receipt_hash_idx');
        });

        Schema::create('ipil_historical_receipt_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_historical_payment_id');
            $table->string('receipt_number');
            $table->string('normalized_sha256', 64);
            $table->boolean('is_duplicate_claim')->default(false);
            $table->timestampsTz();

            $table->unique('ipil_historical_payment_id', 'ipil_receipt_payment_unique');
            $table->foreign('ipil_historical_payment_id', 'ipil_receipt_payment_fk')->references('id')->on('ipil_historical_payments')->restrictOnDelete();
            $table->index('normalized_sha256');
        });

        Schema::create('ipil_historical_permit_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->foreignId('ipil_historical_application_id')->nullable()->constrained('ipil_historical_applications')->nullOnDelete();
            $table->foreignId('ipil_historical_business_id')->nullable()->constrained('ipil_historical_businesses')->nullOnDelete();
            $table->foreignId('ipil_historical_owner_id')->nullable()->constrained('ipil_historical_owners')->nullOnDelete();
            $table->string('permit_number')->nullable();
            $table->string('source_status')->nullable();
            $table->boolean('missing_application')->default(false);
            $table->boolean('broken_business_edge')->default(false);
            $table->boolean('broken_owner_edge')->default(false);
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_permit_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_permit_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index('permit_number');
            $table->index(['missing_application', 'broken_business_edge', 'broken_owner_edge'], 'ipil_hist_permit_findings_idx');
        });

        Schema::create('ipil_historical_clearance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->foreignId('ipil_historical_application_id')->constrained('ipil_historical_applications')->restrictOnDelete();
            $table->string('clearance_name')->nullable();
            $table->boolean('source_completed')->default(false);
            $table->boolean('broken_type_reference')->default(false);
            $table->longText('source_payload_json');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_clearance_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_clearance_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index(['source_completed', 'broken_type_reference'], 'ipil_hist_clearance_findings_idx');
        });

        Schema::create('ipil_historical_media_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipil_rescue_source_identity_id');
            $table->string('source_dataset');
            $table->string('association_state');
            $table->string('evidence_role');
            $table->string('document_type')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('source_sha256', 64);
            $table->unsignedBigInteger('source_size_bytes');
            $table->string('object_relative_path');
            $table->boolean('import_accepted')->default(false);
            $table->boolean('spatie_imported')->default(false);
            $table->string('managed_copy_sha256', 64)->nullable();
            $table->json('source_manifest_entry');
            $table->timestampsTz();

            $table->unique('ipil_rescue_source_identity_id', 'ipil_media_identity_unique');
            $table->foreign('ipil_rescue_source_identity_id', 'ipil_media_identity_fk')->references('id')->on('ipil_rescue_source_identities')->restrictOnDelete();
            $table->index(['association_state', 'import_accepted', 'spatie_imported'], 'ipil_hist_media_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipil_historical_media_evidence');
        Schema::dropIfExists('ipil_historical_clearance_claims');
        Schema::dropIfExists('ipil_historical_permit_claims');
        Schema::dropIfExists('ipil_historical_receipt_claims');
        Schema::dropIfExists('ipil_historical_payments');
        Schema::dropIfExists('ipil_historical_payment_schedules');
        Schema::dropIfExists('ipil_historical_measurements');
        Schema::dropIfExists('ipil_historical_classifications');
        Schema::dropIfExists('ipil_historical_applications');
        Schema::dropIfExists('ipil_historical_businesses');
        Schema::dropIfExists('ipil_historical_owners');
        Schema::dropIfExists('ipil_historical_evidence_records');
        Schema::dropIfExists('ipil_rescue_source_identities');
        Schema::dropIfExists('ipil_rescue_phase_checkpoints');
        Schema::dropIfExists('ipil_rescue_import_runs');
    }
};
