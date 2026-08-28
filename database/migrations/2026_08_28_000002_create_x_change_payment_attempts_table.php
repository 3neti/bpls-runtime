<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_change_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('x_change_payment_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('reference')->nullable()->unique();
            $table->string('status')->default('requested')->index();
            $table->string('provider')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_change_payment_attempts');
    }
};
