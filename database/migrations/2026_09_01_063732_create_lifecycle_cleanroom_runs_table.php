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
        Schema::create('lifecycle_cleanroom_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('status')->default('active')->index();
            $table->string('target_step')->nullable();
            $table->foreignId('started_by_id')->constrained('users');
            $table->foreignId('new_application_id')->nullable()->constrained('permit_applications')->nullOnDelete();
            $table->foreignId('renewal_application_id')->nullable()->constrained('permit_applications')->nullOnDelete();
            $table->json('actor_manifest');
            $table->json('owned_resource_manifest');
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lifecycle_cleanroom_runs');
    }
};
