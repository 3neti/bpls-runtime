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
        Schema::create('legacy_mapping_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_mapping_plan_id')->constrained()->restrictOnDelete();
            $table->string('run_reference');
            $table->string('selection_hash', 64);
            $table->string('status')->default('executing');
            $table->unsignedInteger('selected_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('linked_count')->default(0);
            $table->unsignedInteger('reused_count')->default(0);
            $table->unsignedInteger('mapping_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_mapping_plan_id', 'run_reference']);
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_mapping_executions');
    }
};
