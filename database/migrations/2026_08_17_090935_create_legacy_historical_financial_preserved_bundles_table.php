<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legacy_historical_financial_preserved_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_historical_financial_preservation_execution_id');
            $table->foreignId('legacy_historical_financial_preservation_proposal_id');
            $table->foreignId('legacy_application_id_mapping_id');
            $table->foreignId('legacy_source_id');
            $table->foreignId('legacy_import_batch_id');
            $table->foreignId('legacy_record_id');
            $table->foreignId('permit_application_id')->constrained()->restrictOnDelete();
            $table->string('source_projection_hash', 64);
            $table->string('bundle_snapshot_hash', 64);
            $table->string('status')->default('preserved');
            $table->string('mapping_basis');
            $table->json('snapshot');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_historical_financial_preservation_execution_id', 'legacy_hist_fin_bundle_exec_fk')->references('id')->on('legacy_historical_financial_preservation_executions')->restrictOnDelete();
            $table->foreign('legacy_historical_financial_preservation_proposal_id', 'legacy_hist_fin_bundle_proposal_fk')->references('id')->on('legacy_historical_financial_preservation_proposals')->restrictOnDelete();
            $table->foreign('legacy_application_id_mapping_id', 'legacy_hist_fin_bundle_app_map_fk')->references('id')->on('legacy_application_id_mappings')->restrictOnDelete();
            $table->foreign('legacy_source_id', 'legacy_hist_fin_bundle_source_fk')->references('id')->on('legacy_sources')->restrictOnDelete();
            $table->foreign('legacy_import_batch_id', 'legacy_hist_fin_bundle_batch_fk')->references('id')->on('legacy_import_batches')->restrictOnDelete();
            $table->foreign('legacy_record_id', 'legacy_hist_fin_bundle_record_fk')->references('id')->on('legacy_records')->restrictOnDelete();
            $table->unique(['legacy_source_id', 'legacy_record_id'], 'legacy_hist_fin_bundle_source_record_unique');
            $table->unique('legacy_historical_financial_preservation_proposal_id', 'legacy_hist_fin_bundle_proposal_unique');
            $table->index(['permit_application_id', 'status'], 'legacy_hist_fin_bundle_application_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_historical_financial_preserved_bundles');
    }
};
