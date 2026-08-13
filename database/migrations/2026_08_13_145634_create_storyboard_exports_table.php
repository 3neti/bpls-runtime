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
        Schema::create('storyboard_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storyboard_id')->constrained()->cascadeOnDelete();
            $table->string('format');
            $table->string('status')->default('pending');
            $table->string('path')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['storyboard_id', 'format']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storyboard_exports');
    }
};
