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
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prepared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('pending')->index();
            $table->string('payment_mode')->default('single');
            $table->date('due_on')->nullable()->index();
            $table->unsignedBigInteger('total_amount_cents')->default(0);
            $table->unsignedBigInteger('paid_amount_cents')->default(0);
            $table->json('source_snapshot');
            $table->string('legacy_source_id')->nullable()->index();
            $table->timestamps();

            $table->unique('assessment_id');
            $table->unique(['permit_application_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
