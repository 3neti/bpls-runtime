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
        Schema::create('assessment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permit_application_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_of_business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->index();
            $table->string('name');
            $table->string('category')->index();
            $table->string('calculation_type');
            $table->string('basis')->default('none');
            $table->unsignedBigInteger('basis_amount_cents')->default(0);
            $table->unsignedBigInteger('amount_cents');
            $table->text('legal_basis')->nullable();
            $table->json('rule_snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_lines');
    }
};
