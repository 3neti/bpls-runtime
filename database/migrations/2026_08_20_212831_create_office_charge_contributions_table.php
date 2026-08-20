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
        Schema::create('office_charge_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('office_code')->index();
            $table->string('office_label');
            $table->boolean('is_applicable')->default(true);
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->string('semantic_classification')->default('provisional_uat')->index();
            $table->json('source_snapshot');
            $table->timestamps();

            $table->unique(['permit_application_id', 'office_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_charge_contributions');
    }
};
