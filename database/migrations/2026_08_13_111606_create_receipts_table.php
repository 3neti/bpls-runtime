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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treasury_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('issued')->index();
            $table->string('numbering_authority')->default('manual')->index();
            $table->string('receipt_number');
            $table->unsignedBigInteger('amount_cents');
            $table->timestamp('issued_at')->index();
            $table->text('remarks')->nullable();
            $table->json('source_snapshot');
            $table->string('legacy_source_id')->nullable()->index();
            $table->timestamps();

            $table->unique('treasury_collection_id');
            $table->unique(['numbering_authority', 'receipt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
