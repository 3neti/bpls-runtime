<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_cleanroom_registration_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lifecycle_cleanroom_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('claimed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lifecycle_cleanroom_ceremony_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lifecycle_cleanroom_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('permit_application_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('actor_key')->nullable();
            $table->string('event');
            $table->string('route_name')->nullable();
            $table->string('canonical_step')->nullable();
            $table->unsignedInteger('completed_stage_count')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['lifecycle_cleanroom_run_id', 'sequence'], 'cleanroom_ceremony_event_sequence');
            $table->index(['lifecycle_cleanroom_run_id', 'event'], 'cleanroom_ceremony_event_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_cleanroom_ceremony_events');
        Schema::dropIfExists('lifecycle_cleanroom_registration_invitations');
    }
};
