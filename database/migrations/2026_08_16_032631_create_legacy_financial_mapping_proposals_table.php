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
        Schema::create('legacy_financial_mapping_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_financial_mapping_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_record_id')->constrained()->restrictOnDelete();
            $table->string('source_dataset');
            $table->string('kind');
            $table->string('item_key', 160);
            $table->foreignId('legacy_fee_rule_reconciliation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('projection_hash', 64);
            $table->json('reasons')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_financial_mapping_plan_id', 'legacy_record_id', 'kind', 'item_key'], 'legacy_financial_plan_record_item_unique');
            $table->index(['legacy_financial_mapping_plan_id', 'status'], 'legacy_financial_plan_status_index');
            $table->index(['source_dataset', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_financial_mapping_proposals');
    }
};
