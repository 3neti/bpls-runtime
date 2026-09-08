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
        Schema::create('fee_rule_line_of_business', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_of_business_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('municipal_configuration');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['fee_rule_id', 'line_of_business_id'], 'fee_rule_lob_unique');
        });

        Schema::create('fee_rule_office_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_rule_id')->constrained()->cascadeOnDelete();
            $table->string('office_code');
            $table->string('office_label');
            $table->string('source')->default('municipal_configuration');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['fee_rule_id', 'office_code']);
            $table->index('office_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_rule_office_assignments');
        Schema::dropIfExists('fee_rule_line_of_business');
    }
};
