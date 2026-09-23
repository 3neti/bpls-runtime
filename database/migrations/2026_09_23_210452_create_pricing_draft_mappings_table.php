<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_draft_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pricing_definition_draft_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignId('pricing_charge_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('revenue_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('evidence_reference', 1000);
            $table->char('evidence_sha256', 64);
            $table->text('rationale');
            $table->json('identity_snapshot');
            $table->timestamps();
            $table->unique(['pricing_definition_draft_id', 'revision'], 'pricing_draft_mapping_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_draft_mappings');
    }
};
