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
        Schema::create('legacy_declaration_line_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_declaration_mapping_execution_id')->nullable();
            $table->foreignId('legacy_application_id_mapping_id');
            $table->foreignId('legacy_line_of_business_reconciliation_id');
            $table->foreignId('legacy_source_id');
            $table->foreignId('legacy_import_batch_id');
            $table->foreignId('permit_application_line_id');
            $table->string('dataset_key');
            $table->string('legacy_id');
            $table->unsignedInteger('line_index');
            $table->string('status')->default('mapped');
            $table->text('mapping_basis');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_declaration_mapping_execution_id', 'legacy_decl_line_execution_fk')
                ->references('id')
                ->on('legacy_declaration_mapping_executions')
                ->restrictOnDelete();
            $table->foreign('legacy_application_id_mapping_id', 'legacy_decl_line_application_mapping_fk')
                ->references('id')
                ->on('legacy_application_id_mappings')
                ->restrictOnDelete();
            $table->foreign('legacy_line_of_business_reconciliation_id', 'legacy_decl_line_reconciliation_fk')
                ->references('id')
                ->on('legacy_line_of_business_reconciliations')
                ->restrictOnDelete();
            $table->foreign('legacy_source_id', 'legacy_decl_line_source_fk')
                ->references('id')
                ->on('legacy_sources')
                ->restrictOnDelete();
            $table->foreign('legacy_import_batch_id', 'legacy_decl_line_batch_fk')
                ->references('id')
                ->on('legacy_import_batches')
                ->restrictOnDelete();
            $table->foreign('permit_application_line_id', 'legacy_decl_line_target_fk')
                ->references('id')
                ->on('permit_application_lines')
                ->restrictOnDelete();
            $table->unique(['legacy_source_id', 'dataset_key', 'legacy_id', 'line_index'], 'legacy_decl_line_source_record_unique');
            $table->index(['permit_application_line_id', 'status'], 'legacy_decl_line_target_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_declaration_line_mappings');
    }
};
