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
        Schema::create('legacy_historical_financial_preservation_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_historical_financial_preservation_plan_id');
            $table->foreignId('legacy_record_id');
            $table->foreignId('legacy_application_id_mapping_id')->nullable();
            $table->string('status')->default('blocked');
            $table->string('projection_hash', 64);
            $table->json('reasons')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_historical_financial_preservation_plan_id', 'legacy_hist_fin_proposal_plan_fk')->references('id')->on('legacy_historical_financial_preservation_plans')->cascadeOnDelete();
            $table->foreign('legacy_record_id', 'legacy_hist_fin_proposal_record_fk')->references('id')->on('legacy_records')->restrictOnDelete();
            $table->foreign('legacy_application_id_mapping_id', 'legacy_hist_fin_proposal_app_map_fk')->references('id')->on('legacy_application_id_mappings')->restrictOnDelete();
            $table->unique(['legacy_historical_financial_preservation_plan_id', 'legacy_record_id'], 'legacy_hist_fin_proposal_plan_record_unique');
            $table->index(['status', 'created_at'], 'legacy_hist_fin_proposal_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_historical_financial_preservation_proposals');
    }
};
