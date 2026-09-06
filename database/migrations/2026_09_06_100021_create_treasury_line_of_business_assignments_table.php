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
        Schema::create('treasury_line_of_business_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_of_business_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('assigned')->index();
            $table->json('source_snapshot');
            $table->timestamp('assigned_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['permit_application_id', 'line_of_business_id'], 'treasury_lob_application_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_line_of_business_assignments');
    }
};
