<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_definition_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100);
            $table->unsignedInteger('revision');
            $table->string('method', 40);
            $table->string('basis', 100);
            $table->string('unit_code', 50)->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3);
            // Evidence only: not an accepted operational account mapping.
            $table->string('revenue_account_code', 100)->nullable();
            $table->char('source_sha256', 64);
            $table->string('source_locator', 1000);
            $table->json('source_evidence');
            $table->timestamps();
            $table->unique(['code', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_definition_drafts');
    }
};
