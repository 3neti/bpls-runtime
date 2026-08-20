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
        Schema::create('provisional_uat_permit_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('ready')->index();
            $table->string('decision')->nullable();
            $table->text('reason')->nullable();
            $table->string('permit_number')->nullable()->unique();
            $table->string('synthetic_signature_reference')->nullable();
            $table->timestamp('decided_at')->nullable()->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->string('semantic_classification')->default('provisional_uat')->index();
            $table->json('source_snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provisional_uat_permit_completions');
    }
};
