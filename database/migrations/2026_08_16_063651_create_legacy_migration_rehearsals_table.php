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
        Schema::create('legacy_migration_rehearsals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_batch_id');
            $table->foreignId('legacy_mapping_execution_id');
            $table->foreignId('legacy_application_mapping_execution_id');
            $table->foreignId('legacy_declaration_mapping_execution_id')->nullable();
            $table->foreignId('legacy_financial_mapping_execution_id')->nullable();
            $table->foreignId('legacy_permit_evidence_execution_id')->nullable();
            $table->foreignId('legacy_migration_readiness_assessment_id')->nullable();
            $table->string('run_reference');
            $table->string('verifier_version');
            $table->string('selection_hash', 64);
            $table->string('dependency_snapshot_hash', 64);
            $table->string('status')->default('verifying');
            $table->unsignedInteger('check_count')->default(0);
            $table->unsignedInteger('passed_count')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->json('checks')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_import_batch_id', 'legacy_rehearsal_batch_fk')->references('id')->on('legacy_import_batches')->restrictOnDelete();
            $table->foreign('legacy_mapping_execution_id', 'legacy_rehearsal_registry_exec_fk')->references('id')->on('legacy_mapping_executions')->restrictOnDelete();
            $table->foreign('legacy_application_mapping_execution_id', 'legacy_rehearsal_application_exec_fk')->references('id')->on('legacy_application_mapping_executions')->restrictOnDelete();
            $table->foreign('legacy_declaration_mapping_execution_id', 'legacy_rehearsal_declaration_exec_fk')->references('id')->on('legacy_declaration_mapping_executions')->restrictOnDelete();
            $table->foreign('legacy_financial_mapping_execution_id', 'legacy_rehearsal_financial_exec_fk')->references('id')->on('legacy_financial_mapping_executions')->restrictOnDelete();
            $table->foreign('legacy_permit_evidence_execution_id', 'legacy_rehearsal_permit_exec_fk')->references('id')->on('legacy_permit_evidence_executions')->restrictOnDelete();
            $table->foreign('legacy_migration_readiness_assessment_id', 'legacy_rehearsal_readiness_fk')->references('id')->on('legacy_migration_readiness_assessments')->restrictOnDelete();
            $table->unique(['legacy_import_batch_id', 'run_reference'], 'legacy_migration_rehearsal_batch_run_unique');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_migration_rehearsals');
    }
};
