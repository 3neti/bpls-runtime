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
        Schema::create('legacy_application_mapping_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_application_mapping_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_mapping_id')->nullable()->constrained('legacy_id_mappings')->nullOnDelete();
            $table->foreignId('business_mapping_id')->nullable()->constrained('legacy_id_mappings')->nullOnDelete();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('proposed_action');
            $table->string('status');
            $table->string('identity_fingerprint', 64);
            $table->string('projection_hash', 64);
            $table->json('collision_fingerprints')->nullable();
            $table->json('reasons')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_application_mapping_plan_id', 'legacy_record_id'], 'legacy_application_plan_record_unique');
            $table->index(['legacy_application_mapping_plan_id', 'status'], 'legacy_application_plan_status_index');
            $table->index(['target_id', 'proposed_action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_application_mapping_proposals');
    }
};
