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
        Schema::create('paperless_payment_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paperless_payment_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permit_application_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_of_business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->json('source_snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paperless_payment_order_lines');
    }
};
