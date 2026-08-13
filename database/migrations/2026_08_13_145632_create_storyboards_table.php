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
        Schema::create('storyboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_id')->constrained('users');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index('created_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storyboards');
    }
};
