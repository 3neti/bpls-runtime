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
        Schema::table('business_permit_evaluation_counter_checks', function (Blueprint $table) {
            $table->foreignId('assessment_id')
                ->nullable()
                ->unique()
                ->after('business_permit_evaluation_version_id')
                ->constrained()
                ->restrictOnDelete();
            $table->char('assessment_snapshot_hash', 64)->nullable()->after('assessment_id');
            $table->string('result')->nullable()->after('assessment_snapshot_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_permit_evaluation_counter_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_id');
            $table->dropColumn(['assessment_snapshot_hash', 'result']);
        });
    }
};
