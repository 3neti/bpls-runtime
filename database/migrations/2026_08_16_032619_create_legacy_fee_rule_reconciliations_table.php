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
        Schema::create('legacy_fee_rule_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_source_id')->constrained()->restrictOnDelete();
            $table->string('source_dataset')->default('fees');
            $table->string('source_legacy_id');
            $table->foreignId('fee_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->string('decision_authority')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_source_id', 'source_dataset', 'source_legacy_id'], 'legacy_fee_reconciliation_source_unique');
            $table->index(['status', 'fee_rule_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_fee_rule_reconciliations');
    }
};
