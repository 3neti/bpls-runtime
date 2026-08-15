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
        Schema::create('fee_rule_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('legal_authority');
            $table->string('evidence_reference');
            $table->text('original_text');
            $table->text('normalized_interpretation')->nullable();
            $table->string('decision_authority')->nullable();
            $table->string('decision_reference')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('execution_status');
            $table->text('execution_reason');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['fee_rule_id', 'version']);
            $table->index(['execution_status', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_rule_reconciliations');
    }
};
