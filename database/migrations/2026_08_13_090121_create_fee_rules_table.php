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
        Schema::create('fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_of_business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->index();
            $table->string('name');
            $table->string('category')->index();
            $table->string('scope')->index();
            $table->string('calculation_type')->index();
            $table->string('basis')->default('none');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->unsignedInteger('rate_basis_points')->nullable();
            $table->date('effective_from')->index();
            $table->date('effective_until')->nullable()->index();
            $table->text('legal_basis')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('legacy_source_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['code', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_rules');
    }
};
