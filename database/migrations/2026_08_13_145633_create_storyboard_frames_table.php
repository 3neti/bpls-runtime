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
        Schema::create('storyboard_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storyboard_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->text('dialogue')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->default(5);
            $table->timestamps();

            $table->unique(['storyboard_id', 'position']);
            $table->index('storyboard_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storyboard_frames');
    }
};
