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
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('ownership_type', 40)->nullable()->index();
            $table->string('organization_name')->nullable();
            $table->string('occupancy', 20)->nullable()->index();
            $table->string('building_name')->nullable();
            $table->string('property_index_number')->nullable()->index();
            $table->decimal('business_area_square_meters', 12, 2)->nullable();
            $table->unsignedInteger('male_employee_count')->nullable();
            $table->unsignedInteger('female_employee_count')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('email')->nullable()->index();
            $table->date('established_on')->nullable();
            $table->date('started_on')->nullable();
            $table->date('registered_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'ownership_type',
                'organization_name',
                'occupancy',
                'building_name',
                'property_index_number',
                'business_area_square_meters',
                'male_employee_count',
                'female_employee_count',
                'contact_number',
                'email',
                'established_on',
                'started_on',
                'registered_on',
            ]);
        });
    }
};
