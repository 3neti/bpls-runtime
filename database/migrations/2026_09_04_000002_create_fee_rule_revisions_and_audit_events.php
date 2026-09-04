<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_rule_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_rule_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('proposed')->index();
            $table->char('currency', 3)->default('PHP');
            $table->unsignedBigInteger('previous_amount_minor')->nullable();
            $table->unsignedBigInteger('proposed_amount_minor')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('reason');
            $table->text('authority');
            $table->foreignId('proposed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at');
            $table->timestamp('activated_at')->nullable();
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['fee_rule_id', 'version']);
        });

        Schema::create('fee_rule_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_rule_revision_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['fee_rule_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_rule_audit_events');
        Schema::dropIfExists('fee_rule_revisions');
    }
};
