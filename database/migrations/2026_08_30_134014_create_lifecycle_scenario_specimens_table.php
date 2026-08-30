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
        Schema::create('lifecycle_scenario_specimens', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_id');
            $table->string('scenario_revision');
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->string('semantic_result_hash', 64);
            $table->json('owned_resource_manifest');
            $table->timestamps();

            $table->unique(['scenario_id', 'scenario_revision']);
            $table->unique('permit_application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lifecycle_scenario_specimens');
    }
};
