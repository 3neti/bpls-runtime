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
        Schema::create('legacy_permit_evidence_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_permit_evidence_plan_id');
            $table->string('run_reference');
            $table->string('selection_hash', 64);
            $table->string('status')->default('executing');
            $table->unsignedInteger('selected_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('linked_count')->default(0);
            $table->unsignedInteger('reused_count')->default(0);
            $table->unsignedInteger('mapping_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_permit_evidence_plan_id', 'legacy_permit_ev_exec_plan_fk')
                ->references('id')->on('legacy_permit_evidence_plans')->restrictOnDelete();
            $table->unique(['legacy_permit_evidence_plan_id', 'run_reference'], 'legacy_permit_ev_exec_plan_run_unique');
            $table->index(['status', 'started_at'], 'legacy_permit_ev_exec_status_started_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_permit_evidence_executions');
    }
};
