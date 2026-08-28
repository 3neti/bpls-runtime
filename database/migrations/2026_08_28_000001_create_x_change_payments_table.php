<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treasury_collection_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('external_reference')->unique();
            $table->string('issue_idempotency_key')->unique();
            $table->char('terms_hash', 64);
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('PHP');
            $table->text('binding_secret');
            $table->string('status')->default('pending_issuance')->index();
            $table->string('pay_code')->nullable()->unique();
            $table->string('voucher_id')->nullable();
            $table->string('consumer_status')->nullable();
            $table->string('provider_status')->nullable();
            $table->unsignedBigInteger('collected_total_cents')->default(0);
            $table->unsignedBigInteger('target_amount_cents')->nullable();
            $table->boolean('is_fully_collected')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_payments');
    }
};
