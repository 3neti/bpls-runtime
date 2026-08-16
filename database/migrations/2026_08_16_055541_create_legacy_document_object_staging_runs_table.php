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
        Schema::create('legacy_document_object_staging_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_batch_id');
            $table->string('run_reference');
            $table->string('manifest_schema_version');
            $table->string('manifest_checksum', 64);
            $table->string('status')->default('staging');
            $table->unsignedInteger('object_count')->default(0);
            $table->unsignedInteger('staged_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('legacy_import_batch_id', 'legacy_doc_stage_batch_fk')
                ->references('id')->on('legacy_import_batches')->restrictOnDelete();
            $table->unique(['legacy_import_batch_id', 'run_reference'], 'legacy_doc_stage_batch_run_unique');
            $table->index(['status', 'started_at'], 'legacy_doc_stage_status_started_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_document_object_staging_runs');
    }
};
