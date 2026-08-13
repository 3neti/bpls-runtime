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
        Schema::create('treasury_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('received_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending_receipt')->index();
            $table->string('channel')->default('over_the_counter')->index();
            $table->string('method')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->string('payer_name')->nullable();
            $table->string('reference_number')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->timestamp('received_at')->index();
            $table->json('source_snapshot');
            $table->string('legacy_source_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_collections');
    }
};
