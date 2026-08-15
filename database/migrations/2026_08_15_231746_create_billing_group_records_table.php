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
        Schema::create('billing_group_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('draft_reference')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('description')->nullable();
            $table->date('record_date')->nullable();
            $table->string('payor_name')->nullable();
            $table->json('field_values')->nullable();
            $table->json('schema_snapshot');
            $table->json('source_snapshot');
            $table->timestamps();

            $table->index(['billing_group_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_group_records');
    }
};
