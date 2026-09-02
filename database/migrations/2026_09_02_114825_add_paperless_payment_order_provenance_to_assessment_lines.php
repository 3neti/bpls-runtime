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
        Schema::table('assessment_lines', function (Blueprint $table) {
            $table->foreignId('paperless_payment_order_line_id')
                ->nullable()
                ->after('business_permit_evaluation_item_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unique(
                ['assessment_id', 'paperless_payment_order_line_id'],
                'assessment_payment_order_line_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_lines', function (Blueprint $table) {
            $table->dropUnique('assessment_payment_order_line_unique');
            $table->dropConstrainedForeignId('paperless_payment_order_line_id');
        });
    }
};
