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
        Schema::create('legacy_document_object_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_document_object_staging_run_id');
            $table->foreignId('legacy_record_id');
            $table->foreignId('legacy_application_id_mapping_id');
            $table->string('item_key', 160);
            $table->string('storage_reference_hash', 64);
            $table->string('document_type_hash', 64);
            $table->string('original_name_hash', 64);
            $table->string('object_checksum', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type', 100);
            $table->string('staged_disk', 100);
            $table->string('staged_path', 500);
            $table->string('status')->default('accepted');
            $table->string('decision_authority');
            $table->string('evidence_reference');
            $table->timestamp('decided_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_document_object_staging_run_id', 'legacy_doc_recon_stage_fk')
                ->references('id')->on('legacy_document_object_staging_runs')->restrictOnDelete();
            $table->foreign('legacy_record_id', 'legacy_doc_recon_record_fk')
                ->references('id')->on('legacy_records')->restrictOnDelete();
            $table->foreign('legacy_application_id_mapping_id', 'legacy_doc_recon_app_fk')
                ->references('id')->on('legacy_application_id_mappings')->restrictOnDelete();
            $table->unique(['legacy_record_id', 'item_key'], 'legacy_doc_recon_record_item_unique');
            $table->index(['status', 'object_checksum'], 'legacy_doc_recon_status_checksum_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_document_object_reconciliations');
    }
};
