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
        Schema::create('paperless_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bplo_routing_work_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_permit_evaluation_item_revision_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('issued_by_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('issued')->index();
            $table->unsignedBigInteger('total_amount_cents');
            $table->json('source_snapshot');
            $table->timestamp('issued_at')->index();
            $table->timestamp('superseded_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['bplo_routing_work_id', 'sequence'], 'paperless_order_work_sequence_unique');
            $table->unique('business_permit_evaluation_item_revision_id', 'paperless_order_revision_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paperless_payment_orders');
    }
};
