<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rule_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_rule_reconciliation_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('review_reference', 1000);
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rule_reviews');
    }
};
