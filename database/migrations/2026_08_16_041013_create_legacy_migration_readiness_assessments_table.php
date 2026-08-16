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
        Schema::create('legacy_migration_readiness_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_batch_id')->constrained()->restrictOnDelete();
            $table->string('run_reference');
            $table->string('assessor_version');
            $table->string('dependency_snapshot_hash', 64);
            $table->string('status')->default('assessing');
            $table->boolean('rehearsal_ready')->default(false);
            $table->boolean('cutover_ready')->default(false);
            $table->unsignedInteger('check_count')->default(0);
            $table->unsignedInteger('passed_count')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->json('checks')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_import_batch_id', 'run_reference'], 'legacy_migration_readiness_batch_run_unique');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_migration_readiness_assessments');
    }
};
