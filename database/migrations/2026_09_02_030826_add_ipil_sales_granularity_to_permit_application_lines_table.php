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
        Schema::table('permit_application_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('essential_gross_sales_cents')->nullable()->after('declared_gross_sales_cents');
            $table->unsignedBigInteger('non_essential_gross_sales_cents')->nullable()->after('essential_gross_sales_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permit_application_lines', function (Blueprint $table) {
            $table->dropColumn(['essential_gross_sales_cents', 'non_essential_gross_sales_cents']);
        });
    }
};
