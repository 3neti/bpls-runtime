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
        Schema::create('bplo_routing_determinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('determined_by_id')->constrained('users')->restrictOnDelete();
            $table->text('situational_context');
            $table->json('application_facts_snapshot');
            $table->timestamp('determined_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bplo_routing_determinations');
    }
};
