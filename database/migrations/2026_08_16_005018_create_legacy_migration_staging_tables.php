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
        Schema::create('legacy_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('source_type');
            $table->string('baseline')->nullable();
            $table->string('archive_checksum', 64)->nullable();
            $table->json('provenance');
            $table->string('status')->default('registered');
            $table->timestamps();
        });

        Schema::create('legacy_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_source_id')->constrained()->restrictOnDelete();
            $table->string('run_reference');
            $table->string('manifest_schema_version');
            $table->string('manifest_checksum', 64);
            $table->string('status')->default('staging');
            $table->unsignedInteger('source_record_count')->default(0);
            $table->unsignedInteger('staged_record_count')->default(0);
            $table->unsignedInteger('exception_count')->default(0);
            $table->unsignedInteger('mapping_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_source_id', 'run_reference']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('legacy_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_source_id')->constrained()->restrictOnDelete();
            $table->string('dataset_key');
            $table->string('entity_type');
            $table->string('legacy_id');
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->string('status')->default('staged');
            $table->unsignedInteger('line_number');
            $table->timestamps();

            $table->unique(['legacy_import_batch_id', 'dataset_key', 'legacy_id']);
            $table->index(['legacy_source_id', 'entity_type', 'legacy_id']);
        });

        Schema::create('legacy_id_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_source_id')->constrained()->restrictOnDelete();
            $table->foreignId('legacy_import_batch_id')->constrained()->restrictOnDelete();
            $table->string('dataset_key');
            $table->string('entity_type');
            $table->string('legacy_id');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('status')->default('mapped');
            $table->text('mapping_basis');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legacy_source_id', 'dataset_key', 'legacy_id', 'target_type']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('migration_validation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('dataset_key')->nullable();
            $table->string('check_key');
            $table->string('status');
            $table->json('expected')->nullable();
            $table->json('actual')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();

            $table->index(['legacy_import_batch_id', 'status']);
        });

        Schema::create('migration_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dataset_key')->nullable();
            $table->unsignedInteger('line_number')->nullable();
            $table->string('code');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->text('message');
            $table->json('context')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['legacy_import_batch_id', 'status', 'severity']);
            $table->index(['code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_exceptions');
        Schema::dropIfExists('migration_validation_results');
        Schema::dropIfExists('legacy_id_mappings');
        Schema::dropIfExists('legacy_records');
        Schema::dropIfExists('legacy_import_batches');
        Schema::dropIfExists('legacy_sources');
    }
};
