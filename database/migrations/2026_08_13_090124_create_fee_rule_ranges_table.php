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
        Schema::create('fee_rule_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('min_basis_cents')->default(0);
            $table->unsignedBigInteger('max_basis_cents')->nullable();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->unsignedInteger('rate_basis_points')->nullable();
            $table->timestamps();

            $table->index(['fee_rule_id', 'min_basis_cents', 'max_basis_cents']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_rule_ranges');
    }
};
