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
        Schema::create('legacy_historical_financial_preservation_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('legacy_financial_mapping_plan_id');
            $table->string('run_reference');
            $table->string('planner_version');
            $table->string('dependency_snapshot_hash', 64);
            $table->string('status')->default('planning');
            $table->unsignedInteger('proposal_count')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_financial_mapping_plan_id', 'legacy_hist_fin_plan_source_plan_fk')->references('id')->on('legacy_financial_mapping_plans')->restrictOnDelete();
            $table->unique(['legacy_import_batch_id', 'run_reference'], 'legacy_hist_fin_plan_batch_run_unique');
            $table->index(['status', 'created_at'], 'legacy_hist_fin_plan_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_historical_financial_preservation_plans');
    }
};
