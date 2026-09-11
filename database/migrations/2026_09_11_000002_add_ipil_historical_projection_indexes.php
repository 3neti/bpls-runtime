<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipil_historical_businesses', function (Blueprint $table) {
            $table->index('ipil_historical_owner_id', 'ipil_hist_business_owner_idx');
        });
        Schema::table('ipil_historical_applications', function (Blueprint $table) {
            $table->index('ipil_historical_owner_id', 'ipil_hist_application_owner_idx');
            $table->index('ipil_historical_business_id', 'ipil_hist_application_business_idx');
        });
        Schema::table('ipil_historical_classifications', function (Blueprint $table) {
            $table->index('ipil_historical_application_id', 'ipil_hist_classification_app_idx');
        });
        Schema::table('ipil_historical_measurements', function (Blueprint $table) {
            $table->index('ipil_historical_application_id', 'ipil_hist_measurement_app_idx');
        });
        Schema::table('ipil_historical_payment_schedules', function (Blueprint $table) {
            $table->index('ipil_historical_application_id', 'ipil_hist_schedule_app_idx');
        });
        Schema::table('ipil_historical_payments', function (Blueprint $table) {
            $table->index('ipil_historical_application_id', 'ipil_hist_payment_app_idx');
            $table->index('ipil_historical_payment_schedule_id', 'ipil_hist_payment_schedule_idx');
        });
        Schema::table('ipil_historical_permit_claims', function (Blueprint $table) {
            $table->index('ipil_historical_application_id', 'ipil_hist_permit_app_idx');
            $table->index('ipil_historical_business_id', 'ipil_hist_permit_business_idx');
            $table->index('ipil_historical_owner_id', 'ipil_hist_permit_owner_idx');
        });
        Schema::table('ipil_historical_clearance_claims', function (Blueprint $table) {
            $table->index('ipil_historical_application_id', 'ipil_hist_clearance_app_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ipil_historical_clearance_claims', fn (Blueprint $table) => $table->dropIndex('ipil_hist_clearance_app_idx'));
        Schema::table('ipil_historical_permit_claims', function (Blueprint $table) {
            $table->dropIndex('ipil_hist_permit_app_idx');
            $table->dropIndex('ipil_hist_permit_business_idx');
            $table->dropIndex('ipil_hist_permit_owner_idx');
        });
        Schema::table('ipil_historical_payments', function (Blueprint $table) {
            $table->dropIndex('ipil_hist_payment_app_idx');
            $table->dropIndex('ipil_hist_payment_schedule_idx');
        });
        Schema::table('ipil_historical_payment_schedules', fn (Blueprint $table) => $table->dropIndex('ipil_hist_schedule_app_idx'));
        Schema::table('ipil_historical_measurements', fn (Blueprint $table) => $table->dropIndex('ipil_hist_measurement_app_idx'));
        Schema::table('ipil_historical_classifications', fn (Blueprint $table) => $table->dropIndex('ipil_hist_classification_app_idx'));
        Schema::table('ipil_historical_applications', function (Blueprint $table) {
            $table->dropIndex('ipil_hist_application_owner_idx');
            $table->dropIndex('ipil_hist_application_business_idx');
        });
        Schema::table('ipil_historical_businesses', fn (Blueprint $table) => $table->dropIndex('ipil_hist_business_owner_idx'));
    }
};
