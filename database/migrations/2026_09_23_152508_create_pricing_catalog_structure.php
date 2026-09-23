<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_charge_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('pricing_charge_groups')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('pricing_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('dimension', 50);
            $table->unsignedTinyInteger('decimal_places');
            $table->timestamps();
        });
        Schema::create('pricing_charge_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->foreignId('pricing_charge_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fee_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pricing_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_charge_items');
        Schema::dropIfExists('pricing_units');
        Schema::dropIfExists('pricing_charge_groups');
    }
};
