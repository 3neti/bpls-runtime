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
        Schema::create('treasury_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treasury_line_of_business_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('determined_by_id')->constrained('users')->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('default_amount_cents');
            $table->unsignedBigInteger('determined_amount_cents');
            $table->bigInteger('variance_cents');
            $table->string('currency', 3)->default('PHP');
            $table->json('source_snapshot');
            $table->timestamp('determined_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_line_items');
    }
};
