<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_permit_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('business_permit_evaluation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_permit_evaluation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->char('fingerprint', 64);
            $table->string('reason');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['business_permit_evaluation_id', 'sequence'], 'evaluation_version_sequence_unique');
        });

        Schema::create('business_permit_evaluation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_permit_evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('item_type');
            $table->string('responsible_party');
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_confirmation')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['business_permit_evaluation_id', 'key'], 'evaluation_item_key_unique');
        });

        Schema::create('business_permit_evaluation_item_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_permit_evaluation_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_permit_evaluation_version_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('applicability');
            $table->json('value')->nullable();
            $table->string('source_classification');
            $table->string('idempotency_key')->nullable()->unique();
            $table->char('dependency_fingerprint', 64)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(
                ['business_permit_evaluation_item_id', 'business_permit_evaluation_version_id'],
                'evaluation_item_version_unique',
            );
        });

        Schema::create('business_permit_evaluation_counter_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_permit_evaluation_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('checked_by_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->text('evidence_provenance');
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('business_permit_evaluation_version_id')
                ->nullable()
                ->after('permit_application_id')
                ->constrained()
                ->restrictOnDelete();
            $table->char('business_permit_evaluation_fingerprint', 64)
                ->nullable()
                ->after('business_permit_evaluation_version_id');
        });

        Schema::table('assessment_lines', function (Blueprint $table) {
            $table->foreignId('business_permit_evaluation_item_id')
                ->nullable()
                ->after('fee_rule_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unique(
                ['assessment_id', 'business_permit_evaluation_item_id'],
                'assessment_evaluation_item_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('assessment_lines', function (Blueprint $table) {
            $table->dropUnique('assessment_evaluation_item_unique');
            $table->dropConstrainedForeignId('business_permit_evaluation_item_id');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_permit_evaluation_version_id');
            $table->dropColumn('business_permit_evaluation_fingerprint');
        });

        Schema::dropIfExists('business_permit_evaluation_counter_checks');
        Schema::dropIfExists('business_permit_evaluation_item_revisions');
        Schema::dropIfExists('business_permit_evaluation_items');
        Schema::dropIfExists('business_permit_evaluation_versions');
        Schema::dropIfExists('business_permit_evaluations');
    }
};
