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
        Schema::create('legacy_mapping_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_mapping_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_legacy_record_id')->nullable()->constrained('legacy_records')->restrictOnDelete();
            $table->string('dataset_key');
            $table->string('entity_type');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('proposed_action');
            $table->string('status');
            $table->string('identity_fingerprint', 64);
            $table->string('projection_hash', 64);
            $table->json('collision_fingerprints')->nullable();
            $table->json('reasons')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_mapping_plan_id', 'legacy_record_id']);
            $table->index(['legacy_mapping_plan_id', 'target_type', 'status']);
            $table->index(['target_type', 'target_id']);
            $table->index(['dataset_key', 'identity_fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_mapping_proposals');
    }
};
