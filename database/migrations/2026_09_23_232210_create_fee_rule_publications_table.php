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
        Schema::create('fee_rule_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_rule_revision_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('published_by_id')->constrained('users')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64);
            $table->timestamp('published_at');
            $table->unique(['fee_rule_id', 'effective_from']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_rule_publications');
    }
};
