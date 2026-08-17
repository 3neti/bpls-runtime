<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_historical_financial_mapping_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_source_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_import_batch_id')->constrained('legacy_import_batches')->restrictOnDelete();
            $table->foreignId('registry_import_batch_id')->constrained('legacy_import_batches')->restrictOnDelete();
            $table->foreignId('legacy_financial_mapping_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('legacy_mapping_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('accepted_registry_plan_id')->nullable()->constrained('legacy_mapping_plans')->restrictOnDelete();
            $table->foreignId('registry_execution_id')->nullable()->constrained('legacy_mapping_executions')->restrictOnDelete();
            $table->foreignId('declaration_plan_id')->nullable()->constrained('legacy_declaration_mapping_plans')->restrictOnDelete();
            $table->foreignId('application_plan_id')->nullable()->constrained('legacy_application_mapping_plans')->restrictOnDelete();
            $table->foreignId('application_execution_id')->nullable()->constrained('legacy_application_mapping_executions')->restrictOnDelete();
            $table->string('run_reference');
            $table->string('cohort_sha256', 64);
            $table->string('proposal_package_sha256', 64);
            $table->string('accepted_mapping_set_sha256', 64)->nullable();
            $table->string('status')->default('accepting');
            $table->unsignedInteger('cohort_size');
            $table->string('decision_authority');
            $table->string('evidence_reference');
            $table->timestamp('accepted_at')->nullable();
            $table->json('manifest')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_source_id', 'run_reference'], 'legacy_hist_fin_mapping_set_source_run_unique');
            $table->unique(['legacy_source_id', 'cohort_sha256'], 'legacy_hist_fin_mapping_set_source_cohort_unique');
            $table->index(['status', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_historical_financial_mapping_sets');
    }
};
