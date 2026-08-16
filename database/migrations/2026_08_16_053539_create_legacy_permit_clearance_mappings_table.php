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
        Schema::create('legacy_permit_clearance_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_permit_evidence_execution_id')->nullable();
            $table->foreignId('legacy_application_id_mapping_id');
            $table->foreignId('legacy_clearance_type_reconciliation_id');
            $table->foreignId('legacy_source_id');
            $table->foreignId('legacy_import_batch_id');
            $table->foreignId('legacy_record_id');
            $table->foreignId('permit_clearance_id');
            $table->string('dataset_key');
            $table->string('legacy_id');
            $table->string('status')->default('mapped');
            $table->string('mapping_basis');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_permit_evidence_execution_id', 'legacy_permit_clr_map_exec_fk')
                ->references('id')->on('legacy_permit_evidence_executions')->nullOnDelete();
            $table->foreign('legacy_application_id_mapping_id', 'legacy_permit_clr_map_app_fk')
                ->references('id')->on('legacy_application_id_mappings')->restrictOnDelete();
            $table->foreign('legacy_clearance_type_reconciliation_id', 'legacy_permit_clr_map_recon_fk')
                ->references('id')->on('legacy_clearance_type_reconciliations')->restrictOnDelete();
            $table->foreign('legacy_source_id', 'legacy_permit_clr_map_source_fk')
                ->references('id')->on('legacy_sources')->restrictOnDelete();
            $table->foreign('legacy_import_batch_id', 'legacy_permit_clr_map_batch_fk')
                ->references('id')->on('legacy_import_batches')->restrictOnDelete();
            $table->foreign('legacy_record_id', 'legacy_permit_clr_map_record_fk')
                ->references('id')->on('legacy_records')->restrictOnDelete();
            $table->foreign('permit_clearance_id', 'legacy_permit_clr_map_target_fk')
                ->references('id')->on('permit_clearances')->restrictOnDelete();
            $table->unique(['legacy_source_id', 'dataset_key', 'legacy_id'], 'legacy_permit_clr_map_source_unique');
            $table->unique('permit_clearance_id', 'legacy_permit_clr_map_target_unique');
            $table->index(['legacy_import_batch_id', 'status'], 'legacy_permit_clr_map_batch_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_permit_clearance_mappings');
    }
};
