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
        Schema::create('legacy_declaration_mapping_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_declaration_mapping_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_record_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_index');
            $table->foreignId('legacy_line_of_business_reconciliation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_of_business_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('projection_hash', 64);
            $table->json('reasons')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_declaration_mapping_plan_id', 'legacy_record_id', 'line_index'], 'legacy_declaration_plan_record_line_unique');
            $table->index(['legacy_declaration_mapping_plan_id', 'status'], 'legacy_declaration_plan_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_declaration_mapping_proposals');
    }
};
