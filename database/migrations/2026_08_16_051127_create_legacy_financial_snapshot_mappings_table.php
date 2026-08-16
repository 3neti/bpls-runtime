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
        Schema::create('legacy_financial_snapshot_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_financial_mapping_execution_id')->nullable();
            $table->foreignId('legacy_application_id_mapping_id');
            $table->foreignId('legacy_source_id');
            $table->foreignId('legacy_import_batch_id');
            $table->foreignId('legacy_record_id');
            $table->foreignId('assessment_id');
            $table->foreignId('payment_schedule_id');
            $table->string('dataset_key');
            $table->string('legacy_id');
            $table->string('status')->default('mapped');
            $table->string('mapping_basis');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_financial_mapping_execution_id', 'legacy_fin_snapshot_execution_fk')
                ->references('id')
                ->on('legacy_financial_mapping_executions')
                ->nullOnDelete();
            $table->foreign('legacy_application_id_mapping_id', 'legacy_fin_snapshot_application_fk')
                ->references('id')
                ->on('legacy_application_id_mappings')
                ->restrictOnDelete();
            $table->foreign('legacy_source_id', 'legacy_fin_snapshot_source_fk')
                ->references('id')
                ->on('legacy_sources')
                ->restrictOnDelete();
            $table->foreign('legacy_import_batch_id', 'legacy_fin_snapshot_batch_fk')
                ->references('id')
                ->on('legacy_import_batches')
                ->restrictOnDelete();
            $table->foreign('legacy_record_id', 'legacy_fin_snapshot_record_fk')
                ->references('id')
                ->on('legacy_records')
                ->restrictOnDelete();
            $table->foreign('assessment_id', 'legacy_fin_snapshot_assessment_fk')
                ->references('id')
                ->on('assessments')
                ->restrictOnDelete();
            $table->foreign('payment_schedule_id', 'legacy_fin_snapshot_schedule_fk')
                ->references('id')
                ->on('payment_schedules')
                ->restrictOnDelete();
            $table->unique(['legacy_source_id', 'dataset_key', 'legacy_id'], 'legacy_fin_snapshot_source_record_unique');
            $table->unique('assessment_id');
            $table->unique('payment_schedule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_financial_snapshot_mappings');
    }
};
