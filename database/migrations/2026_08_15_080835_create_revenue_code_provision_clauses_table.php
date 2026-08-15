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
        Schema::create('revenue_code_provision_clauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_code_provision_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('code')->unique();
            $table->string('clause_type');
            $table->text('source_text');
            $table->text('candidate_interpretation');
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->decimal('rate_basis_points', 10, 4)->nullable();
            $table->boolean('is_ceiling')->default(false);
            $table->string('reconciliation_status');
            $table->text('execution_blocker');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['revenue_code_provision_id', 'sequence']);
            $table->index(['revenue_code_provision_id', 'reconciliation_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_code_provision_clauses');
    }
};
