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
        Schema::create('bplo_routing_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bplo_routing_determination_id')->constrained()->cascadeOnDelete();
            $table->string('office_code');
            $table->string('office_label');
            $table->text('situational_reason');
            $table->text('required_work');
            $table->foreignId('permit_application_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('line_of_business_id')->nullable()->constrained()->nullOnDelete();
            $table->json('context_snapshot');
            $table->timestamps();

            $table->unique(['bplo_routing_determination_id', 'office_code', 'permit_application_line_id'], 'bplo_route_work_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bplo_routing_works');
    }
};
