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
        Schema::create('billing_group_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_group_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('field_type');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->unsignedSmallInteger('sort_order');
            $table->json('options')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('default_value')->nullable();
            $table->timestamps();

            $table->unique(['billing_group_id', 'key']);
            $table->unique(['billing_group_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_group_fields');
    }
};
