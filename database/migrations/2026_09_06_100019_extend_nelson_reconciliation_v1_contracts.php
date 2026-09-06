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
        Schema::table('permit_applications', function (Blueprint $table) {
            $table->text('business_activity_description')->nullable()->after('application_year');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('barangay_psgc_code', 10)->nullable()->after('barangay')->index();
        });

        Schema::table('permit_application_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('media_id')->nullable()->after('uploaded_by_id')->index();
            $table->string('document_type', 80)->nullable()->after('label')->index();
            $table->unsignedInteger('version')->default(1)->after('document_type');
            $table->string('checksum_sha256', 64)->nullable()->after('size_bytes');
            $table->timestamp('removed_at')->nullable()->after('uploaded_at')->index();
        });

        Schema::table('collection_allocations', function (Blueprint $table) {
            $table->foreignId('receipt_id')->nullable()->after('payment_schedule_line_id')->constrained()->nullOnDelete();
            $table->string('receipt_group_key')->default('municipal_consolidated')->after('receipt_id')->index();
            $table->string('receipt_group_label')->default('Municipal Collection')->after('receipt_group_key');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['treasury_collection_id']);
            $table->string('receipt_group_key')->default('municipal_consolidated')->after('treasury_collection_id');
            $table->string('receipt_group_label')->default('Municipal Collection')->after('receipt_group_key');
            $table->string('series')->nullable()->after('receipt_number');
            $table->unique(['treasury_collection_id', 'receipt_group_key'], 'receipt_collection_group_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique('receipt_collection_group_unique');
            $table->dropColumn(['receipt_group_key', 'receipt_group_label', 'series']);
            $table->unique('treasury_collection_id');
        });

        Schema::table('collection_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_id');
            $table->dropColumn(['receipt_group_key', 'receipt_group_label']);
        });

        Schema::table('permit_application_documents', function (Blueprint $table) {
            $table->dropColumn(['media_id', 'document_type', 'version', 'checksum_sha256', 'removed_at']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('barangay_psgc_code');
        });

        Schema::table('permit_applications', function (Blueprint $table) {
            $table->dropColumn('business_activity_description');
        });
    }
};
