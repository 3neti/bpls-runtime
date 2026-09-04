<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->char('currency', 3)->default('PHP')->after('total_amount_cents');
            $table->json('assessment_price_input_snapshot')->nullable()->after('source_snapshot');
            $table->char('assessment_price_input_fingerprint', 64)->nullable()->after('assessment_price_input_snapshot');
            $table->json('price_report_snapshot')->nullable()->after('assessment_price_input_fingerprint');
            $table->char('price_report_fingerprint', 64)->nullable()->after('price_report_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn([
                'currency',
                'assessment_price_input_snapshot',
                'assessment_price_input_fingerprint',
                'price_report_snapshot',
                'price_report_fingerprint',
            ]);
        });
    }
};
