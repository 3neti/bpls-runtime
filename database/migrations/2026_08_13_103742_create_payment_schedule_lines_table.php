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
        Schema::create('payment_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('permit_application_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_of_business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->index();
            $table->string('name');
            $table->string('category')->index();
            $table->date('due_on')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('paid_amount_cents')->default(0);
            $table->json('source_snapshot');
            $table->timestamps();

            $table->unique(['payment_schedule_id', 'assessment_line_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedule_lines');
    }
};
