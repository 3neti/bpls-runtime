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
        Schema::create('bplo_routing_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('routing_determination_id')->nullable()->unique()->constrained('bplo_routing_determinations')->nullOnDelete();
            $table->string('profile_version');
            $table->json('profile_keys');
            $table->string('status')->default('awaiting_confirmation');
            $table->text('situational_context');
            $table->json('suggested_work');
            $table->json('application_facts_snapshot');
            $table->timestamp('lodged_at');
            $table->timestamp('review_due_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'review_due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bplo_routing_suggestions');
    }
};
