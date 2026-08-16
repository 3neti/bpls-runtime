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
        Schema::create('legacy_line_of_business_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_source_id')->constrained()->restrictOnDelete();
            $table->string('source_dataset')->default('groups');
            $table->string('source_value_hash', 64);
            $table->foreignId('line_of_business_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->string('decision_authority')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_source_id', 'source_dataset', 'source_value_hash'], 'legacy_lob_reconciliation_source_unique');
            $table->index(['status', 'line_of_business_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_line_of_business_reconciliations');
    }
};
