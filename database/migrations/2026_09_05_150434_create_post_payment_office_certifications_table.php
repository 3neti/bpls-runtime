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
        Schema::create('post_payment_office_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bplo_routing_determination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('certified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('office_code');
            $table->string('office_label');
            $table->json('routing_work_ids');
            $table->string('status')->default('pending')->index();
            $table->string('result')->nullable();
            $table->text('remarks')->nullable();
            $table->json('evidence');
            $table->timestamp('certified_at')->nullable()->index();
            $table->string('semantic_classification')->default('synthetic_only')->index();
            $table->boolean('production_authority')->default(false);
            $table->timestamps();

            $table->unique(['permit_application_id', 'office_code'], 'post_payment_office_certification_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_payment_office_certifications');
    }
};
