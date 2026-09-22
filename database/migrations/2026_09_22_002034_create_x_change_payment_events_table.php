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
        Schema::create('x_change_payment_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('payload_hash', 64);
            $table->string('partner_reference');
            $table->string('external_reference');
            $table->string('pay_code');
            $table->string('provider_collection_id');
            $table->unique(['partner_reference', 'provider_collection_id'], 'x_change_events_provider_collection_unique');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->timestamp('occurred_at');
            $table->string('state')->default('accepted')->index();
            $table->string('failure_code')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_change_payment_events');
    }
};
