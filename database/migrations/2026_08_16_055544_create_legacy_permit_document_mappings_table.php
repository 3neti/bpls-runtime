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
        Schema::create('legacy_permit_document_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_permit_evidence_execution_id')->nullable();
            $table->foreignId('legacy_application_id_mapping_id');
            $table->foreignId('legacy_document_object_reconciliation_id');
            $table->foreignId('legacy_source_id');
            $table->foreignId('legacy_import_batch_id');
            $table->foreignId('legacy_record_id');
            $table->foreignId('permit_application_document_id');
            $table->string('item_key', 160);
            $table->string('status')->default('mapped');
            $table->string('mapping_basis');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_permit_evidence_execution_id', 'legacy_permit_doc_map_exec_fk')
                ->references('id')->on('legacy_permit_evidence_executions')->nullOnDelete();
            $table->foreign('legacy_application_id_mapping_id', 'legacy_permit_doc_map_app_fk')
                ->references('id')->on('legacy_application_id_mappings')->restrictOnDelete();
            $table->foreign('legacy_document_object_reconciliation_id', 'legacy_permit_doc_map_recon_fk')
                ->references('id')->on('legacy_document_object_reconciliations')->restrictOnDelete();
            $table->foreign('legacy_source_id', 'legacy_permit_doc_map_source_fk')
                ->references('id')->on('legacy_sources')->restrictOnDelete();
            $table->foreign('legacy_import_batch_id', 'legacy_permit_doc_map_batch_fk')
                ->references('id')->on('legacy_import_batches')->restrictOnDelete();
            $table->foreign('legacy_record_id', 'legacy_permit_doc_map_record_fk')
                ->references('id')->on('legacy_records')->restrictOnDelete();
            $table->foreign('permit_application_document_id', 'legacy_permit_doc_map_target_fk')
                ->references('id')->on('permit_application_documents')->restrictOnDelete();
            $table->unique(['legacy_record_id', 'item_key'], 'legacy_permit_doc_map_record_item_unique');
            $table->unique('permit_application_document_id', 'legacy_permit_doc_map_target_unique');
            $table->index(['legacy_import_batch_id', 'status'], 'legacy_permit_doc_map_batch_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_permit_document_mappings');
    }
};
