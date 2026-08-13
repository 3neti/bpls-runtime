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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('draft')->index();
            $table->timestamp('assessed_at')->nullable()->index();
            $table->timestamp('superseded_at')->nullable()->index();
            $table->unsignedBigInteger('total_amount_cents')->default(0);
            $table->json('source_snapshot');
            $table->string('legacy_source_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['permit_application_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
