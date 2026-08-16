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
        Schema::create('legacy_application_id_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_application_mapping_execution_id')->nullable();
            $table->foreignId('legacy_source_id');
            $table->foreignId('legacy_import_batch_id');
            $table->foreignId('permit_application_id');
            $table->string('dataset_key');
            $table->string('legacy_id');
            $table->string('status')->default('mapped');
            $table->text('mapping_basis');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_application_mapping_execution_id', 'legacy_app_id_mapping_execution_fk')
                ->references('id')
                ->on('legacy_application_mapping_executions')
                ->restrictOnDelete();
            $table->foreign('legacy_source_id', 'legacy_app_id_mapping_source_fk')
                ->references('id')
                ->on('legacy_sources')
                ->restrictOnDelete();
            $table->foreign('legacy_import_batch_id', 'legacy_app_id_mapping_batch_fk')
                ->references('id')
                ->on('legacy_import_batches')
                ->restrictOnDelete();
            $table->foreign('permit_application_id', 'legacy_app_id_mapping_application_fk')
                ->references('id')
                ->on('permit_applications')
                ->restrictOnDelete();
            $table->unique(['legacy_source_id', 'dataset_key', 'legacy_id'], 'legacy_app_id_mapping_source_record_unique');
            $table->index(['permit_application_id', 'status'], 'legacy_app_id_mapping_target_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_application_id_mappings');
    }
};
