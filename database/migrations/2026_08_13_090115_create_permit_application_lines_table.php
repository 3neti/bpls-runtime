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
        Schema::create('permit_application_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_of_business_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('declared_gross_sales_cents')->default(0);
            $table->unsignedBigInteger('capital_investment_cents')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->date('started_on')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['permit_application_id', 'line_of_business_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permit_application_lines');
    }
};
