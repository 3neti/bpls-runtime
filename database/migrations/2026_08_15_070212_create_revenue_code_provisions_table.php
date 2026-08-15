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
        Schema::create('revenue_code_provisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('source_id');
            $table->string('section_reference');
            $table->string('title');
            $table->string('provision_type');
            $table->text('evidence_summary');
            $table->string('reconciliation_status');
            $table->text('reconciliation_notes')->nullable();
            $table->date('effective_from');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'section_reference']);
            $table->index(['reconciliation_status', 'provision_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_code_provisions');
    }
};
