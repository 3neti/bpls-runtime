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
        Schema::create('billing_group_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('evidence_type');
            $table->string('evidence_reference', 500);
            $table->text('source_excerpt')->nullable();
            $table->text('operational_interpretation')->nullable();
            $table->json('unresolved_questions');
            $table->string('reconciliation_status')->default('pending_municipal_decision');
            $table->string('execution_status')->default('blocked');
            $table->text('execution_reason');
            $table->json('definition_snapshot');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['billing_group_id', 'version']);
            $table->index(['reconciliation_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_group_reconciliations');
    }
};
